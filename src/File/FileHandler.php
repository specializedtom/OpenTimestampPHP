<?php
// src/File/FileHandler.php

namespace OpenTimestampsPHP\File;

use OpenTimestampsPHP\Core\Timestamp;
use OpenTimestampsPHP\Serialization\{Serializer, Deserializer};
use OpenTimestampsPHP\File\DetachedTimestampFile;

class FileHandler {
    private const NONCE_LENGTH = 16; // 128-bit nonce for privacy
    
    /**
     * Create a detached timestamp file with privacy protection
     * Uses a random nonce to prevent calendar servers from learning the file hash
     */
    public static function createDetachedForFile(string $originalFilePath): DetachedTimestampFile {
        if (!file_exists($originalFilePath)) {
            throw new \Exception("Original file not found: $originalFilePath");
        }

        // Step 1: Compute file hash
        $fileHash = hash_file('sha256', $originalFilePath, true);
        if ($fileHash === false) {
            throw new \Exception("Cannot hash file: $originalFilePath");
        }

        // Step 2: Generate random nonce for privacy
        $nonce = random_bytes(self::NONCE_LENGTH);
        
        // Step 3: Create commitment (nonce + file hash)
        $commitment = $nonce . $fileHash;

        // Step 4: Create timestamp with the commitment as message
        $timestamp = new Timestamp($commitment);
        
        // Store nonce in the detached file for later verification
        return new DetachedTimestampFile($timestamp, basename($originalFilePath), $nonce);
    }

    /**
     * Read a detached timestamp file (.ots) with nonce support
     */
    public static function readDetached(string $filePath): DetachedTimestampFile {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: $filePath");
        }

        $data = file_get_contents($filePath);
        if ($data === false) {
            throw new \Exception("Cannot read file: $filePath");
        }

        // Check for magic bytes
        if (substr($data, 0, 4) !== "\x00OpenTimestamps\x00") {
            throw new \Exception("Invalid OpenTimestamps file: missing magic bytes");
        }

        // Check for extended format with nonce (version byte 0x01)
        $version = ord($data[16]);
        $hasNonce = ($version === 0x01);
        
        $nonce = null;
        $timestampData = '';

        if ($hasNonce) {
            // Extended format: magic(16) + version(1) + nonce_length(1) + nonce(n) + timestamp_data
            $nonceLength = ord($data[17]);
            $nonce = substr($data, 18, $nonceLength);
            $timestampData = substr($data, 18 + $nonceLength);
        } else {
            // Legacy format: magic(16) + version(1) + timestamp_data  
            $timestampData = substr($data, 17);
        }

        $timestamp = Deserializer::deserialize($timestampData);
        
