<?php
// src/Client/SpecCompliantClient.php

namespace OpenTimestampsPHP\Client;

use OpenTimestampsPHP\Calendar\MultiCalendarAggregator;
use OpenTimestampsPHP\Calendar\RedundantUpgradeManager;
use OpenTimestampsPHP\Config\Configuration;

class SpecCompliantClient extends Client {
    private MultiCalendarAggregator $aggregator;
    private RedundantUpgradeManager $upgradeManager;
    private array $specConfig;

    public function __construct(array $options = []) {
        parent::__construct($options);
        $this->initializeSpecComponents();
    }

    private function initializeSpecComponents(): void {
        $calendarClient = $this->getCalendarClient();
        
        $this->specConfig = $this->options['spec_compliance'] ?? [
            'multi_calendar_aggregation' => true,
            'redundant_upgrades' => true,
            'min_calendar_responses' => 2,
            'upgrade_redundancy' => 3
        ];

        if ($this->specConfig['multi_calendar_aggregation']) {
            $this->aggregator = new MultiCalendarAggregator($calendarClient, [
                'min_successful' => $this->specConfig['min_calendar_responses'],
                'strategy' => 'quorum'
            ]);
        }

        if ($this->specConfig['redundant_upgrades']) {
            $this->upgradeManager = new RedundantUpgradeManager($calendarClient, [
                'max_parallel_upgrades' => $this->specConfig['upgrade_redundancy']
            ]);
        }
    }

    /**
     * Create timestamp with multi-calendar aggregation
     */
    public function stamp(string $filePath, ?string $outputPath = null, bool $wait = false): string {
        if (!$this->specConfig['multi_calendar_aggregation']) {
            return parent::stamp($filePath, $outputPath, $wait);
        }

        $this->info("Creating timestamp with multi-calendar aggregation...");

        // Create detached file
        $detachedFile = FileHandler::createDetachedForFile($filePath);
        
        // Submit to multiple calendars and aggregate
        $aggregatedTimestamp = $this->aggregator->submitToMultipleCalendars(
            $detachedFile->getTimestamp()
        );
        
        $detachedFile->setTimestamp($aggregatedTimestamp);
        
        // Determine output path
        if ($outputPath === null) {
            $outputPath = $detachedFile->getSuggestedOtsFilename();
        }
        
        // Write detached file
        FileHandler::writeDetached($detachedFile, $outputPath);
        
        $this->info("Multi-calendar timestamp created: $outputPath");
        
        if ($wait) {
            $this->info("Waiting for attestations...");
            $this->waitForAttestations($detachedFile);
            
            // Write updated timestamp
            FileHandler::writeDetached($detachedFile, $outputPath);
        }
        
        return $outputPath;
    }

    /**
     * Upgrade with redundancy
     */
    public function upgrade(string $otsFilePath, bool $force = false): bool {
        if (!$this->specConfig['redundant_upgrades']) {
            return parent::upgrade($otsFilePath, $force);
        }

        $this->info("Upgrading with redundancy...");

        $detachedFile = FileHandler::readDetached($otsFilePath);
        $timestamp = $detachedFile->getTimestamp();

        // Get upgrade statistics
        $upgradeStats = $this->upgradeManager->getUpgradeStats($timestamp);
        $this->info(sprintf(
            "Upgrade readiness: %d/%d pending attestations ready for upgrade",
            $upgradeStats['upgrade_ready'],
            $upgradeStats['total_pending']
        ));

        if ($upgradeStats['upgrade_ready'] === 0 && !$force) {
            $this->warning("No attestations ready for upgrade at this time");
            return false;
        }

        // Perform redundant upgrades
        $result = $this->upgradeManager->upgradeWithRedundancy($timestamp);
        
        // Update timestamp if upgrades were successful
        if ($result->isSuccessful()) {
            FileHandler::writeDetached($detachedFile, $otsFilePath);
        }

        // Report results
        $summary = $result->getSummary();
        $this->info(sprintf(
            "Upgrade completed: %d successful, %d failed, %d skipped (%.1f%% success rate)",
            $summary['successful'],
            $summary['failed'],
            $summary['skipped'],
            $summary['success_rate'] * 100
        ));

        return $result->isSuccessful();
    }

    /**
     * Get enhanced verification with redundancy analysis
     */
    public function verifyWithRedundancy(string $otsFilePath, string $originalFilePath): array {
        $basicResult = parent::verify($otsFilePath, $originalFilePath);
        
        if (!$this->specConfig['multi_calendar_aggregation']) {
            return $basicResult;
        }

        // Enhance with redundancy analysis
        $detachedFile = FileHandler::readDetached($otsFilePath);
        $timestamp = $detachedFile->getTimestamp();
        
        $redundancyAnalysis = $this->analyzeRedundancy($timestamp);
        $fragilityAssessment = $this->assessFragility($timestamp);
        
        return array_merge($basicResult, [
            'redundancy_analysis' => $redundancyAnalysis,
            'fragility_assessment' => $fragilityAssessment,
            'spec_compliance' => $this->checkSpecCompliance($timestamp)
        ]);
    }

