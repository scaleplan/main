<?php

namespace Scaleplan\Main\Traits;

use Scaleplan\DTO\DTO;
use Scaleplan\Result\Interfaces\DbResultInterface;

/**
 * Trait RepositoryTrait
 *
 * @package Scaleplan\Main\Traits
 *
 * @method DbResultInterface getInfo(array|DTO $data)
 * @method DbResultInterface getList(array|DTO $data)
 * @method DbResultInterface put(array|DTO $data)
 * @method DbResultInterface update(array|DTO $data)
 * @method DbResultInterface delete(array|DTO $data)
 * @method DbResultInterface activate(array|DTO $data)
 * @method DbResultInterface deactivate(array|DTO $data)
 */
trait RepositoryTrait
{
    /**
     * @dbName $current
     */
    public static $getInfo =
        'SELECT *'
        . ' FROM ' . self::TABLE
        . ' WHERE id = :id';

    /**
     * @dbName $current
     */
    public static $getList =
        'SELECT id, name'
        . ' FROM ' . self::TABLE
        . ' [LIMIT :limit]'
        . ' [OFFSET :offset]';

    /**
     * @dbName $current
     */
    public static $put =
        'INSERT INTO ' . self::TABLE
        . '   ([fields])'
        . ' VALUES [expression]'
        . ' RETURNING *';

    /**
     * @dbName $current
     */
    public static $update =
        'UPDATE ' . self::TABLE
        . ' SET [expression:not(id)]'
        . ' WHERE id = :id'
        . ' RETURNING *';

    /**
     * @dbName $current
     */
    public static $delete =
        'DELETE FROM ' . self::TABLE
        . ' WHERE id = :id';

    /**
     * @dbName $current
     */
    public static $activate =
        'UPDATE ' . self::TABLE
        . ' SET is_active = true'
        . ' WHERE id = :id';

    /**
     * @dbName $current
     */
    public static $deactivate =
        'UPDATE ' . self::TABLE
        . ' SET is_active = false'
        . ' WHERE id = :id';
}
