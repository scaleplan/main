<?php

namespace Scaleplan\Main\Exceptions;

/**
 * Class RepositoryMethodNotFoundException
 *
 * @package Scaleplan\Main\Exceptions
 */
class RepositoryMethodNotFoundException extends AbstractException
{
    public const MESSAGE = 'Repository or method not found.';
}
