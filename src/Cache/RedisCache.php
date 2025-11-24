<?php
// src/Cache/RedisCache.php

namespace OpenTimestampsPHP\Cache;

use OpenTimestampsPHP\Cache\Exception\CacheException;

class RedisCache extends AbstractCache {
    private \Redis $redis;
    private bool $isConnected = false;

    public function __construct(array $options = []) {
        parent::__construct($options);
        
        $host = $options['host'] ?? '127.0.0.1';
        $port = $options['port'] ?? 6379;
        $timeout = $options['timeout'] ?? 2.5;
        $password = $options['password'] ?? null;
        $database = $options['database'] ?? 0;
        
        $this->redis = new \Redis();
        
        try {
            if (!$this->redis->connect($host, $port, $timeout)) {
                throw new CacheException("Cannot connect to Redis server: $host:$port");
            }
            
            if ($password && !$this->redis->auth($password)) {
                throw new CacheException("Redis authentication failed");
            }
            
            if ($database && !$this->redis->select($database)) {
                throw new CacheException("Cannot select Redis database: $database");
            }
            
            // Test connection
            $this->redis->ping();
            $this->isConnected = true;
            
        } catch (\RedisException $e) {
            throw new CacheException("Redis connection failed: " . $e->getMessage());
        }
    }

    public function get(string $key, $default = null) {
        if (!$this->isConnected) {
            return $default;
        }

        try {
            $value = $this->redis->get($this->prefixedKey($key));
            return $value === false ? $default : unserialize($value);
        } catch (\RedisException $e) {
            return $default;
        }
    }

    public function set(string $key, $value, ?int $ttl = null): bool {
        if (!$this->isConnected) {
            return false;
        }

        $ttl = $ttl ?? $this->defaultTtl;
        $serialized = serialize($value);

        try {
            if ($ttl > 0) {
                return $this->redis->setex($this->prefixedKey($key), $ttl, $serialized);
            } else {
                return $this->redis->set($this->prefixedKey($key), $serialized);
            }
        } catch (\RedisException $e) {
            return false;
        }
    }

    public function delete(string $key): bool {
        if (!$this->isConnected) {
            return false;
        }

        try {
            return $this->redis->del($this->prefixedKey($key)) > 0;
        } catch (\RedisException $e) {
            return false;
        }
    }

    public function clear(): bool {
        if (!$this->isConnected) {
            return false;
        }

        try {
            $pattern = $this->prefixedKey('*');
            $keys = $this->redis->keys($pattern);
            
            if (empty($keys)) {
                return true;
            }
            
            return $this->redis->del($keys) > 0;
        } catch (\RedisException $e) {
            return false;
        }
    }

    public function has(string $key): bool {
        if (!$this->isConnected) {
            return false;
        }

        try {
            return $this->redis->exists($this->prefixedKey($key));
        } catch (\RedisException $e) {
            return false;
        }
    }

    public function getMultiple(array $keys, $default = null): array {
        if (!$this->isConnected) {
            return array_fill_keys($keys, $default);
        }

        try {
            $prefixedKeys = array_map([$this, 'prefixedKey'], $keys);
            $values = $this->redis->mget($prefixedKeys);
            
            $results = [];
            foreach ($keys as $index => $key) {
                $value = $values[$index] ?? false;
                $results[$key] = $value === false ? $default : unserialize($value);
            }
            
            return $results;
        } catch (\RedisException $e) {
            return array_fill_keys($keys, $default);
        }
    }

    public function setMultiple(array $values, ?int $ttl = null): bool {
        if (!$this->isConnected) {
            return false;
        }

        $ttl = $ttl ?? $this->defaultTtl;

        try {
            $pipeline = $this->redis->pipeline();
            
            foreach ($values as $key => $value) {
                $serialized = serialize($value);
                if ($ttl > 0) {
                    $pipeline->setex($this->prefixedKey($key), $ttl, $serialized);
                } else {
                    $pipeline->set($this->prefixedKey($key), $serialized);
                }
            }
            
            $results = $pipeline->exec();
            return !in_array(false, $results, true);
        } catch (\RedisException $e) {
            return false;
        }
    }

    public function increment(string $key, int $value = 1): int {
        if (!$this->isConnected) {
            return 0;
        }

        try {
            return $this->redis->incrBy($this->prefixedKey($key), $value);
        } catch (\RedisException $e) {
            return 0;
        }
    }

    public function getStats(): array {
        $stats = parent::getStats();
        
        if (!$this->isConnected) {
            $stats['connected'] = false;
            return $stats;
        }

        try {
            $info = $this->redis->info();
            $stats['connected'] = true;
            $stats['redis_version'] = $info['redis_version'] ?? 'unknown';
            $stats['used_memory'] = $info['used_memory_human'] ?? 'unknown';
            $stats['connected_clients'] = $info['connected_clients'] ?? 'unknown';
            $stats['keys_count'] = $this->redis->dbSize();
        } catch (\RedisException $e) {
            $stats['connected'] = false;
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }

    public function __destruct() {
        if ($this->isConnected) {
            try {
                $this->redis->close();
            } catch (\RedisException $e) {
                // Ignore close errors
            }
        }
    }
}