<?php

namespace Scaleplan\Main\Traits;

use Scaleplan\Result\Interfaces\DbResultInterface;

/**
 * Trait RepositoryTrait
 *
 * @package Scaleplan\Main\Traits
 *
 * @method DbResultInterface getInfo(array $data)
 * @method DbResultInterface getList(array $data)
 * @method DbResultInterface put(array $data)
 * @method DbResultInterface update(array $data)
 * @method DbResultInterface delete(array $data)
 * @method DbResultInterface activate(array $data)
 * @method DbResultInterface deactivate(array $data)
 */
trait RepositoryTrait
{
    public $getInfo =
        'SELECT *'
      .' FROM ' . self::TABLE
      .' WHERE id = :id';

    public $getList =
        'SELECT id, name'
      .' FROM ' . self::TABLE
      .' LIMIT :limit ' . self::DEFAULT_SORT_DIRECTION
      .' OFFSET :offset';

    public $put =
        'INSERT INTO ' . self::TABLE
      .'   ([fields])'
      .' VALUES [expression]'
      .' RETURNING *';

    public $update =
        'UPDATE ' . self::TABLE
      .' SET [expression:not(id)]'
      .' WHERE id = :id'
      .' RETURNING *';

    public $delete =
        'DELETE FROM ' . self::TABLE
      .' WHERE id = :id';

    public $activate =
        'UPDATE ' . self::TABLE
      .' SET is_active = true'
      .' WHERE id = :id';

    public $deactivate =
        'UPDATE ' . self::TABLE
        .' SET is_active = false'
        .' WHERE id = :id';
}
