<?php

namespace Rosreestr\Parser;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use JsonException;
use Rosreestr\Parser\Exception\RateLimitException;
use Rosreestr\Parser\Proxy\ProxyManager;
use Rosreestr\Parser\RateLimit\AddressSearchRateLimiter;
use Rosreestr\Parser\Relay\RelaySigner;
use Rosreestr\Parser\Response\AddressItem;

class AddressSearchClient
{
    private HttpClient $client;
    private ?HttpClient $relayClient = null;
    private ?string $relayUrl = null;
    private ?string $relayToken = null;
    private ?RelaySigner $relaySigner = null;
    private ?string $relaySession = null;
    private int $maxAttempts;
    private AddressSearchRateLimiter $rateLimiter;

    public function __construct(
        public ?ProxyManager $manager = null,
        ?string $relayUrl = null,
        ?string $relayToken = null,
        ?string $relayPrivateKeyPath = null,
        int $maxAttempts = 2,
        ?AddressSearchRateLimiter $rateLimiter = null,
    ) {
        $relayUrl = self::normalizeOptional($relayUrl);
        $relayToken = self::normalizeOptional($relayToken);
        $relayPrivateKeyPath = self::normalizeOptional($relayPrivateKeyPath);

        if ($relayUrl === null && ($relayToken !== null || $relayPrivateKeyPath !== null)) {
            throw new InvalidArgumentException(
                'Rosreestr relay URL is required when relay authentication is configured.',
            );
        }

        if ($relayUrl !== null && $relayToken === null && $relayPrivateKeyPath === null) {
            throw new InvalidArgumentException(
                'Rosreestr relay requires a token or private signing key.',
            );
        }

        $this->maxAttempts = max(1, $maxAttempts);
        $this->rateLimiter = $rateLimiter ?? AddressSearchRateLimiter::fromEnvironment();

        if ($relayUrl !== null) {
            $this->relayUrl = $relayUrl;
            $this->relayToken = $relayToken;
            $this->relaySession = hash('sha256', 'address:' . bin2hex(random_bytes(16)));

            if ($relayPrivateKeyPath !== null) {
                $this->relaySigner = new RelaySigner(
                    $relayPrivateKeyPath,
                    $this->relaySession,
                );
            }

            $this->relayClient = new HttpClient([
                RequestOptions::TIMEOUT => 15,
                RequestOptions::CONNECT_TIMEOUT => 5,
                RequestOptions::HEADERS => [
                    'User-Agent' => Client::USER_AGENT,
                ],
            ]);
        }

        $this->client = new HttpClient([
            'base_uri' => 'https://lk.rosreestr.ru/',
            RequestOptions::VERIFY => false,
            RequestOptions::TIMEOUT => 15,
            RequestOptions::CONNECT_TIMEOUT => 5,
            RequestOptions::HEADERS => [
                'User-Agent' => Client::USER_AGENT,
            ],
        ]);
    }

    public static function fromEnvironment(
        string $proxyVariable = 'ROSREESTR_PROXY',
        string $relayUrlVariable = 'ROSREESTR_RELAY_URL',
        string $relayTokenVariable = 'ROSREESTR_RELAY_TOKEN',
        string $relayPrivateKeyVariable = 'ROSREESTR_RELAY_PRIVATE_KEY',
        string $proxiesVariable = 'ROSREESTR_PROXIES',
    ): self {
        $relayUrl = self::environmentValue($relayUrlVariable);
        $relayToken = self::environmentValue($relayTokenVariable);
        $relayPrivateKey = self::environmentValue($relayPrivateKeyVariable);

        if ($relayUrl !== null || $relayToken !== null || $relayPrivateKey !== null) {
            return new self(
                null,
                $relayUrl,
                $relayToken,
                $relayPrivateKey,
                self::environmentInt('ROSREESTR_ADDRESS_MAX_ATTEMPTS', 2, 1, 20),
            );
        }

        $manager = ProxyManager::fromString(
            self::environmentValue($proxiesVariable)
            ?? self::environmentValue($proxyVariable),
        );
        $defaultAttempts = max(2, $manager?->count() ?? 1);

        return new self(
            $manager,
            maxAttempts: self::environmentInt(
                'ROSREESTR_ADDRESS_MAX_ATTEMPTS',
                $defaultAttempts,
                1,
                20,
            ),
        );
    }

    public function isRelayEnabled(): bool
    {
        return $this->relayClient !== null;
    }