    private function analyzeRedundancy(Timestamp $timestamp): array {
        $attestations = $this->findAllAttestations($timestamp);
        
        $calendarSources = [];
        $blockchainSources = [];
        
        foreach ($attestations as $attestation) {
            if ($attestation instanceof EnhancedPendingAttestation) {
                $calendarSources[$attestation->getCalendarUrl()] = true;
            } elseif ($attestation instanceof \OpenTimestampsPHP\Attestations\BitcoinBlockHeaderAttestation) {
                $blockchainSources['bitcoin'] = true;
            } elseif ($attestation instanceof \OpenTimestampsPHP\Attestations\LitecoinBlockHeaderAttestation) {
                $blockchainSources['litecoin'] = true;
            }
        }
        
        return [
            'calendar_redundancy' => count($calendarSources),
            'blockchain_redundancy' => count($blockchainSources),
            'total_calendar_sources' => array_keys($calendarSources),
            'total_blockchain_sources' => array_keys($blockchainSources),
            'redundancy_score' => $this->calculateRedundancyScore(count($calendarSources), count($blockchainSources))
        ];
    }

    private function assessFragility(Timestamp $timestamp): array {
        $analysis = $this->analyzeRedundancy($timestamp);
        $calendarRedundancy = $analysis['calendar_redundancy'];
        $blockchainRedundancy = $analysis['blockchain_redundancy'];
        
        $fragile = false;
        $reasons = [];
        
        if ($calendarRedundancy < 2) {
            $fragile = true;
            $reasons[] = 'single_calendar_dependency';
        }
        
        if ($blockchainRedundancy === 0) {
            $fragile = true;
            $reasons[] = 'no_blockchain_attestations';
        }
        
        if ($calendarRedundancy < 1 && $blockchainRedundancy === 0) {
            $fragile = true;
            $reasons[] = 'completely_unverified';
        }
        
        return [
            'fragile' => $fragile,
            'reasons' => $reasons,
            'calendar_fragility' => $calendarRedundancy < 2,
            'blockchain_fragility' => $blockchainRedundancy === 0,
            'recommendations' => $this->generateFragilityRecommendations($calendarRedundancy, $blockchainRedundancy)
        ];
    }

    private function checkSpecCompliance(Timestamp $timestamp): array {
        $analysis = $this->analyzeRedundancy($timestamp);
        $fragility = $this->assessFragility($timestamp);
        
        return [
            'multi_calendar_compliant' => $analysis['calendar_redundancy'] >= 2,
            'multi_blockchain_compliant' => $analysis['blockchain_redundancy'] >= 1,
            'fragility_compliant' => !$fragility['fragile'],
            'overall_compliant' => $analysis['calendar_redundancy'] >= 2 && $analysis['blockchain_redundancy'] >= 1,
            'compliance_score' => $this->calculateComplianceScore($analysis, $fragility)
        ];
    }

    private function calculateRedundancyScore(int $calendarRedundancy, int $blockchainRedundancy): float {
        $calendarScore = min($calendarRedundancy / 3, 1.0); // Max score at 3 calendars
        $blockchainScore = min($blockchainRedundancy / 2, 1.0); // Max score at 2 blockchains
        
        return ($calendarScore * 0.6) + ($blockchainScore * 0.4); // Weight calendars higher
    }

    private function calculateComplianceScore(array $analysis, array $fragility): float {
        $redundancyScore = $analysis['redundancy_score'];
        $fragilityPenalty = $fragility['fragile'] ? 0.3 : 0.0;
        
        return max(0, $redundancyScore - $fragilityPenalty);
    }

    private function generateFragilityRecommendations(int $calendarRedundancy, int $blockchainRedundancy): array {
        $recommendations = [];
        
        if ($calendarRedundancy < 2) {
            $recommendations[] = 'Use multi-calendar aggregation when creating timestamps';
            $recommendations[] = 'Configure at least 3 calendar servers for redundancy';
        }
        
        if ($blockchainRedundancy === 0) {
            $recommendations[] = 'Upgrade pending attestations to blockchain verification';
            $recommendations[] = 'Use the upgrade command with redundancy enabled';
        }
        
        if ($calendarRedundancy < 1) {
            $recommendations[] = 'Timestamp has no calendar attestations - consider recreating';
        }
        
        return $recommendations;
    }

    /**
     * Get spec compliance report
     */
    public function getSpecComplianceReport(string $otsFilePath): array {
        $detachedFile = FileHandler::readDetached($otsFilePath);
        $timestamp = $detachedFile->getTimestamp();
        
        $redundancy = $this->analyzeRedundancy($timestamp);
        $fragility = $this->assessFragility($timestamp);
        $compliance = $this->checkSpecCompliance($timestamp);
        
        return [
            'file' => $otsFilePath,
            'redundancy_analysis' => $redundancy,
            'fragility_assessment' => $fragility,
            'spec_compliance' => $compliance,
            'recommendations' => array_merge(
                $fragility['recommendations'] ?? [],
                $compliance['overall_compliant'] ? [] : ['Consider recreating timestamp with spec-compliant client']
            )
        ];
    }
}