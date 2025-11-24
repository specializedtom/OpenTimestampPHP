<?php
// tests/cli_test.php

require_once __DIR__ . '/../vendor/autoload.php';

use OpenTimestampsPHP\Cli\CliApplication;
use OpenTimestampsPHP\Cli\OutputFormatter;

function test_output_formatter() {
    echo "=== Testing Output Formatter ===\n";
    
    $formatter = new OutputFormatter();
    
    echo $formatter->formatTitle("This is a title") . "\n";
    echo $formatter->formatSubtitle("This is a subtitle") . "\n";
    echo $formatter->formatInfo("This is an info message") . "\n";
    echo $formatter->formatSuccess("This is a success message") . "\n";
    echo $formatter->formatWarning("This is a warning message") . "\n";
    echo $formatter->formatError("This is an error message") . "\n";
    
    echo "Output formatter test completed.\n\n";
}

function test_cli_help() {
    echo "=== Testing CLI Help ===\n";
    
    $app = new CliApplication();
    
    // Test help command
    $argv = ['ots', 'help'];
    $result = $app->run($argv);
    
    echo "Help command exit code: $result\n";
    
    // Test specific command help
    $argv = ['ots', 'help', 'stamp'];
    $result = $app->run($argv);
    
    echo "Stamp help exit code: $result\n";
    
    echo "CLI help test completed.\n\n";
}

function test_cli_commands() {
    echo "=== Testing CLI Commands ===\n";
    
    $app = new CliApplication();
    
    // Test status command
    $argv = ['ots', 'status'];
    $result = $app->run($argv);
    echo "Status command exit code: $result\n";
    
    // Test server list command
    $argv = ['ots', 'server', 'list'];
    $result = $app->run($argv);
    echo "Server list command exit code: $result\n";
    
    // Test unknown command
    $argv = ['ots', 'unknown-command'];
    $result = $app->run($argv);
    echo "Unknown command exit code: $result\n";
    
    echo "CLI commands test completed.\n\n";
}

// Run tests
test_output_formatter();
test_cli_help();
test_cli_commands();