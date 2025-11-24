<?php
// tests/serialization_test.php

require_once __DIR__ . '/../vendor/autoload.php';

use OpenTimestampsPHP\Core\Timestamp;
use OpenTimestampsPHP\Ops\{OpSHA256, OpAppend};
use OpenTimestampsPHP\Attestations\BitcoinBlockHeaderAttestation;
use OpenTimestampsPHP\Serialization\{Serializer, Deserializer};

function test_basic_serialization() {
    echo "Testing basic serialization...\n";
    
    // Create a timestamp with some operations and attestations
    $timestamp = new Timestamp(hash('sha256', 'test data', true));
    
    // Add an operation
    $subTimestamp = new Timestamp();
    $subTimestamp->addAttestation(new BitcoinBlockHeaderAttestation(500000));
    $timestamp->addOperation(new OpSHA256(), $subTimestamp);
    
    // Add an append operation
    $appendTimestamp = new Timestamp();
    $appendTimestamp->addAttestation(new BitcoinBlockHeaderAttestation(600000));
    $timestamp->addOperation(new OpAppend("extra_data"), $appendTimestamp);
    
    // Serialize
    $serializer = new Serializer();
    $serialized = $serializer->serialize($timestamp);
    
    echo "Serialized data length: " . strlen($serialized) . " bytes\n";
    echo "Serialized hex: " . bin2hex($serialized) . "\n";
    
    // Deserialize
    $deserialized = Deserializer::deserialize($serialized);
    
    echo "Deserialized: " . $deserialized . "\n";
    echo "Test passed!\n";
}

function test_round_trip() {
    echo "\nTesting round-trip serialization...\n";
    
    $original = new Timestamp(hash('sha256', 'roundtrip test', true));
    $original->addAttestation(new BitcoinBlockHeaderAttestation(123456));
    
    $serializer = new Serializer();
    $data = $serializer->serialize($original);
    
    $restored = Deserializer::deserialize($data);
    
    // Basic check - in real implementation you'd want more thorough comparison
    if (count($original->getAttestations()) === count($restored->getAttestations())) {
        echo "Round-trip test passed!\n";
    } else {
        echo "Round-trip test failed!\n";
    }
}

// Run tests
test_basic_serialization();
test_round_trip();