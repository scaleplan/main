<?php

namespace Scaleplan\Main\Exceptions;

/**
 * Class ViewNotFoundException
 *
 * @package Scaleplan\Main\Exceptions
 */
class ViewNotFoundException extends AbstractException
{
    public const MESSAGE = 'Файл представления ":subject" не найден.';
    public const CODE = 404;
}
