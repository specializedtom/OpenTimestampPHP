<?php
// tests/calendar_test.php

require_once __DIR__ . '/../vendor/autoload.php';

use OpenTimestampsPHP\Client;
use OpenTimestampsPHP\File\FileHandler;

function test_calendar_submission() {
    echo "=== Testing Calendar Submission ===\n";
    
    // Create a test file
    $testContent = "Calendar integration test " . date('Y-m-d H:i:s');
    $testFile = sys_get_temp_dir() . '/calendar_test.txt';
    file_put_contents($testFile, $testContent);
    
    $client = new Client(['timeout' => 10]);
    
    try {
        // Create timestamp and submit to calendar
        $otsFile = $client->stamp($testFile, null, false);
        echo "Created OTS file: $otsFile\n";
        
        // Get info about the timestamp
        $info = $client->info($otsFile, true);
        echo "Timestamp info:\n";
        echo "  Type: " . $info['type'] . "\n";
        echo "  Operations: " . $info['timestamp_info']['operations_count'] . "\n";
        echo "  Attestations: " . $info['timestamp_info']['attestations_count'] . "\n";
        
        // Try to upgrade (may not work immediately)
        echo "Attempting upgrade...\n";
        $upgraded = $client->upgrade($otsFile);
        echo "Upgrade result: " . ($upgraded ? "SUCCESS" : "NO UPGRADE AVAILABLE") . "\n";
        
        // Verify the timestamp
        echo "Verifying timestamp...\n";
        $verification = $client->verify($otsFile, $testFile);
        
        // Clean up
        unlink($testFile);
        unlink($otsFile);
        
    } catch (Exception $e) {
        echo "Test failed: " . $e->getMessage() . "\n";
        // Clean up on failure
        if (file_exists($testFile)) unlink($testFile);
        if (isset($otsFile) && file_exists($otsFile)) unlink($otsFile);
    }
    
    echo "Calendar test completed.\n\n";
}

function test_calendar_info() {
    echo "=== Testing Calendar Info ===\n";
    
    $client = new Client();
    
    // Test with a known hash (this will likely fail, but tests the infrastructure)
    $testHash = hash('sha256', 'test', true);
    
    try {
        $info = $client->info(bin2hex($testHash));
        echo "Calendar info retrieved for " . count($info) . " servers\n";
    } catch (Exception $e) {
        echo "Calendar info test completed (expected to fail for unknown hash)\n";
    }
    
    echo "Calendar info test completed.\n\n";
}

// Run tests
test_calendar_submission();
test_calendar_info();