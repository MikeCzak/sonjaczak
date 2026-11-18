<?php

declare(strict_types=1);

namespace App\Tests\Course;

use App\Course\Grid\CourseGrid;
use App\Course\Grid\GridRow;
use App\Course\Grid\GridWeek;
use App\Entity\Course;
use App\Entity\CourseBreak;
use App\Entity\Enrollment;
use App\Entity\Enum\CancelledBy;
use App\Entity\Lesson;
use App\Entity\Student;
use PHPUnit\Framework\TestCase;

/**
 * Weeks, days, columns and which column a lesson goes to (umsetzung-admin.md C4b, question 8; days since C4f).
 * Course: Monday 2026-10-05 to Sunday 2026-10-25, three weeks. A on Friday 15:00, B on Friday 15:50.
 */
final class CourseGridTest extends TestCase
{
    private Course $course;
    private Enrollment $a;
    private Enrollment $b;

    protected function setUp(): void
    {
        $this->course = new Course()
            ->setStartsOn(new \DateTimeImmutable('2026-10-05'))
            ->setEndsOn(new \DateTimeImmutable('2026-10-25'))
            ->setLessonMinutes(50);
        // Added in reverse order: the grid sorts the columns by time
        $this->b = $this->enrollment('Berta', '15:50');
        $this->a = $this->enrollment('Anna', '15:00');
    }

    public function testWeeksDaysAndSlots(): void
    {
        $grid = $this->build();

        self::assertSame(['5-15:00', '5-15:50'], array_map(static fn ($c): string => $c->key, $grid->columns));
        self::assertSame('15:50', $grid->columns[1]->startTime());
        self::assertSame(['2026-10-05', '2026-10-12', '2026-10-19'], array_map(static fn (GridWeek $w): string => $w->weekStart->format('Y-m-d'), $grid->weeks));
        // One teaching day (Friday): one row per week
        self::assertSame([['2026-10-09'], ['2026-10-16'], ['2026-10-23']], array_map($this->days(...), $grid->weeks));
        self::assertTrue($grid->weeks[1]->current, 'now (2026-10-16) is in the second week');
        self::assertTrue($grid->weeks[1]->rows[0]->today);
        self::assertFalse($grid->weeks[0]->rows[0]->today);
        self::assertCount(1, $grid->weeks[0]->rows[0]->cells['5-15:00']);
        self::assertSame(1, $grid->weeks[0]->rowspan());
    }

    public function testCancelledLessonGoesToTheLowRow(): void
    {
        $this->lesson($this->a, '2026-10-16 15:00')->setCancelledBy(CancelledBy::Teacher);

        $grid = $this->build();
        $row = $grid->weeks[1]->rows[0];

        self::assertArrayNotHasKey('5-15:00', $row->cells);
        self::assertCount(1, $row->cancelled['5-15:00']);
        self::assertTrue($row->hasCancelled());
        self::assertSame(2, $grid->weeks[1]->rowspan(), 'the week cell also spans the low row');
        self::assertFalse($grid->weeks[0]->rows[0]->hasCancelled());
    }

    public function testDoubleLessonInTheFreedSlotOfTheNeighbour(): void
    {
        $this->lesson($this->a, '2026-10-16 15:00')->setCancelledBy(CancelledBy::Student);
        // Second lesson of B directly before B's own, in A's slot
        $this->lesson($this->b, '2026-10-16 15:00');

        $cell = $this->build()->weeks[1]->rows[0]->cells['5-15:00'];

        self::assertCount(1, $cell);
        self::assertSame($this->b, $cell[0]->getEnrollment());
    }

    public function testEmptyCellOffersADoubleLessonToItsNeighbours(): void
    {
        $this->lesson($this->a, '2026-10-16 15:00')->setCancelledBy(CancelledBy::Student);
        $berta = $this->lesson($this->b, '2026-10-16 15:50');

        $grid = $this->build();

        self::assertSame([['lesson' => $berta, 'position' => 'before']], $grid->weeks[1]->rows[0]->doubleOffers['5-15:00']);
        self::assertSame([], $grid->weeks[0]->rows[0]->doubleOffers, 'no empty cell in the first week');
    }

