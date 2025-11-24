<?php
// src/Blockchain/BitcoinNodeClient.php

namespace OpenTimestampsPHP\Blockchain;

use OpenTimestampsPHP\Blockchain\Exception\BitcoinNodeException;

class BitcoinNodeClient {
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private float $timeout;
    private bool $useHttps;
    private string $rpcPath;
    
    public function __construct(array $config = []) {
        $this->host = $config['host'] ?? '127.0.0.1';
        $this->port = $config['port'] ?? 8332;
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->timeout = $config['timeout'] ?? 30.0;
        $this->useHttps = $config['use_https'] ?? false;
        $this->rpcPath = $config['rpc_path'] ?? '/';
    }

    /**
     * Get block header by height
     */
    public function getBlockHeaderByHeight(int $height): array {
        $hash = $this->getBlockHash($height);
        return $this->getBlockHeader($hash);
    }

    /**
     * Get block header by hash
     */
    public function getBlockHeader(string $blockHash): array {
        $response = $this->call('getblockheader', [$blockHash, true]);
        
        if (!isset($response['result'])) {
            throw new BitcoinNodeException("Failed to get block header for hash: $blockHash");
        }

        return $this->parseBlockHeader($response['result']);
    }

    /**
     * Get block hash by height
     */
    public function getBlockHash(int $height): string {
        $response = $this->call('getblockhash', [$height]);
        
        if (!isset($response['result'])) {
            throw new BitcoinNodeException("Failed to get block hash for height: $height");
        }

        return $response['result'];
    }

    /**
     * Get block by height
     */
    public function getBlockByHeight(int $height): array {
        $hash = $this->getBlockHash($height);
        return $this->getBlock($hash);
    }

    /**
     * Get block by hash
     */
    public function getBlock(string $blockHash): array {
        $response = $this->call('getblock', [$blockHash, 2]); // verbosity 2 for full tx data
        
        if (!isset($response['result'])) {
            throw new BitcoinNodeException("Failed to get block for hash: $blockHash");
        }

        return $response['result'];
    }

    /**
     * Get transaction by ID
     */
    public function getTransaction(string $txid, bool $includeWatchOnly = false): array {
        $response = $this->call('gettransaction', [$txid, $includeWatchOnly]);
        
        if (!isset($response['result'])) {
            throw new BitcoinNodeException("Failed to get transaction: $txid");
        }

        return $response['result'];
    }

    /**
     * Get raw transaction
     */
    public function getRawTransaction(string $txid, bool $verbose = true): array {
        $response = $this->call('getrawtransaction', [$txid, $verbose]);
        
        if (!isset($response['result'])) {
            throw new BitcoinNodeException("Failed to get raw transaction: $txid");
        }

        return $response['result'];
    }

    /**
     * Verify that a message is embedded in a block
     */
    public function verifyMessageInBlock(string $message, int $height): bool {
        try {
            $block = $this->getBlockByHeight($height);
            return $this->findMessageInBlock($message, $block);
        } catch (BitcoinNodeException $e) {
            return false;
        }
    }

    /**
     * Get blockchain info
     */
    public function getBlockchainInfo(): array {
        $response = $this->call('getblockchaininfo', []);
        
        if (!isset($response['result'])) {
            throw new BitcoinNodeException("Failed to get blockchain info");
        }

        return $response['result'];
    }

    /**
     * Get network info
     */
    public function getNetworkInfo(): array {
        $response = $this->call('getnetworkinfo', []);
        
        if (!isset($response['result'])) {
            throw new BitcoinNodeException("Failed to get network info");
        }

        return $response['result'];
    }

    /**
     * Test node connection
     */
    public function testConnection(): bool {
        try {
            $this->getBlockchainInfo();
            return true;
        } catch (BitcoinNodeException $e) {
            return false;
        }
    }

