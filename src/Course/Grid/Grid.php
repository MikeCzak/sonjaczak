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
}
