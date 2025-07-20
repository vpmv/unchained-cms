<?php


namespace App\System\Constructs;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class Md5Cacheable
{
    protected CacheInterface $cache;
    private ?string          $cacheKey;

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
        $this->cache->get(md5($this->cacheKey . $key), function (ItemInterface $item) use ($contents, $ttl) {
            $item->expiresAfter($ttl);

            return $contents;
        });
    }


    /**
     * @param string     $key
     * @param mixed|null $default
     *
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getCache(string $key, mixed $default = null): mixed
    {
        /** @var \Psr\Cache\CacheItemInterface $item */
        $item = $this->cache->getItem(md5($this->cacheKey . $key));

        return $item->isHit() ? $item->get() : $default;
    }

    /**
     * @param string   $key
     * @param callable $callback
     *
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function remember(string $key, callable $callback): mixed
    {
        if ($cache = $this->getCache($key)) {
            return $cache;
        }

        $contents = $callback($this->cacheKey . $key);
        $this->writeCache($key, $contents);

        return $contents;
    }
}