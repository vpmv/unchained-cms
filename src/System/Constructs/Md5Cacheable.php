<?php


namespace App\System\Constructs;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class Md5Cacheable
{
    /** @var \Symfony\Contracts\Cache\CacheInterface */
    protected $cache;
    /** @var string|null */
    private $cacheKey;

    public function __construct(?string $cacheKey = null)
    {
        $this->cache    = new FilesystemAdapter();
        $this->cacheKey = $cacheKey;
    }

    /**
     * @param string $key
     * @param mixed  $contents
     * @param int    $ttl Defaults to 1 week
     *
     * @throws \Symfony\Component\Cache\Exception\InvalidArgumentException
     */
    protected function writeCache(string $key, $contents, int $ttl = 886400 * 7)
    {
        $this->cache->get(md5($this->cacheKey . $key), function (ItemInterface $item) use ($contents, $ttl) {
            $item->expiresAfter($ttl);

            return $contents;
        });
    }


    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     * @throws \Symfony\Component\Cache\Exception\InvalidArgumentException
     */
    protected function getCache(string $key, $default = null)
    {
        /** @var \Psr\Cache\CacheItemInterface $item */
        $item = $this->cache->getItem(md5($this->cacheKey . $key));

        return $item->isHit() ? $item->get() : $default;
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