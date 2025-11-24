<?php
// src/Serialization/Stream.php

namespace OpenTimestampsPHP\Serialization;

class Stream {
    private string $data;
    private int $position = 0;

    public function __construct(string $data = '') {
        $this->data = $data;
    }

    public function readByte(): int {
        if ($this->eof()) {
            throw new \Exception("Unexpected EOF");
        }
        return ord($this->data[$this->position++]);
    }

    public function readBytes(int $length): string {
        if ($this->position + $length > strlen($this->data)) {
            throw new \Exception("Unexpected EOF");
        }
        $result = substr($this->data, $this->position, $length);
        $this->position += $length;
        return $result;
    }

    public function readVaruint(): int {
        $result = 0;
        $shift = 0;
        
        do {
            $byte = $this->readByte();
            $result |= ($byte & 0x7f) << $shift;
            $shift += 7;
        } while ($byte & 0x80);
        
        return $result;
    }

    public function writeByte(int $byte): void {
        $this->data .= chr($byte & 0xFF);
    }

    public function writeBytes(string $bytes): void {
        $this->data .= $bytes;
    }

    public function writeVaruint(int $value): void {
        if ($value < 0) {
            throw new \Exception("Varuint cannot be negative");
        }

        while ($value > 0x7f) {
            $this->writeByte(($value & 0x7f) | 0x80);
            $value >>= 7;
        }
        $this->writeByte($value);
    }

    public function eof(): bool {
        return $this->position >= strlen($this->data);
    }

    public function getPosition(): int {
        return $this->position;
    }

    public function getData(): string {
        return $this->data;
    }

    public function getLength(): int {
        return strlen($this->data);
    }

    public function seek(int $position): void {
        if ($position < 0 || $position > strlen($this->data)) {
            throw new \Exception("Seek position out of range");
        }
        $this->position = $position;
    }
}