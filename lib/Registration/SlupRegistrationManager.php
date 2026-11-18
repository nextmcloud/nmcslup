<?php

namespace OCA\NextMagentaCloudSlup\Registration;

use OCA\NextMagentaCloudSlup\AppInfo\Application;
use OCP\Http\Client\IClientService;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;
use SoapClient;

/**
 * Configure the SLUP connection with:
 * ```
 * sudo -u www-data php /var/www/nextcloud/occ config:app:set nmcslup slupid --value <value handed over by slup team>
 * sudo -u www-data php /var/www/nextcloud/occ config:app:set nmcslup slupsecret --value <value handed over by slup team>
 * sudo -u www-data php /var/www/nextcloud/occ config:app:set nmcslup slupgwendpoint --value <value handed over by slup team>
 * sudo -u www-data php /var/www/nextcloud/occ config:app:set nmcslup slupreceiverurl --value <value handed over by slup team>
 * sudo -u www-data php /var/www/nextcloud/occ config:app:set nmcslup local_cert --value <value handed over by slup team>
 * ```
 */
class SlupRegistrationManager {
	// Keep the SLUP token for at most five days. Normally SLUP disconnects daily and refreshes it.
	public const CACHE_MAX_TIME = 5 * 24 * 3600;

	// Minimum delay between HALFOPEN reconnect attempts is five minutes.
	public const CIRCUIT_HALFOPEN_DELAY = 300;
	public const CIRCUIT_STATE_MULTIPLIER = 12;

	public const CIRCUIT_UNDEFINED = 'undefined';
	public const CIRCUIT_OPEN = 'open';
	public const CIRCUIT_HALFOPEN = 'halfopen';
	public const CIRCUIT_CLOSED = 'closed';

	/** @var LoggerInterface */
	protected $logger;

	/** @var IURLGenerator */
	protected $urlGenerator;

	/** @var IClientService */
	protected $clientService;

	/** @var IConfig */
	protected $config;

	/** @var ICacheFactory */
	protected $cacheFactory;

	/** Local path to the SLUP WSDL file. */
	protected $wsdlPath;

	/** PEM file containing the HTTPS client certificate and private key. */
	protected $localCert;

	/** @var bool */
	protected $forceDisconnect = false;

	/** Distributed cache used for token and circuit state. */
	protected $cache;

	/** SOAP client. Replaceable for unit tests. */
	protected $soapClient;

	/** Seconds until the next HALFOPEN attempt. */
	private $controlIntervalSec;

	public function __construct(LoggerInterface $logger, IURLGenerator $urlGenerator, IClientService $clientService, IConfig $config, ICacheFactory $cacheFactory) {
		$this->logger = $logger;
		$this->urlGenerator = $urlGenerator;
		$this->clientService = $clientService;
		$this->config = $config;
		$this->wsdlPath = dirname(__FILE__) . "/slupService.wsdl";
		$this->localCert = $this->config->getAppValue('nmcslup', 'local_cert', dirname(__FILE__) . '/certkey.pem');

		// Use a distributed cache so circuit state and tokens are shared across cluster nodes.
		// TTL handles cleanup without depending on node shutdown events.
		$this->cache = $cacheFactory->createDistributed("nmcslup");
		$this->soapClient = null;
		$controlIntervalSec = intval($this->config->getAppValue('nmcslup', 'slupcontrolintv', strval(self::CIRCUIT_HALFOPEN_DELAY)));
		$this->setControlInterval($controlIntervalSec);
	}

	// ----- public service methods ------

	// ----- unit test support, do not use in production code -----

	public function getWsdlPath() {
		return \realpath($this->wsdlPath);
	}

	// Allows replacing the delayed SoapClient instance with a mock.
	public function replaceSoapClient($soapClient) {
		$this->soapClient = $soapClient;
	}

	public function resetRecvCount() {
		$this->cache->set("recvmsgcount", 0, self::CIRCUIT_STATE_MULTIPLIER * $this->getControlInterval());
	}

	public function incrementRecvCount() {
		$this->cache->inc("recvmsgcount");

		// A HALFOPEN connection is considered successful only after the first message arrives.
		if ($this->isCircuitHalfOpen()) {
			$this->circuitClosed();
		}
	}

	public function getRecvCount() {
		return $this->cache->get("recvmsgcount");
	}

	// ----- token handling -----

	/**
	 * A forced reconnect is equivalent to losing the current token.
	 */
	public function forceReconnect() {
		return $this->cache->remove('token');
	}

