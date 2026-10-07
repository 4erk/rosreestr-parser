<?php

namespace Rosreestr\Parser\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Rosreestr\Parser\AddressSearchClient;
use Rosreestr\Parser\Exception\RateLimitException;
use Rosreestr\Parser\Proxy\ProxyManager;
use Rosreestr\Parser\RateLimit\AddressSearchRateLimiter;

final class AddressSearchClientTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach ([
            'ROSREESTR_PROXY',
            'ROSREESTR_PROXIES',
            'ROSREESTR_RELAY_URL',
            'ROSREESTR_RELAY_TOKEN',
            'ROSREESTR_RELAY_PRIVATE_KEY',
            'ROSREESTR_ADDRESS_MAX_ATTEMPTS',
            'ROSREESTR_ADDRESS_RATE_STATE_DIR',
            'ROSREESTR_ADDRESS_MIN_INTERVAL_MS',
            'ROSREESTR_ADDRESS_429_COOLDOWN_SECONDS',
        ] as $variable) {
            putenv($variable);
        }
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
        $client = new AddressSearchClient(
            maxAttempts: 1,
            rateLimiter: $this->newLimiter(),
        );

        self::assertSame([], $client->search('   '));
    }

    public function testRateLimitResponseIsTypedAndNotRetried(): void
    {
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => '7'], '{"error":"rate limited"}'),
            new Response(200, [], '[]'),
        ]);
        $client = $this->clientWithMockHandler($mock, 2);

        try {
            $client->search('Москва, Тверская улица, 1');
            self::fail('Expected a typed rate-limit exception.');
        } catch (RateLimitException $exception) {
            self::assertSame(429, $exception->getCode());
            self::assertSame(7, $exception->retryAfterSeconds);
            self::assertStringStartsWith('direct:', $exception->route);
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

    public function testRateLimitRotatesToNextProxy(): void
    {
        $mock = new MockHandler([
            new Response(429, [], '{"error":"rate limited"}'),
            new Response(200, [], '[]'),
        ]);
        $manager = new ProxyManager([
            'http://proxy-a.example:3128',
            'http://proxy-b.example:3128',
        ]);
        $client = $this->clientWithMockHandler($mock, 2, $manager);

        self::assertSame([], $client->search('Москва, Тверская улица, 1'));
        self::assertSame('http://proxy-b.example:3128', $manager->getProxy());
        self::assertCount(0, $mock);
    }

    public function testRateLimitStopsAfterDistinctProxiesAreExhausted(): void
    {
        $mock = new MockHandler([
            new Response(429, [], '{"error":"rate limited"}'),
            new Response(429, [], '{"error":"rate limited"}'),
            new Response(200, [], '[]'),
        ]);
        $manager = new ProxyManager([
            'http://proxy-a.example:3128',
            'http://proxy-b.example:3128',
        ]);
        $client = $this->clientWithMockHandler($mock, 2, $manager);

        try {
            $client->search('Москва, Тверская улица, 1');
            self::fail('Expected all proxies to be rate limited.');
        } catch (RateLimitException) {
            self::assertCount(1, $mock);
        }
    }

    public function testServerErrorRotatesProxy(): void
    {
        $mock = new MockHandler([
            new Response(503, [], '{"error":"temporary"}'),
            new Response(200, [], '[]'),
        ]);
        $manager = new ProxyManager([
            'http://proxy-a.example:3128',
            'http://proxy-b.example:3128',
        ]);
        $client = $this->clientWithMockHandler($mock, 2, $manager);

        self::assertSame([], $client->search('Москва, Тверская улица, 1'));
        self::assertSame('http://proxy-b.example:3128', $manager->getProxy());
    }

    public function testNetworkErrorRotatesProxy(): void
    {
        $request = new Request(
            'GET',
            'https://lk.rosreestr.ru/account-back/address/search',
        );
        $mock = new MockHandler([
            new ConnectException('connection failed', $request),
            new Response(200, [], '[]'),
        ]);
        $manager = new ProxyManager([
            'http://proxy-a.example:3128',
            'http://proxy-b.example:3128',
        ]);
        $client = $this->clientWithMockHandler($mock, 2, $manager);

        self::assertSame([], $client->search('Москва, Тверская улица, 1'));
        self::assertSame('http://proxy-b.example:3128', $manager->getProxy());
    }

    public function testMultipleProxiesCanComeFromEnvironment(): void
    {
        putenv(
            'ROSREESTR_PROXIES='
            . 'http://proxy-a.example:3128,http://proxy-b.example:3128',
        );
        putenv('ROSREESTR_ADDRESS_MIN_INTERVAL_MS=0');

        $client = AddressSearchClient::fromEnvironment();

        self::assertNotNull($client->manager);
        self::assertSame(2, $client->manager->count());
        self::assertTrue($client->manager->hasMultiple());
    }

    public function testCooldownIsSharedAcrossClientsOnSameRoute(): void
    {
        $limiter = $this->newLimiter(cooldownSeconds: 30);
        $firstMock = new MockHandler([
            new Response(429, [], '{"error":"rate limited"}'),
        ]);
        $first = $this->clientWithMockHandler(
            $firstMock,
            1,
            null,
            $limiter,
        );

        try {
            $first->search('Москва, Тверская улица, 1');
            self::fail('Expected the first request to be rate limited.');
        } catch (RateLimitException) {
        }

        $secondMock = new MockHandler([
            new Response(200, [], '[]'),
        ]);
        $second = $this->clientWithMockHandler(
            $secondMock,
            1,
            null,
            $limiter,
        );

        try {
            $second->search('Москва, Тверская улица, 2');
            self::fail('Expected shared cooldown to block the second client.');
        } catch (RateLimitException $exception) {
            self::assertGreaterThanOrEqual(1, $exception->retryAfterSeconds);
        }

        self::assertCount(1, $secondMock);
    }

    public function testRelayRateLimitDoesNotAttemptProxyRotation(): void
    {
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => '4'], '{"error":"rate limited"}'),
            new Response(200, [], '[]'),
        ]);
        $client = new AddressSearchClient(
            null,
            'https://relay.example/relay.php',
            'relay-token',
            maxAttempts: 3,
            rateLimiter: $this->newLimiter(),
        );
        $httpClient = new HttpClient([
            'handler' => HandlerStack::create($mock),
        ]);

        $property = new ReflectionProperty(
            AddressSearchClient::class,
            'relayClient',
        );
        $property->setValue($client, $httpClient);

        try {
            $client->search('Москва, Тверская улица, 1');
            self::fail('Expected relay rate-limit exception.');
        } catch (RateLimitException $exception) {
            self::assertSame(4, $exception->retryAfterSeconds);
            self::assertStringStartsWith('relay:', $exception->route);
        }

        self::assertNull($client->manager);
        self::assertCount(1, $mock);
    }

    private function clientWithMockHandler(
        MockHandler $mock,
        int $maxAttempts,
        ?ProxyManager $manager = null,
        ?AddressSearchRateLimiter $limiter = null,
    ): AddressSearchClient {
        $client = new AddressSearchClient(
            $manager,
            maxAttempts: $maxAttempts,
            rateLimiter: $limiter ?? $this->newLimiter(),
        );
        $httpClient = new HttpClient([
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'https://lk.rosreestr.ru/',
        ]);

        $property = new ReflectionProperty(AddressSearchClient::class, 'client');
        $property->setValue($client, $httpClient);

        return $client;
    }

    private function newLimiter(
        int $minIntervalMs = 0,
        int $cooldownSeconds = 10,
    ): AddressSearchRateLimiter {
        return new AddressSearchRateLimiter(
            sys_get_temp_dir()
                . '/rosreestr-parser-test-'
                . bin2hex(random_bytes(8)),
            $minIntervalMs,
            $cooldownSeconds,
        );
    }
}
