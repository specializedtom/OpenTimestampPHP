<?php
// tests/file_io_test.php

require_once __DIR__ . '/../vendor/autoload.php';

use OpenTimestampsPHP\File\{FileHandler, DetachedTimestampFile};
use OpenTimestampsPHP\Core\Timestamp;
use OpenTimestampsPHP\Attestations\BitcoinBlockHeaderAttestation;

function test_detached_file_io() {
    echo "Testing detached file I/O...\n";
    
    // Create a test file
    $testContent = "This is a test file for OpenTimestamps PHP";
    $testFile = sys_get_temp_dir() . '/test_document.txt';
    file_put_contents($testFile, $testContent);
    
    // Create detached timestamp
    $detachedFile = FileHandler::createDetachedForFile($testFile);
    $detachedFile->getTimestamp()->addAttestation(new BitcoinBlockHeaderAttestation(800000));
    
    // Write .ots file
    $otsFile = sys_get_temp_dir() . '/test_document.txt.ots';
    FileHandler::writeDetached($detachedFile, $otsFile);
    
    echo "Created OTS file: $otsFile\n";
    echo "File size: " . filesize($otsFile) . " bytes\n";
    
    // Read it back
    $readDetachedFile = FileHandler::readDetached($otsFile);
    echo "Read back: " . $readDetachedFile . "\n";
    
    // Verify against original
    $isValid = FileHandler::verifyDetached($readDetachedFile, $testFile);
    echo "Verification: " . ($isValid ? "PASS" : "FAIL") . "\n";
    
    // Clean up
    unlink($testFile);
    unlink($otsFile);
    
    echo "Detached file I/O test completed!\n\n";
}

function test_attached_file_io() {
    echo "Testing attached file I/O...\n";
    
    // Create test file
    $testContent = "This is a test file with attached timestamp";
    $testFile = sys_get_temp_dir() . '/test_attached.txt';
    file_put_contents($testFile, $testContent);
    
    // Create detached timestamp
    $detachedFile = FileHandler::createDetachedForFile($testFile);
    $detachedFile->getTimestamp()->addAttestation(new BitcoinBlockHeaderAttestation(800001));
    
    // Write attached file
    $attachedFile = sys_get_temp_dir() . '/test_attached.txt.otsed';
    FileHandler::writeAttached($detachedFile, $testFile, $attachedFile);
    
    echo "Created attached file: $attachedFile\n";
    echo "Original size: " . filesize($testFile) . " bytes\n";
    echo "Attached size: " . filesize($attachedFile) . " bytes\n";
    
    // Check if has attached timestamp
    $hasTimestamp = FileHandler::hasAttachedTimestamp($attachedFile);
    echo "Has attached timestamp: " . ($hasTimestamp ? "YES" : "NO") . "\n";
    
    // Read attached timestamp
    $readDetachedFile = FileHandler::readAttached($attachedFile);
    echo "Read from attached: " . $readDetachedFile . "\n";
    
    // Extract original
    $extractedFile = sys_get_temp_dir() . '/test_extracted.txt';
    FileHandler::extractOriginalFromAttached($attachedFile, $extractedFile);
    
    $extractedContent = file_get_contents($extractedFile);
    echo "Extracted content matches: " . ($extractedContent === $testContent ? "YES" : "NO") . "\n";
    
    // Clean up
    unlink($testFile);
    unlink($attachedFile);
    unlink($extractedFile);
    
    echo "Attached file I/O test completed!\n\n";
}

function test_client_integration() {
    echo "Testing client integration...\n";
    
    $client = new OpenTimestampsPHP\Client();
    
    // Create test file
    $testFile = sys_get_temp_dir() . '/client_test.txt';
    file_put_contents($testFile, "Client integration test");
    
    // Test info on regular file
    try {
        $info = $client->info($testFile);
        echo "Regular file info: " . json_encode($info) . "\n";
    } catch (\Exception $e) {
        echo "Expected error for regular file: " . $e->getMessage() . "\n";
    }
    
    // Clean up
    unlink($testFile);
    
    echo "Client integration test completed!\n";
}

// Run tests
test_detached_file_io();
test_attached_file_io();
test_client_integration();