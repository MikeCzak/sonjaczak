<?php

declare(strict_types=1);

namespace App\Course\Grid;

use App\Entity\Course;
use App\Entity\CourseBreak;
use App\Entity\Enrollment;
use App\Entity\Lesson;

/**
 * Builds the course grid (umsetzung-admin.md C4b): one row per calendar week, one column per weekly time slot.
 *
 * Which column a lesson goes to (question 8, points 1 and 8):
 * 1. its own enrollment's slot, if the lesson overlaps it (the normal case, also a slightly moved lesson);
 * 2. otherwise the slot on the same weekday it overlaps most, e.g. a double lesson of B in A's freed slot;
 * 3. otherwise its own enrollment's column, e.g. a make-up lesson on another day. The cell then shows date and time.
 */
final class CourseGrid
{
    /**
     * @param iterable<Enrollment> $enrollments with their lessons, ideally loaded in one query
     */
    public function build(Course $course, iterable $enrollments, \DateTimeInterface $now): Grid
    {
        $columns = $this->columns($enrollments);
        $rows = $this->rows($course, $columns, $now);

        foreach ($columns as $column) {
            foreach ($column->enrollments as $enrollment) {
                foreach ($enrollment->getLessons() as $lesson) {
                    $startsAt = $lesson->getStartsAt();
                    if (null === $startsAt) {
                        continue;
                    }
                    $week = $this->monday($startsAt)->format('Y-m-d');
                    // rows() covers every lesson, so the week always exists
                    $rows[$week]->add($this->columnFor($lesson, $column, $columns)->key, $lesson);
                }
            }
        }

        foreach ($rows as $row) {
            foreach ($row->cells as &$lessons) {
                usort($lessons, static fn (Lesson $a, Lesson $b): int => $a->getStartsAt() <=> $b->getStartsAt());
            }
            unset($lessons);
            foreach ($row->cancelled as &$lessons) {
                usort($lessons, static fn (Lesson $a, Lesson $b): int => $a->getStartsAt() <=> $b->getStartsAt());
            }
            unset($lessons);
        }

        return new Grid(array_values($columns), array_values($rows));
    }

    /**
     * One column per weekday and start time, ordered by weekday and time.
     *
     * @param iterable<Enrollment> $enrollments
     *
     * @return array<string, GridColumn>
     */
    private function columns(iterable $enrollments): array
    {
        $columns = [];
        foreach ($enrollments as $enrollment) {
            $startTime = $enrollment->getStartTime();
            if (null === $startTime) {
                continue;
            }
            $start = $this->minuteOfDay($startTime);
            $end = $start + $enrollment->getLessonMinutes();
            $key = $enrollment->getWeekday().'-'.$startTime->format('H:i');

            $columns[$key] ??= new GridColumn($key, $enrollment->getWeekday(), $start, $end);
            $columns[$key]->endMinute = max($columns[$key]->endMinute, $end);
            $columns[$key]->enrollments[] = $enrollment;
        }

        uasort($columns, static fn (GridColumn $a, GridColumn $b): int => [$a->weekday, $a->startMinute] <=> [$b->weekday, $b->startMinute]);

        return $columns;
    }

    /**
     * Calendar weeks from the course start to its end, widened if a lesson lies outside (e.g. a late make-up lesson).
     *
     * @param array<string, GridColumn> $columns
     *
     * @return array<string, GridRow> keyed by the Monday, "Y-m-d"
     */
    private function rows(Course $course, array $columns, \DateTimeInterface $now): array
    {
        $days = array_filter([$course->getStartsOn(), $course->getEndsOn()]);
        foreach ($columns as $column) {
            foreach ($column->enrollments as $enrollment) {
                foreach ($enrollment->getLessons() as $lesson) {
                    if (null !== $lesson->getStartsAt()) {
                        $days[] = $lesson->getStartsAt();
                    }
                }
            }
        }
        if ([] === $days) {
            return [];
        }

        $currentWeek = $this->monday(\DateTimeImmutable::createFromInterface($now))->format('Y-m-d');
        $last = $this->monday(max($days));
        $rows = [];
        for ($week = $this->monday(min($days)); $week <= $last; $week = $week->modify('+1 week')) {
            $key = $week->format('Y-m-d');
            $rows[$key] = new GridRow($week, $this->breaksIn($course, $week), $key === $currentWeek);
        }

        return $rows;
    }

    /**
     * @param array<string, GridColumn> $columns
     */
    private function columnFor(Lesson $lesson, GridColumn $own, array $columns): GridColumn
    {
        $startsAt = $lesson->getStartsAt() ?? throw new \LogicException('Lesson without start.');
        $weekday = (int) $startsAt->format('N');
        $start = $this->minuteOfDay($startsAt);
        $end = $start + $lesson->getDurationMinutes();

        if ($own->overlap($weekday, $start, $end) > 0) {
            return $own;
        }

        $best = $own;
        $bestOverlap = 0;
        foreach ($columns as $column) {
            $overlap = $column->overlap($weekday, $start, $end);
            if ($overlap > $bestOverlap) {
                $best = $column;
                $bestOverlap = $overlap;
            }
        }

        return $best;
    }

    /**
     * @return list<CourseBreak>
     */
    private function breaksIn(Course $course, \DateTimeImmutable $weekStart): array
    {
        $weekEnd = $weekStart->modify('+6 days');

        return array_values($course->getBreaks()->filter(
            static fn (CourseBreak $break): bool => null !== $break->getFirstDay() && null !== $break->getLastDay()
                && $break->getFirstDay() <= $weekEnd && $break->getLastDay() >= $weekStart,
        )->toArray());
    }

    private function monday(\DateTimeImmutable $day): \DateTimeImmutable
    {
        return $day->setTime(0, 0)->modify('-'.((int) $day->format('N') - 1).' days');
    }

    private function minuteOfDay(\DateTimeImmutable $time): int
    {
        return (int) $time->format('G') * 60 + (int) $time->format('i');
    }
}
