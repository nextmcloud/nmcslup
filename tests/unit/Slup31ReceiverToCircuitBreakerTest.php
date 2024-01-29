<?php

declare(strict_types=1);

namespace OCA\NextMagentaCloudSlup\UnitTest;

use OCA\NextMagentaCloudProvisioning\Rules\TariffRules;
use OCA\NextMagentaCloudProvisioning\Rules\UserAccountRules;
use OCA\NextMagentaCloudSlup\AppInfo\Application;
use OCA\NextMagentaCloudSlup\Controller\SlupApiController;
use OCA\NextMagentaCloudSlup\Registration\SlupRegistrationManager;
use OCA\NextMagentaCloudSlup\TestHelper\SoapTestCase;


use OCP\Http\Client\IClientService;
use OCP\ICacheFactory;
use OCP\IConfig;

use OCP\ILogger;

use OCP\IRequest;
use OCP\IURLGenerator;

class Slup31ReceiverToCircuitBreakerTest extends SoapTestCase {

	/**
	 * @var IConfig
	 */
	protected $config;

	public function setUp(): void {
		parent::setUp();
		$app = new \OCP\AppFramework\App(Application::APP_ID);
		$this->config = $this->getMockForAbstractClass(IConfig::class);
		$this->config->expects($this->at(0))
					->method("getAppValue")
					->with($this->equalTo('nmcslup'), $this->equalTo('slupcontrolintv'))
					->willReturn('300');

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
	}


	// ---- real messages tests ----

	public const MESSAGE_BOOKED3GB = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
                        xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
                        xmlns:slupClient="http://slup2soap.idm.telekom.com/slupClient/"
                        xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
        <SOAP-ENV:Body>
            <slupClient:SLUP>
                <token>269421944</token>
                <request>UTS</request>
                <changeTime>2021-11-06T14:27:44Z</changeTime>
                <oldfields><name>alia</name><val>Christa.Haller4</val></oldfields>
                <oldfields><name>name</name><val>Haller4</val></oldfields>
                <oldfields><name>zusa</name><val>Christa4</val></oldfields>
                <oldfields><name>f048</name><val>0</val></oldfields>
                <oldfields><name>f049</name><val>0</val></oldfields>
                <oldfields><name>f051</name><val>0</val></oldfields>
                <oldfields><name>f460</name><val>0</val></oldfields>
                <oldfields><name>f467</name><val>1</val></oldfields>
                <oldfields><name>f468</name><val>0</val></oldfields>
                <oldfields><name>f469</name><val>0</val></oldfields>
                <oldfields><name>f471</name><val>0</val></oldfields>
                <oldfields><name>f556</name><val>0</val></oldfields>
                <oldfields><name>s048</name><val>0</val></oldfields>
                <oldfields><name>s049</name><val>0</val></oldfields>
                <oldfields><name>s051</name><val>0</val></oldfields>
                <oldfields><name>s460</name><val>0</val></oldfields>
                <oldfields><name>s467</name><val>0</val></oldfields>
                <oldfields><name>s468</name><val>0</val></oldfields>
                <oldfields><name>s469</name><val>0</val></oldfields>
                <oldfields><name>s471</name><val>0</val></oldfields>
                <oldfields><name>s556</name><val>0</val></oldfields>
                <oldfields><name>anid</name><val>120049010000000000590615</val></oldfields>
                <oldfields><name>usta</name><val>3</val></oldfields>
                <oldfields><name>mainEmail</name><val>Christa.Haller4@ver.sul.t-online.de</val></oldfields>
                <!-- -->
                <newfields><name>alia</name><val>Christa.Haller4</val></newfields>
                <newfields><name>name</name><val>Haller4</val></newfields>
                <newfields><name>zusa</name><val>Christa4</val></newfields>
                <newfields><name>f048</name><val>0</val></newfields>
                <newfields><name>f049</name><val>0</val></newfields>
                <newfields><name>f051</name><val>0</val></newfields>
                <newfields><name>f460</name><val>0</val></newfields>
                <newfields><name>f467</name><val>0</val></newfields>
                <newfields><name>f468</name><val>0</val></newfields>
                <newfields><name>f469</name><val>0</val></newfields>
                <newfields><name>f471</name><val>0</val></newfields>
                <newfields><name>f556</name><val>1</val></newfields>
                <newfields><name>s048</name><val>0</val></newfields>
                <newfields><name>s049</name><val>0</val></newfields>
                <newfields><name>s051</name><val>0</val></newfields>
                <newfields><name>s460</name><val>0</val></newfields>
                <newfields><name>s467</name><val>0</val></newfields>
                <newfields><name>s468</name><val>0</val></newfields>
                <newfields><name>s469</name><val>0</val></newfields>
                <newfields><name>s471</name><val>0</val></newfields>
                <newfields><name>s556</name><val>0</val></newfields>
                <newfields><name>anid</name><val>120049010000000000590615</val></newfields>
                <newfields><name>usta</name><val>3</val></newfields>
                <newfields><name>mainEmail</name><val>Christa.Haller4@ver.sul.t-online.de</val></newfields>
            </slupClient:SLUP>
        </SOAP-ENV:Body>
        </SOAP-ENV:Envelope>
        XML;

