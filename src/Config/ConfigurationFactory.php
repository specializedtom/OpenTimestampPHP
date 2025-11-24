<?php
// src/Config/ConfigurationFactory.php

namespace OpenTimestampsPHP\Config;

class ConfigurationFactory {
    /**
     * Create configuration based on environment
     */
    public static function create(?string $environment = null): Configuration {
        $environment = $environment ?? self::detectEnvironment();
        
        switch ($environment) {
            case 'production':
                return ConfigurationBuilder::forProduction();
            case 'development':
                return ConfigurationBuilder::forDevelopment();
            case 'testing':
                return ConfigurationBuilder::forTesting();
            default:
                return (new Configuration())->load();
        }
    }

    /**
     * Detect current environment
     */
    public static function detectEnvironment(): string {
        // Check environment variable first
        $env = getenv('OTS_ENV') ?: getenv('APP_ENV');
        if ($env) {
            return $env;
        }

        // Auto-detect based on common patterns
        if (php_sapi_name() === 'cli' && isset($_SERVER['argv'][0])) {
            if (strpos($_SERVER['argv'][0], 'phpunit') !== false) {
                return 'testing';
            }
        }

        if (isset($_SERVER['SERVER_NAME'])) {
            if ($_SERVER['SERVER_NAME'] === 'localhost' || 
                strpos($_SERVER['SERVER_NAME'], '.local') !== false ||
                strpos($_SERVER['SERVER_NAME'], 'dev.') !== false) {
                return 'development';
            }
        }

        return 'production';
    }

    /**
     * Create configuration from file path
     */
    public static function fromFile(string $filePath): Configuration {
        $config = new Configuration($filePath);
        return $config->load();
    }

    /**
     * Create configuration with custom values
     */
    public static function fromArray(array $values): Configuration {
        return Configuration::fromArray($values);
    }
}