<?php
// src/Calendar/Calendar.php

namespace OpenTimestampsPHP\Calendar;

use OpenTimestampsPHP\Core\Timestamp;
use OpenTimestampsPHP\File\DetachedTimestampFile;

class Calendar {
    private CalendarClient $client;
    private array $options;

    public function __construct(array $options = []) {
        $this->client = new CalendarClient(
            $options['servers'] ?? null,
            $options['timeout'] ?? 30
        );
        $this->options = $options;
    }

    /**
     * Submit a file hash to calendar servers
     */
    public function submit(DetachedTimestampFile|Timestamp $input): void {
        if ($input instanceof DetachedTimestampFile) {
            $timestamp = $input->getTimestamp();
        } else {
            $timestamp = $input;
        }

        echo "Submitting timestamp to calendar servers...\n";
        $this->client->submit($timestamp);
        echo "Submission completed.\n";
    }

    /**
     * Upgrade a timestamp (resolve pending attestations)
     */
    public function upgrade(DetachedTimestampFile|Timestamp $input): bool {
        if ($input instanceof DetachedTimestampFile) {
            $timestamp = $input->getTimestamp();
        } else {
            $timestamp = $input;
        }

        echo "Upgrading timestamp...\n";
        $result = $this->client->upgrade($timestamp);
        
        if ($result) {
            echo "Timestamp upgraded successfully.\n";
        } else {
            echo "No upgrades available at this time.\n";
        }
        
        return $result;
    }

    /**
     * Verify a timestamp against calendar servers
     */
    public function verify(Timestamp $timestamp, string $expectedHash): array {
        $result = [
            'valid' => false,
            'verified' => false,
            'attestations' => [],
            'errors' => [],
            'info' => []
        ];

        try {
            // First, upgrade to get the latest attestations
            $this->upgrade($timestamp);
            
            // Check if the message matches expected hash
            if ($timestamp->getMsg() !== $expectedHash) {
                $result['errors'][] = 'Hash mismatch';
                return $result;
            }

            // Verify all attestations in the timestamp
            $attestations = $this->findAllAttestations($timestamp);
            $verifiedAttestations = [];

            foreach ($attestations as $attestation) {
                if ($attestation instanceof BitcoinBlockHeaderAttestation) {
                    $verifiedAttestations[] = [
                        'type' => 'bitcoin',
                        'height' => $attestation->getHeight(),
                        'verified' => $this->verifyBitcoinAttestation($attestation)
                    ];
                } elseif ($attestation instanceof PendingAttestation) {
                    $verifiedAttestations[] = [
                        'type' => 'pending',
                        'uri' => $attestation->getUri(),
                        'verified' => false
                    ];
                }
            }

            $result['attestations'] = $verifiedAttestations;
            $result['verified'] = count($verifiedAttestations) > 0;
            $result['valid'] = $this->isTimestampValid($verifiedAttestations);
            $result['info']['total_attestations'] = count($attestations);
            $result['info']['bitcoin_attestations'] = count(array_filter(
                $verifiedAttestations, 
                fn($a) => $a['type'] === 'bitcoin'
            ));

        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Get information about a timestamp from all calendar servers
     */
    public function info(string $timestampHash): array {
        return $this->client->getTimestampInfo($timestampHash);
    }

    private function findAllAttestations(Timestamp $timestamp): array {
        $attestations = $timestamp->getAttestations();

        foreach ($timestamp->getOps() as [$op, $subTimestamp]) {
            $attestations = array_merge($attestations, $this->findAllAttestations($subTimestamp));
        }

        return $attestations;
    }

    private function verifyBitcoinAttestation(BitcoinBlockHeaderAttestation $attestation): bool {
        // In a full implementation, this would verify against the Bitcoin blockchain
        // For now, we'll just check if the height is reasonable
        $height = $attestation->getHeight();
        return $height > 0 && $height < 1000000; // Reasonable range
    }

    private function isTimestampValid(array $attestations): bool {
        // Consider timestamp valid if it has at least one verified Bitcoin attestation
        foreach ($attestations as $attestation) {
            if ($attestation['type'] === 'bitcoin' && $attestation['verified']) {
                return true;
            }
        }
        return false;
    }
}