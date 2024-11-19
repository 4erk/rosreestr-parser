<?php

namespace Rosreestr\Parser;

/**
 * Интерфейс RequestInterface определяет методы для работы с запросами к API Росреестра.
 */
interface RequestInterface
{
    /**
     * Константа, определяющая тип фильтрации по кадастровому номеру.
     */
    const FILTER_BY_NUMBER = 'cadastral';

    /**
     * Устанавливает значение капчи для запроса.
     *
     * @param string $captcha Значение капчи
     */
    public function setCaptcha(string $captcha): void;

    /**
     * Возвращает текущее значение капчи.
     *
     * @return string Значение капчи
     */
    public function getCaptcha(): string;

    /**
     * Преобразует текущий объект запроса в массив параметров.
     *
     * @return array Массив параметров запроса
     */
    public function toParams(): array;
}
