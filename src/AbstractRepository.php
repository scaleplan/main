<?php

namespace Scaleplan\Main;

use phpDocumentor\Reflection\DocBlock;
use Scaleplan\Data\Data;
use Scaleplan\DTO\DTO;
use Scaleplan\Helpers\ArrayHelper;
use function Scaleplan\Helpers\get_required_env;
use Scaleplan\Helpers\Helper;
use Scaleplan\Main\Constants\ConfigConstants;
use Scaleplan\Main\Exceptions\RepositoryException;
use Scaleplan\Main\Exceptions\RepositoryMethodArgsInvalidException;
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
    public const TABLE = null;
    public const DEFAULT_SORT_DIRECTION = 'DESC';

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
     *
     * @return \ReflectionClassConstant|\ReflectionProperty
     *
     * @throws ServiceMethodNotFoundException
     * @throws \ReflectionException
     */
    private static function getReflector(string $propertyName) : \Reflector
    {
        if (!property_exists(static::class, $propertyName)) {
            return new \ReflectionProperty(static::class, $propertyName);
        }

        if (!defined("static::$propertyName")) {
            return new \ReflectionClassConstant(static::class, $propertyName);
        }

        throw new ServiceMethodNotFoundException();
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
     * @throws \Scaleplan\Data\Exceptions\ValidationException
     * @throws \Scaleplan\Db\Exceptions\ConnectionStringException
     * @throws \Scaleplan\Db\Exceptions\PDOConnectionException
     * @throws \Scaleplan\Db\Exceptions\QueryCountNotMatchParamsException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Result\Exceptions\ResultException
     */
    public static function invoke(string $propertyName, array $data, object $object = null) : DbResult
    {
        if (!$data && empty($data[0])) {
            throw new RepositoryMethodArgsInvalidException();
        }

        if (\is_array($data[0]) && !ArrayHelper::isAccos($data[0])) {
            throw new RepositoryMethodArgsInvalidException();
        }

        $data = $data[0];
        if($data instanceof DTO) {
            $data = $data->toSnakeArray();
        }

        $reflector = static::getReflector($propertyName);
        $sql = $reflector->getValue($object);
        $docBlock = new DocBlock($reflector->getDocComment());

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
     * @throws \Scaleplan\Data\Exceptions\ValidationException
     * @throws \Scaleplan\Db\Exceptions\ConnectionStringException
     * @throws \Scaleplan\Db\Exceptions\PDOConnectionException
     * @throws \Scaleplan\Db\Exceptions\QueryCountNotMatchParamsException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
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
     * @throws \Scaleplan\Data\Exceptions\ValidationException
     * @throws \Scaleplan\Db\Exceptions\ConnectionStringException
     * @throws \Scaleplan\Db\Exceptions\PDOConnectionException
     * @throws \Scaleplan\Db\Exceptions\QueryCountNotMatchParamsException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Result\Exceptions\ResultException
     */
    public function __call(string $propertyName, array $data) : DbResult
    {
        return static::invoke($propertyName, $data, $this);
    }
}
