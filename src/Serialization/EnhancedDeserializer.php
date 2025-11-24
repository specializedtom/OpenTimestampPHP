<?php
// src/Serialization/EnhancedDeserializer.php

namespace OpenTimestampsPHP\Serialization;

use OpenTimestampsPHP\Core\Op;
use OpenTimestampsPHP\Ops\{
    OpSHA1, OpSHA256, OpRIPEMD160, OpKECCAK256,
    OpAppend, OpPrepend, OpReverse, OpHexlify, OpUnHexlify,
    OpSubstr, OpLeft, OpRight, OpXOR, OpAND, OpOR
};

class EnhancedDeserializer {
    public static function deserializeOp(Stream $stream): Op {
        $tag = $stream->readByte();
        
        switch ($tag) {
            // Cryptographic hash operations
            case 0x02:
                return new OpSHA1();
            case 0x03:
                return new OpRIPEMD160();
            case 0x08:
                return new OpSHA256();
            case 0x67:
                return new OpKECCAK256();
                
            // Binary manipulation operations
            case 0x0a:
                return new OpReverse();
            case 0x0b:
                return new OpHexlify();
            case 0x0c:
                return new OpUnHexlify();
                
            // Substring operations
            case 0x0d:
                $start = $stream->readVaruint();
                $length = $stream->readVaruint();
                if ($length === 0xffffffff) {
                    return new OpSubstr($start);
                }
                return new OpSubstr($start, $length);
            case 0x0e:
                $length = $stream->readVaruint();
                return new OpLeft($length);
            case 0x0f:
                $length = $stream->readVaruint();
                return new OpRight($length);
                
            // Bitwise operations
            case 0x10:
                $length = $stream->readVaruint();
                $key = $stream->readBytes($length);
                return new OpXOR($key);
            case 0x11:
                $length = $stream->readVaruint();
                $mask = $stream->readBytes($length);
                return new OpAND($mask);
            case 0x12:
                $length = $stream->readVaruint();
                $mask = $stream->readBytes($length);
                return new OpOR($mask);
                
            // Data attachment operations
            case 0xf0:
                $length = $stream->readVaruint();
                $data = $stream->readBytes($length);
                return new OpAppend($data);
            case 0xf1:
                $length = $stream->readVaruint();
                $data = $stream->readBytes($length);
                return new OpPrepend($data);
                
            default:
                throw new \Exception("Unknown operation tag: 0x" . dechex($tag));
        }
    }
}