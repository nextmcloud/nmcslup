<?php

declare(strict_types=1);

namespace OCA\NextMagentaCloudSlup\AppInfo;

use OCP\ILogger;
use OCP\App\IAppManager;
use OCP\AppFramework\App;

use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;


class Application extends App implements IBootstrap {
	public const APP_ID = 'nmcslup';


	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		// Register the composer autoloader for packages shipped by this app, if applicable
		//include_once __DIR__ . '/../../vendor/autoload.php';
	}

	/**
	 * The boot method seems to be called cyclic in developer mode,
	 * so we cannot use it for SLUP registration on boot
	 */
	public function boot(IBootContext $context): void {
        $appMgr = $this->getContainer()->get(IAppManager::class);
        $logger = $this->getContainer()->get(ILogger::class);
        if ( !$appMgr->isInstalled('nmcprovisioning') ) {
            $logger->error("NmcProvisioning app not installed or enabled, but NmcSlup depends on it!");
        }

        // TODO: may check also for minimal version of dependent app
    }
}
