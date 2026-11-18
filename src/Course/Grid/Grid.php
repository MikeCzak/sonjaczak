<?php

declare(strict_types=1);

namespace App\Course\Grid;

/**
 * The course grid as built by CourseGrid: columns are weekly time slots, rows are teaching days grouped by calendar
 * week (umsetzung-admin.md C4f).
 */
final readonly class Grid
{
    /**
     * @param list<GridColumn> $columns
     * @param list<GridWeek>   $weeks
     */
    public function __construct(
        public array $columns,
        public array $weeks,
    ) {
    }
}
