<?php

namespace Scaleplan\Main\Interfaces;

/**
 * Interface CheckMethodInterface
 *
 * @package Scaleplan\Main\Middlewares
 */
interface CheckMethodInterface
{
    /**
     * @return \ReflectionClass
     */
    public function getRefClass() : \ReflectionClass;

    /**
     * @return \ReflectionMethod
     */
    public function getRefMethod() : \ReflectionMethod;

    /**
     * @return array
     */
    public function getArgs() : array;
}
