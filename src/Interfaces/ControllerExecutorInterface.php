<?php

namespace Scaleplan\Main\Interfaces;

use Scaleplan\Data\Interfaces\CacheInterface;
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
     */
    public function execute() : CurrentResponseInterface;
}
