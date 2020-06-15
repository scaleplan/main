<?php
declare(strict_types=1);

namespace Scaleplan\Main\Exceptions;

/**
 * Class AbstractException
 *
 * @package Scaleplan\Main\Exceptions
 */
abstract class AbstractException extends \Exception
{
    public const MESSAGE = 'Ошибка приложения.';
    public const CODE = 500;

    /**
     * AbstractException constructor.
     *
     * @param string $message
     * @param string|null $subject
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = null,
        string $subject = null,
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
