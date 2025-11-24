<?php
// src/Validation/UrlValidator.php

namespace OpenTimestampsPHP\Validation;

class UrlValidator extends AbstractValidator {
    private array $allowedSchemes = ['https'];
    private array $allowedHosts = [];
    private array $blockedHosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];
    private bool $allowIpAddresses = false;
    private int $maxLength = 2000;

    public function __construct(array $options = []) {
        parent::__construct($options);
        
        $this->allowedSchemes = $options['allowed_schemes'] ?? $this->allowedSchemes;
        $this->allowedHosts = $options['allowed_hosts'] ?? $this->allowedHosts;
        $this->blockedHosts = $options['blocked_hosts'] ?? $this->blockedHosts;
        $this->allowIpAddresses = $options['allow_ip_addresses'] ?? $this->allowIpAddresses;
        $this->maxLength = $options['max_length'] ?? $this->maxLength;
    }

    public function validate($value): bool {
        if (!is_string($value)) {
            $this->setError("URL must be a string");
            return false;
        }

        $url = $this->sanitize($value);

        // Check length
        if (strlen($url) > $this->maxLength) {
            $this->setError("URL too long (max: {$this->maxLength} characters)");
            return false;
        }

        // Parse URL
        $parts = parse_url($url);
        if ($parts === false) {
            $this->setError("Invalid URL format");
            return false;
        }

        // Check scheme
        if (!isset($parts['scheme']) || !in_array($parts['scheme'], $this->allowedSchemes)) {
            $this->setError("URL scheme must be one of: " . implode(', ', $this->allowedSchemes));
            return false;
        }

        // Check host
        if (!isset($parts['host'])) {
            $this->setError("URL must contain a host");
            return false;
        }

        // Validate host
        if (!$this->validateHost($parts['host'])) {
            return false;
        }

        // Check for SSRF vulnerabilities
        if (!$this->validateForSsrf($parts)) {
            return false;
        }

        return true;
    }

    public function sanitize($value): string {
        $url = (string) $value;
        
        // Remove null bytes
        $url = str_replace("\0", '', $url);
        
        // Trim whitespace
        $url = trim($url);
        
        // Ensure proper URL encoding
        $url = filter_var($url, FILTER_SANITIZE_URL);
        
        return $url;
    }

    private function validateHost(string $host): bool {
        // Check against blocked hosts
        if (in_array(strtolower($host), $this->blockedHosts)) {
            $this->setError("Access to host '{$host}' is blocked");
            return false;
        }

        // Check if host is an IP address
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!$this->allowIpAddresses) {
                $this->setError("IP addresses are not allowed in URLs");
                return false;
            }

            // Check for private IP ranges
            if (!$this->isPublicIp($host)) {
                $this->setError("Private IP addresses are not allowed");
                return false;
            }
        } else {
            // Validate domain name
            if (!preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i', $host)) {
                $this->setError("Invalid domain name: {$host}");
                return false;
            }
        }

        // Check allowed hosts if specified
        if (!empty($this->allowedHosts) && !$this->isHostAllowed($host)) {
            $this->setError("Host '{$host}' is not in allowed list");
            return false;
        }

        return true;
    }

    private function validateForSsrf(array $urlParts): bool {
        // Check for URL encoding tricks
        $decodedHost = urldecode($urlParts['host'] ?? '');
        if ($decodedHost !== ($urlParts['host'] ?? '')) {
            $this->setError("URL encoding in hostname is not allowed");
            return false;
        }

        // Check for authentication in URL
        if (isset($urlParts['user']) || isset($urlParts['pass'])) {
            $this->setError("URL authentication credentials are not allowed");
            return false;
        }

        // Check for suspicious ports
        if (isset($urlParts['port'])) {
            $port = (int) $urlParts['port'];
            $suspiciousPorts = [22, 23, 25, 53, 110, 135, 139, 143, 443, 445, 993, 995, 1433, 1521, 3306, 3389, 5432];
            if (in_array($port, $suspiciousPorts)) {
                $this->setError("Suspicious port {$port} is not allowed");
                return false;
            }
        }

        return true;
    }

    private function isPublicIp(string $ip): bool {
        // Private IP ranges
        $privateRanges = [
            '10.0.0.0/8',
            '172.16.0.0/12', 
            '192.168.0.0/16',
            '127.0.0.0/8',
            '169.254.0.0/16',
            '::1/128',
            'fc00::/7',
            'fe80::/10'
        ];

        foreach ($privateRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return false;
            }
        }

        return true;
    }

    private function ipInRange(string $ip, string $range): bool {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($subnet, $bits) = explode('/', $range);
        $ip = inet_pton($ip);
        $subnet = inet_pton($subnet);

        if ($ip === false || $subnet === false) {
            return false;
        }

        $mask = ~((1 << (128 - $bits)) - 1) & 0xffffffffffffffffffffffffffffffff;
        
        return (substr($ip, 0, $bits / 8) === substr($subnet, 0, $bits / 8));
    }

    private function isHostAllowed(string $host): bool {
        foreach ($this->allowedHosts as $allowed) {
            if ($host === $allowed || fnmatch($allowed, $host)) {
                return true;
            }
        }
        return false;
    }
}