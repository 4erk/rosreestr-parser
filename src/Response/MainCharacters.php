<?php

namespace Rosreestr\Parser\Response;

class MainCharacters
{
    public ?string $code;            // Код характеристики объекта
    public ?string $description;     // Описание характеристики
    public ?string $unitCode;        // Код единицы измерения
    public ?string $unitDescription; // Описание единицы измерения
    public mixed $value;             // Значение характеристики
}
