<?php

namespace Scaleplan\Main\Exceptions;

/**
 * Class InvalidHostException
 *
 * @package Scaleplan\Main\Exceptions
 */
class InvalidHostException extends AbstractException
{
    public const MESSAGE = 'Неверный хост.';
    public const CODE = 400;
}
