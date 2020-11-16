<?php

namespace Scaleplan\Main\Middlewares;

use phpDocumentor\Reflection\DocBlock;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Scaleplan\Data\Interfaces\CacheInterface;
use Scaleplan\Http\Constants\ContentTypes;
use Scaleplan\Http\CurrentRequest;
use Scaleplan\Main\App;
use Scaleplan\Main\Constants\ConfigConstants;
use Scaleplan\Main\Exceptions\ViewNotFoundException;
use Scaleplan\Main\Interfaces\ResponseCacheInterface;
use Scaleplan\Main\Interfaces\UserInterface;
use Scaleplan\Result\ArrayResult;
use function Scaleplan\DependencyInjection\get_required_container;
use function Scaleplan\DependencyInjection\get_static_container;
use function Scaleplan\Helpers\get_required_env;

/**
 * Class Cache
 *
 * @package Scaleplan\Main\Middlewares
 */
class ResponseCache implements MiddlewareInterface, ResponseCacheInterface
{
    public const DOCBLOCK_CACHE_DB_NAME_LABEL      = 'cacheDbName';
    public const DOCBLOCK_TAGS_LABEL               = 'tags';
    public const DOCBLOCK_NOCHECK_CACHE_USER_LABEL = 'noCheckCacheUser';

    /**
     * @var DocBlock
     */
    protected $docBlock;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var UserInterface
     */
    protected $user;

    /**
     * @var bool
     */
    protected $isHit = false;

    /**
     * Cache constructor.
     *
     * @param DocBlock $docBlock
     * @param CacheInterface|null $cache
     * @param UserInterface|null $user
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     */
    public function __construct(DocBlock $docBlock, CacheInterface $cache = null, UserInterface $user = null)
    {
        $this->docBlock = $docBlock;
        $this->cache = $cache;
        $this->user = $user ?? get_required_container(UserInterface::class);
    }

    /**
     * @return CacheInterface|null
     */
    public function getCache() : ?CacheInterface
    {
        return $this->cache;
    }

    /**
     * @param CacheInterface|null $cache
     */
    public function setCache(?CacheInterface $cache) : void
    {
        $this->cache = $cache;
    }

    /**
     * @return string
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Helpers\Exceptions\HelperException
     */
    public function getCacheDbName() : string
    {
        $dbName = $this->docBlock->getTagsByName(static::DOCBLOCK_CACHE_DB_NAME_LABEL)[0] ?? null;
        if (!$dbName || !($dbName = trim($dbName->getDescription()))) {
            return get_required_env(ConfigConstants::DEFAULT_DB);
        }

        if ($dbName === '$current') {
            /** @var App $app */
            $app = get_static_container(App::class);

            return $app::getSubdomain() ?: get_required_env(ConfigConstants::DEFAULT_DB);
        }

        return $dbName;
    }

    /**
     * @return array|null
     */
    protected function getMethodTags() : ?array
    {
        $tagStr = $this->docBlock->getTagsByName(static::DOCBLOCK_TAGS_LABEL)[0] ?? null;

        return null !== $tagStr
            ? array_map(static function ($tag) {
                $tag = trim($tag);
                return @constant($tag) ?? $tag;
            }, explode(',', $tagStr->getDescription()))
            : null;
    }

    /**
     * @return int|string
     */
    public function getCacheUserId()
    {
        static $userId;
        if (null === $userId) {
            $userId = $this->isCheckCacheUser() ? $this->user->getId() : '';
        }

        return $userId;
    }

    /**
     * @return bool
     */
    protected function isCheckCacheUser() : bool
    {
        if (empty($this->docBlock->getTagsByName(static::DOCBLOCK_NOCHECK_CACHE_USER_LABEL)[0])) {
            return true;
        }

        return false;
    }

    /**
     * @return mixed|null
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param UserInterface $user
     */
    public function setUser(UserInterface $user) : void
    {
        $this->user = $user;
    }

    /**
     * @return bool
     */
    public function isHit() : bool
    {
        return $this->isHit;
    }

    /**
     * @param CurrentRequest $request
     *
     * @return string|null|array
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Helpers\Exceptions\HelperException
     */
    protected function getCacheValue(CurrentRequest $request)
    {
        if (!$this->getUser() || null === ($tags = $this->getMethodTags())) {
            return null;
        }

        if (!$this->cache) {
            $this->cache = get_required_container(
                CacheInterface::class,
                [$request->getURL(), $request->getParams() + $request->getCacheAdditionalParams()]
            );
            $this->cache->setCacheDbName($this->getCacheDbName());
            $this->cache->setTags($tags);
            if ($request->getAccept() !== ContentTypes::JSON) {
                try {
                    /** @var App $app */
                    $app = get_static_container(App::class);
                    $this->cache->setVerifyingFilePath(
                        get_required_env('BUNDLE_PATH')
                        . get_required_env('VIEWS_PATH')
                        . $app::getViewPath($this->user->getRoleClass())
                    );
                } catch (ViewNotFoundException $e) {
                }
            }
        }

        return $this->cache->getHtml($this->getCacheUserId())->getResult();
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     *
     * @throws \ReflectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ContainerTypeNotSupportingException
     * @throws \Scaleplan\DependencyInjection\Exceptions\DependencyInjectionException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ParameterMustBeInterfaceNameOrClassNameException
     * @throws \Scaleplan\DependencyInjection\Exceptions\ReturnTypeMustImplementsInterfaceException
     * @throws \Scaleplan\Helpers\Exceptions\EnvNotFoundException
     * @throws \Scaleplan\Helpers\Exceptions\HelperException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        /** @var CurrentRequest $request */
        $cacheValue = $this->getCacheValue($request);
        if ($cacheValue !== null) {
            if (is_array($cacheValue)) {
                $request->getResponse()->setContentType(ContentTypes::JSON);
                $cacheValue = new ArrayResult($cacheValue);
            }

            $request->getResponse()->setPayload($cacheValue);
            $this->isHit = true;
        }

        return $request->getResponse();
    }
}
