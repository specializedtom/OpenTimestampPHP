<?php
// src/Cli/OutputFormatter.php

namespace OpenTimestampsPHP\Cli;

class OutputFormatter {
    private bool $colorsSupported;

    public function __construct() {
        $this->colorsSupported = $this->supportsColors();
    }

    public function formatTitle(string $text): string {
        return $this->format($text, 'blue', true);
    }

    public function formatSubtitle(string $text): string {
        return $this->format($text, 'yellow', true);
    }

    public function formatInfo(string $text): string {
        return $this->format($text, 'blue');
    }

    public function formatSuccess(string $text): string {
        return $this->format($text, 'green');
    }

    public function formatWarning(string $text): string {
        return $this->format($text, 'yellow');
    }

    public function formatError(string $text): string {
        return $this->format($text, 'red');
    }

    private function format(string $text, string $color, bool $bold = false): string {
        if (!$this->colorsSupported) {
            return $text;
        }

        $colors = [
            'black' => '0;30',
            'red' => '0;31',
            'green' => '0;32',
            'yellow' => '0;33',
            'blue' => '0;34',
            'magenta' => '0;35',
            'cyan' => '0;36',
            'white' => '0;37',
            'bright_black' => '1;30',
            'bright_red' => '1;31',
            'bright_green' => '1;32',
            'bright_yellow' => '1;33',
            'bright_blue' => '1;34',
            'bright_magenta' => '1;35',
            'bright_cyan' => '1;36',
            'bright_white' => '1;37',
        ];

        $code = $colors[$color] ?? $colors['white'];
        if ($bold) {
            $code = str_replace('0;', '1;', $code);
        }

        return "\033[{$code}m{$text}\033[0m";
    }

    private function supportsColors(): bool {
        // Check if we're in a terminal that supports colors
        if (getenv('NO_COLOR') !== false) {
            return false;
        }

        if (getenv('TERM') === 'dumb') {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return (function_exists('sapi_windows_vt100_support') && sapi_windows_vt100_support(STDOUT))
                || getenv('ANSICON') !== false
                || getenv('ConEmuANSI') === 'ON'
                || getenv('TERM') === 'xterm';
        }

        return function_exists('posix_isatty') && posix_isatty(STDOUT);
    }
}