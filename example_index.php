<?php

declare(strict_types=1);

ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/vendor/autoload.php';

use App\Classes\App;
use App\Models\User;
use Psr\Log\LoggerInterface;
use Scaleplan\AccessToFiles\AccessToFiles;
use Scaleplan\AccessToFiles\AccessToFilesInterface;
use Scaleplan\DependencyInjection\DependencyInjection;
use Scaleplan\Helpers\Helper;
use Scaleplan\Main\Interfaces\ControllerExecutorInterface;
use Scaleplan\Main\Interfaces\UserInterface;
use function Scaleplan\DependencyInjection\get_required_container;
use function Scaleplan\Helpers\get_required_env;

/**
 * @throws ReflectionException
 * @throws SodiumException
 * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
 * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
 * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
 * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
 * @throws \Scaleplan\Event\Exceptions\ClassNotImplementsEventInterfaceException
 * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
 * @throws \Scaleplan\Helpers\Exceptions\HelperException
 * @throws \Scaleplan\Main\Exceptions\InvalidHostException
 */
function init()
{
    $dotEnv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotEnv->load();

    session_name(get_required_env('PROJECT_NAME'));
    session_set_cookie_params(0, '/', '.' . get_required_env('DOMAIN'));
    session_start();

    DependencyInjection::loadContainersFromDir(__DIR__ . get_required_env('CONTAINERS_CONFIGS_PATH'));
    App::init();
    /** @var User $currentUser */
    $currentUser = get_required_container(UserInterface::class);
    App::redirectToDefaultIfRoot($currentUser->getRole());
    register_shutdown_function(static function () {
        Helper::allDBCommit(App::getDatabases());
        /** @var AccessToFiles $af */
        $af = get_required_container(AccessToFilesInterface::class);
        $af->allowFiles();
    });
}

/**
 * @param Throwable $e
 *
 * @throws ReflectionException
 * @throws Throwable
 * @throws \Scaleplan\Db\Exceptions\PDOConnectionException
 * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
 * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
 * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
 * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
 */
function error(Throwable $e)
{
    Helper::allDBRollback(App::getDatabases());
    /** @var AccessToFiles $af */
    $af = get_required_container(AccessToFilesInterface::class);
    $af->clearFiles();
    $logger = get_required_container(LoggerInterface::class);
    $logger->error($e->getMessage());
    throw $e;
}

try {
    init();
    /** @var \Scaleplan\Main\ControllerExecutor $executor */
    $executor = get_required_container(ControllerExecutorInterface::class);
    $executor->execute();
} catch (Throwable $e) {
    error($e);
}
