<?php
// tests/validation_test.php

require_once __DIR__ . '/../vendor/autoload.php';

use OpenTimestampsPHP\Validation\{
    InputSanitizer,
    SecurityManager,
    FilePathValidator,
    UrlValidator,
    HashValidator
};

function test_file_path_validation() {
    echo "=== Testing File Path Validation ===\n";
    
    $validator = new FilePathValidator();
    
    // Valid paths
    $validPaths = [
        '/var/www/document.pdf',
        'C:\\Users\\test\\file.ots',
        './relative/file.txt'
    ];
    
    foreach ($validPaths as $path) {
        if ($validator->validate($path)) {
            echo "✓ Valid path: $path\n";
        } else {
            echo "✗ Invalid path: $path - " . $validator->getError() . "\n";
        }
    }
    
    // Invalid paths
    $invalidPaths = [
        '/etc/passwd',
        '../../secret.txt',
        '/var/www/../etc/passwd',
        "file\0withnull.txt"
    ];
    
    foreach ($invalidPaths as $path) {
        if (!$validator->validate($path)) {
            echo "✓ Correctly blocked: $path - " . $validator->getError() . "\n";
        } else {
            echo "✗ Should have blocked: $path\n";
        }
    }
    
    echo "File path validation test completed.\n\n";
}

function test_url_validation() {
    echo "=== Testing URL Validation ===\n";
    
    $validator = new UrlValidator();
    
    // Valid URLs
    $validUrls = [
        'https://a.pool.opentimestamps.org',
        'https://example.com/api'
    ];
    
    foreach ($validUrls as $url) {
        if ($validator->validate($url)) {
            echo "✓ Valid URL: $url\n";
        } else {
            echo "✗ Invalid URL: $url - " . $validator->getError() . "\n";
        }
    }
    
    // Invalid URLs
    $invalidUrls = [
        'http://insecure.example.com',
        'https://localhost/api',
        'https://127.0.0.1:8080',
        'https://user:pass@example.com'
    ];
    
    foreach ($invalidUrls as $url) {
        if (!$validator->validate($url)) {
            echo "✓ Correctly blocked: $url - " . $validator->getError() . "\n";
        } else {
            echo "✗ Should have blocked: $url\n";
        }
    }
    
    echo "URL validation test completed.\n\n";
}

function test_hash_validation() {
    echo "=== Testing Hash Validation ===\n";
    
    $validator = new HashValidator();
    
    // Valid hashes
    $validHashes = [
        hash('sha256', 'test', false), // hex
        hash('sha256', 'test', true),  // binary
        hash('sha1', 'test', false),
        hash('ripemd160', 'test', false)
    ];
    
    foreach ($validHashes as $hash) {
        if ($validator->validate($hash)) {
            echo "✓ Valid hash: " . (is_string($hash) ? bin2hex(substr($hash, 0, 8)) . "..." : substr($hash, 0, 16) . "...") . "\n";
        } else {
            echo "✗ Invalid hash - " . $validator->getError() . "\n";
        }
    }
    
    // Invalid hashes
    $invalidHashes = [
        'tooshort',
        'x' . hash('sha256', 'test', false), // wrong length
        'invalid characters!!!'
    ];
    
    foreach ($invalidHashes as $hash) {
        if (!$validator->validate($hash)) {
            echo "✓ Correctly blocked invalid hash - " . $validator->getError() . "\n";
        } else {
            echo "✗ Should have blocked: $hash\n";
        }
    }
    
    echo "Hash validation test completed.\n\n";
}

function test_input_sanitizer() {
    echo "=== Testing Input Sanitizer ===\n";
    
    $sanitizer = new InputSanitizer();
    
    // Test multiple inputs
    $inputs = [
        'file_path' => [
            'type' => 'file_path',
            'value' => '/path/to/document.pdf',
            'required' => true
        ],
        'calendar_url' => [
            'type' => 'url', 
            'value' => 'https://a.pool.opentimestamps.org',
            'required' => true
        ],
        'file_hash' => [
            'type' => 'hash',
            'value' => hash('sha256', 'test'),
            'required' => true
        ]
    ];
    
    try {
        $results = $sanitizer->validateMultiple($inputs);
        echo "✓ Multiple input validation passed\n";
        echo "  - File path: " . $results['file_path'] . "\n";
        echo "  - URL: " . $results['calendar_url'] . "\n";
        echo "  - Hash: " . substr($results['file_hash'], 0, 16) . "...\n";
    } catch (Exception $e) {
        echo "✗ Multiple input validation failed: " . $e->getMessage() . "\n";
    }
    
    echo "Input sanitizer test completed.\n\n";
}

function test_security_manager() {
    echo "=== Testing Security Manager ===\n";
    
    $security = new SecurityManager();
    
    // Test file upload validation
    try {
        $testFile = sys_get_temp_dir() . '/test_upload.txt';
        file_put_contents($testFile, 'Test content');
        
        $validatedPath = $security->validateFileUpload($testFile);
        echo "✓ File upload validation passed: $validatedPath\n";
        
        unlink($testFile);
    } catch (Exception $e) {
        echo "✗ File upload validation failed: " . $e->getMessage() . "\n";
    }
    
    // Test HTTP request validation
    try {
        list($url, $options) = $security->validateHttpRequest('https://a.pool.opentimestamps.org');
        echo "✓ HTTP request validation passed\n";
        echo "  - URL: $url\n";
        echo "  - Secure options set: " . (isset($options['http']['follow_location']) ? 'YES' : 'NO') . "\n";
    } catch (Exception $e) {
        echo "✗ HTTP request validation failed: " . $e->getMessage() . "\n";
    }
    
    // Test SSRF protection
    try {
        $security->validateHttpRequest('https://127.0.0.1/internal');
        echo "✗ SSRF protection should have blocked localhost\n";
    } catch (Exception $e) {
        echo "✓ SSRF protection correctly blocked localhost: " . $e->getMessage() . "\n";
    }
    
    // Get security report
    $report = $security->getSecurityReport();
    echo "Security report:\n";
    echo "  - SSRF Protection: " . ($report['ssrf_protection'] ? 'ENABLED' : 'DISABLED') . "\n";
    echo "  - XSS Protection: " . ($report['xss_protection'] ? 'ENABLED' : 'DISABLED') . "\n";
    echo "  - Max File Size: " . $report['max_file_size'] . " bytes\n";
    
    echo "Security manager test completed.\n\n";
}

function test_xss_protection() {
    echo "=== Testing XSS Protection ===\n";
    
    $security = new SecurityManager();
    
    $xssAttempts = [
        '<script>alert("xss")</script>',
        '"><img src=x onerror=alert(1)>',
        'javascript:alert(1)',
        '../../etc/passwd'
    ];
    
    foreach ($xssAttempts as $attempt) {
        $sanitized = $security->sanitizeUserInput($attempt, 'string');
        if (strpos($sanitized, '<script>') === false && 
            strpos($sanitized, 'onerror') === false &&
            strpos($sanitized, 'javascript:') === false) {
            echo "✓ XSS attempt neutralized: " . substr($sanitized, 0, 50) . "...\n";
        } else {
            echo "✗ XSS attempt not properly sanitized: $attempt\n";
        }
    }
    
    echo "XSS protection test completed.\n\n";
}

// Run all tests
test_file_path_validation();
test_url_validation();
test_hash_validation();
test_input_sanitizer();
test_security_manager();
test_xss_protection();