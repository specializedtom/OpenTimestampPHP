<?php
// src/Validation/SecurityManager.php

namespace OpenTimestampsPHP\Validation;

class SecurityManager {
    private InputSanitizer $sanitizer;
    private array $securityConfig;

    public function __construct(array $config = []) {
        $this->sanitizer = new InputSanitizer();
        $this->securityConfig = array_merge([
            'max_file_size' => 100 * 1024 * 1024, // 100MB
            'allowed_protocols' => ['https'],
            'disable_functions' => ['exec', 'system', 'passthru', 'shell_exec'],
            'enable_ssrf_protection' => true,
            'enable_xss_protection' => true
        ], $config);
    }

    /**
     * Validate HTTP request for SSRF protection
     */
    public function validateHttpRequest(string $url, array $contextOptions = []): array {
        if ($this->securityConfig['enable_ssrf_protection']) {
            $this->validateForSsrf($url, $contextOptions);
        }

        // Sanitize URL
        $url = $this->sanitizer->validateUrl($url);

        // Set secure context options
        $secureOptions = $this->getSecureContextOptions($contextOptions);

        return [$url, $secureOptions];
    }

    /**
     * Validate file upload
     */
    public function validateFileUpload(string $filePath, int $maxSize = null): string {
        $maxSize = $maxSize ?? $this->securityConfig['max_file_size'];
        
        // Validate file path
        $filePath = $this->sanitizer->validateFilePath($filePath);

        // Check file size
        $fileSize = filesize($filePath);
        if ($fileSize === false) {
            throw new \InvalidArgumentException("Cannot determine file size");
        }

        if ($fileSize > $maxSize) {
            throw new \InvalidArgumentException(
                "File size {$fileSize} exceeds maximum {$maxSize} bytes"
            );
        }

        // Check file type (basic MIME validation)
        $mimeType = mime_content_type($filePath);
        if ($mimeType === false) {
            throw new \InvalidArgumentException("Cannot determine file type");
        }

        $allowedMimeTypes = [
            'text/plain',
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/zip',
            'application/octet-stream'
        ];

        if (!in_array($mimeType, $allowedMimeTypes)) {
            throw new \InvalidArgumentException("File type '{$mimeType}' is not allowed");
        }

        return $filePath;
    }

    /**
     * Validate and sanitize user input for XSS protection
     */
    public function sanitizeUserInput($input, string $type = 'string') {
        switch ($type) {
            case 'string':
                return $this->sanitizeString($input);
            case 'integer':
                return $this->sanitizeInteger($input);
            case 'float':
                return $this->sanitizeFloat($input);
            case 'boolean':
                return $this->sanitizeBoolean($input);
            case 'array':
                return $this->sanitizeArray($input);
            default:
                return $input;
        }
    }

    private function validateForSsrf(string $url, array $contextOptions): void {
        $parts = parse_url($url);
        
        // Check for IP addresses in URL
        if (isset($parts['host']) && filter_var($parts['host'], FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException("IP addresses in URLs are not allowed for security reasons");
        }

        // Check for authentication in URL
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException("URL authentication is not allowed");
        }

        // Check context options for security issues
        $dangerousOptions = ['proxy', 'header', 'user_agent'];
        foreach ($dangerousOptions as $option) {
            if (isset($contextOptions[$option])) {
                throw new \InvalidArgumentException("Dangerous context option '{$option}' is not allowed");
            }
        }
    }

    private function getSecureContextOptions(array $customOptions = []): array {
        $defaultOptions = [
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
                'follow_location' => 0, // Disable redirects for security
                'max_redirects' => 0,
                'user_agent' => 'OpenTimestampsPHP/Secure'
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'disable_compression' => true,
                'peer_name' => '' // Will be set to actual host
            ]
        ];

        return array_merge_recursive($defaultOptions, $customOptions);
    }

    private function sanitizeString($input): string {
        if (!is_scalar($input) && !method_exists($input, '__toString')) {
            throw new \InvalidArgumentException("Input cannot be converted to string");
        }

        $string = (string) $input;

        // Remove null bytes
        $string = str_replace("\0", '', $string);

        // Basic XSS protection
        $string = htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove control characters (except newline, tab, carriage return)
        $string = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $string);

        return trim($string);
    }

    private function sanitizeInteger($input): int {
        if (!is_numeric($input)) {
            throw new \InvalidArgumentException("Input is not a valid integer");
        }

        $int = (int) $input;

        // Check for integer overflow
        if ((string) $int !== (string) $input) {
            throw new \InvalidArgumentException("Integer value is out of range");
        }

        return $int;
    }

    private function sanitizeFloat($input): float {
        if (!is_numeric($input)) {
            throw new \InvalidArgumentException("Input is not a valid float");
        }

        return (float) $input;
    }

    private function sanitizeBoolean($input): bool {
        if (is_bool($input)) {
            return $input;
        }

        if (is_numeric($input)) {
            return (bool) $input;
        }

        if (is_string($input)) {
            $lower = strtolower($input);
            if (in_array($lower, ['true', '1', 'yes', 'on'])) {
                return true;
            }
            if (in_array($lower, ['false', '0', 'no', 'off', ''])) {
                return false;
            }
        }

        throw new \InvalidArgumentException("Input is not a valid boolean");
    }

    private function sanitizeArray($input): array {
        if (!is_array($input)) {
            throw new \InvalidArgumentException("Input is not an array");
        }

        $sanitized = [];
        foreach ($input as $key => $value) {
            $sanitizedKey = $this->sanitizeString($key);
            $sanitized[$sanitizedKey] = is_array($value) ? $this->sanitizeArray($value) : $this->sanitizeString($value);
        }

        return $sanitized;
    }

    /**
     * Get security report
     */
    public function getSecurityReport(): array {
        return [
            'ssrf_protection' => $this->securityConfig['enable_ssrf_protection'],
            'xss_protection' => $this->securityConfig['enable_xss_protection'],
            'max_file_size' => $this->securityConfig['max_file_size'],
            'allowed_protocols' => $this->securityConfig['allowed_protocols'],
            'disabled_functions' => $this->securityConfig['disable_functions'],
            'php_version' => PHP_VERSION,
            'safe_mode' => ini_get('safe_mode'),
            'open_basedir' => ini_get('open_basedir')
        ];
    }
}