<?php
declare(strict_types=1);

namespace Scaleplan\Main;

use PhpQuery\PhpQueryObject;
use Scaleplan\Main\Constants\ConfigConstants;
use Scaleplan\Main\Interfaces\ViewInterface;
use Scaleplan\Result\DbResult;
use Scaleplan\Result\Interfaces\ArrayResultInterface;
use Scaleplan\Result\Interfaces\DbResultInterface;
use Scaleplan\Templater\Templater;
use function Scaleplan\Helpers\get_env;
use function Scaleplan\Helpers\get_required_env;

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
    public const DATA_LABEL          = 'data';
    public const OPTIONAL_LABEL      = 'is_optional';

    /**
     * Путь к файлу шаблона
     *
     * @var string
     */
    protected $filePath;

    /**
     * Настройки шаблона
     *
     * @var array
     */
    protected $settings = [];

    /**
     * Данные для добавления в шаблон
     *
     * @var DbResultInterface[]
     */
    protected $data = [];

    /**
     * Является ли представление письмом
     *
     * @var bool
     */
    protected $isMessage = false;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var string|null
     */
    protected $userRole;

    /**
     * @var PhpQueryObject
     */
    protected $page;

    /**
     * @var Templater
     */
    protected $template;

    /**
     * View constructor.
     *
     * @param string|null $filePath - путь к файлу шаблона
     * @param array $settings - настройки шаблонизатора
     */
    public function __construct(
        string $filePath = null,
        array $settings = []
    )
    {
        $this->filePath = $filePath;
        $this->settings = $settings;
    }

    /**
     * @return PhpQueryObject
     */
    public function getPage() : PhpQueryObject
    {
        return $this->page;
    }

    /**
     * @param PhpQueryObject $page
     */
    public function setPage(PhpQueryObject $page) : void
    {
        $this->page = $page;
    }

    /**
     * @param string|null $userRole
     */
    public function setUserRole(?string $userRole) : void
    {
        $this->userRole = $userRole;
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
    public static function getFullFilePath(?string $filePath) : ?string
    {
        if (null === $filePath) {
            return null;
        }

        return get_required_env(ConfigConstants::BUNDLE_PATH) . get_required_env('VIEWS_PATH') . $filePath;
    }

    /**
     * Добавить данные для добавления на страницу
     *
     * @param ArrayResultInterface $data - данные
     * @param string $parentSelector - в элемент с каким селектором добавлять данные
     * @param bool $isOptional - не выдавать
     */
    public function addData(ArrayResultInterface $data, string $parentSelector = 'body', $isOptional = false) : void
    {
        $this->data[$parentSelector] = [static::DATA_LABEL => $data, static::OPTIONAL_LABEL => $isOptional,];
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
     * @param string $title
     */
    public function setTitle(string $title) : void
    {
        $this->title = $title;
    }

    /**
     * @return Templater
     *
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     */
    public function getTemplate() : Templater
    {
        if (!$this->template) {
            if ($this->page) {
                $this->template = new Templater(null, $this->settings);
                $this->template->setTemplate($this->page);
            } else {
                $this->template = new Templater(static::getFullFilePath($this->filePath), $this->settings);
            }
        }

        return $this->template;
    }

    /**
     * @param Templater $template
     */
    public function setTemplate(Templater $template) : void
    {
        $this->template = $template;
    }

    /**
     * Отрендерить страницу
     *
     * @return PhpQueryObject
     *
     * @throws \PhpQuery\Exceptions\PhpQueryException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Templater\Exceptions\DomElementNotFoundException
     * @throws \Exception
     */
    public function render() : PhpQueryObject
    {
        $template = $this->getTemplate();

        $template->setUserRole($this->userRole);
        $template->renderIncludes();
        $template->removeForbidden();
        $page = $template->getTemplate();
        if ($this->title) {
            $page->find('title')->text($this->title);
        }

        foreach ($this->data as $selector => $data) {
            if ($data[static::OPTIONAL_LABEL]) {
                $template->setOptionalMultiData($data[static::DATA_LABEL]->getArrayResult(), $selector);
                continue;
            }

            $template->setMultiData($data[static::DATA_LABEL]->getArrayResult(), $selector);
        }

        return $page;
    }

    /**
     * @param \Throwable $e
     *
     * @return PhpQueryObject
     *
     * @throws \PhpQuery\Exceptions\PhpQueryException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Result\Exceptions\ResultException
     * @throws \Scaleplan\Templater\Exceptions\DomElementNotFoundException
     */
    public static function renderError(\Throwable $e) : PhpQueryObject
    {
        $view = new self(get_required_env('ERRORS_PATH')
            . (get_env('ERROR_TEMPLATE_PATH') ?? static::ERROR_TEMPLATE_PATH));
        $view->addData(
            new DbResult(['code' => $e->getCode(), 'message' => iconv('UTF-8', 'UTF-8//IGNORE', $e->getMessage())])
        );
        $view->setTitle($e->getMessage());

        return $view->render();
    }

    /**
     * @return string
     *
     * @throws \PhpQuery\Exceptions\PhpQueryException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Templater\Exceptions\DomElementNotFoundException
     */
    public function __toString()
    {
        return (string)$this->render();
    }
}
