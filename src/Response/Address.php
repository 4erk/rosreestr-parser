<?php

namespace Rosreestr\Parser\Response;

class Address
{
    public ?string $addition;          // Дополнительная информация
    public ?string $address;           // Полный адрес
    public ?string $apartment;         // Номер квартиры
    public ?string $apartmentType;     // Тип помещения (квартира, офис и т.д.)
    public ?string $building;          // Номер корпуса
    public ?string $buildingType;      // Тип корпуса
    public ?string $city;              // Город
    public ?string $cityType;          // Тип населенного пункта (город, поселок и т.д.)
    public ?string $district;          // Район
    public ?string $fiasGuid;          // Уникальный идентификатор ФИАС
    public ?string $house;             // Номер дома
    public ?string $houseType;         // Тип дома
    public ?string $liter;             // Литера дома
    public ?string $locality;          // Населенный пункт
    public ?string $localityType;      // Тип населенного пункта
    public ?string $readableAddress;   // Читаемый адрес
    public ?string $region;            // Регион
    public ?string $sovietVillage;     // Сельсовет
    public ?string $sovietVillageType; // Тип сельсовета
    public ?string $street;            // Улица
    public ?string $streetType;        // Тип улицы
    public ?string $structure;         // Строение
    public ?string $structureType;     // Тип строения
    public ?string $urbanDistrict;     // Внутригородской район
    public ?string $urbanDistrictType; // Тип внутригородского района
}
