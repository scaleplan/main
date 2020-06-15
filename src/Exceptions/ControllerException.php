<?php

namespace Scaleplan\Main\Exceptions;

/**
 * Class ControllerException
 *
 * @package Scaleplan\Main\Exceptions
 */
class ControllerException extends AbstractException
{
    public const MESSAGE = 'Ошибка контроллера.';
    public const CODE = 400;
}
