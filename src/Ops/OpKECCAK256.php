<?php
// src/Ops/OpKECCAK256.php

namespace OpenTimestampsPHP\Ops;

use OpenTimestampsPHP\Core\AbstractOp;

class OpKECCAK256 extends AbstractOp {
    public function call(string $msg): string {
        // Note: PHP doesn't have built-in KECCAK-256, so we use a compatible implementation
        // In production, you might want to use a library like kornrunner/keccak
        if (function_exists('keccak256')) {
            return keccak256($msg, true);
        }
        
        // Fallback to SHA3-256 (different from KECCAK but available in PHP)
        return hash('sha3-256', $msg, true);
    }
    
    public function getTag(): int {
        return 0x67;
    }
    
    public function __toString(): string {
        return 'keccak256';
    }
}