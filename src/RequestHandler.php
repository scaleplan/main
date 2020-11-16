<?php

namespace Scaleplan\Main;

use phpDocumentor\Reflection\DocBlock;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Scaleplan\Access\Exceptions\AuthException;
use Scaleplan\Http\Constants\ContentTypes;
use Scaleplan\Http\CurrentRequest;
use Scaleplan\Http\CurrentResponse;
use Scaleplan\Main\Exceptions\ControllerException;
use Scaleplan\Main\Interfaces\CheckMethodInterface;
use Scaleplan\Main\Interfaces\ResponseCacheInterface;
use Scaleplan\Main\Interfaces\RouterInterface;
use Scaleplan\Main\Interfaces\UserInterface;
use Scaleplan\Main\Middlewares\CheckMethod;
use Scaleplan\Main\Middlewares\ResponseCache;
use Scaleplan\Result\Interfaces\ArrayResultInterface;
use function Scaleplan\DependencyInjection\get_required_container;
use function Scaleplan\Helpers\get_env;
use function Scaleplan\Translator\translate;

/**
 * Class RequestHandler
 *
 * @package Scaleplan\Main
 */
class RequestHandler implements RequestHandlerInterface
{

    /**
     * @var bool
     */
    protected $cacheEnable = false;

    /**
     * @var bool
     */
    protected $checkAccess = true;

    /**
     * @var MiddlewareInterface[]
     */
    protected $middlewares;

    /**
     * RequestHandler constructor.
     */
    public function __construct()
    {
        if (null !== get_env('CACHE_ENABLE')) {
            $this->cacheEnable = (bool) get_env('CACHE_ENABLE');
        }
    }

    /**
     * @return bool
     */
    public function isCacheEnable(): bool
    {
        return $this->cacheEnable;
    }

    /**
     * @param bool $cacheEnable
     */
    public function setCacheEnable(bool $cacheEnable): void
    {
        $this->cacheEnable = $cacheEnable;
    }

    /**
     * @return bool
     */
    public function isCheckAccess(): bool
    {
        return $this->checkAccess;
    }

    /**
     * @param bool $checkAccess
     */
    public function setCheckAccess(bool $checkAccess): void
    {
        $this->checkAccess = $checkAccess;
    }

    /**
     * @param \ReflectionMethod $refMethod
     *
     * @return DocBlock
     */
    public static function getMethodDocBlock(\ReflectionMethod $refMethod): DocBlock
    {
        static $docBlock;
        if (!$docBlock) {
            $docBlock = new DocBlock($refMethod);
        }

        return $docBlock;
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
     * @throws ControllerException
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
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
                throw new ControllerException(translate('main.method-not-available'));
            }

            throw $e;
        }
    }

    /**
     * @param MiddlewareInterface $middleware
     */
    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     *
     * @throws \PhpQuery\Exceptions\PhpQueryException
     * @throws \ReflectionException
     * @throws \Scaleplan\Access\Exceptions\AccessDeniedException
     * @throws \Scaleplan\Access\Exceptions\AccessException
     * @throws \Scaleplan\Access\Exceptions\ClassNotFoundException
     * @throws \Scaleplan\Access\Exceptions\FormatException
     * @throws \Scaleplan\Access\Exceptions\MethodNotFoundException
     * @throws \Scaleplan\Access\Exceptions\ValidationException
     * @throws \Scaleplan\DTO\Exceptions\ValidationException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerNotFoundException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Event\Exceptions\ClassNotImplementsEventInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Helpers\Exceptions\HelperException
     * @throws \Scaleplan\Result\Exceptions\ResultException
     * @throws \Scaleplan\Templater\Exceptions\DomElementNotFoundException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            /** @var Router $router */
            $router = get_required_container(RouterInterface::class);
            [$controllerName, $methodName] = $router->convertURLToControllerMethod();

            /** @var CheckMethod $checkMethod */
            $checkMethod = get_required_container(
                CheckMethodInterface::class,
                [$controllerName, $methodName, $this->checkAccess]
            );
            $response = $checkMethod->process($request, $this);

            $cacheValue = null;
            if ($this->cacheEnable) {
                $docBlock = static::getMethodDocBlock($checkMethod->getRefMethod());
                /** @var ResponseCache $responseCache */
                $responseCache = get_required_container(ResponseCacheInterface::class, [$docBlock,]);
                /** @var CurrentResponse $response */
                $response = $responseCache->process($request, $this);
                if ($responseCache->isHit()) {
                    $response->send();
                    return $response;
                }
            }

            foreach ($this->middlewares ?? [] as $middleware) {
                $middleware->process($request, $this);
            }

            $result = static::executeControllerMethod(
                $checkMethod->getRefClass(),
                $checkMethod->getRefMethod(),
                $checkMethod->getArgs()
            );
            if ($result instanceof ArrayResultInterface) {
                $response->setContentType(ContentTypes::JSON);
            }

            $response->setPayload($result);
            $response->send();

            if ($this->cacheEnable) {
                $responseCache->getCache()
                    && $responseCache->getCache()->setHtml($result, $responseCache->getCacheUserId());
            }
        } catch (AuthException $e) {
            if ($request->getAccept() === ContentTypes::JSON) {
                /** @var CurrentRequest $request */
                $request->getResponse()->buildError($e);
            }

            /** @var UserInterface $user */
            $user = get_required_container(UserInterface::class);
            /** @var CurrentRequest $request */
            $request->getResponse()->redirectUnauthorizedUser($user);
        }

        return $response ?? $request->getResponse();
    }

}
