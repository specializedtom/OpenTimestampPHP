<?php
// src/Cache/CacheFactory.php

namespace OpenTimestampsPHP\Cache;

use OpenTimestampsPHP\Config\Configuration;

class CacheFactory {
    public static function createFromConfig(Configuration $config): CacheManager {
        $cacheConfig = [
            'enabled' => $config->get('cache.enabled', true),
            'driver' => $config->get('cache.driver', 'file'),
            'ttl' => $config->get('cache.ttl', 3600),
            'prefix' => $config->get('cache.prefix', 'ots_'),
        ];

        // Driver-specific options
        $driver = $cacheConfig['driver'];
        switch ($driver) {
            case 'file':
                $cacheConfig['file_options'] = [
                    'path' => $config->get('cache.file_path', sys_get_temp_dir() . '/opentimestamps_cache'),
                    'lock_timeout' => $config->get('cache.file_lock_timeout', 10),
                    'gc_probability' => $config->get('cache.gc_probability', 1),
                    'gc_divisor' => $config->get('cache.gc_divisor', 100),
                ];
                break;
            case 'redis':
                $cacheConfig['redis_options'] = [
                    'host' => $config->get('cache.redis_host', '127.0.0.1'),
                    'port' => $config->get('cache.redis_port', 6379),
                    'password' => $config->get('cache.redis_password'),
                    'database' => $config->get('cache.redis_database', 0),
                    'timeout' => $config->get('cache.redis_timeout', 2.5),
                ];
                break;
            case 'memcached':
                $cacheConfig['memcached_options'] = [
                    'servers' => $config->get('cache.memcached_servers', [['127.0.0.1', 11211]]),
                    'options' => $config->get('cache.memcached_options', []),
                ];
                break;
        }

        return new CacheManager($cacheConfig);
    }

    public static function createFileCache(string $path = null): CacheManager {
        return new CacheManager([
            'driver' => 'file',
            'file_options' => ['path' => $path]
        ]);
    }

    public static function createRedisCache(string $host = '127.0.0.1', int $port = 6379): CacheManager {
        return new CacheManager([
            'driver' => 'redis',
            'redis_options' => compact('host', 'port')
        ]);
    }

    public static function createArrayCache(): CacheManager {
        return new CacheManager([
            'driver' => 'array'
        ]);
    }

    public static function createNullCache(): CacheManager {
        return new CacheManager([
            'enabled' => false
        ]);
    }
}