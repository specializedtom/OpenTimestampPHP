<?php
// src/Calendar/ResponseParser.php

namespace OpenTimestampsPHP\Calendar;

class ResponseParser {
    /**
     * Parse calendar server response for errors and information
     */
    public static function parseResponse(string $response, int $httpCode): array {
        $result = [
            'success' => $httpCode === 200,
            'http_code' => $httpCode,
            'data' => $response,
            'error' => null,
            'headers' => []
        ];

        if ($httpCode !== 200) {
            $result['error'] = self::getHttpError($httpCode);
        }

        // Parse response data if it's text (for info endpoints)
        if (self::isLikelyText($response)) {
            $result['text_response'] = $response;
        }

        return $result;
    }

    private static function getHttpError(int $code): string {
        $errors = [
            400 => 'Bad Request',
            404 => 'Not Found',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable'
        ];

        return $errors[$code] ?? "HTTP Error $code";
    }

    private static function isLikelyText(string $data): bool {
        // Simple heuristic to check if data is text
        if (strlen($data) === 0) return false;
        
        $printable = 0;
        $total = min(strlen($data), 1000); // Check first 1000 bytes
        
        for ($i = 0; $i < $total; $i++) {
            $byte = ord($data[$i]);
            if ($byte >= 32 && $byte <= 126 || $byte == 9 || $byte == 10 || $byte == 13) {
                $printable++;
            }
        }
        
        return ($printable / $total) > 0.8; // 80% printable characters
    }

    /**
     * Parse calendar server URL for information
     */
    public static function parseServerUrl(string $url): array {
        $parts = parse_url($url);
        
        return [
            'scheme' => $parts['scheme'] ?? 'https',
            'host' => $parts['host'] ?? '',
            'port' => $parts['port'] ?? null,
            'path' => $parts['path'] ?? '',
            'base_url' => ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '')
        ];
    }
}