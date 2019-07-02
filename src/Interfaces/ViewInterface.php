<?php

namespace Scaleplan\Main\Interfaces;

use Scaleplan\Result\Interfaces\DbResultInterface;

/**
 * Interface ViewInterface
 *
 * @package Scaleplan\Main\Interfaces
 */
interface ViewInterface
{
    /**
     * @return string|null
     */
    public function getHeaderPath() : ?string;

    /**
     * @param string|null $headerPath
     */
    public function setHeaderPath(?string $headerPath) : void;

    /**
     * @return string|null
     */
    public function getFooterPath() : ?string;

    /**
     * @param string|null $footerPath
     */
    public function setFooterPath(?string $footerPath) : void;

    /**
     * @return string|null
     */
    public function getSideMenuPath() : ?string;

    /**
     * @param string|null $sideMenuPath
     */
    public function setSideMenuPath(?string $sideMenuPath) : void;

    /**
     * Установить является ли представление письмом
     *
     * @param bool $isMessage - новое значение
     */
    public function setIsMessage(bool $isMessage) : void;

    /**
     * Путь к файлу относительно директории с шаблонами представлений
     *
     * @return string
     */
    public function getFilePath() : string;

    /**
     * Добавить данные для добавления на страницу
     *
     * @param DbResultInterface $data - данные
     * @param string $parentSelector - в элемент с каким селектором добавлять данные
     */
    public function addData(DbResultInterface $data, string $parentSelector = 'body') : void;

    /**
     * Удалить данные для добавления на страницу
     *
     * @param string $parentSelector - в элемент с каким селектором больше не надо добавлять данные
     */
    public function deleteData(string $parentSelector) : void;

    /**
     * @return \phpQueryObject
     */
    public function getHeader() : \phpQueryObject;

    /**
     * @return \phpQueryObject
     */
    public function getFooter() : \phpQueryObject;

    /**
     * @return \phpQueryObject
     */
    public function getSideMenu() : \phpQueryObject;

    /**
     * Отрендерить страницу
     *
     * @return \phpQueryObject
     */
    public function render() : \phpQueryObject;
}
