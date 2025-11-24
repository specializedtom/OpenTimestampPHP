<?php
// src/Attestations/Attestation.php

namespace OpenTimestampsPHP\Attestations;

interface Attestation {
    public function verify(string $message, array $context = []): array;
    public function serialize(): string;
    public function __toString(): string;
    public function getType(): string;
}