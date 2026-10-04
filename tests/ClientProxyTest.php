<?php

namespace Rosreestr\Parser\Tests;

use PHPUnit\Framework\TestCase;
use Rosreestr\Parser\Client;

final class ClientProxyTest extends TestCase
{
    public function testEnvironmentProxyIsOptional(): void
    {
        putenv('ROSREESTR_PROXY');
        $cookie = tempnam(sys_get_temp_dir(), 'rr-cookie-');

        try {
            $client = Client::fromEnvironment($cookie);
            self::assertNull($client->manager);
        } finally {
            @unlink($cookie);
        }
    }

    public function testEnvironmentProxyCreatesManager(): void
    {
        putenv('ROSREESTR_PROXY=http://user:pass@proxy.example:18890');
        $cookie = tempnam(sys_get_temp_dir(), 'rr-cookie-');

        try {
            $client = Client::fromEnvironment($cookie);
            self::assertNotNull($client->manager);
            self::assertSame(
                'http://user:pass@proxy.example:18890',
                $client->manager->getProxy(),
            );
        } finally {
            putenv('ROSREESTR_PROXY');
            @unlink($cookie);
        }
    }
}
