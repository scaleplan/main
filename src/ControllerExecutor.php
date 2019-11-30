<?php

namespace Scaleplan\Main;

use phpDocumentor\Reflection\DocBlock;
use Psr\Log\LoggerInterface;
use Scaleplan\Access\Access;
use Scaleplan\Access\AccessControllerParent;
use Scaleplan\Access\Exceptions\AuthException;
use Scaleplan\Data\Data;
use Scaleplan\Data\Interfaces\CacheInterface;
use Scaleplan\Http\Constants\ContentTypes;
use Scaleplan\Http\CurrentResponse;
use Scaleplan\Http\Interfaces\CurrentRequestInterface;
use Scaleplan\Main\Constants\ConfigConstants;
use Scaleplan\Main\Exceptions\ViewNotFoundException;
use Scaleplan\Main\Interfaces\ControllerExecutorInterface;
use Scaleplan\Main\Interfaces\UserInterface;
use Scaleplan\Result\Interfaces\ArrayResultInterface;
use function Scaleplan\DependencyInjection\get_required_container;

/**
 * Class ControllerExecutor
 *
 * @package Scaleplan\Main
 */
class ControllerExecutor implements ControllerExecutorInterface
{
    public const DOCBLOCK_TAGS_LABEL = 'tags';

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
     * ControllerExecutor constructor.
     *
     * @param UserInterface|null $user
     * @param CurrentRequestInterface|null $request
     * @param CacheInterface|null $cache
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\Data\Exceptions\DataException
     * @throws \Scaleplan\Data\Exceptions\ValidationException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
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
        if (!$cache) {
            /** @var Data $cache */
            $cache = get_required_container(CacheInterface::class, [$this->request->getURL(), $this->request->getParams()]);
            try {
                $cache->setVerifyingFilePath(View::getFullFilePath(App::getViewPath()));
            } catch (ViewNotFoundException $e) {
            }
        }
        $this->cache = $cache;
        $this->logger = get_required_container(LoggerInterface::class);
    }

    /**
     * @param \ReflectionMethod $refMethod
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected static function getMethodTags(\ReflectionMethod $refMethod) : array
    {
        $docBlock = new DocBlock($refMethod);
        $tagStr = $docBlock->getTagsByName(static::DOCBLOCK_TAGS_LABEL)[0] ?? '';
        return array_map('trim', explode(',', $tagStr));
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
     */
    protected static function executeControllerMethod(
        \ReflectionClass $refClass,
        \ReflectionMethod $method,
        array $args
    )
    {
        $object = (!$method->isStatic() && $refClass->isInstantiable()) ? $refClass->newInstance() : null;
        $params = $method->getParameters();
        if (!empty($params[0]) && $params[0]->isVariadic()) {
            return $method->invoke($object, $args);
        }

        return $method->invokeArgs($object, $args);
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
     * @return string|null
     *
     * @throws \Scaleplan\Cache\Exceptions\MemcachedCacheException
     * @throws \Scaleplan\Cache\Exceptions\RedisCacheException
     * @throws \Scaleplan\Data\Exceptions\DataException
     * @throws \Scaleplan\Data\Exceptions\ValidationException
     */
    protected function getCacheValue(\ReflectionMethod $refMethod) : ?string
    {
        if (!$this->cache || !$this->user) {
            return null;
        }

        $this->cache->setTags(static::getMethodTags($refMethod));
        $this->cache->setParams($this->request->getParams() + $this->request->getCacheAdditionalParams());
        return $this->cache->getHtml($this->user->getId())->getResult();
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
            [$refClass, $refMethod, $args] = $accessControllerParent->checkControllerMethod(
                $controllerName,
                $methodName,
                $this->request->getParams()
            );

            $cacheValue = $this->getCacheValue($refMethod);
            if ($cacheValue !== null) {
                $this->response->setPayload($cacheValue);
                return $this->response;
            }

            $result = static::executeControllerMethod($refClass, $refMethod, $args);
            if ($result instanceof ArrayResultInterface) {
                $this->response->setContentType(ContentTypes::JSON);
            }

            $this->response->setPayload($result);
            $this->response->send();
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
