<?php
// src/Verification/ConsensusEngine.php

namespace OpenTimestampsPHP\Verification;

use OpenTimestampsPHP\Attestations\{
    BitcoinBlockHeaderAttestation,
    LitecoinBlockHeaderAttestation,
    EthereumAttestation,
    BlockHeaderAttestation
};

class ConsensusEngine {
    private array $blockchainWeights;
    private int $consensusThreshold;
    private array $timestampTolerance;

    public function __construct() {
        // Define relative trust weights for different blockchains
        $this->blockchainWeights = [
            'bitcoin' => 1.0,    // Gold standard
            'litecoin' => 0.8,   // Very reliable
            'ethereum' => 0.7,   // Reliable but different security model
            'pending' => 0.1     // Minimal trust until verified
        ];

        $this->consensusThreshold = 0.6; // 60% confidence required
        $this->timestampTolerance = [
            'max_acceptable_drift' => 7200, // 2 hours in seconds
            'block_time_tolerance' => 3600  // 1 hour tolerance for block times
        ];
    }

    /**
     * Evaluate consensus across multiple attestations
     */
    public function evaluateConsensus(array $verificationResults): array {
        $consensus = [
            'achieved' => false,
            'confidence_score' => 0.0,
            'consensus_level' => 'none',
            'participating_blockchains' => [],
            'timestamp_consistency' => 'unknown',
            'strongest_attestation' => null,
            'detailed_analysis' => []
        ];

        $weightedScore = 0.0;
        $totalWeight = 0.0;
        $blockTimestamps = [];
        $participatingChains = [];

        foreach ($verificationResults['verified_attestations'] as $attestation) {
            if ($attestation['verified']) {
                $blockchain = $attestation['blockchain'] ?? $attestation['type'];
                $weight = $this->blockchainWeights[$blockchain] ?? 0.5;
                
                $weightedScore += $weight;
                $totalWeight += $weight;
                
                $participatingChains[$blockchain] = true;

                // Collect timestamp information for consistency check
                if (isset($attestation['block_data']['timestamp'])) {
                    $blockTimestamps[] = [
                        'blockchain' => $blockchain,
                        'timestamp' => $attestation['block_data']['timestamp'],
                        'height' => $attestation['height'] ?? null,
                        'weight' => $weight
                    ];
                }

                // Track strongest attestation
                if (!$consensus['strongest_attestation'] || $weight > $consensus['strongest_attestation']['weight']) {
                    $consensus['strongest_attestation'] = [
                        'blockchain' => $blockchain,
                        'weight' => $weight,
                        'height' => $attestation['height'] ?? null,
                        'data' => $attestation
                    ];
                }
            }
        }

        // Calculate confidence score
        if ($totalWeight > 0) {
            $consensus['confidence_score'] = $weightedScore / $totalWeight;
            $consensus['achieved'] = $consensus['confidence_score'] >= $this->consensusThreshold;
        }

        // Determine consensus level
        $consensus['consensus_level'] = $this->determineConsensusLevel(
            $consensus['confidence_score'],
            count($participatingChains)
        );

        $consensus['participating_blockchains'] = array_keys($participatingChains);
        $consensus['timestamp_consistency'] = $this->checkTimestampConsistency($blockTimestamps);
        $consensus['detailed_analysis'] = $this->generateDetailedAnalysis($verificationResults, $consensus);

        return $consensus;
    }

    private function determineConsensusLevel(float $confidence, int $chainCount): string {
        if ($confidence >= 0.8 && $chainCount >= 2) {
            return 'strong';
        } elseif ($confidence >= 0.6 && $chainCount >= 1) {
            return 'moderate';
        } elseif ($confidence >= 0.3) {
            return 'weak';
        } else {
            return 'none';
        }
    }

    private function checkTimestampConsistency(array $blockTimestamps): string {
        if (count($blockTimestamps) < 2) {
            return 'insufficient_data';
        }

        $timestamps = [];
        foreach ($blockTimestamps as $block) {
            $timestamps[] = $block['timestamp'];
        }

        $minTimestamp = min($timestamps);
        $maxTimestamp = max($timestamps);
        $drift = $maxTimestamp - $minTimestamp;

        if ($drift <= $this->timestampTolerance['max_acceptable_drift']) {
            return 'consistent';
        } elseif ($drift <= $this->timestampTolerance['max_acceptable_drift'] * 2) {
            return 'moderately_consistent';
        } else {
            return 'inconsistent';
        }
    }

    private function generateDetailedAnalysis(array $verificationResults, array $consensus): array {
        $analysis = [
            'attestation_quality' => [],
            'security_recommendations' => [],
            'risk_factors' => [],
            'improvement_opportunities' => []
        ];

        $verifiedCount = count($verificationResults['verified_attestations']);
        $pendingCount = count($verificationResults['pending_attestations']);
        $failedCount = count($verificationResults['failed_attestations']);

        // Assess attestation quality
        if ($verifiedCount >= 3) {
            $analysis['attestation_quality'][] = 'high_redundancy';
        }
        if ($consensus['timestamp_consistency'] === 'consistent') {
            $analysis['attestation_quality'][] = 'temporal_consistency';
        }
        if (count($consensus['participating_blockchains']) >= 2) {
            $analysis['attestation_quality'][] = 'multi_blockchain';
        }

        // Identify risk factors
        if ($pendingCount > $verifiedCount) {
            $analysis['risk_factors'][] = 'excessive_pending_attestations';
        }
        if ($failedCount > 0) {
            $analysis['risk_factors'][] = 'failed_attestations_present';
        }
        if ($consensus['confidence_score'] < 0.5) {
            $analysis['risk_factors'][] = 'low_confidence_score';
        }

        // Provide recommendations
        if ($verifiedCount === 0 && $pendingCount > 0) {
            $analysis['security_recommendations'][] = 'upgrade_pending_attestations';
        }
        if (count($consensus['participating_blockchains']) === 1) {
            $analysis['security_recommendations'][] = 'add_more_blockchain_attestations';
        }
        if ($consensus['timestamp_consistency'] === 'inconsistent') {
            $analysis['security_recommendations'][] = 'investigate_timestamp_discrepancies';
        }

        return $analysis;
    }

    /**
     * Set custom blockchain weights
     */
    public function setBlockchainWeights(array $weights): void {
        $this->blockchainWeights = array_merge($this->blockchainWeights, $weights);
    }

    /**
     * Set consensus threshold
     */
    public function setConsensusThreshold(float $threshold): void {
        $this->consensusThreshold = max(0.1, min(1.0, $threshold));
    }
}