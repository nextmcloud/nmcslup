<?php

declare(strict_types=1);

namespace OCA\NextMagentaCloudSlup\UnitTest;

use OCP\IRequest;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\ICacheFactory;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClientService;


use OCA\NextMagentaCloudSlup\TestHelper\SoapTestCase;
use OCA\NextMagentaCloudSlup\AppInfo\Application;
use OCA\NextMagentaCloudSlup\Registration\SlupRegistrationManager;
use OCA\NextMagentaCloudSlup\Controller\SlupApiController;

use OCA\NextMagentaCloudProvisioning\Rules\TariffRules;
use OCA\NextMagentaCloudProvisioning\Rules\UserAccountRules;

use OCA\NextMagentaCloudSlup\Registration\SlupCircuitControlJob;

class Slup40ControlJobTest extends SoapTestCase {

	/**
	 * @var IConfig
	 */
	protected $config;


	public function setUp(): void {
		parent::setUp();
		$app = new \OCP\AppFramework\App(Application::APP_ID);
		$this->config = $this->getMockForAbstractClass(IConfig::class);

		$this->urlGenerator = $app->getContainer()->get(IURLGenerator::class);
		$this->config->expects($this->at(0))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupcontrolintv'))
					->willReturn('1500');
		$this->registrationManager = $this->getMockBuilder(SlupRegistrationManager::class)
										->onlyMethods(['sendRegistration'])
										->setConstructorArgs([ $app->getContainer()->get(ILogger::class),
											$this->urlGenerator,
											$app->getContainer()->get(IClientService::class),
											$this->config,
											$app->getContainer()->get(ICacheFactory::class)])
										->getMock();
		$this->soapClientMock = $this->getMockFromWsdl($this->registrationManager->getWsdlPath());
		$this->registrationManager->replaceSoapClient($this->soapClientMock);

		$this->accountRulesMock = $this->createMock(UserAccountRules::class);
		$this->slupController = new SlupApiController(Application::APP_ID,
													$app->getContainer()->get(IRequest::class),
													$app->getContainer()->get(ILogger::class),
													$this->registrationManager,
													$app->getContainer()->get(TariffRules::class),
													$this->accountRulesMock);
		$this->startServer($this->slupController, $this->slupController->getWsdlPath());
		
		$this->app = $app;
	}

	public function testBootJobConstructor() {
		$timeFactory = $this->app->getContainer()->get(ITimeFactory::class);
		$this->assertNotNull($timeFactory);
		$logger = $this->app->getContainer()->get(ILogger::class);
		$this->assertNotNull($logger);
		$config = $this->app->getContainer()->get(IConfig::class);
		$this->assertNotNull($config);
		$regMgr = $this->app->getContainer()->get(SlupRegistrationManager::class);
		$this->assertNotNull($regMgr);

		$job = new SlupCircuitControlJob($timeFactory, $logger, $regMgr);
		$this->assertNotNull($job);
		$this->assertEquals(300, $job->getInterval());
	}

	protected function createControlJob() {
		$timeFactory = $this->app->getContainer()->get(ITimeFactory::class);
		$this->assertNotNull($timeFactory);
		$logger = $this->app->getContainer()->get(ILogger::class);
		$this->assertNotNull($logger);
		$job = new SlupCircuitControlJob($timeFactory, $logger, $this->registrationManager);
		$this->assertNotNull($job);
		$this->assertEquals(1500, $job->getInterval());
		return $job;
	}

	protected function runControlJobToClosed() {
		$this->config->expects($this->at(0))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupid'))
					->willReturn('10TVL0SLUP0000004901NEXTMAGENTACLOUD0000');
		$this->config->expects($this->at(1))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupsecret'))
					->willReturn('<secret>');
		$this->config->expects($this->at(2))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupgwendpoint'))
					->willReturn('https://slup2soap00.idm.ver.sul.t-online.de/slupService/');
        $this->registrationManager->expects($this->once())
                    ->method('sendRegistration')
                    ->with($this->equalTo('https://slup2soap00.idm.ver.sul.t-online.de/slupService/'),
                            $this->equalTo('10TVL0SLUP0000004901NEXTMAGENTACLOUD0000'),
                            $this->equalTo('<secret>'))
                    ->willReturn('0987654321');
        return $this->createControlJob()->run(null);
	}

