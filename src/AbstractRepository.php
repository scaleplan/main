<?php
declare(strict_types=1);

namespace Scaleplan\Main;

use phpDocumentor\Reflection\DocBlock;
use Scaleplan\Cache\Exceptions\MemcachedCacheException;
use Scaleplan\Cache\Exceptions\MemcachedOperationException;
use Scaleplan\Cache\Exceptions\RedisCacheException;
use Scaleplan\Data\Data;
use Scaleplan\Data\Interfaces\DataInterface;
use Scaleplan\Db\PgDb;
use Scaleplan\DTO\DTO;
use Scaleplan\Main\Constants\ConfigConstants;
use Scaleplan\Main\Exceptions\RepositoryMethodArgsInvalidException;
use Scaleplan\Main\Exceptions\RepositoryMethodNotFoundException;
use Scaleplan\Result\Interfaces\DbResultInterface;
use function Scaleplan\DependencyInjection\get_required_container;
use function Scaleplan\DependencyInjection\get_static_container;
use function Scaleplan\Event\dispatch;
use function Scaleplan\Event\dispatch_async;
use function Scaleplan\Helpers\get_required_env;

/**
 * Class AbstractRepository
 *
 * @package Scaleplan\Main
 */
abstract class AbstractRepository
{
    public const TABLE                  = null;
    public const DEFAULT_SORT_DIRECTION = 'DESC';

    public const DB_NAME_TAG     = 'dbName';
    public const PREFIX_TAG      = 'prefix';
    public const MODIFYING_TAG   = 'modifying';
    public const MODEL_TAG       = 'model';
    public const CASTINGS_TAG    = 'cast';
    public const EVENT_TAG       = 'event';
    public const ASYNC_EVENT_TAG = 'asyncEvent';
    public const ASYNC_TAG       = 'async';
    public const DEFERRED_TAG    = 'deferred';
    public const ID_TAG          = 'idTag';
    public const TAGS_TAG        = 'tags';
    public const ID_FIELD_TAG    = 'idField';

    /**
     * @var string
     */
    protected $currentDbName;

    /**
     * @param string $dbName
     */
    public function setCurrentDbName(string $dbName) : void
    {
        $this->currentDbName = $dbName;
    }

    /**
     * Вернуть имя базы данных в зависимости от субдомена
     *
     * @param DocBlock $docBlock - блок описания метода получения имени базы данных
     * @param AbstractRepository $object - объект репозитория
     *
     * @return string
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Helpers\Exceptions\HelperException
     */
    public static function getDbName(DocBlock $docBlock, ?self $object) : string
    {
        $docParam = $docBlock->getTagsByName(static::DB_NAME_TAG)[0] ?? null;
        if (!$docParam) {
            return get_required_env(ConfigConstants::DEFAULT_DB);
        }

        $dbName = trim($docParam->getDescription());

        switch ($dbName) {
            case '$current':
                /** @var App $app */
                $app = get_static_container(App::class);
                if ($object) {
                    return $object->currentDbName ?: $app::getSubdomain();
                }

                return $app::getSubdomain();

            default:
                return $dbName;
        }
    }

    /**
     * @param DocBlock $docBlock
     *
     * @return array|null
     */
    protected static function getTags(DocBlock $docBlock) : ?array
    {
        $tagStr = $docBlock->getTagsByName(static::TAGS_TAG)[0] ?? null;

        return null !== $tagStr ? array_map('trim', explode(',', $tagStr->getDescription())) : null;
    }

    /**
     * Получить префикс, который нужно добавить к именам полей результата запроса к БД
     *
     * @param DocBlock $docBlock - блок описания префикса
     *
     * @return string|null
     */
    public static function getPrefix(DocBlock $docBlock) : ?string
    {
        $docParam = $docBlock->getTagsByName(static::PREFIX_TAG)[0] ?? null;
        if (!$docParam) {
            return null;
        }

        return trim($docParam->getDescription());
    }

    /**
     * @param DocBlock $docBlock
     *
     * @return string
     */
    public static function getIdTag(DocBlock $docBlock) : string
    {
        $docParam = $docBlock->getTagsByName(static::ID_TAG)[0] ?? null;

        return $docParam ? trim((string)$docParam->getDescription()) : '';
    }

    /**
     * @param DocBlock $docBlock
     *
     * @return string
     */
    public static function getIdField(DocBlock $docBlock) : string
    {
        $docParam = $docBlock->getTagsByName(static::ID_FIELD_TAG)[0] ?? null;

        return $docParam ? trim((string)$docParam->getDescription()) : '';
    }

