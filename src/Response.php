<?php

namespace Rosreestr\Parser;

use Rosreestr\Parser\Response\MainCharacters;
use Rosreestr\Parser\Response\ObjectItem;
use Rosreestr\Parser\Response\OldNumber;
use Rosreestr\Parser\Response\Right;
use Yiisoft\Hydrator\Hydrator;

/**
 * Класс Response представляет ответ от парсера Росреестра.
 * Он содержит коллекцию объектов недвижимости (ObjectItem).
 */
class Response
{
    /**
     * Конструктор класса Response.
     *
     * @param ObjectItem[] $items Массив объектов недвижимости
     */
    public function __construct(
        private array $items = []
    )
    {
    }

    /**
     * Возвращает все объекты недвижимости.
     *
     * @return ObjectItem[] Массив объектов недвижимости
     */
    public function getItems(): array
    {
         return $this->items;
    }

    /**
     * Добавляет новый объект недвижимости в коллекцию.
     *
     * @param ObjectItem $item Объект недвижимости для добавления
     */
    public function addItem(ObjectItem $item): void {
        $this->items[] = $item;
    }

    /**
     * Создает экземпляр Response из массива данных.
     *
     * @param array $data Массив данных для создания объектов недвижимости
     * @return self Новый экземпляр класса Response
     */
    public static function createFromArray(array $data): self
    {
        $response = new self();
        foreach ($data as $item) {
            $response->addItem(self::createItem($item));
        }
        return $response;
    }

    /**
     * Создает объект недвижимости из массива данных.
     *
     * @param mixed $item Данные для создания объекта недвижимости
     * @return ObjectItem Созданный объект недвижимости
     */
    private static function createItem(mixed $item): ObjectItem
    {
        $hydrator = new Hydrator();
        $item = $hydrator->create(ObjectItem::class, $item);

        $propertiesToHydrate = [
            'mainCharacters' => MainCharacters::class,
            'oldNumbers' => OldNumber::class,
            'rights' => Right::class,
        ];

        foreach ($propertiesToHydrate as $property => $class) {
            if ($item->$property) {
                $item->$property = array_map(
                    static fn($data) => $hydrator->create($class, $data),
                    $item->$property
                );
            }
        }

        return $item;
    }
}
