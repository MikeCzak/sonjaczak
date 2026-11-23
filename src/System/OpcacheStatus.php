<?php

declare(strict_types=1);

namespace App\System;

/**
 * Snapshot of the OPcache of the running web server (docs/planung/wartung.md W4).
 *
 * Built from opcache_get_status(false) and the ini value opcache.memory_consumption. Only meaningful inside
 * FrankenPHP: the CLI (bin/console, PHPUnit) has its own process and OPcache is off there by default
 * (opcache.enable_cli = 0). Memory values in bytes.
 *
 * Measured 2026-11-23 in the FrankenPHP worker (PHP 8.5.12, ZTS): opcache_get_configuration() reports
 * opcache.memory_consumption as 0 there, and memory_usage.used_memory comes out as minus free_memory. free_memory,
 * wasted_memory and the interned strings are plausible, and ini_get() returns the configured size. So the size comes
 * from the ini value and "used" is computed as size - free - wasted (on the CLI this equals PHP's own used_memory).
 */
final readonly class OpcacheStatus
{
    public function __construct(
        public int $memoryConsumption,
        public int $usedMemory,
        public int $freeMemory,
        public int $wastedMemory,
        public int $internedBufferSize,
        public int $internedUsedMemory,
        public int $numberOfStrings,
        public int $cachedScripts,
        public int $maxCachedKeys,
        public int $hits,
        public int $misses,
        public float $hitRate,
        public int $oomRestarts,
        public int $hashRestarts,
        public bool $cacheFull,
        public ?int $preloadMemory,
        public \DateTimeImmutable $startedAt,
    ) {
    }

    /**
     * Reads the OPcache of the current process; null if OPcache is not enabled here.
     */
    public static function read(): ?self
    {
        if (!\function_exists('opcache_get_status')) {
            return null;
        }

        $status = opcache_get_status(false);
        if (!\is_array($status)) {
            return null;
        }

        // Megabytes, see the class comment for why not opcache_get_configuration()
        $memoryConsumption = (int) \ini_get('opcache.memory_consumption') * 1024 * 1024;

        return self::fromStatus($status, $memoryConsumption);
    }

    /**
     * @param array<mixed> $status            result of opcache_get_status(false)
     * @param int          $memoryConsumption configured size of the shared memory in bytes
     */
    public static function fromStatus(array $status, int $memoryConsumption): ?self
    {
        if (true !== ($status['opcache_enabled'] ?? false)) {
            return null;
        }

        $memory = self::section($status, 'memory_usage');
        $interned = self::section($status, 'interned_strings_usage');
        $statistics = self::section($status, 'opcache_statistics');
        $preload = \is_array($status['preload_statistics'] ?? null) ? $status['preload_statistics'] : null;

        $hitRate = $statistics['opcache_hit_rate'] ?? 0;
        $freeMemory = self::int($memory, 'free_memory');
        $wastedMemory = self::int($memory, 'wasted_memory');

        return new self(
            memoryConsumption: $memoryConsumption,
            usedMemory: max(0, $memoryConsumption - $freeMemory - $wastedMemory),
            freeMemory: $freeMemory,
            wastedMemory: $wastedMemory,
            internedBufferSize: self::int($interned, 'buffer_size'),
            internedUsedMemory: self::int($interned, 'used_memory'),
            numberOfStrings: self::int($interned, 'number_of_strings'),
            cachedScripts: self::int($statistics, 'num_cached_scripts'),
            maxCachedKeys: self::int($statistics, 'max_cached_keys'),
            hits: self::int($statistics, 'hits'),
            misses: self::int($statistics, 'misses'),
            hitRate: (\is_int($hitRate) || \is_float($hitRate)) && is_finite((float) $hitRate) ? (float) $hitRate : 0.0,
            oomRestarts: self::int($statistics, 'oom_restarts'),
            hashRestarts: self::int($statistics, 'hash_restarts'),
            cacheFull: true === ($status['cache_full'] ?? false),
            preloadMemory: null !== $preload ? self::int($preload, 'memory_consumption') : null,
            startedAt: new \DateTimeImmutable('@'.self::int($statistics, 'start_time')),
        );
    }

    /**
     * Share of the shared memory in use, 0–100.
     */
    public function usedPercent(): float
    {
        return $this->memoryConsumption > 0 ? 100 * $this->usedMemory / $this->memoryConsumption : 0.0;
    }

    /**
     * @param array<mixed> $array
     *
     * @return array<mixed>
     */
    private static function section(array $array, string $key): array
    {
        $value = $array[$key] ?? [];

        return \is_array($value) ? $value : [];
    }

    /**
     * @param array<mixed> $array
     */
    private static function int(array $array, string $key): int
    {
        $value = $array[$key] ?? 0;

        return \is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }
}
