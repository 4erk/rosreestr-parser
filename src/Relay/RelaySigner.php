<?php

namespace Rosreestr\Parser\Relay;

use RuntimeException;

final class RelaySigner
{
    public function __construct(
        private readonly string $privateKeyPath,
        private readonly string $session,
    ) {
    }

    public function headers(string $method, string $action, string $body = ''): array
    {
        if (!is_readable($this->privateKeyPath)) {
            throw new RuntimeException('Rosreestr relay private key is not readable.');
        }

        $privateKey = openssl_pkey_get_private(
            (string) file_get_contents($this->privateKeyPath),
        );

        if ($privateKey === false) {
            throw new RuntimeException('Rosreestr relay private key is invalid.');
        }

        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $canonical = self::canonicalRequest(
            $method,
            $action,
            $this->session,
            $timestamp,
            $nonce,
            $body,
        );

        if (!openssl_sign($canonical, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign Rosreestr relay request.');
        }

        return [
            'X-Rosreestr-Session' => $this->session,
            'X-Rosreestr-Timestamp' => $timestamp,
            'X-Rosreestr-Nonce' => $nonce,
            'X-Rosreestr-Signature' => base64_encode($signature),
        ];
    }

    public static function canonicalRequest(
        string $method,
        string $action,
        string $session,
        string $timestamp,
        string $nonce,
        string $body,
    ): string {
        return implode("\n", [
            strtoupper($method),
            $action,
            $session,
            $timestamp,
            $nonce,
            hash('sha256', $body),
        ]);
    }
}
