<?php


namespace App\System\Constructs;

use \Psr\SimpleCache\CacheInterface;

class Md5Cacheable
{
    /** @var \Psr\SimpleCache\CacheInterface */
    protected $cache;
    /** @var string|null */
    private $cacheKey;

    public function __construct(CacheInterface $cache, ?string $cacheKey = null)
    {
        $this->cache    = $cache;
        $this->cacheKey = $cacheKey;
    }

    /**
     * @param string $key
     * @param mixed  $contents
     * @param int    $ttl Defaults to 1 week
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function writeCache(string $key, $contents, int $ttl = 886400 * 7)
    {
        $this->cache->set(md5($this->cacheKey . $key), $contents, $ttl);
    }


    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function getCache(string $key, $default = null)
    {
        return $this->cache->get(md5($this->cacheKey . $key), $default);
    }

    protected function remember(string $key, callable $callback)
    {
        if ($cache = $this->getCache($key)) {
            return $cache;
        }

        $contents = $callback($this->cacheKey . $key);
        $this->writeCache($key, $contents);
        return $contents;
    }
}