    /**
     * @param DocBlock $docBlock
     *
     * @return bool
     */
    public static function isModifying(DocBlock $docBlock) : bool
    {
        return (bool)$docBlock->getTagsByName(static::MODIFYING_TAG);
    }

    /**
     * @param DocBlock $docBlock
     *
     * @return bool
     */
    public static function isDeferred(DocBlock $docBlock) : bool
    {
        return (bool)$docBlock->getTagsByName(static::DEFERRED_TAG);
    }

    /**
     * @param DocBlock $docBlock
     *
     * @return bool
     */
    public static function isAsync(DocBlock $docBlock) : bool
    {
        return (bool)$docBlock->getTagsByName(static::ASYNC_TAG);
    }

    /**
     * @param DocBlock $docBlock
     *
     * @return bool|array|null
     */
    public static function getCastings(DocBlock $docBlock)
    {
        $tags = $docBlock->getTagsByName(static::CASTINGS_TAG);
        if (!$tags) {
            return null;
        }

        $value = explode(' ', reset($tags)->getDescription());
        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        $castings = null;
        foreach ($tags as $cast) {
            $value = explode(' ', $cast->getDescription());
            if (empty($value[1]) || empty($value[0])) {
                continue;
            }

            $castings[\trim($value[0])] = \trim($value[1]);
        }

        return $castings;
    }

    /**
     * @param DocBlock $docBlock
     * @param DbResultInterface $data
     *
     * @param array $params
     *
     * @throws \Scaleplan\Event\Exceptions\ClassNotImplementsEventInterfaceException
     */
    public static function dispatchEvents(DocBlock $docBlock, DbResultInterface $data, array $params) : void
    {
        $tags = $docBlock->getTagsByName(static::EVENT_TAG);
        if (!$tags) {
            return;
        }

        foreach ($tags as $tag) {
            $eventClass = trim($tag->getDescription());
            dispatch($eventClass, ['data' => $data, 'params' => $params,]);
        }
    }

    /**
     * @param DocBlock $docBlock
     * @param DbResultInterface $data
     *
     * @param array $params
     *
     * @throws \Scaleplan\Event\Exceptions\ClassNotImplementsEventInterfaceException
     */
    public static function dispatchAsyncEvents(DocBlock $docBlock, DbResultInterface $data, array $params) : void
    {
        $tags = $docBlock->getTagsByName(static::ASYNC_EVENT_TAG);
        if (!$tags) {
            return;
        }

        foreach ($tags as $tag) {
            $eventClass = trim($tag->getDescription());
            dispatch_async($eventClass, ['data' => $data, 'params' => $params,]);
        }
    }

    /**
     * @param DocBlock $docBlock
     *
     * @return string|null
     */
    public static function getModelClass(DocBlock $docBlock) : ?string
    {
        $docParam = $docBlock->getTagsByName(static::MODEL_TAG)[0] ?? null;
        if (!$docParam) {
            return null;
        }

        return trim($docParam->getDescription());
    }

    /**
     * @param string $propertyName
     *
     * @return \ReflectionClassConstant|\ReflectionProperty
     *
     * @throws RepositoryMethodNotFoundException
     * @throws \ReflectionException
     */
    private static function getReflector(string $propertyName) : \Reflector
    {
        if (\defined("static::$propertyName")) {
            return new \ReflectionClassConstant(static::class, $propertyName);
        }

        if (property_exists(static::class, $propertyName)) {
            return new \ReflectionProperty(static::class, $propertyName);
        }

        throw new RepositoryMethodNotFoundException();
    }

