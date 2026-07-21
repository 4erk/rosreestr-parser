<?php

namespace Rosreestr\Parser\Response;

class ObjectItem
{
    public ?Address $address = null;     // Адрес объекта
    /** @var MainCharacters[]|null */
    public ?array $mainCharacters = null; // Основные характеристики объекта
    /** @var OldNumber[]|null */
    public ?array $oldNumbers = null;     // Старые номера объекта
    /** @var Right[]|null */
    public ?array $rights = null;         // Права на объект

    public ?string $area;                  // Площадь объекта
    public ?string $cadCost;               // Кадастровая стоимость
    public ?int $cadCostDate;              // Дата определения кадастровой стоимости
    public ?int $cadCostDeterminationDate; // Дата утверждения кадастровой стоимости
    public ?int $cadCostRegistrationDate;  // Дата внесения сведений о кадастровой стоимости в ЕГРН
    public ?string $cadEngCertNumber;      // Номер сертификата кадастрового инженера
    public ?string $cadEngFIO;             // ФИО кадастрового инженера
    public ?string $cadEngPhone;           // Телефон кадастрового инженера
    public ?string $cadNumber;             // Кадастровый номер объекта
    public ?string $cadQuarter;            // Кадастровый квартал
    public ?int $cancelDate;               // Дата снятия с кадастрового учета
    /** @var string[]|null  */
    public ?array $childCadNumbers;       // Кадастровые номера дочерних объектов
    public ?string $databaseName;          // Название базы данных
    /** @var Encumbrance[]|null */
    public ?array $encumbrances = null;    // Обременения на объект
    public ?string $floor;                 // Этаж
    public ?int $infoUpdateDate;           // Дата обновления информации
    public ?string $landCategory;          // Категория земель
    public ?string $levelFloor;            // Уровень этажа
    public ?string $objType;               // Тип объекта
    public ?string $objectId;              // Идентификатор объекта
    public ?string $oksCommisioningYear;   // Год ввода в эксплуатацию
    public ?string $oksWallMaterial;       // Материал стен
    public ?string $oksYearBuild;          // Год постройки
    public ?string $ownershipType;         // Тип собственности
    public ?string $parentCadNumber;       // Кадастровый номер родительского объекта
    /** @var string[]|null  */
    public ?array $permittedUse;          // Разрешенное использование
    public ?string $permittedUseByDoc;     // Разрешенное использование по документу
    public ?string $purpose;               // Назначение объекта
    public ?int $regDate;                  // Дата регистрации
    public ?string $regionKey;             // Код региона
    public ?string $status;                // Статус объекта
    public ?string $undergroundFloor;      // Подземный этаж
    public ?array $additional;            // Дополнительно
}