	protected function runControlJobToOpen() {
		$this->config->expects($this->at(0))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupid'))
					->willReturn('10TVL0SLUP0000004901NEXTMAGENTACLOUD0000');
		$this->config->expects($this->at(1))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupsecret'))
					->willReturn('<secret>');
		$this->config->expects($this->at(2))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupgwendpoint'))
					->willReturn('https://slup2soap00.idm.ver.sul.t-online.de/slupService/');
        $this->registrationManager->expects($this->once())
                    ->method('sendRegistration')
                    ->with($this->equalTo('https://slup2soap00.idm.ver.sul.t-online.de/slupService/'),
                            $this->equalTo('10TVL0SLUP0000004901NEXTMAGENTACLOUD0000'),
                            $this->equalTo('<secret>'))
                    ->willThrowException(new \Exception());
        return $this->createControlJob()->run(null);
	}


	public function testConnectOnBootUndefined() {
		$this->config->expects($this->at(0))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupid'))
					->willReturn('10TVL0SLUP0000004901NEXTMAGENTACLOUD0000');
		$this->config->expects($this->at(1))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupsecret'))
					->willReturn('<secret>');
		$this->config->expects($this->at(2))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupgwendpoint'))
					->willReturn('https://slup2soap00.idm.ver.sul.t-online.de/slupService/');
		$this->registrationManager->expects($this->once())
								->method('sendRegistration')
								->with($this->equalTo('https://slup2soap00.idm.ver.sul.t-online.de/slupService/'),
										$this->equalTo('10TVL0SLUP0000004901NEXTMAGENTACLOUD0000'),
										$this->equalTo('<secret>'))
								->willThrowException(new \Exception());
		$this->registrationManager->forceCircuitUndefined();
		$this->registrationManager->resetRecvCount();
		$this->registrationManager->incrementRecvCount();
		$this->registrationManager->setToken('1234567890');
		$job = $this->createControlJob();
		$this->assertTrue($job->connectOnBoot());
		$this->assertFalse($this->registrationManager->isCircuitUndefined());
		$this->assertFalse($this->registrationManager->hasToken());
		$this->assertTrue($this->registrationManager->isCircuitOpen());
	}

	public function testConnectOnBootClosed() {
		$this->registrationManager->circuitClosed();
		$this->registrationManager->resetRecvCount();
		$this->registrationManager->incrementRecvCount();
		$this->registrationManager->setToken('1234567890');
		$job = $this->createControlJob();
		$this->assertFalse($job->connectOnBoot());
		$this->assertFalse($this->registrationManager->isCircuitUndefined());
		$this->assertTrue($this->registrationManager->hasToken());
		$this->assertTrue($this->registrationManager->isCircuitClosed());
	}

	public function testRegistrationLostDetected() {
		$this->config->expects($this->at(0))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupid'))
					->willReturn('10TVL0SLUP0000004901NEXTMAGENTACLOUD0000');
		$this->config->expects($this->at(1))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupsecret'))
					->willReturn('<secret>');
		$this->config->expects($this->at(2))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupgwendpoint'))
					->willReturn('https://slup2soap00.idm.ver.sul.t-online.de/slupService/');
		$this->registrationManager->expects($this->once())
								->method('sendRegistration')
								->with($this->equalTo('https://slup2soap00.idm.ver.sul.t-online.de/slupService/'),
										$this->equalTo('10TVL0SLUP0000004901NEXTMAGENTACLOUD0000'),
										$this->equalTo('<secret>'))
								->willThrowException(new \Exception());

		$this->registrationManager->circuitClosed();
		$this->registrationManager->resetRecvCount();
		$job = $this->createControlJob();
		$this->assertTrue($job->registrationLost());
		$this->assertEquals(0, $this->registrationManager->getRecvCount());
		$this->assertTrue($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->hasToken());
	}

	public function testRegistrationActive() {
		$this->registrationManager->circuitClosed();
		$this->registrationManager->resetRecvCount();
		$this->registrationManager->incrementRecvCount();
		$this->registrationManager->setToken('1234567890');
		$job = $this->createControlJob();
		$this->assertFalse($job->registrationLost());
		$this->assertFalse($this->registrationManager->isCircuitUndefined());
		$this->assertEquals(0, $this->registrationManager->getRecvCount());
		$this->assertTrue($this->registrationManager->isValidToken('1234567890'));
		$this->assertTrue($this->registrationManager->isCircuitClosed());
	}

