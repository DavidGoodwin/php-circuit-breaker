<?php
namespace DavidGoodwin\CircuitBreaker\Storage\Adapter;

use DavidGoodwin\CircuitBreaker\Storage\Adapter\BaseAdapter;
use DavidGoodwin\CircuitBreaker\Storage\StorageException;
use Stash\Pool;

class StashAdapter extends BaseAdapter
{

    protected Pool $stash;

    public function __construct(\Stash\Pool $client, int $ttl = 3600, ?string $cachePrefix = null)
    {
        parent::__construct($ttl, $cachePrefix);
        $this->stash = $client;
    }

    protected function checkExtension()
    {
        if (!class_exists('\Stash\Pool', true)) {
            throw new StorageException("Stash not installed?");
        }
    }

    protected function load(string $key): string
    {
        /* md5 the key, as stash strtolowers it we can't otherwise enforce case sensitivity */
        $key = md5($key);
        try {
            return $this->stash->getItem($key)->get();
        } catch (\Exception $e) {
            throw new StorageException("Failed to load stash key: $key", 1, $e);
        }
    }

    protected function save(string $key, string $value, int $ttl): void
    {
        /* md5 the key, as stash strtolowers it we can't otherwise enforce case sensitivity */
        $key = md5($key);
        try {
            $item = $this->stash->getItem($key);
            $item->set($value);
            $item->expiresAfter($ttl);
            $this->stash->save($item);
        } catch (\Exception $e) {
            throw new StorageException("Failed to save stash key: $key", 1, $e);
        }
    }
}
