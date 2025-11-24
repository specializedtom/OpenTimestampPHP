<?php
// src/Verification/AdvancedTimestampVerifier.php

namespace OpenTimestampsPHP\Verification;

use OpenTimestampsPHP\Core\Timestamp;
use OpenTimestampsPHP\Attestations\Attestation;

class AdvancedTimestampVerifier {
    private MerklePathValidator $merkleValidator;
    private AttestationVerifier $attestationVerifier;
    private ConsensusEngine $consensusEngine;
    private array $verificationOptions;

    public function __construct(array $options = []) {
        $this->merkleValidator = new MerklePathValidator();
        $this->attestationVerifier = new AttestationVerifier();
        $this->consensusEngine = new ConsensusEngine();
        
        $this->verificationOptions = array_merge([
            'require_merkle_integrity' => true,
            'require_consensus' => false,
            'min_confidence_score' => 0.6,
            'max_timestamp_drift' => 7200,
            'allow_pending_attestations' => true
        ], $options);
    }

    /**
     * Perform comprehensive timestamp verification
     */
    public function verifyComprehensive(Timestamp $timestamp, string $originalMessage): array {
        $verification = [
            'overall_valid' => false,
            'verification_time' => date('c'),
            'original_message_hex' => bin2hex($originalMessage),
            'components' => [],
            'security_assessment' => [],
            'recommendations' => []
        ];

        try {
            // 1. Merkle Path Validation
            $merkleResult = $this->merkleValidator->validateMerklePath($timestamp, $originalMessage);
            $verification['components']['merkle_path'] = $merkleResult;

            // 2. Attestation Verification
            $attestationResult = $this->attestationVerifier->verifyTimestamp($timestamp, $originalMessage);
            $verification['components']['attestations'] = $attestationResult;

            // 3. Consensus Evaluation
            $consensusResult = $this->consensusEngine->evaluateConsensus($attestationResult);
            $verification['components']['consensus'] = $consensusResult;

            // 4. Security Analysis
            $securityAnalysis = $this->performSecurityAnalysis($timestamp, $originalMessage, [
                'merkle' => $merkleResult,
                'attestations' => $attestationResult,
                'consensus' => $consensusResult
            ]);
            $verification['security_assessment'] = $securityAnalysis;

            // 5. Determine overall validity
            $verification['overall_valid'] = $this->determineOverallValidity(
                $merkleResult,
                $attestationResult,
                $consensusResult,
                $securityAnalysis
            );

            // 6. Generate recommendations
            $verification['recommendations'] = $this->generateRecommendations(
                $merkleResult,
                $attestationResult,
                $consensusResult,
                $securityAnalysis
            );

        } catch (\Exception $e) {
            $verification['error'] = 'Comprehensive verification failed: ' . $e->getMessage();
            $verification['overall_valid'] = false;
        }

        return $verification;
    }

    private function performSecurityAnalysis(Timestamp $timestamp, string $message, array $componentResults): array {
        $analysis = [
            'security_level' => 'unknown',
            'risk_factors' => [],
            'strength_indicators' => [],
            'vulnerability_assessment' => []
        ];

        $merkle = $componentResults['merkle'];
        $attestations = $componentResults['attestations'];
        $consensus = $componentResults['consensus'];

        // Strength indicators
        if ($merkle['path_integrity']) {
            $analysis['strength_indicators'][] = 'merkle_integrity';
        }
        if ($attestations['summary']['verified'] >= 2) {
            $analysis['strength_indicators'][] = 'multiple_verified_attestations';
        }
        if (count($consensus['participating_blockchains']) >= 2) {
            $analysis['strength_indicators'][] = 'multi_blockchain_attestation';
        }
        if ($consensus['confidence_score'] >= 0.8) {
            $analysis['strength_indicators'][] = 'high_confidence_consensus';
        }

        // Risk factors
        if ($attestations['summary']['pending'] > $attestations['summary']['verified']) {
            $analysis['risk_factors'][] = 'excessive_pending_attestations';
        }
        if (!$merkle['path_integrity']) {
            $analysis['risk_factors'][] = 'merkle_path_integrity_compromised';
        }
        if ($consensus['timestamp_consistency'] === 'inconsistent') {
            $analysis['risk_factors'][] = 'timestamp_inconsistency';
        }
        if ($attestations['summary']['failed'] > 0) {
            $analysis['risk_factors'][] = 'failed_attestations';
        }

        // Determine overall security level
        $strengthScore = count($analysis['strength_indicators']);
        $riskScore = count($analysis['risk_factors']);

        if ($riskScore === 0 && $strengthScore >= 3) {
            $analysis['security_level'] = 'high';
        } elseif ($riskScore <= 1 && $strengthScore >= 2) {
            $analysis['security_level'] = 'medium';
        } elseif ($riskScore <= 2 && $strengthScore >= 1) {
            $analysis['security_level'] = 'low';
        } else {
            $analysis['security_level'] = 'very_low';
        }

        // Vulnerability assessment
        if ($analysis['security_level'] === 'very_low') {
            $analysis['vulnerability_assessment'][] = 'timestamp_may_be_invalid_or_tampered';
        }
        if (in_array('merkle_path_integrity_compromised', $analysis['risk_factors'])) {
            $analysis['vulnerability_assessment'][] = 'possible_merkle_tree_tampering';
        }
        if (in_array('timestamp_inconsistency', $analysis['risk_factors'])) {
            $analysis['vulnerability_assessment'][] = 'potential_backdating_attempt';
        }

        return $analysis;
    }