	public function testInvalidTokenOnOpen() {
		$this->registrationManager->circuitOpen();
		$this->assertFalse($this->registrationManager->hasToken());
		$this->assertEquals(SlupRegistrationManager::CIRCUIT_OPEN,
			$this->registrationManager->circuitState());
		$this->assertTrue($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertFalse($this->registrationManager->isCircuitClosed());

		$result = $this->callSoap(self::MESSAGE_BOOKED3GB);
		$this->assertSlupResponse("F003", $result, "invalid token");
	}

	public function testInvalidTokenOnClosed() {
		$this->registrationManager->setToken('1122334455');
		$this->registrationManager->circuitClosed();
		$this->assertTrue($this->registrationManager->hasToken());
		$this->assertEquals(SlupRegistrationManager::CIRCUIT_CLOSED,
			$this->registrationManager->circuitState());
		$this->assertFalse($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertTrue($this->registrationManager->isCircuitClosed());

		$result = $this->callSoap(self::MESSAGE_BOOKED3GB);
		$this->assertSlupResponse("F003", $result, "invalid token");
	}

	public function testValidTokenOnClosed() {
		$this->accountRulesMock->expects($this->once())
								->method('deriveAccountState')
								->with($this->equalTo('120049010000000000590615'), $this->equalTo('Christa.Haller4'),
									$this->equalTo('Christa.Haller4@ver.sul.t-online.de'), $this->isNull(),
									$this->isType('string'))
								->willReturn(['allowed' => true, 'reason' => 'Updated', 'changed' => true ]);

		$this->registrationManager->setToken('269421944');
		$this->registrationManager->circuitClosed();
		$this->assertTrue($this->registrationManager->hasToken());
		$this->assertEquals(SlupRegistrationManager::CIRCUIT_CLOSED,
			$this->registrationManager->circuitState());
		$this->assertFalse($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertTrue($this->registrationManager->isCircuitClosed());

		$result = $this->callSoap(self::MESSAGE_BOOKED3GB);
		$this->assertSlupResponse("0010", $result, "Updated");
	}

	public const MESSAGE_CONNECT = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
                        xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
                        xmlns:slupClient="http://slup2soap.idm.telekom.com/slupClient/"
                        xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
        <SOAP-ENV:Body>
            <slupClient:SLUPConnect>
                <token>269421944</token>
                </slupClient:SLUPConnect>
        </SOAP-ENV:Body>
        </SOAP-ENV:Envelope>
        XML;


	public function testInvalidConnectTokenOnOpen() {
		$this->registrationManager->clearToken();
		$this->registrationManager->circuitOpen();
		$this->assertFalse($this->registrationManager->hasToken());
		$this->assertEquals(SlupRegistrationManager::CIRCUIT_OPEN,
			$this->registrationManager->circuitState());
		$this->assertTrue($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertFalse($this->registrationManager->isCircuitClosed());

		$result = $this->callSoap(self::MESSAGE_CONNECT);
		$this->assertSlupResponse("F003", $result, "invalid token");
	}
	
	public function testValidConnectTokenOnClosed() {
		$this->registrationManager->setToken('269421944');
		$this->registrationManager->circuitClosed();
		$this->assertTrue($this->registrationManager->hasToken());
		$this->assertEquals(SlupRegistrationManager::CIRCUIT_CLOSED,
			$this->registrationManager->circuitState());
		$this->assertFalse($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertTrue($this->registrationManager->isCircuitClosed());

		$result = $this->callSoap(self::MESSAGE_CONNECT);
		$this->assertSlupResponse("0000", $result, "connected");
	}

	public function testInvalidConnectTokenOnClosed() {
		$this->registrationManager->setToken('1122334455');
		$this->registrationManager->circuitClosed();
		$this->assertTrue($this->registrationManager->hasToken());
		$this->assertEquals(SlupRegistrationManager::CIRCUIT_CLOSED,
			$this->registrationManager->circuitState());
		$this->assertFalse($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertTrue($this->registrationManager->isCircuitClosed());

		$result = $this->callSoap(self::MESSAGE_CONNECT);
		$this->assertSlupResponse("F003", $result, "invalid token");
	}

	public const MESSAGE_DISCONNECT = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
                    xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
                    xmlns:slupClient="http://slup2soap.idm.telekom.com/slupClient/"
                    xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <SOAP-ENV:Body>
        <slupClient:SLUPDisconnect>
            <token>269421944</token>
        </slupClient:SLUPDisconnect>
    </SOAP-ENV:Body>
    </SOAP-ENV:Envelope>
    XML;


	public function testInvalidDisconnectTokenOnOpen() {
		$this->registrationManager->clearToken();
		$this->registrationManager->circuitOpen();
		$this->assertFalse($this->registrationManager->hasToken());
		$this->assertEquals(SlupRegistrationManager::CIRCUIT_OPEN,
			$this->registrationManager->circuitState());
		$this->assertTrue($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertFalse($this->registrationManager->isCircuitClosed());

		$result = $this->callSoap(self::MESSAGE_DISCONNECT);
		$this->assertSlupResponse("F003", $result, "invalid token");
	}

	public function testInvalidDisconnectTokenOnClosed() {
		$this->registrationManager->setToken('1122334455');
		$this->registrationManager->circuitClosed();
		$this->assertTrue($this->registrationManager->hasToken());
		$this->assertEquals(SlupRegistrationManager::CIRCUIT_CLOSED,
			$this->registrationManager->circuitState());
		$this->assertFalse($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertTrue($this->registrationManager->isCircuitClosed());

		$result = $this->callSoap(self::MESSAGE_DISCONNECT);
		$this->assertSlupResponse("F003", $result, "invalid token");
		$this->assertTrue($this->registrationManager->hasToken());
		$this->assertEquals(SlupRegistrationManager::CIRCUIT_CLOSED,
			$this->registrationManager->circuitState());
		$this->assertFalse($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertTrue($this->registrationManager->isCircuitClosed());
	}

	public function testDisconnectCircuitOpened() {
		$this->registrationManager->setToken('269421944');
		$this->registrationManager->circuitClosed();
		$this->assertTrue($this->registrationManager->hasToken());
		$this->assertEquals(SlupRegistrationManager::CIRCUIT_CLOSED,
			$this->registrationManager->circuitState());
		$this->assertFalse($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertTrue($this->registrationManager->isCircuitClosed());

		$result = $this->callSoap(self::MESSAGE_DISCONNECT);
		$this->assertSlupResponse("0000", $result, "disconnected");
		$this->assertFalse($this->registrationManager->hasToken());
		$this->assertEquals(SlupRegistrationManager::CIRCUIT_OPEN,
			$this->registrationManager->circuitState());
		$this->assertTrue($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertFalse($this->registrationManager->isCircuitClosed());
	}


	public function testAlreadyConnectedA007ForcedDisconnect() {
		$detail = new \stdClass;
		$detail->FaultResponse = new \stdClass;
		$detail->FaultResponse->code = 'A007';
		$detail->FaultResponse->message = 'lockfile exists';
		$this->registrationManager->expects($this->once())
							->method('sendRegistration')
							->will($this->throwException(new \SoapFault('SOAP-ENV:Server',
								'Application error', null, $detail)));

		$this->registrationManager->setToken('1122334455');
		$this->registrationManager->circuitHalfOpen();
		$this->assertTrue($this->registrationManager->hasToken());
		$this->assertEquals(SlupRegistrationManager::CIRCUIT_CLOSED,
			$this->registrationManager->circuitState());
		$this->assertFalse($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertTrue($this->registrationManager->isCircuitClosed());

		// with the next message, we must signal a lost token
		$result = $this->callSoap(self::MESSAGE_BOOKED3GB);
		$this->assertSlupResponse("F003", $result, "invalid token");
		$this->assertTrue($this->registrationManager->hasToken());
		
		// surprisingly, the reconnect already assumes normal operation
		// so it is executed in closed state
		$this->assertEquals(SlupRegistrationManager::CIRCUIT_CLOSED,
			$this->registrationManager->circuitState());
		$this->assertFalse($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertTrue($this->registrationManager->isCircuitClosed());
		
		// the F003 lost token provokes a disconnect (with the old token)
		// so we assume that this is not an attack vector
		$result = $this->callSoap(self::MESSAGE_DISCONNECT);
		$this->assertSlupResponse("0000", $result, "disconnected");
		$this->assertFalse($this->registrationManager->hasToken());
		$this->assertEquals(SlupRegistrationManager::CIRCUIT_OPEN,
			$this->registrationManager->circuitState());
		$this->assertTrue($this->registrationManager->isCircuitOpen());
		$this->assertFalse($this->registrationManager->isCircuitHalfOpen());
		$this->assertFalse($this->registrationManager->isCircuitClosed());
	}
}
