<?php

namespace Scaleplan\Main\Exceptions;

/**
 * Class RepositoryMethodArgsInvalidException
 *
 * @package Scaleplan\Main\Exceptions
 */
class RepositoryMethodArgsInvalidException extends RepositoryException
{
    public const MESSAGE = 'Метод :subject требует аргументы в виде ассоциативного массива или объекта DTO.';
    public const CODE = 406;
}
