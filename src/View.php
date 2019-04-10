<?php

namespace Scaleplan\Main;

use phpQuery;
use Scaleplan\Access\Access;
use function Scaleplan\DependencyInjection\get_required_container;
use function Scaleplan\Helpers\get_env;
use function Scaleplan\Helpers\get_required_env;
use Scaleplan\Http\Interfaces\CurrentRequestInterface;
use Scaleplan\Main\Constants\ConfigConstants;
use Scaleplan\Main\Interfaces\ViewInterface;
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
class View implements ViewInterface
{
    public const ERROR_TEMPLATE_PATH = '/universal.html';

    /**
     * Путь к файлу шаблона
     *
     * @var string
     */
    protected $filePath = '';

    /**
     * Шапка
     *
     * @var string|null
     */
    protected $headerPath;

    /**
     * @var string|null
     */
    protected $footerPath;

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
     * @param string|null $headerPath - шапка
     * @param string|null $footerPath - подвал
     * @param array $settings - настройки шаблонизатора
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     */
    public function __construct(
        ?string $filePath,
        string $headerPath = null,
        string $footerPath = null,
        array $settings = []
    )
    {
        $this->filePath = $filePath;
        /** @var CurrentRequestInterface $currentRequest */
        $currentRequest = get_required_container(CurrentRequestInterface::class);
        $this->headerPath = $headerPath;
        $this->footerPath = $footerPath;
        /** @var Access $access */
        $access = get_required_container(Access::class);
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
     * @param string|null $filePath
     *
     * @return string|null
     *
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     */
    protected static function getFullFilePath(?string $filePath) : ?string
    {
        if (null === $filePath) {
            return null;
        }

        return get_required_env(ConfigConstants::BUNDLE_PATH) . get_required_env('VIEWS_PATH') . $filePath;
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
     * @return \phpQueryObject
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     */
    public function getHeader() : \phpQueryObject
    {
        return phpQuery::newDocumentFileHTML(static::getFullFilePath($this->headerPath));;
    }

    /**
     * @return \phpQueryObject
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     */
    public function getFooter() : \phpQueryObject
    {
        return phpQuery::newDocumentFileHTML(static::getFullFilePath($this->footerPath));;
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
        $page = new Templater(static::getFullFilePath($this->filePath), $this->settings);
        $page->removeForbidden();
        $page->renderIncludes();
        $template = $page->getTemplate();
        $body = $template->find('body');
        $body->prepend($this->getHeader());
        $body->append($this->getFooter());

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
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Result\Exceptions\ResultException
     */
    public static function renderError(\Throwable $e) : \phpQueryObject
    {
        $view = new static(get_required_env('ERRORS_PATH')
            . (get_env('ERROR_TEMPLATE_PATH') ?? static::ERROR_TEMPLATE_PATH));
        $view->addData(new DbResult(['code' => $e->getCode(), 'message' => $e->getMessage()]));

        return $view->render();
    }
}
