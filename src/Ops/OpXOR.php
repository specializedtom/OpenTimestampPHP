<?php
// src/Ops/OpXOR.php

namespace OpenTimestampsPHP\Ops;

use OpenTimestampsPHP\Core\AbstractOp;

class OpXOR extends AbstractOp {
    private string $key;

    public function __construct(string $key) {
        $this->key = $key;
    }

    public function call(string $msg): string {
        $result = '';
        $keyLen = strlen($this->key);
        
        for ($i = 0; $i < strlen($msg); $i++) {
            $result .= $msg[$i] ^ $this->key[$i % $keyLen];
        }
        
        return $result;
    }
    
    public function getTag(): int {
        return 0x10;
    }
    
    public function serialize(): string {
        return pack('C', $this->getTag()) . 
               $this->encodeVarint(strlen($this->key)) . 
               $this->key;
    }
    
    public function getKey(): string {
        return $this->key;
    }
    
    public function __toString(): string {
        return 'xor:' . bin2hex($this->key);
    }
    
    private function encodeVarint(int $value): string {
        if ($value < 0xfd) {
            return chr($value);
        } elseif ($value <= 0xffff) {
            return pack('Cv', 0xfd, $value);
        } elseif ($value <= 0xffffffff) {
            return pack('CV', 0xfe, $value);
        } else {
            return pack('CP', 0xff, $value);
        }
    }
}