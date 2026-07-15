<?php

namespace Rosreestr\Parser\Tests;

use PHPUnit\Framework\TestCase;
use Rosreestr\Parser\Response;
use Rosreestr\Parser\Response\Address;
use Rosreestr\Parser\Response\Encumbrance;
use Rosreestr\Parser\Response\MainCharacters;
use Rosreestr\Parser\Response\OldNumber;
use Rosreestr\Parser\Response\Right;

final class ResponseTest extends TestCase
{
    public function testOmittedRelatedSectionsRemainNull(): void
    {
        $item = Response::createFromArray([[]])->getItems()[0];

        self::assertNull($item->address);
        self::assertNull($item->mainCharacters);
        self::assertNull($item->oldNumbers);
        self::assertNull($item->rights);
        self::assertNull($item->encumbrances);
    }

    public function testExplicitEmptyCollectionsRemainEmpty(): void
    {
        $item = Response::createFromArray([[
            'mainCharacters' => [],
            'oldNumbers' => [],
            'rights' => [],
            'encumbrances' => [],
        ]])->getItems()[0];

        self::assertSame([], $item->mainCharacters);
        self::assertSame([], $item->oldNumbers);
        self::assertSame([], $item->rights);
        self::assertSame([], $item->encumbrances);
    }

    public function testExplicitNullRelatedSectionsRemainNull(): void
    {
        $item = Response::createFromArray([[
            'address' => null,
            'mainCharacters' => null,
            'oldNumbers' => null,
            'rights' => null,
            'encumbrances' => null,
        ]])->getItems()[0];

        self::assertNull($item->address);
        self::assertNull($item->mainCharacters);
        self::assertNull($item->oldNumbers);
        self::assertNull($item->rights);
        self::assertNull($item->encumbrances);
    }

    public function testNonEmptyRelatedSectionsAreHydrated(): void
    {
        $item = Response::createFromArray([[
            'address' => ['readableAddress' => 'Test address'],
            'mainCharacters' => [['code' => 'area', 'value' => '42']],
            'oldNumbers' => [['numType' => 'inventory', 'numValue' => 'old-42']],
            'rights' => [['rightNumber' => 'right-42']],
            'encumbrances' => [['encumbranceNumber' => 'encumbrance-42']],
        ]])->getItems()[0];

        self::assertInstanceOf(Address::class, $item->address);
        self::assertSame('Test address', $item->address->readableAddress);
        self::assertContainsOnlyInstancesOf(MainCharacters::class, $item->mainCharacters);
        self::assertContainsOnlyInstancesOf(OldNumber::class, $item->oldNumbers);
        self::assertContainsOnlyInstancesOf(Right::class, $item->rights);
        self::assertContainsOnlyInstancesOf(Encumbrance::class, $item->encumbrances);
    }
}
