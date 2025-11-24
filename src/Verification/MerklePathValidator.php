<?php
// src/Verification/MerklePathValidator.php

namespace OpenTimestampsPHP\Verification;

use OpenTimestampsPHP\Core\Timestamp;
use OpenTimestampsPHP\Core\Op;

class MerklePathValidator {
    /**
     * Validate the entire Merkle path from leaf to root attestations
     */
    public function validateMerklePath(Timestamp $timestamp, string $leafMessage): array {
        $result = [
            'valid' => false,
            'errors' => [],
            'warnings' => [],
            'path_integrity' => true,
            'leaf_to_root_paths' => [],
            'root_attestations' => []
        ];

        try {
            // Find all root attestations (attestations at the end of operation chains)
            $rootAttestations = $this->findRootAttestations($timestamp, $leafMessage);
            $result['root_attestations'] = $rootAttestations;

            if (empty($rootAttestations)) {
                $result['errors'][] = 'No root attestations found in timestamp';
                return $result;
            }

            // Validate each Merkle path
            foreach ($rootAttestations as $attestationData) {
                $pathResult = $this->validateSingleMerklePath(
                    $attestationData['path'],
                    $leafMessage,
                    $attestationData['root_message']
                );

                $result['leaf_to_root_paths'][] = $pathResult;

                if (!$pathResult['valid']) {
                    $result['path_integrity'] = false;
                    $result['errors'] = array_merge($result['errors'], $pathResult['errors']);
                }
            }

            $result['valid'] = $result['path_integrity'] && count($rootAttestations) > 0;

        } catch (\Exception $e) {
            $result['errors'][] = 'Merkle path validation failed: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Find all root attestations and their Merkle paths
     */
    private function findRootAttestations(Timestamp $timestamp, string $currentMessage, array $currentPath = []): array {
        $rootAttestations = [];

        // If this timestamp has attestations and no operations, it's a root
        if (!empty($timestamp->getAttestations()) && empty($timestamp->getOps())) {
            foreach ($timestamp->getAttestations() as $attestation) {
                $rootAttestations[] = [
                    'attestation' => $attestation,
                    'root_message' => $currentMessage,
                    'path' => $currentPath,
                    'path_length' => count($currentPath)
                ];
            }
        }

        // Recursively search through operations
        foreach ($timestamp->getOps() as [$op, $subTimestamp]) {
            $newMessage = $op->call($currentMessage);
            $newPath = array_merge($currentPath, [
                'operation' => (string)$op,
                'input_message' => bin2hex($currentMessage),
                'output_message' => bin2hex($newMessage)
            ]);

            $subRoots = $this->findRootAttestations($subTimestamp, $newMessage, $newPath);
            $rootAttestations = array_merge($rootAttestations, $subRoots);
        }

        return $rootAttestations;
    }

    /**
     * Validate a single Merkle path by recomputing
     */
    private function validateSingleMerklePath(array $path, string $leafMessage, string $expectedRoot): array {
        $result = [
            'valid' => false,
            'computed_root' => '',
            'expected_root' => bin2hex($expectedRoot),
            'steps' => [],
            'errors' => []
        ];

        $currentMessage = $leafMessage;
        $result['steps'][] = [
            'type' => 'leaf',
            'message_hex' => bin2hex($currentMessage),
            'description' => 'Starting leaf message'
        ];

        try {
            foreach ($path as $step) {
                if (is_array($step) && isset($step['operation'])) {
                    // This is an operation step from our enhanced path tracking
                    $op = $this->recreateOperationFromString($step['operation']);
                    $currentMessage = $op->call($currentMessage);

                    $result['steps'][] = [
                        'type' => 'operation',
                        'operation' => $step['operation'],
                        'input_hex' => $step['input_message'],
                        'output_hex' => bin2hex($currentMessage),
                        'valid' => $step['output_message'] === bin2hex($currentMessage)
                    ];

                    if ($step['output_message'] !== bin2hex($currentMessage)) {
                        $result['errors'][] = "Operation '{$step['operation']}' produced unexpected output";
                    }
                }
            }

            $result['computed_root'] = bin2hex($currentMessage);
            $result['valid'] = $result['computed_root'] === $result['expected_root'];

            if (!$result['valid']) {
                $result['errors'][] = sprintf(
                    "Merkle path mismatch: computed %s, expected %s",
                    $result['computed_root'],
                    $result['expected_root']
                );
            }

        } catch (\Exception $e) {
            $result['errors'][] = 'Path computation failed: ' . $e->getMessage();
        }

        return $result;
    }

    private function recreateOperationFromString(string $opString): Op {
        // Parse operation string and recreate the operation object
        // This is a simplified implementation - you'd want more robust parsing
        if (strpos($opString, 'append:') === 0) {
            $data = hex2bin(substr($opString, 7));
            return new \OpenTimestampsPHP\Ops\OpAppend($data);
        } elseif (strpos($opString, 'prepend:') === 0) {
            $data = hex2bin(substr($opString, 8));
            return new \OpenTimestampsPHP\Ops\OpPrepend($data);
        } elseif ($opString === 'sha256') {
            return new \OpenTimestampsPHP\Ops\OpSHA256();
        } elseif ($opString === 'ripemd160') {
            return new \OpenTimestampsPHP\Ops\OpRIPEMD160();
        } elseif ($opString === 'sha1') {
            return new \OpenTimestampsPHP\Ops\OpSHA1();
        }

        throw new \Exception("Unknown operation: $opString");
    }

    /**
     * Analyze Merkle tree structure for security properties
     */
    public function analyzeMerkleStructure(Timestamp $timestamp, string $leafMessage): array {
        $analysis = [
            'tree_depth' => 0,
            'branching_factor' => 0,
            'operation_distribution' => [],
            'path_redundancy' => 0,
            'security_indicators' => []
        ];

        $rootAttestations = $this->findRootAttestations($timestamp, $leafMessage);
        $analysis['root_attestations_count'] = count($rootAttestations);

        // Calculate tree depth
        $maxDepth = 0;
        $operationCounts = [];
        $uniquePaths = [];

        foreach ($rootAttestations as $attestation) {
            $pathLength = $attestation['path_length'];
            $maxDepth = max($maxDepth, $pathLength);

            // Count operations
            foreach ($attestation['path'] as $step) {
                if (is_array($step) && isset($step['operation'])) {
                    $op = $step['operation'];
                    if (!isset($operationCounts[$op])) {
                        $operationCounts[$op] = 0;
                    }
                    $operationCounts[$op]++;
                }
            }

            // Track unique paths
            $pathHash = md5(serialize($attestation['path']));
            $uniquePaths[$pathHash] = true;
        }

        $analysis['tree_depth'] = $maxDepth;
        $analysis['operation_distribution'] = $operationCounts;
        $analysis['unique_paths_count'] = count($uniquePaths);
        $analysis['path_redundancy'] = count($rootAttestations) / max(1, count($uniquePaths));

        // Security indicators
        if ($analysis['root_attestations_count'] >= 3) {
            $analysis['security_indicators'][] = 'high_redundancy';
        }
        if ($analysis['tree_depth'] >= 4) {
            $analysis['security_indicators'][] = 'sufficient_depth';
        }
        if ($analysis['path_redundancy'] > 1.5) {
            $analysis['security_indicators'][] = 'path_diversity';
        }
        if (isset($operationCounts['sha256']) && $operationCounts['sha256'] >= 2) {
            $analysis['security_indicators'][] = 'crypto_operations';
        }

        return $analysis;
    }
}