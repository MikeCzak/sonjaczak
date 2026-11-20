<?php

declare(strict_types=1);

namespace App\Course\Grid;

/**
 * The course grid as built by CourseGrid: teaching days grouped by calendar week (umsetzung-admin.md C4f), each with
 * boxes on one time axis for the whole course (C4g).
 */
final readonly class Grid
{
    /**
     * @param list<GridWeek> $weeks
     */
    public function __construct(
        public GridAxis $axis,
        public array $weeks,
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->weeks;
    }

    /**
     * The row of a day, null if the grid has none for it. After a lesson was moved within its day, the grid page
     * replaces just this row (umsetzung-admin.md C4h).
     */
    public function rowOn(\DateTimeInterface $day): ?GridRow
    {
        $key = $day->format('Y-m-d');
        foreach ($this->weeks as $week) {
            foreach ($week->rows as $row) {
                if ($row->day->format('Y-m-d') === $key) {
                    return $row;
                }
            }
        }

        return null;
    }
}