	public function testRegistrationAlreadyOpen() {
		$this->registrationManager->circuitOpen();
		$this->registrationManager->resetRecvCount();
		$this->registrationManager->incrementRecvCount();
		$this->registrationManager->clearToken();
		$job = $this->createControlJob();
		$this->assertFalse($job->registrationLost());
		$this->assertFalse($this->registrationManager->isCircuitUndefined());
		$this->assertFalse($this->registrationManager->hasToken());
		$this->assertTrue($this->registrationManager->isCircuitOpen());
	}

	public function testSwitchHalfOpenToOpen() {
		$this->config->expects($this->at(0))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupid'))
					->willReturn('10TVL0SLUP0000004901NEXTMAGENTACLOUD0000');
		$this->config->expects($this->at(1))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupsecret'))
					->willReturn('<secret>');
		$this->config->expects($this->at(2))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupgwendpoint'))
					->willReturn('https://slup2soap00.idm.ver.sul.t-online.de/slupService/');
		$this->registrationManager->expects($this->once())
								->method('sendRegistration')
								->with($this->equalTo('https://slup2soap00.idm.ver.sul.t-online.de/slupService/'),
										$this->equalTo('10TVL0SLUP0000004901NEXTMAGENTACLOUD0000'),
										$this->equalTo('<secret>'))
								->willThrowException(new \Exception());

		$this->registrationManager->circuitOpen();
		$job = $this->createControlJob();
		$this->assertTrue($job->switchCircuit());
		$this->assertTrue($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->hasToken());
	}

	public function testSwitchHalfOpenToClosed() {
		$this->config->expects($this->at(0))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupid'))
					->willReturn('10TVL0SLUP0000004901NEXTMAGENTACLOUD0000');
		$this->config->expects($this->at(1))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupsecret'))
					->willReturn('<secret>');
		$this->config->expects($this->at(2))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupgwendpoint'))
					->willReturn('https://slup2soap00.idm.ver.sul.t-online.de/slupService/');
		$this->registrationManager->expects($this->once())
								->method('sendRegistration')
								->with($this->equalTo('https://slup2soap00.idm.ver.sul.t-online.de/slupService/'),
										$this->equalTo('10TVL0SLUP0000004901NEXTMAGENTACLOUD0000'),
										$this->equalTo('<secret>'))
								->willReturn('0987654321');

		$this->registrationManager->circuitOpen();
		$job = $this->createControlJob();
		$this->assertTrue($job->switchCircuit());
		$this->assertTrue($this->registrationManager->isCircuitClosed());
		$this->assertTrue($this->registrationManager->isValidToken('0987654321'));
	}

