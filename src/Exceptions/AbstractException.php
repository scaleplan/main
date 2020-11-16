<?php
declare(strict_types=1);

namespace Scaleplan\Main\Exceptions;

use function Scaleplan\Translator\translate;

/**
 * Class AbstractException
 *
 * @package Scaleplan\Main\Exceptions
 */
abstract class AbstractException extends \Exception
{
    public const MESSAGE = 'main.app-error';
    public const CODE    = 500;

    /**
     * AbstractException constructor.
     *
     * @param string|null $message
     * @param string|null $subject
     * @param int $code
     * @param \Throwable|null $previous
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     */
    public function __construct(
        string $message = null,
        string $subject = null,
        int $code = 0,
        \Throwable $previous = null
    )
    {
        parent::__construct(
            translate($message ?? '', ['subject' => $subject,])
                ?: $message
                ?: translate(static::MESSAGE, ['subject' => $subject,])
                ?: static::MESSAGE,
            $code ?: static::CODE,
            $previous
        );
    }
}
