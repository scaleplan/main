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
}