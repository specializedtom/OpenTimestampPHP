<?php
// tests/config_test.php

require_once __DIR__ . '/../vendor/autoload.php';

use OpenTimestampsPHP\Config\{
    Configuration,
    ConfigurationBuilder,
    ConfigurationFactory
};
use OpenTimestampsPHP\Config\Exception\ConfigException;

function test_basic_configuration() {
    echo "=== Testing Basic Configuration ===\n";
    
    $config = Configuration::withDefaults();
    
    // Test basic getters
    $servers = $config->get('calendar.servers');
    echo "Default servers: " . count($servers) . "\n";
    
    $timeout = $config->get('calendar.timeout');
    echo "Default timeout: $timeout\n";
    
    $strictness = $config->get('verification.strictness');
    echo "Default strictness: $strictness\n";
    
    echo "Basic configuration test completed.\n\n";
}

function test_configuration_builder() {
    echo "=== Testing Configuration Builder ===\n";
    
    $config = (new ConfigurationBuilder())
        ->withCalendarServers(['https://custom.pool.opentimestamps.org'])
        ->withTimeout(60)
        ->withStrictness('high')
        ->withLogging('debug', '/tmp/ots.log')
        ->build();
    
    echo "Custom servers: " . implode(', ', $config->get('calendar.servers')) . "\n";
    echo "Custom timeout: " . $config->get('calendar.timeout') . "\n";
    echo "Custom strictness: " . $config->get('verification.strictness') . "\n";
    echo "Log file: " . $config->get('logging.file') . "\n";
    
    echo "Configuration builder test completed.\n\n";
}

function test_environment_configuration() {
    echo "=== Testing Environment Configuration ===\n";
    
    // Set some environment variables
    putenv('OTS_CALENDAR_SERVERS=https://env1.pool.org,https://env2.pool.org');
    putenv('OTS_TIMEOUT=45');
    putenv('OTS_LOG_LEVEL=warn');
    
    $config = (new Configuration())->load();
    
    echo "Env servers: " . implode(', ', $config->get('calendar.servers')) . "\n";
    echo "Env timeout: " . $config->get('calendar.timeout') . "\n";
    echo "Env log level: " . $config->get('logging.level') . "\n";
    
    // Clean up
    putenv('OTS_CALENDAR_SERVERS');
    putenv('OTS_TIMEOUT');
    putenv('OTS_LOG_LEVEL');
    
    echo "Environment configuration test completed.\n\n";
}

function test_configuration_factory() {
    echo "=== Testing Configuration Factory ===\n";
    
    $productionConfig = ConfigurationFactory::create('production');
    $developmentConfig = ConfigurationFactory::create('development');
    $testingConfig = ConfigurationFactory::create('testing');
    
    echo "Production strictness: " . $productionConfig->get('verification.strictness') . "\n";
    echo "Development strictness: " . $developmentConfig->get('verification.strictness') . "\n";
    echo "Testing strictness: " . $testingConfig->get('verification.strictness') . "\n";
    
    echo "Configuration factory test completed.\n\n";
}

function test_configuration_validation() {
    echo "=== Testing Configuration Validation ===\n";
    
    try {
        $invalidConfig = Configuration::fromArray([
            'calendar' => [
                'servers' => [] // Empty servers should fail validation
            ]
        ]);
        echo "ERROR: Validation should have failed for empty servers\n";
    } catch (ConfigException $e) {
        echo "✓ Correctly caught validation error: " . $e->getMessage() . "\n";
    }
    
    try {
        $invalidConfig = Configuration::fromArray([
            'calendar' => [
                'servers' => ['http://insecure.pool.org'] // HTTP should fail
            ]
        ]);
        echo "ERROR: Validation should have failed for HTTP server\n";
    } catch (ConfigException $e) {
        echo "✓ Correctly caught validation error: " . $e->getMessage() . "\n";
    }
    
    echo "Configuration validation test completed.\n\n";
}

function test_configurable_client() {
    echo "=== Testing Configurable Client ===\n";
    
    $config = (new ConfigurationBuilder())
        ->withTimeout(25)
        ->withStrictness('medium')
        ->build();
    
    $client = new \OpenTimestampsPHP\Client\ConfigurableClient($config);
    
    $calendarClient = $client->getCalendarClient();
    $verifier = $client->getVerifier();
    
    echo "Configurable client created successfully\n";
    echo "Client config timeout: " . $client->getConfig()->get('calendar.timeout') . "\n";
    
    echo "Configurable client test completed.\n\n";
}

// Run all tests
test_basic_configuration();
test_configuration_builder();
test_environment_configuration();
test_configuration_factory();
test_configuration_validation();
test_configurable_client();