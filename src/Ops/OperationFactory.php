<?php
// src/Ops/OperationFactory.php

namespace OpenTimestampsPHP\Ops;

class OperationFactory {
    public static function createFromString(string $operationString): \OpenTimestampsPHP\Core\Op {
        $parts = explode(':', $operationString);
        $opName = $parts[0];
        
        switch ($opName) {
            case 'sha1':
                return new OpSHA1();
            case 'sha256':
                return new OpSHA256();
            case 'ripemd160':
                return new OpRIPEMD160();
            case 'keccak256':
                return new OpKECCAK256();
            case 'reverse':
                return new OpReverse();
            case 'hexlify':
                return new OpHexlify();
            case 'unhexlify':
                return new OpUnHexlify();
            case 'append':
                if (!isset($parts[1])) {
                    throw new \InvalidArgumentException("Append operation requires data");
                }
                return new OpAppend(hex2bin($parts[1]));
            case 'prepend':
                if (!isset($parts[1])) {
                    throw new \InvalidArgumentException("Prepend operation requires data");
                }
                return new OpPrepend(hex2bin($parts[1]));
            case 'substr':
                if (!isset($parts[1])) {
                    throw new \InvalidArgumentException("Substr operation requires start position");
                }
                $start = (int)$parts[1];
                $length = isset($parts[2]) ? (int)$parts[2] : null;
                return new OpSubstr($start, $length);
            case 'left':
                if (!isset($parts[1])) {
                    throw new \InvalidArgumentException("Left operation requires length");
                }
                return new OpLeft((int)$parts[1]);
            case 'right':
                if (!isset($parts[1])) {
                    throw new \InvalidArgumentException("Right operation requires length");
                }
                return new OpRight((int)$parts[1]);
            case 'xor':
                if (!isset($parts[1])) {
                    throw new \InvalidArgumentException("XOR operation requires key");
                }
                return new OpXOR(hex2bin($parts[1]));
            case 'and':
                if (!isset($parts[1])) {
                    throw new \InvalidArgumentException("AND operation requires mask");
                }
                return new OpAND(hex2bin($parts[1]));
            case 'or':
                if (!isset($parts[1])) {
                    throw new \InvalidArgumentException("OR operation requires mask");
                }
                return new OpOR(hex2bin($parts[1]));
            default:
                throw new \InvalidArgumentException("Unknown operation: $opName");
        }
    }
    
    public static function getAllOperations(): array {
        return [
            'sha1' => OpSHA1::class,
            'sha256' => OpSHA256::class,
            'ripemd160' => OpRIPEMD160::class,
            'keccak256' => OpKECCAK256::class,
            'reverse' => OpReverse::class,
            'hexlify' => OpHexlify::class,
            'unhexlify' => OpUnHexlify::class,
            'append' => OpAppend::class,
            'prepend' => OpPrepend::class,
            'substr' => OpSubstr::class,
            'left' => OpLeft::class,
            'right' => OpRight::class,
            'xor' => OpXOR::class,
            'and' => OpAND::class,
            'or' => OpOR::class,
        ];
    }
}