<?php
// src/Ops/OpUnHexlify.php

namespace OpenTimestampsPHP\Ops;

use OpenTimestampsPHP\Core\AbstractOp;

class OpUnHexlify extends AbstractOp {
    public function call(string $msg): string {
        // Convert binary to hex string, then interpret that as hex and convert back
        // This should halve the size of the message if it was hex-encoded
        $hex = bin2hex($msg);
        if (strlen($hex) % 2 !== 0) {
            throw new \InvalidArgumentException("Message length must be even for unhexlify");
        }
        return hex2bin($hex);
    }
    
    public function getTag(): int {
        return 0x0c;
    }
    
    public function __toString(): string {
        return 'unhexlify';
    }
}