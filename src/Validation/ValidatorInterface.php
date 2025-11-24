<?php
// src/Validation/ValidatorInterface.php

namespace OpenTimestampsPHP\Validation;

interface ValidatorInterface {
    public function validate($value): bool;
    public function getError(): ?string;
    public function sanitize($value);
}