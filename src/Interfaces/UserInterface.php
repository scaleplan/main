<?php

namespace Scaleplan\Main\Interfaces;

/**
 * Interface UserInterface
 *
 * @package Scaleplan\Main\Interfaces
 */
interface UserInterface
{
    /**
     * Вернуть идентификатор пользователя
     *
     * @return int
     */
    public function getId() : int;

    /**
     * Синглтон объекта текущего пользователя
     *
     * @return UserInterface
     */
    public static function getCurrentUser() : UserInterface;

    /**
     * Получить идентификатор пользователя по умолчанию
     *
     * @return int
     */
    public static function getDefaultId() : int;
}