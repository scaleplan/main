<?php

namespace Scaleplan\Main;

use Scaleplan\Data\Data;
use Scaleplan\Http\CurrentRequest;
use Scaleplan\Http\Interfaces\CurrentRequestInterface;
use Scaleplan\Main\Interfaces\CacheInterface;

return [
    CurrentRequestInterface::class => CurrentRequest::class,
    CacheInterface::class          => Data::class . '::getInstance',
];
