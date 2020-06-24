<?php
declare(strict_types=1);

namespace Scaleplan\Main;

use Scaleplan\Db\Db;
use Scaleplan\Db\Interfaces\DbInterface;
use Scaleplan\Db\Interfaces\TableTagsInterface;
use Scaleplan\Helpers\Helper;
use Scaleplan\Http\Interfaces\CurrentRequestInterface;
use Scaleplan\Main\Constants\ConfigConstants;
use Scaleplan\Main\Exceptions\AppException;
use Scaleplan\Main\Exceptions\CacheException;
use Scaleplan\Main\Exceptions\DatabaseException;
use Scaleplan\Main\Exceptions\InvalidHostException;
use Scaleplan\Main\Exceptions\ViewNotFoundException;
use Scaleplan\Main\Interfaces\UserInterface;
use Scaleplan\NginxGeo\NginxGeoInterface;
use Symfony\Component\Yaml\Yaml;
use function Scaleplan\DependencyInjection\get_required_container;
use function Scaleplan\Helpers\get_env;
use function Scaleplan\Helpers\get_required_env;
use function Scaleplan\Translator\translate;

/**
 * Class App
 *
 * @package Scaleplan\Main
 */
class App
{
    public const CACHE_PERSISTENT_ID         = 125437;
    public const SESSION_CURRENCY_CODE_LABEL = 'currencyCode';

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
    protected static $locale = 'en_US';

    /**
     * @var string
     */
    protected static $host;

    /**
     * @var string
     */
    protected static $currencyCode;

