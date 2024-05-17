<?php

namespace OCA\NextMagentaCloudSlup\Registration;

use OCA\NextMagentaCloudSlup\AppInfo\Application;
use OCP\Http\Client\IClientService;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\ILogger;

use OCP\IURLGenerator;
use SoapClient;

/**
 * Please configure this app for SLUP connection endpoint with the following commands
 * ```
 * sudo -u www-data php /var/www/nextcloud/occ config:app:set nmcslup slupid --value <value handed over by slup team>
 * sudo -u www-data php /var/www/nextcloud/occ config:app:set nmcslup slupsecret --value <value handed over by slup team>
 * sudo -u www-data php /var/www/nextcloud/occ config:app:set nmcslup slupgwendpoint --value value handed over by slup team>
 * ```
 */
class SlupRegistrationManager {
	// persist at most 5 days in cache
	// usually, SLUP does a disconnect every day which refreshes cache
	// worst case, we force a reconnect after 5 days with empty token
	public const CACHE_MAX_TIME = 5 * 24 * 3600;

	// sec between HALFOPEN trials, minimum is 300sec
	public const CIRCUIT_HALFOPEN_DELAY = 300;
	public const CIRCUIT_STATE_MULTIPLIER = 12;

	public const CIRCUIT_UNDEFINED = 'undefined';
	public const CIRCUIT_OPEN = 'open';
	public const CIRCUIT_HALFOPEN = 'halfopen';
	public const CIRCUIT_CLOSED = 'closed';

	/** @var ILogger */
	protected $logger;

	/** @var IURLGenerator */
	protected $urlGenerator;

	/** @var IClientService */
	protected $clientService;

	/** @var IConfig */
	protected $config;

	/** @var ICacheFactory */
	protected $cacheFactory;

	/** local location of wsdl file */
	protected $wsdlPath;

	/** local path to a client certificate for use with HTTPS authentication. It must be a PEM encoded file which contains your certificate and private key. */
	protected $localCert;

	/** @var bool */
	protected $forceDisconnect = false;

	protected $cache;

	/** @val int */
	private $controlIntervalSec; // time to next half-open try

	/**
	 * @param ILogger $logger
	 * @param IURLGenerator $urlGenerator
	 * @param IClientService $clientService
	 * @param IConfig $config
	 * @param ICacheFactory $cachefactory
	 */
	public function __construct(ILogger $logger,
		IURLGenerator $urlGenerator,
		IClientService $clientService,
		IConfig $config,
		ICacheFactory $cacheFactory) {
		$this->logger = $logger;
		$this->urlGenerator = $urlGenerator;
		$this->clientService = $clientService;
		$this->config = $config;

		$this->wsdlPath = dirname(__FILE__) . "/slupService.wsdl";
		$this->localCert = $this->config->getAppValue('nmcslup', 'local_cert', dirname(__FILE__) . '/cert.pem');

		// in a scaled cluster, it is not a good idea to depend on
		// a shutdown procedure as you have to find out when last cluster
		// node goes down. It is easier to work with TTL and let
		// the cache cleanup itself after a time.
		$this->cache = $cacheFactory->createDistributed("nmcslup");

		$this->soapClient = null;
		// app configs only support string values
		$controlIntervalSec = intval($this->config->getAppValue('nmcslup', 'slupcontrolintv',
			strval(self::CIRCUIT_HALFOPEN_DELAY)));
		$this->setControlInterval($controlIntervalSec);
	}


	// ----- public service methods ------

	// ----- unittest support methods, do not use in production code -----
	public function getWsdlPath() {
		return \realpath($this->wsdlPath);
	}

	// this allows for a delayed replacement of the SoapClient with getWsdlPath
	// in constructor for mocking
	public function replaceSoapClient($soapClient) {
		$this->soapClient = $soapClient;
	}

	public function resetRecvCount() {
		$this->cache->set("recvmsgcount", 0, self::CIRCUIT_STATE_MULTIPLIER * $this->getControlInterval());
	}

	public function incrementRecvCount() {
		$this->cache->inc("recvmsgcount");

		//Invariant: Connection is only successful if the first message is received
		if($this->isCircuitHalfOpen()) {
			$this->circuitClosed();
		}
	}

