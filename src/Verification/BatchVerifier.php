<?php
// src/Verification/BatchVerifier.php

namespace OpenTimestampsPHP\Verification;

class BatchVerifier {
    private AdvancedTimestampVerifier $verifier;
    private array $results;
    private int $batchStartTime;

    public function __construct(array $verificationOptions = []) {
        $this->verifier = new AdvancedTimestampVerifier($verificationOptions);
        $this->results = [];
    }

    /**
     * Verify multiple timestamps in batch
     */
    public function verifyBatch(array $timestampTasks): array {
        $this->batchStartTime = time();
        $batchId = uniqid('batch_', true);

        $batchResult = [
            'batch_id' => $batchId,
            'start_time' => date('c', $this->batchStartTime),
            'total_tasks' => count($timestampTasks),
            'completed_tasks' => 0,
            'successful_verifications' => 0,
            'failed_verifications' => 0,
            'task_results' => [],
            'summary_statistics' => [],
            'batch_duration_seconds' => 0
        ];

        foreach ($timestampTasks as $taskId => $task) {
            $taskResult = $this->verifySingleTask($taskId, $task);
            $batchResult['task_results'][$taskId] = $taskResult;

            if ($taskResult['overall_valid']) {
                $batchResult['successful_verifications']++;
            } else {
                $batchResult['failed_verifications']++;
            }

            $batchResult['completed_tasks']++;
        }

        $batchResult['batch_duration_seconds'] = time() - $this->batchStartTime;
        $batchResult['summary_statistics'] = $this->generateBatchStatistics($batchResult);

        return $batchResult;
    }

    private function verifySingleTask(string $taskId, array $task): array {
        $taskResult = [
            'task_id' => $taskId,
            'description' => $task['description'] ?? 'Unknown task',
            'start_time' => date('c'),
            'overall_valid' => false,
            'error' => null,
            'verification_result' => null
        ];

        try {
            if (!isset($task['timestamp']) || !isset($task['original_message'])) {
                throw new \Exception("Missing timestamp or original message in task");
            }

            $verificationOptions = $task['verification_options'] ?? [];
            
            $taskResult['verification_result'] = $this->verifier->verifyComprehensive(
                $task['timestamp'],
                $task['original_message']
            );

            $taskResult['overall_valid'] = $taskResult['verification_result']['overall_valid'];
            $taskResult['end_time'] = date('c');

        } catch (\Exception $e) {
            $taskResult['error'] = $e->getMessage();
            $taskResult['end_time'] = date('c');
        }

        return $taskResult;
    }

    private function generateBatchStatistics(array $batchResult): array {
        $stats = [
            'success_rate' => 0,
            'average_confidence' => 0,
            'security_level_distribution' => [],
            'common_issues' => []
        ];

        $totalConfidence = 0;
        $confidenceCount = 0;
        $securityLevels = [];
        $commonErrors = [];

        foreach ($batchResult['task_results'] as $taskResult) {
            if ($taskResult['verification_result']) {
                $consensus = $taskResult['verification_result']['components']['consensus'] ?? [];
                $security = $taskResult['verification_result']['security_assessment'] ?? [];

                if (isset($consensus['confidence_score'])) {
                    $totalConfidence += $consensus['confidence_score'];
                    $confidenceCount++;
                }

                $securityLevel = $security['security_level'] ?? 'unknown';
                if (!isset($securityLevels[$securityLevel])) {
                    $securityLevels[$securityLevel] = 0;
                }
                $securityLevels[$securityLevel]++;

                // Collect common issues
                if (!$taskResult['overall_valid'] && isset($taskResult['verification_result']['recommendations'])) {
                    foreach ($taskResult['verification_result']['recommendations'] as $rec) {
                        if (strpos($rec, 'INVALID:') === 0 || strpos($rec, 'CRITICAL:') === 0) {
                            if (!isset($commonErrors[$rec])) {
                                $commonErrors[$rec] = 0;
                            }
                            $commonErrors[$rec]++;
                        }
                    }
                }
            }
        }

        if ($batchResult['total_tasks'] > 0) {
            $stats['success_rate'] = ($batchResult['successful_verifications'] / $batchResult['total_tasks']) * 100;
        }

        if ($confidenceCount > 0) {
            $stats['average_confidence'] = $totalConfidence / $confidenceCount;
        }

        $stats['security_level_distribution'] = $securityLevels;
        
        // Sort common errors by frequency
        arsort($commonErrors);
        $stats['common_issues'] = array_slice($commonErrors, 0, 5); // Top 5 issues

        return $stats;
    }

    /**
     * Generate batch verification report
     */
    public function generateReport(array $batchResult): string {
        $report = "BATCH VERIFICATION REPORT\n";
        $report .= "=======================\n\n";
        
        $report .= "Batch ID: {$batchResult['batch_id']}\n";
        $report .= "Start Time: {$batchResult['start_time']}\n";
        $report .= "Duration: {$batchResult['batch_duration_seconds']} seconds\n";
        $report .= "Total Tasks: {$batchResult['total_tasks']}\n";
        $report .= "Successful: {$batchResult['successful_verifications']}\n";
        $report .= "Failed: {$batchResult['failed_verifications']}\n";
        $report .= "Success Rate: " . number_format($batchResult['summary_statistics']['success_rate'], 2) . "%\n\n";

        $report .= "SECURITY LEVEL DISTRIBUTION:\n";
        foreach ($batchResult['summary_statistics']['security_level_distribution'] as $level => $count) {
            $percentage = ($count / $batchResult['total_tasks']) * 100;
            $report .= "  - $level: $count (" . number_format($percentage, 1) . "%)\n";
        }

        if (!empty($batchResult['summary_statistics']['common_issues'])) {
            $report .= "\nTOP ISSUES:\n";
            foreach ($batchResult['summary_statistics']['common_issues'] as $issue => $count) {
                $report .= "  - $issue (occurred $count times)\n";
            }
        }

        $report .= "\nDETAILED RESULTS:\n";
        $report .= "================\n\n";

        foreach ($batchResult['task_results'] as $taskId => $taskResult) {
            $status = $taskResult['overall_valid'] ? 'VALID' : 'INVALID';
            $report .= "$taskId: $status - {$taskResult['description']}\n";
            
            if ($taskResult['error']) {
                $report .= "  ERROR: {$taskResult['error']}\n";
            }
            
            if ($taskResult['verification_result']) {
                $confidence = $taskResult['verification_result']['components']['consensus']['confidence_score'] ?? 0;
                $security = $taskResult['verification_result']['security_assessment']['security_level'] ?? 'unknown';
                $report .= "  Confidence: " . number_format($confidence * 100, 1) . "%, Security: $security\n";
            }
            
            $report .= "\n";
        }

        return $report;
    }
}