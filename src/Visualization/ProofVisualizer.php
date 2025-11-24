<?php
// src/Visualization/ProofVisualizer.php

namespace OpenTimestampsPHP\Visualization;

use OpenTimestampsPHP\Core\Timestamp;
use OpenTimestampsPHP\File\DetachedTimestampFile;

class ProofVisualizer {
    private Timestamp $timestamp;
    private string $originalMessage;
    private ?string $nonce;

    public function __construct(Timestamp $timestamp, string $originalMessage = '', ?string $nonce = null) {
        $this->timestamp = $timestamp;
        $this->originalMessage = $originalMessage;
        $this->nonce = $nonce;
    }

    /**
     * Generate human-readable proof summary with privacy information
     */
    public function generateProofSummary(): array {
        $stats = $this->timestamp->getProofStats();
        
        $summary = [
            'proof_structure' => [
                'total_operations' => $stats['total_operations'],
                'total_attestations' => $stats['total_attestations'],
                'merkle_paths' => $stats['merkle_paths'],
                'maximum_depth' => $stats['max_depth'],
                'original_message_size' => strlen($this->originalMessage)
            ],
            'privacy_protection' => [
                'enabled' => $this->nonce !== null,
                'nonce_length' => $this->nonce ? strlen($this->nonce) : 0,
                'commitment_size' => $this->timestamp->getMsg() ? strlen($this->timestamp->getMsg()) : 0
            ],
            'operation_breakdown' => $this->formatOperationBreakdown($stats['operation_types']),
            'attestation_breakdown' => $this->formatAttestationBreakdown($stats['attestation_types']),
            'security_assessment' => $this->assessSecurity($stats),
            'tree_visualization' => $this->timestamp->toTreeString()
        ];

        // Add privacy to security assessment
        if ($this->nonce !== null) {
            $summary['security_assessment']['factors'][] = 'privacy_protection';
            $summary['security_assessment']['score'] += 10;
        }

        return $summary;
    }

    /**
     * Generate detailed proof analysis
     */
    public function generateDetailedAnalysis(): array {
        $analysis = $this->generateProofSummary();
        
        // Add Merkle path analysis
        $analysis['merkle_analysis'] = $this->analyzeMerklePaths();
        
        // Add timestamp information
        $analysis['timestamp_info'] = $this->getTimestampInfo();
        
        // Add verification recommendations
        $analysis['recommendations'] = $this->generateRecommendations();
        
        return $analysis;
    }

    private function formatOperationBreakdown(array $operationTypes): array {
        $breakdown = [];
        $total = array_sum($operationTypes);
        
        foreach ($operationTypes as $type => $count) {
            $className = basename(str_replace('\\', '/', $type));
            $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
            
            $breakdown[] = [
                'operation' => $className,
                'count' => $count,
                'percentage' => $percentage,
                'description' => $this->getOperationDescription($className)
            ];
        }
        
        return $breakdown;
    }

    private function formatAttestationBreakdown(array $attestationTypes): array {
        $breakdown = [];
        $total = array_sum($attestationTypes);
        
        foreach ($attestationTypes as $type => $count) {
            $className = basename(str_replace('\\', '/', $type));
            $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
            
            $breakdown[] = [
                'attestation' => $className,
                'count' => $count,
                'percentage' => $percentage,
                'status' => $this->getAttestationStatus($className)
            ];
        }
        
        return $breakdown;
    }

    private function getOperationDescription(string $className): string {
        $descriptions = [
            'OpSHA256' => 'Cryptographic hash function (SHA-256)',
            'OpSHA1' => 'Cryptographic hash function (SHA-1)',
            'OpRIPEMD160' => 'Cryptographic hash function (RIPEMD-160)',
            'OpAppend' => 'Append data to message',
            'OpPrepend' => 'Prepend data to message',
            'OpReverse' => 'Reverse message bytes',
            'OpHexlify' => 'Convert binary to hex',
            'OpUnHexlify' => 'Convert hex to binary',
            'OpSubstr' => 'Extract substring from message',
            'OpLeft' => 'Take leftmost bytes',
            'OpRight' => 'Take rightmost bytes'
        ];
        
        return $descriptions[$className] ?? 'Unknown operation';
    }

    private function getAttestationStatus(string $className): string {
        $statuses = [
            'BitcoinBlockHeaderAttestation' => 'verified',
            'LitecoinBlockHeaderAttestation' => 'verified', 
            'EthereumAttestation' => 'verified',
            'PendingAttestation' => 'pending',
            'PendingAttestation' => 'upgrade_required'
        ];
        
        return $statuses[$className] ?? 'unknown';
    }

