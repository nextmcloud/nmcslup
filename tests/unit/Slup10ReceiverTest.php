<?php

declare(strict_types=1);

namespace OCA\NextMagentaCloudSlup\UnitTest;

use OCA\NextMagentaCloudProvisioning\Rules\TariffRules;

use OCA\NextMagentaCloudProvisioning\Rules\UserAccountRules;

use OCA\NextMagentaCloudProvisioning\User\NmcUserService;
use OCA\NextMagentaCloudSlup\AppInfo\Application;
use OCA\NextMagentaCloudSlup\Controller\SlupApiController;

use OCA\NextMagentaCloudSlup\Registration\SlupRegistrationManager;
use OCA\NextMagentaCloudSlup\TestHelper\SoapTestCase;
use OCA\UserOIDC\Db\User;

use OCP\IConfig;

use OCP\ILogger;
use OCP\IRequest;

/**
 * This test must be run with --stderr, e.g.
 * phpunit --stderr --bootstrap tests/bootstrap.php tests/unit/SlupReceiverTest.php
 */
class Slup10ReceiverTest extends SoapTestCase {

	/**
	 * @var SlupApiController
	 */
	private $slupController;

	/**
	 * @var NmcUserService|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $userServiceMock;
	/**
	 * @var SlupRegistrationManager|\PHPUnỉt\Framework\MockObject\MockObject
	 */
	private $registrationManagerMock;


	public function setUp(): void {
		parent::setUp();
		$this->userServiceMock = $this->createMock(NmcUserService::class);
		$this->registrationManagerMock = $this->createMock(SlupRegistrationManager::class);
		$this->config = $this->getMockForAbstractClass(IConfig::class);
		$app = new \OCP\AppFramework\App(Application::APP_ID);
		$this->accountRules = new UserAccountRules($this->config,
			$app->getContainer()->get(ILogger::class),
			$this->userServiceMock);
		$this->slupController = new SlupApiController(Application::APP_ID,
			$app->getContainer()->get(IRequest::class),
			$app->getContainer()->get(ILogger::class),
			$this->registrationManagerMock,
			$app->getContainer()->get(TariffRules::class),
			$this->accountRules);
		$this->startServer($this->slupController, $this->slupController->getWsdlPath());
	}

	protected function assertTariffCreated($message, $tariff) {
		$this->registrationManagerMock->expects($this->once())
						   ->method('incrementRecvCount');
		$this->registrationManagerMock->expects($this->once())
						   ->method('isValidToken')
						   ->with($this->equalTo('269421944'))
						   ->willReturn(true);

		$this->userServiceMock->expects($this->once())
						->method('userExists')
						->with($this->equalTo('Telekom'), $this->equalTo('120049010000000000590615'))
						->willReturn(false);

		$this->userServiceMock->expects($this->once())
						->method('create')
						->with($this->equalTo('Telekom'), $this->equalTo('120049010000000000590615'), $this->equalTo('Christa.Haller4'),
							$this->equalTo('Christa.Haller4@ver.sul.t-online.de'), $this->equalTo(null), $this->equalTo($tariff),
							$this->equalTo(false), $this->equalTo(true))
						->willReturn('120049010000000000590615');


		$result = $this->callSoap($message);
		$this->assertSlupResponse("0010", $result, "Created");
	}

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
               <newfields><name>f048</name><val>1</val></newfields>
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

	public function testCreate3GB() {
		$this->assertTariffCreated(self::MESSAGE_BOOKED3GB, TariffRules::NMC_RATE_FREE3);
	}

	public const MESSAGE_BOOKED10GB = <<<XML
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
               <oldfields><name>name</name><val>Haller4</val></oldfields>
               <oldfields><name>zusa</name><val>Christa4</val></oldfields>
               <oldfields><name>f048</name><val>0</val></oldfields>
               <oldfields><name>f049</name><val>0</val></oldfields>
               <oldfields><name>f051</name><val>0</val></oldfields>
               <oldfields><name>f460</name><val>0</val></oldfields>
               <oldfields><name>f467</name><val>0</val></oldfields>
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
               <newfields><name>name</name><val>Haller4</val></newfields>
               <newfields><name>zusa</name><val>Christa4</val></newfields>
               <newfields><name>f048</name><val>1</val></newfields>
               <newfields><name>f049</name><val>0</val></newfields>
               <newfields><name>f051</name><val>0</val></newfields>
               <newfields><name>f460</name><val>1</val></newfields>
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