    /**
     * @var bool
     */
    protected static $isStartTransaction = true;

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
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
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
            throw new CacheException(translate('main.cache-type-not-found'));
        }

        if ($cacheType === 'memcached' && !extension_loaded('memcached')) {
            throw new CacheException(translate('main.memcached-not-load'));
        }

        if ($cacheType === 'redis' && !extension_loaded('redis')) {
            throw new CacheException(translate('main.redis-not-load'));
        }

        if ($cacheType === 'memcached' && !$port) {
            throw new CacheException(translate('main.memcached-port-not-found'));
        }

        if (empty($connectType) || !\in_array($connectType, ['connect', 'pconnect'], true)) {
            throw new CacheException(translate('main.cache-type-not-found'));
        }

        if (empty($hostOrSocket)) {
            throw new CacheException(translate('main.host-not-found'));
        }

        if ($cacheType === 'memcached') {
            $memcached = $connectType === 'pconnect' ? new \Memcached(static::CACHE_PERSISTENT_ID) : new \Memcached();
            if (!$memcached->addServer($hostOrSocket, $port)) {
                throw new CacheException(translate('main.memcached-connect-failed'));
            }

            return static::$caches[$hostOrSocket] = $memcached;
        }

        $redis = new \Redis();
        if (!$redis->$connectType($hostOrSocket, $port)) {
            throw new CacheException(translate('main.redis-connect-failed'));
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
        if (isset($_SERVER['HTTP_HOST'])) {
            if (!Helper::hostCheck($_SERVER['HTTP_HOST'])) {
                throw new InvalidHostException(translate('main.wrong-http-host'));
            }

            static::$host = $_SERVER['HTTP_HOST'];
        }

        /** @var NginxGeoInterface $geo */
        $geo = get_required_container(NginxGeoInterface::class, [$_REQUEST]);

        $timeZoneName = $geo->getCountryCode() && $geo->getRegionCode()
            ? geoip_time_zone_by_country_and_region($geo->getCountryCode(), $geo->getRegionCode())
            : date_default_timezone_get();
        static::$timeZone = new \DateTimeZone($timeZoneName);

        static::$locale = \Locale::acceptFromHttp(($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''))
            ?: get_required_env('DEFAULT_LANG');
        setlocale(LC_ALL, static::$locale);

        date_default_timezone_set(static::$timeZone->getName());

        static::$currencyCode = $_SESSION[static::SESSION_CURRENCY_CODE_LABEL] ?? \NumberFormatter::create(
                static::getLocale(),
                \NumberFormatter::CURRENCY
            )->getTextAttribute(\NumberFormatter::CURRENCY_CODE);

        if (null !== get_env('IS_START_TRANSACTION')) {
            static::$isStartTransaction = (bool)get_env('IS_START_TRANSACTION');
        }
    }

    /**
     * @return bool
     */
    public static function isStartTransaction() : bool
    {
        return self::$isStartTransaction;
    }

    /**
     * @param bool $isStartTransaction
     */
    public static function setIsStartTransaction(bool $isStartTransaction) : void
    {
        self::$isStartTransaction = $isStartTransaction;
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
     * @param string $name
     *
     * @return array
     *
     * @throws AppException
     * @throws DatabaseException
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     */
    protected static function getDatabaseConnectionInfo(string $name) : array
    {
        if (empty(static::$$name)) {
            if (empty($_SESSION['databases'][$name])) {
                $filePath = get_required_env(ConfigConstants::BUNDLE_PATH)
                    . get_required_env(ConfigConstants::DB_CONFIGS_PATH)
                    . "/$name.yml";

                if (!file_exists($filePath)) {
                    throw new AppException(translate('main.db-connect-file-not-found', ['name' => $name,]));
                }

                $_SESSION['databases'][$name] = Yaml::parse(file_get_contents($filePath));
            }

            $db = $_SESSION['databases'][$name];
        } else {
            $db = static::$$name;
        }

        if (empty($db['DNS'])) {
            throw new DatabaseException(translate('main.db-connection-string-not-found', ['name' => $name,]));
        }

        if (empty($db['USER'])) {
            throw new DatabaseException(translate('main.db-user-not-found', ['name' => $name,]));
        }

        if (empty($db['PASSWORD'])) {
            throw new DatabaseException(translate('main.db-password-not-found', ['name' => $name,]));
        }

        return $db;
    }

    /**
     * @param DbInterface $dbConnect
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     */
    protected static function initDb(DbInterface $dbConnect) : void
    {
        /** @var UserInterface $user */
        $user = get_required_container(UserInterface::class);

        $dbConnect->setUserId($user->getId());
        $dbConnect->setLocale(static::getLocale());
        $dbConnect->setTimeZone(static::getTimeZone());
        $dbConnect->setIsTransactional(static::$isStartTransaction);

        /** @var TableTagsInterface $tableTags */
        $tableTags = get_required_container(TableTagsInterface::class, [$dbConnect]);
        $tableTags->initTablesList(!empty($db['SCHEMAS']) ? $db['SCHEMAS'] : null);
    }

    /**
     * Подкючиться к базе данных (если подключения еще нет) и вернуть объект подключения
     *
     * @param string|null $name - имя базы данных
     *
     * @param bool $isNewConnection
     *
     * @return DbInterface
     *
     * @throws AppException
     * @throws DatabaseException
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     */
    public static function getDB(string $name = null, bool $isNewConnection = false) : DbInterface
    {
        if (!$name) {
            $name = get_required_env(ConfigConstants::DEFAULT_DB);
        }

        if (\in_array($name, static::$denyDatabases, true)) {
            throw new DatabaseException(translate('main.db-connect-deny', ['name' => $name,]));
        }

        if (!empty(static::$databases[$name]) && !$isNewConnection) {
            return static::$databases[$name];
        }

        $db = static::getDatabaseConnectionInfo($name);

        /** @var DbInterface $dbConnect */
        $dbConnect = get_required_container(
            DbInterface::class,
            [
                $db['DNS'],
                $db['USER'],
                $db['PASSWORD'],
                !empty($db['OPTIONS']) ? $db['OPTIONS'] : [],
            ],
            !$isNewConnection
        );
        static::initDb($dbConnect);
        if (!$isNewConnection) {
            static::$databases[$name] = $dbConnect;
        }

        return $dbConnect;
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
        static $subdomain;
        if (null === $subdomain) {
            $subdomain = Helper::getSubdomain();
            $disableSubdomains = array_map('trim', explode(',', (string)getenv('DISABLED_SUBDOMAINS')));
            $subdomainArray = explode('.', $subdomain);
            foreach ($subdomainArray as $index => $part) {
                if (in_array($part, $disableSubdomains, true)) {
                    unset($subdomainArray[$index]);
                }
            }

            $subdomain = implode('.', $subdomainArray);
        }

        return $subdomain;
    }

    /**
     * @return string
     */
    public static function getCurrencyCode() : string
    {
        return self::$currencyCode;
    }

    /**
     * @param string $currencyCode
     */
    public static function setCurrencyCode(string $currencyCode) : void
    {
        $_SESSION[static::SESSION_CURRENCY_CODE_LABEL] = $currencyCode;
        self::$currencyCode = $currencyCode;
    }
}
