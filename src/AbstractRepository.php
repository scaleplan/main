<?php

namespace Scaleplan\Main;

use phpDocumentor\Reflection\DocBlock;
use Scaleplan\Data\Data;
use function Scaleplan\Helpers\get_required_env;
use Scaleplan\Helpers\Helper;
use Scaleplan\Main\Constants\ConfigConstants;
use Scaleplan\Main\Exceptions\RepositoryException;
use Scaleplan\Main\Exceptions\ServiceMethodNotFoundException;
use Scaleplan\Result\DbResult;

/**
 * Мать всех моделей
 *
 * Class Service
 *
 * @package Scaleplan\Main
 */
abstract class AbstractRepository
{
    /**
     * Идентификатор модели в системе Access
     */
    public const MODEL_TYPE_ID = 0;

    /**
     * Вернуть имя базы данных в зависимости от субдомена
     *
     * @param DocBlock $docBlock - блок описания метода получения имени базы данных
     *
     * @return string
     *
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     */
    public static function getDbName(DocBlock $docBlock) : string
    {
        $docParam = $docBlock->getTagsByName('dbName')[0] ?? null;
        if (!$docParam) {
            return get_required_env(ConfigConstants::DEFAULT_DB);
        }

        $dbName = trim($docParam->getDescription());

        switch ($dbName) {
            case '$current':
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
     * @return string
     */
    public static function getPrefix(DocBlock $docBlock) : string
    {
        $docParam = $docBlock->getTagsByName('prefix')[0] ?? null;
        if (!$docParam) {
            return '';
        }

        return trim($docParam->getDescription());
    }

    /**
     * @param string $propertyName
     * @param array $data
     * @param object|null $object
     *
     * @return DbResult
     *
     * @throws Exceptions\CacheException
     * @throws Exceptions\DatabaseException
     * @throws RepositoryException
     * @throws ServiceMethodNotFoundException
     * @throws \ReflectionException
     * @throws \Scaleplan\Data\Exceptions\CacheDriverNotSupportedException
     * @throws \Scaleplan\Data\Exceptions\DataException
     * @throws \Scaleplan\Db\Exceptions\ConnectionStringException
     * @throws \Scaleplan\Db\Exceptions\PDOConnectionException
     * @throws \Scaleplan\Db\Exceptions\QueryCountNotMatchParamsException
     * @throws \Scaleplan\Db\Exceptions\QueryExecutionException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Result\Exceptions\ResultException
     */
    public static function invoke(string $propertyName, array $data, \object $object = null) : DbResult
    {
        if (!property_exists(static::class, $propertyName)) {
            throw new ServiceMethodNotFoundException();
        }

        if (count($data) !== 1 || !\is_array($data[0])) {
            throw new RepositoryException("Метод $propertyName принимает параметры в виде массива");
        }

        $reflectionProperty = new \ReflectionProperty(static::class, $propertyName);
        $sql = $reflectionProperty->getValue($object);
        $docBlock = new DocBlock($reflectionProperty->getDocComment());

        $dbName = static::getDbName($docBlock);

        $dataStory = Data::getInstance($sql, $data);
        $dataStory->setCacheConnect(App::getCache());
        $dataStory->setDbConnect(App::getDB($dbName));
        $dataStory->setPrefix(static::getPrefix($docBlock));

        return $dataStory->getValue();
    }

    /**
     * @param string $propertyName
     * @param array $data
     *
     * @return DbResult
     *
     * @throws Exceptions\CacheException
     * @throws Exceptions\DatabaseException
     * @throws RepositoryException
     * @throws ServiceMethodNotFoundException
     * @throws \ReflectionException
     * @throws \Scaleplan\Data\Exceptions\CacheDriverNotSupportedException
     * @throws \Scaleplan\Data\Exceptions\DataException
     * @throws \Scaleplan\Db\Exceptions\ConnectionStringException
     * @throws \Scaleplan\Db\Exceptions\PDOConnectionException
     * @throws \Scaleplan\Db\Exceptions\QueryCountNotMatchParamsException
     * @throws \Scaleplan\Db\Exceptions\QueryExecutionException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Result\Exceptions\ResultException
     */
    public static function __callStatic(string $propertyName, array $data) : DbResult
    {
        return static::invoke($propertyName, $data);
    }

    /**
     * @param string $propertyName
     * @param array $data
     *
     * @return DbResult
     *
     * @throws Exceptions\CacheException
     * @throws Exceptions\DatabaseException
     * @throws RepositoryException
     * @throws ServiceMethodNotFoundException
     * @throws \ReflectionException
     * @throws \Scaleplan\Data\Exceptions\CacheDriverNotSupportedException
     * @throws \Scaleplan\Data\Exceptions\DataException
     * @throws \Scaleplan\Db\Exceptions\ConnectionStringException
     * @throws \Scaleplan\Db\Exceptions\PDOConnectionException
     * @throws \Scaleplan\Db\Exceptions\QueryCountNotMatchParamsException
     * @throws \Scaleplan\Db\Exceptions\QueryExecutionException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Result\Exceptions\ResultException
     */
    public function __call(string $propertyName, array $data) : DbResult
    {
        return static::invoke($propertyName, $data, $this);
    }
}
