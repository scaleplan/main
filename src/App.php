<?php

namespace Scaleplan\Main;

use Scaleplan\Db\Db;
use Scaleplan\Db\Interfaces\DbInterface;
use Scaleplan\Db\Interfaces\TableTagsInterface;
use function Scaleplan\DependencyInjection\get_container;
use function Scaleplan\Helpers\get_required_env;
use Scaleplan\Helpers\Helper;
use Scaleplan\Http\Interfaces\CurrentRequestInterface;
use Scaleplan\Main\Constants\ConfigConstants;
use Scaleplan\Main\Exceptions\CacheException;
use Scaleplan\Main\Exceptions\DatabaseException;
use Scaleplan\Main\Exceptions\InvalidHostException;
use Scaleplan\NginxGeo\NginxGeoInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class App
 *
 * @package Scaleplan\Main
 */
class App
{
    /**
     * Базы данных, подключение к которым запрещено
     *
     * @var array
     */
    protected static $denyDatabases = [];

    /**
     * Подключения к базам данных
     *
     * @var array
     */
    protected static $databases = [];

    /**
     * Подключения к кэшам
     *
     * @var array
     */
    protected static $caches = [];

    /**
     * @var \DateTimeZone|null
     */
    protected static $timeZone;

    /**
     * @var string
     */
    protected static $lang;

    /**
     * @var array - Данные для подключения к базам данных
     */
    private static $main = [
        'DNS'      => 'pgsql:host=/var/run/postgresql;port=5432;dbname=main',
        'USER'     => 'user',
        'PASSWORD' => 'password',
        'SCHEMAS'  => ['public', 'users'],
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
     * @throws CacheException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     */
    public static function getCache(
        string $cacheType = null,
        string $connectType = null,
        string $hostOrSocket = null,
        int $port = null
    )
    {
        $cacheType = $cacheType ?? get_required_env('CACHE_TYPE');
        $connectType = $connectType ?? get_required_env('CACHE_CONNECT_TYPE');
        $hostOrSocket = $hostOrSocket ?? get_required_env('CACHE_HOST_OR_SOCKET');
        $port = $port ?? (int)get_required_env('CACHE_PORT');

        if (!empty(static::$caches[$hostOrSocket])) {
            return static::$caches[$hostOrSocket];
        }

        if (empty($cacheType) || !\in_array($cacheType, ['redis', 'memcached'], true)) {
            throw new CacheException('Для подключения к кэшу не хватает параметра cacheType, либо он задан неверно');
        }

        if ($cacheType === 'memcached' && !$port) {
            throw new CacheException(
                'Для Memcached недоступно подключение к Unix-сокету. Необходимо задать порт подключения'
            );
        }

        if (empty($connectType) || !\in_array($connectType, ['connect', 'pconnect'], true)) {
            throw new CacheException('Для подключения к кэшу не хватает параметра cacheType, либо он задан неверно');
        }

        if (empty($hostOrSocket)) {
            throw new CacheException('Для подключения к кэшу не хватает параметра hostOrSocket, либо он задан неверно');
        }

        if ($cacheType === 'memcached') {
            $memcached = new \Memcached();
            if (!$memcached->addServer($hostOrSocket, $port)) {
                throw new CacheException('Не удалось подключиться к Memcached');
            }

            return static::$caches[$hostOrSocket] = $memcached;
        }

        $redis = new \Redis();
        if (!$redis->$connectType($hostOrSocket, $port)) {
            throw new CacheException('Не удалось подключиться к Redis');
        }

        return static::$caches[$hostOrSocket] = $redis;
    }

    /**
     * Инициализация приложения
     *
     * @throws InvalidHostException
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     */
    public static function init() : void
    {
        $host = $_SERVER['HTTP_HOST'];
        if (!Helper::hostCheck($host)) {
            throw new InvalidHostException('Передан неверный заголовок HTTP-HOST');
        }

        /** @var NginxGeoInterface $geo */
        $geo = get_container(NginxGeoInterface::class, [$_REQUEST]);

        $timeZoneName = $geo->getCountryCode() && $geo->getRegionCode()
            ? geoip_time_zone_by_country_and_region($geo->getCountryCode(), $geo->getRegionCode())
            : date_default_timezone_get();
        static::$timeZone = new \DateTimeZone($timeZoneName);
        static::$lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) ?: get_required_env('DEFAULT_LANG');
    }

    /**
     * @return \DateTimeZone|null
     */
    public static function getTimeZone() : ?\DateTimeZone
    {
        return static::$timeZone;
    }

    /**
     * @return string
     */
    public static function getLang() : string
    {
        return static::$lang;
    }

    /**
     * Возвращает все автивные на данный момент подключения в базам данных
     *
     * @return Db[]
     */
    public static function getDatabases() : array
    {
        return static::$databases;
    }

    /**
     * Подкючиться к базе данных (если подключения еще нет) и вернуть объект подключения
     *
     * @param string $name - имя базы данных
     *
     * @return DbInterface
     *
     * @throws DatabaseException
     * @throws \ReflectionException
     * @throws \Scaleplan\Db\Exceptions\ConnectionStringException
     * @throws \Scaleplan\Db\Exceptions\PDOConnectionException
     * @throws \Scaleplan\Db\Exceptions\QueryCountNotMatchParamsException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     */
    public static function getDB(string $name = null) : DbInterface
    {
        if (!$name) {
            $name = get_required_env(ConfigConstants::DEFAULT_DB);
        }

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
                        get_required_env(ConfigConstants::BUNDLE_PATH)
                        . get_required_env(ConfigConstants::DB_CONFIGS_PATH)
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

        /** @var DbInterface $dbConnect */
        $dbConnect = get_container(
            DbInterface::class,
            [
                $db['DNS'],
                $db['USER'],
                $db['PASSWORD'],
                !empty($db['OPTIONS']) ? $db['OPTIONS'] : [],
            ]
        );
        /** @var TableTagsInterface $tableTags */
        $tableTags = get_container(TableTagsInterface::class, [$dbConnect]);
        $tableTags->initTablesList(!empty($db['SCHEMAS']) ? $db['SCHEMAS'] : []);

        return static::$databases[$name] = $dbConnect;
    }

    /**
     * @return string|null
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     */
    public static function getViewPath() : ?string
    {
        /** @var CurrentRequestInterface $request */
        $request = get_container(CurrentRequestInterface::class);
        $url = explode('?', $request->getURL())[0];
        $path = getenv('VIEWS_CONFIG')
            ? (include (get_required_env(ConfigConstants::BUNDLE_PATH) . getenv('VIEWS_CONFIG')))[$url] ?? null
            : null;
        if (!$path) {
            $path = get_required_env(ConfigConstants::BUNDLE_PATH)
                . get_required_env(ConfigConstants::VIEWS_PATH)
                . '/' . static::$lang
                . $url
                . '.html';
            if (!file_exists($path)) {
                return null;
            }
        }

        return $path;
    }
}
