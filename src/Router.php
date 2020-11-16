<?php
declare(strict_types=1);

namespace Scaleplan\Main;

use Scaleplan\Http\Interfaces\CurrentRequestInterface;
use Scaleplan\Main\Constants\ConfigConstants;
use Scaleplan\Main\Interfaces\RouterInterface;
use function Scaleplan\DependencyInjection\get_required_container;

/**
 * Class Router
 *
 * @package Scaleplan\Main
 */
class Router implements RouterInterface
{
    /**
     * @var CurrentRequestInterface
     */
    protected $request;

    /**
     * @var string
     */
    protected $defaultControllerPath = 'default';

    /**
     * Router constructor.
     *
     * @param CurrentRequestInterface|null $request
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     */
    public function __construct(CurrentRequestInterface $request = null)
    {
        $this->request = $request ?? get_required_container(CurrentRequestInterface::class);
    }

    /**
     * Сконвертить урл в пару ИмяКонтроллера, ИмяМетода
     *
     * @return array
     */
    public function convertURLToControllerMethod() : array
    {
        $path = preg_split('/[\/?]/', $this->request->getURL());
        $controllerName = getenv(ConfigConstants::CONTROLLERS_NAMESPACE)
            . str_replace(' ', '', ucwords(str_replace('-', ' ', $path[1])))
            . getenv(ConfigConstants::CONTROLLERS_POSTFIX);
        $methodName = getenv(ConfigConstants::CONTROLLERS_METHOD_PREFIX)
            . str_replace(' ', '', ucwords(str_replace('-', ' ', $path[2] ?? $this->defaultControllerPath)));

        return [$controllerName, $methodName];
    }
}
