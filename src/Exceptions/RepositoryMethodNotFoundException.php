<?php

namespace Scaleplan\Main\Exceptions;

/**
 * Class RepositoryMethodNotFoundException
 *
 * @package Scaleplan\Main\Exceptions
 */
class RepositoryMethodNotFoundException extends AbstractException
{
    public const MESSAGE = 'main.repo-or-method-not-found';
    public const CODE = 404;
}
