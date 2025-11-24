<?php
// src/Ops/OpAppend.php

namespace OpenTimestampsPHP\Ops;

use OpenTimestampsPHP\Core\AbstractOp;

class OpAppend extends AbstractOp {
    private string $data;

    public function __construct(string $data) {
        $this->data = $data;
    }

    public function call(string $msg): string {
        return $msg . $this->data;
    }
    
    public function getTag(): int {
        return 0xf0;
    }
    
    public function serialize(): string {
        return pack('C', $this->getTag()) . 
               $this->encodeVarint(strlen($this->data)) . 
               $this->data;
    }
    
    public function getData(): string {
        return $this->data;
    }
    
    public function __toString(): string {
        return 'append:' . bin2hex($this->data);
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
