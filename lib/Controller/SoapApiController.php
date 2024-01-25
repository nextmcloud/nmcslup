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

/**
 * Public interface of ownCloud for apps to use.
 * AppFramework\Controller class
 */

namespace OCA\NextMagentaCloudSlup\Controller;

use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\Response;

use OCP\ILogger;
use OCP\IRequest;

/**
 * Base class to inherit a Soap API handling controller from
 * common API controller to provide SOAP style API
 */
abstract class SoapApiController extends ApiController {

	/** @var ILogger */
	protected $logger;

	/**
	 * constructor of the controller
	 *
	 * Because SoapController is derived from general API controller,
	 * it could be used with 'normal' route registration in route.php
	 *
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
	 * @since 8.1.0
	 */
	public function __construct($appName,
		IRequest $request,
		string $wsdlPath,
		ILogger $logger,
		$corsMethods = 'POST',
		$corsAllowedHeaders = 'Authorization, Content-Type, Accept',
		$corsMaxAge = 1728000) {
		parent::__construct($appName, $request, $corsMethods,
			$corsAllowedHeaders, $corsMaxAge);
		// some SOAP message use application/soap+xml as content type
		$this->registerResponder('soap+xml', function ($message) {
			return $this->buildSoapResponse($message);
		});
		// others simply text/xml as content type
		$this->registerResponder('text/xml', function ($message) {
			return $this->buildSoapResponse($message);
		});

		$this->wsdlPath = $wsdlPath;
		$this->logger = $logger;
	}

	public function buildSoapResponse($message) {
		// TODO: set Content-Type correctly depending on the received
		// Content type and accept header
		return $message;
	}

	/**
	 * Since the SOAP endpoint defaults to SOAP XML
	 * we need to enforce the right responder by changing
	 * the default parameter
	 *
	 * @param mixed $response the value that was returned from a controller and
	 * is not a Response instance
	 * @param string $format the format for which a formatter has been registered
	 * @throws \DomainException if format does not match a registered formatter
	 * @return Response
	 */
	public function buildResponse($response, $format = 'soap+xml') {
		return parent::buildResponse($response, $format);
	}

	public function getWsdlPath() {
		return \realpath($this->wsdlPath);
	}

	protected function soapCall() {
		libxml_set_external_entity_loader(static function ($public, $system, $context) {
			return $system;
		});
		$soapServer = new \SoapServer($this->wsdlPath);
		ini_set("soap.wsdl_cache_enabled", "0");
		$soapServer->setObject($this);

		// this is used to trace all original messages on debug for reuse and transparency
		$this->logger->debug("Received SLUP message:" . PHP_EOL . file_get_contents("php://input"));

		ob_start();                     //output buffering
		$soapServer->handle();
		$soapOutput = ob_get_clean();
		$this->logger->debug("soapOutput: " . $soapOutput);
		//to get current buffer contents and delete current output buffer
		return new SoapResponse($soapOutput);
	}
}
