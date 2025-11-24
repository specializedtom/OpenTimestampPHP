<?php
// src/Ops/OpRIPEMD160.php

namespace OpenTimestampsPHP\Ops;

use OpenTimestampsPHP\Core\AbstractOp;

class OpRIPEMD160 extends AbstractOp {
    public function call(string $msg): string {
        return hash('ripemd160', $msg, true);
    }
    
    public function getTag(): int {
        return 0x03;
    }
}