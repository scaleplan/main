<?php

namespace Scaleplan\Main;

use App\Classes\App;
use Scaleplan\Access\AccessControllerParent;
use function Scaleplan\DependencyInjection\get_container;
use Scaleplan\Form\Form;
use function Scaleplan\Helpers\get_required_env;
use Scaleplan\Helpers\NameConverter;
use Scaleplan\Http\CurrentRequest;
use Scaleplan\Http\Interfaces\CurrentRequestInterface;
use Scaleplan\Main\Constants\ConfigConstants;
use Scaleplan\Result\DbResult;
use Scaleplan\Result\HTMLResult;
use Scaleplan\Result\Interfaces\DbResultInterface;
use Scaleplan\Result\Interfaces\HTMLResultInterface;
use Scaleplan\Result\Interfaces\ResultInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Controller
 *
 * @package Scaleplan\Main
 */
abstract class AbstractController extends AccessControllerParent
{
    public const FORMS_PATH_ENV_NAME = 'FORM_PATH';

    /**
     * @var string|null
     */
    protected $repositoryName;

    /**
     * @var string|null
     */
    protected $serviceName;

    /**
     * @var CurrentRequest
     */
    protected $request;

    /**
     * AbstractController constructor.
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     */
    public function __construct()
    {
        $this->request = get_container(CurrentRequestInterface::class);

        $model = str_replace(
            get_required_env(ConfigConstants::CONTROLLERS_POSTFIX),
            '',
            substr(strrchr(__CLASS__, "\\"), 1)
        );
        $this->repositoryName = get_required_env(ConfigConstants::REPOSITORIES_NAMESPACE)
            . '/' . lcfirst($model) . get_required_env(ConfigConstants::REPOSITORIES_POSTFIX);

        $this->serviceName = get_required_env(ConfigConstants::SERVICES_NAMESPACE)
            . '/' . lcfirst($model) . get_required_env(ConfigConstants::SERVICES_POSTFIX);
    }

    /**
     * @return string|null
     */
    public function getRepositoryName() : ?string
    {
        return $this->repositoryName;
    }

    /**
     * @param string|null $repositoryName
     */
    public function setRepositoryName(?string $repositoryName) : void
    {
        $this->repositoryName = $repositoryName;
    }

    /**
     * @return string|null
     */
    public function getServiceName() : ?string
    {
        return $this->serviceName;
    }

    /**
     * @param string|null $serviceName
     */
    public function setServiceName(?string $serviceName) : void
    {
        $this->serviceName = $serviceName;
    }

    /**
     * Вернуть связанный с контроллером репозиторий
     *
     * @return AbstractRepository|null
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     */
    public function getRepository() : ?AbstractRepository
    {
        return get_container($this->repositoryName);
    }

    /**
     * @return AbstractService|null
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     */
    public function getService() : ?AbstractService
    {
        return get_container($this->serviceName);
    }

    /**
     * Сформировать ответ
     *
     * @param DbResultInterface $result - результат запроса к БД
     * @param string $parentSelector - куда на странице вставлять результат запроса
     *
     * @return ResultInterface
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Templater\Exceptions\DomElementNotFountException
     * @throws \Scaleplan\Templater\Exceptions\FileNotFountException
     */
    protected function formatResponse(DbResultInterface $result, string $parentSelector = 'body') : ResultInterface
    {
        if ($this->request->isAjax()) {
            return $result;
        }

        $page = new View(App::getViewPath());
        $page->addData($result, $parentSelector);

        return new HTMLResult($page->render());
    }

    /**
     * @param string $methodName
     *
     * @return string
     *
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     */
    public static function getMethodUrl(string $methodName) : string
    {
        $model = strtr(
            substr(strrchr(__CLASS__, "\\"), 1),
            [
                get_required_env(ConfigConstants::CONTROLLERS_POSTFIX) => '',
                get_required_env(ConfigConstants::CONTROLLERS_METHOD_PREFIX) => '',
            ]
        );

        return NameConverter::camelCaseToSnakeCase($model) . '/' . NameConverter::camelCaseToSnakeCase($methodName);
    }
}
