<?php

declare(strict_types=1);

namespace App\Course\Grid;

use App\Entity\CourseBreak;
use App\Entity\Lesson;

/**
 * One day of the course grid: a teaching day of the course, or another day with a lesson (e.g. a make-up lesson on a
 * Wednesday). Cancelled lessons are kept apart: they are shown in a low second row below the day, so their time slot
 * is visibly free (umsetzung-admin.md C4, question 8; rows per day since C4f).
 */
final class GridRow
{
    /**
     * Lessons that take place (not cancelled), by column key.
     *
     * @var array<string, list<Lesson>>
     */
    public array $cells = [];

    /**
     * Cancelled lessons, by column key.
     *
     * @var array<string, list<Lesson>>
     */
    public array $cancelled = [];

    /**
     * Buttons for a double lesson in an empty cell, by column key: the lesson of the neighbouring slot on the same
     * day and on which side of it the second lesson goes (umsetzung-admin.md C4, question 8, point 10).
     *
     * @var array<string, list<array{lesson: Lesson, position: 'before'|'after'}>>
     */
    public array $doubleOffers = [];

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
     * ISO weekday, 1 = Monday, as GridColumn::$weekday.
     */
    public function weekday(): int
    {
        return (int) $this->day->format('N');
    }

    public function add(string $columnKey, Lesson $lesson): void
    {
        if ($lesson->isCancelled()) {
            $this->cancelled[$columnKey][] = $lesson;
        } else {
            $this->cells[$columnKey][] = $lesson;
        }
    }

    public function hasCancelled(): bool
    {
        return [] !== $this->cancelled;
    }
}