	public function testCreate10GB() {
		$this->assertTariffCreated(self::MESSAGE_BOOKED10GB, TariffRules::NMC_RATE_FREE10);
	}

	public const MESSAGE_BOOKED15GB = <<<XML
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
               <oldfields><name>name</name><val>Haller4</val></oldfields>
               <oldfields><name>zusa</name><val>Christa4</val></oldfields>
               <oldfields><name>f048</name><val>1</val></oldfields>
               <oldfields><name>f049</name><val>0</val></oldfields>
               <oldfields><name>f051</name><val>0</val></oldfields>
               <oldfields><name>f460</name><val>0</val></oldfields>
               <oldfields><name>f467</name><val>0</val></oldfields>
               <oldfields><name>f468</name><val>0</val></oldfields>
               <oldfields><name>f469</name><val>0</val></oldfields>
               <oldfields><name>f471</name><val>0</val></oldfields>
               <oldfields><name>f556</name><val>1</val></oldfields>
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
               <oldfields><name>extMail</name><val>Christa.H@ver.sul.t-online.de</val></oldfields>
               <!-- -->
               <newfields><name>name</name><val>Haller4</val></newfields>
               <newfields><name>zusa</name><val>Christa4</val></newfields>
               <newfields><name>f048</name><val>0</val></newfields>
               <newfields><name>f049</name><val>1</val></newfields>
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
               <newfields><name>extMail</name><val>Christa.Haller4@ver.sul.t-online.de</val></newfields>
            </slupClient:SLUP>
         </SOAP-ENV:Body>
      </SOAP-ENV:Envelope>
      XML;

	protected function assertTariffUpdated($message, $tariff, $mainEmail = 'Christa.Haller4@ver.sul.t-online.de', $extEmail = null) {
		$this->registrationManagerMock->expects($this->once())
						   ->method('incrementRecvCount');
		$this->registrationManagerMock->expects($this->once())
					 ->method('isValidToken')
					 ->with($this->equalTo('269421944'))
					 ->willReturn(true);
		$this->registrationManagerMock->expects($this->once())
					 ->method('isValidToken')
					 ->with($this->equalTo('269421944'))
					 ->willReturn(true);

		// return a dummy user
		$user = $this->getMockBuilder(User::class)
					 ->setMethods(['getUserId', 'getDisplayName'])
					 ->getMock();
		$user->method('getUserId')
					->willReturn('120049010000000000590615');
		$user->method('getDisplayName')
					->willReturn('ugly');

		$this->userServiceMock->expects($this->once())
				->method('userExists')
				->with($this->equalTo('Telekom'), $this->equalTo('120049010000000000590615'))
				->willReturn(true);

		$this->userServiceMock->expects($this->never())
						->method('create');

		$this->userServiceMock->expects($this->once())
						   ->method('update')
						   ->with($this->equalTo('Telekom'), $this->equalTo('120049010000000000590615'), $this->equalTo('Christa.Haller4'),
						   	$this->equalTo($mainEmail), $this->equalTo($extEmail), $this->equalTo($tariff),
						   	$this->equalTo(false), $this->equalTo(true))
						   ->willReturn('120049010000000000590615');

		$result = $this->callSoap($message);
		//fwrite(STDERR, strval($result));
		$this->assertSlupResponse("0010", $result, "Updated");
	}

	public function testUpdate15GB() {
		$this->assertTariffUpdated(self::MESSAGE_BOOKED15GB, TariffRules::NMC_RATE_S15,
			'Christa.Haller4@ver.sul.t-online.de', 'Christa.Haller4@ver.sul.t-online.de');
	}

