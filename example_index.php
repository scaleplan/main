<?php

ob_start();

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use App\Classes\App;
use Scaleplan\Helpers\Helper;
use function Scaleplan\Helpers\get_required_env;
use Scaleplan\Access\Access;
use Scaleplan\Access\AccessModify;
use function Scaleplan\DependencyInjection\get_required_container;
use Scaleplan\Main\Interfaces\ControllerExecutorInterface;
use Scaleplan\AccessToFiles\AccessToFilesInterface;

try {
    $dotEnv = new Dotenv\Dotenv($_SERVER['DOCUMENT_ROOT']);
    $dotEnv->load();

    session_start(['name' => get_required_env('PROJECT_NAME'), 'cookie_domain' => '.' . get_required_env('DOMAIN')]);
    /** @var \Scaleplan\Main\Interfaces\UserInterface $currentUser */
    $currentUser = get_required_container(\Scaleplan\Main\Interfaces\UserInterface::class);
    App::init();
    $accessConfigPath = get_required_env('ACCESS_CONFIG_PATH');
    Access::getInstance($currentUser->getId(), $accessConfigPath);
    /** @var AccessModify $accessModify */
    $accessModify = AccessModify::getInstance($currentUser->getId(), $accessConfigPath);
    $accessModify->saveAccessRightsToCache();

    /** @var \Scaleplan\Main\ControllerExecutor $executor */
    $executor = get_required_container(ControllerExecutorInterface::class);
    $executor->execute()->send();

    session_write_close();
    fastcgi_finish_request();
    Helper::allDBCommit(App::getDatabases());

    /** @var AccessToFilesInterface $af */
    $af = get_required_container(AccessToFilesInterface::class);
    $af->allowFiles();

} catch (Throwable $e) {
    Helper::allDBRollback(App::getDatabases());
    $log->error($e->getMessage());
    //throw $e;
}
