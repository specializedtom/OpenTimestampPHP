<?php
// src/Calendar/MultiCalendarAggregator.php

namespace OpenTimestampsPHP\Calendar;

use OpenTimestampsPHP\Core\Timestamp;
use OpenTimestampsPHP\Serialization\Serializer;

class MultiCalendarAggregator {
    private CalendarClient $client;
    private array $config;
    private array $aggregationStrategies = ['all', 'quorum', 'first_success'];

    public function __construct(CalendarClient $client, array $config = []) {
        $this->client = $client;
        $this->config = array_merge([
            'strategy' => 'quorum',
            'quorum_size' => 2,
            'min_successful' => 1,
            'timeout_per_calendar' => 10,
            'max_parallel_requests' => 5
        ], $config);
    }

    /**
     * Submit to multiple calendars and aggregate responses
     */
    public function submitToMultipleCalendars(Timestamp $timestamp): Timestamp {
        $serializer = new Serializer();
        $serializedData = $serializer->serialize($timestamp);
        
        $responses = $this->submitToAllCalendars($serializedData);
        $aggregated = $this->aggregateResponses($responses, $timestamp);
        
        return $aggregated;
    }

    /**
     * Submit to all calendar servers in parallel
     */
    private function submitToAllCalendars(string $serializedData): array {
        $servers = $this->client->getServers();
        $responses = [];
        $pending = [];
        
        // Create multi curl handle for parallel requests
        $mh = curl_multi_init();
        
        foreach ($servers as $server) {
            $ch = $this->createCurlHandle($server, $serializedData);
            curl_multi_add_handle($mh, $ch);
            $pending[$server] = $ch;
        }
        
        // Execute parallel requests
        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        
        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) != -1) {
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }
        
        // Collect responses
        foreach ($pending as $server => $ch) {
            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode === 200 && $response !== false) {
                $responses[$server] = [
                    'success' => true,
                    'data' => $response,
                    'response_time' => curl_getinfo($ch, CURLINFO_TOTAL_TIME)
                ];
            } else {
                $responses[$server] = [
                    'success' => false,
                    'error' => curl_error($ch),
                    'http_code' => $httpCode
                ];
            }
            
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        
        curl_multi_close($mh);
        return $responses;
    }

    private function createCurlHandle(string $server, string $data) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $server . '/digest',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout_per_calendar'],
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-opentimestamps',
                'User-Agent: OpenTimestampsPHP/1.0'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        return $ch;
    }

    /**
     * Aggregate responses from multiple calendars
     */
    private function aggregateResponses(array $responses, Timestamp $original): Timestamp {
        $successful = array_filter($responses, fn($r) => $r['success']);
        
        if (count($successful) < $this->config['min_successful']) {
            throw new \RuntimeException(sprintf(
                "Insufficient calendar responses: %d successful, required %d",
                count($successful),
                $this->config['min_successful']
            ));
        }

        $aggregated = clone $original;
        
        foreach ($successful as $server => $response) {
            try {
                $responseTimestamp = $this->client->deserializeResponse($response['data']);
                $this->mergeTimestamp($aggregated, $responseTimestamp, $server);
            } catch (\Exception $e) {
                // Log but continue with other responses
                error_log("Failed to process response from $server: " . $e->getMessage());
            }
        }

        return $aggregated;
    }

    /**
     * Merge timestamp from another calendar
     */
    private function mergeTimestamp(Timestamp $target, Timestamp $source, string $calendarUrl): void {
        // Add calendar-specific attestations with source tracking
        $sourceAttestations = $this->findPendingAttestations($source);
        
        foreach ($sourceAttestations as $attestation) {
            // Enhance attestation with calendar source information
            $enhancedAttestation = new EnhancedPendingAttestation(
                $attestation->getUri(),
                $calendarUrl,
                time()
            );
            $target->addAttestation($enhancedAttestation);
        }

        // Merge operations recursively
        $this->mergeOperations($target, $source, $calendarUrl);
    }

    private function mergeOperations(Timestamp $target, Timestamp $source, string $calendarUrl): void {
        foreach ($source->getOps() as [$sourceOp, $sourceSubTimestamp]) {
            $found = false;
            
            // Look for matching operation in target
            foreach ($target->getOps() as [$targetOp, $targetSubTimestamp]) {
                if ($this->operationsMatch($targetOp, $sourceOp)) {
                    $this->mergeOperations($targetSubTimestamp, $sourceSubTimestamp, $calendarUrl);
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                // Add new operation path from this calendar
                $newSubTimestamp = new Timestamp();
                $this->mergeOperations($newSubTimestamp, $sourceSubTimestamp, $calendarUrl);
                $target->addOperation($sourceOp, $newSubTimestamp);
            }
        }
    }

    private function operationsMatch($op1, $op2): bool {
        return (string)$op1 === (string)$op2;
    }

    private function findPendingAttestations(Timestamp $timestamp): array {
        $attestations = [];
        
        foreach ($timestamp->getAttestations() as $attestation) {
            if ($attestation instanceof \OpenTimestampsPHP\Attestations\PendingAttestation) {
                $attestations[] = $attestation;
            }
        }
        
        foreach ($timestamp->getOps() as [$op, $subTimestamp]) {
            $attestations = array_merge($attestations, $this->findPendingAttestations($subTimestamp));
        }
        
        return $attestations;
    }

    /**
     * Get aggregation statistics
     */
    public function getAggregationStats(array $responses): array {
        $successful = array_filter($responses, fn($r) => $r['success']);
        $failed = array_filter($responses, fn($r) => !$r['success']);
        
        $responseTimes = array_map(fn($r) => $r['response_time'] ?? 0, $successful);
        
        return [
            'total_servers' => count($responses),
            'successful' => count($successful),
            'failed' => count($failed),
            'success_rate' => count($responses) > 0 ? (count($successful) / count($responses)) * 100 : 0,
            'avg_response_time' => count($responseTimes) > 0 ? array_sum($responseTimes) / count($responseTimes) : 0,
            'min_response_time' => count($responseTimes) > 0 ? min($responseTimes) : 0,
            'max_response_time' => count($responseTimes) > 0 ? max($responseTimes) : 0,
            'failed_servers' => array_keys($failed)
        ];
    }
}