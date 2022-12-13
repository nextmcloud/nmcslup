<?php

declare(strict_types=1);

namespace OCA\NextMagentaCloudSlup\UnitTest;

use OCA\NextMagentaCloudSlup\Controller\SlupStatusController;
use OCA\NextMagentaCloudSlup\Registration\SlupRegistrationManager;

use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Bootstrap\IBootContext;

use OCA\NextMagentaCloudSlup\AppInfo\Application;

use PHPUnit\Framework\TestCase;

class SlupStatusApiTest extends TestCase {

    public function setUp(): void {
		parent::setUp();

        $app = new \OCP\AppFramework\App(Application::APP_ID);
        $this->slupStatusController = $app->getContainer()->get(SlupStatusController::class);

        $this->registrationManager = $app->getContainer()->get(SlupRegistrationManager::class);
	}

    public function testStatusUndefined() {
        $status = $this->slupStatusController->status()->getData();
        $this->assertEquals($status['circuit_state'], 'undefined');
        $this->assertEquals($status['has_token'], 'false');
        $this->assertIsNumeric($status['num_msg_since_keepalive']);
        $this->assertEquals($status['num_msg_since_keepalive'], 0);
    }


    public function testStatusOpen() {
        $this->registrationManager->circuitOpen();
        $this->registrationManager->clearToken();

        $status = $this->slupStatusController->status()->getData();
        $this->assertEquals($status['circuit_state'], 'open');
        $this->assertEquals($status['has_token'], 'false');
        $this->assertIsNumeric($status['num_msg_since_keepalive']);
        $this->assertEquals($status['num_msg_since_keepalive'], 0);
    }

    // the halfopen test does try a connection atm
    // no easy way to avoid this yet
    // public function testStatusHalfOpen() {
    //     $this->registrationManager->circuitHalfOpen();
    //     $this->registrationManager->setToken('0123456789');

    //     $status = $this->slupStatusController->status()->getData();
    //     $this->assertEquals($status['circuit_state'], 'halfopen');
    //     $this->assertEquals($status['has_token'], 'true');
    //     $this->assertEquals($status['num_msg_since_keepalive'], 0);
    // }


    public function testStatusClosed() {
        $this->registrationManager->circuitClosed();
        $this->registrationManager->setToken('0123456789');
        $this->registrationManager->incrementRecvCount();

        $status = $this->slupStatusController->status()->getData();
        $this->assertEquals($status['circuit_state'], 'closed');
        $this->assertEquals($status['has_token'], 'true');
        $this->assertIsNumeric($status['num_msg_since_keepalive']);
        $this->assertEquals($status['num_msg_since_keepalive'], 1);
    }

}