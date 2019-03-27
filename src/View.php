<?php

namespace Scaleplan\Main;

use Scaleplan\Access\Access;
use function Scaleplan\DependencyInjection\get_container;
use function Scaleplan\Helpers\get_required_env;
use Scaleplan\Http\Interfaces\CurrentRequestInterface;
use Scaleplan\Main\Constants\ConfigConstants;
use Scaleplan\Result\DbResult;
use Scaleplan\Result\Interfaces\DbResultInterface;
use Scaleplan\Templater\Templater;

/**
 * Представление
 *
 * Class View
 *
 * @package Scaleplan\Main
 */
class View
{
    public const ERROR_TEMPLATE_PATH = 'error/universal.html';

    /**
     * Путь к файлу шаблона
     *
     * @var string
     */
    protected $filePath = '';

    /**
     * Шапка
     *
     * @var string
     */
    protected $header;

    /**
     * @var string
     */
    protected $footer;

    /**
     * Настройки шаблона
     *
     * @var array
     */
    protected $settings = [];

    /**
     * Данные для добавления в шаблон
     *
     * @var array
     */
    protected $data = [];

    /**
     * Является ли представление письмом
     *
     * @var bool
     */
    protected $isMessage = false;

    /**
     * View constructor.
     *
     * @param string $filePath - путь к файлу шаблона
     * @param string $header - шапка
     * @param string $footer - подвал
     * @param array $settings - настройки шаблонизатора
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     */
    public function __construct(?string $filePath, string $header = '', string $footer = '', array $settings = [])
    {
        $this->filePath = $filePath;
        /** @var CurrentRequestInterface $currentRequest */
        $currentRequest = get_container(CurrentRequestInterface::class);
        $this->header = $header ?? ($currentRequest && !$currentRequest->isAjax() ? static::getHeader() : '');
        $this->footer = $footer ?? ($currentRequest && !$currentRequest->isAjax() ? static::getHeader() : '');
        /** @var Access $access */
        $access = Access::create(App::getCurrentUser()->getId());
        $this->settings['forbiddenSelectors']
            = $this->settings['forbiddenSelectors'] ?? $access->getForbiddenSelectors($currentRequest->getURL());
        $this->settings = $settings;
    }

    /**
     * Установить является ли представление письмом
     *
     * @param bool $isMessage - новое значение
     */
    public function setIsMessage(bool $isMessage) : void
    {
        $this->isMessage = $isMessage;
    }

    /**
     * Путь к файлу относительно директории с шаблонами представлений
     *
     * @return string
     */
    public function getFilePath() : string
    {
        return $this->filePath;
    }

    /**
     * Полный путь к файлу относительно директории с шаблонами представлений
     *
     * @return string
     *
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     */
    public function getFullFilePath() : string
    {
        return get_required_env('BUNDLE_') .
            ($this->isMessage
                ? get_required_env(ConfigConstants::MESSAGES_PATH)
                : get_required_env(ConfigConstants::VIEWS_PATH)
            )
            . '/'
            . $this->filePath;
    }

    /**
     * Добавить данные для добавления на страницу
     *
     * @param DbResultInterface $data - данные
     * @param string $parentSelector - в элемент с каким селектором добавлять данные
     */
    public function addData(DbResultInterface $data, string $parentSelector = 'body') : void
    {
        $this->data[$parentSelector] = $data;
    }

    /**
     * Удалить данные для добавления на страницу
     *
     * @param string $parentSelector - в элемент с каким селектором больше не надо добавлять данные
     */
    public function deleteData(string $parentSelector) : void
    {
        unset($this->data[$parentSelector]);
    }

    /**
     * @return string
     */
    public static function getHeader() : string
    {
        return '';
    }

    /**
     * @return string
     */
    public static function getFooter() : string
    {
        return '';
    }

    /**
     * Отрендерить страницу
     *
     * @return \phpQueryObject
     *
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Templater\Exceptions\DomElementNotFountException
     * @throws \Scaleplan\Templater\Exceptions\FileNotFountException
     */
    public function render() : \phpQueryObject
    {
        $page = new Templater($this->getFullFilePath(), $this->settings);
        $page->removeForbidden();
        $page->renderIncludes();
        $template = $page->getTemplate();
        $template->find('body')->prepend($this->header);
        $template->find('body')->append($this->footer);

        foreach ($this->data as $selector => $data) {
            $page->setMultiData($data->getArrayResult(), $selector);
        }

        return $template;
    }

    /**
     * @param \Throwable $e
     *
     * @return \phpQueryObject
     *
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Result\Exceptions\ResultException
     * @throws \Scaleplan\Templater\Exceptions\DomElementNotFountException
     * @throws \Scaleplan\Templater\Exceptions\FileNotFountException
     */
    public function renderError(\Throwable $e) : \phpQueryObject
    {
        $this->filePath = get_required_env('ERROR_TEMPLATE_PATH');
        $this->data = [new DbResult(['code' => $e->getCode(), 'message' => $e->getMessage()])];

        return $this->render();
    }
}
