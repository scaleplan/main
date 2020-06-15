<?php
declare(strict_types=1);

namespace Scaleplan\Main;

use phpDocumentor\Reflection\DocBlock;
use Psr\Log\LoggerInterface;
use Scaleplan\Access\Access;
use Scaleplan\Access\AccessControllerParent;
use Scaleplan\Access\Exceptions\AuthException;
use Scaleplan\Data\Interfaces\CacheInterface;
use Scaleplan\Http\Constants\ContentTypes;
use Scaleplan\Http\CurrentResponse;
use Scaleplan\Http\Interfaces\CurrentRequestInterface;
use Scaleplan\Main\Constants\ConfigConstants;
use Scaleplan\Main\Exceptions\ControllerException;
use Scaleplan\Main\Exceptions\ViewNotFoundException;
use Scaleplan\Main\Interfaces\ControllerExecutorInterface;
use Scaleplan\Main\Interfaces\UserInterface;
use Scaleplan\Result\ArrayResult;
use Scaleplan\Result\Interfaces\ArrayResultInterface;
use function Scaleplan\DependencyInjection\get_required_container;
use function Scaleplan\DependencyInjection\get_static_container;
use function Scaleplan\Helpers\get_env;
use function Scaleplan\Helpers\get_required_env;

/**
 * Class ControllerExecutor
 *
 * @package Scaleplan\Main
 */
class ControllerExecutor implements ControllerExecutorInterface
{
    public const DOCBLOCK_TAGS_LABEL               = 'tags';
    public const DOCBLOCK_NOCHECK_CACHE_USER_LABEL = 'noCheckCacheUser';
    public const DOCBLOCK_CACHE_DB_NAME_LABEL      = 'cacheDbName';

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CurrentRequestInterface
     */
    protected $request;

    /**
     * @var CurrentResponse
     */
    protected $response;

    /**
     * @var UserInterface
     */
    protected $user;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var string
     */
    protected $defaultControllerPath = 'default';

    /**
     * @var bool
     */
    protected $checkAccess = true;

    /**
     * @var bool
     */
    protected $cacheEnable = false;

    /**
     * ControllerExecutor constructor.
     *
     * @param UserInterface|null $user
     * @param CurrentRequestInterface|null $request
     * @param CacheInterface|null $cache
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     */
    public function __construct(
        UserInterface $user = null,
        CurrentRequestInterface $request = null,
        CacheInterface $cache = null
    )
    {
        $this->request = $request ?? get_required_container(CurrentRequestInterface::class);
        $this->response = $this->request->getResponse();
        $this->user = $user ?? get_required_container(UserInterface::class);
        $this->logger = get_required_container(LoggerInterface::class);
        $this->cache = $cache;
        if (null !== get_env('CACHE_ENABLE')) {
            $this->cacheEnable = (bool)get_env('CACHE_ENABLE');
        }
    }

    /**
     * @return bool
     */
    public function isCacheEnable() : bool
    {
        return $this->cacheEnable;
    }

    /**
     * @param bool $cacheEnable
     */
    public function setCacheEnable(bool $cacheEnable) : void
    {
        $this->cacheEnable = $cacheEnable;
    }

    /**
     * @param $docBlock
     *
     * @return int|string
     */
    public function getCacheUserId($docBlock)
    {
        static $userId;
        if (null === $userId) {
            $userId = static::isCheckCacheUser($docBlock) ? $this->user->getId() : '';
        }

        return $userId;
    }

    /**
     * @return bool
     */
    public function isCheckAccess() : bool
    {
        return $this->checkAccess;
    }

    /**
     * @param bool $checkAccess
     */
    public function setCheckAccess(bool $checkAccess) : void
    {
        $this->checkAccess = $checkAccess;
    }

    /**
     * @param DocBlock $docBlock
     *
     * @return array|null
     */
    protected static function getMethodTags(DocBlock $docBlock) : ?array
    {
        $tagStr = $docBlock->getTagsByName(static::DOCBLOCK_TAGS_LABEL)[0] ?? null;

        return null !== $tagStr
            ? array_map(static function ($tag) {
                $tag = trim($tag);
                return @constant($tag) ?? $tag;
            }, explode(',', $tagStr->getDescription()))
            : null;
    }

    /**
     * @param DocBlock $docBlock
     *
     * @return bool
     */
    protected static function isCheckCacheUser(DocBlock $docBlock) : bool
    {
        if (empty($docBlock->getTagsByName(static::DOCBLOCK_NOCHECK_CACHE_USER_LABEL)[0])) {
            return true;
        }

        return false;
    }

    /**
     * @param DocBlock $docBlock
     *
     * @return string
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Helpers\Exceptions\HelperException
     */
    public static function getCacheDbName(DocBlock $docBlock) : string
    {
        $dbName = $docBlock->getTagsByName(static::DOCBLOCK_CACHE_DB_NAME_LABEL)[0] ?? null;
        if (!$dbName || !($dbName = trim($dbName->getDescription()))) {
            return get_required_env(ConfigConstants::DEFAULT_DB);
        }

        if ($dbName === '$current') {
            /** @var App $app */
            $app = get_static_container(App::class);

            return $app::getSubdomain() ?: get_required_env(ConfigConstants::DEFAULT_DB);
        }

        return $dbName;
    }

