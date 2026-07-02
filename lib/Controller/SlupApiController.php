<?php
/**
 * @copyright Copyright (c) 2021, T-Systems International
 *
 * @author Bernd Rederlechner <bernd.rederlechner@t-systems.com>
 *
 * @license AGPL-3.0
 */

namespace OCA\NextMagentaCloudSlup\Controller;

use OCA\NextMagentaCloudProvisioning\Rules\DisplaynameRules;
use OCA\NextMagentaCloudProvisioning\Rules\TariffRules;
use OCA\NextMagentaCloudProvisioning\Rules\UserAccountRules;
use OCA\NextMagentaCloudSlup\Registration\SlupRegistrationManager;
use OCA\NextMagentaCloudSlup\Service\ForbiddenException;
use OCA\NextMagentaCloudSlup\Service\NotFoundException;
use OCA\NextMagentaCloudSlup\User\UserExistException;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class SlupApiController extends SoapApiController {
	public const PROVIDER_PREFIX = 'Telekom';

	/** @var SlupRegistrationManager */
	private $slupRegistrationMgr;

	/** @var TariffRules */
	private $tariffRules;

	/** @var UserAccountRules */
	private $accountRules;

	/** @var DisplaynameRules */
	private $displaynameRules;

	public function __construct($appName,
		IRequest $request,
		LoggerInterface $logger,
		SlupRegistrationManager $slupRegistrationMgr,
		TariffRules $tariffRules,
		DisplaynameRules $displaynameRules,
		UserAccountRules $accountRules,
		$corsMethods = 'POST',
		$corsAllowedHeaders = 'Authorization, Content-Type, Accept',
		$corsMaxAge = 1728000) {
		parent::__construct($appName, $request,
			$wsdlPath = dirname(__FILE__) . "/slupClient.wsdl",
			$logger,
			$corsMethods,
			$corsAllowedHeaders,
			$corsMaxAge);
		$this->slupRegistrationMgr = $slupRegistrationMgr;
		$this->tariffRules = $tariffRules;
		$this->displaynameRules = $displaynameRules;
		$this->accountRules = $accountRules;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function soapCall() {
		return parent::soapCall();
	}

	public function getUserDetails($request, string $field, string $prefix = 'urn:telekom.com:') {
		if ($request === null || $prefix === null) {
			return null;
		}

		if (!property_exists($request, $field)) {
			return null;
		}

		$claims = new \stdClass();
		$claims->changeTime = $request->changeTime;

		foreach ($request->$field as $element) {
			$claims->{$prefix . $element->name} = $element->val;
		}

		return $claims;
	}

	private function extractProperty($claims, string $prefix, string $property) {
		if ($claims === null || $prefix === null || $property === null) {
			return null;
		}

		return property_exists($claims, $prefix . $property) ? $claims->{$prefix . $property} : null;
	}

	private function getProperty($newFieldsClaims, $oldFieldsClaims, string $prefix, string $property) {
		$propertyValue = $this->extractProperty($newFieldsClaims, $prefix, $property);
		if ($propertyValue === null) {
			$propertyValue = $this->extractProperty($oldFieldsClaims, $prefix, $property);
		}

		return $propertyValue;
	}

	private function getUserName($newFieldsClaims, $oldFieldsClaims, string $prefix = 'urn:telekom.com:') {
		return $this->getProperty($newFieldsClaims, $oldFieldsClaims, $prefix, 'anid');
	}

	private function getDisplayName($newFieldsClaims, $oldFieldsClaims, string $prefix = 'urn:telekom.com:') {
		return $this->displaynameRules->deriveDisplayname($newFieldsClaims);
	}

	private function getEmail($newFieldsClaims, $oldFieldsClaims, string $prefix = 'urn:telekom.com:') {
		$mainEmail = $this->getProperty($newFieldsClaims, $oldFieldsClaims, $prefix, 'mainEmail');
		if ($mainEmail !== null) {
			return $mainEmail;
		}

		return $this->getProperty($newFieldsClaims, $oldFieldsClaims, $prefix, 'extMail');
	}

	private function getAltEmail($newFieldsClaims, $oldFieldsClaims, string $prefix = 'urn:telekom.com:') {
		return $this->getProperty($newFieldsClaims, $oldFieldsClaims, $prefix, 'extMail');
	}

	private function getQuota($newFieldsClaims, string $prefix = 'urn:telekom.com:') {
		return $this->tariffRules->deriveQuota($newFieldsClaims);
	}

	public function SLUP($request) {
		$this->logger->info("Counting message.");
		$this->slupRegistrationMgr->incrementRecvCount();

		$this->logger->info("Checking token.");
		$token = strval($request->token);

		if (!$this->slupRegistrationMgr->isValidToken($token)) {
			$this->logger->error("SLUP invalid token on message.");
			return array('returncode' => 'F003', 'detail' => 'invalid token');
		}

		if ($request->request != 'UTS' && $request->request != 'UTN') {
			$this->logger->warning("SLUP request type is other than 'UTS' or 'UTN' ");
			return array('returncode' => '0000', 'detail' => 'ok');
		}

		$newFieldsClaims = $this->getUserDetails($request, "newfields");
		$oldFieldsClaims = $this->getUserDetails($request, "oldfields");
		$userName = $this->getUserName($newFieldsClaims, $oldFieldsClaims);
		$displayName = $this->getDisplayName($newFieldsClaims, $oldFieldsClaims);
		$email = $this->getEmail($newFieldsClaims, $oldFieldsClaims);
		$altEmail = $this->getAltEmail($newFieldsClaims, $oldFieldsClaims);
		$quota = $this->getQuota($newFieldsClaims);

		try {
			$this->logger->info("User account modification start");
			$evalResult = $this->accountRules->deriveAccountState(
				$userName,
				$displayName,
				$email,
				$quota,
				$newFieldsClaims,
				false,
				self::PROVIDER_PREFIX
			);

			$this->logger->info(json_encode($evalResult));

			if ($evalResult['changed']) {
				return array('returncode' => '0010', 'detail' => $evalResult['reason']);
			}

			return array('returncode' => '0000', 'detail' => $evalResult['reason']);
		} catch (\InvalidArgumentException | ForbiddenException | NotFoundException | UserExistException | \Exception $e) {
			$this->logger->logException($e, [
				'message' => "SLUP processing error: {$e->getMessage()}): " . PHP_EOL . json_encode($request),
				'level' => LoggerInterface::ERROR,
				'app' => 'nmcslup'
			]);
		}

		return array('returncode' => '0012', 'detail' => 'Internal processing error');
	}

	public function SLUPConnect($request) {
		$token = strval($request->token);

		if (!$this->slupRegistrationMgr->isValidToken($token)) {
			$this->logger->error("SLUP invalid token on connect.");
			return array('returncode' => 'F003', 'detail' => 'invalid token');
		}

		return array('returncode' => '0000', 'detail' => 'connected');
	}

	public function SLUPDisconnect($request) {
		$token = strval($request->token);

		if ($token == '0') {
			$this->logger->info("SLUP gateway connection test ok.");
			return array('returncode' => '0000', 'detail' => 'connection ok');
		}

		if (!$this->slupRegistrationMgr->isValidToken($token)) {
			$this->logger->error("SLUP invalid token on disconnect.");
			return array('returncode' => 'F003', 'detail' => 'invalid token');
		}

		$this->slupRegistrationMgr->clearToken();
		$this->slupRegistrationMgr->circuitOpen();

		return array('returncode' => '0000', 'detail' => 'disconnected');
	}
}
