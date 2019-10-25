<?php

namespace Scaleplan\Main;

use phpDocumentor\Reflection\DocBlock;
use Scaleplan\Cache\Exceptions\MemcachedCacheException;
use Scaleplan\Cache\Exceptions\MemcachedOperationException;
use Scaleplan\Cache\Exceptions\RedisCacheException;
use Scaleplan\Data\Data;
use Scaleplan\Data\Interfaces\DataInterface;
use Scaleplan\DTO\DTO;
use Scaleplan\Helpers\ArrayHelper;
use Scaleplan\Helpers\Helper;
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
 *
 * @method DbResultInterface getFullInfo(array|DTO $data)
 * @method DbResultInterface put(array|DTO $data)
 * @method DbResultInterface update(array|DTO $data)
 * @method DbResultInterface delete(DTO|array $id)
 * @method DbResultInterface getInfo(DTO|array $id)
 * @method DbResultInterface getList(array|DTO $data)
 * @method DbResultInterface deactivate(array|DTO $data)
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
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
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
                if ($object) {
                    return $object->currentDbName ?: Helper::getSubdomain();
                }

                return Helper::getSubdomain();

            default:
                return $dbName;
        }
    }

    /**
     * Получить префикс, который нужно добавить к именам полей результата запроса к БД
     *
     * @param null|DocBlock $docBlock - блок описания префикса
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
     * @return bool
     */
    public static function isModifying(DocBlock $docBlock) : bool
    {
        return (bool)$docBlock->getTagsByName(static::MODIFYING_TAG);
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
     * @throws \Scaleplan\Event\Exceptions\ClassNotImplementsEventInterfaceException
     */
    public static function dispatchEvents(DocBlock $docBlock, DbResultInterface $data) : void
    {
        $tags = $docBlock->getTagsByName(static::EVENT_TAG);
        if (!$tags) {
            return;
        }

        foreach ($tags as $tag) {
            $eventClass = trim($tag->getDescription());
            dispatch($eventClass, ['data' => $data]);
        }
    }

    /**
     * @param DocBlock $docBlock
     * @param DbResultInterface $data
     *
     * @throws \Scaleplan\Event\Exceptions\ClassNotImplementsEventInterfaceException
     */
    public static function dispatchAsyncEvents(DocBlock $docBlock, DbResultInterface $data) : void
    {
        $tags = $docBlock->getTagsByName(static::ASYNC_EVENT_TAG);
        if (!$tags) {
            return;
        }

        foreach ($tags as $tag) {
            $eventClass = trim($tag->getDescription());
            dispatch_async($eventClass, ['data' => $data]);
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
     * @param self|null $object
     *
     * @return DbResultInterface
     *
     * @throws Exceptions\DatabaseException
     * @throws RepositoryMethodArgsInvalidException
     * @throws RepositoryMethodNotFoundException
     * @throws \ReflectionException
     * @throws \Scaleplan\Data\Exceptions\DataException
     * @throws \Scaleplan\Data\Exceptions\DbConnectException
     * @throws MemcachedCacheException
     * @throws MemcachedOperationException
     * @throws RedisCacheException
     * @throws \Scaleplan\Data\Exceptions\ValidationException
     * @throws \Scaleplan\Db\Exceptions\ConnectionStringException
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

        if ($params && \is_array($params[0]) && !ArrayHelper::isAccos($params[0])) {
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
        $data->setDbConnect($app::getDB(static::getDbName($docBlock, $object)));
        $data->setPrefix(static::getPrefix($docBlock));
        if (static::isModifying($docBlock)) {
            $data->setIsModifying();
        }

        $castings = static::getCastings($docBlock);
        if (null !== $castings) {
            $data->setCastings($castings);
        }

        $result = $data->getValue();
        $result->setModelClass(static::getModelClass($docBlock));
        static::dispatchEvents($docBlock, $result);
        static::dispatchAsyncEvents($docBlock, $result);

        return $result;
    }

    /**
     * @param string $propertyName
     * @param array $data
     *
     * @return DbResultInterface
     *
     * @throws Exceptions\DatabaseException
     * @throws RepositoryMethodArgsInvalidException
     * @throws RepositoryMethodNotFoundException
     * @throws \ReflectionException
     * @throws \Scaleplan\Data\Exceptions\DataException
     * @throws \Scaleplan\Data\Exceptions\DbConnectException
     * @throws MemcachedCacheException
     * @throws MemcachedOperationException
     * @throws RedisCacheException
     * @throws \Scaleplan\Data\Exceptions\ValidationException
     * @throws \Scaleplan\Db\Exceptions\ConnectionStringException
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
     * @throws RepositoryMethodArgsInvalidException
     * @throws RepositoryMethodNotFoundException
     * @throws \ReflectionException
     * @throws \Scaleplan\Data\Exceptions\DataException
     * @throws \Scaleplan\Data\Exceptions\DbConnectException
     * @throws MemcachedCacheException
     * @throws MemcachedOperationException
     * @throws RedisCacheException
     * @throws \Scaleplan\Data\Exceptions\ValidationException
     * @throws \Scaleplan\Db\Exceptions\ConnectionStringException
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
     * @throws \Scaleplan\Result\Exceptions\ResultException
     */
    public function __call(string $propertyName, array $data) : DbResultInterface
    {
        return static::invoke($propertyName, $data, $this);
    }
}
