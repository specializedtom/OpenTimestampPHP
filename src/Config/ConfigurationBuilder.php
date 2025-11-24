<?php
// src/Config/ConfigurationBuilder.php

namespace OpenTimestampsPHP\Config;

class ConfigurationBuilder {
    private Configuration $config;

    public function __construct() {
        $this->config = new Configuration();
    }

    public function withCalendarServers(array $servers): self {
        $this->config->set('calendar.servers', $servers);
        return $this;
    }

    public function withTimeout(int $timeout): self {
        $this->config->set('calendar.timeout', $timeout);
        return $this;
    }

    public function withMaxRetries(int $maxRetries): self {
        $this->config->set('calendar.max_retries', $maxRetries);
        return $this;
    }

    public function withStrictness(string $strictness): self {
        $this->config->set('verification.strictness', $strictness);
        return $this;
    }

    public function withLogging(string $level, ?string $file = null): self {
        $this->config->set('logging.level', $level);
        if ($file !== null) {
            $this->config->set('logging.file', $file);
        }
        return $this;
    }

    public function withCache(string $driver, int $ttl = 3600): self {
        $this->config->set('cache.driver', $driver);
        $this->config->set('cache.ttl', $ttl);
        return $this;
    }

    public function withFileHandling(string $mode, bool $backups = true): self {
        $this->config->set('file_handling.default_mode', $mode);
        $this->config->set('file_handling.create_backups', $backups);
        return $this;
    }

    public function build(): Configuration {
        return $this->config->load();
    }

    /**
     * Create a production-ready configuration
     */
    public static function forProduction(): Configuration {
        return (new self())
            ->withCalendarServers([
                'https://a.pool.opentimestamps.org',
                'https://b.pool.opentimestamps.org',
                'https://c.pool.opentimestamps.org'
            ])
            ->withTimeout(30)
            ->withMaxRetries(3)
            ->withStrictness('high')
            ->withLogging('info')
            ->withCache('file')
            ->withFileHandling('detached', true)
            ->build();
    }

    /**
     * Create a development configuration
     */
    public static function forDevelopment(): Configuration {
        return (new self())
            ->withCalendarServers([
                'https://a.pool.opentimestamps.org',
                'https://b.pool.opentimestamps.org'
            ])
            ->withTimeout(10)
            ->withMaxRetries(2)
            ->withStrictness('medium')
            ->withLogging('debug')
            ->withCache('file', 600)
            ->withFileHandling('detached', false)
            ->build();
    }

    /**
     * Create a testing configuration
     */
    public static function forTesting(): Configuration {
        return (new self())
            ->withCalendarServers([
                'https://a.pool.opentimestamps.org'
            ])
            ->withTimeout(5)
            ->withMaxRetries(1)
            ->withStrictness('low')
            ->withLogging('error') // Minimal logging for tests
            ->withCache('file', 60)
            ->withFileHandling('detached', false)
            ->build();
    }
}