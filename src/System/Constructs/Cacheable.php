<?php


namespace App\System\Constructs;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class Cacheable
{
    protected FilesystemAdapter $cache;
    private ?string             $cacheKey;

    public function __construct(?string $cacheKey = null)
    {
        $this->cache = new FilesystemAdapter();
        $this->cacheKey = $cacheKey;
    }

    /**
     * @param string $key
     * @param mixed  $contents
     * @param int    $ttl Defaults to 1 week
     *
     * @return void
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function writeCache(string $key, mixed $contents, int $ttl = 886400 * 7): void
    {
        $this->cache->get($this->cacheKey . $key, function (ItemInterface $item) use ($contents, $ttl) {
            $item->expiresAfter($ttl);

            return $contents;
        });
    }


    /**
     * @param string $key
     * @param        $default
     *
     * @return mixed|null
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getCache(string $key, $default = null): mixed
    {
        /** @var \Psr\Cache\CacheItemInterface $item */
        $item = $this->cache->getItem($this->cacheKey . $key);
        if (!$item->isHit()) {
            return $default;
        }
        return $item->get();
    }

    /**
     * @param string   $key
     * @param callable $callback
     * @param int      $ttl
     *
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function remember(string $key, callable $callback, int $ttl = 886400 * 7): mixed
    {
        if ($cache = $this->getCache($key)) {
            return $cache;
        }

        $contents = $callback($this->cacheKey . $key);
        $this->writeCache($key, $contents, $ttl);

        return $contents;
    }

    protected function clearCacheKeys(array $keys): void
    {
        $keys = array_map(function ($v) {
            return str_starts_with($v, $this->cacheKey) ? $v : $this->cacheKey . $v;
        }, $keys);
        $this->cache->deleteItems($keys);
    }

    protected function clearCache(): void
    {
        $this->cache->clear();
    }
}