	public function isValidToken(string $token) {
		if ($this->cache->hasKey("token")) {
			$lastGatewayToken = $this->cache->get('token');
			return !is_null($lastGatewayToken) && $lastGatewayToken == $token;
		}

		return false;
	}

	public function hasToken(): bool {
		return $this->cache->hasKey("token");
	}

	public function setToken(string $token) {
		$this->cache->set("token", $token, self::CACHE_MAX_TIME);
	}

	public function getToken() {
		if ($this->cache->hasKey("token")) {
			return $this->cache->get('token');
		}

		return null;
	}

	public function clearToken() {
		return $this->cache->remove('token');
	}

	// ----- circuit breaker handling -----

	/** Set the reconnect interval to at least five minutes. */
	public function setControlInterval(int $controlIntervalSec): void {
		$this->controlIntervalSec = $controlIntervalSec < 300 ? 300 : $controlIntervalSec;
	}

	public function getControlInterval(): int {
		return $this->controlIntervalSec;
	}

	/** Primarily used by unit tests. */
	public function forceCircuitUndefined() {
		return $this->cache->remove('circuitstate');
	}

	public function isCircuitUndefined(): bool {
		return !$this->cache->hasKey("circuitstate");
	}

	public function isCircuitOpen(): bool {
		$circuitState = $this->cache->get('circuitstate');
		return !is_null($circuitState) && $circuitState == self::CIRCUIT_OPEN;
	}

	public function isCircuitHalfOpen(): bool {
		$circuitState = $this->cache->get('circuitstate');
		return !is_null($circuitState) && $circuitState == self::CIRCUIT_HALFOPEN;
	}

	public function isCircuitClosed(): bool {
		$circuitState = $this->cache->get('circuitstate');
		return !is_null($circuitState) && $circuitState == self::CIRCUIT_CLOSED;
	}

	public function circuitState(): string {
		if ($this->isCircuitUndefined()) {
			return self::CIRCUIT_UNDEFINED;
		}

		return $this->cache->get('circuitstate');
	}

	/**
	 * OPEN pauses normal connection attempts until the next HALFOPEN trial.
	 * Nextcloud background jobs have a minimum scheduling interval of five minutes.
	 */
	public function circuitOpen($delay = null) {
		// An external caller may increase the reconnect delay.
		if (!is_null($delay)) {
			$this->setControlInterval($delay);
		}

		if (!$this->isCircuitOpen()) {
			$this->logger->info("SLUP switched to OPEN");
		}

		// Refresh the state TTL on every call.
		$this->cache->set("circuitstate", self::CIRCUIT_OPEN, self::CIRCUIT_STATE_MULTIPLIER * $this->getControlInterval());
		// TODO: expose circuit state to monitoring
	}

	/**
	 * HALFOPEN performs a controlled reconnect attempt.
	 */
	public function circuitHalfOpen() {
		$this->logger->info("SLUP switched to HALFOPEN");
		$this->cache->set("circuitstate", self::CIRCUIT_HALFOPEN, self::CIRCUIT_STATE_MULTIPLIER * $this->getControlInterval());
		// TODO: expose circuit state to monitoring

		if (!$this->registerSlup()) {
			$this->circuitOpen();
		}
	}

	/**
	 * CLOSED means the SLUP connection is operating normally.
	 */
	public function circuitClosed() {
		if (!$this->isCircuitClosed()) {
			$this->logger->info("SLUP circuit CLOSED, normal business");
		}

		$this->cache->set("circuitstate", self::CIRCUIT_CLOSED, self::CIRCUIT_STATE_MULTIPLIER * $this->getControlInterval());
		// TODO: expose circuit state to monitoring
	}

