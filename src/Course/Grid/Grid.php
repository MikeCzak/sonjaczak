<?php

declare(strict_types=1);

namespace App\Course\Grid;

/**
 * The course grid as built by CourseGrid: columns are weekly time slots, rows are calendar weeks.
 */
final readonly class Grid
{
    /**
     * @param list<GridColumn> $columns
     * @param list<GridRow>    $rows
     */
    public function __construct(
        public array $columns,
        public array $rows,
    ) {
    }
}
