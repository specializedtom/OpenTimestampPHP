<?php
// src/Validation/TimestampValidator.php

namespace OpenTimestampsPHP\Validation;

use OpenTimestampsPHP\Core\Timestamp;
use OpenTimestampsPHP\Core\Op;

class TimestampValidator extends AbstractValidator {
    private int $maxDepth = 100;
    private int $maxOperations = 1000;
    private int $maxAttestations = 100;
    private bool $checkCircularReferences = true;

    public function __construct(array $options = []) {
        parent::__construct($options);
        
        $this->maxDepth = $options['max_depth'] ?? $this->maxDepth;
        $this->maxOperations = $options['max_operations'] ?? $this->maxOperations;
        $this->maxAttestations = $options['max_attestations'] ?? $this->maxAttestations;
        $this->checkCircularReferences = $options['check_circular_references'] ?? $this->checkCircularReferences;
    }

    public function validate($value): bool {
        if (!$value instanceof Timestamp) {
            $this->setError("Value must be a Timestamp instance");
            return false;
        }

        $visited = new \SplObjectStorage();
        $operationCount = 0;
        $attestationCount = 0;

        return $this->validateTimestamp($value, $visited, 0, $operationCount, $attestationCount);
    }

    public function sanitize($value) {
        // Timestamp objects are immutable, no sanitization needed
        return $value;
    }

    private function validateTimestamp(
        Timestamp $timestamp, 
        \SplObjectStorage $visited, 
        int $depth,
        int &$operationCount,
        int &$attestationCount
    ): bool {
        // Check depth
        if ($depth > $this->maxDepth) {
            $this->setError("Timestamp tree depth exceeds maximum: {$this->maxDepth}");
            return false;
        }

        // Check for circular references
        if ($this->checkCircularReferences) {
            if ($visited->contains($timestamp)) {
                $this->setError("Circular reference detected in timestamp tree");
                return false;
            }
            $visited->attach($timestamp);
        }

        // Validate message
        $msg = $timestamp->getMsg();
        if ($msg !== null) {
            $hashValidator = new HashValidator(['require_binary' => true]);
            if (!$hashValidator->validate($msg)) {
                $this->setError("Invalid message hash: " . $hashValidator->getError());
                return false;
            }
        }

        // Validate attestations
        $attestations = $timestamp->getAttestations();
        $attestationCount += count($attestations);
        
        if ($attestationCount > $this->maxAttestations) {
            $this->setError("Too many attestations: {$attestationCount} (max: {$this->maxAttestations})");
            return false;
        }

        foreach ($attestations as $attestation) {
            if (!$this->validateAttestation($attestation)) {
                return false;
            }
        }

        // Validate operations recursively
        foreach ($timestamp->getOps() as [$op, $subTimestamp]) {
            $operationCount++;
            
            if ($operationCount > $this->maxOperations) {
                $this->setError("Too many operations: {$operationCount} (max: {$this->maxOperations})");
                return false;
            }

            if (!$this->validateOperation($op)) {
                return false;
            }

            if (!$this->validateTimestamp($subTimestamp, $visited, $depth + 1, $operationCount, $attestationCount)) {
                return false;
            }
        }

        return true;
    }

    private function validateAttestation($attestation): bool {
        if (!is_object($attestation)) {
            $this->setError("Attestation must be an object");
            return false;
        }

        $allowedClasses = [
            'OpenTimestampsPHP\Attestations\BitcoinBlockHeaderAttestation',
            'OpenTimestampsPHP\Attestations\LitecoinBlockHeaderAttestation', 
            'OpenTimestampsPHP\Attestations\EthereumAttestation',
            'OpenTimestampsPHP\Attestations\PendingAttestation'
        ];

        $valid = false;
        foreach ($allowedClasses as $className) {
            if ($attestation instanceof $className) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            $this->setError("Invalid attestation type: " . get_class($attestation));
            return false;
        }

        // Validate specific attestation properties
        if (method_exists($attestation, 'getHeight')) {
            $height = $attestation->getHeight();
            if (!is_int($height) || $height < 0 || $height > 10000000) {
                $this->setError("Invalid block height: {$height}");
                return false;
            }
        }

        if ($attestation instanceof \OpenTimestampsPHP\Attestations\PendingAttestation) {
            $uriValidator = new UrlValidator([
                'allowed_schemes' => ['https', 'http'],
                'allowed_hosts' => ['*.opentimestamps.org', '*.pool.opentimestamps.org']
            ]);
            
            if (!$uriValidator->validate($attestation->getUri())) {
                $this->setError("Invalid pending attestation URI: " . $uriValidator->getError());
                return false;
            }
        }

        return true;
    }

    private function validateOperation(Op $op): bool {
        $allowedClasses = [
            'OpenTimestampsPHP\Ops\OpSHA1',
            'OpenTimestampsPHP\Ops\OpSHA256',
            'OpenTimestampsPHP\Ops\OpRIPEMD160',
            'OpenTimestampsPHP\Ops\OpKECCAK256',
            'OpenTimestampsPHP\Ops\OpAppend',
            'OpenTimestampsPHP\Ops\OpPrepend',
            'OpenTimestampsPHP\Ops\OpReverse',
            'OpenTimestampsPHP\Ops\OpHexlify',
            'OpenTimestampsPHP\Ops\OpUnHexlify',
            'OpenTimestampsPHP\Ops\OpSubstr',
            'OpenTimestampsPHP\Ops\OpLeft',
            'OpenTimestampsPHP\Ops\OpRight',
            'OpenTimestampsPHP\Ops\OpXOR',
            'OpenTimestampsPHP\Ops\OpAND',
            'OpenTimestampsPHP\Ops\OpOR'
        ];

        $valid = false;
        foreach ($allowedClasses as $className) {
            if ($op instanceof $className) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            $this->setError("Invalid operation type: " . get_class($op));
            return false;
        }

        // Validate operation-specific data
        if ($op instanceof \OpenTimestampsPHP\Ops\OpAppend || 
            $op instanceof \OpenTimestampsPHP\Ops\OpPrepend) {
            $data = $op->getData();
            if (strlen($data) > 1024) { // Reasonable limit for operation data
                $this->setError("Operation data too large: " . strlen($data) . " bytes");
                return false;
            }
        }

        if ($op instanceof \OpenTimestampsPHP\Ops\OpSubstr) {
            $start = $op->getStart();
            if ($start < 0) {
                $this->setError("Substring start cannot be negative");
                return false;
            }
        }

        return true;
    }
}