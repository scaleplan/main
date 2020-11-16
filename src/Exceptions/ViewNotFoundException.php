<?php

namespace Scaleplan\Main\Exceptions;

/**
 * Class ViewNotFoundException
 *
 * @package Scaleplan\Main\Exceptions
 */
class ViewNotFoundException extends AbstractException
{
    public const MESSAGE = 'main.view-file-not-found';
    public const CODE = 404;
}
