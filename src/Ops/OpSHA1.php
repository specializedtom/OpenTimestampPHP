<?php
// src/Ops/OpSHA1.php

namespace OpenTimestampsPHP\Ops;

use OpenTimestampsPHP\Core\AbstractOp;

class OpSHA1 extends AbstractOp {
    public function call(string $msg): string {
        return hash('sha1', $msg, true);
    }
    
    public function getTag(): int {
        return 0x02;
    }
}