<?php

namespace Scaleplan\Main;

use phpDocumentor\Reflection\DocBlock;
use Scaleplan\Data\Data;
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
     * @param null|DocBlock $docBlock - блок описания метода получения имени базы данных
     *
     * @return string
     *
     * @throws Exceptions\SettingNotFoundException
     */
    public static function getDbName(?DocBlock $docBlock) : string
    {
        if (!$docBlock) {
            return App::getSetting(ConfigConstants::DEFAULT_DB);
        }

        if (empty($docParam = $docBlock->getTagsByName('dbName'))) {
            return App::getSetting(ConfigConstants::DEFAULT_DB);
        }

        $docParam = end($docParam);
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
    public static function getPrefix(?DocBlock $docBlock) : string
    {
        if (!$docBlock) {
            return '';
        }

        if (empty($docParam = $docBlock->getTagsByName('prefix'))) {
            return '';
        }

        $docParam = end($docParam);

        return trim($docParam->getDescription());
    }

    /**
     * Выполнить метод или SQL-запрос хранящийся в свойстве
     *
     * @param string $propertyName
     * @param array $data
     * @param \object|null $object
     *
     * @return DbResult
     *
     * @throws Exceptions\CacheException
     * @throws Exceptions\DatabaseException
     * @throws Exceptions\SettingNotFoundException
     * @throws RepositoryException
     * @throws ServiceMethodNotFoundException
     * @throws \ReflectionException
     * @throws \Scaleplan\Data\Exceptions\DataException
     * @throws \Scaleplan\Db\Exceptions\ConnectionStringException
     * @throws \Scaleplan\Db\Exceptions\PDOConnectionException
     * @throws \Scaleplan\Db\Exceptions\QueryCountNotMatchParamsException
     */
    private static function invoke(string $propertyName, array $data, \object $object = null) : DbResult
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
        $prefix = static::getPrefix($docBlock);

        $dataStory = Data::create($sql, $data);
        $dataStory->setCacheConnect(App::getCache());
        $dataStory->setDbConnect(App::getDB($dbName));
        $dataStory->setPrefix($prefix);

        return $dataStory->getValue();
    }

    /**
     * Magic-метод для выполнения приватных статических методов и SQL-запросов хранящихся в статических свойствах
     * классов
     *
     * @param string $propertyName - имя свойства
     * @param array $data - данных для исполнения запроса
     *
     * @return DbResult
     *
     * @throws Exceptions\CacheException
     * @throws Exceptions\DatabaseException
     * @throws Exceptions\SettingNotFoundException
     * @throws ServiceMethodNotFoundException
     * @throws \ReflectionException
     * @throws \Scaleplan\Data\Exceptions\DataException
     * @throws \Scaleplan\Db\Exceptions\ConnectionStringException
     * @throws \Scaleplan\Db\Exceptions\PDOConnectionException
     * @throws \Scaleplan\Db\Exceptions\QueryCountNotMatchParamsException
     */
    public static function __callStatic(string $propertyName, array $data) : DbResult
    {
        return static::invoke($propertyName, $data);
    }

    /**
     * Magic-метод для выполнения приватных нестатических методов и SQL-запросов хранящихся в нестатических свойствах
     * классов
     *
     * @param string $propertyName - имя свойства
     * @param array $data - данных для исполнения запроса
     *
     * @return DbResult
     *
     * @throws Exceptions\CacheException
     * @throws Exceptions\DatabaseException
     * @throws Exceptions\SettingNotFoundException
     * @throws ServiceMethodNotFoundException
     * @throws \ReflectionException
     * @throws \Scaleplan\Data\Exceptions\DataException
     * @throws \Scaleplan\Db\Exceptions\ConnectionStringException
     * @throws \Scaleplan\Db\Exceptions\PDOConnectionException
     * @throws \Scaleplan\Db\Exceptions\QueryCountNotMatchParamsException
     */
    public function __call(string $propertyName, array $data) : DbResult
    {
        return static::invoke($propertyName, $data, $this);
    }
}