	public const MESSAGE_BOOKED25GB = <<<XML
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
               <oldfields><name>alia</name><val>Christa.Hallhuber</val></oldfields>
               <oldfields><name>name</name><val>Haller4</val></oldfields>
               <oldfields><name>zusa</name><val>Christa4</val></oldfields>
               <oldfields><name>f048</name><val>1</val></oldfields>
               <oldfields><name>f049</name><val>0</val></oldfields>
               <oldfields><name>f051</name><val>0</val></oldfields>
               <oldfields><name>f460</name><val>0</val></oldfields>
               <oldfields><name>f467</name><val>0</val></oldfields>
               <oldfields><name>f468</name><val>0</val></oldfields>
               <oldfields><name>f469</name><val>0</val></oldfields>
               <oldfields><name>f471</name><val>0</val></oldfields>
               <oldfields><name>f556</name><val>1</val></oldfields>
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
               <oldfields><name>extMail</name><val>Christa.Haller4@magenta.de</val></oldfields>
               <!-- -->
               <newfields><name>name</name><val>Haller4</val></newfields>
               <newfields><name>zusa</name><val>Christa4</val></newfields>
               <newfields><name>f048</name><val>0</val></newfields>
               <newfields><name>f049</name><val>1</val></newfields>
               <newfields><name>f051</name><val>0</val></newfields>
               <newfields><name>f460</name><val>0</val></newfields>
               <newfields><name>f467</name><val>1</val></newfields>
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
               <newfields><name>extMail</name><val>Christa.Haller4@magenta.de</val></newfields>
            </slupClient:SLUP>
         </SOAP-ENV:Body>
      </SOAP-ENV:Envelope>
      XML;

	public function testUpdate25GB() {
		$this->assertTariffUpdated(self::MESSAGE_BOOKED25GB, TariffRules::NMC_RATE_S25,
			'Christa.Haller4@ver.sul.t-online.de', 'Christa.Haller4@magenta.de');
	}

	public const MESSAGE_BOOKED100GB = <<<XML
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
               <oldfields><name>f048</name><val>1</val></oldfields>
               <oldfields><name>f049</name><val>1</val></oldfields>
               <oldfields><name>f051</name><val>0</val></oldfields>
               <oldfields><name>f460</name><val>0</val></oldfields>
               <oldfields><name>f467</name><val>0</val></oldfields>
               <oldfields><name>f468</name><val>0</val></oldfields>
               <oldfields><name>f469</name><val>0</val></oldfields>
               <oldfields><name>f471</name><val>0</val></oldfields>
               <oldfields><name>f556</name><val>1</val></oldfields>
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
               <oldfields><name>mainEmail</name><val>Christa.H4@ver.sul.t-online.de</val></oldfields>
               <!-- -->
               <newfields><name>alia</name><val>Christa.Haller4</val></newfields>
               <newfields><name>name</name><val>Haller4</val></newfields>
               <newfields><name>zusa</name><val>Christa4</val></newfields>
               <newfields><name>f048</name><val>0</val></newfields>
               <newfields><name>f049</name><val>1</val></newfields>
               <newfields><name>f051</name><val>0</val></newfields>
               <newfields><name>f460</name><val>0</val></newfields>
               <newfields><name>f467</name><val>0</val></newfields>
               <newfields><name>f468</name><val>1</val></newfields>
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

	public function testUpdate100GB() {
		$this->assertTariffUpdated(self::MESSAGE_BOOKED100GB, TariffRules::NMC_RATE_M100, 'Christa.Haller4@ver.sul.t-online.de', null);
	}

	// this is a rebooked custoer from Rückgewinngungsprozess
	// it should be still in the system for 60 days and is updated if found
	public const MESSAGE_BOOKED500GB = <<<XML
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
            <oldfields><name>f467</name><val>0</val></oldfields>
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
            <!-- -->
            <newfields><name>name</name><val>Haller4</val></newfields>
            <newfields><name>zusa</name><val>Christa4</val></newfields>
            <newfields><name>f048</name><val>0</val></newfields>
            <newfields><name>f049</name><val>0</val></newfields>
            <newfields><name>f051</name><val>0</val></newfields>
            <newfields><name>f460</name><val>0</val></newfields>
            <newfields><name>f467</name><val>0</val></newfields>
            <newfields><name>f468</name><val>0</val></newfields>
            <newfields><name>f469</name><val>1</val></newfields>
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
            <newfields><name>extMail</name><val>Christa.Haller4@magna.de</val></newfields>
         </slupClient:SLUP>
      </SOAP-ENV:Body>
   </SOAP-ENV:Envelope>
   XML;

