<?php

namespace OCA\NextMagentaCloudSlup\Registration;

class SoapWSSEToken {
	/** @var object */
	private $UsernameToken;

	public function __construct($innerVal) {
		$this->UsernameToken = $innerVal;
	}
}
