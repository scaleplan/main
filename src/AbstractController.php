<?php
declare(strict_types=1);

namespace Scaleplan\Main;

use Scaleplan\DTO\DTO;
use Scaleplan\Helpers\NameConverter;
use Scaleplan\Http\Constants\ContentTypes;
use Scaleplan\Http\Interfaces\CurrentRequestInterface;
use Scaleplan\Main\Constants\ConfigConstants;
use Scaleplan\Main\Exceptions\ControllerException;
use Scaleplan\Result\HTMLResult;
use Scaleplan\Result\Interfaces\DbResultInterface;
use Scaleplan\Result\Interfaces\ResultInterface;
use function Scaleplan\DependencyInjection\get_required_container;
use function Scaleplan\Helpers\get_required_env;

/**
 * Class Controller
 *
 * @package Scaleplan\Main
 */
abstract class AbstractController
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
     * @var CurrentRequestInterface
     */
    protected $request;

    /**
     * @var string
     */
    protected $modelName;

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
        $this->request = get_required_container(CurrentRequestInterface::class);

        $this->modelName = str_replace(
            get_required_env(ConfigConstants::CONTROLLERS_POSTFIX),
            '',
            ucfirst(substr(strrchr(static::class, "\\"), 1))
        );
        $this->repositoryName = get_required_env(ConfigConstants::REPOSITORIES_NAMESPACE)
            . $this->modelName . get_required_env(ConfigConstants::REPOSITORIES_POSTFIX);

        $this->serviceName = get_required_env(ConfigConstants::SERVICES_NAMESPACE)
            . $this->modelName . get_required_env(ConfigConstants::SERVICES_POSTFIX);
    }

    /**
     * @return string
     */
    public function getModelName() : string
    {
        return $this->modelName;
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
     * @return AbstractRepository
     *
     * @throws ControllerException
     */
    public function getRepository() : AbstractRepository
    {
        if (!class_exists($this->repositoryName)) {
            throw new ControllerException('Репозиторий не найден.');
        }

        return new $this->repositoryName;
    }

    /**
     * Вернуть связанный с контроллером сервис
     *
     * @return AbstractService
     *
     * @throws ControllerException
     */
    public function getService() : AbstractService
    {
        if (!class_exists($this->serviceName)) {
            throw new ControllerException('Сервис не найден.');
        }

        return new $this->serviceName;
    }

    /**
     * Сформировать ответ
     *
     * @param DbResultInterface $result - результат запроса к БД
     * @param string $parentSelector - куда на странице вставлять результат запроса
     *
     * @return ResultInterface
     * @throws Exceptions\ViewNotFoundException
     * @throws \PhpQuery\Exceptions\PhpQueryException
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Templater\Exceptions\DomElementNotFountException
     */
    protected function formatResponse(DbResultInterface $result, string $parentSelector = 'body') : ResultInterface
    {
        if ($this->request->isAjax() || $this->request->getAccept() === ContentTypes::JSON) {
            return $result;
        }

        $page = new View(App::getViewPath());
        $page->addData($result, $parentSelector);

        return new HTMLResult($page->render());
    }

    /**
     * @param string $methodName
     * @param DTO|null $dto
     *
     * @return string
     *
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     */
    public static function getMethodUrl(string $methodName, DTO $dto = null) : string
    {
        $model = str_replace(
            get_required_env(ConfigConstants::CONTROLLERS_POSTFIX),
            '',
            substr(strrchr(static::class, "\\"), 1)
        );
        $method = str_replace(get_required_env(ConfigConstants::CONTROLLERS_METHOD_PREFIX), '', $methodName);
        $params = '';
        if ($dto) {
            $params = '?' . http_build_query($dto->toSnakeArray());
        }

        return
            '/' . NameConverter::camelCaseToKebabCase($model)
            . '/' . NameConverter::camelCaseToKebabCase($method) . $params;
    }
}