	/**
	 * Register with SLUP and store the returned token.
	 * Returns true when connected or already registered.
	 */
	public function registerSlup($wait = 5) {
		// Do not reconnect while an active registration is already confirmed.
		if ($this->isCircuitClosed()) {
			return true;
		}

		$slupid = $this->config->getAppValue('nmcslup', 'slupid', "10TVL0SLUP0000004901NEXTMAGENTACLOUD0000");
		$slupsecret = $this->config->getAppValue('nmcslup', 'slupsecret', "<no default secret>");
		$slupGwEndpoint = $this->config->getAppValue('nmcslup', 'slupgwendpoint', 'https://slup2soap00.idm.ver.sul.t-online.de/slupService/');

		try {
			// First registration attempt.
			$token = $this->sendRegistration($slupGwEndpoint, $slupid, $slupsecret);
			$this->setToken($token);
			return true;
		} catch (\SoapFault $sf) {
			$slupDetailCode = $this->getSlupSoapFaultDetail($sf, 'code', 'none');
			$slupDetailMessage = $this->getSlupSoapFaultDetail($sf, 'message', 'none');

			// A007 means SLUP already has an active registration.
			if ($slupDetailCode === 'A007') {
				return true;
			}

			// Retry transient HTTP SOAP faults once before opening the circuit.
			if ((string)$sf->faultcode !== 'HTTP') {
				$this->logger->error('SLUP try1 SOAP fault, giving up', [
					'faultCode' => (string)$sf->faultcode,
					'faultString' => (string)$sf->faultstring,
					'slupDetailCode' => $slupDetailCode,
					'slupDetailMessage' => $slupDetailMessage,
					'app' => Application::APP_ID,
				]);
				$this->clearToken();
				return false;
			}

			$this->logger->warning('SLUP try1 HTTP SOAP fault, retrying once', [
				'faultCode' => (string)$sf->faultcode,
				'faultString' => (string)$sf->faultstring,
				'slupDetailCode' => $slupDetailCode,
				'slupDetailMessage' => $slupDetailMessage,
				'app' => Application::APP_ID,
			]);
		} catch (\Throwable $e) {
			$this->logger->error('SLUP try1 critical fault, giving up', [
				'exceptionClass' => get_class($e),
				'exceptionCode' => $e->getCode(),
				'exceptionMessage' => $e->getMessage(),
				'exceptionFile' => $e->getFile(),
				'exceptionLine' => $e->getLine(),
				'app' => Application::APP_ID,
			]);
			$this->clearToken();
			return false;
		}

		// Short delay before the single retry.
		sleep($wait);

		try {
			$token = $this->sendRegistration($slupGwEndpoint, $slupid, $slupsecret);
			$this->setToken($token);
			return true;
		} catch (\SoapFault $sf) {
			$slupDetailCode = $this->getSlupSoapFaultDetail($sf, 'code', 'none');
			$slupDetailMessage = $this->getSlupSoapFaultDetail($sf, 'message', 'none');

			// A007 is also successful on the retry.
			if ($slupDetailCode === 'A007') {
				return true;
			}

			$this->logger->error('SLUP try2 SOAP fault, giving up', [
				'faultCode' => (string)$sf->faultcode,
				'faultString' => (string)$sf->faultstring,
				'slupDetailCode' => $slupDetailCode,
				'slupDetailMessage' => $slupDetailMessage,
				'app' => Application::APP_ID,
			]);
			$this->clearToken();
			return false;
		} catch (\Throwable $e) {
			$this->logger->error('SLUP try2 critical fault, giving up', [
				'exceptionClass' => get_class($e),
				'exceptionCode' => $e->getCode(),
				'exceptionMessage' => $e->getMessage(),
				'exceptionFile' => $e->getFile(),
				'exceptionLine' => $e->getLine(),
				'app' => Application::APP_ID,
			]);
			$this->clearToken();
			return false;
		}
	}

	// ----- implementation details ------

	/**
	 * Safely read a value from the optional SLUP SOAP fault detail.
	 * Some SOAP faults contain no detail object at all.
	 */
	public function getSlupSoapFaultDetail(\SoapFault $fault, string $name, string $default = ''): string {
		if (!property_exists($fault, 'detail') || !is_object($fault->detail)) {
			return $default;
		}

		if (!property_exists($fault->detail, 'FaultResponse') || !is_object($fault->detail->FaultResponse)) {
			return $default;
		}

		if (!property_exists($fault->detail->FaultResponse, $name)) {
			return $default;
		}

		return (string)$fault->detail->FaultResponse->{$name};
	}