    private function assessSecurity(array $stats): array {
        $assessment = [
            'level' => 'low',
            'score' => 0,
            'factors' => []
        ];

        $score = 0;

        // Factor: Number of attestations
        if ($stats['total_attestations'] >= 3) {
            $score += 30;
            $assessment['factors'][] = 'multiple_attestations';
        }

        // Factor: Multiple blockchain types
        $blockchainCount = count(array_filter(array_keys($stats['attestation_types']), 
            fn($type) => strpos($type, 'BlockHeaderAttestation') !== false));
        if ($blockchainCount >= 2) {
            $score += 25;
            $assessment['factors'][] = 'multiple_blockchains';
        }

        // Factor: Proof depth
        if ($stats['max_depth'] >= 3) {
            $score += 20;
            $assessment['factors'][] = 'sufficient_depth';
        }

        // Factor: Operation diversity
        if (count($stats['operation_types']) >= 3) {
            $score += 15;
            $assessment['factors'][] = 'operation_diversity';
        }

        // Factor: No pending attestations
        $hasPending = isset($stats['attestation_types']['OpenTimestampsPHP\Attestations\PendingAttestation']);
        if (!$hasPending) {
            $score += 10;
            $assessment['factors'][] = 'all_attestations_verified';
        }

        $assessment['score'] = $score;

        // Determine security level
        if ($score >= 80) {
            $assessment['level'] = 'high';
        } elseif ($score >= 60) {
            $assessment['level'] = 'medium';
        } elseif ($score >= 40) {
            $assessment['level'] = 'low';
        } else {
            $assessment['level'] = 'very_low';
        }

        return $assessment;
    }

    private function analyzeMerklePaths(): array {
        $analysis = [
            'total_paths' => 0,
            'unique_paths' => 0,
            'path_lengths' => [],
            'operations_per_path' => []
        ];

        $this->collectMerklePathData($this->timestamp, [], $analysis);
        
        if ($analysis['total_paths'] > 0) {
            $analysis['average_path_length'] = array_sum($analysis['path_lengths']) / $analysis['total_paths'];
            $analysis['max_path_length'] = max($analysis['path_lengths']);
            $analysis['min_path_length'] = min($analysis['path_lengths']);
        }

        return $analysis;
    }

    private function collectMerklePathData(Timestamp $timestamp, array $currentPath, array &$analysis): void {
        $currentOps = count($currentPath);

        // If we have attestations, this is a complete path
        if (!empty($timestamp->getAttestations())) {
            $analysis['total_paths']++;
            $analysis['path_lengths'][] = $currentOps;
            $analysis['operations_per_path'][] = $currentOps;
        }

        // Continue down operations
        foreach ($timestamp->getOps() as [$op, $subTimestamp]) {
            $newPath = array_merge($currentPath, [$op]);
            $this->collectMerklePathData($subTimestamp, $newPath, $analysis);
        }
    }

    private function getTimestampInfo(): array {
        $privacyInfo = [];
        if ($this->nonce !== null) {
            $privacyInfo = [
                'privacy_protection' => 'enabled',
                'nonce_hex' => bin2hex($this->nonce),
                'file_hash_hex' => bin2hex(substr($this->timestamp->getMsg() ?? '', strlen($this->nonce))),
                'commitment_hex' => bin2hex($this->timestamp->getMsg() ?? '')
            ];
        } else {
            $privacyInfo = [
                'privacy_protection' => 'disabled',
                'file_hash_hex' => bin2hex($this->timestamp->getMsg() ?? '')
            ];
        }

        return array_merge([
            'message_hash' => $this->timestamp->getMsg() ? bin2hex($this->timestamp->getMsg()) : null,
            'message_size' => $this->timestamp->getMsg() ? strlen($this->timestamp->getMsg()) : 0,
            'tree_representation' => $this->timestamp->toTreeString()
        ], $privacyInfo);
    }

    private function generateRecommendations(): array {
        $recommendations = [];
        $stats = $this->timestamp->getProofStats();

        if ($stats['total_attestations'] < 2) {
            $recommendations[] = 'Consider adding more attestations for better security';
        }

        if (isset($stats['attestation_types']['OpenTimestampsPHP\Attestations\PendingAttestation'])) {
            $recommendations[] = 'Upgrade pending attestations to get blockchain confirmations';
        }

        if ($stats['max_depth'] < 2) {
            $recommendations[] = 'Shallow proof depth - consider adding more operations';
        }

        $blockchainCount = count(array_filter(array_keys($stats['attestation_types']), 
            fn($type) => strpos($type, 'BlockHeaderAttestation') !== false));
        if ($blockchainCount < 2) {
            $recommendations[] = 'Add attestations from multiple blockchains for redundancy';
        }

        if ($this->nonce === null) {
            $recommendations[] = 'Create new timestamp with privacy protection (nonce)';
        }

        return $recommendations;
    }

    /**
     * Generate a compact visualization for CLI display
     */
    public function generateCompactView(): string {
        $stats = $this->timestamp->getProofStats();
        
        $output = "PROOF STRUCTURE\n";
        $output .= "═══════════════\n";
        $output .= "Total operations: " . $stats['total_operations'] . "\n";
        $output .= "Total attestations: " . $stats['total_attestations'] . "\n";
        $output .= "Merkle paths: " . $stats['merkle_paths'] . "\n";
        $output .= "Maximum depth: " . $stats['max_depth'] . "\n";
        $output .= "Privacy protection: " . ($this->nonce !== null ? "ENABLED" : "DISABLED") . "\n\n";

        $output .= "ATTESTATIONS:\n";
        foreach ($stats['attestation_types'] as $type => $count) {
            $name = basename(str_replace('\\', '/', $type));
            $output .= "  • " . $name . ": " . $count . "\n";
        }

        return $output;
    }
}