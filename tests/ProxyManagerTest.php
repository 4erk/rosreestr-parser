<?php

namespace Rosreestr\Parser\Tests;

use PHPUnit\Framework\TestCase;
use Rosreestr\Parser\Proxy\ProxyManager;

final class ProxyManagerTest extends TestCase
{
    public function testEmptyManagerReturnsNull(): void
    {
        $manager = new ProxyManager();

        self::assertNull($manager->getProxy());
        $manager->next();
        self::assertSame(0, $manager->counter);
    }

    public function testManagerRotatesProxies(): void
    {
        $manager = new ProxyManager(['http://proxy-one:3128', 'http://proxy-two:3128']);

        self::assertSame('http://proxy-one:3128', $manager->getProxy());
        $manager->next();
        self::assertSame('http://proxy-two:3128', $manager->getProxy());
        $manager->next();
        self::assertSame('http://proxy-one:3128', $manager->getProxy());
    }

    public function testFromStringIgnoresEmptyValue(): void
    {
        self::assertNull(ProxyManager::fromString(null));
        self::assertNull(ProxyManager::fromString('   '));
    }

    public function testFromStringCreatesSingleProxyManager(): void
    {
        $manager = ProxyManager::fromString(' http://user:pass@proxy:18890 ');

        self::assertNotNull($manager);
        self::assertSame('http://user:pass@proxy:18890', $manager->getProxy());
    }
}
