<?php
// src/Ops/OpReverse.php

namespace OpenTimestampsPHP\Ops;

use OpenTimestampsPHP\Core\AbstractOp;

class OpReverse extends AbstractOp {
    public function call(string $msg): string {
        return strrev($msg);
    }
    
    public function getTag(): int {
        return 0x0a;
    }
    
    public function __toString(): string {
        return 'reverse';
    }
}