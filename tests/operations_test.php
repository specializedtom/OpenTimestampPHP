<?php
// tests/operations_test.php

require_once __DIR__ . '/../vendor/autoload.php';

use OpenTimestampsPHP\Ops\{
    OpSHA1, OpSHA256, OpRIPEMD160, OpKECCAK256,
    OpAppend, OpPrepend, OpReverse, OpHexlify, OpUnHexlify,
    OpSubstr, OpLeft, OpRight, OpXOR, OpAND, OpOR,
    OperationFactory
};
use OpenTimestampsPHP\Serialization\{
    EnhancedSerializer, EnhancedDeserializer, Stream
};

function test_basic_operations() {
    echo "=== Testing Basic Operations ===\n";
    
    $testMessage = "Hello, OpenTimestamps!";
    
    $operations = [
        new OpSHA1(),
        new OpSHA256(),
        new OpRIPEMD160(),
        new OpReverse(),
        new OpHexlify(),
    ];
    
    foreach ($operations as $op) {
        $result = $op->call($testMessage);
        echo get_class($op) . ": " . bin2hex(substr($result, 0, 16)) . "...\n";
    }
    
    echo "Basic operations test completed.\n\n";
}

function test_data_operations() {
    echo "=== Testing Data Operations ===\n";
    
    $testMessage = "test";
    
    // Append
    $appendOp = new OpAppend("123");
    $result = $appendOp->call($testMessage);
    echo "Append: '$testMessage' -> '" . $result . "'\n";
    
    // Prepend  
    $prependOp = new OpPrepend("ABC");
    $result = $prependOp->call($testMessage);
    echo "Prepend: '$testMessage' -> '" . $result . "'\n";
    
    // Substring
    $substrOp = new OpSubstr(1, 2);
    $result = $substrOp->call($testMessage);
    echo "Substr(1,2): '$testMessage' -> '" . $result . "'\n";
    
    echo "Data operations test completed.\n\n";
}

function test_bitwise_operations() {
    echo "=== Testing Bitwise Operations ===\n";
    
    $testMessage = "abcd";
    
    // XOR
    $xorOp = new OpXOR("\x01\x01\x01\x01");
    $result = $xorOp->call($testMessage);
    echo "XOR: " . bin2hex($testMessage) . " -> " . bin2hex($result) . "\n";
    
    // AND
    $andOp = new OpAND("\xF0\xF0\xF0\xF0");
    $result = $andOp->call($testMessage);
    echo "AND: " . bin2hex($testMessage) . " -> " . bin2hex($result) . "\n";
    
    echo "Bitwise operations test completed.\n\n";
}

function test_serialization_roundtrip() {
    echo "=== Testing Serialization Round-Trip ===\n";
    
    $operations = [
        new OpSHA256(),
        new OpAppend("test_data"),
        new OpPrepend("prefix_"),
        new OpSubstr(2, 5),
        new OpXOR("\xAA\xBB\xCC"),
    ];
    
    $serializer = new EnhancedSerializer();
    $successCount = 0;
    
    foreach ($operations as $originalOp) {
        try {
            // Serialize
            $serializer->serializeOp($originalOp);
            $serialized = $serializer->getData();
            
            // Deserialize
            $stream = new Stream($serialized);
            $deserializedOp = EnhancedDeserializer::deserializeOp($stream);
            
            // Test equivalence
            $testData = "sample input";
            $originalResult = $originalOp->call($testData);
            $deserializedResult = $deserializedOp->call($testData);
            
            if ($originalResult === $deserializedResult) {
                $successCount++;
                echo "✓ " . get_class($originalOp) . " round-trip successful\n";
            } else {
                echo "✗ " . get_class($originalOp) . " round-trip failed\n";
            }
            
        } catch (Exception $e) {
            echo "✗ " . get_class($originalOp) . " error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "Serialization round-trip: $successCount/" . count($operations) . " successful\n\n";
}

function test_operation_factory() {
    echo "=== Testing Operation Factory ===\n";
    
    $testCases = [
        'sha256',
        'append:74657374', // 'test' in hex
        'prepend:707265666978', // 'prefix' in hex
        'substr:2:5',
        'left:10',
        'xor:aabbcc',
    ];
    
    foreach ($testCases as $opString) {
        try {
            $op = OperationFactory::createFromString($opString);
            echo "✓ Created: $opString -> " . get_class($op) . "\n";
        } catch (Exception $e) {
            echo "✗ Failed: $opString -> " . $e->getMessage() . "\n";
        }
    }
    
    echo "Operation factory test completed.\n\n";
}

function test_complex_operation_chain() {
    echo "=== Testing Complex Operation Chain ===\n";
    
    $originalMessage = "Original document content";
    
    // Create a complex chain of operations
    $operations = [
        new OpSHA256(),
        new OpAppend("extra"),
        new OpPrepend("prefix_"),
        new OpSHA1(),
        new OpReverse(),
        new OpHexlify(),
    ];
    
    $currentMessage = $originalMessage;
    echo "Starting message: '$originalMessage'\n";
    
    foreach ($operations as $i => $op) {
        $currentMessage = $op->call($currentMessage);
        echo "Step $i (" . get_class($op) . "): " . bin2hex(substr($currentMessage, 0, 16)) . "...\n";
    }
    
    echo "Final result length: " . strlen($currentMessage) . " bytes\n";
    echo "Complex operation chain test completed.\n\n";
}

// Run all tests
test_basic_operations();
test_data_operations();
test_bitwise_operations();
test_serialization_roundtrip();
test_operation_factory();
test_complex_operation_chain();