	public function testControlJobBootRunToOpen() {
		$this->registrationManager->forceCircuitUndefined();
		$this->assertTrue($this->registrationManager->isCircuitUndefined());
		$this->runControlJobToOpen();
		$this->assertFalse($this->registrationManager->isCircuitUndefined());
		$this->assertFalse($this->registrationManager->hasToken());
		$this->assertTrue($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertFalse($this->registrationManager->isCircuitClosed());
	}

	public function testControlJobBootRunToClosed() {
		$this->registrationManager->forceCircuitUndefined();
		$this->assertTrue($this->registrationManager->isCircuitUndefined());
		$this->runControlJobToClosed();
		$this->assertFalse($this->registrationManager->isCircuitUndefined());
		$this->assertTrue($this->registrationManager->hasToken());
		$this->assertTrue($this->registrationManager->isValidToken('0987654321'));
		$this->assertFalse($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertTrue($this->registrationManager->isCircuitClosed());
	}

	public function testControlJobOpenRunToOpen() {
		$this->registrationManager->circuitOpen();
		$this->assertTrue($this->registrationManager->isCircuitOpen());
		$this->runControlJobToOpen();
		$this->assertFalse($this->registrationManager->isCircuitUndefined());
		$this->assertFalse($this->registrationManager->hasToken());
		$this->assertTrue($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertFalse($this->registrationManager->isCircuitClosed());
	}

	public function testControlJobOpenRunToClosed() {
		$this->registrationManager->circuitOpen();
		$this->assertTrue($this->registrationManager->isCircuitOpen());
		$this->runControlJobToClosed();
		$this->assertFalse($this->registrationManager->isCircuitUndefined());
		$this->assertTrue($this->registrationManager->hasToken());
		$this->assertTrue($this->registrationManager->isValidToken('0987654321'));
		$this->assertFalse($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertTrue($this->registrationManager->isCircuitClosed());
	}


    public function testJobDetectNormalOps() {
		$this->config->expects($this->at(0))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupcontrolintv'))
					->willReturn('1500');
		$this->registrationManager = $this->getMockBuilder(SlupRegistrationManager::class)
										->onlyMethods(['circuitHalfOpen'])
										->setConstructorArgs([ $this->app->getContainer()->get(ILogger::class),
											$this->urlGenerator,
											$this->app->getContainer()->get(IClientService::class),
											$this->config,
											$this->app->getContainer()->get(ICacheFactory::class) ])
										->getMock();

        $this->registrationManager->expects($this->never())
                            ->method('circuitHalfOpen');

        // set state open, assert job creation
		$job = $this->createControlJob();

        $this->registrationManager->circuitClosed();
        $this->registrationManager->setToken('0123456789');
        $this->registrationManager->resetRecvCount();
        $this->registrationManager->incrementRecvCount();
        $this->registrationManager->incrementRecvCount();
        $this->registrationManager->incrementRecvCount();
        $job->run(null);
        $this->assertTrue($this->registrationManager->isValidToken('0123456789'));
        $this->assertEquals(0, $this->registrationManager->getRecvCount());
		$this->assertTrue($this->registrationManager->isCircuitClosed());
    }

    public function testJobDetectNoMessages() {
		$this->config->expects($this->at(0))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupcontrolintv'))
					->willReturn('1500');
		$this->registrationManager = $this->getMockBuilder(SlupRegistrationManager::class)
										->onlyMethods(['circuitHalfOpen'])
										->setConstructorArgs([ $this->app->getContainer()->get(ILogger::class),
											$this->urlGenerator,
											$this->app->getContainer()->get(IClientService::class),
											$this->config,
											$this->app->getContainer()->get(ICacheFactory::class) ])
										->getMock();

        $this->registrationManager->expects($this->once())
                            ->method('circuitHalfOpen');

        // set state open, assert job creation
		$job = $this->createControlJob();

        $this->registrationManager->circuitClosed();
        $this->registrationManager->setToken('0123456789');
        $this->registrationManager->resetRecvCount();
        $job->run(null);
        $this->assertEquals(0, $this->registrationManager->getRecvCount());
    }

    public function testJobDetectA007ClosedKeepToken() {
		$this->config->expects($this->at(0))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupcontrolintv'))
					->willReturn('1500');
		$this->registrationManager = $this->getMockBuilder(SlupRegistrationManager::class)
										->onlyMethods(['sendRegistration'])
										->setConstructorArgs([ $this->app->getContainer()->get(ILogger::class),
											$this->urlGenerator,
											$this->app->getContainer()->get(IClientService::class),
											$this->config,
											$this->app->getContainer()->get(ICacheFactory::class)])
										->getMock();

        $detail = new \stdClass;
		$detail->FaultResponse = new \stdClass;
		$detail->FaultResponse->code = 'A007';
		$detail->FaultResponse->message = 'lockfile exists';
        $this->registrationManager->expects($this->once())
                            ->method('sendRegistration')
                            ->willThrowException(new \SoapFault('SOAP-ENV:Server', 
                                                        'Application error', null, $detail));

        // set state open, assert job creation
		$job = $this->createControlJob();

        $this->registrationManager->circuitClosed();
        $this->registrationManager->setToken('1122334455');
        $this->registrationManager->resetRecvCount();
        $job->run(null);
        $this->assertTrue($this->registrationManager->isCircuitClosed());
        $this->assertTrue($this->registrationManager->isValidToken('1122334455'));
        $this->assertEquals(0, $this->registrationManager->getRecvCount());
    }

    public function testJobDetectClosedNewToken() {
		$this->config->expects($this->at(0))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupcontrolintv'))
					->willReturn('1500');
		$this->registrationManager = $this->getMockBuilder(SlupRegistrationManager::class)
										->onlyMethods(['sendRegistration'])
										->setConstructorArgs([ $this->app->getContainer()->get(ILogger::class),
											$this->urlGenerator,
											$this->app->getContainer()->get(IClientService::class),
											$this->config,
											$this->app->getContainer()->get(ICacheFactory::class)])
										->getMock();

        $detail = new \stdClass;
		$detail->FaultResponse = new \stdClass;
		$detail->FaultResponse->code = 'A007';
		$detail->FaultResponse->message = 'lockfile exists';
        $this->registrationManager->expects($this->once())
                            ->method('sendRegistration')
                            ->willReturn('1234567890');

        // set state open, assert job creation
		$job = $this->createControlJob();

        $this->registrationManager->circuitClosed();
        $this->registrationManager->setToken('1122334455');
        $this->registrationManager->resetRecvCount();
        $job->run(null);
        $this->assertTrue($this->registrationManager->isCircuitClosed());
        $this->assertFalse($this->registrationManager->isValidToken('1122334455'));
        $this->assertTrue($this->registrationManager->isValidToken('1234567890'));
        $this->assertEquals(0, $this->registrationManager->getRecvCount());
    }

    public function testJobDetectOpen() {
		$this->config->expects($this->at(0))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupcontrolintv'))
					->willReturn('1500');
		$this->registrationManager = $this->getMockBuilder(SlupRegistrationManager::class)
										->onlyMethods(['sendRegistration'])
										->setConstructorArgs([ $this->app->getContainer()->get(ILogger::class),
											$this->urlGenerator,
											$this->app->getContainer()->get(IClientService::class),
											$this->config,
											$this->app->getContainer()->get(ICacheFactory::class) ])
										->getMock();

        $this->registrationManager->expects($this->at(0))
                            ->method('sendRegistration')
                            ->will($this->throwException(new \SoapFault('SOAP:Server', 
                                                        'Unauthorized client')));


        // set state open, assert job creation
		$job = $this->createControlJob();

        $this->registrationManager->circuitClosed();
        $this->registrationManager->setToken('1122334455');
        $this->registrationManager->resetRecvCount();
        $job->run(null);
        $this->assertTrue($this->registrationManager->isCircuitOpen());
        $this->assertFalse($this->registrationManager->isValidToken('1122334455'));
        $this->assertFalse($this->registrationManager->isValidToken('1234567890'));
        $this->assertEquals(0, $this->registrationManager->getRecvCount());
    }

    /**
     * 
     * @large
     */
    public function testJobDetectA007SecondTryClosedKeepToken() {
		$this->config->expects($this->at(0))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupcontrolintv'))
					->willReturn('1500');
		$this->registrationManager = $this->getMockBuilder(SlupRegistrationManager::class)
										->onlyMethods(['sendRegistration'])
										->setConstructorArgs([ $this->app->getContainer()->get(ILogger::class),
											$this->urlGenerator,
											$this->app->getContainer()->get(IClientService::class),
											$this->config,
											$this->app->getContainer()->get(ICacheFactory::class)])
										->getMock();

        $this->registrationManager->expects($this->at(0))
                            ->method('sendRegistration')
                            ->will($this->throwException(new \SoapFault('HTTP', 
                                                        'No connection')));
        $detail = new \stdClass;
        $detail->FaultResponse = new \stdClass;
		$detail->FaultResponse->code = 'A007';
		$detail->FaultResponse->message = 'lockfile exists';
        $this->registrationManager->expects($this->at(1))
                            ->method('sendRegistration')
                            ->will($this->throwException(new \SoapFault('SOAP-ENV:Server', 
                                                        'Application error', null, $detail)));


        // set state open, assert job creation
		$job = $this->createControlJob();

        $this->registrationManager->circuitClosed();
        $this->registrationManager->setToken('1122334455');
        $this->registrationManager->resetRecvCount();
        $job->run(null);
        $this->assertTrue($this->registrationManager->isCircuitClosed());
        $this->assertTrue($this->registrationManager->isValidToken('1122334455'));
        $this->assertFalse($this->registrationManager->isValidToken('1234567890'));
        $this->assertEquals(0, $this->registrationManager->getRecvCount());
    }

}