    /**
     * @param string $propertyName
     * @param array $params
     * @param AbstractRepository|null $object
     *
     * @return DbResultInterface
     *
     * @throws Exceptions\DatabaseException
     * @throws MemcachedCacheException
     * @throws MemcachedOperationException
     * @throws RedisCacheException
     * @throws RepositoryMethodArgsInvalidException
     * @throws RepositoryMethodNotFoundException
     * @throws \ReflectionException
     * @throws \Scaleplan\Data\Exceptions\DataException
     * @throws \Scaleplan\Data\Exceptions\DbConnectException
     * @throws \Scaleplan\Data\Exceptions\ValidationException
     * @throws \Scaleplan\Db\Exceptions\DbException
     * @throws \Scaleplan\Db\Exceptions\InvalidIsolationLevelException
     * @throws \Scaleplan\Db\Exceptions\PDOConnectionException
     * @throws \Scaleplan\Db\Exceptions\QueryCountNotMatchParamsException
     * @throws \Scaleplan\Db\Exceptions\QueryExecutionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Event\Exceptions\ClassNotImplementsEventInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Helpers\Exceptions\HelperException
     * @throws \Scaleplan\Result\Exceptions\ResultException
     */
    public static function invoke(
        string $propertyName,
        array $params,
        self $object = null
    ) : DbResultInterface
    {
//        if ($params && empty($params[0])) {
//            throw new RepositoryMethodArgsInvalidException(null, $propertyName);
//        }

        if ($params && !is_array($params[0]) && !($params[0] instanceof DTO)) {
            throw new RepositoryMethodArgsInvalidException(null, $propertyName);
        }

        $params && $params = $params[0];
        if ($params instanceof DTO) {
            $params = $params->toSnakeArray();
        }

        $reflector = static::getReflector($propertyName);
        if ($reflector instanceof \ReflectionClassConstant) {
            $sql = $reflector->getValue();
        } else {
            $sql = $reflector->getValue($object);
        }

        $docBlock = new DocBlock($reflector->getDocComment());

        /** @var App $app */
        $app = get_static_container(App::class);
        /** @var Data $data */
        $data = get_required_container(DataInterface::class, [$sql, $params]);
        $db = $app::getDB(static::getDbName($docBlock, $object));
        if ($db instanceof PgDb && static::isDeferred($docBlock)) {
            $db->setTransactionDeferred(true);
        }

        $data->setDbConnect($db);

        if (static::isAsync($docBlock)) {
            $data->setIsAsync();
        }

        $data->setPrefix(static::getPrefix($docBlock));
        if (static::isModifying($docBlock)) {
            $data->setIsModifying();
        }

        $castings = static::getCastings($docBlock);
        if (null !== $castings) {
            $data->setCastings($castings);
        }

        $data->setTags(static::getTags($docBlock));
        $data->setIdTag(static::getIdTag($docBlock));
        $data->setIdField(static::getIdField($docBlock));

        $result = $data->getValue();
        $result->setModelClass(static::getModelClass($docBlock));
        static::dispatchEvents($docBlock, $result, $params);
        static::dispatchAsyncEvents($docBlock, $result, $params);

        return $result;
    }

    /**
     * @param string $propertyName
     * @param array $data
     *
     * @return DbResultInterface
     *
     * @throws Exceptions\DatabaseException
     * @throws MemcachedCacheException
     * @throws MemcachedOperationException
     * @throws RedisCacheException
     * @throws RepositoryMethodArgsInvalidException
     * @throws RepositoryMethodNotFoundException
     * @throws \ReflectionException
     * @throws \Scaleplan\Data\Exceptions\DataException
     * @throws \Scaleplan\Data\Exceptions\DbConnectException
     * @throws \Scaleplan\Data\Exceptions\ValidationException
     * @throws \Scaleplan\Db\Exceptions\DbException
     * @throws \Scaleplan\Db\Exceptions\InvalidIsolationLevelException
     * @throws \Scaleplan\Db\Exceptions\PDOConnectionException
     * @throws \Scaleplan\Db\Exceptions\QueryCountNotMatchParamsException
     * @throws \Scaleplan\Db\Exceptions\QueryExecutionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Event\Exceptions\ClassNotImplementsEventInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Helpers\Exceptions\HelperException
     * @throws \Scaleplan\Result\Exceptions\ResultException
     */
    public static function __callStatic(string $propertyName, array $data) : DbResultInterface
    {
        return static::invoke($propertyName, $data);
    }

    /**
     * @param string $propertyName
     * @param array $data
     *
     * @return DbResultInterface
     *
     * @throws Exceptions\DatabaseException
     * @throws MemcachedCacheException
     * @throws MemcachedOperationException
     * @throws RedisCacheException
     * @throws RepositoryMethodArgsInvalidException
     * @throws RepositoryMethodNotFoundException
     * @throws \ReflectionException
     * @throws \Scaleplan\Data\Exceptions\DataException
     * @throws \Scaleplan\Data\Exceptions\DbConnectException
     * @throws \Scaleplan\Data\Exceptions\ValidationException
     * @throws \Scaleplan\Db\Exceptions\DbException
     * @throws \Scaleplan\Db\Exceptions\InvalidIsolationLevelException
     * @throws \Scaleplan\Db\Exceptions\PDOConnectionException
     * @throws \Scaleplan\Db\Exceptions\QueryCountNotMatchParamsException
     * @throws \Scaleplan\Db\Exceptions\QueryExecutionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Event\Exceptions\ClassNotImplementsEventInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Helpers\Exceptions\HelperException
     * @throws \Scaleplan\Result\Exceptions\ResultException
     */
    public function __call(string $propertyName, array $data) : DbResultInterface
    {
        return static::invoke($propertyName, $data, $this);
    }
}