    /**
     * @return mixed|null
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param UserInterface $user
     */
    public function setUser(UserInterface $user) : void
    {
        $this->user = $user;
    }

    /**
     * @return CacheInterface
     */
    public function getCache() : CacheInterface
    {
        return $this->cache;
    }

    /**
     * @param CacheInterface $cache
     */
    public function setCache(CacheInterface $cache) : void
    {
        $this->cache = $cache;
    }

    /**
     * Выполнить метод контроллера
     *
     * @param \ReflectionClass $refClass - класс контроллера
     * @param \ReflectionMethod $method - метод контроллера
     * @param array $args - аргументы метода
     *
     * @return mixed
     *
     * @throws \ReflectionException
     * @throws ControllerException
     */
    protected static function executeControllerMethod(
        \ReflectionClass $refClass,
        \ReflectionMethod $method,
        array $args
    )
    {
        $object = (!$method->isStatic() && $refClass->isInstantiable()) ? $refClass->newInstance() : null;
        $params = $method->getParameters();
        try {
            if (!empty($params[0]) && $params[0]->isVariadic()) {
                return $method->invoke($object, $args);
            }

            return $method->invokeArgs($object, $args);
        } catch (\ReflectionException $e) {
            if (strpos($e->getMessage(), 'Trying to invoke private method') !== false) {
                throw new ControllerException('Метод не доступен.');
            }

            throw $e;
        }
    }

    /**
     * Сконвертить урл в пару ИмяКонтроллера, ИмяМетода
     *
     * @return array
     */
    protected function convertURLToControllerMethod() : array
    {
        $path = preg_split('/[\/?]/', $this->request->getURL());
        $controllerName = getenv(ConfigConstants::CONTROLLERS_NAMESPACE)
            . str_replace(' ', '', ucwords(str_replace('-', ' ', $path[1])))
            . getenv(ConfigConstants::CONTROLLERS_POSTFIX);
        $methodName = getenv(ConfigConstants::CONTROLLERS_METHOD_PREFIX)
            . str_replace(' ', '', ucwords(str_replace('-', ' ', $path[2] ?? $this->defaultControllerPath)));

        return [$controllerName, $methodName];
    }

    /**
     * @param \ReflectionMethod $refMethod
     *
     * @return DocBlock
     */
    public static function getMethodDocBlock(\ReflectionMethod $refMethod) : DocBlock
    {
        static $docBlock;
        if (!$docBlock) {
            $docBlock = new DocBlock($refMethod);
        }

        return $docBlock;
    }

    /**
     * @param \ReflectionMethod $refMethod
     *
     * @return string|null|array
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Helpers\Exceptions\HelperException
     */
    protected function getCacheValue(\ReflectionMethod $refMethod)
    {
        $docBlock = static::getMethodDocBlock($refMethod);
        if (!$this->cacheEnable || !$this->user || null === ($tags = static::getMethodTags($docBlock))) {
            return null;
        }

        if (!$this->cache) {
            $this->cache = get_required_container(
                CacheInterface::class,
                [$this->request->getURL(), $this->request->getParams() + $this->request->getCacheAdditionalParams()]
            );
            $this->cache->setCacheDbName(static::getCacheDbName($docBlock));
            $this->cache->setTags($tags);
            if ($this->request->getAccept() !== ContentTypes::JSON) {
                try {
                    /** @var App $app */
                    $app = get_static_container(App::class);
                    $this->cache->setVerifyingFilePath(
                        get_required_env('BUNDLE_PATH')
                        . get_required_env('VIEWS_PATH')
                        . $app::getViewPath($this->user->getRoleClass())
                    );
                } catch (ViewNotFoundException $e) {
                }
            }
        }

        return $this->cache->getHtml($this->getCacheUserId($docBlock))->getResult();
    }

    /**
     * @return CurrentResponse
     *
     * @throws \Throwable
     */
    public function execute() : CurrentResponse
    {
        try {
            [$controllerName, $methodName] = $this->convertURLToControllerMethod();

            $access = get_required_container(Access::class);
            $accessControllerParent = new AccessControllerParent($access);
            $accessControllerParent->setCheckMethod($this->checkAccess);
            [$refClass, $refMethod, $args] = $accessControllerParent->checkControllerMethod(
                $controllerName,
                $methodName,
                $this->request->getParams()
            );

            $cacheValue = $this->getCacheValue($refMethod);
            if ($cacheValue !== null) {
                if (is_array($cacheValue)) {
                    $this->response->setContentType(ContentTypes::JSON);
                    $cacheValue = new ArrayResult($cacheValue);
                }

                $this->response->setPayload($cacheValue);
                $this->response->send();

                return $this->response;
            }

            $result = static::executeControllerMethod($refClass, $refMethod, $args);
            if ($result instanceof ArrayResultInterface) {
                $this->response->setContentType(ContentTypes::JSON);
            }

            $this->response->setPayload($result);
            $this->response->send();

            if ($this->cacheEnable && $this->cache) {
                $this->cache->setHtml($result, $this->getCacheUserId(static::getMethodDocBlock($refMethod)));
            }
        } catch (AuthException $e) {
            if ($this->request->getAccept() === ContentTypes::JSON) {
                $this->response->buildError($e);
            }

            /** @var UserInterface $user */
            $user = get_required_container(UserInterface::class);
            $this->response->redirectUnauthorizedUser($user);
        }

        return $this->response;
    }
}
