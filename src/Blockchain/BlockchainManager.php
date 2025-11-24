<?php
// src/Blockchain/BlockchainManager.php

namespace OpenTimestampsPHP\Blockchain;

use OpenTimestampsPHP\Attestations\EnhancedBitcoinAttestation;

class BlockchainManager {
    private ?BitcoinNodeClient $bitcoinNode = null;
    private array $config;
    private array $nodeStatus = [];

    public function __construct(array $config = []) {
        $this->config = $config;
        
        // Initialize Bitcoin node if configured
        if (isset($config['bitcoin_node'])) {
            $this->bitcoinNode = new BitcoinNodeClient($config['bitcoin_node']);
        }
    }

    /**
     * Get enhanced Bitcoin attestation with node support
     */
    public function createBitcoinAttestation(int $height): EnhancedBitcoinAttestation {
        $attestation = new EnhancedBitcoinAttestation($height);
        
        if ($this->bitcoinNode) {
            $attestation->setNodeClient($this->bitcoinNode);
            $attestation->setPreferNode(
                $this->config['bitcoin_node']['prefer_node'] ?? true
            );
        }
        
        return $attestation;
    }

    /**
     * Verify a message against a Bitcoin block
     */
    public function verifyBitcoinAttestation(string $message, int $height): array {
        $attestation = $this->createBitcoinAttestation($height);
        
        try {
            $blockHeader = $attestation->fetchBlockHeader($height);
            $verification = $attestation->verifyWithMerkleProof($message, $blockHeader);
            
            $verification['block_header'] = $blockHeader;
            $verification['node_used'] = $this->bitcoinNode !== null;
            
            return $verification;
        } catch (\Exception $e) {
            return [
                'verified' => false,
                'method' => 'failed',
                'error' => $e->getMessage(),
                'node_used' => $this->bitcoinNode !== null
            ];
        }
    }

    /**
     * Get blockchain status
     */
    public function getBlockchainStatus(): array {
        $status = [
            'bitcoin' => [
                'node_configured' => $this->bitcoinNode !== null,
                'node_connected' => false,
                'status' => 'unknown'
            ]
        ];

        if ($this->bitcoinNode) {
            try {
                $nodeStatus = $this->bitcoinNode->getNodeStatus();
                $status['bitcoin']['node_connected'] = true;
                $status['bitcoin']['status'] = 'connected';
                $status['bitcoin']['details'] = $nodeStatus;
                $this->nodeStatus['bitcoin'] = $nodeStatus;
            } catch (BitcoinNodeException $e) {
                $status['bitcoin']['status'] = 'error';
                $status['bitcoin']['error'] = $e->getMessage();
            }
        }

        return $status;
    }

    /**
     * Batch verify multiple attestations
     */
    public function batchVerifyBitcoinAttestations(array $attestations): array {
        $results = [];
        
        foreach ($attestations as $key => $attestation) {
            $message = $attestation['message'];
            $height = $attestation['height'];
            
            $results[$key] = $this->verifyBitcoinAttestation($message, $height);
        }

        return $results;
    }

    /**
     * Get Bitcoin node client
     */
    public function getBitcoinNode(): ?BitcoinNodeClient {
        return $this->bitcoinNode;
    }

    /**
     * Check if Bitcoin node is available and connected
     */
    public function isBitcoinNodeAvailable(): bool {
        if (!$this->bitcoinNode) {
            return false;
        }

        try {
            return $this->bitcoinNode->testConnection();
        } catch (BitcoinNodeException $e) {
            return false;
        }
    }

    /**
     * Get recommended verification method
     */
    public function getRecommendedVerificationMethod(): string {
        if ($this->isBitcoinNodeAvailable()) {
            return 'node';
        }
        return 'explorer';
    }

    /**
     * Get synchronization status
     */
    public function getSyncStatus(): array {
        if (!$this->bitcoinNode) {
            return ['synced' => false, 'reason' => 'no_node_configured'];
        }

        try {
            $info = $this->bitcoinNode->getBlockchainInfo();
            
            $synced = $info['blocks'] === $info['headers'];
            $progress = $synced ? 100 : ($info['blocks'] / $info['headers'] * 100);
            
            return [
                'synced' => $synced,
                'progress' => round($progress, 2),
                'blocks' => $info['blocks'],
                'headers' => $info['headers'],
                'verification_blocks' => $info['blocks'],
                'initial_block_download' => $info['initialblockdownload'] ?? false
            ];
        } catch (BitcoinNodeException $e) {
            return ['synced' => false, 'reason' => 'node_error', 'error' => $e->getMessage()];
        }
    }
}