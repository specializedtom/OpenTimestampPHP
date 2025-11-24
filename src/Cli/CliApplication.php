<?php
// src/Cli/CliApplication.php

namespace OpenTimestampsPHP\Cli;

use OpenTimestampsPHP\Client;
use OpenTimestampsPHP\Config\ConfigurationFactory;
use OpenTimestampsPHP\Blockchain\BlockchainManager;
use OpenTimestampsPHP\Verification\NodeEnhancedAttestationVerifier;
use OpenTimestampsPHP\File\FileHandler;

class CliApplication {
    private array $config;
    private Client $client;
    private OutputFormatter $formatter;
    private bool $verbose = false;
    private bool $quiet = false;

    public function __construct(array $config = []) {
        $this->config = $config;
        $this->formatter = new OutputFormatter();
        $this->initializeClient();
    }

    public function run(array $argv): int {
        $command = $argv[1] ?? null;
        $args = array_slice($argv, 2);

        try {
            $this->parseGlobalOptions($args);
            
            switch ($command) {
                case 'stamp':
                    return $this->stampCommand($args);
                case 'verify':
                    return $this->verifyCommand($args);
                case 'upgrade':
                    return $this->upgradeCommand($args);
                case 'info':
                    return $this->infoCommand($args);
                case 'status':
                    return $this->statusCommand($args);
                case 'server':
                    return $this->serverCommand($args);
                case 'help':
                case '--help':
                case '-h':
                    return $this->helpCommand($args);
                case null:
                    $this->error("No command provided");
                    return $this->helpCommand($args);
                default:
                    $this->error("Unknown command: $command");
                    return $this->helpCommand($args);
            }
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            if ($this->verbose) {
                $this->error("Stack trace: " . $e->getTraceAsString());
            }
            return 1;
        }
    }

