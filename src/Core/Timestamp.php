<?php
// src/Core/Timestamp.php

namespace OpenTimestampsPHP\Core;

class Timestamp {
    private array $attestations = [];
    private array $ops = [];
    private ?string $msg = null;

    public function __construct(?string $msg = null) {
        $this->msg = $msg;
    }

    public function addAttestation($attestation): void {
        $this->attestations[] = $attestation;
    }

    public function addOperation(Op $op, Timestamp $timestamp): void {
        $this->ops[] = [$op, $timestamp];
    }

    public function getMsg(): ?string {
        return $this->msg;
    }

    public function setMsg(string $msg): void {
        $this->msg = $msg;
    }

    public function getAttestations(): array {
        return $this->attestations;
    }

    public function getOps(): array {
        return $this->ops;
    }

    public function __toString(): string {
        $result = "Timestamp(";
        if ($this->msg) {
            $result .= "msg: " . bin2hex($this->msg);
        }
        $result .= ", ops: " . count($this->ops);
        $result .= ", attestations: " . count($this->attestations);
        $result .= ")";
        return $result;
    }

    /**
     * Get detailed tree representation for visualization
     */
    public function toTreeString(string $prefix = "", bool $isLast = true): string {
        $result = "";
        
        // Current node
        $connector = $isLast ? "└── " : "├── ";
        $result .= $prefix . $connector . $this->getNodeDescription() . "\n";

        // Prepare prefix for children
        $childPrefix = $prefix . ($isLast ? "    " : "│   ");

        // Operations (branches)
        $opCount = count($this->ops);
        foreach ($this->ops as $i => [$op, $subTimestamp]) {
            $isLastOp = ($i === $opCount - 1) && empty($this->attestations);
            $result .= $childPrefix . "├── Operation: " . $op . "\n";
            $result .= $subTimestamp->toTreeString($childPrefix . "│   ", $isLastOp);
        }

        // Attestations (leaves)
        $attCount = count($this->attestations);
        foreach ($this->attestations as $i => $attestation) {
            $isLastAtt = $i === $attCount - 1;
            $result .= $childPrefix . ($isLastAtt ? "└── " : "├── ") 
                     . "Attestation: " . $attestation . "\n";
        }

        return $result;
    }

    private function getNodeDescription(): string {
        $parts = [];
        
        if ($this->msg) {
            $parts[] = "msg: " . substr(bin2hex($this->msg), 0, 16) . "...";
        }
        
        $parts[] = "ops: " . count($this->ops);
        $parts[] = "atts: " . count($this->attestations);
        
        return "Timestamp(" . implode(", ", $parts) . ")";
    }

    /**
     * Get proof statistics
     */
    public function getProofStats(): array {
        $stats = [
            'total_operations' => 0,
            'total_attestations' => 0,
            'max_depth' => 0,
            'operation_types' => [],
            'attestation_types' => [],
            'merkle_paths' => 0
        ];

        $this->calculateStats($stats, 0);
        return $stats;
    }

    private function calculateStats(array &$stats, int $depth): void {
        $stats['max_depth'] = max($stats['max_depth'], $depth);
        $stats['total_operations'] += count($this->ops);
        $stats['total_attestations'] += count($this->attestations);

        // Count operation types
        foreach ($this->ops as [$op, $subTimestamp]) {
            $opType = get_class($op);
            if (!isset($stats['operation_types'][$opType])) {
                $stats['operation_types'][$opType] = 0;
            }
            $stats['operation_types'][$opType]++;
            $subTimestamp->calculateStats($stats, $depth + 1);
        }

        // Count attestation types and merkle paths
        foreach ($this->attestations as $attestation) {
            $attType = get_class($attestation);
            if (!isset($stats['attestation_types'][$attType])) {
                $stats['attestation_types'][$attType] = 0;
            }
            $stats['attestation_types'][$attType]++;
            $stats['merkle_paths']++;
        }
    }
}