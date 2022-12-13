<?php

namespace OCA\NextMagentaCloudSlup\Registration;

class SoapWSSEAuth {
	/** @var object */
	private $Username;

	/** @var object */
	private $Password;

	public function __construct($username, $password) {
		$this->Username = $username;
		$this->Password = $password;
	}
}
