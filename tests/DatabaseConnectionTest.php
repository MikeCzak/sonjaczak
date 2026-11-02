<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Smoke test: the kernel boots in the test environment and the configured database is reachable.
 */
final class DatabaseConnectionTest extends KernelTestCase
{
    public function testDatabaseIsReachable(): void
    {
        self::bootKernel();

        $connection = self::getContainer()->get(Connection::class);

        self::assertSame(1, $connection->fetchOne('SELECT 1'));
    }
}
