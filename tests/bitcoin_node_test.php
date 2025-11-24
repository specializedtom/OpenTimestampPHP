<?php
// tests/bitcoin_node_test.php

require_once __DIR__ . '/../vendor/autoload.php';

use OpenTimestampsPHP\Blockchain\BitcoinNodeClient;
use OpenTimestampsPHP\Blockchain\BlockchainManager;

function test_bitcoin_node_connection() {
    echo "=== Testing Bitcoin Node Connection ===\n";
    
    $config = [
        'host' => '127.0.0.1',
        'port' => 8332,
        'username' => 'your_rpc_username',
        'password' => 'your_rpc_password',
        'timeout' => 10
    ];
    
    try {
        $node = new BitcoinNodeClient($config);
        
        if ($node->testConnection()) {
            echo "✓ Bitcoin node connection successful\n";
            
            // Get node status
            $status = $node->getNodeStatus();
            echo "  - Blocks: {$status['blocks']}\n";
            echo "  - Version: {$status['version']}\n";
            echo "  - Network: {$status['network']}\n";
            echo "  - Connections: {$status['connections']}\n";
        } else {
            echo "✗ Bitcoin node connection failed\n";
        }
        
    } catch (Exception $e) {
        echo "✗ Bitcoin node error: " . $e->getMessage() . "\n";
        echo "  Make sure Bitcoin Core is running with RPC enabled\n";
        echo "  and the credentials are correct.\n";
    }
    
    echo "Bitcoin node connection test completed.\n\n";
}

function test_blockchain_manager() {
    echo "=== Testing Blockchain Manager ===\n";
    
    $config = [
        'bitcoin_node' => [
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'your_rpc_username', 
            'password' => 'your_rpc_password',
            'prefer_node' => true
        ]
    ];
    
    $manager = new BlockchainManager($config);
    
    // Test blockchain status
    $status = $manager->getBlockchainStatus();
    echo "Blockchain status:\n";
    echo "  - Bitcoin node configured: " . ($status['bitcoin']['node_configured'] ? 'YES' : 'NO') . "\n";
    echo "  - Bitcoin node connected: " . ($status['bitcoin']['node_connected'] ? 'YES' : 'NO') . "\n";
    
    if ($status['bitcoin']['node_connected']) {
        // Test sync status
        $syncStatus = $manager->getSyncStatus();
        echo "  - Synced: " . ($syncStatus['synced'] ? 'YES' : 'NO') . "\n";
        echo "  - Progress: " . ($syncStatus['progress'] ?? 'N/A') . "%\n";
        echo "  - Blocks: " . ($syncStatus['blocks'] ?? 'N/A') . "\n";
        
        // Test attestation creation
        $attestation = $manager->createBitcoinAttestation(800000);
        echo "  - Created enhanced Bitcoin attestation: " . get_class($attestation) . "\n";
    }
    
    echo "Blockchain manager test completed.\n\n";
}

function test_verification_with_node() {
    echo "=== Testing Verification with Node ===\n";
    
    $config = [
        'bitcoin_node' => [
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'your_rpc_username',
            'password' => 'your_rpc_password'
        ]
    ];
    
    $manager = new BlockchainManager($config);
    
    if (!$manager->isBitcoinNodeAvailable()) {
        echo "Bitcoin node not available, skipping verification test\n";
        return;
    }
    
    // Test verification with a known block
    $message = hash('sha256', 'test message', true);
    $height = 800000; // Known block height
    
    try {
        $result = $manager->verifyBitcoinAttestation($message, $height);
        
        echo "Bitcoin attestation verification:\n";
        echo "  - Verified: " . ($result['verified'] ? 'YES' : 'NO') . "\n";
        echo "  - Method: " . ($result['method'] ?? 'unknown') . "\n";
        echo "  - Node used: " . ($result['node_used'] ? 'YES' : 'NO') . "\n";
        
        if (isset($result['merkle_proof'])) {
            echo "  - Merkle proof available: YES\n";
        }
        
    } catch (Exception $e) {
        echo "Verification failed: " . $e->getMessage() . "\n";
    }
    
    echo "Verification with node test completed.\n\n";
}

function test_node_configuration_examples() {
    echo "=== Node Configuration Examples ===\n";
    
    $examples = [
        'Local Bitcoin Core' => [
            'host' => '127.0.0.1',
            'port' => 8332,
            'username' => 'bitcoinrpc',
            'password' => 'your_password_here'
        ],
        'Remote Node with HTTPS' => [
            'host' => 'bitcoin.example.com',
            'port' => 443,
            'username' => 'rpc_user',
            'password' => 'rpc_password',
            'use_https' => true
        ],
        'Testnet Node' => [
            'host' => '127.0.0.1',
            'port' => 18332,
            'username' => 'testnetrpc',
            'password' => 'testnet_password'
        ]
    ];
    
    foreach ($examples as $name => $config) {
        echo "$name:\n";
        echo "  Host: {$config['host']}:{$config['port']}\n";
        echo "  HTTPS: " . ($config['use_https'] ?? false ? 'YES' : 'NO') . "\n";
        echo "\n";
    }
    
    echo "Configuration examples completed.\n\n";
}

// Run tests
test_bitcoin_node_connection();
test_blockchain_manager(); 
test_verification_with_node();
test_node_configuration_examples();