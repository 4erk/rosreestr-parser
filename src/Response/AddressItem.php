<?php

namespace Rosreestr\Parser\Response;

use Yiisoft\Hydrator\Hydrator;

class AddressItem
{
    public $cadnum;
    public $full_name;
    public $actual;
    public $type;

    public static function createFromArray(array $data):self {
        return (new Hydrator())->create(self::class, $data);
    }
}
