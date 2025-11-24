<?php
// src/Verification/AttestationVerifier.php

namespace OpenTimestampsPHP\Verification;

use OpenTimestampsPHP\Attestations\{
    Attestation, 
    BitcoinBlockHeaderAttestation,
    LitecoinBlockHeaderAttestation,
    EthereumAttestation,
    PendingAttestation
};
use OpenTimestampsPHP\Core\Timestamp;
use OpenTimestampsPHP\Calendar\CalendarClient;

class AttestationVerifier {
    private ?CalendarClient $calendarClient;
    private array $verificationCache = [];
    private bool $cacheEnabled = true;

    public function __construct(?CalendarClient $calendarClient = null) {
        $this->calendarClient = $calendarClient;
    }

    /**
     * Verify all attestations in a timestamp tree
     */
    public function verifyTimestamp(Timestamp $timestamp, string $rootMessage): array {
        $results = [
            'valid' => false,
            'verified_attestations' => [],
            'pending_attestations' => [],
            'failed_attestations' => [],
            'summary' => [
                'total' => 0,
                'verified' => 0,
                'pending' => 0,
                'failed' => 0
            ]
        ];

        // Find all attestations in the tree
        $allAttestations = $this->findAllAttestations($timestamp, $rootMessage);
        $results['summary']['total'] = count($allAttestations);

        foreach ($allAttestations as $attestationData) {
            $attestation = $attestationData['attestation'];
            $message = $attestationData['message'];
            $path = $attestationData['path'];

            $cacheKey = $this->getAttestationCacheKey($attestation, $message);
            
            if ($this->cacheEnabled && isset($this->verificationCache[$cacheKey])) {
                $verificationResult = $this->verificationCache[$cacheKey];
            } else {
                $context = ['calendar_client' => $this->calendarClient];
                $verificationResult = $attestation->verify($message, $context);
                $verificationResult['path'] = $path;
                
                if ($this->cacheEnabled) {
                    $this->verificationCache[$cacheKey] = $verificationResult;
                }
            }

            // Categorize results
            if ($verificationResult['verified']) {
                $results['verified_attestations'][] = $verificationResult;
                $results['summary']['verified']++;
            } elseif ($attestation instanceof PendingAttestation) {
                $results['pending_attestations'][] = $verificationResult;
                $results['summary']['pending']++;
            } else {
                $results['failed_attestations'][] = $verificationResult;
                $results['summary']['failed']++;
            }
        }

        // Overall validity: at least one verified attestation
        $results['valid'] = $results['summary']['verified'] > 0;

        return $results;
    }

    /**
     * Find all attestations in the timestamp tree with their paths
     */
    private function findAllAttestations(Timestamp $timestamp, string $currentMessage, array $currentPath = []): array {
        $attestations = [];

        // Add current timestamp's attestations
        foreach ($timestamp->getAttestations() as $attestation) {
            $attestations[] = [
                'attestation' => $attestation,
                'message' => $currentMessage,
                'path' => $currentPath
            ];
        }

        // Recursively process operations
        foreach ($timestamp->getOps() as [$op, $subTimestamp]) {
            $newMessage = $op->call($currentMessage);
            $newPath = array_merge($currentPath, [(string)$op]);
            
            $subAttestations = $this->findAllAttestations($subTimestamp, $newMessage, $newPath);
            $attestations = array_merge($attestations, $subAttestations);
        }

        return $attestations;
    }

    /**
     * Get detailed information about attestations in a timestamp
     */
    public function analyzeAttestations(Timestamp $timestamp, string $rootMessage): array {
        $analysis = [
            'attestation_types' => [],
            'blockchains' => [],
            'earliest_block' => null,
            'latest_block' => null,
            'security_level' => 'unknown'
        ];

        $allAttestations = $this->findAllAttestations($timestamp, $rootMessage);

        foreach ($allAttestations as $attestationData) {
            $attestation = $attestationData['attestation'];
            $type = $attestation->getType();
            
            // Count types
            if (!isset($analysis['attestation_types'][$type])) {
                $analysis['attestation_types'][$type] = 0;
            }
            $analysis['attestation_types'][$type]++;

            // Track blockchains
            if ($attestation instanceof \OpenTimestampsPHP\Attestations\BlockHeaderAttestation) {
                $blockchain = $attestation->getBlockchain();
                if (!isset($analysis['blockchains'][$blockchain])) {
                    $analysis['blockchains'][$blockchain] = 0;
                }
                $analysis['blockchains'][$blockchain]++;

                // Track block heights
                $height = $attestation->getHeight();
                if ($analysis['earliest_block'] === null || $height < $analysis['earliest_block']) {
                    $analysis['earliest_block'] = $height;
                }
                if ($analysis['latest_block'] === null || $height > $analysis['latest_block']) {
                    $analysis['latest_block'] = $height;
                }
            }
        }

        // Calculate security level
        $analysis['security_level'] = $this->calculateSecurityLevel($analysis);

        return $analysis;
    }

    private function calculateSecurityLevel(array $analysis): string {
        $bitcoinCount = $analysis['blockchains']['bitcoin'] ?? 0;
        $litecoinCount = $analysis['blockchains']['litecoin'] ?? 0;
        $ethereumCount = $analysis['attestation_types']['ethereum'] ?? 0;
        $pendingCount = $analysis['attestation_types']['pending'] ?? 0;

        $totalVerified = $bitcoinCount + $litecoinCount + $ethereumCount;

        if ($totalVerified >= 2) {
            return 'high';
        } elseif ($totalVerified === 1) {
            return 'medium';
        } elseif ($pendingCount > 0) {
            return 'pending';
        } else {
            return 'none';
        }
    }

    private function getAttestationCacheKey(Attestation $attestation, string $message): string {
        $type = $attestation->getType();
        
        if ($attestation instanceof BitcoinBlockHeaderAttestation || 
            $attestation instanceof LitecoinBlockHeaderAttestation) {
            $height = $attestation->getHeight();
            return "{$type}_{$height}_" . md5($message);
        } elseif ($attestation instanceof EthereumAttestation) {
            $txHash = $attestation->getTransactionHash();
            return "{$type}_{$txHash}_" . md5($message);
        } else {
            return "{$type}_" . md5(serialize($attestation) . $message);
        }
    }

    public function setCalendarClient(CalendarClient $calendarClient): void {
        $this->calendarClient = $calendarClient;
    }

    public function clearCache(): void {
        $this->verificationCache = [];
    }

    public function enableCache(bool $enabled): void {
        $this->cacheEnabled = $enabled;
    }
}