    /**
     * Make JSON-RPC call to Bitcoin node
     */
    private function call(string $method, array $params = []) {
        $request = [
            'method' => $method,
            'params' => $params,
            'id' => uniqid(),
            'jsonrpc' => '2.0'
        ];

        $url = ($this->useHttps ? 'https://' : 'http://') . 
               $this->host . ':' . $this->port . $this->rpcPath;

        $context = $this->createStreamContext($request);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            throw new BitcoinNodeException("RPC call failed: " . ($error['message'] ?? 'Unknown error'));
        }

        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BitcoinNodeException("Invalid JSON response: " . json_last_error_msg());
        }

        if (isset($data['error']) && $data['error'] !== null) {
            throw new BitcoinNodeException(
                "RPC error: " . ($data['error']['message'] ?? 'Unknown RPC error')
            );
        }

        return $data;
    }

    private function createStreamContext(array $request): resource {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password),
            'Connection: close'
        ];

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => json_encode($request),
                'timeout' => $this->timeout,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => $this->useHttps,
                'verify_peer_name' => $this->useHttps,
                'allow_self_signed' => !$this->useHttps
            ]
        ];

        return stream_context_create($options);
    }

    private function parseBlockHeader(array $rpcResponse): array {
        return [
            'hash' => $rpcResponse['hash'],
            'height' => $rpcResponse['height'],
            'version' => $rpcResponse['version'],
            'versionHex' => $rpcResponse['versionHex'],
            'merkleroot' => $rpcResponse['merkleroot'],
            'time' => $rpcResponse['time'],
            'mediantime' => $rpcResponse['mediantime'],
            'nonce' => $rpcResponse['nonce'],
            'bits' => $rpcResponse['bits'],
            'difficulty' => $rpcResponse['difficulty'],
            'chainwork' => $rpcResponse['chainwork'],
            'nTx' => $rpcResponse['nTx'],
            'previousblockhash' => $rpcResponse['previousblockhash'],
            'nextblockhash' => $rpcResponse['nextblockhash'] ?? null,
            'header_hex' => $this->constructHeaderHex($rpcResponse)
        ];
    }

    private function constructHeaderHex(array $header): string {
        // Convert all fields to little-endian hex
        $version = str_pad(dechex($header['version']), 8, '0', STR_PAD_LEFT);
        $prevHash = implode('', array_reverse(str_split($header['previousblockhash'], 2)));
        $merkleRoot = implode('', array_reverse(str_split($header['merkleroot'], 2)));
        $time = str_pad(dechex($header['time']), 8, '0', STR_PAD_LEFT);
        $bits = $header['bits'];
        $nonce = str_pad(dechex($header['nonce']), 8, '0', STR_PAD_LEFT);

        return $version . $prevHash . $merkleRoot . $time . $bits . $nonce;
    }

    private function findMessageInBlock(string $message, array $block): bool {
        // Check in coinbase transaction
        if (isset($block['tx'][0])) {
            $coinbaseTx = $block['tx'][0];
            if ($this->checkTransactionForMessage($message, $coinbaseTx)) {
                return true;
            }
        }

        // Check in other transactions (for non-coinbase attestations)
        foreach ($block['tx'] as $transaction) {
            if ($this->checkTransactionForMessage($message, $transaction)) {
                return true;
            }
        }

        return false;
    }

    private function checkTransactionForMessage(string $message, array $transaction): bool {
        // Check OP_RETURN outputs
        foreach ($transaction['vout'] as $output) {
            if (isset($output['scriptPubKey']['asm'])) {
                $asm = $output['scriptPubKey']['asm'];
                if (strpos($asm, 'OP_RETURN') === 0) {
                    // Extract data from OP_RETURN
                    $data = $this->extractOpReturnData($asm);
                    if ($data && strpos($data, $message) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function extractOpReturnData(string $asm): string {
        // OP_RETURN format: OP_RETURN <data in hex>
        $parts = explode(' ', $asm);
        if (count($parts) >= 2 && $parts[0] === 'OP_RETURN') {
            $hexData = $parts[1];
            return hex2bin($hexData);
        }
        return '';
    }

    /**
     * Get node status
     */
    public function getNodeStatus(): array {
        $blockchainInfo = $this->getBlockchainInfo();
        $networkInfo = $this->getNetworkInfo();

        return [
            'connected' => true,
            'blocks' => $blockchainInfo['blocks'],
            'headers' => $blockchainInfo['headers'],
            'difficulty' => $blockchainInfo['difficulty'],
            'size_on_disk' => $blockchainInfo['size_on_disk'],
            'pruned' => $blockchainInfo['pruned'],
            'version' => $networkInfo['version'],
            'protocol_version' => $networkInfo['protocolversion'],
            'connections' => $networkInfo['connections'],
            'network' => $blockchainInfo['chain']
        ];
    }
}