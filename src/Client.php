<?php
// src/Client.php

namespace OpenTimestampsPHP;

use OpenTimestampsPHP\Core\Timestamp;
use OpenTimestampsPHP\Serialization\{Deserializer, Serializer};
use OpenTimestampsPHP\File\{FileHandler, DetachedTimestampFile};
use OpenTimestampsPHP\Calendar\Calendar;

class Client {
    private Calendar $calendar;
    private array $options;

    public function __construct(array $options = []) {
        $this->options = $options;
        $this->calendar = new Calendar($options);
    }

    /**
     * Create a timestamp for a file with privacy protection
     * Uses a random nonce to prevent calendar servers from learning the file hash
     */
    public function stamp(string $filePath, ?string $outputPath = null, bool $wait = false): string {
        echo "Creating timestamp for: $filePath\n";
        echo "Generating random nonce for privacy protection...\n";
        
        // Create detached file with nonce
        $detachedFile = FileHandler::createDetachedForFile($filePath);
        $nonce = $detachedFile->getNonce();
        
        if ($nonce) {
            echo "✓ Nonce generated: " . bin2hex($nonce) . "\n";
            $commitment = $detachedFile->getCommitment();
            echo "✓ Commitment (nonce + file hash) created\n";
            echo "  File hash: " . bin2hex($detachedFile->getFileHash()) . "\n";
            echo "  Commitment: " . bin2hex($commitment) . "\n";
        }
        
        // Submit to calendar servers (they only see the commitment, not the file hash)
        echo "Submitting commitment to calendar servers...\n";
        $this->calendar->submit($detachedFile);
        
        // Wait for completion if requested
        if ($wait) {
            echo "Waiting for attestations...\n";
            $this->waitForAttestations($detachedFile);
        }
        
        // Determine output path
        if ($outputPath === null) {
            $outputPath = $detachedFile->getSuggestedOtsFilename();
        }
        
        // Write detached file (includes nonce for later verification)
        FileHandler::writeDetached($detachedFile, $outputPath);
        
        echo "✓ Timestamp created with privacy protection: $outputPath\n";
        return $outputPath;
    }

    /**
     * Verify a timestamp against original file with nonce support
     */
    public function verify(string $otsFilePath, string $originalFilePath): array {
        echo "Verifying timestamp: $otsFilePath\n";
        
        $result = [
            'valid' => false,
            'verified' => false,
            'file_match' => false,
            'privacy_protected' => false,
            'attestations' => [],
            'errors' => [],
            'warnings' => []
        ];

        try {
            // Read the detached file
            $detachedFile = FileHandler::readDetached($otsFilePath);
            $timestamp = $detachedFile->getTimestamp();
            $nonce = $detachedFile->getNonce();
            
            $result['privacy_protected'] = ($nonce !== null);
            
            if ($nonce) {
                echo "✓ Privacy protection detected (nonce used)\n";
                echo "  Nonce: " . bin2hex($nonce) . "\n";
            } else {
                echo "⚠ Legacy timestamp (no privacy protection)\n";
                $result['warnings'][] = 'Legacy timestamp without privacy protection';
            }

            // Verify file hash matches (handles both with and without nonce)
            $result['file_match'] = FileHandler::verifyDetached($detachedFile, $originalFilePath);
            if (!$result['file_match']) {
                $result['errors'][] = 'File hash does not match timestamp';
                return $result;
            }
            
            echo "✓ File hash verified\n";
            
            // For verification with calendar, we need to use the commitment
            $commitment = $timestamp->getMsg();
            echo "Verifying commitment with calendar servers...\n";
            
            $verificationResult = $this->calendar->verify($timestamp, $commitment);
            
            $result = array_merge($result, $verificationResult);
            $result['valid'] = $result['file_match'] && $result['valid'];
            
        } catch (\Exception $e) {
            $result['errors'][] = 'Verification failed: ' . $e->getMessage();
        }
        
        $this->printVerificationResult($result);
        return $result;
    }

    /**
     * Upgrade a timestamp (resolve pending attestations)
     */
    public function upgrade(string $otsFilePath, bool $force = false): bool {
        echo "Upgrading timestamp: $otsFilePath\n";
        
        $detachedFile = FileHandler::readDetached($otsFilePath);
        
        $initialAttestations = $this->countAttestations($detachedFile->getTimestamp());
        echo "Initial attestations: $initialAttestations\n";
        
        // Upgrade with calendar
        $upgraded = $this->calendar->upgrade($detachedFile);
        
        if ($upgraded) {
            $finalAttestations = $this->countAttestations($detachedFile->getTimestamp());
            echo "Upgraded attestations: $finalAttestations\n";
            
            // Write back the upgraded timestamp
            FileHandler::writeDetached($detachedFile, $otsFilePath);
            echo "Timestamp upgraded successfully.\n";
        } else {
            echo "No upgrades available.\n";
        }
        
        return $upgraded;
    }