    private function stampCommand(array $args): int {
        $options = $this->parseOptions($args, [
            'output' => 'o:',
            'wait' => 'w',
            'attached' => 'a',
            'calendar' => 'c:',
            'timeout' => 't:'
        ]);

        $file = $args[0] ?? null;
        if (!$file) {
            $this->error("Please specify a file to stamp");
            return 1;
        }

        if (!file_exists($file)) {
            $this->error("File not found: $file");
            return 1;
        }

        $outputFile = $options['output'] ?? null;
        $wait = isset($options['wait']);
        $attached = isset($options['attached']);
        
        if (isset($options['calendar'])) {
            $this->config['calendar']['servers'] = explode(',', $options['calendar']);
            $this->initializeClient();
        }

        if (isset($options['timeout'])) {
            $this->config['calendar']['timeout'] = (int)$options['timeout'];
            $this->initializeClient();
        }

        $this->info("Creating timestamp for: $file");

        try {
            if ($attached) {
                $result = $this->client->stampAttached($file, $outputFile);
                $this->success("Created attached timestamp: $result");
            } else {
                $result = $this->client->stamp($file, $outputFile, $wait);
                $this->success("Created detached timestamp: $result");
            }

            if ($wait) {
                $this->info("Waiting for attestations...");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to create timestamp: " . $e->getMessage());
            return 1;
        }
    }

    private function verifyCommand(array $args): int {
        $options = $this->parseOptions($args, [
            'verbose' => 'v',
            'json' => 'j',
            'time-window' => 't:'
        ]);

        $otsFile = $args[0] ?? null;
        $originalFile = $args[1] ?? null;

        if (!$otsFile) {
            $this->error("Please specify a timestamp file (.ots)");
            return 1;
        }

        if (!file_exists($otsFile)) {
            $this->error("Timestamp file not found: $otsFile");
            return 1;
        }

        // For attached timestamps, we don't need the original file
        $isAttached = FileHandler::hasAttachedTimestamp($otsFile);
        
        if (!$isAttached && !$originalFile) {
            // Try to guess original file name
            if (str_ends_with($otsFile, '.ots')) {
                $originalFile = substr($otsFile, 0, -4);
            }
            
            if (!$originalFile || !file_exists($originalFile)) {
                $this->error("Please specify the original file for verification");
                return 1;
            }
        }

        $this->info("Verifying timestamp: $otsFile");

        try {
            if ($isAttached) {
                $result = $this->client->verifyAttached($otsFile);
            } else {
                $result = $this->client->verify($otsFile, $originalFile);
            }

            if (isset($options['json'])) {
                echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
                return $result['valid'] ? 0 : 1;
            }

            $this->displayVerificationResult($result, $otsFile, $originalFile);
            return $result['valid'] ? 0 : 1;

        } catch (\Exception $e) {
            $this->error("Verification failed: " . $e->getMessage());
            return 1;
        }
    }

    private function upgradeCommand(array $args): int {
        $options = $this->parseOptions($args, [
            'force' => 'f',
            'calendar' => 'c:'
        ]);

        $otsFile = $args[0] ?? null;
        if (!$otsFile) {
            $this->error("Please specify a timestamp file (.ots)");
            return 1;
        }

        if (!file_exists($otsFile)) {
            $this->error("Timestamp file not found: $otsFile");
            return 1;
        }

        if (isset($options['calendar'])) {
            $this->config['calendar']['servers'] = explode(',', $options['calendar']);
            $this->initializeClient();
        }

        $this->info("Upgrading timestamp: $otsFile");

        try {
            $force = isset($options['force']);
            $success = $this->client->upgrade($otsFile, $force);

            if ($success) {
                $this->success("Timestamp upgraded successfully");
            } else {
                $this->warning("No upgrades available at this time");
            }

            return $success ? 0 : 2; // Exit code 2 for no upgrades

        } catch (\Exception $e) {
            $this->error("Upgrade failed: " . $e->getMessage());
            return 1;
        }
    }

    private function infoCommand(array $args): int {
        $options = $this->parseOptions($args, [
            'tree' => 't',
            'compact' => 'c', 
            'json' => 'j',
            'verbose' => 'v'
        ]);

        $file = $args[0] ?? null;
        if (!$file) {
            $this->error("Please specify a timestamp file");
            return 1;
        }

        $infoCommand = new InfoCommand($this->formatter);
        return $infoCommand->execute($file, $options);
    }

    private function statusCommand(array $args): int {
        $options = $this->parseOptions($args, [
            'json' => 'j'
        ]);

        $this->info("Checking OpenTimestamps status");

        try {
            $status = $this->getSystemStatus();

            if (isset($options['json'])) {
                echo json_encode($status, JSON_PRETTY_PRINT) . "\n";
                return 0;
            }

            $this->displayStatusResult($status);
            return 0;

        } catch (\Exception $e) {
            $this->error("Status command failed: " . $e->getMessage());
            return 1;
        }
    }

    private function serverCommand(array $args): int {
        $subcommand = $args[0] ?? 'status';
        
        switch ($subcommand) {
            case 'status':
                return $this->serverStatusCommand(array_slice($args, 1));
            case 'list':
                return $this->serverListCommand(array_slice($args, 1));
            default:
                $this->error("Unknown server command: $subcommand");
                return 1;
        }
    }

    private function serverStatusCommand(array $args): int {
        $this->info("Checking calendar server status");

        try {
            $calendarClient = $this->client->getCalendarClient();
            $stats = $calendarClient->getAttestationStats();

            $this->displayServerStatus($stats);
            return 0;

        } catch (\Exception $e) {
            $this->error("Server status command failed: " . $e->getMessage());
            return 1;
        }
    }

    private function serverListCommand(array $args): int {
        $this->info("Available calendar servers:");

        $servers = $this->config['calendar']['servers'] ?? [];
        foreach ($servers as $server) {
            $this->output("  - $server");
        }

        return 0;
    }

    private function helpCommand(array $args): int {
        $command = $args[0] ?? null;

        if ($command) {
            $this->displayCommandHelp($command);
        } else {
            $this->displayGeneralHelp();
        }

        return 0;
    }

    private function displayVerificationResult(array $result, string $otsFile, ?string $originalFile = null): void {
        $this->output("\n" . $this->formatter->formatTitle("VERIFICATION RESULT"));

        if ($result['valid']) {
            $this->success("✓ Timestamp is VALID");
        } else {
            $this->error("✗ Timestamp is INVALID");
        }

        $this->output("  File: " . basename($otsFile));
        if ($originalFile) {
            $this->output("  Original: " . basename($originalFile));
        }

        if (isset($result['file_match'])) {
            $status = $result['file_match'] ? '✓ MATCH' : '✗ MISMATCH';
            $this->output("  File hash: $status");
        }

        if (isset($result['attestations'])) {
            $this->output("\n  Attestations:");
            foreach ($result['attestations'] as $att) {
                $status = $att['verified'] ? '✓ VERIFIED' : '⧖ PENDING';
                $type = strtoupper($att['type']);
                $info = '';
                
                if (isset($att['height'])) {
                    $info = " (height: {$att['height']})";
                } elseif (isset($att['uri'])) {
                    $info = " (uri: {$att['uri']})";
                }
                
                $this->output("    - $type: $status$info");
            }
        }

        if (isset($result['security_assessment'])) {
            $security = $result['security_assessment']['security_level'] ?? 'unknown';
            $this->output("  Security level: " . strtoupper($security));
        }

        if (!empty($result['errors'])) {
            $this->output("\n  Errors:");
            foreach ($result['errors'] as $error) {
                $this->error("    - $error");
            }
        }

        if (!empty($result['recommendations'])) {
            $this->output("\n  Recommendations:");
            foreach ($result['recommendations'] as $rec) {
                $this->warning("    - $rec");
            }
        }
    }

    private function displayInfoResult(array $info, string $file): void {
        $this->output("\n" . $this->formatter->formatTitle("TIMESTAMP INFO"));

        $this->output("  File: " . basename($file));
        $this->output("  Type: " . strtoupper($info['type']));
        $this->output("  Original filename: " . $info['original_filename']);
        $this->output("  File size: " . $this->formatBytes($info['file_size']));

        if (isset($info['timestamp_info'])) {
            $ts = $info['timestamp_info'];
            $this->output("  Operations: " . ($ts['operations_count'] ?? 0));
            $this->output("  Attestations: " . ($ts['attestations_count'] ?? 0));

            if (isset($ts['attestations_detail'])) {
                $detail = $ts['attestations_detail'];
                $this->output("  Attestation details:");
                $this->output("    - Bitcoin: " . ($detail['bitcoin'] ?? 0));
                $this->output("    - Litecoin: " . ($detail['litecoin'] ?? 0));
                $this->output("    - Pending: " . ($detail['pending'] ?? 0));
                $this->output("    - Total: " . ($detail['total'] ?? 0));
            }
        }

        if (isset($info['timestamp_hash'])) {
            $this->output("  Timestamp hash: " . $info['timestamp_hash']);
        }
    }

    private function displayStatusResult(array $status): void {
        $this->output("\n" . $this->formatter->formatTitle("SYSTEM STATUS"));

        // Calendar servers
        $calendar = $status['calendar_servers'] ?? [];
        $this->output("Calendar Servers:");
        $this->output("  Responsive: {$calendar['responsive_servers']}/{$calendar['total_servers']}");
        $this->output("  Success rate: " . ($calendar['attestation_success_rate'] ?? 0) . "%");

        // Bitcoin node
        $bitcoin = $status['bitcoin_node'] ?? [];
        $this->output("Bitcoin Node:");
        $this->output("  Configured: " . ($bitcoin['node_configured'] ? 'YES' : 'NO'));
        if ($bitcoin['node_configured']) {
            $this->output("  Connected: " . ($bitcoin['node_connected'] ? 'YES' : 'NO'));
            if ($bitcoin['node_connected']) {
                $this->output("  Blocks: " . ($bitcoin['details']['blocks'] ?? 'N/A'));
                $this->output("  Network: " . ($bitcoin['details']['network'] ?? 'N/A'));
            }
        }

        // Cache
        $cache = $status['cache'] ?? [];
        $this->output("Cache:");
        $this->output("  Enabled: " . ($cache['enabled'] ? 'YES' : 'NO'));
        if ($cache['enabled']) {
            $this->output("  Driver: " . strtoupper($cache['driver']));
            $this->output("  Entries: " . ($cache['entries_count'] ?? 'N/A'));
        }
    }

    private function displayServerStatus(array $stats): void {
        $this->output("\n" . $this->formatter->formatTitle("CALENDAR SERVER STATUS"));

        $this->output("Total servers: {$stats['total_servers']}");
        $this->output("Responsive servers: {$stats['responsive_servers']}");
        $this->output("Success rate: " . round($stats['attestation_success_rate'], 1) . "%");

        if (!empty($stats['server_details'])) {
            $this->output("\nServer details:");
            foreach ($stats['server_details'] as $server) {
                $status = $server['responsive'] ? '✓ ONLINE' : '✗ OFFLINE';
                $time = $server['response_time'] ? " ({$server['response_time']}ms)" : '';
                $error = $server['error'] ? " - {$server['error']}" : '';
                $this->output("  - {$server['url']}: $status$time$error");
            }
        }
    }

    private function displayGeneralHelp(): void {
        $this->output($this->formatter->formatTitle("OPENTIMESTAMPS CLI"));
        $this->output("Create and verify blockchain timestamps\n");

        $this->output($this->formatter->formatSubtitle("USAGE"));
        $this->output("  ots <command> [options] [arguments]\n");

        $this->output($this->formatter->formatSubtitle("COMMANDS"));
        $this->output("  stamp <file>         Create a timestamp for a file");
        $this->output("  verify <ots> [file]  Verify a timestamp against original file");
        $this->output("  upgrade <ots>        Upgrade pending attestations");
        $this->output("  info <file>          Show timestamp information");
        $this->output("  status               Show system status");
        $this->output("  server <command>     Manage calendar servers");
        $this->output("  help [command]       Show help for a command\n");

        $this->output($this->formatter->formatSubtitle("EXAMPLES"));
        $this->output("  ots stamp document.pdf");
        $this->output("  ots verify document.pdf.ots document.pdf");
        $this->output("  ots upgrade document.pdf.ots");
        $this->output("  ots info document.pdf.ots");
        $this->output("  ots status");
        $this->output("  ots server status\n");

        $this->output("Use 'ots help <command>' for more information about a command.");
    }

    private function displayCommandHelp(string $command): void {
        $help = [
            'stamp' => [
                'description' => 'Create a timestamp for a file',
                'usage' => 'ots stamp [options] <file>',
                'options' => [
                    '-o, --output <file>' => 'Output file for the timestamp',
                    '-a, --attached' => 'Create attached timestamp (embed in file)',
                    '-w, --wait' => 'Wait for attestations to be confirmed',
                    '-c, --calendar <urls>' => 'Use specific calendar servers (comma-separated)',
                    '-t, --timeout <seconds>' => 'Request timeout in seconds'
                ],
                'examples' => [
                    'ots stamp document.pdf',
                    'ots stamp -o doc.timestamp document.pdf',
                    'ots stamp -a document.pdf',
                    'ots stamp -w document.pdf'
                ]
            ],
            'verify' => [
                'description' => 'Verify a timestamp against the original file',
                'usage' => 'ots verify [options] <ots_file> [original_file]',
                'options' => [
                    '-v, --verbose' => 'Show detailed verification information',
                    '-j, --json' => 'Output results in JSON format',
                    '-t, --time-window <seconds>' => 'Allowed time window for verification'
                ],
                'examples' => [
                    'ots verify document.pdf.ots document.pdf',
                    'ots verify -v document.pdf.ots',
                    'ots verify -j document.pdf.ots document.pdf'
                ]
            ],
            'upgrade' => [
                'description' => 'Upgrade pending attestations to confirmed ones',
                'usage' => 'ots upgrade [options] <ots_file>',
                'options' => [
                    '-f, --force' => 'Force upgrade even if no upgrades available',
                    '-c, --calendar <urls>' => 'Use specific calendar servers'
                ],
                'examples' => [
                    'ots upgrade document.pdf.ots',
                    'ots upgrade -f document.pdf.ots'
                ]
            ],
            'info' => [
                'description' => 'Show information about a timestamp file',
                'usage' => 'ots info [options] <file>',
                'options' => [
                    '-v, --verbose' => 'Show detailed information',
                    '-j, --json' => 'Output results in JSON format',
                    '-d, --detailed' => 'Show detailed attestation information'
                ],
                'examples' => [
                    'ots info document.pdf.ots',
                    'ots info -v document.pdf.ots',
                    'ots info -j document.pdf.ots'
                ]
            ]
        ];

        if (!isset($help[$command])) {
            $this->error("No help available for command: $command");
            return;
        }

        $cmd = $help[$command];
        
        $this->output($this->formatter->formatTitle(strtoupper($command)));
        $this->output($cmd['description'] . "\n");
        
        $this->output($this->formatter->formatSubtitle("USAGE"));
        $this->output("  " . $cmd['usage'] . "\n");
        
        if (!empty($cmd['options'])) {
            $this->output($this->formatter->formatSubtitle("OPTIONS"));
            foreach ($cmd['options'] as $option => $description) {
                $this->output("  " . str_pad($option, 30) . $description);
            }
            $this->output("");
        }
        
        if (!empty($cmd['examples'])) {
            $this->output($this->formatter->formatSubtitle("EXAMPLES"));
            foreach ($cmd['examples'] as $example) {
                $this->output("  " . $example);
            }
        }
    }

    private function getSystemStatus(): array {
        $status = [];

        // Calendar servers
        try {
            $calendarClient = $this->client->getCalendarClient();
            $status['calendar_servers'] = $calendarClient->getAttestationStats();
        } catch (\Exception $e) {
            $status['calendar_servers'] = ['error' => $e->getMessage()];
        }

        // Bitcoin node
        try {
            $blockchainManager = new BlockchainManager($this->config);
            $status['bitcoin_node'] = $blockchainManager->getBlockchainStatus()['bitcoin'] ?? [];
        } catch (\Exception $e) {
            $status['bitcoin_node'] = ['error' => $e->getMessage()];
        }

        // Cache
        try {
            $cacheManager = $this->client->getCacheManager();
            $status['cache'] = $cacheManager->getStats();
        } catch (\Exception $e) {
            $status['cache'] = ['error' => $e->getMessage()];
        }

        return $status;
    }

    private function parseGlobalOptions(array &$args): void {
        foreach ($args as $i => $arg) {
            if ($arg === '-v' || $arg === '--verbose') {
                $this->verbose = true;
                unset($args[$i]);
            } elseif ($arg === '-q' || $arg === '--quiet') {
                $this->quiet = true;
                unset($args[$i]);
            }
        }
        $args = array_values($args);
    }

    private function parseOptions(array &$args, array $options): array {
        $shortOpts = '';
        $longOpts = [];
        
        foreach ($options as $long => $short) {
            if (str_ends_with($short, ':')) {
                $longOpts[] = $long . ':';
            } else {
                $longOpts[] = $long;
            }
            $shortOpts .= $short;
        }

        $parsed = getopt($shortOpts, $longOpts);
        
        // Remove options from args
        foreach ($parsed as $key => $value) {
            foreach ($args as $i => $arg) {
                if ($arg === '-' . $key || $arg === '--' . $key) {
                    unset($args[$i]);
                    if ($value !== false) {
                        unset($args[$i + 1]);
                    }
                }
            }
        }

        $args = array_values($args);
        return $parsed ?: [];
    }

    private function initializeClient(): void {
        $config = ConfigurationFactory::createFromArray($this->config);
        $this->client = new Client(['config' => $config]);
    }

    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function output(string $message): void {
        if (!$this->quiet) {
            echo $message . "\n";
        }
    }

    private function info(string $message): void {
        if (!$this->quiet) {
            echo $this->formatter->formatInfo($message) . "\n";
        }
    }

    private function success(string $message): void {
        if (!$this->quiet) {
            echo $this->formatter->formatSuccess($message) . "\n";
        }
    }

    private function warning(string $message): void {
        if (!$this->quiet) {
            echo $this->formatter->formatWarning($message) . "\n";
        }
    }

    private function error(string $message): void {
        if (!$this->quiet) {
            echo $this->formatter->formatError($message) . "\n";
        }
    }
}