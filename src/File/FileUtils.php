<?php
// src/File/FileUtils.php

namespace OpenTimestampsPHP\File;

class FileUtils {
    /**
     * Calculate file hash using specified algorithm
     */
    public static function calculateFileHash(string $filePath, string $algorithm = 'sha256'): string {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: $filePath");
        }

        $hash = hash_file($algorithm, $filePath, true);
        if ($hash === false) {
            throw new \Exception("Cannot calculate hash for file: $filePath");
        }

        return $hash;
    }

    /**
     * Get file size in bytes
     */
    public static function getFileSize(string $filePath): int {
        $size = filesize($filePath);
        if ($size === false) {
            throw new \Exception("Cannot get file size: $filePath");
        }
        return $size;
    }

    /**
     * Check if path is a detached timestamp file (.ots)
     */
    public static function isDetachedTimestampFile(string $filePath): bool {
        if (!file_exists($filePath)) {
            return false;
        }

        // Check extension
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'ots') {
            return false;
        }

        // Check magic bytes
        $data = file_get_contents($filePath);
        if ($data === false) {
            return false;
        }

        return substr($data, 0, 4) === "\x00OpenTimestamps\x00";
    }

    /**
     * Create a backup of a file
     */
    public static function createBackup(string $filePath, string $backupSuffix = '.bak'): string {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: $filePath");
        }

        $backupPath = $filePath . $backupSuffix;
        
        if (!copy($filePath, $backupPath)) {
            throw new \Exception("Cannot create backup: $backupPath");
        }

        return $backupPath;
    }

    /**
     * Safely write to a file with atomic replacement
     */
    public static function atomicWrite(string $filePath, string $data): void {
        $tempPath = $filePath . '.tmp';
        
        // Write to temporary file
        $result = file_put_contents($tempPath, $data);
        if ($result === false) {
            throw new \Exception("Cannot write temporary file: $tempPath");
        }
        
        // Atomically replace the original file
        if (!rename($tempPath, $filePath)) {
            // Clean up temp file on failure
            unlink($tempPath);
            throw new \Exception("Cannot atomically replace file: $filePath");
        }
    }
}