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

class SlupSendRegistrationTest extends TestCase {

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
																$app->getContainer()->get(ICacheFactory::class) );
		$this->soapClientMock = $this->getMockFromWsdl($this->registrationManager->getWsdlPath());
		$this->registrationManager->replaceSoapClient($this->soapClientMock);

		$this->realConnectManager = new SlupRegistrationManager($app->getContainer()->get(ILogger::class),
																$this->urlGenerator,
																$app->getContainer()->get(IClientService::class),
																$this->config,
																$app->getContainer()->get(ICacheFactory::class));
	}

	/**
	 * This tests really tries to connect an provokes error
	 */
	public function testSendRegistrationEndpointOk() {

		//$this->expectException(SlupConnectException::class);
		try {
			// take the current
			$token = $this->realConnectManager->sendRegistration('https://slup2soap00.idm.ver.sul.t-online.de/slupService/',
				'10TVL0SLUP0000004901NEXTMAGENTACLOUDDEV1',
				'AA06485C-6C44-47E3-A71A-F3DE2E50A769', true);
			$this->fail("Expected SoapFault, none thrown.");
		} catch (\SoapFault $soapEx) {
			//$this->assertEquals("SOAP:Server", $soapEx->faultcode);
			//$this->assertEquals("Application error", strval($soapEx->faultstring));
		}
	}

	/**
	 * This tests really tries to connect an provokes error
	 */
	public function testSendRegistrationNetworkFail() {

		//$this->expectException(SlupConnectException::class);
		try {
			$token = $this->realConnectManager->sendRegistration('https://dont.exist.de/',
				'10TVL0SLUP0000004901NEXTMAGENTACLOUD0000',
				'<secret>');
			$this->fail("Expected SoapFault, none thrown.");
		} catch (\SoapFault $soapEx) {
			$this->assertEquals("HTTP", $soapEx->faultcode);
			$this->assertEquals("Could not connect to host", strval($soapEx->faultstring));
		}
	}
}