	public function testUpdate500GB() {
		//	$this->assertTariffUpdated(self::MESSAGE_BOOKED500GB, TariffRules::NMC_RATE_L500, null, null);

		// current displayname workaround
		$this->assertTariffUpdated(self::MESSAGE_BOOKED500GB, TariffRules::NMC_RATE_L500, 'Christa.Haller4@magna.de', 'Christa.Haller4@magna.de');
	}


	// it should be still in the system for 60 days and is updated if found
	public const MESSAGE_BOOKED1TB = <<<XML
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
               <oldfields><name>f467</name><val>0</val></oldfields>
               <oldfields><name>f468</name><val>1</val></oldfields>
               <oldfields><name>f469</name><val>1</val></oldfields>
               <oldfields><name>f471</name><val>0</val></oldfields>
               <oldfields><name>f556</name><val>1</val></oldfields>
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
               <!-- -->
               <newfields><name>alia</name><val>Christa.Haller4</val></newfields>
               <newfields><name>name</name><val>Haller4</val></newfields>
               <newfields><name>zusa</name><val>Christa4</val></newfields>
               <newfields><name>f048</name><val>0</val></newfields>
               <newfields><name>f049</name><val>0</val></newfields>
               <newfields><name>f051</name><val>0</val></newfields>
               <newfields><name>f460</name><val>0</val></newfields>
               <newfields><name>f467</name><val>0</val></newfields>
               <newfields><name>f468</name><val>1</val></newfields>
               <newfields><name>f469</name><val>1</val></newfields>
               <newfields><name>f471</name><val>1</val></newfields>
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
   
	public function testUpdate1TB() {
		$this->assertTariffUpdated(self::MESSAGE_BOOKED1TB, TariffRules::NMC_RATE_XL1);
	}
   
	// it should be still in the system for 60 days and is updated if found
	public const MESSAGE_BOOKED5TB = <<<XML
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
               <oldfields><name>f467</name><val>0</val></oldfields>
               <oldfields><name>f468</name><val>1</val></oldfields>
               <oldfields><name>f469</name><val>1</val></oldfields>
               <oldfields><name>f471</name><val>0</val></oldfields>
               <oldfields><name>f556</name><val>1</val></oldfields>
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
               <newfields><name>f051</name><val>1</val></newfields>
               <newfields><name>f460</name><val>0</val></newfields>
               <newfields><name>f467</name><val>0</val></newfields>
               <newfields><name>f468</name><val>1</val></newfields>
               <newfields><name>f469</name><val>1</val></newfields>
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
   
	public function testUpdate5TB() {
		$this->assertTariffUpdated(self::MESSAGE_BOOKED5TB, TariffRules::NMC_RATE_XXL5);
	}
   




	public const MESSAGE_WITHDRAW = <<<XML
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
               <oldfields><name>f468</name><val>1</val></oldfields>
               <oldfields><name>f469</name><val>0</val></oldfields>
               <oldfields><name>f471</name><val>0</val></oldfields>
               <oldfields><name>f556</name><val>1</val></oldfields>
               <oldfields><name>s048</name><val>0</val></oldfields>
               <oldfields><name>s049</name><val>0</val></oldfields>
               <oldfields><name>s051</name><val>0</val></oldfields>
               <oldfields><name>s460</name><val>0</val></oldfields>
               <oldfields><name>s467</name><val>0</val></oldfields>
               <oldfields><name>s468</name><val>0</val></oldfields>
               <oldfields><name>s469</name><val>0</val></oldfields>
               <oldfields><name>s471</name><val>0</val></oldfields>
               <oldfields><name>s556</name><val>1</val></oldfields>
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
               <newfields><name>f556</name><val>0</val></newfields>
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

