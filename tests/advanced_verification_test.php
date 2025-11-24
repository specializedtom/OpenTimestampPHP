<?php
// tests/advanced_verification_test.php

require_once __DIR__ . '/../vendor/autoload.php';

use OpenTimestampsPHP\Verification\{
    AdvancedTimestampVerifier,
    BatchVerifier,
    MerklePathValidator
};
use OpenTimestampsPHP\Core\Timestamp;
use OpenTimestampsPHP\Attestations\{
    BitcoinBlockHeaderAttestation,
    LitecoinBlockHeaderAttestation,
    PendingAttestation
};

function test_advanced_verification() {
    echo "=== Testing Advanced Verification ===\n";
    
    $verifier = new AdvancedTimestampVerifier();
    
    // Create a complex timestamp for testing
    $rootMessage = hash('sha256', 'advanced verification test', true);
    $timestamp = new Timestamp($rootMessage);
    
    // Add multiple attestations
    $timestamp->addAttestation(new BitcoinBlockHeaderAttestation(800000));
    $timestamp->addAttestation(new LitecoinBlockHeaderAttestation(2000000));
    $timestamp->addAttestation(new PendingAttestation('https://example.com/pending'));
    
    // Perform comprehensive verification
    $result = $verifier->verifyComprehensive($timestamp, $rootMessage);
    
    echo "Comprehensive Verification Result:\n";
    echo "Overall Valid: " . ($result['overall_valid'] ? 'YES' : 'NO') . "\n";
    echo "Security Level: " . ($result['security_assessment']['security_level'] ?? 'unknown') . "\n";
    echo "Confidence Score: " . ($result['components']['consensus']['confidence_score'] ?? 0) . "\n";
    
    if (!empty($result['recommendations'])) {
        echo "\nRecommendations:\n";
        foreach ($result['recommendations'] as $rec) {
            echo "  - $rec\n";
        }
    }
    
    echo "Advanced verification test completed.\n\n";
}

function test_batch_verification() {
    echo "=== Testing Batch Verification ===\n";
    
    $batchVerifier = new BatchVerifier();
    
    $tasks = [];
    for ($i = 0; $i < 3; $i++) {
        $message = "batch test message $i";
        $timestamp = new Timestamp(hash('sha256', $message, true));
        $timestamp->addAttestation(new BitcoinBlockHeaderAttestation(800000 + $i));
        
        $tasks["task_$i"] = [
            'timestamp' => $timestamp,
            'original_message' => hash('sha256', $message, true),
            'description' => "Test timestamp $i",
            'verification_options' => []
        ];
    }
    
    $batchResult = $batchVerifier->verifyBatch($tasks);
    
    echo "Batch Verification Completed:\n";
    echo "Total: {$batchResult['total_tasks']}\n";
    echo "Successful: {$batchResult['successful_verifications']}\n";
    echo "Failed: {$batchResult['failed_verifications']}\n";
    echo "Success Rate: " . number_format($batchResult['summary_statistics']['success_rate'], 1) . "%\n";
    
    // Generate report
    $report = $batchVerifier->generateReport($batchResult);
    file_put_contents('batch_verification_report.txt', $report);
    echo "Batch report saved to: batch_verification_report.txt\n";
    
    echo "Batch verification test completed.\n\n";
}

function test_merkle_analysis() {
    echo "=== Testing Merkle Analysis ===\n";
    
    $validator = new MerklePathValidator();
    
    $message = hash('sha256', 'merkle analysis test', true);
    $timestamp = new Timestamp($message);
    $timestamp->addAttestation(new BitcoinBlockHeaderAttestation(800000));
    
    $analysis = $validator->analyzeMerkleStructure($timestamp, $message);
    
    echo "Merkle Structure Analysis:\n";
    echo "Tree Depth: {$analysis['tree_depth']}\n";
    echo "Root Attestations: {$analysis['root_attestations_count']}\n";
    echo "Unique Paths: {$analysis['unique_paths_count']}\n";
    echo "Path Redundancy: {$analysis['path_redundancy']}\n";
    
    if (!empty($analysis['security_indicators'])) {
        echo "Security Indicators: " . implode(', ', $analysis['security_indicators']) . "\n";
    }
    
    echo "Merkle analysis test completed.\n\n";
}

// Run tests
test_advanced_verification();
test_batch_verification();
test_merkle_analysis();