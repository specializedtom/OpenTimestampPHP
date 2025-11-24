<?php
// src/Ops/OpSubstr.php

namespace OpenTimestampsPHP\Ops;

use OpenTimestampsPHP\Core\AbstractOp;

class OpSubstr extends AbstractOp {
    private int $start;
    private ?int $length;

    public function __construct(int $start, ?int $length = null) {
        $this->start = $start;
        $this->length = $length;
    }

    public function call(string $msg): string {
        if ($this->length === null) {
            return substr($msg, $this->start);
        }
        return substr($msg, $this->start, $this->length);
    }
    
    public function getTag(): int {
        return 0x0d;
    }
    
    public function serialize(): string {
        $data = pack('C', $this->getTag()) . 
                $this->encodeVarint($this->start);
        
        if ($this->length !== null) {
            $data .= $this->encodeVarint($this->length);
        } else {
            $data .= $this->encodeVarint(0xffffffff); // Special value for "to end"
        }
        
        return $data;
    }
    
    public function __toString(): string {
        if ($this->length === null) {
            return "substr:{$this->start}";
        }
        return "substr:{$this->start}:{$this->length}";
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