	public function getRecvCount() {
		return $this->cache->get("recvmsgcount");
	}



	// ----- token handling -----

	/**
	 * forced reconnect behaves the same a lost token, so
	 * this is only a coding alias for clearing the token
	 */
	public function forceReconnect() {
		return $this->cache->remove('token');
	}

	public function isValidToken(string $token) {
		if ($this->cache->hasKey("token")) {
			$lastGatewayToken = $this->cache->get('token');
			return (!is_null($lastGatewayToken) && ($lastGatewayToken == $token));
		} else {
			return false;
		}
	}

	public function hasToken() : bool {
		return $this->cache->hasKey("token");
	}

	public function setToken(string $token) {
		$this->cache->set("token", $token, self::CACHE_MAX_TIME);
	}


	public function getToken() {
		if ($this->cache->hasKey("token")) {
			return $this->cache->get('token');
		} else {
			return null;
		}
	}

	public function clearToken() {
		return $this->cache->remove('token');
	}

	// ----- circuit breaker handling -----

	/** set control interval to at least 5 min */
	public function setControlInterval(int $controlIntervalSec) : void {
		$this->controlIntervalSec = ($controlIntervalSec < 300) ? 300 : $controlIntervalSec;
	}

	public function getControlInterval() : int {
		return $this->controlIntervalSec;
	}

	/** This method is primarily for test purposes */
	public function forceCircuitUndefined() {
		return $this->cache->remove('circuitstate');
	}

	public function isCircuitUndefined() : bool {
		return (!$this->cache->hasKey("circuitstate"));
	}

	public function isCircuitOpen() : bool {
		$circuitState = $this->cache->get('circuitstate');
		return (!is_null($circuitState) && ($circuitState == self::CIRCUIT_OPEN));
	}

	public function isCircuitHalfOpen() : bool {
		$circuitState = $this->cache->get('circuitstate');
		return (!is_null($circuitState) && ($circuitState == self::CIRCUIT_HALFOPEN));
	}

	public function isCircuitClosed() : bool {
		$circuitState = $this->cache->get('circuitstate');
		return (!is_null($circuitState) && ($circuitState == self::CIRCUIT_CLOSED));
	}

	public function circuitState() : string {
		if ($this->isCircuitUndefined()) {
			return self::CIRCUIT_UNDEFINED;
		} else {
			return $this->cache->get('circuitstate');
		}
	}

	/**
	 * While in open state, we let the partner system calm down
	 * and plan a background job for the next half-open trial
	 *
	 * Note that the minimal granularity of NextCloud scheduler
	 * is 5 min.
	 */
	public function circuitOpen($delay = null) {

		// the delay parameter could be used to enlarge delay artificially from outside
		// (not used yet)
		if (!is_null($delay)) {
			$this->setControlInterval($delay);
		}

		if (!$this->isCircuitOpen()) {
			// only set cache if clock is not ticking for an older open circuit
			$this->logger->info("SLUP switched to OPEN");
		}
		// always refresh state on call
		$this->cache->set("circuitstate", self::CIRCUIT_OPEN, self::CIRCUIT_STATE_MULTIPLIER * $this->getControlInterval());
		// TODO: signal state to monitoring
	}

	/**
	 * While in half-open state, system tries to reconnect
	 */
	public function circuitHalfOpen() {
		// duration of HALF_OPEN state is potentially undefined
		// but state is exited quickly, so TTL maximum should be ok
		$this->logger->info("SLUP switched to HALFOPEN");
		$this->cache->set("circuitstate", self::CIRCUIT_HALFOPEN, self::CIRCUIT_STATE_MULTIPLIER * $this->getControlInterval());
		// we log start and scheduling differently, so no log
		// TODO: signal state to monitoring

		if (!$this->registerSlup()) {
			$this->circuitOpen();
		}
	}

	/**
	 * System goes back to normal business
	 * TODO: signal in monitoring
	 */
	public function circuitClosed() {
		if (!$this->isCircuitClosed()) {
			$this->logger->info("SLUP circuit CLOSED, normal business");
		}
		$this->cache->set("circuitstate", self::CIRCUIT_CLOSED, self::CIRCUIT_STATE_MULTIPLIER * $this->getControlInterval());
	}


