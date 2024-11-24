<?php

namespace Rosreestr\Parser\Response;

class Right
{
    public ?string $ownershipType;     // Тип собственности
    public ?string $part;              // Доля в праве собственности
    public ?string $rightNumber;       // Номер права
    public ?int $rightRegDate;         // Дата регистрации права
    public ?string $rightType;         // Тип права
    public ?string $rightTypeDesc;     // Описание типа права
    public ?bool $sharedOwnershipType; // Признак общей собственности
}
