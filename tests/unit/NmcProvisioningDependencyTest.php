<?php

declare(strict_types=1);

namespace OCA\NextMagentaCloudSlup\UnitTest;

use OCP\ILogger;
use OCP\App\IAppManager;
use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Bootstrap\IBootContext;

use OCA\NextMagentaCloudSlup\AppInfo\Application;

use PHPUnit\Framework\TestCase;

class NmcProvisioningDependencyTest extends TestCase {
	public function setUp(): void {
		parent::setUp();

        $realApp= new Application();
		$this->appMgr = $realApp->getContainer()->get(IAppManager::class);
		$this->appMgr->disableApp('nmcprovisioning');

		$this->app = $this->getMockBuilder(Application::class)
                        ->onlyMethods(['getContainer'])
                        ->getMock();
        $this->logger = $this->getMockForAbstractClass(ILogger::class);
        $containerMock = $this->createMock(IAppContainer::class);
        $containerMock->expects($this->at(0))
                        ->method('get')
                        ->willReturn($this->appMgr);
        $containerMock->expects($this->at(1))
                        ->method('get')
                        ->willReturn($this->logger);
        $this->app->expects($this->exactly(2))
                    ->method('getContainer')
                    ->willReturn($containerMock);                
	}

	public function tearDown(): void {
		parent::tearDown();
		$this->appMgr->enableApp('nmcprovisioning');
	}

    //public function testCheckOnly() {
    //    $this->assertFalse($this->appMgr->isInstalled('nmcprovisioning'));
    //}

	public function testBootDependencyCheck() {
		$this->logger->expects($this->once())
			->method('error');

		$context = $this->getMockForAbstractClass(IBootContext::class);

		$this->app->boot($context);
	}
}
