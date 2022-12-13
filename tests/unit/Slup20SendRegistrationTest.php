<?php

declare(strict_types=1);

namespace OCA\NextMagentaCloudSlup\UnitTest;

use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\ICacheFactory;
use OCP\Http\Client\IClientService;

use PHPUnit\Framework\TestCase;

use OCA\NextMagentaCloudSlup\AppInfo\Application;
use OCA\NextMagentaCloudSlup\Registration\SlupRegistrationManager;
use OCA\NextMagentaCloudSlup\Registration\SlupConnectException;

class Slup20SendRegistrationTest extends TestCase {

	/**
	 * @var IConfig
	 */
	protected $config;


	public function setUp(): void {
		parent::setUp();
		$app = new \OCP\AppFramework\App(Application::APP_ID);
		$this->config = $app->getContainer()->get(IConfig::class);
		$this->urlGenerator = $app->getContainer()->get(IURLGenerator::class);
		$this->registrationManager = new SlupRegistrationManager($app->getContainer()->get(ILogger::class),
																$this->urlGenerator,
																$app->getContainer()->get(IClientService::class),
																$this->config,
																$app->getContainer()->get(ICacheFactory::class));
		$this->soapClientMock = $this->getMockFromWsdl($this->registrationManager->getWsdlPath());
		$this->registrationManager->replaceSoapClient($this->soapClientMock);

		$this->realConnectManager = new SlupRegistrationManager($app->getContainer()->get(ILogger::class),
																$this->urlGenerator,
																$app->getContainer()->get(IClientService::class),
																$this->config,
																$app->getContainer()->get(ICacheFactory::class));
	}

	public function testSendRegistrationOk0000() {
		$eventEndpoint = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.SlupApi.soapCall');

		$result = new \stdClass;
		$result->token = '12233445678';
		$result->SLUPreturncode = '0000';
		$result->detail = 'ok';
		$this->soapClientMock->expects($this->once())
			->method('startSLUP2')
			->with($this->equalTo([ 'slupURL' => $eventEndpoint ]))
			->willReturn($result);
		$token = $this->registrationManager->sendRegistration('https://slup2soap00.idm.ver.sul.t-online.de/slupServiceX/',
															'10TVL0SLUP0000004901NEXTMAGENTACLOUD0000',
															'<secret>');
		$this->assertEquals('12233445678', $token);
	}

	// original fault message for A007 case documentation only
	public const MESSAGE_A007_ALREADY_CONNECTED = <<<XML
        <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
                            xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
                            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                            xmlns:xsd="http://www.w3.org/2001/XMLSchema"
                            xmlns:slupns="http://slup2soap.idm.telekom.com/slupService/"
                            xmlns:c14n="http://www.w3.org/2001/10/xml-exc-c14n#"
                            xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
                            xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"
                            xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
            <SOAP-ENV:Body>
            <SOAP-ENV:Fault>
                <faultcode>SOAP-ENV:Server</faultcode>
                <faultstring>Application error</faultstring>
                <detail>
                <slupns:FaultResponse>
                    <code>A007</code>
                    <message>lockfile exists</message>
                </slupns:FaultResponse>
                </detail>
            </SOAP-ENV:Fault>
            </SOAP-ENV:Body>
        </SOAP-ENV:Envelope>
        XML;


	public function testSendRegistrationConnectedA007() {
		$eventEndpoint = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.SlupApi.soapCall');

        $this->registrationManager->forceReconnect();
		$detail = new \stdClass;
		$detail->FaultResponse = new \stdClass;
		$detail->FaultResponse->code = 'A007';
		$detail->FaultResponse->message = 'lockfile exists';
		$this->soapClientMock->expects($this->once())
			->method('startSLUP2')
			->with($this->equalTo([ 'slupURL' => $eventEndpoint ]))
			->will($this->throwException(new \SoapFault('SOAP-ENV:Server', 'Application error', null, $detail)));
		
        try {
            $token = $this->registrationManager->sendRegistration('https://slup2soap00.idm.ver.sul.t-online.de/slupServiceX/',
                '10TVL0SLUP0000004901NEXTMAGENTACLOUD0000',
                '<secret>');
            $this->fail("Unexpected behavior");
        } catch (\SoapFault $sf) {
			$slupDetailCode = $this->registrationManager->getSlupSoapFaultDetail($sf, 'code', 'none');
            $this->assertEquals("A007", $slupDetailCode);
            
        } 

    }

	public function testSendRegistrationSLUPFail() {
		$eventEndpoint = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.SlupApi.soapCall');

		$result = new \stdClass;
		$result->token = '12233445679';
		$result->SLUPreturncode = 'F023';
		$result->detail = 'SLUPDisconnect failed (nonWSI), check connectivity';
		$this->soapClientMock->expects($this->once())
			->method('startSLUP2')
			->with($this->equalTo([ 'slupURL' => $eventEndpoint ]))
			->willReturn($result);
		$this->soapClientMock->expects($this->atLeastOnce())
			->method('__getLastResponse')
			->willReturn("<somemessage>");

		$this->expectException(SlupConnectException::class);
		$this->registrationManager->sendRegistration('https://slup2soap00.idm.ver.sul.t-online.de/slupService/',
													'10TVL0SLUP0000004901NEXTMAGENTACLOUD0000',
													'<secret>');
        $this->assertFalse($this->registrationManager->hasToken());                                            
	}

	public function testSendRegistrationNullFail() {
		//$this->expectException(SlupConnectException::class);
		$this->expectException(\Exception::class);
		$this->realConnectManager->sendRegistration('https://slup2soap00.idm.ver.sul.t-online.de/inaccessibleService/',
													'10TVL0SLUP0000004901NEXTMAGENTACLOUD0000',
													'<secret>');
	}
}
