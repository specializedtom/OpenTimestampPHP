<?php
// tests/attestation_test.php

require_once __DIR__ . '/../vendor/autoload.php';

use OpenTimestampsPHP\Attestations\{
    BitcoinBlockHeaderAttestation,
    LitecoinBlockHeaderAttestation,
    PendingAttestation
};
use OpenTimestampsPHP\Verification\AttestationVerifier;
use OpenTimestampsPHP\Core\Timestamp;

function test_attestation_verification() {
    echo "=== Testing Attestation Verification ===\n";
    
    $verifier = new AttestationVerifier();
    
    // Create a test timestamp with multiple attestations
    $rootMessage = hash('sha256', 'test message', true);
    $timestamp = new Timestamp($rootMessage);
    
    // Add some attestations (using known block heights for testing)
    $timestamp->addAttestation(new BitcoinBlockHeaderAttestation(800000));
    $timestamp->addAttestation(new LitecoinBlockHeaderAttestation(2000000));
    $timestamp->addAttestation(new PendingAttestation('https://example.com/pending'));
    
    // Analyze attestations
    $analysis = $verifier->analyzeAttestations($timestamp, $rootMessage);
    echo "Attestation Analysis:\n";
    print_r($analysis);
    
    // Verify attestations
    $verification = $verifier->verifyTimestamp($timestamp, $rootMessage);
    echo "Verification Results:\n";
    echo "Overall valid: " . ($verification['valid'] ? 'YES' : 'NO') . "\n";
    echo "Verified: " . $verification['summary']['verified'] . "\n";
    echo "Pending: " . $verification['summary']['pending'] . "\n";
    echo "Failed: " . $verification['summary']['failed'] . "\n";
    
    if (!empty($verification['verified_attestations'])) {
        echo "\nVerified Attestations:\n";
        foreach ($verification['verified_attestations'] as $verified) {
            echo "  - {$verified['blockchain']} block {$verified['height']}\n";
        }
    }
    
    echo "Attestation verification test completed.\n\n";
}

function test_attestation_serialization() {
    echo "=== Testing Attestation Serialization ===\n";
    
    $attestations = [
        new BitcoinBlockHeaderAttestation(123456),
        new LitecoinBlockHeaderAttestation(987654),
        new PendingAttestation('https://pool.opentimestamps.org/calendar/abc123')
    ];
    
    foreach ($attestations as $attestation) {
        $serialized = $attestation->serialize();
        echo get_class($attestation) . " serialized to " . strlen($serialized) . " bytes\n";
        echo "Hex: " . bin2hex($serialized) . "\n\n";
    }
    
    echo "Attestation serialization test completed.\n\n";
}

// Run tests
test_attestation_verification();
test_attestation_serialization();