<?php

namespace Scaleplan\Main\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Scaleplan\Access\Access;
use Scaleplan\Access\AccessControllerParent;
use Scaleplan\Http\CurrentRequest;
use Scaleplan\Main\Interfaces\CheckMethodInterface;
use function Scaleplan\DependencyInjection\get_required_container;

/**
 * Class CheckMethod
 *
 * @package Scaleplan\Main\Middlewares
 */
class CheckMethod implements MiddlewareInterface, CheckMethodInterface
{
    /**
     * @var string
     */
    protected $controllerName;

    /**
     * @var string
     */
    protected $methodName;

    /**
     * @var bool
     */
    protected $checkAccess;

    /**
     * @var \ReflectionClass
     */
    protected $refClass;

    /**
     * @var \ReflectionMethod
     */
    protected $refMethod;

    /**
     * @var array
     */
    protected $args;

    /**
     * CheckAccess constructor.
     *
     * @param string $controllerName
     * @param string $methodName
     * @param bool $checkAccess
     */
    public function __construct(
        string $controllerName,
        string $methodName,
        bool $checkAccess = true
    ) {
        $this->controllerName = $controllerName;
        $this->methodName = $methodName;
        $this->checkAccess = $checkAccess;
    }

    /**
     * @return \ReflectionClass
     */
    public function getRefClass() : \ReflectionClass
    {
        return $this->refClass;
    }

    /**
     * @return \ReflectionMethod
     */
    public function getRefMethod() : \ReflectionMethod
    {
        return $this->refMethod;
    }

    /**
     * @return array
     */
    public function getArgs() : array
    {
        return $this->args;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     * @throws \ReflectionException
     * @throws \Scaleplan\Access\Exceptions\AccessDeniedException
     * @throws \Scaleplan\Access\Exceptions\AccessException
     * @throws \Scaleplan\Access\Exceptions\AuthException
     * @throws \Scaleplan\Access\Exceptions\ClassNotFoundException
     * @throws \Scaleplan\Access\Exceptions\FormatException
     * @throws \Scaleplan\Access\Exceptions\MethodNotFoundException
     * @throws \Scaleplan\Access\Exceptions\ValidationException
     * @throws \Scaleplan\DTO\Exceptions\ValidationException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Event\Exceptions\ClassNotImplementsEventInterfaceException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $access = get_required_container(Access::class);
        $accessControllerParent = new AccessControllerParent($access);
        $accessControllerParent->setCheckMethod($this->checkAccess);
        [$this->refClass, $this->refMethod, $this->args] = $accessControllerParent->checkControllerMethod(
            $this->controllerName,
            $this->methodName,
            $request->getQueryParams()
        );

        /** @var CurrentRequest $request */
        return $request->getResponse();
    }
}
