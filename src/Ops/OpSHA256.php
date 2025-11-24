<?php
// src/Ops/OpSHA256.php

namespace OpenTimestampsPHP\Ops;

use OpenTimestampsPHP\Core\AbstractOp;

class OpSHA256 extends AbstractOp {
    public function call(string $msg): string {
        return hash('sha256', $msg, true);
    }
    
    public function getTag(): int {
        return 0x08;
    }
}