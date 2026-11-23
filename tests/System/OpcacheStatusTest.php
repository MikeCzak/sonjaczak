<?php

declare(strict_types=1);

namespace App\Tests\System;

use App\System\OpcacheStatus;
use PHPUnit\Framework\TestCase;

final class OpcacheStatusTest extends TestCase
{
    public function testReadsTheValuesFromStatusAndConfiguration(): void
    {
        // Shape as returned by PHP 8.5 in the dev container (2026-11-23), plus preload statistics
        $status = [
            'opcache_enabled' => true,
            'cache_full' => false,
            // used_memory as reported in the FrankenPHP worker (minus free_memory), must not be used
            'memory_usage' => ['used_memory' => -201325568, 'free_memory' => 201325568, 'wasted_memory' => 1024, 'current_wasted_percentage' => 0.0],
            'interned_strings_usage' => ['buffer_size' => 33554432, 'used_memory' => 8792328, 'free_memory' => 24762104, 'number_of_strings' => 8351],
            'opcache_statistics' => ['num_cached_scripts' => 1200, 'max_cached_keys' => 32531, 'hits' => 990, 'misses' => 10, 'opcache_hit_rate' => 99.0, 'oom_restarts' => 0, 'hash_restarts' => 0, 'start_time' => 1795455190],
            'preload_statistics' => ['memory_consumption' => 5242880],
        ];
        $opcache = OpcacheStatus::fromStatus($status, 268435456);

        self::assertNotNull($opcache);
        self::assertSame(268435456, $opcache->memoryConsumption);
        // 256 MB - free - wasted
        self::assertSame(67108864, $opcache->usedMemory);
        self::assertEqualsWithDelta(25.0, $opcache->usedPercent(), 0.001);
        self::assertSame(1200, $opcache->cachedScripts);
        self::assertSame(99.0, $opcache->hitRate);
        self::assertSame(5242880, $opcache->preloadMemory);
        self::assertSame(1795455190, $opcache->startedAt->getTimestamp());
    }

    public function testDisabledOpcacheGivesNull(): void
    {
        self::assertNull(OpcacheStatus::fromStatus(['opcache_enabled' => false], 268435456));
    }

    public function testMissingPreloadStatisticsStayNull(): void
    {
        $opcache = OpcacheStatus::fromStatus(['opcache_enabled' => true], 0);

        self::assertNotNull($opcache);
        self::assertNull($opcache->preloadMemory);
        self::assertSame(0.0, $opcache->usedPercent());
    }

    public function testHitRateNotANumberBecomesZero(): void
    {
        $opcache = OpcacheStatus::fromStatus(['opcache_enabled' => true, 'opcache_statistics' => ['opcache_hit_rate' => \NAN]], 268435456);

        self::assertNotNull($opcache);
        self::assertSame(0.0, $opcache->hitRate);
    }
}
