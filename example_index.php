<?php
declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/Configs/eventSetter.php';

use Ahc\Env\Loader;
use App\Classes\App;
use App\Classes\QooizHelper;
use App\Interfaces\Service\NotifyServiceInterface;
use App\Interfaces\Service\UserServiceInterface;
use App\Models\User;
use App\Services\NotifyService;
use App\Services\UserService;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Scaleplan\AccessToFiles\AccessToFiles;
use Scaleplan\AccessToFiles\AccessToFilesInterface;
use Scaleplan\DependencyInjection\DependencyInjection;
use Scaleplan\DTO\Exceptions\ValidationException;
use Scaleplan\Event\EventDispatcher;
use Scaleplan\Helpers\Helper;
use Scaleplan\Http\Constants\ContentTypes;
use Scaleplan\Http\CurrentRequest;
use Scaleplan\Http\CurrentResponse;
use Scaleplan\Http\Interfaces\CurrentRequestInterface;
use Scaleplan\Http\Interfaces\CurrentResponseInterface;
use Scaleplan\HttpStatus\HttpStatusCodes;
use Scaleplan\Main\Interfaces\UserInterface;
use Scaleplan\Main\RequestHandler;
use Scaleplan\Notify\Interfaces\NotifyInterface;
use Scaleplan\Notify\Notify;
use function Scaleplan\DependencyInjection\get_required_container;
use function Scaleplan\DependencyInjection\get_required_static_container;
use function Scaleplan\Helpers\get_required_env;

const NOT_SAVING_URLS = [
    '/user/auth',
    '/user/registration',
];

set_error_handler(static function ($errno, $errstr, $errfile, $errline) {
    // error was suppressed with the @-operator
    if (0 === error_reporting()) {
        return false;
    }

    throw new ErrorException($errstr, HttpStatusCodes::HTTP_INTERNAL_SERVER_ERROR, $errno, $errfile, $errline);
});

/**
 * @throws ReflectionException
 * @throws SodiumException
 * @throws \Scaleplan\Cache\Exceptions\MemcachedCacheException
 * @throws \Scaleplan\Cache\Exceptions\MemcachedOperationException
 * @throws \Scaleplan\Cache\Exceptions\RedisCacheException
 * @throws \Scaleplan\Data\Exceptions\DataException
 * @throws \Scaleplan\Data\Exceptions\DbConnectException
 * @throws \Scaleplan\Data\Exceptions\ValidationException
 * @throws \Scaleplan\Db\Exceptions\DbException
 * @throws \Scaleplan\Db\Exceptions\InvalidIsolationLevelException
 * @throws \Scaleplan\Db\Exceptions\PDOConnectionException
 * @throws \Scaleplan\Db\Exceptions\QueryCountNotMatchParamsException
 * @throws \Scaleplan\Db\Exceptions\QueryExecutionException
 * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
 * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
 * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
 * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
 * @throws \Scaleplan\Event\Exceptions\ClassNotImplementsListenerInterfaceException
 * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
 * @throws \Scaleplan\Helpers\Exceptions\HelperException
 * @throws \Scaleplan\Main\Exceptions\DatabaseException
 * @throws \Scaleplan\Main\Exceptions\InvalidHostException
 * @throws \Scaleplan\Result\Exceptions\ResultException
 * @throws \Scaleplan\Main\Exceptions\AppException
 * @throws ValidationException
 */
function init()
{
    (new Loader)->load(__DIR__ . (empty($_COOKIE['phpbrowser']) ? '/.env' : '/.env.test'));
    DependencyInjection::loadContainersFromDir(__DIR__ . get_required_env('CONTAINERS_CONFIGS_PATH'));

    session_name(get_required_env('COOKIE_NAME'));
    session_set_cookie_params(0, '/', '.' . get_required_env('MAIN_DOMAIN'));
    session_start();

    App::init();

    /** @var User $currentUser */
    $currentUser = get_required_container(UserInterface::class);
    /** @var UserService $userService */
    $userService = get_required_static_container(UserServiceInterface::class);
    if (!$currentUser->isGuest() && session_id() !== $userService::getSessionId()) {
        session_destroy();
    }

    eventSet();

    /** @var CurrentRequest $request */
    $request = get_required_container(CurrentRequestInterface::class);
    $request->getResponse()->addCookie(get_required_env('COOKIE_NAME'), session_id());

    App::redirectToDefaultIfRoot($currentUser->getRole());
}

