<?php
// src/Ops/OpAND.php

namespace OpenTimestampsPHP\Ops;

use OpenTimestampsPHP\Core\AbstractOp;

class OpAND extends AbstractOp {
    private string $mask;

    public function __construct(string $mask) {
        $this->mask = $mask;
    }

    public function call(string $msg): string {
        $result = '';
        $maskLen = strlen($this->mask);
        
        for ($i = 0; $i < strlen($msg); $i++) {
            $result .= $msg[$i] & $this->mask[$i % $maskLen];
        }
        
        return $result;
    }
    
    public function getTag(): int {
        return 0x11;
    }
    
    public function serialize(): string {
        return pack('C', $this->getTag()) . 
               $this->encodeVarint(strlen($this->mask)) . 
               $this->mask;
    }
    
    public function getMask(): string {
        return $this->mask;
    }
    
    public function __toString(): string {
        return 'and:' . bin2hex($this->mask);
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