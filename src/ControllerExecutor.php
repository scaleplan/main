<?php

namespace Scaleplan\Main;

use phpDocumentor\Reflection\DocBlock;
use Psr\Log\LoggerInterface;
use Scaleplan\Access\AccessControllerParent;
use Scaleplan\Data\Data;
use Scaleplan\Data\Interfaces\CacheInterface;
use function Scaleplan\DependencyInjection\get_container;
use Scaleplan\Http\Exceptions\InvalidUrlException;
use Scaleplan\Http\Interfaces\CurrentRequestInterface;
use Scaleplan\Http\Interfaces\CurrentResponseInterface;
use Scaleplan\Main\Constants\ConfigConstants;
use Scaleplan\Main\Interfaces\ControllerExecutorInterface;
use Scaleplan\Main\Interfaces\UserInterface;

/**
 * Class ControllerExecutor
 *
 * @package Scaleplan\Main
 */
class ControllerExecutor implements ControllerExecutorInterface
{
    public const DOCBLOCK_TAGS_LABEL = 'tags';

    /**
     * Шаблон проверки правильности формата URL
     */
    public const PAGE_URL_TEMPLATE = '/^.+?\/[^\/]+$/';

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CurrentRequestInterface
     */
    protected $request;

    /**
     * @var CurrentResponseInterface
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
     * ControllerExecutor constructor.
     *
     * @param UserInterface|null $user
     * @param CurrentRequestInterface|null $request
     * @param CacheInterface|null $cache
     *
     * @throws Exceptions\CacheException
     * @throws \ReflectionException
     * @throws \Scaleplan\Data\Exceptions\CacheDriverNotSupportedException
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
        $this->request = $request ?? get_container(CurrentRequestInterface::class);
        $this->response = $this->request->getResponse();
        $this->user = $user ?? get_container(UserInterface::class);
        if (!$cache) {
            /** @var Data $cache */
            $cache = get_container(CacheInterface::class, [$this->request->getURL(), $this->request->getParams()]);
            $cache->setCacheConnect(App::getCache());
            $cache->setVerifyingFilePath(App::getViewPath());
        }
        $this->cache = $cache;
        $this->logger = get_container(LoggerInterface::class);
    }

    /**
     * @param \ReflectionMethod $refMethod
     *
     * @return array
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
        $path = preg_split ('/[\/?]/', $this->request->getURL());
        $controllerName = getenv(ConfigConstants::CONTROLLERS_NAMESPACE)
            . str_replace(' ', '', ucwords(str_replace('-', ' ', $path[1])))
            . getenv(ConfigConstants::CONTROLLERS_POSTFIX);
        $methodName = getenv(ConfigConstants::CONTROLLERS_METHOD_PREFIX)
            . str_replace(' ', '', ucwords(str_replace('-', ' ', $path[2])));

        return [$controllerName, $methodName];
    }

    /**
     * @param \ReflectionMethod $refMethod
     *
     * @return string|null
     *
     * @throws Exceptions\CacheException
     * @throws \Scaleplan\Data\Exceptions\CacheDriverNotSupportedException
     * @throws \Scaleplan\Data\Exceptions\DataException
     * @throws \Scaleplan\Data\Exceptions\ValidationException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     */
    protected function getCacheValue(\ReflectionMethod $refMethod) : ?string
    {
        if (!$this->cache || !$this->user) {
            return null;
        }
        $this->cache->setCacheConnect(App::getCache());
        $this->cache->setTags(static::getMethodTags($refMethod));
        $this->cache->setParams($this->request->getParams() + $this->request->getCacheAdditionalParams());
        return $this->cache->getHtml($this->user->getId());
    }

    /**
     * Проверить URL на соответствие маске по умолчанию
     *
     * @param string $url - URL
     *
     * @return bool
     */
    public static function checkUrl(string $url) : bool
    {
        if (!($template = getenv('PAGE_URL_TEMPLATE') ?: static::PAGE_URL_TEMPLATE)) {
            return true;
        }

        return !empty($url) && preg_match($template, $url);
    }

    /**
     * @return CurrentResponseInterface
     *
     * @throws InvalidUrlException
     * @throws \Scaleplan\Http\Exceptions\EnvVarNotFoundOrInvalidException
     */
    public function execute() : CurrentResponseInterface
    {
        if (empty($this->request->getURL()) || !static::checkUrl($this->request->getURL())) {
            throw new InvalidUrlException();
        }
        try {
            [$controllerName, $methodName] = $this->convertURLToControllerMethod();

            [$refClass, $refMethod, $args] = AccessControllerParent::checkControllerMethod(
                $controllerName,
                $methodName,
                $this->request->getParams()
            );

            $cacheValue = $this->getCacheValue($refMethod);
            if ($cacheValue !== null) {
                $this->response->setPayload($cacheValue);
                return $this->response;
            }
            $this->response->setPayload(static::executeControllerMethod($refClass, $refMethod, $args));
        } catch (\Throwable $e) {
            $this->response->buildError($e);
        }

        return $this->response;
    }
}
