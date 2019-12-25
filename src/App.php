<?php

namespace Scaleplan\Main;

use Scaleplan\Db\Db;
use Scaleplan\Db\Interfaces\DbInterface;
use Scaleplan\Db\Interfaces\TableTagsInterface;
use Scaleplan\Helpers\Helper;
use Scaleplan\Http\Interfaces\CurrentRequestInterface;
use Scaleplan\Main\Constants\ConfigConstants;
use Scaleplan\Main\Exceptions\CacheException;
use Scaleplan\Main\Exceptions\DatabaseException;
use Scaleplan\Main\Exceptions\InvalidHostException;
use Scaleplan\Main\Exceptions\ViewNotFoundException;
use Scaleplan\Main\Interfaces\UserInterface;
use Scaleplan\NginxGeo\NginxGeoInterface;
use Symfony\Component\Yaml\Yaml;
use function Scaleplan\DependencyInjection\get_required_container;
use function Scaleplan\Helpers\get_required_env;

/**
 * Class App
 *
 * @package Scaleplan\Main
 */
class App
{
    public const CACHE_PERSISTENT_ID = 125437;

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
    protected static $locale;

    /**
     * @var string
     */
    protected static $host;

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
        $connectType = $connectType ?? ((bool)get_required_env('CACHE_PCONNECT') ? 'pconnect' : 'connect');
        $hostOrSocket = $hostOrSocket ?? get_required_env('CACHE_HOST_OR_SOCKET');
        $port = $port ?? (int)get_required_env('CACHE_PORT');

        if (!empty(static::$caches[$hostOrSocket])) {
            return static::$caches[$hostOrSocket];
        }

        if (empty($cacheType) || !\in_array($cacheType, ['redis', 'memcached'], true)) {
            throw new CacheException('Для подключения к кэшу не хватает параметра cacheType, либо он задан неверно');
        }

        if ($cacheType === 'memcached' && !extension_loaded('memcached')) {
            throw new CacheException('Расширение memcached не загруженно');
        }

        if ($cacheType === 'redis' && !extension_loaded('redis')) {
            throw new CacheException('Расширение redis не загруженно');
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
            $memcached = $connectType === 'pconnect' ? new \Memcached(static::CACHE_PERSISTENT_ID) : new \Memcached();
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
        if (!Helper::hostCheck($_SERVER['HTTP_HOST'])) {
            throw new InvalidHostException('Передан неверный заголовок HTTP-HOST');
        }

        static::$host = $_SERVER['HTTP_HOST'];

        /** @var NginxGeoInterface $geo */
        $geo = get_required_container(NginxGeoInterface::class, [$_REQUEST]);

        $timeZoneName = $geo->getCountryCode() && $geo->getRegionCode()
            ? geoip_time_zone_by_country_and_region($geo->getCountryCode(), $geo->getRegionCode())
            : date_default_timezone_get();
        static::$timeZone = new \DateTimeZone($timeZoneName);

        static::$locale = get_required_env('DEFAULT_LANG');
        if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            static::$locale = locale_accept_from_http($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        }
        setlocale(LC_ALL, static::$locale);

        date_default_timezone_set(static::$timeZone->getName());
    }

    /**
     * @return string
     */
    public static function getLang() : string
    {
        static $lang;
        if (!$lang) {
            $lang = explode('_', static::$locale)[0];
        }

        return $lang;
    }

    /**
     * @return string
     */
    public static function getHost() : string
    {
        return self::$host;
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
    public static function getLocale() : string
    {
        return static::$locale;
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
            throw new DatabaseException("В данных о подключении к БД '$name' не хватает строки подключения");
        }

        if (empty($db['USER'])) {
            throw new DatabaseException("В данных о подключении к БД '$name' не хватает имени пользователя БД");
        }

        if (empty($db['PASSWORD'])) {
            throw new DatabaseException("В данных о подключении к БД '$name' не хватает пароля пользователя БД");
        }

        /** @var DbInterface $dbConnect */
        $dbConnect = get_required_container(
            DbInterface::class,
            [
                $db['DNS'],
                $db['USER'],
                $db['PASSWORD'],
                !empty($db['OPTIONS']) ? $db['OPTIONS'] : [],
            ]
        );
        /** @var TableTagsInterface $tableTags */
        $tableTags = get_required_container(TableTagsInterface::class, [$dbConnect]);
        $tableTags->initTablesList(!empty($db['SCHEMAS']) ? $db['SCHEMAS'] : null);

        /** @var UserInterface $user */
        $user = get_required_container(UserInterface::class);
        $dbConnect->setUserId($user->getId());
        $dbConnect->setLocale(static::getLocale());
        $dbConnect->setTimeZone(static::getTimeZone());

        return static::$databases[$name] = $dbConnect;
    }

    /**
     * @param string|null $role
     * @param string|null $filePath
     *
     * @return string
     *
     * @throws ViewNotFoundException
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     */
    public static function getViewPath(string $role = null, string $filePath = null) : string
    {
        /** @var CurrentRequestInterface $request */
        $request = get_required_container(CurrentRequestInterface::class);
        $filePath = $filePath ?: explode('?', $request->getURL())[0];
        $pathsToCheck = [];

        if ($role) {
            $pathArray = explode('/', $filePath);
            $tplName = array_pop($pathArray);
            $fileDirectory = implode('/', $pathArray);
            $roleFilePath = "$fileDirectory/$role-$tplName";

            $pathsToCheck[] = getenv('VIEWS_CONFIG')
                ? (include(get_required_env(ConfigConstants::BUNDLE_PATH)
                    . getenv('VIEWS_CONFIG')))[$roleFilePath] ?? null
                : null;

            $pathsToCheck[] = get_required_env(ConfigConstants::PRIVATE_TEMPLATES_PATH)
                . '/' . static::getLocale()
                . $roleFilePath
                . '.html';

            $pathsToCheck[] = get_required_env(ConfigConstants::PUBLIC_TEMPLATES_PATH)
                . '/' . static::getLocale()
                . $roleFilePath
                . '.html';
        }

        $pathsToCheck[] = getenv('VIEWS_CONFIG')
            ? (include(get_required_env(ConfigConstants::BUNDLE_PATH) . getenv('VIEWS_CONFIG')))[$filePath] ?? null
            : null;

        $pathsToCheck[] = get_required_env(ConfigConstants::PRIVATE_TEMPLATES_PATH)
            . '/' . static::getLocale()
            . $filePath
            . '.html';

        $pathsToCheck[] = get_required_env(ConfigConstants::PUBLIC_TEMPLATES_PATH)
            . '/' . static::getLocale()
            . $filePath
            . '.html';

        foreach ($pathsToCheck as $path) {
            $pathsToCheck = get_required_env(ConfigConstants::BUNDLE_PATH)
                . get_required_env(ConfigConstants::VIEWS_PATH)
                . $path;
            if (!$path || !file_exists($pathsToCheck)) {
                continue;
            }

            return $path;
        }

        throw new ViewNotFoundException(null, $filePath);
    }

    /**
     * @return string
     *
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Helpers\Exceptions\HelperException
     */
    public static function getSubdomain() : string
    {
        $subdomain = Helper::getSubdomain();
        $disableSubdomains = array_map('trim', explode(',', (string)getenv('DISABLED_SUBDOMAINS')));
        $subdomainArray = explode('.', $subdomain);
        foreach ($subdomainArray as $index => $part) {
            if (in_array($part, $disableSubdomains, true)) {
                unset($subdomainArray[$index]);
            }
        }

        return implode('.', $subdomainArray);
    }
}
