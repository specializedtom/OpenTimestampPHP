<?php
// src/Attestations/BlockHeaderAttestation.php

namespace OpenTimestampsPHP\Attestations;

abstract class BlockHeaderAttestation implements Attestation {
    protected int $height;
    protected string $blockchain;
    
    public function __construct(int $height) {
        $this->height = $height;
    }

    public function getHeight(): int {
        return $this->height;
    }

    public function getBlockchain(): string {
        return $this->blockchain;
    }

    abstract public function getBlockExplorerUrl(): string;
    abstract protected function fetchBlockHeader(int $height): array;
    abstract protected function verifyBlockHeader(string $message, array $blockHeader): bool;

    public function verify(string $message, array $context = []): array {
        $result = [
            'valid' => false,
            'verified' => false,
            'blockchain' => $this->blockchain,
            'height' => $this->height,
            'error' => null,
            'block_data' => null
        ];

        try {
            $blockHeader = $this->fetchBlockHeader($this->height);
            $result['block_data'] = $blockHeader;
            
            if ($this->verifyBlockHeader($message, $blockHeader)) {
                $result['valid'] = true;
                $result['verified'] = true;
            } else {
                $result['error'] = 'Message not found in block header';
            }
            
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }
}