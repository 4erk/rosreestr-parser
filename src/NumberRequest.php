<?php

namespace Rosreestr\Parser;

/**
 * Класс NumberRequest представляет запрос к API Росреестра по кадастровому номеру.
 */
class NumberRequest implements RequestInterface
{
    /**
     * Константы для типов объектов недвижимости
     */
    const OBJECT_TYPE_LAND_PLOT = '002001001000'; // Земельный участок
    const OBJECT_TYPE_BUILDING = '002001002000';  // Здание
    const OBJECT_TYPE_ROOM = '002001003000';      // Помещение

    /**
     * Конструктор класса NumberRequest.
     *
     * @param string[] $numbers     Массив кадастровых номеров
     * @param string[] $objectTypes Массив типов объектов
     * @param string   $captcha     Значение капчи
     */
    public function __construct(
        private array $numbers = [],
        private array $objectTypes = [],
        private string $captcha = ''
    )
    {
    }

    /**
     * Добавляет кадастровый номер в запрос.
     *
     * @param string $number Кадастровый номер
     */
    public function addNumber(string $number): void
    {
        $this->numbers[] = $number;
    }

    /**
     * Добавляет тип объекта в запрос.
     *
     * @param string $objectType Тип объекта
     */
    public function addObjectType(string $objectType): void
    {
        $this->objectTypes[] = $objectType;
    }

    /**
     * Устанавливает значение капчи для запроса.
     *
     * @param string $captcha Значение капчи
     */
    public function setCaptcha(string $captcha): void
    {
        $this->captcha = $captcha;
    }

    /**
     * Возвращает текущее значение капчи.
     *
     * @return string Значение капчи
     */
    public function getCaptcha(): string
    {
        return $this->captcha;
    }

    /**
     * Преобразует текущий объект запроса в массив параметров.
     *
     * @return array Массив параметров запроса
     */
    public function toParams(): array
    {
        return [
            'cadNumbers' => $this->numbers,
            'captcha' => $this->captcha,
            'filterType' => RequestInterface::FILTER_BY_NUMBER,
            'objTypes' => $this->objectTypes,
        ];
    }
}
