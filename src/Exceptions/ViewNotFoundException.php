<?php

namespace Scaleplan\Main\Exceptions;

/**
 * Class ViewNotFoundException
 *
 * @package Scaleplan\Main\Exceptions
 */
class ViewNotFoundException extends AbstractException
{
    public const MESSAGE = 'View file ":subject" not found.';
    public const CODE = 404;
}
