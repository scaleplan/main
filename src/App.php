<?php

namespace Scaleplan\Main;

use Scaleplan\CachePDO\CachePDO;
use Scaleplan\Data\Data;
use Scaleplan\Helpers\Helper;
use Scaleplan\Main\Exceptions\CacheException;
use Scaleplan\Main\Exceptions\DatabaseException;
use Scaleplan\Main\Exceptions\InvalidHostException;
use Scaleplan\Main\Exceptions\SettingNotFoundException;
use Scaleplan\Main\Interfaces\UserInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class App
 *
 * @package Scaleplan\Main
 */
class App
{
    /**
     * @param string $settingName
     *
     * @return array|false|string
     * @throws SettingNotFoundException
     */
    public static function getSetting(string $settingName)
    {
        if (getenv($settingName) === false) {
            throw new SettingNotFoundException("Setting $settingName not found");
        }

        return getenv($settingName);
    }

    /**
     * Базы данных, подключение к которым запрещено
     *
     * @var array
     */
    private static $denyDatabases = [];

    /**
     * Подключения к базам данных
     *
     * @var array
     */
    private static $databases = [];

    /**
     * Подключения к кэшам
     *
     * @var array
     */
    private static $caches = [];

    /* Данные для подключения к базам данных */
    private static $main = [
        'DNS' => 'pgsql:host=/var/run/postgresql;port=5432;dbname=main',
        'USER' => 'avtomonov',
        'PASSWORD' => 'password',
        'SCHEMAS' => ['public', 'users']
    ];

    /**
     * Данные для подключение к кэшу
     */

    /**
     * Подключиться к кэшу
     *
     * @param string $cacheType - тип кэша (Redis или Memcached)
     * @param string $connectType - тип подключений (connect или pconnect)
     * @param string $hostOrSocket - хост или сокет подключения
     * @param int $port - порт поключения
     *
     * @return \Memcached|mixed|\Redis
     *
     * @throws CacheException
     */
    public static function getCache(
        string $cacheType = null,
        string $connectType = null,
        string $hostOrSocket = null,
        int $port = null
    )
    {
        $cacheType = $cacheType ?? getenv('CACHE_TYPE');
        $connectType = $connectType ?? getenv('CACHE_CONNECT_TYPE');
        $hostOrSocket = $hostOrSocket ?? getenv('CACHE_HOST_OR_SOCKET');
        $port = $port ?? getenv('CACHE_PORT');

        if (!empty(static::$caches[$hostOrSocket])) {
            return static::$caches[$hostOrSocket];
        }

        if (empty($cacheType) || !\in_array($cacheType, ['redis', 'memcached'], true)) {
            throw new CacheException('Для подключения к кэшу не хватает параметра cacheType, либо он задан неверно');
        }

        if ($cacheType === 'memcached' && !$port) {
            throw new CacheException('Для Memcached недоступно подключение к Unix-сокету. Необходимо задать порт подключения');
        }

        if (empty($connectType) || !\in_array($connectType, ['connect', 'pconnect'], true)) {
            throw new CacheException('Для подключения к кэшу не хватает параметра cacheType, либо он задан неверно');
        }

        if (empty($hostOrSocket)) {
            throw new CacheException('Для подключения к кэшу не хватает параметра hostOrSocket, либо он задан неверно');
        }

        if ($cacheType === 'memcached') {
            $memcached = new \Memcached;
            if (!$memcached->addServer($hostOrSocket, $port)) {
                throw new CacheException('Не удалось подключиться к Memcached');
            }

            return static::$caches[$hostOrSocket] = $memcached;
        }

        $redis = new \Redis;
        if (!$redis->$connectType($hostOrSocket, $port)) {
            throw new CacheException('Не удалось подключиться к Redis');
        }

        return static::$caches[$hostOrSocket] = $redis;
    }

    /**
     * @var UserInterface
     */
    protected static $user;

    /**
     * @return UserInterface
     */
    public static function getCurrentUser() : UserInterface
    {
        return static::$user;
    }

    /**
     * Инициализация приложения
     *
     * @param UserInterface $user
     *
     * @throws CacheException
     * @throws DatabaseException
     * @throws InvalidHostException
     * @throws SettingNotFoundException
     * @throws \Scaleplan\CachePDO\Exceptions\ConnectionStringException
     * @throws \Scaleplan\CachePDO\Exceptions\PDOConnectionException
     */
    public static function init(UserInterface $user):void
    {
        $host = $_SERVER['HTTP_HOST'];
        if (!Helper::hostCheck($host)) {
            throw new InvalidHostException('Передан неверный заголовок HTTP-HOST');
        }

        static::$user = UserInterface::getCurrentUser();

        Data::setSettings(
            [
                'dbConnect' => static::getDB(static::getSetting('DEFAULT_DB')),
                'cacheConnect' => static::getCache()
            ]
        );
    }

    /**
     * Возвращает все автивные на данный момент подключения в базам данных
     *
     * @return CachePDO[]
     */
    public static function getDatabases(): array
    {
        return static::$databases;
    }

    /**
     * Подкючиться к базе данных (если подключения еще нет) и вернуть объект подключения
     *
     * @param $name - имя базы данных
     *
     * @return CachePDO
     *
     * @throws DatabaseException
     * @throws SettingNotFoundException
     * @throws \Scaleplan\CachePDO\Exceptions\ConnectionStringException
     * @throws \Scaleplan\CachePDO\Exceptions\PDOConnectionException
     */
    public static function getDB($name): CachePDO
    {
        if (\in_array($name, static::$denyDatabases, true)) {
            throw new DatabaseException("Подключение к базе данных $name не разрешено");
        }

        if (!empty(static::$databases[$name])) {
            return static::$databases[$name];
        }

        $_SESSION['databases'] = $_SESSION['databases'] ?? [];

        if (empty(static::$$name)) {
            if (empty($_SESSION['databases'][$name])) {
                $_SESSION['databases'][$name]
                    = Yaml::parse(
                        file_get_contents(
                            $_SERVER['DOCUMENT_ROOT']
                            . static::getSetting('DB_CONFIGS_PATH')
                            . "/$name.yml"
                        )
                );
            }

            $db = $_SESSION['databases'][$name];
        } else {
            $db = static::$$name;
        }

        if (empty($db['DNS'])) {
            throw new DatabaseException('В данных о подключении к БД не хватает строки подключения');
        }

        if (empty($db['USER'])) {
            throw new DatabaseException('В данных о подключении к БД не хватает имени пользователя БД');
        }

        if (empty($db['PASSWORD'])) {
            throw new DatabaseException('В данных о подключении к БД не хватает пароля пользователя БД');
        }

        $dbConnect = new CachePDO(
            $db['DNS'],
            $db['USER'],
            $db['PASSWORD'],
            !empty($db['SCHEMAS']) ? $db['SCHEMAS'] : [],
            !empty($db['OPTIONS']) ? $db['OPTIONS'] : []
        );

        return static::$databases[$name] = $dbConnect;
    }
}