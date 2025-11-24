<?php
// src/Validation/InputSanitizer.php

namespace OpenTimestampsPHP\Validation;

class InputSanitizer {
    private array $validators = [];

    public function __construct() {
        // Default validators
        $this->validators = [
            'file_path' => new FilePathValidator([
                'allowed_extensions' => ['ots', 'txt', 'pdf', 'doc', 'docx', 'jpg', 'png', 'zip'],
                'max_path_length' => 4096,
                'allow_relative_paths' => false,
                'base_directory' => getcwd()
            ]),
            'url' => new UrlValidator([
                'allowed_schemes' => ['https'],
                'allowed_hosts' => ['*.opentimestamps.org', '*.pool.opentimestamps.org'],
                'blocked_hosts' => ['localhost', '127.0.0.1', '::1'],
                'allow_ip_addresses' => false,
                'max_length' => 2000
            ]),
            'hash' => new HashValidator([
                'allowed_algorithms' => ['sha256', 'sha1', 'ripemd160'],
                'require_binary' => false
            ]),
            'timestamp' => new TimestampValidator([
                'max_depth' => 100,
                'max_operations' => 1000,
                'max_attestations' => 100,
                'check_circular_references' => true
            ])
        ];
    }

    /**
     * Validate and sanitize input based on type
     */
    public function sanitizeInput(string $type, $value, bool $throwException = true) {
        if (!isset($this->validators[$type])) {
            if ($throwException) {
                throw new \InvalidArgumentException("Unknown validator type: {$type}");
            }
            return $value;
        }

        $validator = $this->validators[$type];
        
        if (!$validator->validate($value)) {
            if ($throwException) {
                throw new \InvalidArgumentException(
                    "Input validation failed for type '{$type}': " . $validator->getError()
                );
            }
            return null;
        }

        return $validator->sanitize($value);
    }

    /**
     * Batch validate multiple inputs
     */
    public function validateMultiple(array $inputs): array {
        $results = [];
        $errors = [];

        foreach ($inputs as $key => $input) {
            $type = $input['type'] ?? 'string';
            $value = $input['value'] ?? null;
            $required = $input['required'] ?? false;

            if ($required && $value === null) {
                $errors[$key] = "Required field '{$key}' is missing";
                continue;
            }

            if ($value === null) {
                $results[$key] = null;
                continue;
            }

            try {
                $results[$key] = $this->sanitizeInput($type, $value, true);
            } catch (\InvalidArgumentException $e) {
                $errors[$key] = $e->getMessage();
            }
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(
                "Input validation errors: " . implode('; ', $errors)
            );
        }

        return $results;
    }

    /**
     * Add custom validator
     */
    public function addValidator(string $type, ValidatorInterface $validator): void {
        $this->validators[$type] = $validator;
    }

    /**
     * Get validator for type
     */
    public function getValidator(string $type): ?ValidatorInterface {
        return $this->validators[$type] ?? null;
    }

    /**
     * Quick validation methods
     */
    public function validateFilePath(string $path, bool $throw = true): ?string {
        return $this->sanitizeInput('file_path', $path, $throw);
    }

    public function validateUrl(string $url, bool $throw = true): ?string {
        return $this->sanitizeInput('url', $url, $throw);
    }

    public function validateHash($hash, bool $throw = true) {
        return $this->sanitizeInput('hash', $hash, $throw);
    }

    public function validateTimestamp($timestamp, bool $throw = true) {
        return $this->sanitizeInput('timestamp', $timestamp, $throw);
    }
}