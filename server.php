<?php

use GuzzleHttp\Exception\GuzzleException;
use Rosreestr\Parser\AddressSearchClient;
use Rosreestr\Parser\Client;
use Rosreestr\Parser\NumberRequest;
use Swoole\Http\Server;

require_once __DIR__ . '/vendor/autoload.php';

// Создание Swoole HTTP сервера
$http = new Server('0.0.0.0', 9501);

// Обработчик HTTP-запросов
$http->on('request', function ($request, $response) {

    switch ($request->server['request_uri']) {
        case '/':
            // Отправка HTML-страницы
            $response->end(file_get_contents(__DIR__ . '/index.html'));
            break;

        case '/captcha.png':
            // Получение и отправка изображения капчи
            $client = new Client(__DIR__ . '/cookies.txt');
            $captcha = $client->getCaptcha();
            $captcha->save('captcha.png');
            $response->header('Content-Type', 'image/png');
            $response->end($captcha->getBody());
            break;

        case '/request':
            // Обработка запроса к API Росреестра
            $client = new Client(__DIR__ . '/cookies.txt');
            $number = $request->post['number'];
            $objectType = $request->post['type'];
            $captcha = $request->post['captcha'];
            $request = new NumberRequest(
                [$number],
                [$objectType],
                $captcha
            );
            try {
                // Отправка запроса и получение результата
                $result = $client->sendRequest($request);
                $response->end(print_r($result, true));
            } catch (GuzzleException $e) {
                // Обработка ошибок при запросе
                $result = $e->getResponse()?->getBody()->getContents() ?? $e->getMessage();
                $response->end(print_r($result, true));
            }
            break;
        case '/address':
            $client = new AddressSearchClient();
            $result = $client->search($request->post['address']);
            $response->end(print_r($result, true));
    }

});

// Запуск сервера
$http->start();
