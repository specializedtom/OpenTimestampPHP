<?php
// src/Serialization/Serializer.php

namespace OpenTimestampsPHP\Serialization;

use OpenTimestampsPHP\Core\{Timestamp, Op, Attestation};
use OpenTimestampsPHP\Ops\{OpSHA256, OpRIPEMD160, OpSHA1, OpAppend, OpPrepend};
use OpenTimestampsPHP\Attestations\{BitcoinBlockHeaderAttestation, PendingAttestation};

class Serializer {
    private Stream $stream;

    public function __construct() {
        $this->stream = new Stream();
    }

    public function serialize(Timestamp $timestamp): string {
        $this->serializeTimestamp($timestamp);
        return $this->stream->getData();
    }

    private function serializeTimestamp(Timestamp $timestamp): void {
        // Serialize operations first
        foreach ($timestamp->getOps() as [$op, $subTimestamp]) {
            $this->stream->writeByte(0x00); // Operation tag
            $this->serializeOp($op);
            $this->serializeTimestamp($subTimestamp);
        }

        // Serialize attestations
        foreach ($timestamp->getAttestations() as $attestation) {
            $this->serializeAttestation($attestation);
        }

        // End of timestamp
        $this->stream->writeByte(0xf0);
    }

    private function serializeOp(Op $op): void {
        if ($op instanceof OpSHA256) {
            $this->stream->writeVaruint(0x02);
        } elseif ($op instanceof OpRIPEMD160) {
            $this->stream->writeVaruint(0x03);
        } elseif ($op instanceof OpSHA1) {
            $this->stream->writeVaruint(0x04);
        } elseif ($op instanceof OpAppend) {
            $this->stream->writeVaruint(0x08);
            $data = $op->getData();
            $this->stream->writeVaruint(strlen($data));
            $this->stream->writeBytes($data);
        } elseif ($op instanceof OpPrepend) {
            $this->stream->writeVaruint(0x09);
            $data = $op->getData();
            $this->stream->writeVaruint(strlen($data));
            $this->stream->writeBytes($data);
        } else {
            throw new \Exception("Unsupported operation type: " . get_class($op));
        }
    }

    private function serializeAttestation(Attestation $attestation): void {
        if ($attestation instanceof BitcoinBlockHeaderAttestation) {
            $this->stream->writeByte(0x08);
            $this->stream->writeVaruint($attestation->getHeight());
        } elseif ($attestation instanceof PendingAttestation) {
            $this->stream->writeByte(0x09);
            $uri = $attestation->getUri();
            $this->stream->writeVaruint(strlen($uri));
            $this->stream->writeBytes($uri);
        } else {
            throw new \Exception("Unsupported attestation type: " . get_class($attestation));
        }
    }
}