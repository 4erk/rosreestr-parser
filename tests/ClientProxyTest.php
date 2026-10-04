<?php

namespace Rosreestr\Parser\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rosreestr\Parser\Client;

final class ClientProxyTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('ROSREESTR_PROXY');
        putenv('ROSREESTR_RELAY_URL');
        putenv('ROSREESTR_RELAY_TOKEN');
    }

    public function testEnvironmentTransportIsOptional(): void
    {
        $client = Client::fromEnvironment($this->cookiePath());

        self::assertNull($client->manager);
        self::assertFalse($client->isRelayEnabled());
    }

    public function testEnvironmentProxyCreatesManager(): void
    {
        putenv('ROSREESTR_PROXY=http://user:pass@proxy.example:18890');

        $client = Client::fromEnvironment($this->cookiePath());

        self::assertNotNull($client->manager);
        self::assertSame(
            'http://user:pass@proxy.example:18890',
            $client->manager->getProxy(),
        );
        self::assertFalse($client->isRelayEnabled());
    }

    public function testRelayTakesPrecedenceOverProxy(): void
    {
        putenv('ROSREESTR_PROXY=http://proxy.example:3128');
        putenv('ROSREESTR_RELAY_URL=https://example.test/relay.php');
        putenv('ROSREESTR_RELAY_TOKEN=secret-token');

        $client = Client::fromEnvironment($this->cookiePath());

        self::assertNull($client->manager);
        self::assertTrue($client->isRelayEnabled());
    }

    public function testRelayConfigurationMustBeComplete(): void
    {
        putenv('ROSREESTR_RELAY_URL=https://example.test/relay.php');

        $this->expectException(InvalidArgumentException::class);

        Client::fromEnvironment($this->cookiePath());
    }

    private function cookiePath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rr-cookie-');

        if ($path === false) {
            self::fail('Could not allocate a temporary cookie path.');
        }

        @unlink($path);

        return $path;
    }
}
