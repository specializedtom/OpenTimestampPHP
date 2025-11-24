<?php
// src/Attestations/EthereumAttestation.php

namespace OpenTimestampsPHP\Attestations;

class EthereumAttestation implements Attestation {
    private string $transactionHash;
    private int $blockNumber;

    public function __construct(string $transactionHash, int $blockNumber) {
        $this->transactionHash = $transactionHash;
        $this->blockNumber = $blockNumber;
    }

    public function getType(): string {
        return 'ethereum';
    }

    public function getTransactionHash(): string {
        return $this->transactionHash;
    }

    public function getBlockNumber(): int {
        return $this->blockNumber;
    }

    public function verify(string $message, array $context = []): array {
        $result = [
            'valid' => false,
            'verified' => false,
            'blockchain' => 'ethereum',
            'transaction_hash' => $this->transactionHash,
            'block_number' => $this->blockNumber,
            'error' => null,
            'transaction_data' => null
        ];

        try {
            $transaction = $this->fetchTransaction($this->transactionHash);
            $result['transaction_data'] = $transaction;
            
            // Check if message is in transaction data
            if ($this->verifyTransaction($message, $transaction)) {
                $result['valid'] = true;
                $result['verified'] = true;
            } else {
                $result['error'] = 'Message not found in transaction data';
            }
            
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    private function fetchTransaction(string $txHash): array {
        $explorers = [
            'https://api.etherscan.io/api',
            'https://cloudflare-eth.com'
        ];

        foreach ($explorers as $explorer) {
            try {
                if (strpos($explorer, 'etherscan.io') !== false) {
                    return $this->fetchFromEtherscan($txHash, $explorer);
                } else {
                    return $this->fetchFromRPC($txHash, $explorer);
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        throw new \Exception("All Ethereum explorers failed for transaction $txHash");
    }

    private function fetchFromEtherscan(string $txHash, string $baseUrl): array {
        // Note: Etherscan requires API key for production use
        $url = "$baseUrl?module=proxy&action=eth_getTransactionByHash&txhash=$txHash&apikey=freekey";
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        
        if (!isset($data['result']['hash'])) {
            throw new \Exception("Transaction not found");
        }

        return $data['result'];
    }

    private function fetchFromRPC(string $txHash, string $rpcUrl): array {
        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'eth_getTransactionByHash',
            'params' => [$txHash],
            'id' => 1
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => $request
            ]
        ]);

        $response = file_get_contents($rpcUrl, false, $context);
        $data = json_decode($response, true);
        
        if (!isset($data['result']['hash'])) {
            throw new \Exception("Transaction not found");
        }

        return $data['result'];
    }

    private function verifyTransaction(string $message, array $transaction): bool {
        // Check if message appears in transaction input data
        if (isset($transaction['input'])) {
            $inputData = hex2bin(substr($transaction['input'], 2));
            return strpos($inputData, $message) !== false;
        }
        
        return false;
    }

    public function serialize(): string {
        // Ethereum attestation serialization format
        $data = pack('C', 0x20) . 
                hex2bin($this->transactionHash) . 
                $this->encodeVarint($this->blockNumber);
        return $data;
    }

    private function encodeVarint(int $value): string {
        if ($value < 0xFD) {
            return chr($value);
        } elseif ($value <= 0xFFFF) {
            return pack('Cv', 0xFD, $value);
        } elseif ($value <= 0xFFFFFFFF) {
            return pack('CV', 0xFE, $value);
        } else {
            return pack('CP', 0xFF, $value);
        }
    }

    public function __toString(): string {
        return "EthereumAttestation(tx: " . substr($this->transactionHash, 0, 16) . "...)";
    }
}