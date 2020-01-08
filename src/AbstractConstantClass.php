<?php
declare(strict_types=1);

namespace Scaleplan\Main;

use function Scaleplan\Translator\translate;

/**
 * Class AbstractConstantClass
 *
 * @package Scaleplan\Main
 */
class AbstractConstantClass
{
    public const ALL = [];

    /**
     * @return array
     */
    public static function getLocalAll() : array
    {
        return static::getLocalConstants(static::ALL);
    }

    /**
     * @param array $constants
     *
     * @return array
     */
    protected static function getLocalConstants(array $constants) : array
    {
        return array_map(static function ($value) {
            return translate("constant.$value") ?? $value;
        }, $constants);
    }
}
