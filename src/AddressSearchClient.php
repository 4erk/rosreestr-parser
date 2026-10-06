<?php

namespace Rosreestr\Parser;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use JsonException;
use Rosreestr\Parser\Proxy\ProxyManager;
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

    public function __construct(
        public ?ProxyManager $manager = null,
        ?string $relayUrl = null,
        ?string $relayToken = null,
        ?string $relayPrivateKeyPath = null,
        int $maxAttempts = 2,
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
            );
        }

        return new self(
            ProxyManager::fromString(self::environmentValue($proxyVariable)),
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

        for ($attempt = 0; $attempt < $this->maxAttempts; $attempt++) {
            try {
                return $this->searchOnce($address);
            } catch (GuzzleException $e) {
                $lastException = $e;

                if (!$this->shouldRetry($e) || $attempt + 1 >= $this->maxAttempts) {
                    break;
                }

                $this->manager?->next();
                usleep(250_000);
            }
        }

        throw $lastException;
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

    private static function normalizeOptional(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
