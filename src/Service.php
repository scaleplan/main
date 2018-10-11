<?php

namespace Scaleplan\Main;

use phpDocumentor\Reflection\DocBlock;
use Scaleplan\Access\AccessServiceParent;
use Scaleplan\Access\AccessServiceResult;
use Scaleplan\Data\Data;
use Scaleplan\Helpers\Helper;
use Scaleplan\Main\Exceptions\ServiceMethodNotFoundException;

/**
 * Мать всех моделей
 *
 * Class Service
 *
 * @package Scaleplan\Main
 */
abstract class Service extends AccessServiceParent
{
    /**
     * Идентификатор модели в системе Acless
     */
    public const MODEL_TYPE_ID = 0;

    /**
     * Конструктор
     *
     * @throws ServiceMethodNotFoundException
     */
    public function __construct()
    {
        if (!property_exists($this, 'getInfo') && !method_exists($this, 'getInfo')) {
            throw new ServiceMethodNotFoundException('Отсутствует способ получения основной информации о модели ' . static::class);
        }

        if (!property_exists($this, 'put') && !method_exists($this, 'put')) {
            throw new ServiceMethodNotFoundException('Отсутствует способ сохранения новой модели');
        }

        if (!property_exists($this, 'update') && !method_exists($this, 'update')) {
            throw new ServiceMethodNotFoundException('Отсутствует способ изменения модели');
        }

        if (!property_exists($this, 'delete') && !method_exists($this, 'delete')) {
            throw new ServiceMethodNotFoundException('Отсутствует способ удаления модели');
        }

        if (!property_exists($this, 'getList') && !method_exists($this, 'getList')) {
            throw new ServiceMethodNotFoundException('Отсутствует способ получения списка моделей');
        }
    }

    /**
     * Подмена метода getFullInfo методом getInfo
     *
     * @param string $name - имя метода
     * @param $subject - объект или класс проверки
     *
     * @return string
     */
    private static function fullInfoMissRepair(string &$name, $subject) : string
    {
        if ($name === 'getFullInfo' && !property_exists($subject, $name) && !method_exists($subject, $name)) {
            $name = 'getInfo';
        }

        return $name;
    }

    /**
     * Вернуть имя базы данных в зависимости от субдомена
     *
     * @param null|DocBlock $docBlock - блок описания метода получения имени базы данных
     *
     * @return string
     *
     * @throws Exceptions\SettingNotFoundException
     */
    protected static function getDbName(?DocBlock $docBlock) : string
    {
        if (!$docBlock) {
            return App::getSetting('DEFAULT_DB');
        }

        if (empty($docParam = $docBlock->getTagsByName('dbName'))) {
            return App::getSetting('DEFAULT_DB');
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
    protected static function getPrefix(?DocBlock $docBlock) : string
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
     * @param AccessServiceResult $aResult - объект выполнения
     * @param Service|null $object - объект модели
     *
     * @return AccessServiceResult
     * @throws Exceptions\CacheException
     * @throws Exceptions\DatabaseException
     * @throws Exceptions\SettingNotFoundException
     * @throws \ReflectionException
     * @throws \Scaleplan\CachePDO\Exceptions\ConnectionStringException
     * @throws \Scaleplan\CachePDO\Exceptions\PDOConnectionException
     */
    private static function invoke(AccessServiceResult $aResult, Service $object = null) : AccessServiceResult
    {
        if ($aResult->getMethod()) {
            $aResult->getMethod()->setAccessible(true);
            $result = $aResult->getIsPlainArgs() ? $aResult->getMethod()->invokeArgs($object, $aResult->getArgs()) : $aResult->getMethod()->invoke($object, $aResult->getArgs());
            $aResult->setRawResult($result);
            return $aResult;
        }

        if (!$aResult->getProperty()) {
            return $aResult;
        }

        $aResult->getProperty()->setAccessible(true);
        $sql = $aResult->getProperty()->getValue($object);
        $data = $aResult->getArgs();

        $docBlock = new DocBlock($aResult->getProperty());

        $dbName = static::getDbName($docBlock);
        $prefix = static::getPrefix($docBlock);

        $dataStory = Data::create($sql, $data);
        $dataStory->setCacheConnect(App::getCache());
        $dataStory->setDbConnect(App::getDB($dbName));

        $aResult->setRawResult($dataStory->getValue($prefix));

        return $aResult;
    }

    /**
     * Magic-метод для выполнения приватных статических методов и SQL-запросов хранящихся в статических свойствах
     * классов
     *
     * @param string $name - имя свойства
     * @param array $data - данных для исполнения запроса
     *
     * @return AccessServiceResult
     * @throws Exceptions\CacheException
     * @throws Exceptions\DatabaseException
     * @throws Exceptions\SettingNotFoundException
     * @throws \ReflectionException
     * @throws \Scaleplan\Access\Exceptions\AccessException
     * @throws \Scaleplan\CachePDO\Exceptions\ConnectionStringException
     * @throws \Scaleplan\CachePDO\Exceptions\PDOConnectionException
     */
    public static function __callStatic(string $name, array $data) : AccessServiceResult
    {
        if ($name === 'getFullInfo') {
            self::fullInfoMissRepair($name, self::class);
        }

        $aResult = parent::__callStatic($name, $data);

        return self::invoke($aResult);
    }

    /**
     * Magic-метод для выполнения приватных нестатических методов и SQL-запросов хранящихся в нестатических свойствах
     * классов
     *
     * @param string $name - имя свойства
     * @param array $data - данных для исполнения запроса
     *
     * @return AccessServiceResult
     *
     * @throws Exceptions\CacheException
     * @throws Exceptions\DatabaseException
     * @throws Exceptions\SettingNotFoundException
     * @throws \ReflectionException
     * @throws \Scaleplan\Access\Exceptions\AccessException
     * @throws \Scaleplan\CachePDO\Exceptions\ConnectionStringException
     * @throws \Scaleplan\CachePDO\Exceptions\PDOConnectionException
     */
    public function __call(string $name, array $data) : AccessServiceResult
    {
        if ($name === 'getFullInfo') {
            self::fullInfoMissRepair($name, $this);
        }

        $aResult = parent::__call($name, $data);

        return self::invoke($aResult, $this);
    }
}