	/**
	 * return null if connection is not established, otherwise current token.
	 */
	public function registerSlup($wait = 5) {
		// only connect if not obviously already connected
		if ($this->isCircuitClosed()) {
			// a successful registration is active already
			return true;
		}

		$slupid = $this->config->getAppValue('nmcslup', 'slupid', "10TVL0SLUP0000004901NEXTMAGENTACLOUD0000");
		$slupsecret = $this->config->getAppValue('nmcslup', 'slupsecret', "<no default secret>");
		$slupGwEndpoint = $this->config->getAppValue('nmcslup', 'slupgwendpoint', 'https://slup2soap00.idm.ver.sul.t-online.de/slupService/');
		$trace = ($this->config->getSystemValue('loglevel', ILogger::WARN) == ILogger::DEBUG);

		try {
			// in case of short hick ups, try a onetime single retry
			// to not delay next retry too much
			// by going into open state
			$token = $this->sendRegistration($slupGwEndpoint, $slupid, $slupsecret, $trace);
			$this->setToken($token);
			return true;
		} catch (\SoapFault $sf) {
			$slupDetailCode = $this->getSlupSoapFaultDetail($sf, 'code', 'none');
			if ($slupDetailCode == 'A007') {
				// go on, either with the current token or a forced reconnect
				// on non-matching current token (=A007 at first connect after boot)
				return true;
			} elseif ($sf->faultcode == 'HTTP') {
				$this->logger->warning("SLUP try1 http fault failure, direct retry");
			} else {
				$this->logger->error("SLUP try1 http fault, giving up");
				$this->clearToken();
				return false;
			}
		} catch (\Throwable $e) {
			$this->logger->error("SLUP try1 critical fault, giving up");
			$this->clearToken();
			return false;
		}

		sleep($wait); // short term retry after default 5sec

		try {
			$token = $this->sendRegistration($slupGwEndpoint, $slupid, $slupsecret);
			$this->setToken($token);
			return true;
		} catch (\SoapFault $sf) {
			$slupDetailCode = $this->getSlupSoapFaultDetail($sf, 'code', 'none');
			if ($slupDetailCode == 'A007') {
				// go on, either with the current token or a forced reconnect
				// on non-matching current token (=A007 at first connect after boot)
				return true;
			} else {
				$this->logger->error("SLUP try2 SOAP fault, giving up");
				$this->clearToken();
				return false;
			}
		} catch (\Throwable $e) {
			$this->logger->error("SLUP try2 failed again, giving up");
			$this->clearToken();
			return false;
		}
	}

	// ----- implementation details ------

	/**
	 * Stable SLUP fault detail extraction
	 * Public for unittest support
	 */
	public function getSlupSoapFaultDetail(\SoapFault $fault, String $name, string $default = '') {
		if (property_exists($fault, 'detail') &&
			 property_exists($fault->detail, 'FaultResponse') &&
			 property_exists($fault->detail->FaultResponse, $name)) {
			return $fault->detail->FaultResponse->{$name};
		} else {
			return $default;
		}
	}

