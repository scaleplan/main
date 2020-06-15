<?php

namespace Scaleplan\Main\Exceptions;

/**
 * Class SettingNotFoundException
 *
 * @package Scaleplan\Main\Exceptions
 */
class SettingNotFoundException extends AbstractException
{
    public const MESSAGE = 'Настройка не найдена.';
    public const CODE = 404;
}
