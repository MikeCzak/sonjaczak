<?php

declare(strict_types=1);

namespace App\Tests\Course;

use App\Course\Grid\CourseGrid;
use App\Course\Grid\Grid;
use App\Course\Grid\GridItem;
use App\Course\Grid\GridRow;
use App\Course\Grid\GridWeek;
use App\Entity\Course;
use App\Entity\CourseBreak;
use App\Entity\Enrollment;
use App\Entity\Enum\CancelledBy;
use App\Entity\Lesson;
use App\Entity\Student;
use App\Entity\TrialLesson;
use PHPUnit\Framework\TestCase;

/**
 * Weeks, days and boxes on the time axis (umsetzung-admin.md C4b; days since C4f; time axis since C4g).
 * Course: Monday 2026-10-05 to Sunday 2026-10-25, three weeks. A on Friday 15:00, B on Friday 15:50, 50 minutes.
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

    public function testWeeksDaysAndAxis(): void
    {
        $grid = $this->build();

        // 15:00 to 16:40, widened to full hours
        self::assertSame([900, 1020], [$grid->axis->startMinute, $grid->axis->endMinute]);
        self::assertSame([900, 960, 1020], $grid->axis->hours());
        self::assertSame(['2026-10-05', '2026-10-12', '2026-10-19'], array_map(static fn (GridWeek $w): string => $w->weekStart->format('Y-m-d'), $grid->weeks));
        // One teaching day (Friday): one row per week
        self::assertSame([['2026-10-09'], ['2026-10-16'], ['2026-10-23']], array_map($this->days(...), $grid->weeks));
        self::assertTrue($grid->weeks[1]->current, 'now (2026-10-16) is in the second week');
        self::assertTrue($grid->weeks[1]->rows[0]->today);
        self::assertFalse($grid->weeks[0]->rows[0]->today);
        self::assertSame(1, $grid->weeks[0]->rowspan());

        $row = $grid->weeks[0]->rows[0];
        self::assertSame(['15:00', '15:50'], array_map(static fn (GridItem $i): string => $i->startTime(), $row->items));
        self::assertSame([0, 0], array_map(static fn (GridItem $i): int => $i->lane, $row->items), 'back to back is no overlap');
        self::assertSame([], $row->items[0]->overlaps);
        self::assertSame(1, $row->lanes());
    }

    public function testLessonsAreNumberedByCountingLessons(): void
    {
        $this->lesson($this->a, '2026-10-09 15:00')->setCancelledBy(CancelledBy::Teacher);
        $this->lesson($this->b, '2026-10-09 15:50')->setCancelledBy(CancelledBy::Student);

        $weeks = $this->build()->weeks;

        // Sonja's cancellation does not count: no number, Anna's next lesson is her first
        self::assertNull($weeks[0]->rows[0]->cancelled[0]->number);
        self::assertSame(1, $this->item($weeks[1]->rows[0], $this->a)->number);
        // The student's cancellation counts
        self::assertSame(1, $weeks[0]->rows[0]->cancelled[1]->number);
        self::assertSame(2, $this->item($weeks[1]->rows[0], $this->b)->number);
    }

    /**
     * Mike 2026-11-18, option A: on the enrollment's next lesson that takes place (now = 2026-10-16 12:00).
     */
    public function testMakeUpDueOnTheNextLesson(): void
    {
        $this->a->setPlannedLessons(3);
        $this->b->setPlannedLessons(3);
        $this->lesson($this->a, '2026-10-09 15:00')->setCancelledBy(CancelledBy::Teacher);

        $weeks = $this->build()->weeks;

        self::assertSame(1, $this->item($weeks[1]->rows[0], $this->a)->makeUpsDue);
        self::assertSame(0, $this->item($weeks[2]->rows[0], $this->a)->makeUpsDue);
        self::assertSame(0, $this->item($weeks[1]->rows[0], $this->b)->makeUpsDue, 'nothing due for Berta');
    }

    public function testCancelledLessonGoesToTheLowRowAndFreesTheSlot(): void
    {
        $this->lesson($this->a, '2026-10-16 15:00')->setCancelledBy(CancelledBy::Teacher);
        $berta = $this->lesson($this->b, '2026-10-16 15:50');

        $grid = $this->build();
        $row = $grid->weeks[1]->rows[0];

        self::assertCount(1, $row->cancelled);
        self::assertTrue($row->hasCancelled());
        self::assertSame(2, $grid->weeks[1]->rowspan(), 'the week cell also spans the low row');
        self::assertFalse($grid->weeks[0]->rows[0]->hasCancelled());

        // Anna's slot is free and offers Berta, who comes after it, to start earlier
        self::assertSame([GridItem::FREE, GridItem::LESSON], array_map(static fn (GridItem $i): string => $i->kind, $row->items));
        self::assertSame([900, 950], [$row->items[0]->startMinute, $row->items[0]->endMinute]);
        self::assertSame([['lesson' => $berta, 'position' => 'before']], $row->items[0]->doubleOffers);
        self::assertSame([], array_filter($grid->weeks[0]->rows[0]->items, static fn (GridItem $i): bool => GridItem::FREE === $i->kind));
    }

    public function testDoubleLessonInTheFreedSlotOfTheNeighbour(): void
    {
        $this->lesson($this->a, '2026-10-16 15:00')->setCancelledBy(CancelledBy::Student);
        // Second lesson of B directly before B's own, in A's slot: no free box there
        $this->lesson($this->b, '2026-10-16 15:00');

        $items = $this->build()->weeks[1]->rows[0]->items;

        self::assertSame([GridItem::LESSON, GridItem::LESSON], array_map(static fn (GridItem $i): string => $i->kind, $items));
        self::assertSame($this->b, $items[0]->lesson?->getEnrollment());
    }

    /**
     * A trial lesson at the same time as a lesson: both on their own lane, both marked. A trial lesson in a free
     * slot takes it: no free box, no double lesson offered there.
     */
    public function testTrialLessonsOverlapOrTakeAFreeSlot(): void
    {
        $this->lesson($this->a, '2026-10-16 15:00')->setCancelledBy(CancelledBy::Teacher);
        $inFreeSlot = $this->trial('2026-10-16 15:00');
        $overlapping = $this->trial('2026-10-23 16:00');

        $grid = $this->build([$inFreeSlot, $overlapping]);

        $free = $grid->weeks[1]->rows[0];
        self::assertSame([GridItem::TRIAL, GridItem::LESSON], array_map(static fn (GridItem $i): string => $i->kind, $free->items));
        self::assertSame([], $free->items[0]->overlaps);

        $busy = $grid->weeks[2]->rows[0];
        $berta = $this->item($busy, $this->b);
        $trial = $busy->items[2];
        self::assertSame($overlapping, $trial->trial);
        self::assertSame([$trial], $berta->overlaps);
        self::assertSame([$berta], $trial->overlaps);
        self::assertSame([0, 1], [$berta->lane, $trial->lane]);
        self::assertSame(2, $busy->lanes());
        // The trial ends 16:50: the axis ends 17:00 as before
        self::assertSame(1020, $grid->axis->endMinute);
    }

    /**
     * A make-up lesson or trial lesson on a Wednesday gets a row for that day. Nobody has a slot on Wednesday, so
     * there are no free boxes; the axis widens for the late make-up lesson.
     */
    public function testMakeUpOnAnotherDayGetsItsOwnRow(): void
    {
        $makeUp = new Lesson($this->b, new \DateTimeImmutable('2026-10-21 17:00'));
        $makeUp->setMakeUpFor($this->lesson($this->b, '2026-10-09 15:50'));

        $grid = $this->build([$this->trial('2026-10-13 10:00')]);
        $week = $grid->weeks[2];

        self::assertSame(['2026-10-21', '2026-10-23'], $this->days($week));
        self::assertSame([$makeUp], array_map(static fn (GridItem $i): ?Lesson => $i->lesson, $week->rows[0]->items));
        self::assertSame(2, $week->rowspan());
        self::assertSame(['2026-10-13', '2026-10-16'], $this->days($grid->weeks[1]));
        self::assertSame([600, 1080], [$grid->axis->startMinute, $grid->axis->endMinute]);
    }

    public function testRowsWidenForLessonsAfterTheEnd(): void
    {
        new Lesson($this->a, new \DateTimeImmutable('2026-11-02 10:00'));

        $weeks = $this->build()->weeks;

        self::assertCount(5, $weeks);
        self::assertSame(['2026-10-30'], $this->days($weeks[3]));
        // Friday 30.10. lies between Anna's regular lessons (her last is 2.11.): her slot is free; Berta had ended
        self::assertSame(['15:00'], array_map(static fn (GridItem $i): string => $i->startTime(), $weeks[3]->rows[0]->items));
        self::assertSame(GridItem::FREE, $weeks[3]->rows[0]->items[0]->kind);
        // The last day is the lesson's: no Friday row after it
        self::assertSame(['2026-11-02'], $this->days($weeks[4]));
    }

    /**
     * A break on every teaching day of a week marks the week as a whole and is named there; otherwise it belongs to
     * the day it falls on. Breaks on days without a row (here Monday) do not show (C4f). A break day has no free
     * boxes.
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
        // The lessons on the free Friday 16.10. were not planned there in reality; here they still are, but no free box
        self::assertSame([], array_filter($weeks[1]->rows[0]->items, static fn (GridItem $i): bool => GridItem::FREE === $i->kind));
    }

    /**
     * @return list<string>
     */
    private function days(GridWeek $week): array
    {
        return array_map(static fn (GridRow $r): string => $r->day->format('Y-m-d'), $week->rows);
    }

    /**
     * @param list<TrialLesson> $trials
     */
    private function build(array $trials = []): Grid
    {
        return new CourseGrid()->build($this->course, [$this->b, $this->a], $trials, new \DateTimeImmutable('2026-10-16 12:00'));
    }

    private function item(GridRow $row, Enrollment $enrollment): GridItem
    {
        foreach ($row->items as $item) {
            if ($enrollment === $item->lesson?->getEnrollment()) {
                return $item;
            }
        }

        throw new \LogicException('No lesson of the enrollment in the row.');
    }

    private function trial(string $startsAt): TrialLesson
    {
        return new TrialLesson()->setStudent(new Student()->setFirstName('Schnupper')->setLastName('Test'))
            ->setStartsAt(new \DateTimeImmutable($startsAt));
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
