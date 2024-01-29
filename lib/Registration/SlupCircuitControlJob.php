<?php

namespace OCA\NextMagentaCloudSlup\Registration;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

use OCP\ILogger;

class SlupCircuitControlJob extends TimedJob {

	/** @var ILogger */
	private $logger;

	/** @var SlupRegistrationManager */
	private $registrationManager;

	public function __construct(ITimeFactory $time,
		ILogger $logger,
		SlupRegistrationManager $registrationManager) {
		parent::__construct($time);
		$this->logger = $logger;
		$this->registrationManager = $registrationManager;

		// Run once after 5 minutes delay
		$this->setInterval($this->registrationManager->getControlInterval());
	}

	// for unittest only
	public function getInterval() : int {
		return $this->interval;
	}

	public function connectOnBoot() : bool {
		if ($this->registrationManager->isCircuitUndefined()) {
			$this->logger->debug("ControlJob: boot setup");
			$this->registrationManager->resetRecvCount();
			$this->registrationManager->clearToken();
			/** this is run the first time at least 5min */
			$this->registrationManager->circuitHalfOpen();
			return true;
		} else {
			return false;
		}
	}

	public function registrationLost() : bool {
		if ($this->registrationManager->isCircuitClosed() &&
			($this->registrationManager->getRecvCount() == 0)) {
			// do a sendSLUP2 connection trial directly or got to
			// OPEN state - without removing current token
			$this->logger->info("ControlJob: No new SLUP messages, check with reconnect");
			$this->registrationManager->circuitHalfOpen();
			return true;
		} else {
			$this->registrationManager->resetRecvCount();
			return false;
		}
	}

	public function switchCircuit() : bool {
		if ($this->registrationManager->isCircuitOpen()) {
			$this->registrationManager->circuitHalfOpen();
			return true;
		} else {
			// the call is needed to prolong the livetime of the
			// state field in distributed cache until next try.
			return false;
		}
	}


	/**
	 * Remember that NextCLoud job intervals are relative
	 * to the timepoint when app is enabled, so usually, boot
	 * is executed 5min after app enabling
	 */
	public function run($arguments) {
		// Method redeclared public for unittest purpose
		if ($this->connectOnBoot()) {
			return;
		}

		if ($this->registrationLost()) {
			return;
		}
		
		if ($this->switchCircuit()) {
			return;
		}

		// for all positive cases, we refresh the closed
		// state for the next test cycle
		$this->registrationManager->circuitClosed();

	}
}
