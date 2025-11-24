<?php
// tests/bootstrap.php
require_once __DIR__ . '/../vendor/autoload.php';

// tests/BasicTest.php
use PHPUnit\Framework\TestCase;
use OpenTimestampsPHP\Client;
use OpenTimestampsPHP\Validation\InputSanitizer;

class BasicTest extends TestCase {
    public function testBasicValidation() {
        $sanitizer = new InputSanitizer();
        
        $validPath = $sanitizer->validateFilePath('/tmp/test.pdf');
        $this->assertEquals('/tmp/test.pdf', $validPath);
        
        $validUrl = $sanitizer->validateUrl('https://a.pool.opentimestamps.org');
        $this->assertEquals('https://a.pool.opentimestamps.org', $validUrl);
    }
}