	/**
	 * Expected result: user is never created
	 */
	public function testWithdrawNonExistingUser() {
		$this->registrationManagerMock->expects($this->once())
			->method('incrementRecvCount');

		$this->registrationManagerMock->expects($this->once())
		 ->method('isValidToken')
		 ->with($this->equalTo('269421944'))
		 ->willReturn(true);

		$this->userServiceMock->expects($this->once())
				->method('userExists')
				->with($this->equalTo('Telekom'), $this->equalTo('120049010000000000590615'))
				->willReturn(false);

		$this->userServiceMock->expects($this->never())
		 ->method('create');
		$this->userServiceMock->expects($this->never())
		 ->method('update');

		$result = $this->callSoap(self::MESSAGE_WITHDRAW);
		$this->assertSlupResponse("0000", $result, "No tariff no new account");
	}
   

	/**
	 * Expected result: user is set to read-only (quota 0 B)
	 */
	public function testWithdrawExistingUser() {
		$this->registrationManagerMock->expects($this->once())
						   ->method('incrementRecvCount');
		$this->registrationManagerMock->expects($this->once())
		 ->method('isValidToken')
		 ->with($this->equalTo('269421944'))
		 ->willReturn(true);

		// return a dummy user
		$user = $this->getMockBuilder(User::class)
			   ->setMethods(['getUserId', 'getDisplayName'])
			   ->getMock();
		$user->method('getUserId')
		   ->willReturn('120049010000000000590615');
		$user->method('getDisplayName')
		   ->willReturn('ugly');

		$this->userServiceMock->expects($this->once())
		   ->method('userExists')
		   ->with($this->equalTo('Telekom'), $this->equalTo('120049010000000000590615'))
		   ->willReturn(true);

		$withdrawTime = \DateTime::createFromFormat(\DateTimeInterface::ISO8601, "2021-11-06T14:27:44Z");
		$deletionTime = clone $withdrawTime;
		$deletionTime->add(new \DateInterval("P60D"));
		$this->userServiceMock->expects($this->once())
			   ->method('markDeletion')
			   ->with($this->equalTo('120049010000000000590615'), $this->equalTo($withdrawTime))
			   ->willReturn(new \DateTime());
	
		$this->userServiceMock->expects($this->never())
			->method('create');

		$this->userServiceMock->expects($this->once())
			->method('update')
			->with($this->equalTo('Telekom'), $this->equalTo('120049010000000000590615'), $this->equalTo('Christa.Haller4'),
				$this->equalTo('Christa.Haller4@ver.sul.t-online.de'), $this->isNull(), $this->isType('string'),
				$this->isFalse(), $this->isFalse())
			->willReturn('120049010000000000590615');

		$result = $this->callSoap(self::MESSAGE_WITHDRAW);
		$this->assertSlupResponse("0010", $result, "Withdrawn");
	}

	public const MESSAGE_LOCKED = <<<XML
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
               <oldfields><name>f468</name><val>1</val></oldfields>
               <oldfields><name>f469</name><val>0</val></oldfields>
               <oldfields><name>f471</name><val>0</val></oldfields>
               <oldfields><name>f556</name><val>1</val></oldfields>
               <oldfields><name>s048</name><val>0</val></oldfields>
               <oldfields><name>s049</name><val>0</val></oldfields>
               <oldfields><name>s051</name><val>0</val></oldfields>
               <oldfields><name>s460</name><val>0</val></oldfields>
               <oldfields><name>s467</name><val>0</val></oldfields>
               <oldfields><name>s468</name><val>0</val></oldfields>
               <oldfields><name>s469</name><val>0</val></oldfields>
               <oldfields><name>s471</name><val>0</val></oldfields>
               <oldfields><name>s556</name><val>1</val></oldfields>
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
               <newfields><name>s556</name><val>1</val></newfields>
               <newfields><name>anid</name><val>120049010000000000590615</val></newfields>
               <newfields><name>usta</name><val>3</val></newfields>
               <newfields><name>mainEmail</name><val>Christa.Haller4@ver.sul.t-online.de</val></newfields>
           </slupClient:SLUP>
         </SOAP-ENV:Body>
      </SOAP-ENV:Envelope>
      XML;

