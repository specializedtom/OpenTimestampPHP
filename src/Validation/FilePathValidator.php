<?php
// src/Validation/FilePathValidator.php

namespace OpenTimestampsPHP\Validation;

class FilePathValidator extends AbstractValidator {
    private array $allowedExtensions = ['ots', 'txt', 'pdf', 'doc', 'docx', 'jpg', 'png'];
    private int $maxPathLength = 4096;
    private bool $allowRelativePaths = false;
    private ?string $baseDirectory = null;

    public function __construct(array $options = []) {
        parent::__construct($options);
        
        $this->allowedExtensions = $options['allowed_extensions'] ?? $this->allowedExtensions;
        $this->maxPathLength = $options['max_path_length'] ?? $this->maxPathLength;
        $this->allowRelativePaths = $options['allow_relative_paths'] ?? $this->allowRelativePaths;
        $this->baseDirectory = $options['base_directory'] ?? null;
    }

    public function validate($value): bool {
        if (!is_string($value)) {
            $this->setError("File path must be a string");
            return false;
        }

        $path = $this->sanitize($value);

        // Check length
        if (strlen($path) > $this->maxPathLength) {
            $this->setError("File path too long (max: {$this->maxPathLength} characters)");
            return false;
        }

        // Check for null bytes (path traversal)
        if (strpos($path, "\0") !== false) {
            $this->setError("File path contains null bytes");
            return false;
        }

        // Check for directory traversal
        if (!$this->allowRelativePaths && $this->containsPathTraversal($path)) {
            $this->setError("File path contains directory traversal attempts");
            return false;
        }

        // Check if path is within base directory
        if ($this->baseDirectory && !$this->isWithinBaseDirectory($path)) {
            $this->setError("File path is outside allowed directory");
            return false;
        }

        // Check file extension if specified
        if (!empty($this->allowedExtensions)) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($extension && !in_array($extension, $this->allowedExtensions)) {
                $this->setError("File extension '{$extension}' is not allowed");
                return false;
            }
        }

        return true;
    }

    public function sanitize($value): string {
        $path = (string) $value;
        
        // Remove null bytes
        $path = str_replace("\0", '', $path);
        
        // Normalize path separators
        $path = str_replace('\\', '/', $path);
        
        // Remove multiple slashes
        $path = preg_replace('#/+#', '/', $path);
        
        // Resolve relative paths if allowed
        if ($this->allowRelativePaths && $this->baseDirectory) {
            $path = $this->resolveRelativePath($path);
        }
        
        return trim($path);
    }

    private function containsPathTraversal(string $path): bool {
        $patterns = [
            '\.\./',
            '\.\.\\',
            '//',
            '\\\\',
            '\.\.$'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match("#{$pattern}#", $path)) {
                return true;
            }
        }
        
        return false;
    }

    private function isWithinBaseDirectory(string $path): bool {
        if (!$this->baseDirectory) {
            return true;
        }

        $realBase = realpath($this->baseDirectory);
        $realPath = realpath($path);

        if ($realBase === false || $realPath === false) {
            return false;
        }

        $realBase = rtrim($realBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $realPath = rtrim($realPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return strpos($realPath, $realBase) === 0;
    }

    private function resolveRelativePath(string $path): string {
        if ($this->baseDirectory && !$this->isAbsolutePath($path)) {
            $path = rtrim($this->baseDirectory, '/') . '/' . ltrim($path, '/');
        }
        return $path;
    }

    private function isAbsolutePath(string $path): bool {
        return $path[0] === '/' || 
               (strlen($path) > 1 && $path[1] === ':') || // Windows drive
               $path[0] === '\\';
    }
}