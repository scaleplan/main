<?php

namespace Scaleplan\Main\Exceptions;

/**
 * Class ControllerException
 *
 * @package Scaleplan\Main\Exceptions
 */
class ControllerException extends AbstractException
{
    public const MESSAGE = 'main.controller-error';
    public const CODE = 400;
}
