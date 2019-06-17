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
    public const CODE = 500;

    /**
     * AbstractException constructor.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     * @param string|null $subject
     */
    public function __construct(
        string $subject = null,
        string $message = '',
        int $code = 0,
        \Throwable $previous = null
    )
    {
        parent::__construct(
            str_replace(':subject', $subject, $message ?: static::MESSAGE) ?: static::MESSAGE,
            $code ?: static::CODE,
            $previous
        );
    }
}
