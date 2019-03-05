<?php

namespace Scaleplan\Main\Exceptions;

/**
 * Class AbstractException
 *
 * @package Scaleplan\Main\Exceptions
 */
abstract class AbstractException extends \Exception
{
    public const MESSAGE = '';

    /**
     * AbstractException constructor.
     *
     * @param string|null $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(string $message = '', int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message ?: static::MESSAGE, $code, $previous);
    }
}