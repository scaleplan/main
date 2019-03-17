<?php

namespace Scaleplan\Main\Interfaces;

use Scaleplan\Http\Interfaces\CurrentResponseInterface;

/**
 * Interface ControllerExecutorInterface
 *
 * @package Scaleplan\Main\Interfaces
 */
interface ControllerExecutorInterface
{
    /**
     * @return mixed|null
     */
    public function getUser();

    /**
     * @param UserInterface $user
     */
    public function setUser(UserInterface $user) : void;

    /**
     * @return CacheInterface
     */
    public function getCache() : CacheInterface;

    /**
     * @param CacheInterface $cache
     */
    public function setCache(CacheInterface $cache) : void;

    /**
     * @return CurrentResponseInterface
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Http\Exceptions\EnvVarNotFoundOrInvalidException
     */
    public function execute() : CurrentResponseInterface;
}
