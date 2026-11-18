<?php

declare(strict_types=1);

namespace App\Course\Grid;

use App\Entity\CourseBreak;

/**
 * One calendar week of the course grid: its teaching days, one row each (umsetzung-admin.md C4f). The first column
 * spans all rows of the week (rowspan).
 */
final readonly class GridWeek
{
    /**
     * @param list<GridRow> $rows ordered by day, at least one
     */
    public function __construct(
        public \DateTimeImmutable $weekStart,
        public array $rows,
        public bool $current,
    ) {
    }

    public function weekEnd(): \DateTimeImmutable
    {
        return $this->weekStart->modify('+6 days');
    }

    /**
     * True if every teaching day of the week is a break day: the week is marked as a whole and the breaks are named
     * once, in the week cell, instead of on each day.
     */
    public function isWholeBreak(): bool
    {
        foreach ($this->rows as $row) {
            if ([] === $row->breaks) {
                return false;
            }
        }

        return true;
    }

    /**
     * The breaks on the week's teaching days, each once.
     *
     * @return list<CourseBreak>
     */
    public function breaks(): array
    {
        $breaks = [];
        foreach ($this->rows as $row) {
            foreach ($row->breaks as $break) {
                $breaks[spl_object_id($break)] = $break;
            }
        }

        return array_values($breaks);
    }

    /**
     * Table rows the week cell spans: each day plus the low row of cancelled lessons below it, if there is one.
     */
    public function rowspan(): int
    {
        $rowspan = 0;
        foreach ($this->rows as $row) {
            $rowspan += $row->hasCancelled() ? 2 : 1;
        }

        return $rowspan;
    }
}