	/**
	 * Register the callback URL with the SLUP SOAP gateway.
	 * Public to allow direct unit testing.
	 */
	public function sendRegistration($gwendpoint, $appid, $appsec, $trace = false) {
		$receiverEndpoint = $this->config->getAppValue('nmcslup', 'slupreceiverurl', 'https://magentacloud.de/index.php/apps/nmcslup/api/1.0/slup');
		$soapClient = null;

		$this->logger->debug($appid . ": Register at " . $gwendpoint . " -cb-> " . $receiverEndpoint);

		try {
			$this->logger->debug("SLUP try to connect");

			if (is_null($this->soapClient)) {
				$this->logger->debug("Creating new SoapClient");

				// Create the SOAP client lazily so tests can replace it with a mock.
				libxml_set_external_entity_loader(static function ($public, $system, $context) {
					return $system;
				});

				$soapClient = new SoapClient($this->wsdlPath, [
					'connection_timeout' => 20,
					'cache_wsdl' => 0,
					'trace' => $trace,
					'exceptions' => true,
					'location' => $gwendpoint,
					'local_cert' => $this->localCert,
				]);
			} else {
				$this->logger->debug("Using existing SoapClient");
				$soapClient = $this->soapClient;
			}

			// WS-Security UsernameToken namespace.
			$strWSSENS = "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd";

			// Build the WS-Security UsernameToken from SLUP ID and secret.
			$objSoapVarUser = new \SoapVar($appid, XSD_STRING, null, $strWSSENS, null, $strWSSENS);
			$objSoapVarPass = new \SoapVar($appsec, XSD_STRING, null, $strWSSENS, null, $strWSSENS);
			$objWSSEAuth = new SoapWSSEAuth($objSoapVarUser, $objSoapVarPass);
			$objSoapVarWSSEAuth = new \SoapVar($objWSSEAuth, SOAP_ENC_OBJECT, null, $strWSSENS, 'UsernameToken', $strWSSENS);
			$objWSSEToken = new SoapWSSEToken($objSoapVarWSSEAuth);
			$objSoapVarWSSEToken = new \SoapVar($objWSSEToken, SOAP_ENC_OBJECT, null, $strWSSENS, 'UsernameToken', $strWSSENS);
			$objSoapVarHeaderVal = new \SoapVar($objSoapVarWSSEToken, SOAP_ENC_OBJECT, null, $strWSSENS, 'Security', $strWSSENS);

			// mustUnderstand=true requires the gateway to process the security header.
			$objSoapVarWSSEHeader = new \SoapHeader($strWSSENS, 'Security', $objSoapVarHeaderVal, true);
			$soapClient->__setSoapHeaders([$objSoapVarWSSEHeader]);

			// Register our callback URL and receive the current SLUP token.
			$response = $soapClient->startSLUP2(['slupURL' => $receiverEndpoint]);

			if (is_null($response)) {
				throw new \Exception("Unknown SLUP NULL response, undefined state");
			}

			if ($response->SLUPreturncode == '0000') {
				$this->logger->debug("SLUP registration succeeded");
				return $response->token;
			}

			$this->logger->error('SLUP registration rejected', [
				'slupReturnCode' => (string)$response->SLUPreturncode,
				'slupDetail' => (string)$response->detail,
				'endpoint' => $gwendpoint,
				'receiverEndpoint' => $receiverEndpoint,
				'app' => Application::APP_ID,
			]);

			// Let the caller handle non-successful SLUP return codes.
			throw new SlupConnectException("[{$response->SLUPreturncode}] {$response->detail}", \hexdec($response->SLUPreturncode));
		} catch (\SoapFault $fault) {
			$slupDetailCode = $this->getSlupSoapFaultDetail($fault, 'code', 'none');
			$slupDetailMessage = $this->getSlupSoapFaultDetail($fault, 'message', 'none');

			// Log structured fault data only. Never log the SOAP request because it contains credentials.
			$context = [
				'faultCode' => (string)$fault->faultcode,
				'faultString' => (string)$fault->faultstring,
				'slupDetailCode' => $slupDetailCode,
				'slupDetailMessage' => $slupDetailMessage,
				'endpoint' => $gwendpoint,
				'receiverEndpoint' => $receiverEndpoint,
				'app' => Application::APP_ID,
			];

			// A007 means "already connected" and is not an actual failure.
			if ($slupDetailCode === 'A007') {
				$this->logger->debug('SLUP SOAP fault: already connected', $context);
			} else {
				$this->logger->error('SLUP SOAP fault', $context);
			}

			throw $fault;
		} catch (\Throwable $e) {
			// Include enough context to diagnose TLS, certificate and client-side failures.
			$this->logger->error('SLUP client error', [
				'exceptionClass' => get_class($e),
				'exceptionCode' => $e->getCode(),
				'exceptionMessage' => $e->getMessage(),
				'exceptionFile' => $e->getFile(),
				'exceptionLine' => $e->getLine(),
				'endpoint' => $gwendpoint,
				'receiverEndpoint' => $receiverEndpoint,
				'localCert' => $this->localCert,
				'localCertExists' => is_file($this->localCert),
				'localCertReadable' => is_readable($this->localCert),
				'app' => Application::APP_ID,
			]);

			throw $e;
		}
	}
}
