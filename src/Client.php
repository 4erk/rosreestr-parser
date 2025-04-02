<?php

namespace Rosreestr\Parser;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Cookie\CookieJarInterface;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use JsonException;
use Rosreestr\Parser\Proxy\ProxyManager;

/**
 * Класс Client представляет клиент для взаимодействия с API Росреестра.
 */
class Client
{
    public const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome';

    private HttpClient $client;
    private CookieJarInterface $cookieJar;

    /**
     * Конструктор класса Client.
     *
     * @param string $cookiePath Путь для сохранения файла куки
     */
    public function __construct(private readonly string $cookiePath, public ?ProxyManager $manager = null)
    {
        $this->cookieJar = new FileCookieJar($this->cookiePath, true);
        $this->client = new HttpClient([
            'base_uri'              => 'https://lk.rosreestr.ru/',
            RequestOptions::COOKIES => $this->cookieJar,
            RequestOptions::VERIFY  => false,
            RequestOptions::HEADERS => [
                'User-Agent' => self::USER_AGENT,
            ],
        ]);
    }

    /**
     * Получает изображение капчи с сервера Росреестра.
     *
     * @return Captcha Объект капчи с содержимым изображения
     * @throws GuzzleException В случае ошибки при выполнении HTTP-запроса
     */
    public function getCaptcha(): Captcha
    {
        $response = $this->client->get('/account-back/captcha.png', [
            RequestOptions::PROXY =>  $this->manager?->getProxy(),
        ]);
        $this->cookieJar->save($this->cookiePath);
        return new Captcha($response->getBody()->getContents());
    }

    /**
     * Отправляет запрос к API Росреестра.
     *
     * @param RequestInterface $request Объект запроса
     * @return Response Ответ от API Росреестра
     * @throws GuzzleException В случае ошибки при выполнении HTTP-запроса
     * @throws JsonException В случае ошибки при парсинге JSON-ответа
     */
    public function sendRequest(RequestInterface $request): Response
    {
        $captcha = $request->getCaptcha();
        $this->client->get('/account-back/captcha/' . $captcha, [
            RequestOptions::PROXY =>  $this->manager?->getProxy(),
        ]);
        $response = $this->client->post('/account-back/on', [
            RequestOptions::PROXY =>  $this->manager?->getProxy(),
        ]);
        $this->cookieJar->save($this->cookiePath);
        $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        return Response::createFromArray($data['elements'] ?? []);
    }

    public function updateProxy(): void
    {
        $this->manager->next();
    }
}
