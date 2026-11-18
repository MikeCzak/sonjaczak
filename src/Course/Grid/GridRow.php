<?php

declare(strict_types=1);

namespace App\Course\Grid;

use App\Entity\CourseBreak;

/**
 * One day of the course grid: a teaching day of the course, or another day with a lesson or trial lesson (e.g. a
 * make-up lesson on a Wednesday). Since C4g a time axis with boxes (umsetzung-admin.md C4g). Cancelled lessons are
 * kept apart, in a low red row below the day, so their slot is visibly free.
 */
final class GridRow
{
    /**
     * What takes place, and free slots, ordered by time.
     *
     * @var list<GridItem>
     */
    public array $items = [];

    /**
     * Cancelled lessons, ordered by time.
     *
     * @var list<GridItem>
     */
    public array $cancelled = [];

    /**
     * @param list<CourseBreak> $breaks breaks that contain this day
     */
    public function __construct(
        public readonly \DateTimeImmutable $day,
        public readonly array $breaks,
        public readonly bool $today,
    ) {
    }

    /**
     * ISO weekday, 1 = Monday.
     */
    public function weekday(): int
    {
        return (int) $this->day->format('N');
    }

    public function add(GridItem $item): void
    {
        if (GridItem::LESSON === $item->kind && !$item->takesPlace()) {
            $this->cancelled[] = $item;
        } else {
            $this->items[] = $item;
        }
    }

    public function hasCancelled(): bool
    {
        return [] !== $this->cancelled;
    }

    /**
     * Whether something that takes place overlaps this time (a free slot is only shown if not).
     */
    public function isTaken(int $startMinute, int $endMinute): bool
    {
        foreach ($this->items as $item) {
            if ($item->takesPlace() && $item->overlapsTime($startMinute, $endMinute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Number of lanes the day needs (at least one), for its height.
     */
    public function lanes(): int
    {
        return self::laneCount($this->items);
    }

    public function cancelledLanes(): int
    {
        return self::laneCount($this->cancelled);
    }

    /**
     * Once all items are in: order by time, stack overlapping ones, mark overlaps, offer double lessons.
     */
    public function finish(): void
    {
        $byTime = static fn (GridItem $a, GridItem $b): int => [$a->startMinute, $a->endMinute] <=> [$b->startMinute, $b->endMinute];
        usort($this->items, $byTime);
        usort($this->cancelled, $byTime);
        self::stack($this->items);
        self::stack($this->cancelled);

        foreach ($this->items as $i => $item) {
            foreach (\array_slice($this->items, $i + 1) as $other) {
                if ($item->takesPlace() && $other->takesPlace() && $item->overlapsTime($other->startMinute, $other->endMinute)) {
                    $item->overlaps[] = $other;
                    $other->overlaps[] = $item;
                }
            }
        }

        foreach ($this->items as $free) {
            if (GridItem::FREE === $free->kind) {
                $this->offerDoubles($free);
            }
        }
    }

    /**
     * A free slot offers a double lesson to the neighbours on the same day whose second lesson would fall into it:
     * the one before continues after its lesson, the one after starts earlier. Whether it really fits is checked by
     * the server when the button is used (LessonController::double()).
     */
    private function offerDoubles(GridItem $free): void
    {
        $before = null;
        $after = null;
        foreach ($this->items as $item) {
            if (GridItem::LESSON !== $item->kind || null === $item->lesson || !$item->takesPlace()) {
                continue;
            }
            $minutes = $item->lesson->getEnrollment()->getLessonMinutes();
            if ($item->endMinute <= $free->startMinute && $free->overlapsTime($item->endMinute, $item->endMinute + $minutes)
                && (null === $before || $item->endMinute > $before->endMinute)) {
                $before = $item;
            }
            if ($item->startMinute >= $free->endMinute && $free->overlapsTime($item->startMinute - $minutes, $item->startMinute)
                && (null === $after || $item->startMinute < $after->startMinute)) {
                $after = $item;
            }
        }
        if (null !== $before?->lesson) {
            $free->doubleOffers[] = ['lesson' => $before->lesson, 'position' => 'after'];
        }
        if (null !== $after?->lesson) {
            $free->doubleOffers[] = ['lesson' => $after->lesson, 'position' => 'before'];
        }
    }

    /**
     * Each item into the first lane that is free at its start (items ordered by start). Back to back fits.
     *
     * @param list<GridItem> $items
     */
    private static function stack(array $items): void
    {
        $laneEnds = [];
        foreach ($items as $item) {
            $lane = 0;
            while (isset($laneEnds[$lane]) && $laneEnds[$lane] > $item->startMinute) {
                ++$lane;
            }
            $item->lane = $lane;
            $laneEnds[$lane] = $item->endMinute;
        }
    }

    /**
     * @param list<GridItem> $items
     */
    private static function laneCount(array $items): int
    {
        return 1 + max([0, ...array_map(static fn (GridItem $item): int => $item->lane, $items)]);
    }
}
