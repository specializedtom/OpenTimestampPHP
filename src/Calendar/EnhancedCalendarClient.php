<?php
// src/Calendar/EnhancedCalendarClient.php

namespace OpenTimestampsPHP\Calendar;

use OpenTimestampsPHP\Attestations\{
    BitcoinBlockHeaderAttestation,
    LitecoinBlockHeaderAttestation,
    PendingAttestation
};

class EnhancedCalendarClient extends CalendarClient {
    /**
     * Upgrade a specific pending attestation
     */
    public function upgradePendingAttestation(PendingAttestation $pending): array {
        $result = [
            'upgraded' => false,
            'verified' => false,
            'new_attestations' => [],
            'error' => null
        ];

        try {
            $uri = $pending->getUri();
            
            if (preg_match('#^https?://[^/]+#', $uri, $matches)) {
                $server = $matches[0];
                $path = substr($uri, strlen($server));
                
                $response = $this->makeCalendarRequest($server, $path, '', 'GET');
                $upgradedTimestamp = $this->deserializeResponse($response);
                
                // Extract new attestations from upgraded timestamp
                $newAttestations = $this->extractAttestations($upgradedTimestamp);
                $result['new_attestations'] = $newAttestations;
                $result['upgraded'] = count($newAttestations) > 0;
                $result['verified'] = $this->hasVerifiedAttestations($newAttestations);
            }
            
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    private function deserializeResponse(string $response): \OpenTimestampsPHP\Core\Timestamp {
        $deserializer = new \OpenTimestampsPHP\Serialization\Deserializer();
        return $deserializer->deserialize($response);
    }

    private function extractAttestations(\OpenTimestampsPHP\Core\Timestamp $timestamp): array {
        $attestations = [];
        
        $this->extractAttestationsRecursive($timestamp, $attestations);
        
        return $attestations;
    }

    private function extractAttestationsRecursive(\OpenTimestampsPHP\Core\Timestamp $timestamp, array &$attestations): void {
        // Add current attestations
        $attestations = array_merge($attestations, $timestamp->getAttestations());
        
        // Process child timestamps
        foreach ($timestamp->getOps() as [$op, $subTimestamp]) {
            $this->extractAttestationsRecursive($subTimestamp, $attestations);
        }
    }

    private function hasVerifiedAttestations(array $attestations): bool {
        foreach ($attestations as $attestation) {
            if ($attestation instanceof BitcoinBlockHeaderAttestation || 
                $attestation instanceof LitecoinBlockHeaderAttestation) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get attestation statistics from calendar servers
     */
    public function getAttestationStats(): array {
        $stats = [
            'total_servers' => count($this->servers),
            'responsive_servers' => 0,
            'attestation_success_rate' => 0,
            'server_details' => []
        ];

        $successfulRequests = 0;

        foreach ($this->servers as $server) {
            $serverStats = [
                'url' => $server,
                'responsive' => false,
                'response_time' => null,
                'error' => null
            ];

            $startTime = microtime(true);
            
            try {
                // Simple health check
                $this->makeCalendarRequest($server, '/digest', 'test', 'POST');
                $serverStats['responsive'] = true;
                $serverStats['response_time'] = round((microtime(true) - $startTime) * 1000, 2);
                $stats['responsive_servers']++;
                $successfulRequests++;
            } catch (\Exception $e) {
                $serverStats['error'] = $e->getMessage();
            }

            $stats['server_details'][] = $serverStats;
        }

        if ($stats['total_servers'] > 0) {
            $stats['attestation_success_rate'] = ($successfulRequests / $stats['total_servers']) * 100;
        }

        return $stats;
    }
}