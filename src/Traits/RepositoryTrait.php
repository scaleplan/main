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
        '[(SELECT id, name
            FROM ' . self::TABLE
        . ' WHERE id = :id)
            UNION]
          (SELECT id, name
            FROM ' . self::TABLE
        . ' WHERE 1 = 1
             [AND id != :id]
           [LIMIT :limit]
           [OFFSET :offset])';

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
    public static $getAccessList =
                       "WITH r AS (SELECT
                         (CASE WHEN rr.is_allow THEN rr.ids END) allow,
                         (CASE WHEN NOT rr.is_allow THEN rr.ids END) deny,
                          url.field,
                          rr.is_allow,
                          url.text
                        FROM access.role_right rr
                        RIGHT JOIN access.url ON url.id = rr.url_id
                        LEFT JOIN access.user_role uro ON uro.role = rr.role
                        WHERE uro.user_id = :user_id
                          AND url.text = :url
                          AND rr.is_allow
                          AND url.field = 'id'),
                        
                        u AS (SELECT
                         (CASE WHEN ur.is_allow THEN ur.ids END) allow,
                         (CASE WHEN NOT ur.is_allow THEN ur.ids END) deny,
                          url.field,
                          ur.is_allow,
                          url.text
                        FROM access.user_right ur
                        RIGHT JOIN access.url ON url.id = ur.url_id
                        WHERE ur.user_id = :user_id
                          AND url.text = :url
                          AND ur.is_allow
                          AND url.field = 'id'),
                        
                        c AS (SELECT
                         (CASE 
                            WHEN u.deny | COALESCE(r.deny, ARRAY[]::int4[]) IS NULL
                            THEN u.allow | COALESCE(r.allow, ARRAY[]::int4[])
                            ELSE (u.deny | COALESCE(r.deny, ARRAY[]::int4[])) - COALESCE(u.allow | COALESCE(r.allow, ARRAY[]::int4[]), ARRAY[]::int4[])
                          END) ids,
                         (CASE 
                            WHEN u.deny | COALESCE(r.deny, ARRAY[]::int4[]) IS NULL
                            THEN COALESCE(u.is_allow, r.is_allow)
                            ELSE false
                          END) is_allow
                        FROM r FULL JOIN u USING(field, text))
                        
                        SELECT id, name
                        FROM " . self::TABLE . ', c
                        WHERE (c.is_allow AND (c.ids IS NULL OR id::int4 = ANY(c.ids)))
                          OR (NOT c.is_allow AND c.ids IS NOT NULL AND id::int4 != ALL(c.ids))
                       [LIMIT :limit]
                       [OFFSET :offset]';

    /**
     * @dbName $current
     */
    public static $count = 'SELECT count(id) FROM ' . self::TABLE;
}
