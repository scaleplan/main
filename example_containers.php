<?php

namespace Scaleplan\Main;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Scaleplan\Data\Data;
use Scaleplan\Data\Interfaces\CacheInterface;
use function Scaleplan\Helpers\get_required_env;
use Scaleplan\Http\CurrentRequest;
use Scaleplan\Http\Interfaces\CurrentRequestInterface;
use Scaleplan\NginxGeo\NginxGeo;
use Scaleplan\NginxGeo\NginxGeoInterface;

return [
    CurrentRequestInterface::class => CurrentRequest::class,
    CacheInterface::class          => Data::class . '::getInstance',
    NginxGeoInterface::class       => NginxGeo::class,
    LoggerInterface::class         => static function (int $minLevel) : LoggerInterface {
        $logPath = get_required_env('LOG_PATH');

        $logger = new Logger(get_required_env('APP_NAME'));
        $logger::setTimezone(App::getTimeZone());
        $logger->pushHandler(new StreamHandler("$logPath/error.log", $minLevel));

        return $logger;
    },
];
