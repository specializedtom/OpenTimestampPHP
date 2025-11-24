<?php
// src/Validation/AbstractValidator.php

namespace OpenTimestampsPHP\Validation;

abstract class AbstractValidator implements ValidatorInterface {
    protected ?string $error = null;
    protected array $options;

    public function __construct(array $options = []) {
        $this->options = $options;
    }

    public function getError(): ?string {
        return $this->error;
    }

    protected function setError(string $error): void {
        $this->error = $error;
    }

    public function sanitize($value) {
        // Default sanitization - override in child classes
        return $value;
    }
}