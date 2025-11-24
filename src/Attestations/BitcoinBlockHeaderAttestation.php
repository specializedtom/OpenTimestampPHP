<?php
// src/Attestations/BitcoinBlockHeaderAttestation.php

namespace OpenTimestampsPHP\Attestations;

class BitcoinBlockHeaderAttestation extends BlockHeaderAttestation {
    protected string $blockchain = 'bitcoin';
    private array $explorers = [
        'https://blockstream.info/api',
        'https://blockchain.info',
        'https://api.blockcypher.com/v1/btc'
    ];

    public function __construct(int $height) {
        parent::__construct($height);
    }

    public function getType(): string {
        return 'bitcoin';
    }

    public function getBlockExplorerUrl(): string {
        return "https://blockstream.info/block/{$this->height}";
    }

    public function serialize(): string {
        return pack('C', 0x08) . $this->encodeVarint($this->height);
    }

    protected function fetchBlockHeader(int $height): array {
        foreach ($this->explorers as $explorer) {
            try {
                if (strpos($explorer, 'blockstream.info') !== false) {
                    return $this->fetchFromBlockstream($height, $explorer);
                } elseif (strpos($explorer, 'blockchain.info') !== false) {
                    return $this->fetchFromBlockchainInfo($height);
                } elseif (strpos($explorer, 'blockcypher.com') !== false) {
                    return $this->fetchFromBlockcypher($height);
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        throw new \Exception("All Bitcoin explorers failed for height $height");
    }

    private function fetchFromBlockstream(int $height, string $baseUrl): array {
        // Get block hash first
        $hashUrl = "$baseUrl/block-height/$height";
        $blockHash = file_get_contents($hashUrl);
        if (!$blockHash) {
            throw new \Exception("Failed to get block hash");
        }

        // Get block header
        $headerUrl = "$baseUrl/block/$blockHash/header";
        $headerHex = file_get_contents($headerUrl);
        if (!$headerHex) {
            throw new \Exception("Failed to get block header");
        }

        return [
            'hash' => $blockHash,
            'header_hex' => $headerHex,
            'height' => $height,
            'explorer' => 'blockstream'
        ];
    }

    private function fetchFromBlockchainInfo(int $height): array {
        $url = "https://blockchain.info/block-height/$height?format=json";
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        
        if (!isset($data['blocks'][0]['hash'])) {
            throw new \Exception("Block not found");
        }

        $block = $data['blocks'][0];
        return [
            'hash' => $block['hash'],
            'header_hex' => $this->constructHeaderFromBlockchainInfo($block),
            'height' => $height,
            'explorer' => 'blockchain.info'
        ];
    }

    private function fetchFromBlockcypher(int $height): array {
        $url = "https://api.blockcypher.com/v1/btc/main/blocks/$height";
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        
        if (!isset($data['hash'])) {
            throw new \Exception("Block not found");
        }

        return [
            'hash' => $data['hash'],
            'header_hex' => $data['header'],
            'height' => $height,
            'explorer' => 'blockcypher'
        ];
    }

    protected function verifyBlockHeader(string $message, array $blockHeader): bool {
        // In Bitcoin, the message should be in the coinbase transaction
        // For OpenTimestamps, it's typically in the Merkle root via an OP_RETURN
        // This is a simplified check - real implementation would parse the block
        $headerBinary = hex2bin($blockHeader['header_hex']);
        
        // Check if message appears in the header (simplified)
        // Real implementation would check the Merkle root and transactions
        return strpos($headerBinary, $message) !== false;
    }

    private function constructHeaderFromBlockchainInfo(array $block): string {
        // Construct block header from blockchain.info data
        $version = str_pad(dechex($block['version']), 8, '0', STR_PAD_LEFT);
        $prevHash = strrev(hex2bin($block['prev_block'])) ?: '';
        $prevHash = bin2hex($prevHash);
        $merkleRoot = strrev(hex2bin($block['mrkl_root'])) ?: '';
        $merkleRoot = bin2hex($merkleRoot);
        $timestamp = str_pad(dechex($block['time']), 8, '0', STR_PAD_LEFT);
        $bits = $block['bits'];
        $nonce = str_pad(dechex($block['nonce']), 8, '0', STR_PAD_LEFT);
        
        return $version . $prevHash . $merkleRoot . $timestamp . $bits . $nonce;
    }

    private function encodeVarint(int $value): string {
        if ($value < 0xFD) {
            return chr($value);
        } elseif ($value <= 0xFFFF) {
            return pack('Cv', 0xFD, $value);
        } elseif ($value <= 0xFFFFFFFF) {
            return pack('CV', 0xFE, $value);
        } else {
            return pack('CP', 0xFF, $value);
        }
    }

    public function __toString(): string {
        return "BitcoinBlockHeaderAttestation(height: {$this->height})";
    }
}