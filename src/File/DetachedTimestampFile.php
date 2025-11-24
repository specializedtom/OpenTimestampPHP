<?php
// src/File/DetachedTimestampFile.php

namespace OpenTimestampsPHP\File;

use OpenTimestampsPHP\Core\Timestamp;

class DetachedTimestampFile {
    private Timestamp $timestamp;
    private string $originalFilename;
    private ?string $filePath;
    private ?string $nonce;

    public function __construct(Timestamp $timestamp, string $originalFilename, ?string $nonce = null, ?string $filePath = null) {
        $this->timestamp = $timestamp;
        $this->originalFilename = $originalFilename;
        $this->nonce = $nonce;
        $this->filePath = $filePath;
    }

    public function getTimestamp(): Timestamp {
        return $this->timestamp;
    }

    public function setTimestamp(Timestamp $timestamp): void {
        $this->timestamp = $timestamp;
    }

    public function getOriginalFilename(): string {
        return $this->originalFilename;
    }

    public function getFilePath(): ?string {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): void {
        $this->filePath = $filePath;
    }

    public function getNonce(): ?string {
        return $this->nonce;
    }

    public function setNonce(?string $nonce): void {
        $this->nonce = $nonce;
    }

    /**
     * Get the commitment (nonce + file hash) used for this timestamp
     */
    public function getCommitment(): string {
        return $this->timestamp->getMsg() ?? '';
    }

    /**
     * Get the actual file hash (without nonce)
     */
    public function getFileHash(): string {
        if ($this->nonce === null) {
            return $this->timestamp->getMsg() ?? '';
        }
        return substr($this->timestamp->getMsg() ?? '', strlen($this->nonce));
    }

    /**
     * Get the suggested .ots filename for this detached file
     */
    public function getSuggestedOtsFilename(): string {
        return $this->originalFilename . '.ots';
    }

    /**
     * Get the suggested filename for attached timestamp
     */
    public function getSuggestedAttachedFilename(): string {
        return $this->originalFilename . '.otsed';
    }

    public function __toString(): string {
        $nonceInfo = $this->nonce ? "with nonce" : "without nonce";
        return sprintf(
            "DetachedTimestampFile(original: %s, %s, timestamp: %s)",
            $this->originalFilename,
            $nonceInfo,
            $this->timestamp
        );
    }
}