<?php
// src/Attestations/EnhancedPendingAttestation.php

namespace OpenTimestampsPHP\Attestations;

class EnhancedPendingAttestation extends PendingAttestation {
    private string $calendarUrl;
    private int $submissionTime;
    private array $upgradeAttempts = [];
    private ?string $preferredUpgradeServer = null;

    public function __construct(string $uri, string $calendarUrl, int $submissionTime) {
        parent::__construct($uri);
        $this->calendarUrl = $calendarUrl;
        $this->submissionTime = $submissionTime;
    }

    public function getCalendarUrl(): string {
        return $this->calendarUrl;
    }

    public function getSubmissionTime(): int {
        return $this->submissionTime;
    }

    public function addUpgradeAttempt(string $server, bool $success, ?string $error = null): void {
        $this->upgradeAttempts[] = [
            'server' => $server,
            'timestamp' => time(),
            'success' => $success,
            'error' => $error
        ];
        
        if ($success && $this->preferredUpgradeServer === null) {
            $this->preferredUpgradeServer = $server;
        }
    }

    public function getUpgradeAttempts(): array {
        return $this->upgradeAttempts;
    }

    public function getLastUpgradeAttempt(): ?array {
        return end($this->upgradeAttempts) ?: null;
    }

    public function getPreferredUpgradeServer(): ?string {
        return $this->preferredUpgradeServer;
    }

    public function getUpgradeSuccessRate(): float {
        if (empty($this->upgradeAttempts)) {
            return 0.0;
        }
        
        $successful = array_filter($this->upgradeAttempts, fn($attempt) => $attempt['success']);
        return count($successful) / count($this->upgradeAttempts);
    }

    public function shouldRetryUpgrade(int $minRetryDelay = 300): bool {
        $lastAttempt = $this->getLastUpgradeAttempt();
        
        if (!$lastAttempt) {
            return true;
        }
        
        // Don't retry too frequently
        if (time() - $lastAttempt['timestamp'] < $minRetryDelay) {
            return false;
        }
        
        // Retry if last attempt failed or success rate is low
        return !$lastAttempt['success'] || $this->getUpgradeSuccessRate() < 0.5;
    }

    public function getUpgradeRecommendation(): string {
        $successRate = $this->getUpgradeSuccessRate();
        
        if ($successRate >= 0.8) {
            return 'high_reliability';
        } elseif ($successRate >= 0.5) {
            return 'medium_reliability';
        } else {
            return 'low_reliability';
        }
    }
}