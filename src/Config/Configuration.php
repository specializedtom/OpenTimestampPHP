<?php
// src/Config/Configuration.php

namespace OpenTimestampsPHP\Config;

use OpenTimestampsPHP\Config\Exception\ConfigException;

class Configuration {
    private array $config;
    private string $configPath;
    private bool $isLoaded = false;
    
    // Default configuration values
    private array $defaults = [
        'bitcoin_node' => [
            'enabled' => false,
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => '',
            'password' => '',
            'timeout' => 30,
            'use_https' => false,
            'rpc_path' => '/',
            'prefer_node' => true,
            'fallback_to_explorers' => true
        ],        
        'calendar' => [
            'servers' => [
                'https://a.pool.opentimestamps.org',
                'https://b.pool.opentimestamps.org', 
                'https://c.pool.opentimestamps.org'
            ],
            'timeout' => 30,
            'user_agent' => 'OpenTimestampsPHP/1.0',
            'max_retries' => 3,
            'retry_delay' => 1,
            'backoff_strategy' => 'exponential'
        ],
        'verification' => [
            'strictness' => 'medium', // 'low', 'medium', 'high'
            'require_merkle_integrity' => true,
            'require_consensus' => false,
            'min_confidence_score' => 0.6,
            'max_timestamp_drift' => 7200,
            'allow_pending_attestations' => true,
            'cache_ttl' => 3600
        ],
        'attestations' => [
            'preferred_blockchains' => ['bitcoin', 'litecoin'],
            'bitcoin_explorers' => [
                'https://blockstream.info/api',
                'https://blockchain.info',
                'https://api.blockcypher.com/v1/btc'
            ],
            'litecoin_explorers' => [
                'https://api.blockcypher.com/v1/ltc/main',
                'https://chainz.cryptoid.info/ltc/api.dws'
            ],
            'ethereum_explorers' => [
                'https://api.etherscan.io/api',
                'https://cloudflare-eth.com'
            ]
        ],
        'file_handling' => [
            'default_mode' => 'detached', // 'detached' or 'attached'
            'create_backups' => true,
            'backup_suffix' => '.bak',
            'atomic_writes' => true
        ],
        'logging' => [
            'enabled' => true,
            'level' => 'info', // 'debug', 'info', 'warn', 'error'
            'file' => null, // null for stderr
            'format' => 'text', // 'text' or 'json'
            'max_file_size' => 10485760, // 10MB
            'max_files' => 5
        ],
        'cache' => [
            'enabled' => true,
            'driver' => 'file', // 'file', 'redis', 'memcached'
            'ttl' => 3600,
            'file_path' => '/tmp/opentimestamps_cache',
            'redis_host' => '127.0.0.1',
            'redis_port' => 6379,
            'memcached_servers' => [['127.0.0.1', 11211]]
        ],
        'performance' => [
            'max_concurrent_requests' => 5,
            'batch_size' => 10,
            'enable_compression' => true,
            'optimize_merkle_trees' => true
        ],
        'security' => [
            'validate_ssl_certificates' => true,
            'allowed_calendar_servers' => [], // Empty = allow defaults + configured
            'max_file_size' => 104857600, // 100MB
            'allowed_hash_algorithms' => ['sha256', 'sha1', 'ripemd160']
        ]
    ];

    public function __construct(?string $configPath = null) {
        $this->config = $this->defaults;
        $this->configPath = $configPath ?? $this->discoverConfigPath();
    }

    /**
     * Load configuration from file and environment
     */
    public function load(): self {
        if ($this->isLoaded) {
            return $this;
        }

        // 1. Load from file if exists
        if ($this->configPath && file_exists($this->configPath)) {
            $this->loadFromFile($this->configPath);
        }

        // 2. Override with environment variables
        $this->loadFromEnvironment();

        // 3. Validate the final configuration
        $this->validate();

        $this->isLoaded = true;
        
        return $this;
    }

    /**
     * Discover configuration file path
     */
    private function discoverConfigPath(): ?string {
        $possiblePaths = [
            getcwd() . '/opentimestamps.json',
            getcwd() . '/opentimestamps.yaml',
            getcwd() . '/opentimestamps.yml',
            getcwd() . '/.opentimestamps.json',
            getcwd() . '/.opentimestamps.yaml',
            getcwd() . '/.opentimestamps.yml',
            $_SERVER['HOME'] ?? null . '/.config/opentimestamps/config.json',
            $_SERVER['HOME'] ?? null . '/.config/opentimestamps/config.yaml',
            '/etc/opentimestamps/config.json',
            '/etc/opentimestamps/config.yaml'
        ];

        foreach ($possiblePaths as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Load configuration from file
     */
    private function loadFromFile(string $filePath): void {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        try {
            switch ($extension) {
                case 'json':
                    $this->loadFromJson($filePath);
                    break;
                case 'yaml':
                case 'yml':
                    $this->loadFromYaml($filePath);
                    break;
                case 'php':
                    $this->loadFromPhp($filePath);
                    break;
                default:
                    throw new ConfigException("Unsupported config file format: $extension");
            }
        } catch (ConfigException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ConfigException("Failed to load config file: " . $e->getMessage());
        }
    }

    private function loadFromJson(string $filePath): void {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new ConfigException("Cannot read config file: $filePath");
        }

        $config = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ConfigException("Invalid JSON in config file: " . json_last_error_msg());
        }

        $this->mergeConfig($config);
    }

