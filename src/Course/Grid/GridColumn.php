<?php

declare(strict_types=1);

namespace App\Course\Grid;

use App\Entity\Enrollment;

/**
 * A weekly time slot in the course grid: weekday and start time of one or more enrollments (usually one).
 * Times are minutes after midnight, so overlaps are simple arithmetic.
 */
final class GridColumn
{
    /**
     * @var list<Enrollment>
     */
    public array $enrollments = [];

    public function __construct(
        public readonly string $key,
        public readonly int $weekday,
        public readonly int $startMinute,
        public int $endMinute,
    ) {
    }

    /**
     * Minutes a lesson on this weekday overlaps the slot, 0 if not at all or on another day.
     */
    public function overlap(int $weekday, int $startMinute, int $endMinute): int
    {
        if ($weekday !== $this->weekday) {
            return 0;
        }

        return max(0, min($endMinute, $this->endMinute) - max($startMinute, $this->startMinute));
    }

    /**
     * "15:30", for the column header and to tell whether a lesson starts at the usual time.
     */
    public function startTime(): string
    {
        return \sprintf('%02d:%02d', intdiv($this->startMinute, 60), $this->startMinute % 60);
    }
}
