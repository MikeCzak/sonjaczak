<?php

declare(strict_types=1);

namespace App\Tests\Course;

use App\Course\Grid\CourseGrid;
use App\Course\Grid\GridRow;
use App\Entity\Course;
use App\Entity\CourseBreak;
use App\Entity\Enrollment;
use App\Entity\Enum\CancelledBy;
use App\Entity\Lesson;
use App\Entity\Student;
use PHPUnit\Framework\TestCase;

/**
 * Rows, columns and which column a lesson goes to (umsetzung-admin.md C4b, question 8).
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

    public function testWeeksAndSlots(): void
    {
        $grid = $this->build();

        self::assertSame(['5-15:00', '5-15:50'], array_map(static fn ($c): string => $c->key, $grid->columns));
        self::assertSame('15:50', $grid->columns[1]->startTime());
        self::assertSame(['2026-10-05', '2026-10-12', '2026-10-19'], array_map(static fn (GridRow $r): string => $r->weekStart->format('Y-m-d'), $grid->rows));
        self::assertTrue($grid->rows[1]->current, 'now (2026-10-14) is in the second week');
        self::assertCount(1, $grid->rows[0]->cells['5-15:00']);
    }

    public function testCancelledLessonGoesToTheLowRow(): void
    {
        $this->lesson($this->a, '2026-10-16 15:00')->setCancelledBy(CancelledBy::Teacher);

        $row = $this->build()->rows[1];

        self::assertArrayNotHasKey('5-15:00', $row->cells);
        self::assertCount(1, $row->cancelled['5-15:00']);
        self::assertTrue($row->hasCancelled());
        self::assertFalse($this->build()->rows[0]->hasCancelled());
    }

    public function testDoubleLessonInTheFreedSlotOfTheNeighbour(): void
    {
        $this->lesson($this->a, '2026-10-16 15:00')->setCancelledBy(CancelledBy::Student);
        // Second lesson of B directly before B's own, in A's slot
        $this->lesson($this->b, '2026-10-16 15:00');

        $cell = $this->build()->rows[1]->cells['5-15:00'];

        self::assertCount(1, $cell);
        self::assertSame($this->b, $cell[0]->getEnrollment());
    }

    public function testMakeUpOnAnotherDayStaysInItsOwnColumn(): void
    {
        $makeUp = new Lesson($this->b, new \DateTimeImmutable('2026-10-21 17:00'));

        $cell = $this->build()->rows[2]->cells['5-15:50'];

        self::assertSame([$makeUp, $this->lessonsOn($this->b, '2026-10-23')], $cell, 'sorted by time');
    }

    public function testRowsWidenForLessonsAfterTheEnd(): void
    {
        new Lesson($this->a, new \DateTimeImmutable('2026-11-02 10:00'));

        $rows = $this->build()->rows;

        self::assertCount(5, $rows);
        self::assertSame([], $rows[3]->cells);
    }

    public function testBreaksMarkTheirWeeks(): void
    {
        $this->course->addBreak(new CourseBreak('Nationalfeiertag', new \DateTimeImmutable('2026-10-26')));
        $this->course->addBreak(new CourseBreak('Schulautonom', new \DateTimeImmutable('2026-10-16')));

        $rows = $this->build()->rows;

        self::assertSame([], $rows[0]->breaks);
        self::assertSame('Schulautonom', $rows[1]->breaks[0]->getLabel());
    }

    private function build(): \App\Course\Grid\Grid
    {
        return new CourseGrid()->build($this->course, [$this->b, $this->a], new \DateTimeImmutable('2026-10-14 12:00'));
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

    private function lessonsOn(Enrollment $enrollment, string $day): Lesson
    {
        foreach ($enrollment->getLessons() as $lesson) {
            if ($lesson->getStartsAt()?->format('Y-m-d') === $day) {
                return $lesson;
            }
        }

        throw new \LogicException('No lesson on '.$day);
    }
}
