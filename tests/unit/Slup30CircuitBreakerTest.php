<?php

declare(strict_types=1);

namespace OCA\NextMagentaCloudSlup\UnitTest;

use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\ICacheFactory;
use OCP\Http\Client\IClientService;

use PHPUnit\Framework\Assert;

use OCP\AppFramework\App;

use OCA\NextMagentaCloudSlup\AppInfo\Application;
use OCA\NextMagentaCloudSlup\TestHelper\SoapTestCase;
use OCA\NextMagentaCloudSlup\Registration\SlupRegistrationManager;

class Slup30CircuitBreakerTest extends SoapTestCase {

	/**
	 * @var IConfig
	 */
	protected $config;

	/**
	 * @var IBootstrap
	 */
	protected $app;

	public function setUp(): void {
		parent::setUp();
		$app = new App(Application::APP_ID);
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
		$this->app = $app;
	}

	public function testCircuitOpen() {
		$this->registrationManager->circuitOpen();
		$this->assertFalse($this->registrationManager->hasToken());
		$this->assertEquals(SlupRegistrationManager::CIRCUIT_OPEN, $this->registrationManager->circuitState());
		$this->assertTrue($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertFalse($this->registrationManager->isCircuitClosed());
	}

	public function testCircuitOpenToClosed() {
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

        // set state open, assert job creation
		$this->registrationManager->circuitOpen();
		$this->assertFalse($this->registrationManager->hasToken());

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
		$this->assertEquals(SlupRegistrationManager::CIRCUIT_CLOSED,
							$this->registrationManager->circuitState());
		$this->assertFalse($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertTrue($this->registrationManager->isCircuitClosed());
	}


	public function testCircuitOpenToConnectDirectFail() {
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

		// set state open, assert job creation
		$this->registrationManager->circuitOpen();
		$this->assertFalse($this->registrationManager->hasToken());

		// simulate successful job execution (with direct fail)
		$this->registrationManager->expects($this->once())
								->method('sendRegistration')
								->willThrowException(new \Exception('This is more than unexpected'));
		$this->registrationManager->circuitHalfOpen();
		$this->assertFalse($this->registrationManager->hasToken());
		$this->assertFalse($this->registrationManager->isValidToken('1122334455'));
		$this->assertFalse($this->registrationManager->isValidToken('1122334466'));
		$this->assertEquals(SlupRegistrationManager::CIRCUIT_OPEN,
							$this->registrationManager->circuitState());
		$this->assertTrue($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertFalse($this->registrationManager->isCircuitClosed());
	}


	/**
	 * Ths exceptional case is in untested in many projects until the problem
	 * really occurs. So this test is not allowed to be commented out for no reason.
	 *
	 * @large
	 * Unfortunately, this test takes some seconds to complete
	 */
	public function testCircuitOpenToConnectRetryFail() {
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

        // set state open, assert job creation
		$this->registrationManager->circuitOpen();
		$this->assertFalse($this->registrationManager->hasToken());

		// simulate successful job execution (with a connection retry)
		$this->registrationManager->expects($this->exactly(2))
								->method('sendRegistration')
								->willThrowException(new \SoapFault('HTTP', "Could not connect to host"));
		$this->registrationManager->circuitHalfOpen();
		$this->assertFalse($this->registrationManager->hasToken());
		$this->assertFalse($this->registrationManager->isValidToken('1122334455'));
		$this->assertFalse($this->registrationManager->isValidToken('1122334466'));
		$this->assertEquals(SlupRegistrationManager::CIRCUIT_OPEN,
							$this->registrationManager->circuitState());
		$this->assertTrue($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertFalse($this->registrationManager->isCircuitClosed());
	}
}
