<?php
// src/Cache/ArrayCache.php

namespace OpenTimestampsPHP\Cache;

class ArrayCache extends AbstractCache {
    private array $data = [];
    private array $expirations = [];

    public function get(string $key, $default = null) {
        if (!$this->has($key)) {
            return $default;
        }
        
        return $this->data[$this->prefixedKey($key)];
    }

    public function set(string $key, $value, ?int $ttl = null): bool {
        $prefixedKey = $this->prefixedKey($key);
        $this->data[$prefixedKey] = $value;
        
        $ttl = $ttl ?? $this->defaultTtl;
        $this->expirations[$prefixedKey] = $ttl > 0 ? time() + $ttl : 0;
        
        return true;
    }

    public function delete(string $key): bool {
        $prefixedKey = $this->prefixedKey($key);
        unset($this->data[$prefixedKey]);
        unset($this->expirations[$prefixedKey]);
        
        return true;
    }

    public function clear(): bool {
        $this->data = [];
        $this->expirations = [];
        return true;
    }

    public function has(string $key): bool {
        $prefixedKey = $this->prefixedKey($key);
        
        if (!isset($this->data[$prefixedKey])) {
            return false;
        }
        
        $expiration = $this->expirations[$prefixedKey] ?? 0;
        if ($expiration > 0 && $expiration < time()) {
            $this->delete($key);
            return false;
        }
        
        return true;
    }

    public function getStats(): array {
        $stats = parent::getStats();
        $stats['entries_count'] = count($this->data);
        $stats['memory_usage'] = $this->formatBytes(memory_get_usage(true));
        
        $expiredCount = 0;
        $now = time();
        foreach ($this->expirations as $expiration) {
            if ($expiration > 0 && $expiration < $now) {
                $expiredCount++;
            }
        }
        
        $stats['expired_entries'] = $expiredCount;
        
        return $stats;
    }

    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}