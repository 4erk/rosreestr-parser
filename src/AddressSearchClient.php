<?php

namespace Rosreestr\Parser;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use JsonException;
use Rosreestr\Parser\Response\AddressItem;

class AddressSearchClient
{
    public const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome';

    private HttpClient $client;


    public function __construct()
    {
        $this->client = new HttpClient(['base_uri' => 'https://lk.rosreestr.ru/']);
    }

    /**
     * @param string $address
     * @return array|AddressItem[]
     * @throws JsonException
     */
    public function search(string $address): array
    {
        for ($i = 0; $i < 10; $i++) {
            try {
                $response = $this->client->get('/account-back/address/search', [
                    RequestOptions::QUERY  => ['term' => $address],
                    RequestOptions::VERIFY => false,
                ]);
                break;
            } catch (GuzzleException $e) {
                sleep(1);
            }
        }
        if (!isset($response)) {
            return [];
        }
        $data = $response->getBody()->getContents();
        $data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        $result = [];
        foreach ($data as $item) {
            $result[] = AddressItem::createFromArray($item);
        }
        return $result;
    }
}
