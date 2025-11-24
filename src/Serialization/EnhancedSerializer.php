<?php
// src/Serialization/EnhancedSerializer.php

namespace OpenTimestampsPHP\Serialization;

use OpenTimestampsPHP\Core\Op;
use OpenTimestampsPHP\Ops\{
    OpSHA1, OpSHA256, OpRIPEMD160, OpKECCAK256,
    OpAppend, OpPrepend, OpReverse, OpHexlify, OpUnHexlify,
    OpSubstr, OpLeft, OpRight, OpXOR, OpAND, OpOR
};

class EnhancedSerializer {
    private Stream $stream;

    public function __construct() {
        $this->stream = new Stream();
    }

    public function serializeOp(Op $op): void {
        if ($op instanceof OpSHA1 ||
            $op instanceof OpSHA256 ||
            $op instanceof OpRIPEMD160 ||
            $op instanceof OpKECCAK256 ||
            $op instanceof OpReverse ||
            $op instanceof OpHexlify ||
            $op instanceof OpUnHexlify) {
            // Simple operations with no parameters
            $this->stream->writeByte($op->getTag());
        } elseif ($op instanceof OpAppend || 
                 $op instanceof OpPrepend ||
                 $op instanceof OpXOR ||
                 $op instanceof OpAND ||
                 $op instanceof OpOR) {
            // Operations with binary data parameter
            $this->stream->writeByte($op->getTag());
            $data = $op->getData();
            $this->stream->writeVaruint(strlen($data));
            $this->stream->writeBytes($data);
        } elseif ($op instanceof OpSubstr) {
            // Substring operation with start and optional length
            $this->stream->writeByte($op->getTag());
            $this->stream->writeVaruint($op->getStart());
            $length = $op->getLength() ?? 0xffffffff;
            $this->stream->writeVaruint($length);
        } elseif ($op instanceof OpLeft || $op instanceof OpRight) {
            // Left/Right operations with length parameter
            $this->stream->writeByte($op->getTag());
            $this->stream->writeVaruint($op->getLength());
        } else {
            throw new \Exception("Unsupported operation type: " . get_class($op));
        }
    }

    public function getData(): string {
        return $this->stream->getData();
    }
}