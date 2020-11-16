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
 * @method DbResultInterface getAccessList(array|DTO $data)
 * @method DbResultInterface count()
 */
trait RepositoryTrait
{
    /**
     * RepositoryTrait constructor.
     */
    public function __construct()
    {
        foreach (['getInfo', 'getList', 'put', 'update', 'delete', 'getAccessList', 'count',] as $propertyName) {
            $this->$propertyName = str_replace(':table', static::TABLE, $this->$propertyName);
        }
    }

    /**
     * @dbName $current
     */
    public $getInfo =
        'SELECT * FROM :table WHERE id = :id';

    /**
     * @dbName $current
     */
    public $getList =
        "[(SELECT id, name
           FROM :table
           WHERE id = :id)
           UNION]
          (SELECT id, name
           FROM :table
           WHERE 1 = 1
            [AND id != :id]
            [AND name ILIKE '%' || :search || '%']
          [LIMIT :limit]
          [OFFSET :offset])";

    /**
     * @dbName $current
     */
    public $put =
        'INSERT INTO :table([fields])
         VALUES [expression]
         RETURNING id, [fields]';

    /**
     * @dbName $current
     */
    public $update =
        'UPDATE :table
         SET [expression]
         WHERE id = :id
         RETURNING [fields]';

    /**
     * @dbName $current
     */
    public $delete =
        'DELETE FROM :table
         WHERE id = :id
         RETURNING id';

    /**
     * @dbName $current
     */
    public $getAccessList =
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
                        FROM :table, c
                        WHERE (c.is_allow AND (c.ids IS NULL OR id::int4 = ANY(c.ids)))
                          OR (NOT c.is_allow AND c.ids IS NOT NULL AND id::int4 != ALL(c.ids))
                       [LIMIT :limit]
                       [OFFSET :offset]";

    /**
     * @dbName $current
     */
    public $count = 'SELECT count(id) FROM :table';
}
