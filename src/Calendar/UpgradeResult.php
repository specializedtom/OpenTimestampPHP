<?php
// src/Calendar/UpgradeResult.php

namespace OpenTimestampsPHP\Calendar;

use OpenTimestampsPHP\Attestations\EnhancedPendingAttestation;

class UpgradeResult {
    private array $successful = [];
    private array $failed = [];
    private array $skipped = [];
    private array $errors = [];

    public function addSuccess(EnhancedPendingAttestation $attestation, string $server): void {
        $this->successful[] = [
            'attestation' => $attestation,
            'server' => $server,
            'timestamp' => time()
        ];
    }

    public function addFailure(EnhancedPendingAttestation $attestation, string $server, string $error): void {
        $this->failed[] = [
            'attestation' => $attestation,
            'server' => $server,
            'error' => $error,
            'timestamp' => time()
        ];
        $this->errors[] = $error;
    }

    public function addSkipped(EnhancedPendingAttestation $attestation, string $reason): void {
        $this->skipped[] = [
            'attestation' => $attestation,
            'reason' => $reason,
            'timestamp' => time()
        ];
    }

    public function merge(UpgradeResult $other): void {
        $this->successful = array_merge($this->successful, $other->successful);
        $this->failed = array_merge($this->failed, $other->failed);
        $this->skipped = array_merge($this->skipped, $other->skipped);
        $this->errors = array_merge($this->errors, $other->errors);
    }

    public function isSuccessful(): bool {
        return !empty($this->successful);
    }

    public function hasFailures(): bool {
        return !empty($this->failed);
    }

    public function getSuccessfulCount(): int {
        return count($this->successful);
    }

    public function getFailedCount(): int {
        return count($this->failed);
    }

    public function getSkippedCount(): int {
        return count($this->skipped);
    }

    public function getSuccessRate(): float {
        $total = $this->getTotalAttempted();
        return $total > 0 ? $this->getSuccessfulCount() / $total : 0.0;
    }

    public function getTotalAttempted(): int {
        return $this->getSuccessfulCount() + $this->getFailedCount();
    }

    public function getSummary(): array {
        return [
            'successful' => $this->getSuccessfulCount(),
            'failed' => $this->getFailedCount(),
            'skipped' => $this->getSkippedCount(),
            'success_rate' => $this->getSuccessRate(),
            'total_attempted' => $this->getTotalAttempted(),
            'errors' => array_unique($this->errors)
        ];
    }

    public function getDetailedReport(): array {
        return [
            'successful' => $this->successful,
            'failed' => $this->failed,
            'skipped' => $this->skipped,
            'summary' => $this->getSummary()
        ];
    }
}