    /**
     * @return AddressItem[]
     * @throws GuzzleException
     * @throws JsonException
     */
    public function search(string $address): array
    {
        $address = trim($address);

        if ($address === '') {
            return [];
        }

        $lastException = null;
        $triedRoutes = [];
        $attemptBudget = $this->manager?->hasMultiple()
            ? max($this->maxAttempts, $this->manager->count())
            : $this->maxAttempts;

        for ($attempt = 0; $attempt < $attemptBudget; $attempt++) {
            $route = $this->currentRoute();
            $triedRoutes[$route] = true;

            try {
                $this->rateLimiter->beforeRequest($route);

                return $this->searchOnce($address);
            } catch (RateLimitException $e) {
                $lastException = $e;

                if ($this->rotateToUntriedProxy($triedRoutes)) {
                    continue;
                }

                throw $e;
            } catch (RequestException $e) {
                $status = $e->getResponse()?->getStatusCode();

                if ($status === 429) {
                    $retryAfter = $this->retryAfterSeconds($e) ?? 10;
                    $this->rateLimiter->markRateLimited($route, $retryAfter);
                    $rateLimit = new RateLimitException($retryAfter, $route, $e);
                    $lastException = $rateLimit;

                    if ($this->rotateToUntriedProxy($triedRoutes)) {
                        continue;
                    }

                    throw $rateLimit;
                }

                $lastException = $e;

                if (!$this->shouldRetry($e) || $attempt + 1 >= $attemptBudget) {
                    break;
                }

                if ($this->manager?->hasMultiple()) {
                    if (!$this->rotateToUntriedProxy($triedRoutes)) {
                        break;
                    }
                } else {
                    usleep(250_000);
                }
            } catch (GuzzleException $e) {
                $lastException = $e;

                if ($attempt + 1 >= $attemptBudget) {
                    break;
                }

                if ($this->manager?->hasMultiple()) {
                    if (!$this->rotateToUntriedProxy($triedRoutes)) {
                        break;
                    }
                } else {
                    usleep(250_000);
                }
            }
        }

        if ($lastException !== null) {
            throw $lastException;
        }

        throw new \RuntimeException('Rosreestr address search failed without an exception.');
    }

    /**
     * @return AddressItem[]
     * @throws GuzzleException
     * @throws JsonException
     */
    private function searchOnce(string $address): array
    {
        if ($this->relayClient !== null) {
            $payload = json_encode(
                ['term' => $address],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );

            $response = $this->relayClient->post($this->relayUrl, [
                RequestOptions::QUERY => ['action' => 'address'],
                RequestOptions::HEADERS => array_merge(
                    $this->relayHeaders('POST', 'address', $payload),
                    ['Content-Type' => 'application/json'],
                ),
                RequestOptions::BODY => $payload,
            ]);
        } else {
            $response = $this->client->get('/account-back/address/search', [
                RequestOptions::QUERY => ['term' => $address],
                RequestOptions::PROXY => $this->manager?->getProxy(),
            ]);
        }

        $data = json_decode(
            $response->getBody()->getContents(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (!is_array($data)) {
            return [];
        }

        return array_map(
            static fn (array $item): AddressItem => AddressItem::createFromArray($item),
            $data,
        );
    }

    private function currentRoute(): string
    {
        if ($this->relayClient !== null) {
            return 'relay:' . substr(hash('sha256', (string) $this->relayUrl), 0, 16);
        }

        $proxy = $this->manager?->getProxy();

        if ($proxy !== null) {
            return 'proxy:' . substr(hash('sha256', $proxy), 0, 16);
        }

        return 'direct:lk.rosreestr.ru';
    }

    private function rotateToUntriedProxy(array $triedRoutes): bool
    {
        if (!$this->manager?->hasMultiple()) {
            return false;
        }

        $candidates = $this->manager->count() - 1;

        for ($i = 0; $i < $candidates; $i++) {
            $this->manager->next();
            $route = $this->currentRoute();

            if (!isset($triedRoutes[$route])) {
                return true;
            }
        }

        return false;
    }

    private function retryAfterSeconds(RequestException $exception): ?int
    {
        $response = $exception->getResponse();

        if ($response === null) {
            return null;
        }

        $retryAfter = trim($response->getHeaderLine('Retry-After'));

        if ($retryAfter === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $retryAfter)) {
            return max(1, (int) $retryAfter);
        }

        $timestamp = strtotime($retryAfter);

        if ($timestamp === false) {
            return null;
        }

        return max(1, $timestamp - time());
    }

    private function shouldRetry(GuzzleException $exception): bool
    {
        if (!$exception instanceof RequestException || !$exception->hasResponse()) {
            return true;
        }

        return $exception->getResponse()->getStatusCode() >= 500;
    }

    private function relayHeaders(string $method, string $action, string $body): array
    {
        $headers = [
            'X-Rosreestr-Session' => $this->relaySession,
            'Accept' => 'application/json',
        ];

        if ($this->relaySigner !== null) {
            return array_merge(
                $headers,
                $this->relaySigner->headers($method, $action, $body),
            );
        }

        $headers['Authorization'] = 'Bearer ' . $this->relayToken;

        return $headers;
    }

    private static function environmentValue(string $name): ?string
    {
        $value = getenv($name);

        return $value === false ? null : self::normalizeOptional($value);
    }

    private static function environmentInt(
        string $name,
        int $default,
        int $minimum,
        int $maximum,
    ): int {
        $value = self::environmentValue($name);

        if ($value === null || !preg_match('/^\d+$/', $value)) {
            return $default;
        }

        return max($minimum, min($maximum, (int) $value));
    }

    private static function normalizeOptional(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
