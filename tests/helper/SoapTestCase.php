<?php

declare(strict_types=1);

namespace OCA\NextMagentaCloudSlup\TestHelper;

use OCP\AppFramework\Http\Response;


use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

/**
 * This test must be run with --stderr, e.g.
 * phpunit --stderr --bootstrap tests/bootstrap.php tests/unit/SlupReceiverTest.php
 */
class SoapTestCase extends TestCase {

	/**
	 * @var SoapApiController
	 */
	private $slupController;

	protected function startServer($controller, string $wsdlPath) {
		$this->soapTestServer = new SoapTestServer($wsdlPath, $controller);
	}

	protected function callSoap($message) {
		$response = $this->soapTestServer->callSoap($message);
		$this->assertNotNull($response);
		$parsedResult = simplexml_load_string($response);
		$parsedResult->registerXPathNamespace("e", "http://schemas.xmlsoap.org/soap/envelope/");
		$parsedResult->registerXPathNamespace("s", "http://slup2soap.idm.telekom.com/slupClient/");
		return $parsedResult;
	}

	/**
	 * Assert that the response is of SLUP type
	 */
	protected function assertSlupResponseMessage($parsedResult) {
		$isSlupResponse = $parsedResult->xpath("//e:Envelope/e:Body/s:SLUPResponse|s:SLUPConnectResponse|s:SLUPDisconnectResponse");
		$this->assertNotFalse($isSlupResponse,
		 "Not a slup response message:" . PHP_EOL . strval($parsedResult->asXML()));
		$this->assertNotNull($isSlupResponse,
		 "Not a slup response message:" . PHP_EOL . strval($parsedResult->asXML()));
	}

	/**
	 * Assert SLUP response code and details
	 */
	protected function assertSlupResponse(string $expectedCode, $parsedResult, string $containedText = null) {
		$this->assertSlupResponseMessage($parsedResult);
		$codePath = $parsedResult->xpath("//e:Envelope/e:Body//returncode");
		$code = (($codePath != null) && ($codePath != false)) ? $codePath[0] : '';
		$this->assertEquals($expectedCode, $code, "SLUP code " . $expectedCode . " expected, actual " . $code);
		if ($containedText !== null) {
			$detailPath = $parsedResult->xpath("//e:Envelope/e:Body//detail");
			$details = (($detailPath != null) && ($detailPath != false)) ? $detailPath[0] : '';
			$this->assertStringContainsString($containedText, strval($details), "Expected '" . $containedText . "' in detail text, but not found in '" . $details . "'");
		}
	}
}
