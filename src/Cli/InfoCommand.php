<?php
// src/Cli/InfoCommand.php

namespace OpenTimestampsPHP\Cli;

use OpenTimestampsPHP\Visualization\ProofVisualizer;
use OpenTimestampsPHP\File\FileHandler;

class InfoCommand {
    private OutputFormatter $formatter;

    public function __construct(OutputFormatter $formatter) {
        $this->formatter = $formatter;
    }

    public function execute(string $filePath, array $options = []): int {
        try {
            if (!file_exists($filePath)) {
                $this->formatter->error("File not found: {$filePath}");
                return 1;
            }

            $this->formatter->title("Timestamp Information");
            $this->formatter->info("File: " . basename($filePath));

            // Read timestamp file
            if (FileHandler::hasAttachedTimestamp($filePath)) {
                $detachedFile = FileHandler::readAttached($filePath);
                $this->formatter->info("Type: Attached timestamp");
            } else {
                $detachedFile = FileHandler::readDetached($filePath);
                $this->formatter->info("Type: Detached timestamp");
            }

            $timestamp = $detachedFile->getTimestamp();
            $originalMessage = $timestamp->getMsg() ?? '';

            // Create visualizer
            $visualizer = new ProofVisualizer($timestamp, $originalMessage);

            // Display based on options
            if (isset($options['tree']) || isset($options['verbose'])) {
                $this->displayTreeView($visualizer);
            } elseif (isset($options['compact'])) {
                $this->displayCompactView($visualizer);
            } elseif (isset($options['json'])) {
                $this->displayJsonOutput($visualizer);
            } else {
                $this->displaySummary($visualizer);
            }

            return 0;

        } catch (\Exception $e) {
            $this->formatter->error("Error: " . $e->getMessage());
            if (isset($options['verbose'])) {
                $this->formatter->error("Stack trace: " . $e->getTraceAsString());
            }
            return 1;
        }
    }

    private function displaySummary(ProofVisualizer $visualizer): void {
        $summary = $visualizer->generateProofSummary();

        $this->formatter->subtitle("Proof Structure");
        echo "Total operations: " . $summary['proof_structure']['total_operations'] . "\n";
        echo "Total attestations: " . $summary['proof_structure']['total_attestations'] . "\n";
        echo "Merkle paths: " . $summary['proof_structure']['merkle_paths'] . "\n";
        echo "Maximum depth: " . $summary['proof_structure']['max_depth'] . "\n";

        $this->formatter->subtitle("Security Assessment");
        $security = $summary['security_assessment'];
        echo "Level: " . strtoupper($security['level']) . "\n";
        echo "Score: " . $security['score'] . "/100\n";
        
        if (!empty($security['factors'])) {
            echo "Factors: " . implode(', ', $security['factors']) . "\n";
        }

        $this->formatter->subtitle("Attestations");
        foreach ($summary['attestation_breakdown'] as $att) {
            $status = $att['status'] === 'verified' ? '✓' : '⧖';
            echo "  {$status} {$att['attestation']}: {$att['count']} ({$att['percentage']}%)\n";
        }

        $this->formatter->subtitle("Recommendations");
        $recommendations = $visualizer->generateDetailedAnalysis()['recommendations'];
        if (empty($recommendations)) {
            echo "  ✓ Proof structure looks good!\n";
        } else {
            foreach ($recommendations as $rec) {
                echo "  • {$rec}\n";
            }
        }

        echo "\n" . $this->formatter->info("Use --tree for detailed tree view or --compact for brief overview");
    }

    private function displayTreeView(ProofVisualizer $visualizer): void {
        $this->formatter->subtitle("Proof Tree Structure");
        $summary = $visualizer->generateProofSummary();
        echo $summary['tree_visualization'] . "\n";

        $this->formatter->subtitle("Tree Statistics");
        $analysis = $visualizer->generateDetailedAnalysis();
        
        echo "Total paths: " . $analysis['merkle_analysis']['total_paths'] . "\n";
        if (isset($analysis['merkle_analysis']['average_path_length'])) {
            echo "Average path length: " . round($analysis['merkle_analysis']['average_path_length'], 2) . " operations\n";
            echo "Max path length: " . $analysis['merkle_analysis']['max_path_length'] . " operations\n";
        }

        $this->formatter->subtitle("Operations Used");
        foreach ($analysis['operation_breakdown'] as $op) {
            echo "  • {$op['operation']}: {$op['count']} - {$op['description']}\n";
        }
    }

    private function displayCompactView(ProofVisualizer $visualizer): void {
        echo $visualizer->generateCompactView();
    }

    private function displayJsonOutput(ProofVisualizer $visualizer): void {
        $analysis = $visualizer->generateDetailedAnalysis();
        echo json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
}