<?php

namespace Rosreestr\Parser;

/**
 * Класс Captcha представляет объект капчи для работы с API Росреестра.
 */
class Captcha
{
    /**
     * Конструктор класса Captcha.
     *
     * @param string $body Содержимое изображения капчи
     * @throws \RuntimeException Если содержимое капчи не загружено
     */
    public function __construct(private string $body)
    {
        if (!isset($this->body)) {
            throw new \RuntimeException('Captcha body is not loaded');
        }
    }

    /**
     * Возвращает содержимое изображения капчи.
     *
     * @return string Содержимое изображения капчи
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Загружает содержимое капчи из файла.
     *
     * @param string $path Путь к файлу с изображением капчи
     */
    public function load($path): void
    {
        $this->body = file_get_contents($path);
    }

    /**
     * Сохраняет содержимое капчи в файл.
     *
     * @param string $path Путь для сохранения файла с изображением капчи
     */
    public function save($path): void
    {
        file_put_contents($path, $this->getBody());
    }
}