	public function testLockNonExistingUser() {
		$this->registrationManagerMock->expects($this->once())
						   ->method('incrementRecvCount');
		$this->registrationManagerMock->expects($this->once())
		 ->method('isValidToken')
		 ->with($this->equalTo('269421944'))
		 ->willReturn(true);

		$this->userServiceMock->expects($this->once())
				->method('userExists')
				->with($this->equalTo('Telekom'), $this->equalTo('120049010000000000590615'))
				->willReturn(false);

		$this->userServiceMock->expects($this->never())
		 ->method('create');
		$this->userServiceMock->expects($this->never())
		 ->method('update');

		$result = $this->callSoap(self::MESSAGE_LOCKED);
		$this->assertSlupResponse("0000", $result, "Locked no new account");
	}


	public function testLockExistingUser() {
		$this->registrationManagerMock->expects($this->once())
						   ->method('incrementRecvCount');
		$this->registrationManagerMock->expects($this->once())
		 ->method('isValidToken')
		 ->with($this->equalTo('269421944'))
		 ->willReturn(true);

		// return a dummy user
		$user = $this->getMockBuilder(User::class)
			   ->setMethods(['getUserId', 'getDisplayName'])
			   ->getMock();
		$user->method('getUserId')
		   ->willReturn('120049010000000000590615');
		$user->method('getDisplayName')
		   ->willReturn('ugly');

		$this->userServiceMock->expects($this->once())
		   ->method('userExists')
		   ->with($this->equalTo('Telekom'), $this->equalTo('120049010000000000590615'))
		   ->willReturn(true);

		$this->userServiceMock->expects($this->never())
			->method('create');

		$this->userServiceMock->expects($this->once())
			->method('update')
			->with($this->equalTo('Telekom'), $this->equalTo('120049010000000000590615'), $this->equalTo('Christa.Haller4'),
				$this->equalTo('Christa.Haller4@ver.sul.t-online.de'), $this->isNull(), $this->isType('string'),
				$this->equalTo(false), $this->equalTo(false))
			->willReturn('120049010000000000590615');
		$result = $this->callSoap(self::MESSAGE_LOCKED);
		$this->assertSlupResponse("0010", $result, "Locked");
	}

	public function testDisconnectInvalidToken() {
		$this->registrationManagerMock->expects($this->once())
		 ->method('isValidToken')
		 ->with($this->equalTo('269421955'))
		 ->willReturn(false);

		$invalidTestMessage = <<<XML
         <?xml version="1.0" encoding="UTF-8"?>
         <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
                           xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
                           xmlns:slupClient="http://slup2soap.idm.telekom.com/slupClient/"
                           xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
            <SOAP-ENV:Body>
               <slupClient:SLUPDisconnect>
                  <token>269421955</token>
               </slupClient:SLUPDisconnect>
            </SOAP-ENV:Body>
         </SOAP-ENV:Envelope>
         XML;
		$result = $this->callSoap($invalidTestMessage);
		$this->assertSlupResponse("F003", $result, "invalid token");
	}

	public function testDisconnectTestConnection() {
		$connectionTestMessage = <<<XML
         <?xml version="1.0" encoding="UTF-8"?>
         <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
                           xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
                           xmlns:slupClient="http://slup2soap.idm.telekom.com/slupClient/"
                           xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
            <SOAP-ENV:Body>
               <slupClient:SLUPDisconnect>
                  <token>0</token>
               </slupClient:SLUPDisconnect>
            </SOAP-ENV:Body>
         </SOAP-ENV:Envelope>
         XML;
		$result = $this->callSoap($connectionTestMessage);
		$this->assertSlupResponse("0000", $result, "connection ok");
	}

	public const MESSAGE_NORATE = <<<XML
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

	public function testCreateNoRate() {
		$this->assertTariffCreated(self::MESSAGE_NORATE, TariffRules::NMC_RATE_S25);
	}



}
