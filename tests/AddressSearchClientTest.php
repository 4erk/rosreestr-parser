<?php

namespace Rosreestr\Parser\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
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

    public function testRateLimitResponseIsNotRetried(): void
    {
        $mock = new MockHandler([
            new Response(429, [], '{"error":"rate limited"}'),
            new Response(200, [], '[]'),
        ]);
        $client = $this->clientWithMockHandler($mock, 2);

        try {
            $client->search('Москва, Тверская улица, 1');
            self::fail('Expected a 429 client exception.');
        } catch (ClientException $exception) {
            self::assertSame(429, $exception->getResponse()->getStatusCode());
        }

        self::assertCount(1, $mock);
    }

    public function testServerErrorCanStillBeRetried(): void
    {
        $mock = new MockHandler([
            new Response(503, [], '{"error":"temporary"}'),
            new Response(200, [], '[]'),
        ]);
        $client = $this->clientWithMockHandler($mock, 2);

        self::assertSame([], $client->search('Москва, Тверская улица, 1'));
        self::assertCount(0, $mock);
    }

    private function clientWithMockHandler(MockHandler $mock, int $maxAttempts): AddressSearchClient
    {
        $client = new AddressSearchClient(maxAttempts: $maxAttempts);
        $httpClient = new HttpClient([
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'https://lk.rosreestr.ru/',
        ]);

        $property = new ReflectionProperty(AddressSearchClient::class, 'client');
        $property->setValue($client, $httpClient);

        return $client;
    }
}