    private function loadFromYaml(string $filePath): void {
        if (!extension_loaded('yaml')) {
            throw new ConfigException("YAML extension required for YAML config files");
        }

        $config = yaml_parse_file($filePath);
        if ($config === false) {
            throw new ConfigException("Invalid YAML in config file: $filePath");
        }

        $this->mergeConfig($config);
    }

    private function loadFromPhp(string $filePath): void {
        $config = require $filePath;
        if (!is_array($config)) {
            throw new ConfigException("PHP config file must return an array");
        }

        $this->mergeConfig($config);
    }

    /**
     * Load configuration from environment variables
     */
    private function loadFromEnvironment(): void {
        $envVars = [
            // Calendar settings
            'OTS_CALENDAR_SERVERS' => function($value) {
                $this->set('calendar.servers', explode(',', $value));
            },
            'OTS_TIMEOUT' => function($value) {
                $this->set('calendar.timeout', (int)$value);
            },
            'OTS_MAX_RETRIES' => function($value) {
                $this->set('calendar.max_retries', (int)$value);
            },
            
            // Verification settings
            'OTS_VERIFICATION_STRICTNESS' => function($value) {
                $this->set('verification.strictness', $value);
            },
            'OTS_MIN_CONFIDENCE' => function($value) {
                $this->set('verification.min_confidence_score', (float)$value);
            },
            
            // Logging settings
            'OTS_LOG_LEVEL' => function($value) {
                $this->set('logging.level', $value);
            },
            'OTS_LOG_FILE' => function($value) {
                $this->set('logging.file', $value);
            },
            
            // Cache settings
            'OTS_CACHE_DRIVER' => function($value) {
                $this->set('cache.driver', $value);
            },
            'OTS_CACHE_TTL' => function($value) {
                $this->set('cache.ttl', (int)$value);
            }
        ];

        foreach ($envVars as $envVar => $setter) {
            $value = getenv($envVar);
            if ($value !== false) {
                $setter($value);
            }
        }
    }

    /**
     * Merge configuration recursively
     */
    private function mergeConfig(array $config): void {
        $this->config = $this->arrayMergeRecursive($this->config, $config);
    }

    private function arrayMergeRecursive(array $array1, array $array2): array {
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                $array1[$key] = $this->arrayMergeRecursive($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }
        return $array1;
    }

    /**
     * Validate configuration
     */
    private function validate(): void {
        // Calendar servers validation
        $servers = $this->get('calendar.servers');
        if (!is_array($servers) || empty($servers)) {
            throw new ConfigException("At least one calendar server must be configured");
        }
        
        foreach ($servers as $server) {
            if (!filter_var($server, FILTER_VALIDATE_URL)) {
                throw new ConfigException("Invalid calendar server URL: $server");
            }
            if (strpos($server, 'https://') !== 0) {
                throw new ConfigException("Calendar server must use HTTPS: $server");
            }
        }

        // Timeout validation
        $timeout = $this->get('calendar.timeout');
        if (!is_int($timeout) || $timeout < 1 || $timeout > 300) {
            throw new ConfigException("Timeout must be between 1 and 300 seconds");
        }

        // Strictness validation
        $strictness = $this->get('verification.strictness');
        if (!in_array($strictness, ['low', 'medium', 'high'])) {
            throw new ConfigException("Strictness must be 'low', 'medium', or 'high'");
        }

        // Cache TTL validation
        $ttl = $this->get('cache.ttl');
        if (!is_int($ttl) || $ttl < 0) {
            throw new ConfigException("Cache TTL must be a positive integer");
        }

        // Log level validation
        $logLevel = $this->get('logging.level');
        if (!in_array($logLevel, ['debug', 'info', 'warn', 'error'])) {
            throw new ConfigException("Log level must be 'debug', 'info', 'warn', or 'error'");
        }
    }

    /**
     * Get configuration value using dot notation
     */
    public function get(string $key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }

    /**
     * Set configuration value using dot notation
     */
    public function set(string $key, $value): self {
        $keys = explode('.', $key);
        $config = &$this->config;
        
        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }
        
        return $this;
    }

    /**
     * Check if configuration key exists
     */
    public function has(string $key): bool {
        return $this->get($key, null) !== null;
    }

    /**
     * Get all configuration as array
     */
    public function all(): array {
        return $this->config;
    }

    /**
     * Get configuration file path
     */
    public function getConfigPath(): ?string {
        return $this->configPath;
    }

    /**
     * Check if configuration is loaded
     */
    public function isLoaded(): bool {
        return $this->isLoaded;
    }

    /**
     * Create configuration from array
     */
    public static function fromArray(array $config): self {
        $instance = new self();
        $instance->config = $instance->arrayMergeRecursive($instance->defaults, $config);
        $instance->validate();
        $instance->isLoaded = true;
        return $instance;
    }

    /**
     * Create configuration with defaults only
     */
    public static function withDefaults(): self {
        $instance = new self();
        $instance->isLoaded = true;
        return $instance;
    }
}