<?php

namespace Scaleplan\Main\Exceptions;

/**
 * Class RepositoryMethodArgsInvalidException
 *
 * @package Scaleplan\Main\Exceptions
 */
class RepositoryMethodArgsInvalidException extends RepositoryException
{
    public const MESSAGE = ':subject method requires arguments as an associative array or DTO object';
    public const CODE = 406;
}
