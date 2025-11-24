<?php
// src/Attestations/PendingAttestation.php

namespace OpenTimestampsPHP\Attestations;

class PendingAttestation implements Attestation {
    private string $uri;
    private ?string $calendarUrl;

    public function __construct(string $uri) {
        $this->uri = $uri;
        $this->calendarUrl = $this->parseCalendarUrl($uri);
    }

    public function getType(): string {
        return 'pending';
    }

    public function getUri(): string {
        return $this->uri;
    }

    public function getCalendarUrl(): ?string {
        return $this->calendarUrl;
    }

    public function verify(string $message, array $context = []): array {
        $result = [
            'valid' => false,
            'verified' => false,
            'type' => 'pending',
            'uri' => $this->uri,
            'error' => null,
            'upgrade_attempted' => false,
            'upgrade_result' => null
        ];

        // For pending attestations, we try to upgrade them
        if ($this->calendarUrl && isset($context['calendar_client'])) {
            try {
                $result['upgrade_attempted'] = true;
                $upgradeResult = $context['calendar_client']->upgradePendingAttestation($this);
                $result['upgrade_result'] = $upgradeResult;
                
                if ($upgradeResult['upgraded']) {
                    $result['valid'] = true;
                    $result['verified'] = $upgradeResult['verified'];
                }
            } catch (\Exception $e) {
                $result['error'] = "Upgrade failed: " . $e->getMessage();
            }
        } else {
            $result['error'] = 'No calendar client available for upgrade';
        }

        return $result;
    }

    public function serialize(): string {
        return pack('C', 0x09) . 
               $this->encodeVarint(strlen($this->uri)) . 
               $this->uri;
    }

    private function parseCalendarUrl(string $uri): ?string {
        if (preg_match('#^https?://[^/]+#', $uri, $matches)) {
            return $matches[0];
        }
        return null;
    }

    private function encodeVarint(int $value): string {
        if ($value < 0xFD) {
            return chr($value);
        } elseif ($value <= 0xFFFF) {
            return pack('Cv', 0xFD, $value);
        } elseif ($value <= 0xFFFFFFFF) {
            return pack('CV', 0xFE, $value);
        } else {
            return pack('CP', 0xFF, $value);
        }
    }

    public function __toString(): string {
        return "PendingAttestation(uri: {$this->uri})";
    }
}