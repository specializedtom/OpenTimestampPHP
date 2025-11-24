<?php
// src/Cache/CacheManager.php

namespace OpenTimestampsPHP\Cache;

use OpenTimestampsPHP\Cache\Exception\CacheException;

class CacheManager {
    private CacheInterface $cache;
    private array $config;
    private bool $enabled;

    public function __construct(array $config = []) {
        $this->config = array_merge([
            'enabled' => true,
            'driver' => 'file',
            'ttl' => 3600,
            'prefix' => 'ots_',
        ], $config);
        
        $this->enabled = $this->config['enabled'];
        $this->cache = $this->createCacheInstance();
    }

    private function createCacheInstance(): CacheInterface {
        if (!$this->enabled) {
            return new ArrayCache(['ttl' => 0]); // Null cache effectively
        }

        $driver = $this->config['driver'];
        $options = $this->config[$driver . '_options'] ?? [];
        $options['ttl'] = $this->config['ttl'];
        $options['prefix'] = $this->config['prefix'];

        switch ($driver) {
            case 'file':
                return new FileCache($options);
            case 'redis':
                return new RedisCache($options);
            case 'memcached':
                return new MemcachedCache($options);
            case 'array':
                return new ArrayCache($options);
            default:
                throw new CacheException("Unsupported cache driver: $driver");
        }
    }

    /**
     * Cache block headers with blockchain-specific TTL
     */
    public function cacheBlockHeader(string $blockchain, int $height, array $blockHeader): bool {
        if (!$this->enabled) {
            return false;
        }

        $key = "block_header:{$blockchain}:{$height}";
        
        // Longer TTL for confirmed blocks
        $ttl = $height < 1000000 ? 86400 * 30 : 86400 * 7; // 30 days for old blocks, 7 for recent
        
        return $this->cache->set($key, $blockHeader, $ttl);
    }

    public function getBlockHeader(string $blockchain, int $height): ?array {
        if (!$this->enabled) {
            return null;
        }

        $key = "block_header:{$blockchain}:{$height}";
        return $this->cache->get($key);
    }

    /**
     * Cache calendar responses
     */
    public function cacheCalendarResponse(string $digest, $response, int $ttl = 3600): bool {
        if (!$this->enabled) {
            return false;
        }

        $key = "calendar_response:" . bin2hex($digest);
        return $this->cache->set($key, $response, $ttl);
    }

    public function getCalendarResponse(string $digest) {
        if (!$this->enabled) {
            return null;
        }

        $key = "calendar_response:" . bin2hex($digest);
        return $this->cache->get($key);
    }

    /**
     * Cache timestamp verification results
     */
    public function cacheVerificationResult(string $timestampHash, array $result, int $ttl = 3600): bool {
        if (!$this->enabled) {
            return false;
        }

        $key = "verification_result:" . bin2hex($timestampHash);
        return $this->cache->set($key, $result, $ttl);
    }

    public function getVerificationResult(string $timestampHash): ?array {
        if (!$this->enabled) {
            return null;
        }

        $key = "verification_result:" . bin2hex($timestampHash);
        return $this->cache->get($key);
    }

    /**
     * Cache Merkle tree structures
     */
    public function cacheMerkleTree(string $rootHash, array $tree, int $ttl = 86400): bool {
        if (!$this->enabled) {
            return false;
        }

        $key = "merkle_tree:" . bin2hex($rootHash);
        return $this->cache->set($key, $tree, $ttl);
    }

    public function getMerkleTree(string $rootHash): ?array {
        if (!$this->enabled) {
            return null;
        }

        $key = "merkle_tree:" . bin2hex($rootHash);
        return $this->cache->get($key);
    }

    /**
     * Rate limiting
     */
    public function rateLimit(string $action, string $identifier, int $maxAttempts, int $window): bool {
        if (!$this->enabled) {
            return false;
        }

        $key = "rate_limit:{$action}:{$identifier}";
        $attempts = $this->cache->get($key, 0);
        
        if ($attempts >= $maxAttempts) {
            return true; // Limited
        }
        
        $this->cache->increment($key);
        $this->cache->set($key, $attempts + 1, $window);
        
        return false;
    }

    /**
     * Clear all OpenTimestamps cache entries
     */
    public function clearAll(): bool {
        return $this->cache->clear();
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array {
        $stats = $this->cache->getStats();
        $stats['enabled'] = $this->enabled;
        $stats['config'] = [
            'driver' => $this->config['driver'],
            'ttl' => $this->config['ttl']
        ];
        
        return $stats;
    }

    /**
     * Enable/disable cache
     */
    public function setEnabled(bool $enabled): void {
        $this->enabled = $enabled;
        if ($enabled && !isset($this->cache)) {
            $this->cache = $this->createCacheInstance();
        }
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function getCache(): CacheInterface {
        return $this->cache;
    }
}