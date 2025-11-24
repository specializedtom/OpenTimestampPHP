<?php
// src/Core/AbstractOp.php

namespace OpenTimestampsPHP\Core;

abstract class AbstractOp implements Op {
    abstract public function getTag(): int;
    
    public function serialize(): string {
        return pack('C', $this->getTag());
    }
    
    public function __toString(): string {
        $className = (new \ReflectionClass($this))->getShortName();
        return strtolower(substr($className, 2)); // Remove "Op" prefix
    }
}