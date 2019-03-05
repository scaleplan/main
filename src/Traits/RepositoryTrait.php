<?php

namespace Scaleplan\Main\Traits;

/**
 * Trait RepositoryTrait
 *
 * @package Scaleplan\Main\Traits
 */
trait RepositoryTrait
{
    public static $getInfo =
        'SELECT *'
      .' FROM ' . self::TABLE_NAME
      .' WHERE id = :id';

    public static $getList =
        'SELECT id, name'
      .' FROM ' . self::TABLE_NAME
      .' LIMIT :limit ' . self::DEFAULT_SORT_DIRECTION
      .' OFFSET :offset';

    public static $put =
        'INSERT INTO ' . self::TABLE_NAME
      .'   ([fields])'
      .' VALUES [expression]'
      .' RETURNING *';

    public static $update =
        'UPDATE ' . self::TABLE_NAME
      .' SET [expression:not(id)]'
      .' WHERE id = :id'
      .' RETURNING *';

    public static $delete =
        'DELETE FROM ' . self::TABLE_NAME
      .' WHERE id = :id';

    public static $activate =
        'UPDATE ' . self::TABLE_NAME
      .' SET is_active = true'
      .' WHERE id = :id';

    public static $deactivate =
        'UPDATE ' . self::TABLE_NAME
        .' SET is_active = false'
        .' WHERE id = :id';
}