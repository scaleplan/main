<?php

namespace Scaleplan\Main\Interfaces;

use PhpQuery\PhpQueryObject;
use Scaleplan\Result\Interfaces\DbResultInterface;

/**
 * Interface ViewInterface
 *
 * @package Scaleplan\Main\Interfaces
 */
interface ViewInterface
{
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
     * Отрендерить страницу
     *
     * @return PhpQueryObject
     */
    public function render() : PhpQueryObject;

    /**
     * @param \Throwable $e
     *
     * @return PhpQueryObject
     */
    public static function renderError(\Throwable $e) : PhpQueryObject;
}