    /**
     * A make-up lesson on a Wednesday gets a row for that day, in its own enrollment's column. The Friday slots in
     * that row offer no double lesson: nobody has a slot on Wednesday.
     */
    public function testMakeUpOnAnotherDayGetsItsOwnRow(): void
    {
        $makeUp = new Lesson($this->b, new \DateTimeImmutable('2026-10-21 17:00'));

        $week = $this->build()->weeks[2];

        self::assertSame(['2026-10-21', '2026-10-23'], $this->days($week));
        self::assertSame([$makeUp], $week->rows[0]->cells['5-15:50']);
        self::assertSame([], $week->rows[0]->doubleOffers);
        self::assertSame(2, $week->rowspan());
    }

    public function testRowsWidenForLessonsAfterTheEnd(): void
    {
        new Lesson($this->a, new \DateTimeImmutable('2026-11-02 10:00'));

        $weeks = $this->build()->weeks;

        self::assertCount(5, $weeks);
        self::assertSame(['2026-10-30'], $this->days($weeks[3]));
        self::assertSame([], $weeks[3]->rows[0]->cells);
        // The last day is the lesson's: no Friday row after it
        self::assertSame(['2026-11-02'], $this->days($weeks[4]));
    }

    /**
     * A break on every teaching day of a week marks the week as a whole and is named there; otherwise it belongs to
     * the day it falls on. Breaks on days without a row (here Monday) do not show (C4f).
     */
    public function testBreaksMarkTheirDaysOrTheWholeWeek(): void
    {
        $this->course->addBreak(new CourseBreak('Schulautonom', new \DateTimeImmutable('2026-10-16')));
        $this->course->addBreak(new CourseBreak('Feiertag', new \DateTimeImmutable('2026-10-21')));
        $this->course->addBreak(new CourseBreak('Montag frei', new \DateTimeImmutable('2026-10-05')));
        new Lesson($this->b, new \DateTimeImmutable('2026-10-21 17:00'));

        $weeks = $this->build()->weeks;

        self::assertFalse($weeks[0]->isWholeBreak());
        self::assertSame([], $weeks[0]->breaks());
        self::assertTrue($weeks[1]->isWholeBreak());
        self::assertSame('Schulautonom', $weeks[1]->breaks()[0]->getLabel());
        // Wednesday (make-up lesson) is a holiday, Friday is not
        self::assertFalse($weeks[2]->isWholeBreak());
        self::assertSame('Feiertag', $weeks[2]->rows[0]->breaks[0]->getLabel());
        self::assertSame([], $weeks[2]->rows[1]->breaks);
    }

    /**
     * @return list<string>
     */
    private function days(GridWeek $week): array
    {
        return array_map(static fn (GridRow $r): string => $r->day->format('Y-m-d'), $week->rows);
    }

    private function build(): \App\Course\Grid\Grid
    {
        return new CourseGrid()->build($this->course, [$this->b, $this->a], new \DateTimeImmutable('2026-10-16 12:00'));
    }

    /**
     * Enrollment on Friday with one lesson per week of the course.
     */
    private function enrollment(string $name, string $time): Enrollment
    {
        $enrollment = new Enrollment($this->course, new Student()->setFirstName($name)->setLastName('Test'))
            ->setStartTime(new \DateTimeImmutable($time));
        foreach (['2026-10-09', '2026-10-16', '2026-10-23'] as $day) {
            new Lesson($enrollment, new \DateTimeImmutable($day.' '.$time));
        }

        return $enrollment;
    }

    private function lesson(Enrollment $enrollment, string $startsAt): Lesson
    {
        foreach ($enrollment->getLessons() as $lesson) {
            if ($lesson->getStartsAt()?->format('Y-m-d H:i') === $startsAt) {
                return $lesson;
            }
        }

        return new Lesson($enrollment, new \DateTimeImmutable($startsAt));
    }
}