    private function determineOverallValidity(
        array $merkleResult,
        array $attestationResult,
        array $consensusResult,
        array $securityAnalysis
    ): bool {
        // Must have valid Merkle paths
        if ($this->verificationOptions['require_merkle_integrity'] && !$merkleResult['path_integrity']) {
            return false;
        }

        // Must have at least one verified attestation
        if ($attestationResult['summary']['verified'] === 0) {
            return false;
        }

        // Check consensus requirement if enabled
        if ($this->verificationOptions['require_consensus'] && !$consensusResult['achieved']) {
            return false;
        }

        // Check minimum confidence score
        if ($consensusResult['confidence_score'] < $this->verificationOptions['min_confidence_score']) {
            return false;
        }

        // Check security level
        if (in_array($securityAnalysis['security_level'], ['very_low'])) {
            return false;
        }

        return true;
    }

    private function generateRecommendations(
        array $merkleResult,
        array $attestationResult,
        array $consensusResult,
        array $securityAnalysis
    ): array {
        $recommendations = [];

        // Merkle path recommendations
        if (!$merkleResult['path_integrity']) {
            $recommendations[] = 'INVALID: Merkle path integrity compromised - timestamp cannot be trusted';
        }

        // Attestation recommendations
        if ($attestationResult['summary']['verified'] === 0) {
            $recommendations[] = 'CRITICAL: No verified attestations found';
        } elseif ($attestationResult['summary']['verified'] === 1) {
            $recommendations[] = 'Consider adding more attestations for redundancy';
        }

        if ($attestationResult['summary']['pending'] > 0) {
            $recommendations[] = 'Upgrade pending attestations to improve verification strength';
        }

        // Consensus recommendations
        if (!$consensusResult['achieved']) {
            $recommendations[] = 'Add attestations from more blockchains to achieve consensus';
        }

        if ($consensusResult['confidence_score'] < 0.8) {
            $recommendations[] = 'Consider adding higher-weight blockchain attestations (Bitcoin)';
        }

        // Security recommendations
        if ($securityAnalysis['security_level'] === 'low') {
            $recommendations[] = 'Timestamp security is low - consider recreating with more attestations';
        }

        if (in_array('timestamp_inconsistency', $securityAnalysis['risk_factors'])) {
            $recommendations[] = 'Investigate timestamp inconsistencies across blockchains';
        }

        return $recommendations;
    }

    /**
     * Verify timestamp with specific time window
     */
    public function verifyWithTimeWindow(Timestamp $timestamp, string $originalMessage, int $expectedTime, int $tolerance = 3600): array {
        $verification = $this->verifyComprehensive($timestamp, $originalMessage);
        
        // Add time window analysis
        $verification['time_window_analysis'] = $this->analyzeTimeWindow(
            $verification,
            $expectedTime,
            $tolerance
        );

        return $verification;
    }

    private function analyzeTimeWindow(array $verification, int $expectedTime, int $tolerance): array {
        $analysis = [
            'within_window' => false,
            'expected_time' => date('c', $expectedTime),
            'tolerance_seconds' => $tolerance,
            'actual_timestamps' => [],
            'deviations' => []
        ];

        // Extract timestamps from verified attestations
        foreach ($verification['components']['attestations']['verified_attestations'] as $attestation) {
            if (isset($attestation['block_data']['timestamp'])) {
                $blockTime = $attestation['block_data']['timestamp'];
                $analysis['actual_timestamps'][] = [
                    'blockchain' => $attestation['blockchain'],
                    'timestamp' => $blockTime,
                    'human_readable' => date('c', $blockTime),
                    'deviation_seconds' => abs($blockTime - $expectedTime)
                ];
            }
        }

        // Check if within time window
        if (!empty($analysis['actual_timestamps'])) {
            $withinWindow = true;
            foreach ($analysis['actual_timestamps'] as $ts) {
                if ($ts['deviation_seconds'] > $tolerance) {
                    $withinWindow = false;
                    $analysis['deviations'][] = sprintf(
                        '%s: %d seconds outside tolerance',
                        $ts['blockchain'],
                        $ts['deviation_seconds'] - $tolerance
                    );
                }
            }
            $analysis['within_window'] = $withinWindow;
        }

        return $analysis;
    }
}