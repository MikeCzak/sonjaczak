<?php

declare(strict_types=1);

namespace App\Course\Grid;

/**
 * The time axis of the course grid (umsetzung-admin.md C4g): the same for every row, so a time is at the same place in
 * each of them. From the earliest start to the latest end of everything in the grid, widened to full hours. Times are
 * minutes after midnight.
 */
final readonly class GridAxis
{
    public function __construct(
        public int $startMinute,
        public int $endMinute,
    ) {
    }

    /**
     * Earliest start rounded down, latest end rounded up to a full hour; at least one hour.
     *
     * @param list<GridItem> $items
     */
    public static function around(array $items): self
    {
        if ([] === $items) {
            return new self(0, 60);
        }
        $start = min(array_map(static fn (GridItem $item): int => $item->startMinute, $items));
        $end = max(array_map(static fn (GridItem $item): int => $item->endMinute, $items));
        $start = intdiv($start, 60) * 60;
        $end = max($start + 60, (int) ceil($end / 60) * 60);

        return new self($start, $end);
    }

    public function length(): int
    {
        return $this->endMinute - $this->startMinute;
    }

    /**
     * The full hours on the axis, both ends included, as minutes after midnight.
     *
     * @return list<int>
     */
    public function hours(): array
    {
        return range($this->startMinute, $this->endMinute, 60);
    }
}
