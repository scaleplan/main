<?php

namespace Scaleplan\Main;

use Scaleplan\Access\Access;
use Scaleplan\Http\Request;
use Scaleplan\Result\DbResult;
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
    /**
     * Путь к файлу шаблона
     *
     * @var string
     */
    protected $filePath = '';

    /**
     * Добавлять ли шапку
     *
     * @var bool
     */
    protected $addHeader = true;

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
     * Конструктор
     *
     * @param string $filePath - путь к файлу шаблона
     * @param bool $addHeader - добавлять ли шапку
     * @param array $settings - настройки шаблонизатора
     *
     * @throws \Scaleplan\Helpers\Exceptions\FileUploadException
     * @throws \Scaleplan\Helpers\Exceptions\HelperException
     * @throws \Scaleplan\Http\Exceptions\InvalidUrlException
     */
    public function __construct(string $filePath, bool $addHeader = null, array $settings = [])
    {
        $this->filePath = $filePath;
        $this->addHeader = $addHeader ?? !Request::getCurrentRequest()->isAjax();
        $this->settings = $settings;
    }

    /**
     * Установить является ли представление письмом
     *
     * @param bool $isMessage - новое значение
     */
    public function setIsMessage(bool $isMessage): void
    {
        $this->isMessage = $isMessage;
    }

    /**
     * Путь к файлу относительно директории с шаблонами представлений
     *
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Полный путь к файлу относительно директории с шаблонами представлений
     *
     * @return string
     *
     * @throws Exceptions\SettingNotFoundException
     */
    public function getFullFilePath(): string
    {
        return $_SERVER['DOCUMENT_ROOT'] .
            ($this->isMessage ? App::getSetting('MESSAGES_PATH') : App::getSetting('VIEWS_PATH'))
            . '/'
            . $this->filePath;
    }

    /**
     * Вернуть флаг добавления шабки
     *
     * @return bool
     */
    public function getAddHeader(): bool
    {
        return $this->addHeader;
    }

    /**
     * Добавить данные для добавления на страницу
     *
     * @param DbResult $data - данные
     * @param string $parentSelector - в элемент с каким селектором добавлять данные
     */
    public function addData(DbResult $data, string $parentSelector): void
    {
        $this->data[$parentSelector] = $data;
    }

    /**
     * Удалить данные для добавления на страницу
     *
     * @param string $parentSelector - в элемент с каким селектором больше не надо добавлять данные
     */
    public function deleteData(string $parentSelector): void
    {
        unset($this->data[$parentSelector]);
    }

    /**
     * Удалить со страницы данные недоступные текущему пользователю
     *
     * @param \phpQueryObject $template - страница
     *
     * @return mixed
     *
     * @throws \Scaleplan\Access\Exceptions\ConfigException
     * @throws \Scaleplan\Redis\Exceptions\RedisSingletonException
     */
    protected static function removeUnresolvedElements(&$template)
    {
        $elements = $template->find('*[data-acless-url-id]');
        /** @var Access $access */
        $access = Access::create(App::getCurrentUser()->getId());
        $accessUrls = $access->getAccessRights();

        foreach ($elements as $el) {
            if (empty($aclessRight = $accessUrls[$el->attr('data-acless-url-id')])) {
                $el->remove();
                break;
            }

            if (
                !empty($value = $el->attr('data-acless-value'))
                &&
                !\in_array($value, json_decode($aclessRight['values'] ?? '', true) ?? [], true)
            ) {
                $el->remove();
                break;
            }
        }

        return $template;
    }

    /**
     * @return string|\phpQueryObject
     */
    public static function getHeader()
    {
        return '';
    }

    /**
     * Отрендерить страницу
     *
     * @return \phpQueryObject
     *
     * @throws Exceptions\SettingNotFoundException
     * @throws \Scaleplan\Access\Exceptions\ConfigException
     * @throws \Scaleplan\Redis\Exceptions\RedisSingletonException
     * @throws \Scaleplan\Templater\Exceptions\DomElementNotFountException
     * @throws \Scaleplan\Templater\Exceptions\FileNotFountException
     */
    public function render() : \phpQueryObject
    {
        $page = new Templater($this->getFullFilePath(), $this->settings);
        $template = $page->getTemplate();
        static::removeUnresolvedElements($template);
        if ($this->addHeader) {
            $template->find('body')->prepend(static::getHeader());
        }

        foreach ($this->data as $selector => $data) {
            $page->setMultiData($data->getArrayResult(), $selector);
        }

        return $template;
    }
}