/**
 * @throws ReflectionException
 * @throws \Pusher\PusherException
 * @throws \Scaleplan\AccessToFiles\Exceptions\AccessToFilesException
 * @throws \Scaleplan\Db\Exceptions\PDOConnectionException
 * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
 * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
 * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
 * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
 * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
 * @throws \Scaleplan\Notify\Exceptions\NotifyException
 * @throws \Scaleplan\Redis\Exceptions\RedisSingletonException
 */
function registerOkCallback()
{
    /** @var User $currentUser */
    $currentUser = get_required_container(UserInterface::class);
    Helper::allDBCommit(App::getDatabases());
    /** @var AccessToFiles $af */
    $af = get_required_container(AccessToFilesInterface::class);
    $af->allowFiles();
    /** @var NotifyService $notifyService */
    $notifyService = get_required_container(NotifyServiceInterface::class);
    /** @var Notify $notify */
    $notify = get_required_container(NotifyInterface::class);
    $notify->sendOld([$notifyService::USER_CHANNEL_PREFIX . $currentUser->getId()]);

    /** @var CurrentRequest $currentRequest */
    $currentRequest = get_required_container(CurrentRequestInterface::class);

    if ($_SERVER
        && $_SERVER['REQUEST_METHOD'] === 'GET'
        && $currentRequest->getAccept() !== ContentTypes::JSON
        && !$currentRequest->isAjax()
        && !in_array(explode('?', $_SERVER['REQUEST_URI'])[0], NOT_SAVING_URLS, true)
    ) {
        $_SESSION[QooizHelper::SESSION_LAST_URL_FIELD] =
            $currentRequest::getScheme()
            . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
    }

    foreach (EventDispatcher::getAsyncEvents() as $eventFunc) {
        $eventFunc();
    }
    Helper::allDBCommit(App::getDatabases());
}

/**
 * @param Throwable $e
 *
 * @throws ReflectionException
 * @throws \Scaleplan\Db\Exceptions\PDOConnectionException
 * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
 * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
 * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
 * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
 */
function registerErrorCallback(Throwable $e)
{
    Helper::allDBRollback(App::getDatabases());
    /** @var AccessToFiles $af */
    $af = get_required_container(AccessToFilesInterface::class);
    $af->clearFiles();
    /** @var CurrentRequest $currentRequest */
    $currentRequest = get_required_container(CurrentRequestInterface::class);
    /** @var \Monolog\Logger $logger */
    $logger = get_required_container(LoggerInterface::class);
    $jsonParams = json_encode($currentRequest->getParams(), JSON_UNESCAPED_UNICODE);
    $logger->error(
        "Error message: {$e->getMessage()}. "
        . "File: {$e->getFile()}. "
        . "Line: {$e->getLine()}. "
        . "URL: {$currentRequest->getURL()}. "
        . "Params: $jsonParams. "
        . ($e instanceof ValidationException ? 'Errors: ' . json_encode($e->getErrors(), JSON_UNESCAPED_UNICODE) : '')
    //. "Trace: {$e->getTraceAsString()}"
    );
}

/**
 * @param Throwable $e
 *
 * @throws ReflectionException
 * @throws Throwable
 * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
 * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
 * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
 * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
 */
function error(Throwable $e)
{
    /** @var CurrentResponse $response */
    $response = get_required_static_container(CurrentResponseInterface::class);
    $response::sendError($e);
    registerErrorCallback($e);
    //throw $e;
}

try {
    //$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'Ru-ru';
    init();
    /** @var CurrentRequest $request */
    $request = get_required_container(CurrentRequestInterface::class);
    if (get_required_env('ENVIRONMENT') === 'maintenance') {
        $request->getResponse()->XRedirect('/public/maintenance/index.html');
        exit;
    }

    /** @var RequestHandler $executor */
    $executor = get_required_container(RequestHandlerInterface::class);
    if (!App::getSubdomain()) {
        $executor->setCheckAccess(false);
    }

    $executor->handle($request);
    registerOkCallback();
} catch (Throwable $e) {
//    throw $e;
    error($e);
}
