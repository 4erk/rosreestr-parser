<?php

namespace Rosreestr\Parser\Response;

class Encumbrance
{
    public ?int $startDate;            // Дата начала обременения
    public ?string $encumbranceNumber; // Номер обременения
    public ?int $encumbranceDate;      // Дата обременения
    public ?string $type;              // Тип обременения
    public ?string $typeDesc;          // Описание типа обременения
    public ?string $rightNum;          // Номер права
}
