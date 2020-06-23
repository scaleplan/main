<?php

namespace Scaleplan\Main\Exceptions;

/**
 * Class RepositoryMethodArgsInvalidException
 *
 * @package Scaleplan\Main\Exceptions
 */
class RepositoryMethodArgsInvalidException extends RepositoryException
{
    public const MESSAGE = 'main.array-required';
    public const CODE = 406;
}