	/**
	 *
	 * Method is public for unittest purposes.
	 */
	public function sendRegistration($gwendpoint, $appid, $appsec, $trace = false) {
		$receiverEndpoint = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.SlupApi.soapCall');
		$this->logger->debug($appid . ": Register at " . $gwendpoint . " -cb-> " . $receiverEndpoint);

		try {
			$this->logger->debug("SLUP try to connect");
			if (is_null($this->soapClient)) {
				$this->logger->debug("Creating new SoapClient");
				// late client creation or mocking to access settings before creation properly
				libxml_set_external_entity_loader(static function ($public, $system, $context) {
					return $system;
				});

				$soapClient = new SoapClient($this->wsdlPath, array('connection_timeout' => 20, // limit response time to 20sec
					'cache_wsdl' => 0,
					'trace' => $trace,
					'exceptions' => true,
					'location' => $gwendpoint,
					'local_cert' => $this->localCert));
			} else {
				$this->logger->debug("Using existing SoapClient");
				// for mocking purpose (only)
				$soapClient = $this->soapClient;
			}

			//security name-space
			$strWSSENS = "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd";
			// Create SOAP Vars
			$objSoapVarUser = new \SoapVar($appid, XSD_STRING, null, $strWSSENS, null, $strWSSENS);
			$objSoapVarPass = new \SoapVar($appsec, XSD_STRING, null, $strWSSENS, null, $strWSSENS);
			// Create Object for Auth Class and pass in soap var
			$objWSSEAuth = new SoapWSSEAuth($objSoapVarUser, $objSoapVarPass);
			// Create SoapVar out of object of Auth class
			$objSoapVarWSSEAuth = new \SoapVar($objWSSEAuth, SOAP_ENC_OBJECT, null, $strWSSENS, 'UsernameToken', $strWSSENS);
			// Create object for Token Class
			$objWSSEToken = new SoapWSSEToken($objSoapVarWSSEAuth);
			// Create SoapVar out of object of Token class
			$objSoapVarWSSEToken = new \SoapVar($objWSSEToken, SOAP_ENC_OBJECT, null, $strWSSENS, 'UsernameToken', $strWSSENS);
			// Create SoapVar for 'Security' node
			$objSoapVarHeaderVal = new \SoapVar($objSoapVarWSSEToken, SOAP_ENC_OBJECT, null, $strWSSENS, 'Security', $strWSSENS);
			// Create header object out of security soapvar, third parameter here makes 'mustUnderstand=1'
			$objSoapVarWSSEHeader = new \SoapHeader($strWSSENS, 'Security', $objSoapVarHeaderVal, true);
			// Set headers for soapclient object
			$soapClient->__setSoapHeaders(array($objSoapVarWSSEHeader));
			// Call startSLUP2
			$response = $soapClient->startSLUP2(array('slupURL' => $receiverEndpoint));
			if (is_null($response)) {
				throw new Exception("Unknown SLUP NULL response, undefined state");
			}

			$this->logger->debug("startSLUP2: " . $soapClient->__getLastResponse());
			if ($response->SLUPreturncode == '0000') {
				// these are the cases when we ar connected
				return $response->token;
			} else {
				$this->logger->critical("Response: " . strval($soapClient->__getLastResponse()) . PHP_EOL .
										"SLUP-Connect code: {$response->SLUPreturncode}, message: {$response->detail}");
				// give the caller the chance to handle the problem individually
				throw new SlupConnectException("[{$response->SLUPreturncode}] {$response->detail}", \hexdec($response->SLUPreturncode));
			}
		} catch (\SoapFault $fault) {
			$slupDetailCode = $this->getSlupSoapFaultDetail($fault, 'code', 'none');
			$slupDetailMessage = $this->getSlupSoapFaultDetail($fault, 'message', 'none');
			// A007 already connected is delivered as SOAPFault
			// and handled as "good" state; so this special sitation
			// is only logged in debug mode
			if ($slupDetailCode == 'A007') {
				$level = ILogger::DEBUG;
			} else {
				$level = ILogger::ERROR;
			}
			if (!$soapClient) {
				$this->logger->debug($fault->getMessage());
				$this->logger->logException($fault, [
					'message' => "SOAPFault code: {$fault->faultcode}:{$slupDetailCode}" . PHP_EOL .
						"SOAPFault message: {$fault->faultstring}:{$slupDetailMessage}",
					'level' => $level,
					'app' => Application::APP_ID]);
			} else {
				$this->logger->logException($fault, [
					'message' => "Response: " . strval($soapClient?->__getLastResponse()) . PHP_EOL .
						"SOAPFault code: {$fault->faultcode}:{$slupDetailCode}" . PHP_EOL .
						"SOAPFault message: {$fault->faultstring}:{$slupDetailMessage}" . PHP_EOL .
						"SOAP Fault request: " . $soapClient?->__getLastRequest() . PHP_EOL,
					'level' => $level,
					'app' => Application::APP_ID]);
			}
			throw $fault;
		} catch (\Throwable $e) {
			$this->logger->logException($e, [
				'message' => "Error code: {$e->getCode()}, message: {$e->getMessage()})",
				'level' => ILogger::ERROR,
				'app' => Application::APP_ID]);
			throw $e;
		}
	}
}
