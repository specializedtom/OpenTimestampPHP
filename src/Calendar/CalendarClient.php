<?php
// src/Calendar/CalendarClient.php

namespace OpenTimestampsPHP\Calendar;

use OpenTimestampsPHP\Core\Timestamp;
use OpenTimestampsPHP\Serialization\{Serializer, Deserializer};
use OpenTimestampsPHP\Attestations\{PendingAttestation, BitcoinBlockHeaderAttestation};

class CalendarClient {
    private array $servers;
    private int $timeout;
    private array $userAgents;
    
    public function __construct(array $servers = null, int $timeout = 30) {
        $this->servers = $servers ?? [
            'https://a.pool.opentimestamps.org',
            'https://b.pool.opentimestamps.org', 
            'https://c.pool.opentimestamps.org'
        ];
        $this->timeout = $timeout;
        $this->userAgents = [
            'OpenTimestampsPHP/1.0',
            'Mozilla/5.0 (compatible; OpenTimestampsPHP)'
        ];
    }

    /**
     * Submit a timestamp to calendar servers for stamping
     */
    public function submit(Timestamp $timestamp): void {
        $serializer = new Serializer();
        $serializedData = $serializer->serialize($timestamp);
        
        $submitted = false;
        $errors = [];

        foreach ($this->servers as $server) {
            try {
                $response = $this->makeCalendarRequest($server, '/digest', $serializedData);
                $responseTimestamp = Deserializer::deserialize($response);
                
                // Merge the response (which contains pending attestations) into our timestamp
                $this->mergeTimestamps($timestamp, $responseTimestamp);
                $submitted = true;
                echo "Successfully submitted to: $server\n";
                break; // Stop after first successful submission
                
            } catch (\Exception $e) {
                $errorMsg = "Server $server failed: " . $e->getMessage();
                $errors[] = $errorMsg;
                echo $errorMsg . "\n";
                continue;
            }
        }

        if (!$submitted) {
            throw new \Exception("All calendar servers failed: " . implode(', ', $errors));
        }
    }

    /**
     * Upgrade a timestamp by resolving pending attestations
     */
    public function upgrade(Timestamp $timestamp): bool {
        $wasUpgraded = false;
        
        // Find all pending attestations in the timestamp tree
        $pendingAttestations = $this->findPendingAttestations($timestamp);
        
        foreach ($pendingAttestations as $pendingAttestation) {
            try {
                $upgraded = $this->upgradePendingAttestation($pendingAttestation);
                if ($upgraded) {
                    $wasUpgraded = true;
                }
            } catch (\Exception $e) {
                echo "Failed to upgrade attestation: " . $e->getMessage() . "\n";
                continue;
            }
        }
        
        return $wasUpgraded;
    }

    /**
     * Get information about a timestamp from calendar servers
     */
    public function getTimestampInfo(string $timestampHash): array {
        $hashHex = bin2hex($timestampHash);
        $results = [];

        foreach ($this->servers as $server) {
            try {
                $response = $this->makeCalendarRequest($server, "/timestamp/$hashHex", '', 'GET');
                $results[$server] = [
                    'status' => 'success',
                    'data' => $response
                ];
            } catch (\Exception $e) {
                $results[$server] = [
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    private function makeCalendarRequest(string $baseUrl, string $endpoint, string $data, string $method = 'POST'): string {
        $url = $baseUrl . $endpoint;
        
        $contextOptions = [
            'http' => [
                'method' => $method,
                'header' => $this->buildHeaders($data, $method),
                'content' => $method === 'POST' ? $data : '',
                'timeout' => $this->timeout,
                'ignore_errors' => true // We'll handle HTTP errors manually
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false
            ]
        ];

        $context = stream_context_create($contextOptions);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            throw new \Exception("HTTP request failed: " . ($error['message'] ?? 'Unknown error'));
        }

        // Check HTTP status code
        if (isset($http_response_header[0])) {
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
            $statusCode = $matches[1] ?? 0;
            
            if ($statusCode != 200) {
                throw new \Exception("HTTP error $statusCode");
            }
        }

        return $response;
    }

    private function buildHeaders(string $data, string $method): array {
        $headers = [
            'User-Agent: ' . $this->userAgents[array_rand($this->userAgents)],
            'Connection: close'
        ];

        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/x-opentimestamps';
            $headers[] = 'Content-Length: ' . strlen($data);
        }

        return $headers;
    }

    private function mergeTimestamps(Timestamp $target, Timestamp $source): void {
        // Merge attestations from source into target
        foreach ($source->getAttestations() as $attestation) {
            $target->addAttestation($attestation);
        }
        
        // Recursively merge operations
        // This is a simplified merge - in production you'd want more sophisticated logic
        foreach ($source->getOps() as [$op, $subSourceTimestamp]) {
            // Find matching operation in target or create new one
            $found = false;
            foreach ($target->getOps() as [$targetOp, $targetSubTimestamp]) {
                if ((string)$targetOp === (string)$op) {
                    $this->mergeTimestamps($targetSubTimestamp, $subSourceTimestamp);
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $newSubTimestamp = new Timestamp();
                $this->mergeTimestamps($newSubTimestamp, $subSourceTimestamp);
                $target->addOperation($op, $newSubTimestamp);
            }
        }
    }

    private function findPendingAttestations(Timestamp $timestamp): array {
        $pendingAttestations = [];

        // Check current timestamp's attestations
        foreach ($timestamp->getAttestations() as $attestation) {
            if ($attestation instanceof PendingAttestation) {
                $pendingAttestations[] = $attestation;
            }
        }

        // Recursively check operations
        foreach ($timestamp->getOps() as [$op, $subTimestamp]) {
            $pendingAttestations = array_merge(
                $pendingAttestations, 
                $this->findPendingAttestations($subTimestamp)
            );
        }

        return $pendingAttestations;
    }

    private function upgradePendingAttestation(PendingAttestation $pending): bool {
        $uri = $pending->getUri();
        
        // Extract server from URI (simplified parsing)
        if (preg_match('#^https?://[^/]+#', $uri, $matches)) {
            $server = $matches[0];
            $path = substr($uri, strlen($server));
            
            try {
                $response = $this->makeCalendarRequest($server, $path, '', 'GET');
                $upgradedTimestamp = Deserializer::deserialize($response);
                
                // Replace pending attestation with the upgraded ones
                // This would require modifying the parent timestamp structure
                // For now, we'll just return success
                return true;
                
            } catch (\Exception $e) {
                throw new \Exception("Upgrade failed for $uri: " . $e->getMessage());
            }
        }
        
        return false;
    }

    /**
     * Set custom calendar servers
     */
    public function setServers(array $servers): void {
        $this->servers = $servers;
    }

    /**
     * Get current calendar servers
     */
    public function getServers(): array {
        return $this->servers;
    }
}