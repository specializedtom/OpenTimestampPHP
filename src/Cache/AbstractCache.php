<?php
// src/Cache/AbstractCache.php

namespace OpenTimestampsPHP\Cache;

abstract class AbstractCache implements CacheInterface {
    protected int $defaultTtl;
    protected string $prefix;
    protected array $options;

    public function __construct(array $options = []) {
        $this->defaultTtl = $options['ttl'] ?? 3600;
        $this->prefix = $options['prefix'] ?? 'ots_';
        $this->options = $options;
    }

    public function getMultiple(array $keys, $default = null): array {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    public function setMultiple(array $values, ?int $ttl = null): bool {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    public function deleteMultiple(array $keys): bool {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    public function increment(string $key, int $value = 1): int {
        $current = $this->get($key, 0);
        $newValue = $current + $value;
        $this->set($key, $newValue);
        return $newValue;
    }

    public function decrement(string $key, int $value = 1): int {
        return $this->increment($key, -$value);
    }

    protected function prefixedKey(string $key): string {
        return $this->prefix . $key;
    }

    public function getStats(): array {
        return [
            'driver' => get_class($this),
            'prefix' => $this->prefix,
            'default_ttl' => $this->defaultTtl
        ];
    }
}