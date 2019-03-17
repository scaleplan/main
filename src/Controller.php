<?php

namespace Scaleplan\Main;

use Scaleplan\Access\AccessControllerParent;
use function Scaleplan\Helpers\get_required_env;
use Scaleplan\Http\CurrentRequest;
use Scaleplan\Main\Constants\ConfigConstants;
use Scaleplan\Result\DbResult;

/**
 * Class Controller
 *
 * @package Scaleplan\Main
 */
abstract class Controller extends AccessControllerParent
{
    //use ControllerTrait;

    /**
     * Контроллеры при обращение к которым не требуется субдомен
     */
    public const GLOBAL_CONTROLLERS = [];

    /**
     * При обращении к этим контроллерам обзязательно требуется авторизация
     */
    public const ONLY_AUTH_CONTROLLERS = [];

    /**
     * Имя таблицы
     *
     * @var string
     */
    protected $repositoryName = '';

    /**
     * @var CurrentRequest
     */
    protected $request;

    /**
     * Controller constructor.
     *
     * @param CurrentRequest $request
     *
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     */
    public function __construct(CurrentRequest $request)
    {
        $this->request = $request;

        $model = str_replace(
            get_required_env(ConfigConstants::CONTROLLERS_POSTFIX),
            '',
            substr(strrchr(__CLASS__, "\\"), 1)
        );
        $this->repositoryName = lcfirst($model) . 'Repository';
    }

    /**
     * Вернуть имя связанного с контроллером репозитория по умолчанию
     *
     * @return string
     */
    public function getRepositoryName() : string
    {
        return $this->repositoryName;
    }

    /**
     * Сформировать ответ
     *
     * @param DbResult $result - результат запроса к БД
     * @param string $parentSelector - куда на странице вставлять результат запроса
     *
     * @return string
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\Access\Exceptions\ConfigException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Redis\Exceptions\RedisSingletonException
     * @throws \Scaleplan\Templater\Exceptions\DomElementNotFountException
     * @throws \Scaleplan\Templater\Exceptions\FileNotFountException
     */
    protected function formatResponse(DbResult $result, \string $parentSelector = 'body') : string
    {
        if ($this->request->isAjax()) {
            return $result->getJsonResult();
        }

        $page = new View($this->request->getURL() . '.html');
        $page->addData($result, $parentSelector);

        return $page->render();
    }
}
