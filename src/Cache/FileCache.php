<?php
// src/Cache/FileCache.php

namespace OpenTimestampsPHP\Cache;

use OpenTimestampsPHP\Cache\Exception\CacheException;

class FileCache extends AbstractCache {
    private string $cacheDir;
    private int $fileLockTimeout;
    private int $gcProbability;
    private int $gcDivisor;

    public function __construct(array $options = []) {
        parent::__construct($options);
        
        $this->cacheDir = $options['path'] ?? sys_get_temp_dir() . '/opentimestamps_cache';
        $this->fileLockTimeout = $options['lock_timeout'] ?? 10;
        $this->gcProbability = $options['gc_probability'] ?? 1;
        $this->gcDivisor = $options['gc_divisor'] ?? 100;
        
        $this->ensureCacheDirectory();
        $this->runGarbageCollection();
    }

    public function get(string $key, $default = null) {
        $filename = $this->getFilename($key);
        
        if (!file_exists($filename)) {
            return $default;
        }

        $lock = $this->acquireLock($filename, 'r');
        if (!$lock) {
            return $default;
        }

        try {
            $data = file_get_contents($filename);
            if ($data === false) {
                return $default;
            }

            $cached = unserialize($data);
            if (!$cached || !is_array($cached) || !isset($cached['expires']) || !isset($cached['data'])) {
                return $default;
            }

            if ($cached['expires'] > 0 && $cached['expires'] < time()) {
                $this->delete($key);
                return $default;
            }

            return $cached['data'];
        } finally {
            $this->releaseLock($lock);
        }
    }

    public function set(string $key, $value, ?int $ttl = null): bool {
        $filename = $this->getFilename($key);
        $tempFile = $filename . '.' . uniqid('', true) . '.tmp';

        $ttl = $ttl ?? $this->defaultTtl;
        $expires = $ttl > 0 ? time() + $ttl : 0;

        $data = serialize([
            'expires' => $expires,
            'data' => $value,
            'created' => time()
        ]);

        $lock = $this->acquireLock($filename, 'w');
        if (!$lock) {
            return false;
        }

        try {
            // Write to temporary file first
            if (file_put_contents($tempFile, $data) === false) {
                @unlink($tempFile);
                return false;
            }

            // Atomically move to final location
            if (!rename($tempFile, $filename)) {
                @unlink($tempFile);
                return false;
            }

            return true;
        } finally {
            $this->releaseLock($lock);
        }
    }

    public function delete(string $key): bool {
        $filename = $this->getFilename($key);
        
        if (!file_exists($filename)) {
            return true;
        }

        $lock = $this->acquireLock($filename, 'w');
        if (!$lock) {
            return false;
        }

        try {
            return unlink($filename);
        } finally {
            $this->releaseLock($lock);
        }
    }

    public function clear(): bool {
        $success = true;
        $pattern = $this->cacheDir . '/' . $this->prefix . '*';
        
        foreach (glob($pattern) as $filename) {
            if (!unlink($filename)) {
                $success = false;
            }
        }
        
        return $success;
    }

    public function has(string $key): bool {
        $filename = $this->getFilename($key);
        
        if (!file_exists($filename)) {
            return false;
        }

        $lock = $this->acquireLock($filename, 'r');
        if (!$lock) {
            return false;
        }

        try {
            $data = file_get_contents($filename);
            if ($data === false) {
                return false;
            }

            $cached = unserialize($data);
            if (!$cached || !is_array($cached) || !isset($cached['expires'])) {
                return false;
            }

            if ($cached['expires'] > 0 && $cached['expires'] < time()) {
                $this->delete($key);
                return false;
            }

            return true;
        } finally {
            $this->releaseLock($lock);
        }
    }

    public function getStats(): array {
        $stats = parent::getStats();
        $pattern = $this->cacheDir . '/' . $this->prefix . '*';
        $files = glob($pattern) ?: [];
        
        $totalSize = 0;
        $expiredCount = 0;
        $validCount = 0;

        foreach ($files as $filename) {
            $size = filesize($filename);
            $totalSize += $size;
            
            $data = file_get_contents($filename);
            if ($data !== false) {
                $cached = unserialize($data);
                if ($cached && is_array($cached) && isset($cached['expires'])) {
                    if ($cached['expires'] > 0 && $cached['expires'] < time()) {
                        $expiredCount++;
                    } else {
                        $validCount++;
                    }
                }
            }
        }

        $stats['cache_dir'] = $this->cacheDir;
        $stats['total_files'] = count($files);
        $stats['valid_entries'] = $validCount;
        $stats['expired_entries'] = $expiredCount;
        $stats['total_size'] = $this->formatBytes($totalSize);
        $stats['disk_usage'] = $this->getDiskUsage();

        return $stats;
    }

    private function getFilename(string $key): string {
        $hash = hash('sha256', $key);
        // Use subdirectories to avoid too many files in one directory
        $subDir = substr($hash, 0, 2);
        $dir = $this->cacheDir . '/' . $subDir;
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return $dir . '/' . $this->prefixedKey($hash);
    }

    private function ensureCacheDirectory(): void {
        if (!is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0755, true)) {
                throw new CacheException("Cannot create cache directory: " . $this->cacheDir);
            }
        }

        if (!is_writable($this->cacheDir)) {
            throw new CacheException("Cache directory is not writable: " . $this->cacheDir);
        }
    }

    private function acquireLock(string $filename, string $mode): ?resource {
        $lockFile = $filename . '.lock';
        $startTime = microtime(true);
        
        while (microtime(true) - $startTime < $this->fileLockTimeout) {
            $handle = @fopen($lockFile, 'c+');
            if ($handle === false) {
                usleep(100000); // 100ms
                continue;
            }
            
            if (flock($handle, $mode === 'w' ? LOCK_EX : LOCK_SH)) {
                return $handle;
            }
            
            fclose($handle);
            usleep(100000); // 100ms
        }
        
        return null;
    }

    private function releaseLock($lock): void {
        if (is_resource($lock)) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function runGarbageCollection(): void {
        // Simple probabilistic garbage collection
        if (rand(1, $this->gcDivisor) <= $this->gcProbability) {
            $this->collectGarbage();
        }
    }

    private function collectGarbage(): void {
        $pattern = $this->cacheDir . '/**/' . $this->prefix . '*';
        $files = glob($pattern) ?: [];
        $now = time();
        
        foreach ($files as $filename) {
            // Skip lock files
            if (strpos($filename, '.lock') !== false) {
                continue;
            }
            
            $data = file_get_contents($filename);
            if ($data === false) {
                continue;
            }
            
            $cached = unserialize($data);
            if ($cached && is_array($cached) && isset($cached['expires'])) {
                if ($cached['expires'] > 0 && $cached['expires'] < $now) {
                    @unlink($filename);
                }
            }
        }
    }

    private function getDiskUsage(): array {
        $pattern = $this->cacheDir . '/**/' . $this->prefix . '*';
        $files = glob($pattern) ?: [];
        
        $totalSize = 0;
        foreach ($files as $filename) {
            $totalSize += filesize($filename);
        }
        
        $free = disk_free_space($this->cacheDir);
        $total = disk_total_space($this->cacheDir);
        $used = $total - $free;
        
        return [
            'used' => $this->formatBytes($used),
            'free' => $this->formatBytes($free),
            'total' => $this->formatBytes($total),
            'cache_usage_percent' => $total > 0 ? round(($totalSize / $total) * 100, 2) : 0
        ];
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