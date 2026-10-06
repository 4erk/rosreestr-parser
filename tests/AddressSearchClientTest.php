<?php

namespace Rosreestr\Parser\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rosreestr\Parser\AddressSearchClient;

final class AddressSearchClientTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('ROSREESTR_PROXY');
        putenv('ROSREESTR_RELAY_URL');
        putenv('ROSREESTR_RELAY_TOKEN');
        putenv('ROSREESTR_RELAY_PRIVATE_KEY');
    }

    public function testEnvironmentTransportIsOptional(): void
    {
        $client = AddressSearchClient::fromEnvironment();

        self::assertNull($client->manager);
        self::assertFalse($client->isRelayEnabled());
    }

    public function testEnvironmentProxyCreatesManager(): void
    {
        putenv('ROSREESTR_PROXY=http://proxy.example:3128');

        $client = AddressSearchClient::fromEnvironment();

        self::assertNotNull($client->manager);
        self::assertSame('http://proxy.example:3128', $client->manager->getProxy());
        self::assertFalse($client->isRelayEnabled());
    }

    public function testEnvironmentRelayTakesPrecedenceOverProxy(): void
    {
        putenv('ROSREESTR_PROXY=http://proxy.example:3128');
        putenv('ROSREESTR_RELAY_URL=https://example.test/relay.php');
        putenv('ROSREESTR_RELAY_PRIVATE_KEY=/tmp/relay-private.pem');

        $client = AddressSearchClient::fromEnvironment();

        self::assertNull($client->manager);
        self::assertTrue($client->isRelayEnabled());
    }

    public function testIncompleteRelayConfigurationIsRejected(): void
    {
        putenv('ROSREESTR_RELAY_URL=https://example.test/relay.php');

        $this->expectException(InvalidArgumentException::class);

        AddressSearchClient::fromEnvironment();
    }

    public function testEmptyAddressReturnsImmediately(): void
    {
        $client = new AddressSearchClient(maxAttempts: 1);

        self::assertSame([], $client->search('   '));
    }
}
