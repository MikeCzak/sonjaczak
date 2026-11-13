<?php

declare(strict_types=1);

namespace App\Tests\Lesson;

use App\Entity\Course;
use App\Entity\CourseBreak;
use App\Entity\Enrollment;
use App\Entity\Enum\LessonStatus;
use App\Entity\Lesson;
use App\Lesson\LessonPlanner;
use PHPUnit\Framework\TestCase;

final class LessonPlannerTest extends TestCase
{
    public function testFifteenFridaysFromCourseStartSkippingBreaks(): void
    {
        // Course starts on a Monday, lessons on Friday 15:30
        $enrollment = $this->enrollment($this->course('2026-10-05', '2027-02-26')->addBreak($this->break('2026-10-27', '2026-10-31')));

        self::assertSame(15, new LessonPlanner()->fillUp($enrollment));

        $days = $this->days($enrollment);
        self::assertSame('2026-10-09 15:30', $days[0]);
        self::assertSame('2026-10-23 15:30', $days[2]);
        self::assertSame('2026-11-06 15:30', $days[3], 'Herbstferien week is skipped');
        self::assertCount(15, $days);
        foreach ($enrollment->getLessons() as $lesson) {
            self::assertSame(LessonStatus::Planned, $lesson->getStatus());
            self::assertSame(50, $lesson->getDurationMinutes());
        }
    }

    public function testStopsAtCourseEnd(): void
    {
        // Only 4 Fridays between 2026-10-05 and 2026-11-01
        $enrollment = $this->enrollment($this->course('2026-10-05', '2026-11-01'));

        self::assertSame(4, new LessonPlanner()->fillUp($enrollment));
        self::assertSame('2026-10-30 15:30', $this->days($enrollment)[3]);
    }

    public function testCalledAgainItOnlyFillsGaps(): void
    {
        $planner = new LessonPlanner();
        $enrollment = $this->enrollment($this->course('2026-10-05', '2027-02-26'));
        $planner->fillUp($enrollment);

        self::assertSame(0, $planner->fillUp($enrollment));
        self::assertCount(15, $enrollment->getLessons());
    }

    public function testCancellationBySonjaIsNotFilledUpButCancellationByStudentIs(): void
    {
        $planner = new LessonPlanner();
        $enrollment = $this->enrollment($this->course('2026-10-05', '2027-06-30'));
        $planner->fillUp($enrollment);

        $lessons = $enrollment->getLessons()->toArray();
        $lessons[0]->setStatus(LessonStatus::CancelledByStudent);
        $lessons[1]->setStatus(LessonStatus::CancelledByTeacher);

        // Filling up never replaces a cancellation: whether Sonja's is made up is the make-up rule's business
        self::assertSame(0, $planner->fillUp($enrollment));
        self::assertCount(15, $enrollment->getLessons());
        self::assertSame(14, $enrollment->countedLessons());
    }

    public function testNewBreakRemovesPlannedLessonsKeepsOthersAndFillsUpAtTheEnd(): void
    {
        $planner = new LessonPlanner();
        $course = $this->course('2026-10-05', '2027-06-30');
        $enrollment = $this->enrollment($course);
        $planner->fillUp($enrollment);
        $lessons = $enrollment->getLessons()->toArray();
        $lessons[0]->setStatus(LessonStatus::Held);

        // A break over the first two Fridays: the held one stays as a conflict, the planned one goes
        $course->addBreak($this->break('2026-10-09', '2026-10-16'));
        $result = $planner->applyBreaks($course);

        self::assertSame(1, $result->removed);
        self::assertSame([$lessons[0]], $result->conflicts);
        self::assertSame(1, $result->added);
        self::assertCount(15, $enrollment->getLessons());
        self::assertNotContains('2026-10-16 15:30', $this->days($enrollment));
        // Fridays 2026-10-09 … 2027-01-15 were planned; the replacement comes the week after the last one
        self::assertContains('2027-01-22 15:30', $this->days($enrollment));
    }

    private function course(string $startsOn, string $endsOn): Course
    {
        return new Course()
            ->setStartsOn(new \DateTimeImmutable($startsOn))
            ->setEndsOn(new \DateTimeImmutable($endsOn))
            ->setPriceCents(49500);
    }

    private function break(string $first, string $last): CourseBreak
    {
        return new CourseBreak('Ferien', new \DateTimeImmutable($first), new \DateTimeImmutable($last));
    }

    private function enrollment(Course $course): Enrollment
    {
        $enrollment = new Enrollment($course)->setWeekday(5)->setStartTime(new \DateTimeImmutable('15:30'));
        $course->getEnrollments()->add($enrollment);

        return $enrollment;
    }

    /**
     * @return list<string>
     */
    private function days(Enrollment $enrollment): array
    {
        $days = array_map(static fn (Lesson $lesson): string => (string) $lesson->getStartsAt()?->format('Y-m-d H:i'), $enrollment->getLessons()->toArray());
        sort($days);

        return $days;
    }
}
