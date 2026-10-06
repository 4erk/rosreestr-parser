<?php

namespace Rosreestr\Parser\Tests;

use PHPUnit\Framework\TestCase;
use Rosreestr\Parser\Relay\RelaySigner;

final class RelaySignerTest extends TestCase
{
    public function testHeadersContainVerifiableSignature(): void
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        self::assertNotFalse($resource);
        self::assertTrue(openssl_pkey_export($resource, $privatePem));

        $details = openssl_pkey_get_details($resource);
        self::assertIsArray($details);

        $privatePath = tempnam(sys_get_temp_dir(), 'rr-relay-key-');
        self::assertNotFalse($privatePath);
        file_put_contents($privatePath, $privatePem);

        try {
            $session = str_repeat('a', 64);
            $body = '{"term":"Невский проспект 1"}';
            $signer = new RelaySigner($privatePath, $session);
            $headers = $signer->headers('POST', 'address', $body);

            self::assertSame($session, $headers['X-Rosreestr-Session']);
            self::assertMatchesRegularExpression('/^\d+$/', $headers['X-Rosreestr-Timestamp']);
            self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $headers['X-Rosreestr-Nonce']);

            $canonical = RelaySigner::canonicalRequest(
                'POST',
                'address',
                $session,
                $headers['X-Rosreestr-Timestamp'],
                $headers['X-Rosreestr-Nonce'],
                $body,
            );

            $signature = base64_decode($headers['X-Rosreestr-Signature'], true);
            self::assertNotFalse($signature);

            $publicKey = openssl_pkey_get_public($details['key']);
            self::assertNotFalse($publicKey);
            self::assertSame(
                1,
                openssl_verify($canonical, $signature, $publicKey, OPENSSL_ALGO_SHA256),
            );
        } finally {
            @unlink($privatePath);
        }
    }

    public function testCanonicalRequestIsStable(): void
    {
        self::assertSame(
            implode("\n", [
                'POST',
                'address',
                str_repeat('b', 64),
                '1700000000',
                str_repeat('c', 32),
                hash('sha256', '{"term":"test"}'),
            ]),
            RelaySigner::canonicalRequest(
                'post',
                'address',
                str_repeat('b', 64),
                '1700000000',
                str_repeat('c', 32),
                '{"term":"test"}',
            ),
        );
    }
}
