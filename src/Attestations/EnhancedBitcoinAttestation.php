<?php
// src/Attestations/EnhancedBitcoinAttestation.php

namespace OpenTimestampsPHP\Attestations;

use OpenTimestampsPHP\Blockchain\BitcoinNodeClient;
use OpenTimestampsPHP\Blockchain\Exception\BitcoinNodeException;

class EnhancedBitcoinAttestation extends BitcoinBlockHeaderAttestation {
    private ?BitcoinNodeClient $nodeClient = null;
    private bool $preferNode = true;

    public function setNodeClient(BitcoinNodeClient $client): void {
        $this->nodeClient = $client;
    }

    public function setPreferNode(bool $prefer): void {
        $this->preferNode = $prefer;
    }

    protected function fetchBlockHeader(int $height): array {
        // Try node first if available and preferred
        if ($this->preferNode && $this->nodeClient) {
            try {
                return $this->fetchFromNode($height);
            } catch (BitcoinNodeException $e) {
                // Fall back to explorers
                if ($this->preferNode) {
                    throw $e;
                }
            }
        }

        // Try explorers
        try {
            return parent::fetchBlockHeader($height);
        } catch (\Exception $e) {
            // If explorers fail and we have a node, try it as fallback
            if ($this->nodeClient && !$this->preferNode) {
                return $this->fetchFromNode($height);
            }
            throw $e;
        }
    }

    private function fetchFromNode(int $height): array {
        if (!$this->nodeClient) {
            throw new BitcoinNodeException("Bitcoin node client not configured");
        }

        return $this->nodeClient->getBlockHeaderByHeight($height);
    }

    protected function verifyBlockHeader(string $message, array $blockHeader): bool {
        // If we have a node client, use it for more robust verification
        if ($this->nodeClient) {
            return $this->verifyWithNode($message, $blockHeader);
        }

        // Fall back to basic verification
        return parent::verifyBlockHeader($message, $blockHeader);
    }

    private function verifyWithNode(string $message, array $blockHeader): bool {
        $height = $blockHeader['height'];
        
        try {
            return $this->nodeClient->verifyMessageInBlock($message, $height);
        } catch (BitcoinNodeException $e) {
            // If node verification fails, fall back to basic check
            $headerBinary = hex2bin($blockHeader['header_hex']);
            return strpos($headerBinary, $message) !== false;
        }
    }

    /**
     * Enhanced verification with merkle proof
     */
    public function verifyWithMerkleProof(string $message, array $blockHeader): array {
        $result = [
            'verified' => false,
            'method' => 'basic',
            'merkle_proof' => null,
            'error' => null
        ];

        if (!$this->nodeClient) {
            $result['verified'] = $this->verifyBlockHeader($message, $blockHeader);
            $result['method'] = 'basic';
            return $result;
        }

        try {
            $height = $blockHeader['height'];
            $block = $this->nodeClient->getBlockByHeight($height);
            
            // Look for the message in transactions
            foreach ($block['tx'] as $txIndex => $transaction) {
                if ($this->checkTransactionForMessage($message, $transaction)) {
                    $result['verified'] = true;
                    $result['method'] = 'merkle_proof';
                    $result['merkle_proof'] = [
                        'tx_index' => $txIndex,
                        'tx_id' => $transaction['txid'],
                        'block_hash' => $block['hash'],
                        'merkle_root' => $block['merkleroot']
                    ];
                    break;
                }
            }

            if (!$result['verified']) {
                // Fall back to basic verification
                $result['verified'] = parent::verifyBlockHeader($message, $blockHeader);
                $result['method'] = 'basic_fallback';
            }

        } catch (BitcoinNodeException $e) {
            $result['error'] = $e->getMessage();
            $result['verified'] = parent::verifyBlockHeader($message, $blockHeader);
            $result['method'] = 'basic_fallback_error';
        }

        return $result;
    }

    private function checkTransactionForMessage(array $transaction, string $message): bool {
        // Check scriptSig (coinbase) and scriptPubKey (OP_RETURN)
        foreach ($transaction['vin'] as $input) {
            if (isset($input['coinbase'])) {
                $coinbaseData = hex2bin($input['coinbase']);
                if (strpos($coinbaseData, $message) !== false) {
                    return true;
                }
            }
        }

        foreach ($transaction['vout'] as $output) {
            if (isset($output['scriptPubKey']['asm'])) {
                $asm = $output['scriptPubKey']['asm'];
                if (strpos($asm, 'OP_RETURN') === 0) {
                    $parts = explode(' ', $asm);
                    if (count($parts) >= 2) {
                        $data = hex2bin($parts[1]);
                        if (strpos($data, $message) !== false) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }
}