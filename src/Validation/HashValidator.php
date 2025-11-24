<?php
// src/Validation/HashValidator.php

namespace OpenTimestampsPHP\Validation;

class HashValidator extends AbstractValidator {
    private array $allowedAlgorithms = ['sha256', 'sha1', 'ripemd160'];
    private bool $requireBinary = false;

    public function __construct(array $options = []) {
        parent::__construct($options);
        
        $this->allowedAlgorithms = $options['allowed_algorithms'] ?? $this->allowedAlgorithms;
        $this->requireBinary = $options['require_binary'] ?? $this->requireBinary;
    }

    public function validate($value): bool {
        if (!is_string($value)) {
            $this->setError("Hash must be a string");
            return false;
        }

        // Check if it's a binary hash
        if ($this->requireBinary) {
            return $this->validateBinaryHash($value);
        }

        // Check if it's a hex hash
        if (ctype_xdigit($value)) {
            return $this->validateHexHash($value);
        }

        // Assume binary hash
        return $this->validateBinaryHash($value);
    }

    public function sanitize($value) {
        if (is_string($value) && ctype_xdigit($value)) {
            // It's a hex string, convert to binary if required
            if ($this->requireBinary) {
                return hex2bin($value);
            }
            return strtolower($value); // Normalize hex case
        }
        
        return $value;
    }

    private function validateBinaryHash(string $hash): bool {
        $length = strlen($hash);
        
        $validLengths = [
            'sha1' => 20,
            'sha256' => 32,
            'ripemd160' => 20
        ];

        foreach ($validLengths as $algo => $validLength) {
            if (in_array($algo, $this->allowedAlgorithms) && $length === $validLength) {
                return true;
            }
        }

        $this->setError(sprintf(
            "Invalid binary hash length: %d bytes. Expected: %s",
            $length,
            implode(', ', array_map(fn($a) => $validLengths[$a] . ' bytes for ' . $a, $this->allowedAlgorithms))
        ));
        return false;
    }

    private function validateHexHash(string $hash): bool {
        $length = strlen($hash);
        
        $validLengths = [
            'sha1' => 40,
            'sha256' => 64,
            'ripemd160' => 40
        ];

        foreach ($validLengths as $algo => $validLength) {
            if (in_array($algo, $this->allowedAlgorithms) && $length === $validLength) {
                return true;
            }
        }

        $this->setError(sprintf(
            "Invalid hex hash length: %d characters. Expected: %s",
            $length,
            implode(', ', array_map(fn($a) => $validLengths[$a] . ' chars for ' . $a, $this->allowedAlgorithms))
        ));
        return false;
    }
}