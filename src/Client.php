<?php

namespace Rosreestr\Parser;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Cookie\CookieJarInterface;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use JsonException;
use Rosreestr\Parser\Proxy\ProxyManager;
use Rosreestr\Parser\Relay\RelaySigner;

/**
 * Client for the Rosreestr API.
 */
class Client
{
    public const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome';

    private HttpClient $client;
    private CookieJarInterface $cookieJar;
    private ?HttpClient $relayClient = null;
    private ?string $relayUrl = null;
    private ?string $relayToken = null;
    private ?RelaySigner $relaySigner = null;
    private ?string $relaySession = null;

    public function __construct(
        private readonly string $cookiePath,
        public ?ProxyManager $manager = null,
        ?string $relayUrl = null,
        ?string $relayToken = null,
        ?string $relayPrivateKeyPath = null,
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

        if ($relayUrl !== null) {
            $this->relayUrl = $relayUrl;
            $this->relayToken = $relayToken;
            $this->relaySession = hash('sha256', $cookiePath);

            if ($relayPrivateKeyPath !== null) {
                $this->relaySigner = new RelaySigner(
                    $relayPrivateKeyPath,
                    $this->relaySession,
                );
            }

            $this->relayClient = new HttpClient([
                RequestOptions::TIMEOUT => 60,
                RequestOptions::CONNECT_TIMEOUT => 60,
                RequestOptions::HEADERS => [
                    'User-Agent' => self::USER_AGENT,
                ],
            ]);
        }

        $this->cookieJar = new FileCookieJar($this->cookiePath, true);
        $this->client = new HttpClient([
            'base_uri' => 'https://lk.rosreestr.ru/',
            RequestOptions::COOKIES => $this->cookieJar,
            RequestOptions::VERIFY => false,
            RequestOptions::TIMEOUT => 60,
            RequestOptions::CONNECT_TIMEOUT => 60,
            RequestOptions::HEADERS => [
                'User-Agent' => self::USER_AGENT,
            ],
        ]);
    }

    public static function fromEnvironment(
        string $cookiePath,
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
                $cookiePath,
                null,
                $relayUrl,
                $relayToken,
                $relayPrivateKey,
            );
        }

        return new self(
            $cookiePath,
            ProxyManager::fromString(self::environmentValue($proxyVariable)),
        );
    }

    public function isRelayEnabled(): bool
    {
        return $this->relayClient !== null;
    }

    /**
     * @throws GuzzleException
     */
    public function getCaptcha(): Captcha
    {
        if ($this->relayClient !== null) {
            $response = $this->relayClient->get($this->relayUrl, [
                RequestOptions::QUERY => ['action' => 'captcha'],
                RequestOptions::HEADERS => $this->relayHeaders('GET', 'captcha', ''),
            ]);

            return new Captcha($response->getBody()->getContents());
        }

        $response = $this->client->get('/account-back/captcha.png', [
            RequestOptions::PROXY => $this->manager?->getProxy(),
        ]);
        $this->cookieJar->save($this->cookiePath);

        return new Captcha($response->getBody()->getContents());
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function sendRequest(RequestInterface $request): Response
    {
        if ($this->relayClient !== null) {
            $payload = json_encode(
                $request->toParams(),
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );

            $response = $this->relayClient->post($this->relayUrl, [
                RequestOptions::QUERY => ['action' => 'search'],
                RequestOptions::HEADERS => array_merge(
                    $this->relayHeaders('POST', 'search', $payload),
                    ['Content-Type' => 'application/json'],
                ),
                RequestOptions::BODY => $payload,
            ]);

            $data = json_decode(
                $response->getBody()->getContents(),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            return Response::createFromArray($data['elements'] ?? []);
        }

        $captcha = $request->getCaptcha();
        $this->client->get('/account-back/captcha/' . rawurlencode($captcha), [
            RequestOptions::PROXY => $this->manager?->getProxy(),
        ]);
        $response = $this->client->post('/account-back/on', [
            RequestOptions::PROXY => $this->manager?->getProxy(),
            RequestOptions::JSON => $request->toParams(),
        ]);
        $this->cookieJar->save($this->cookiePath);

        $data = json_decode(
            $response->getBody()->getContents(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return Response::createFromArray($data['elements'] ?? []);
    }

    public function updateProxy(): void
    {
        $this->manager?->next();
    }

    private function relayHeaders(string $method, string $action, string $body): array
    {
        $headers = [
            'X-Rosreestr-Session' => $this->relaySession,
            'Accept' => 'application/json, image/png',
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

    public static function relayCanonicalRequest(
        string $method,
        string $action,
        string $session,
        string $timestamp,
        string $nonce,
        string $body,
    ): string {
        return RelaySigner::canonicalRequest(
            $method,
            $action,
            $session,
            $timestamp,
            $nonce,
            $body,
        );
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