        return new DetachedTimestampFile($timestamp, basename($filePath, '.ots'), $nonce);
    }

    /**
     * Write a detached timestamp file with nonce support
     */
    public static function writeDetached(DetachedTimestampFile $detachedFile, string $filePath): void {
        $serializer = new Serializer();
        $timestampData = $serializer->serialize($detachedFile->getTimestamp());
        
        $nonce = $detachedFile->getNonce();
        
        if ($nonce !== null) {
            // Extended format with nonce
            $nonceLength = strlen($nonce);
            $fileData = "\x00OpenTimestamps\x00\x01" . chr($nonceLength) . $nonce . $timestampData;
        } else {
            // Legacy format without nonce
            $fileData = "\x00OpenTimestamps\x00\x00" . $timestampData;
        }
        
        $result = file_put_contents($filePath, $fileData);
        if ($result === false) {
            throw new \Exception("Cannot write file: $filePath");
        }
    }

    /**
     * Verify a detached timestamp against the original file with nonce support
     */
    public static function verifyDetached(DetachedTimestampFile $detachedFile, string $originalFilePath): bool {
        if (!file_exists($originalFilePath)) {
            throw new \Exception("Original file not found: $originalFilePath");
        }

        // Compute file hash
        $fileHash = hash_file('sha256', $originalFilePath, true);
        if ($fileHash === false) {
            throw new \Exception("Cannot hash file: $originalFilePath");
        }

        $timestamp = $detachedFile->getTimestamp();
        $commitment = $timestamp->getMsg();
        $nonce = $detachedFile->getNonce();

        if ($nonce !== null) {
            // New format: commitment should be nonce + file_hash
            $expectedCommitment = $nonce . $fileHash;
            return $commitment === $expectedCommitment;
        } else {
            // Legacy format: commitment is just the file hash
            return $commitment === $fileHash;
        }
    }

    /**
     * Extract the original file hash from a timestamp (for verification)
     * This removes the nonce to get the actual file hash
     */
    public static function extractFileHash(DetachedTimestampFile $detachedFile): string {
        $timestamp = $detachedFile->getTimestamp();
        $commitment = $timestamp->getMsg();
        $nonce = $detachedFile->getNonce();

        if ($nonce !== null) {
            // Remove nonce to get the original file hash
            return substr($commitment, strlen($nonce));
        } else {
            // Legacy format: commitment is the file hash
            return $commitment;
        }
    }

    /**
     * Get the nonce from the commitment (for debugging/info)
     */
    public static function extractNonce(DetachedTimestampFile $detachedFile): ?string {
        return $detachedFile->getNonce();
    }

    /**
     * Read an attached timestamp file (file with timestamp appended)
     */
    public static function readAttached(string $filePath): DetachedTimestampFile {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: $filePath");
        }

        $data = file_get_contents($filePath);
        if ($data === false) {
            throw new \Exception("Cannot read file: $filePath");
        }

        // Look for the OTS magic bytes from the end of the file
        $magic = "\x00OpenTimestamps\x00";
        $magicPos = strrpos($data, $magic);
        
        if ($magicPos === false) {
            throw new \Exception("No OpenTimestamps data found in file");
        }

        // Extract the original file data and timestamp data
        $originalFileData = substr($data, 0, $magicPos);
        $timestampData = substr($data, $magicPos + 17); // Skip magic + version
        
        // Check for extended format with nonce
        $version = ord($data[$magicPos + 16]);
        $hasNonce = ($version === 0x01);
        
        $nonce = null;
        if ($hasNonce) {
            $nonceLength = ord($timestampData[0]);
            $nonce = substr($timestampData, 1, $nonceLength);
            $timestampData = substr($timestampData, 1 + $nonceLength);
        }

        // Create timestamp from the serialized data
        $timestamp = Deserializer::deserialize($timestampData);
        
        // Set the commitment in the timestamp
        $fileHash = hash('sha256', $originalFileData, true);
        if ($nonce !== null) {
            $timestamp->setMsg($nonce . $fileHash);
        } else {
            $timestamp->setMsg($fileHash);
        }
        
        return new DetachedTimestampFile($timestamp, basename($filePath), $nonce);
    }

    /**
     * Write an attached timestamp file (append timestamp to original file)
     */
    public static function writeAttached(DetachedTimestampFile $detachedFile, string $originalFilePath, string $outputPath): void {
        if (!file_exists($originalFilePath)) {
            throw new \Exception("Original file not found: $originalFilePath");
        }

        $originalData = file_get_contents($originalFilePath);
        if ($originalData === false) {
            throw new \Exception("Cannot read original file: $originalFilePath");
        }

        $serializer = new Serializer();
        $timestampData = $serializer->serialize($detachedFile->getTimestamp());
        
        $nonce = $detachedFile->getNonce();
        
        // Construct attached file
        if ($nonce !== null) {
            // Extended format with nonce
            $nonceLength = strlen($nonce);
            $attachedData = $originalData . "\x00OpenTimestamps\x00\x01" . chr($nonceLength) . $nonce . $timestampData;
        } else {
            // Legacy format without nonce
            $attachedData = $originalData . "\x00OpenTimestamps\x00\x00" . $timestampData;
        }
        
        $result = file_put_contents($outputPath, $attachedData);
        if ($result === false) {
            throw new \Exception("Cannot write attached file: $outputPath");
        }
    }

    /**
     * Check if a file has an attached timestamp
     */
    public static function hasAttachedTimestamp(string $filePath): bool {
        if (!file_exists($filePath)) {
            return false;
        }

        $data = file_get_contents($filePath);
        if ($data === false) {
            return false;
        }

        return strpos($data, "\x00OpenTimestamps\x00") !== false;
    }

    /**
     * Extract original file from attached timestamp file
     */
    public static function extractOriginalFromAttached(string $attachedFilePath, string $outputPath): void {
        if (!file_exists($attachedFilePath)) {
            throw new \Exception("Attached file not found: $attachedFilePath");
        }

        $data = file_get_contents($attachedFilePath);
        if ($data === false) {
            throw new \Exception("Cannot read attached file: $attachedFilePath");
        }

        $magic = "\x00OpenTimestamps\x00";
        $magicPos = strrpos($data, $magic);
        
        if ($magicPos === false) {
            throw new \Exception("No OpenTimestamps data found in file");
        }

        $originalData = substr($data, 0, $magicPos);
        
        $result = file_put_contents($outputPath, $originalData);
        if ($result === false) {
            throw new \Exception("Cannot write original file: $outputPath");
        }
    }
}