<?php
// src/Attestations/LitecoinBlockHeaderAttestation.php

namespace OpenTimestampsPHP\Attestations;

class LitecoinBlockHeaderAttestation extends BlockHeaderAttestation {
    protected string $blockchain = 'litecoin';
    private array $explorers = [
        'https://api.blockcypher.com/v1/ltc/main',
        'https://chainz.cryptoid.info/ltc/api.dws'
    ];

    public function __construct(int $height) {
        parent::__construct($height);
    }

    public function getType(): string {
        return 'litecoin';
    }

    public function getBlockExplorerUrl(): string {
        return "https://blockexplorer.one/litecoin/mainnet/block/$this->height";
    }

    public function serialize(): string {
        return pack('C', 0x30) . $this->encodeVarint($this->height);
    }

    protected function fetchBlockHeader(int $height): array {
        foreach ($this->explorers as $explorer) {
            try {
                if (strpos($explorer, 'blockcypher.com') !== false) {
                    return $this->fetchFromBlockcypher($height, $explorer);
                } elseif (strpos($explorer, 'cryptoid.info') !== false) {
                    return $this->fetchFromCryptoID($height);
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        throw new \Exception("All Litecoin explorers failed for height $height");
    }

    private function fetchFromBlockcypher(int $height, string $baseUrl): array {
        $url = "$baseUrl/blocks/$height";
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

    private function fetchFromCryptoID(int $height): array {
        // CryptoID API (may require API key in production)
        $url = "https://chainz.cryptoid.info/ltc/api.dws?q=getblockhash&height=$height";
        $blockHash = trim(file_get_contents($url), '"');
        
        if (!$blockHash || strlen($blockHash) !== 64) {
            throw new \Exception("Failed to get block hash");
        }

        $headerUrl = "https://chainz.cryptoid.info/ltc/api.dws?q=getblockheader&hash=$blockHash&hex=true";
        $headerHex = trim(file_get_contents($headerUrl), '"');
        
        if (!$headerHex) {
            throw new \Exception("Failed to get block header");
        }

        return [
            'hash' => $blockHash,
            'header_hex' => $headerHex,
            'height' => $height,
            'explorer' => 'cryptoid'
        ];
    }

    protected function verifyBlockHeader(string $message, array $blockHeader): bool {
        // Similar to Bitcoin verification
        $headerBinary = hex2bin($blockHeader['header_hex']);
        return strpos($headerBinary, $message) !== false;
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
        return "LitecoinBlockHeaderAttestation(height: {$this->height})";
    }
}