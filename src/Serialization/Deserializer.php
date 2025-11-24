<?php
// src/Serialization/Deserializer.php

namespace OpenTimestampsPHP\Serialization;

use OpenTimestampsPHP\Core\{Timestamp, Attestation};
use OpenTimestampsPHP\Ops\{OpSHA256, OpRIPEMD160, OpSHA1, OpAppend, OpPrepend};
use OpenTimestampsPHP\Attestations\{BitcoinBlockHeaderAttestation, PendingAttestation};

class Deserializer {
    public static function deserialize(string $data): Timestamp {
        $stream = new Stream($data);
        $timestamp = self::deserializeTimestamp($stream);
        
        if (!$stream->eof()) {
            throw new \Exception("Extra data at end of stream");
        }
        
        return $timestamp;
    }

    private static function deserializeTimestamp(Stream $stream): Timestamp {
        $timestamp = new Timestamp();
        
        while (!$stream->eof()) {
            $tag = $stream->readByte();
            
            switch ($tag) {
                case 0x00: // Operation
                    $op = self::deserializeOp($stream);
                    $subTimestamp = self::deserializeTimestamp($stream);
                    $timestamp->addOperation($op, $subTimestamp);
                    break;
                    
                case 0x08: // Bitcoin Block Header Attestation
                    $attestation = self::deserializeBitcoinAttestation($stream);
                    $timestamp->addAttestation($attestation);
                    break;
                    
                case 0x09: // Pending Attestation
                    $attestation = self::deserializePendingAttestation($stream);
                    $timestamp->addAttestation($attestation);
                    break;
                    
                case 0xf0: // End of timestamp
                    return $timestamp;
                    
                case 0xf1: // Unknown future commitment
                    self::skipUnknownCommitment($stream);
                    break;
                    
                default:
                    throw new \Exception("Unknown tag: 0x" . dechex($tag));
            }
        }
        
        return $timestamp;
    }

    private static function deserializeOp(Stream $stream): \OpenTimestampsPHP\Core\Op {
        $opTag = $stream->readVaruint();
        
        switch ($opTag) {
            case 0x02: 
                return new OpSHA256();
            case 0x03: 
                return new OpRIPEMD160();
            case 0x04:
                return new OpSHA1();
            case 0x08:
                $len = $stream->readVaruint();
                $data = $stream->readBytes($len);
                return new OpAppend($data);
            case 0x09:
                $len = $stream->readVaruint();
                $data = $stream->readBytes($len);
                return new OpPrepend($data);
            case 0x0a:
                $len = $stream->readVaruint();
                $data = $stream->readBytes($len);
                // OpReverse - not commonly used but in spec
                return new class($data) implements \OpenTimestampsPHP\Core\Op {
                    private string $data;
                    public function __construct(string $data) { $this->data = $data; }
                    public function call(string $msg): string { 
                        return strrev($msg); 
                    }
                    public function __toString(): string { return 'reverse'; }
                };
            default:
                throw new \Exception("Unknown operation tag: 0x" . dechex($opTag));
        }
    }

    private static function deserializeBitcoinAttestation(Stream $stream): BitcoinBlockHeaderAttestation {
        $height = $stream->readVaruint();
        return new BitcoinBlockHeaderAttestation($height);
    }

    private static function deserializePendingAttestation(Stream $stream): PendingAttestation {
        $uriLength = $stream->readVaruint();
        $uri = $stream->readBytes($uriLength);
        return new PendingAttestation($uri);
    }

    private static function skipUnknownCommitment(Stream $stream): void {
        $commitmentType = $stream->readVaruint();
        $commitmentLength = $stream->readVaruint();
        $stream->readBytes($commitmentLength); // Skip the commitment data
    }
}