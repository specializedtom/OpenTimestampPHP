<?php
// src/Client/ConfigurableClient.php

namespace OpenTimestampsPHP\Client;

use OpenTimestampsPHP\Config\Configuration;
use OpenTimestampsPHP\Calendar\CalendarClient;
use OpenTimestampsPHP\Verification\AdvancedTimestampVerifier;
use OpenTimestampsPHP\File\FileHandler;

class ConfigurableClient {
    private Configuration $config;
    private CalendarClient $calendarClient;
    private AdvancedTimestampVerifier $verifier;
    private array $instances = [];

    public function __construct(Configuration $config) {
        $this->config = $config;
    }

    public function getCalendarClient(): CalendarClient {
        if (!isset($this->instances['calendar'])) {
            $this->instances['calendar'] = new CalendarClient(
                $this->config->get('calendar.servers'),
                $this->config->get('calendar.timeout')
            );
        }
        return $this->instances['calendar'];
    }

    public function getVerifier(): AdvancedTimestampVerifier {
        if (!isset($this->instances['verifier'])) {
            $verificationOptions = [
                'require_merkle_integrity' => $this->config->get('verification.require_merkle_integrity'),
                'require_consensus' => $this->config->get('verification.require_consensus'),
                'min_confidence_score' => $this->config->get('verification.min_confidence_score'),
                'max_timestamp_drift' => $this->config->get('verification.max_timestamp_drift'),
                'allow_pending_attestations' => $this->config->get('verification.allow_pending_attestations')
            ];
            
            $this->instances['verifier'] = new AdvancedTimestampVerifier($verificationOptions);
        }
        return $this->instances['verifier'];
    }

    public function getFileHandler(): FileHandler {
        if (!isset($this->instances['file_handler'])) {
            // FileHandler doesn't need configuration yet, but might in the future
            $this->instances['file_handler'] = new FileHandler();
        }
        return $this->instances['file_handler'];
    }

    public function getConfig(): Configuration {
        return $this->config;
    }

    /**
     * Create a stamp operation with current configuration
     */
    public function stamp(string $filePath, ?string $outputPath = null): string {
        $client = new \OpenTimestampsPHP\Client();
        
        // Apply configuration to the client if it supports configuration
        // This is a simplified example - in reality you'd want to refactor the main Client
        return $client->stamp($filePath, $outputPath);
    }

    /**
     * Create a verification operation with current configuration
     */
    public function verify(string $otsFilePath, string $originalFilePath): array {
        $client = new \OpenTimestampsPHP\Client();
        return $client->verify($otsFilePath, $originalFilePath);
    }
}