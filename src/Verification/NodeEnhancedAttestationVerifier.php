<?php
// src/Verification/NodeEnhancedAttestationVerifier.php

namespace OpenTimestampsPHP\Verification;

use OpenTimestampsPHP\Blockchain\BlockchainManager;

class NodeEnhancedAttestationVerifier extends AttestationVerifier {
    private BlockchainManager $blockchainManager;

    public function __construct(?BlockchainManager $blockchainManager = null, ?\OpenTimestampsPHP\Calendar\CalendarClient $calendarClient = null) {
        parent::__construct($calendarClient);
        $this->blockchainManager = $blockchainManager ?? new BlockchainManager();
    }

    /**
     * Enhanced verification with node support
     */
    public function verifyTimestampWithNode(Timestamp $timestamp, string $rootMessage): array {
        $basicResults = parent::verifyTimestamp($timestamp, $rootMessage);
        
        // Enhance Bitcoin attestation results with node verification
        $enhancedResults = $this->enhanceBitcoinVerification($basicResults);
        
        $enhancedResults['node_status'] = $this->blockchainManager->getBlockchainStatus();
        $enhancedResults['recommended_method'] = $this->blockchainManager->getRecommendedVerificationMethod();
        $enhancedResults['sync_status'] = $this->blockchainManager->getSyncStatus();
        
        return $enhancedResults;
    }

    private function enhanceBitcoinVerification(array $basicResults): array {
        $enhancedResults = $basicResults;
        
        foreach ($enhancedResults['verified_attestations'] as &$attestation) {
            if ($attestation['type'] === 'bitcoin' && $attestation['verified']) {
                // Re-verify with node for enhanced confidence
                $nodeVerification = $this->blockchainManager->verifyBitcoinAttestation(
                    $attestation['message'],
                    $attestation['height']
                );
                
                $attestation['node_verification'] = $nodeVerification;
                $attestation['verification_confidence'] = $this->calculateConfidence($nodeVerification);
            }
        }

        return $enhancedResults;
    }

    private function calculateConfidence(array $nodeVerification): string {
        if (!$nodeVerification['verified']) {
            return 'none';
        }

        switch ($nodeVerification['method']) {
            case 'merkle_proof':
                return 'high';
            case 'basic':
                return 'medium';
            case 'basic_fallback':
                return 'low';
            default:
                return 'unknown';
        }
    }

    public function getBlockchainManager(): BlockchainManager {
        return $this->blockchainManager;
    }

    /**
     * Verify with node preference
     */
    public function verifyWithNodePreference(Timestamp $timestamp, string $rootMessage): array {
        $nodeAvailable = $this->blockchainManager->isBitcoinNodeAvailable();
        
        if ($nodeAvailable) {
            return $this->verifyTimestampWithNode($timestamp, $rootMessage);
        }
        
        // Fall back to basic verification
        $results = parent::verifyTimestamp($timestamp, $rootMessage);
        $results['node_available'] = false;
        $results['verification_method'] = 'explorer_fallback';
        
        return $results;
    }
}