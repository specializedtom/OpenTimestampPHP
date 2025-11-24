<?php
// src/Cache/MemcachedCache.php

namespace OpenTimestampsPHP\Cache;

use OpenTimestampsPHP\Cache\Exception\CacheException;

class MemcachedCache extends AbstractCache {
    private \Memcached $memcached;
    private bool $isConnected = false;

    public function __construct(array $options = []) {
        parent::__construct($options);
        
        $servers = $options['servers'] ?? [['127.0.0.1', 11211]];
        $options = $options['options'] ?? [];
        
        $this->memcached = new \Memcached();
        
        // Set options
        if (!empty($options)) {
            $this->memcached->setOptions($options);
        }
        
        // Add servers
        $this->memcached->addServers($servers);
        
        // Test connection
        $this->isConnected = $this->memcached->getVersion() !== false;
    }

    public function get(string $key, $default = null) {
        if (!$this->isConnected) {
            return $default;
        }

        $value = $this->memcached->get($this->prefixedKey($key));
        
        if ($this->memcached->getResultCode() === \Memcached::RES_SUCCESS) {
            return $value;
        }
        
        return $default;
    }

    public function set(string $key, $value, ?int $ttl = null): bool {
        if (!$this->isConnected) {
            return false;
        }

        $ttl = $ttl ?? $this->defaultTtl;
        
        return $this->memcached->set($this->prefixedKey($key), $value, $ttl);
    }

    public function delete(string $key): bool {
        if (!$this->isConnected) {
            return false;
        }

        return $this->memcached->delete($this->prefixedKey($key));
    }

    public function clear(): bool {
        if (!$this->isConnected) {
            return false;
        }

        return $this->memcached->flush();
    }

    public function has(string $key): bool {
        if (!$this->isConnected) {
            return false;
        }

        $this->memcached->get($this->prefixedKey($key));
        return $this->memcached->getResultCode() === \Memcached::RES_SUCCESS;
    }

    public function getMultiple(array $keys, $default = null): array {
        if (!$this->isConnected) {
            return array_fill_keys($keys, $default);
        }

        $prefixedKeys = array_map([$this, 'prefixedKey'], $keys);
        $values = $this->memcached->getMulti($prefixedKeys);
        
        $results = [];
        foreach ($keys as $key) {
            $prefixedKey = $this->prefixedKey($key);
            $results[$key] = $values[$prefixedKey] ?? $default;
        }
        
        return $results;
    }

    public function setMultiple(array $values, ?int $ttl = null): bool {
        if (!$this->isConnected) {
            return false;
        }

        $ttl = $ttl ?? $this->defaultTtl;
        $prefixedValues = [];
        
        foreach ($values as $key => $value) {
            $prefixedValues[$this->prefixedKey($key)] = $value;
        }
        
        return $this->memcached->setMulti($prefixedValues, $ttl);
    }

    public function deleteMultiple(array $keys): bool {
        if (!$this->isConnected) {
            return false;
        }

        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        
        return $success;
    }

    public function getStats(): array {
        $stats = parent::getStats();
        $stats['connected'] = $this->isConnected;
        
        if ($this->isConnected) {
            try {
                $serverStats = $this->memcached->getStats();
                $stats['servers'] = $serverStats;
                $stats['server_count'] = count($serverStats);
            } catch (\Exception $e) {
                $stats['error'] = $e->getMessage();
            }
        }
        
        return $stats;
    }
}