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

use OCA\NextMagentaCloudSlup\Registration\SlupRegistrationManager;
use OCP\AppFramework\ApiController;

use OCP\AppFramework\Http\DataResponse;
use OCP\ILogger;

use OCP\IRequest;

class SlupStatusController extends ApiController {

	/** @var ILogger */
	protected $logger;

	/** @var SlupRegistrationManager */
	private $slupRegistrationMgr;


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
		$corsMethods = 'POST',
		$corsAllowedHeaders = 'Authorization, Content-Type, Accept',
		$corsMaxAge = 1728000) {
		parent::__construct($appName, $request, $corsMethods,
			$corsAllowedHeaders, $corsMaxAge);
		$this->logger = $logger;
		$this->slupRegistrationMgr = $slupRegistrationMgr;
	}

	/**
	 * Depending on the settings here,
	 * SOAP could be protected by login or not.
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function status() {
		return new DataResponse(array( 'circuit_state' => $this->slupRegistrationMgr->circuitState(),
			'has_token' => $this->slupRegistrationMgr->hasToken() ? 'true' : 'false',
			'num_msg_since_keepalive' => $this->slupRegistrationMgr->getRecvCount() ?? 0));

	}
}
