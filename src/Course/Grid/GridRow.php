<?php

declare(strict_types=1);

namespace App\Course\Grid;

use App\Entity\CourseBreak;
use App\Entity\Lesson;

/**
 * One calendar week of the course grid. Cancelled lessons are kept apart: they are shown in a low second row below
 * the week, so their time slot is visibly free (umsetzung-admin.md C4, question 8).
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
     * @param list<CourseBreak> $breaks breaks that touch this week
     */
    public function __construct(
        public readonly \DateTimeImmutable $weekStart,
        public readonly array $breaks,
        public readonly bool $current,
    ) {
    }

    public function weekEnd(): \DateTimeImmutable
    {
        return $this->weekStart->modify('+6 days');
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
