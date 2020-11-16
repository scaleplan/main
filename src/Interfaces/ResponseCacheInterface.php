<?php

namespace Scaleplan\Main\Interfaces;

use Scaleplan\Data\Interfaces\CacheInterface;

/**
 * Interface ResponseCacheInterface
 *
 * @package Scaleplan\Main\Middlewares
 */
interface ResponseCacheInterface
{
    /**
     * @return CacheInterface|null
     */
    public function getCache() : ?CacheInterface;

    /**
     * @param CacheInterface|null $cache
     */
    public function setCache(?CacheInterface $cache) : void;

    /**
     * @return string
     */
    public function getCacheDbName() : string;

    /**
     * @return int|string
     */
    public function getCacheUserId();

    /**
     * @return mixed|null
     */
    public function getUser();

    /**
     * @param UserInterface $user
     */
    public function setUser(UserInterface $user) : void;

    /**
     * @return bool
     */
    public function isHit() : bool;
}
