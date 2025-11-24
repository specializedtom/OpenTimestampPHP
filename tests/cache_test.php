<?php
// tests/cache_test.php

require_once __DIR__ . '/../vendor/autoload.php';

use OpenTimestampsPHP\Cache\{
    CacheManager,
    CacheFactory,
    FileCache,
    ArrayCache
};

function test_array_cache() {
    echo "=== Testing Array Cache ===\n";
    
    $cache = new ArrayCache(['ttl' => 2]); // 2 second TTL for testing
    
    // Basic set/get
    $cache->set('test_key', 'test_value');
    $value = $cache->get('test_key');
    echo "Basic set/get: " . ($value === 'test_value' ? 'PASS' : 'FAIL') . "\n";
    
    // TTL expiration
    $cache->set('expiring_key', 'will_expire', 1);
    sleep(2);
    $expired = $cache->get('expiring_key', 'not_found');
    echo "TTL expiration: " . ($expired === 'not_found' ? 'PASS' : 'FAIL') . "\n";
    
    // Multiple operations
    $cache->setMultiple(['key1' => 'val1', 'key2' => 'val2']);
    $values = $cache->getMultiple(['key1', 'key2', 'key3'], 'default');
    echo "Multiple operations: " . 
         ($values['key1'] === 'val1' && $values['key2'] === 'val2' && $values['key3'] === 'default' ? 'PASS' : 'FAIL') . "\n";
    
    // Increment
    $cache->set('counter', 5);
    $cache->increment('counter', 3);
    $counter = $cache->get('counter');
    echo "Increment: " . ($counter === 8 ? 'PASS' : 'FAIL') . "\n";
    
    // Stats
    $stats = $cache->getStats();
    echo "Stats: " . ($stats['entries_count'] > 0 ? 'PASS' : 'FAIL') . "\n";
    
    echo "Array cache test completed.\n\n";
}

function test_cache_manager() {
    echo "=== Testing Cache Manager ===\n";
    
    $manager = CacheFactory::createArrayCache();
    
    // Test block header caching
    $blockHeader = [
        'hash' => '0000000000000000000abc123...',
        'height' => 800000,
        'timestamp' => time()
    ];
    
    $manager->cacheBlockHeader('bitcoin', 800000, $blockHeader);
    $retrieved = $manager->getBlockHeader('bitcoin', 800000);
    echo "Block header caching: " . ($retrieved['height'] === 800000 ? 'PASS' : 'FAIL') . "\n";
    
    // Test calendar response caching
    $calendarResponse = ['pending_attestations' => []];
    $digest = hash('sha256', 'test', true);
    $manager->cacheCalendarResponse($digest, $calendarResponse);
    $retrieved = $manager->getCalendarResponse($digest);
    echo "Calendar response caching: " . (!empty($retrieved) ? 'PASS' : 'FAIL') . "\n";
    
    // Test rate limiting
    $limited1 = $manager->rateLimit('test_action', 'user1', 3, 60);
    $limited2 = $manager->rateLimit('test_action', 'user1', 3, 60);
    $limited3 = $manager->rateLimit('test_action', 'user1', 3, 60);
    $limited4 = $manager->rateLimit('test_action', 'user1', 3, 60); // Should be limited
    
    echo "Rate limiting: " . (!$limited1 && !$limited2 && !$limited3 && $limited4 ? 'PASS' : 'FAIL') . "\n";
    
    // Test stats
    $stats = $manager->getStats();
    echo "Cache manager stats: " . (isset($stats['enabled']) ? 'PASS' : 'FAIL') . "\n";
    
    echo "Cache manager test completed.\n\n";
}

function test_file_cache() {
    echo "=== Testing File Cache ===\n";
    
    $tempDir = sys_get_temp_dir() . '/ots_cache_test';
    $cache = new FileCache(['path' => $tempDir, 'ttl' => 2]);
    
    try {
        // Basic functionality
        $cache->set('file_test', 'file_value');
        $value = $cache->get('file_test');
        echo "File cache basic: " . ($value === 'file_value' ? 'PASS' : 'FAIL') . "\n";
        
        // Persistence (create new instance)
        $cache2 = new FileCache(['path' => $tempDir]);
        $value = $cache2->get('file_test');
        echo "File cache persistence: " . ($value === 'file_value' ? 'PASS' : 'FAIL') . "\n";
        
        // Stats
        $stats = $cache->getStats();
        echo "File cache stats: " . (isset($stats['total_files']) ? 'PASS' : 'FAIL') . "\n";
        
        // Clean up
        $cache->clear();
        
    } catch (Exception $e) {
        echo "File cache test failed: " . $e->getMessage() . "\n";
    }
    
    // Clean up directory
    if (is_dir($tempDir)) {
        array_map('unlink', glob("$tempDir/*/*"));
        @rmdir($tempDir);
    }
    
    echo "File cache test completed.\n\n";
}

function test_cache_factory() {
    echo "=== Testing Cache Factory ===\n";
    
    $fileCache = CacheFactory::createFileCache();
    echo "File cache factory: " . ($fileCache->isEnabled() ? 'PASS' : 'FAIL') . "\n";
    
    $arrayCache = CacheFactory::createArrayCache();
    echo "Array cache factory: " . ($arrayCache->isEnabled() ? 'PASS' : 'FAIL') . "\n";
    
    $nullCache = CacheFactory::createNullCache();
    echo "Null cache factory: " . (!$nullCache->isEnabled() ? 'PASS' : 'FAIL') . "\n";
    
    echo "Cache factory test completed.\n\n";
}

// Run tests
test_array_cache();
test_cache_manager();
test_file_cache();
test_cache_factory();