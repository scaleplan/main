<?php

namespace Scaleplan\Main\Exceptions;

/**
 * Class AbstractException
 *
 * @package Scaleplan\Main\Exceptions
 */
abstract class AbstractException extends \Exception
{
    public const MESSAGE = 'Application error.';

    /**
     * AbstractException constructor.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     * @param string|null $subject
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        \Throwable $previous = null,
        string $subject = null
    )
    {
        parent::__construct(str_replace(':subject', $subject, $message ?? static::MESSAGE), $code, $previous);
    }
}
