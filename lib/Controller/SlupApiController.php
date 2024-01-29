<?php
/**
 * @copyright Copyright (c) 2021, T-Systems International
 *
 * @author Bernd Rederlechner <bernd.rederlechner@t-systems.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\NextMagentaCloudSlup\Controller;

use OCA\NextMagentaCloudProvisioning\Rules\DisplaynameRules;
use OCA\NextMagentaCloudProvisioning\Rules\TariffRules;
use OCA\NextMagentaCloudProvisioning\Rules\UserAccountRules;

use OCA\NextMagentaCloudSlup\Registration\SlupRegistrationManager;
use OCA\NextMagentaCloudSlup\Service\ForbiddenException;
use OCA\NextMagentaCloudSlup\Service\NotFoundException;
use OCA\NextMagentaCloudSlup\User\UserExistException;

use OCP\ILogger;
use OCP\IRequest;

class SlupApiController extends SoapApiController {
	public const PROVIDER_PREFIX = 'Telekom';


	/** ILogger already comes from parent class */

	/** @var SlupRegistrationManager */
	private $slupRegistrationMgr;

	/** @var TariffRules */
	private $tariffRules;

	/** @var UserAccountService */
	private $accountRules;

	/** @var DisplaynameRules */
	private $displaynameRules;

	/**
	 * constructor of the controller
	 *
	 * @param string $appName the name of the app
	 * @param IRequest $request an instance of the request
	 * @param string $corsMethods comma separated string of HTTP verbs which
	 * should be allowed for websites or webapps when calling your API, defaults to
	 * 'POST' only for SOAP messages
	 * @param string $corsAllowedHeaders comma separated string of HTTP headers
	 * which should be allowed for websites or webapps when calling your API,
	 * defaults to 'Authorization, Content-Type, Accept'
	 * @param int $corsMaxAge number in seconds how long a preflighted OPTIONS
	 * request should be cached, defaults to 1728000 seconds
	 */
	public function __construct($appName,
		IRequest $request,
		ILogger $logger,
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
	 * Depending on the settings here,
	 * SOAP could be protected by login or not.
	 *
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
		return property_exists($claims, $prefix . $property) ? $claims->{$prefix . $property } : null;
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
		if ($mainEmail != null) {
			return $mainEmail;
		} else {
			return $this->getProperty($newFieldsClaims, $oldFieldsClaims, $prefix, 'extMail');
		}

	}

	private function getAltEmail($newFieldsClaims, $oldFieldsClaims, string $prefix = 'urn:telekom.com:') {
		return $this->getProperty($newFieldsClaims, $oldFieldsClaims, $prefix, 'extMail');
	}

	private function getQuota($newFieldsClaims, string $prefix = 'urn:telekom.com:') {
		return $this->tariffRules->deriveQuota($newFieldsClaims);
	}

	// ---------------- Supported SOAP functions ------------------------
	public function SLUP($request) {
		$this->logger->info("Counting message.");
		$this->slupRegistrationMgr->incrementRecvCount();

		$this->logger->info("Checking token.");
		$token = strval($request->token);
		if (!$this->slupRegistrationMgr->isValidToken($token)) {
			$this->logger->error("SLUP invalid token on message.");
			// save the currently send token to validate the follow-up disconnect
			// message that must follow
			$this->slupRegistrationMgr->setToken($token);
			// signal invalid token
			return array('returncode' => 'F003', 'detail' => 'invalid token');
		}

		// we should not assume any circuit breaker state here as a parallel
		// disconnect could happen while processing

		// process messages
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
			$evalResult = $this->accountRules->deriveAccountState($userName, $displayName, $email, $quota,
				$newFieldsClaims, false, self::PROVIDER_PREFIX);
			$this->logger->info(json_encode($evalResult));
			if ($evalResult['changed']) {
				return array('returncode' => '0010', 'detail' => $evalResult['reason']);
			} else {
				return array('returncode' => '0000', 'detail' => $evalResult['reason']);
			}
		} catch (\InvalidArgumentException | ForbiddenException | NotFoundException | UserExistException | \Exception $e) {
			$this->logger->logException($e, [
				'message' => "SLUP processing error: {$e->getMessage()}): "  . PHP_EOL .  json_encode($request),
				'level' => ILogger::ERROR,
				'app' => 'nmcslup'
			]);
		}

		return array('returncode' => '0012', 'detail' => 'Internal processing error');
	}

	public function SLUPConnect($request) {
		$token = strval($request->token);
		if (!$this->slupRegistrationMgr->isValidToken($token)) {
			$this->logger->error("SLUP invalid token on connect.");
			// with this, we get a disconnect next
			return array('returncode' => 'F003', 'detail' => 'invalid token');
		}

		// we should not assume any circuit breaker state here as a parallel
		// disconnect could happen while processing

		return array('returncode' => '0000', 'detail' => 'connected');
	}

	public function SLUPDisconnect($request) {
		$token = strval($request->token);
		if ($token == '0') {
			// a special connection test
			$this->logger->info("SLUP gateway connection test ok.");
			return array('returncode' => '0000', 'detail' => 'connection ok');
		}

		// we should not assume any circuit breaker state here as a parallel
		// disconnect could happen while processing
		if (!$this->slupRegistrationMgr->isValidToken($token)) {
			$this->logger->error("SLUP invalid token on disconnect.");
			// with this, we get a disconnect next
			return array('returncode' => 'F003', 'detail' => 'invalid token');
		} else {
			$this->slupRegistrationMgr->clearToken();
			$this->slupRegistrationMgr->circuitOpen();
			return array('returncode' => '0000', 'detail' => 'disconnected');
		}
	}
}
