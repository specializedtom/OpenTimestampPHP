<?php
// src/Ops/OpHexlify.php

namespace OpenTimestampsPHP\Ops;

use OpenTimestampsPHP\Core\AbstractOp;

class OpHexlify extends AbstractOp {
    public function call(string $msg): string {
        // Convert binary to hex string, then convert that hex string to binary
        // This effectively doubles the size of the message
        $hex = bin2hex($msg);
        return hex2bin($hex);
    }
    
    public function getTag(): int {
        return 0x0b;
    }
    
    public function __toString(): string {
        return 'hexlify';
    }
}