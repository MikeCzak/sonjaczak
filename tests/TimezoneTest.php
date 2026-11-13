<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Lesson times are stored without time zone, so PHP must run in Vienna time (frankenphp/conf.d/10-app.ini).
 */
final class TimezoneTest extends TestCase
{
    public function testPhpRunsInViennaTime(): void
    {
        self::assertSame('Europe/Vienna', date_default_timezone_get());
    }
}
