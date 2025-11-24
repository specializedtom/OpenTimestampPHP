<?php
// src/Ops/OpLeft.php

namespace OpenTimestampsPHP\Ops;

use OpenTimestampsPHP\Core\AbstractOp;

class OpLeft extends AbstractOp {
    private int $length;

    public function __construct(int $length) {
        $this->length = $length;
    }

    public function call(string $msg): string {
        return substr($msg, 0, $this->length);
    }
    
    public function getTag(): int {
        return 0x0e;
    }
    
    public function serialize(): string {
        return pack('C', $this->getTag()) . 
               $this->encodeVarint($this->length);
    }
    
    public function __toString(): string {
        return "left:{$this->length}";
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
