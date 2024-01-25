<?php

declare(strict_types=1);

namespace OCA\NextMagentaCloudSlup\UnitTest;

use OCA\NextMagentaCloudProvisioning\Rules\TariffRules;
use OCA\NextMagentaCloudProvisioning\Rules\UserAccountRules;
use OCA\NextMagentaCloudSlup\AppInfo\Application;
use OCA\NextMagentaCloudSlup\Controller\SlupApiController;
use OCA\NextMagentaCloudSlup\Registration\SlupCircuitControlJob;
use OCA\NextMagentaCloudSlup\Registration\SlupRegistrationManager;
use OCA\NextMagentaCloudSlup\TestHelper\SoapTestCase;

use OCP\AppFramework\Utility\ITimeFactory;

use OCP\Http\Client\IClientService;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\ILogger;

use OCP\IRequest;
use OCP\IURLGenerator;

use PHPUnit\Framework\Assert;

class Slup32JobToCircuitBreakerTest extends SoapTestCase {

	/**
	 * @var IConfig
	 */
	protected $config;

	public function setUp(): void {
		parent::setUp();
		$app = new \OCP\AppFramework\App(Application::APP_ID);
		$this->config = $this->getMockForAbstractClass(IConfig::class);

		$this->urlGenerator = $app->getContainer()->get(IURLGenerator::class);
		$this->registrationManager = $this->getMockBuilder(SlupRegistrationManager::class)
										->onlyMethods(['sendRegistration'])
										->setConstructorArgs([ $app->getContainer()->get(ILogger::class),
											$this->urlGenerator,
											$app->getContainer()->get(IClientService::class),
											$this->config,
											$app->getContainer()->get(ICacheFactory::class) ])
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

	public function tearDown(): void {
		parent::tearDown();
		// in case you need a complete reset of db app values for nmcslup, uncomment this
		//$this->config->deleteAppValues('nmcslup');
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
	}

	public function testControlJobBootRunToOpen() {
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
								->willReturn(null);

		$timeFactory = $this->app->getContainer()->get(ITimeFactory::class);
		$this->assertNotNull($timeFactory);
		$logger = $this->app->getContainer()->get(ILogger::class);
		$this->assertNotNull($logger);
		$job = new SlupCircuitControlJob($timeFactory, $logger, $this->registrationManager);
		$this->assertNotNull($job);
		$this->assertEquals(300, $job->getInterval());

		$this->registrationManager->forceCircuitUndefined();
		$this->assertTrue($this->registrationManager->isCircuitUndefined());
		$job->run(null);
		$this->assertFalse($this->registrationManager->isCircuitUndefined());
		$this->assertFalse($this->registrationManager->hasToken());
		$this->assertTrue($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertFalse($this->registrationManager->isCircuitClosed());
	}



	public function testJobCircuitOpen() {
		// set state open, assert job creation
		$this->registrationManager->circuitOpen();
		$this->assertFalse($this->registrationManager->hasToken());
	}

	public function testJobCircuitHalfOpenSuccess() {
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
		// simulate successful job execution
		$this->registrationManager->expects($this->once())
								->method('sendRegistration')
								->with($this->equalTo('https://slup2soap00.idm.ver.sul.t-online.de/slupService/'),
									$this->equalTo('10TVL0SLUP0000004901NEXTMAGENTACLOUD0000'),
									$this->equalTo('<secret>'))
								->willReturn('1122334455');
		$this->registrationManager->circuitHalfOpen();
		$this->assertTrue($this->registrationManager->hasToken());
		$this->assertTrue($this->registrationManager->isValidToken('1122334455'));
		$this->assertFalse($this->registrationManager->isValidToken('1122334466'));
		$this->assertFalse($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertTrue($this->registrationManager->isCircuitClosed());
	}

	public function testHalfOpenJobRunToOpen() {
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
		$timeFactory = $this->app->getContainer()->get(ITimeFactory::class);
		$this->assertNotNull($timeFactory);
		$logger = $this->app->getContainer()->get(ILogger::class);
		$this->assertNotNull($logger);
		$this->registrationManager->expects($this->once())
								->method('sendRegistration')
								->with($this->equalTo('https://slup2soap00.idm.ver.sul.t-online.de/slupService/'),
									$this->equalTo('10TVL0SLUP0000004901NEXTMAGENTACLOUD0000'),
									$this->equalTo('<secret>'))
										->willThrowException(new \Exception());
		$job = new SlupCircuitControlJob($timeFactory, $logger, $this->registrationManager);
		$this->assertNotNull($job);
		$this->assertEquals(SlupRegistrationManager::CIRCUIT_HALFOPEN_DELAY, $job->getInterval());
			
		$job->run(null);
		$this->assertFalse($this->registrationManager->hasToken());
		$this->assertTrue($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertFalse($this->registrationManager->isCircuitClosed());
	}

	public function testBootJobRunToClosed() {
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

		$timeFactory = $this->app->getContainer()->get(ITimeFactory::class);
		$this->assertNotNull($timeFactory);
		$logger = $this->app->getContainer()->get(ILogger::class);
		$this->assertNotNull($logger);
		$this->registrationManager->expects($this->once())
								->method('sendRegistration')
								->with($this->equalTo('https://slup2soap00.idm.ver.sul.t-online.de/slupService/'),
									$this->equalTo('10TVL0SLUP0000004901NEXTMAGENTACLOUD0000'),
									$this->equalTo('<secret>'))
								->willReturn('1122334455');
		$job = new SlupCircuitControlJob($timeFactory, $logger, $this->registrationManager);
		$this->assertNotNull($job);
		$job->run(null);
		$this->assertTrue($this->registrationManager->hasToken());
		$this->assertFalse($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertTrue($this->registrationManager->isCircuitClosed());
	}

	public function testHalfOpenJobRunToClosed() {
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

		$timeFactory = $this->app->getContainer()->get(ITimeFactory::class);
		$this->assertNotNull($timeFactory);
		$logger = $this->app->getContainer()->get(ILogger::class);
		$this->assertNotNull($logger);
		$this->registrationManager->expects($this->once())
								->method('sendRegistration')
								->with($this->equalTo('https://slup2soap00.idm.ver.sul.t-online.de/slupService/'),
									$this->equalTo('10TVL0SLUP0000004901NEXTMAGENTACLOUD0000'),
									$this->equalTo('<secret>'))
								->willReturn('1122334455');
		$job = new SlupCircuitControlJob($timeFactory, $logger, $this->registrationManager);
		$this->assertNotNull($job);
		$job->run(null);
		$this->assertTrue($this->registrationManager->hasToken());
		$this->assertFalse($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertTrue($this->registrationManager->isCircuitClosed());
	}

}