    /**
     * Get information about a timestamp file with privacy details
     */
    public function info(string $filePath, bool $detailed = false): array {
        if (FileHandler::hasAttachedTimestamp($filePath)) {
            $detachedFile = FileHandler::readAttached($filePath);
            $type = 'attached';
        } else {
            $detachedFile = FileHandler::readDetached($filePath);
            $type = 'detached';
        }
        
        $timestamp = $detachedFile->getTimestamp();
        $attestations = $this->analyzeAttestations($timestamp);
        
        $info = [
            'type' => $type,
            'original_filename' => $detachedFile->getOriginalFilename(),
            'file_size' => filesize($filePath),
            'privacy_protection' => $detachedFile->getNonce() !== null,
            'timestamp_info' => [
                'operations_count' => count($timestamp->getOps()),
                'attestations_count' => count($timestamp->getAttestations()),
                'attestations_detail' => $attestations,
                'commitment_used' => $detachedFile->getNonce() !== null
            ]
        ];

        // Add privacy details
        if ($detachedFile->getNonce()) {
            $info['privacy_details'] = [
                'nonce_hex' => bin2hex($detachedFile->getNonce()),
                'nonce_length' => strlen($detachedFile->getNonce()),
                'file_hash_hex' => bin2hex($detachedFile->getFileHash()),
                'commitment_hex' => bin2hex($detachedFile->getCommitment())
            ];
        }

        if ($detailed && $timestamp->getMsg()) {
            $info['timestamp_hash'] = bin2hex($timestamp->getMsg());
            
            // Get info from calendar servers
            $calendarInfo = $this->calendar->info($timestamp->getMsg());
            $info['calendar_servers'] = $calendarInfo;
        }

        return $info;
    }

    private function waitForAttestations(DetachedTimestampFile $detachedFile, int $maxAttempts = 10, int $delay = 5): void {
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            $initialCount = $this->countAttestations($detachedFile->getTimestamp());
            
            // Try to upgrade
            $this->calendar->upgrade($detachedFile);
            
            $newCount = $this->countAttestations($detachedFile->getTimestamp());
            
            if ($newCount > $initialCount) {
                echo "New attestations received.\n";
                return;
            }
            
            $attempts++;
            if ($attempts < $maxAttempts) {
                echo "Waiting for attestations... ($attempts/$maxAttempts)\n";
                sleep($delay);
            }
        }
        
        echo "Timeout waiting for attestations.\n";
    }

    private function countAttestations(Timestamp $timestamp): int {
        $count = count($timestamp->getAttestations());
        
        foreach ($timestamp->getOps() as [$op, $subTimestamp]) {
            $count += $this->countAttestations($subTimestamp);
        }
        
        return $count;
    }

    private function analyzeAttestations(Timestamp $timestamp): array {
        $attestations = [
            'bitcoin' => 0,
            'pending' => 0,
            'total' => 0
        ];

        $allAttestations = $this->findAllAttestations($timestamp);
        $attestations['total'] = count($allAttestations);

        foreach ($allAttestations as $attestation) {
            if ($attestation instanceof \OpenTimestampsPHP\Attestations\BitcoinBlockHeaderAttestation) {
                $attestations['bitcoin']++;
            } elseif ($attestation instanceof \OpenTimestampsPHP\Attestations\PendingAttestation) {
                $attestations['pending']++;
            }
        }

        return $attestations;
    }

    private function findAllAttestations(Timestamp $timestamp): array {
        $attestations = $timestamp->getAttestations();

        foreach ($timestamp->getOps() as [$op, $subTimestamp]) {
            $attestations = array_merge($attestations, $this->findAllAttestations($subTimestamp));
        }

        return $attestations;
    }

    private function printVerificationResult(array $result): void {
        echo "\n=== Verification Result ===\n";
        echo "File match: " . ($result['file_match'] ? "YES" : "NO") . "\n";
        echo "Timestamp valid: " . ($result['valid'] ? "YES" : "NO") . "\n";
        echo "Attestations verified: " . ($result['verified'] ? "YES" : "NO") . "\n";
        echo "Privacy protected: " . ($result['privacy_protected'] ? "YES" : "NO") . "\n";
        
        if (!empty($result['attestations'])) {
            echo "\nAttestations:\n";
            foreach ($result['attestations'] as $att) {
                $status = $att['verified'] ? 'VERIFIED' : 'PENDING';
                echo "  - {$att['type']}: {$status}";
                if (isset($att['height'])) echo " (height: {$att['height']})";
                if (isset($att['uri'])) echo " (uri: {$att['uri']})";
                echo "\n";
            }
        }
        
        if (!empty($result['errors'])) {
            echo "\nErrors:\n";
            foreach ($result['errors'] as $error) {
                echo "  - $error\n";
            }
        }
        
        if (!empty($result['warnings'])) {
            echo "\nWarnings:\n";
            foreach ($result['warnings'] as $warning) {
                echo "  - $warning\n";
            }
        }
        echo "==========================\n\n";
    }

    /**
     * Get calendar client (for CLI status commands)
     */
    public function getCalendarClient() {
        if (!$this->calendar) {
            $this->calendar = new Calendar($this->options);
        }
        return $this->calendar->getClient();
    }

    /**
     * Get cache manager (for CLI status commands)
     */
    public function getCacheManager() {
        // You'll need to implement this based on your caching system
        return $this->cacheManager;
    }

    /**
     * Verify attached timestamp file
     */
    public function verifyAttached(string $attachedFilePath): array {
        $fileHandler = new FileHandler();
        $detachedFile = $fileHandler->readAttached($attachedFilePath);
        
        // Extract and verify against the embedded original data
        $tempDir = sys_get_temp_dir();
        $tempOriginalPath = $tempDir . '/ots_original_' . uniqid();
        
        try {
            $fileHandler->extractOriginalFromAttached($attachedFilePath, $tempOriginalPath);
            $result = $this->verify($attachedFilePath, $tempOriginalPath);
            
            // Clean up temp file
            unlink($tempOriginalPath);
            
            return $result;
        } catch (\Exception $e) {
            // Clean up temp file on error
            if (file_exists($tempOriginalPath)) {
                unlink($tempOriginalPath);
            }
            throw $e;
        }
    }
}