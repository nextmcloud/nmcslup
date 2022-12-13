<?php

declare(strict_types=1);

namespace OCA\NextMagentaCloudSlup\TestHelper;

/**
 * This wrapper class is required to isolate SoapServer header manipulations
 * from phpunit which does not like any header outputs
 */
class SoapTestServer {
	public function __construct(string $wsdl, object $controller) {
		$this->server = new \SoapServer($wsdl);
		ini_set("soap.wsdl_cache_enabled", "0");
		$this->server->setObject($controller);
	}

	public function callSoap(string $message) {
		ob_start();                     //output buffering
		$this->server->handle($message);
		$soapOutput = ob_get_contents();
		ob_end_clean();
		header_remove();
		//to get current buffer contents and delete current output buffer
		return $soapOutput;
	}
}
