<?php
// src/Core/Op.php

namespace OpenTimestampsPHP\Core;

interface Op {
    public function call(string $msg): string;
    public function serialize(): string;
    public function __toString(): string;
    public function getTag(): int;
}