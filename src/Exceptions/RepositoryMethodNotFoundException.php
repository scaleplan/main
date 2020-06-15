<?php

namespace Scaleplan\Main\Exceptions;

/**
 * Class RepositoryMethodNotFoundException
 *
 * @package Scaleplan\Main\Exceptions
 */
class RepositoryMethodNotFoundException extends AbstractException
{
    public const MESSAGE = 'Репозиторий или метод репозитория не найден.';
    public const CODE = 404;
}
