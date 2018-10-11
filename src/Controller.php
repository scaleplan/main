<?php

namespace Scaleplan\Main;

use Scaleplan\Access\AccessControllerParent;
use Scaleplan\Http\Request;
use Scaleplan\Result\AbstractResult;
use Scaleplan\Result\DbResult;
use Scaleplan\Result\HTMLResult;

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
     * Имя модели
     *
     * @var string
     */
    protected $modelName = '';

    /**
     * Полное имя модели
     *
     * @var string
     */
    protected $fullServiceName = '';

    /**
     * Controller constructor.
     *
     * @throws Exceptions\SettingNotFoundException
     */
    public function __construct()
    {
        $this->modelName = strtr(
            static::class,
            [
                App::getSetting('CONTROLLERS_NAMESPACE') => '',
                App::getSetting('CONTROLLERS_POSTFIX') => ''
            ]
        );
        $this->fullServiceName = App::getSetting('SERVICES_NAMESPACE') . $this->modelName;
    }

    /**
     * Вернуть имя связанной с контроллером модели по умолчанию
     *
     * @return string
     */
    public function getServiceName(): string
    {
        return $this->modelName;
    }

    /**
     * Вернуть полное имя связанной с контроллером модели по умолчанию
     *
     * @return string
     */
    public function getFullServiceName(): string
    {
        return $this->fullServiceName;
    }

    /**
     * Сформировать ответ
     *
     * @param DbResult $result - результат запроса к БД
     * @param string $parentSelector - куда на странице вставлять результат запроса
     *
     * @return AbstractResult
     *
     * @throws Exceptions\SettingNotFoundException
     * @throws \Scaleplan\Access\Exceptions\ConfigException
     * @throws \Scaleplan\Helpers\Exceptions\FileUploadException
     * @throws \Scaleplan\Helpers\Exceptions\HelperException
     * @throws \Scaleplan\Http\Exceptions\InvalidUrlException
     * @throws \Scaleplan\Redis\Exceptions\RedisSingletonException
     * @throws \Scaleplan\Templater\Exceptions\DomElementNotFountException
     * @throws \Scaleplan\Templater\Exceptions\FileNotFountException
     */
    protected static function formatResponse(DbResult $result, string $parentSelector = 'body'): AbstractResult
    {
        if (Request::getCurrentRequest()->isAjax()) {
            return $result;
        }

        $page = new View(Request::getCurrentRequest()->getURL() . '.html');
        $page->addData($result, $parentSelector);

        return new HTMLResult($page->render());
    }
}