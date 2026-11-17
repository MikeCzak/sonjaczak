<?php

declare(strict_types=1);

namespace App\Tests\Lesson;

use App\Entity\Course;
use App\Entity\CourseBreak;
use App\Entity\Enrollment;
use App\Entity\Enum\CancelledBy;
use App\Entity\Lesson;
use App\Entity\Student;
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
            self::assertFalse($lesson->isCancelled());
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

    public function testFillUpAtEndLeavesTheGapOfADeletedLesson(): void
    {
        $planner = new LessonPlanner();
        $enrollment = $this->enrollment($this->course('2026-10-05', '2027-02-26'));
        $planner->fillUp($enrollment);
        $second = $enrollment->getLessons()->get(1);
        self::assertNotNull($second);
        $enrollment->removeLesson($second);

        self::assertSame(1, $planner->fillUpAtEnd($enrollment));
        $starts = $enrollment->getLessons()->map(static fn ($lesson): string => $lesson->getStartsAt()?->format('Y-m-d') ?? '')->toArray();
        self::assertNotContains('2026-10-16', $starts, 'fillUp() would put it back here');
        self::assertContains('2027-01-22', $starts, 'one week after the former last lesson (2027-01-15)');
    }

    public function testCancellationBySonjaIsNotFilledUpButCancellationByStudentIs(): void
    {
        $planner = new LessonPlanner();
        $enrollment = $this->enrollment($this->course('2026-10-05', '2027-06-30'));
        $planner->fillUp($enrollment);

        $lessons = $enrollment->getLessons()->toArray();
        $lessons[0]->setCancelledBy(CancelledBy::Student);
        $lessons[1]->setCancelledBy(CancelledBy::Teacher);

        // Filling up never replaces a cancellation: whether Sonja's is made up is the make-up rule's business
        self::assertSame(0, $planner->fillUp($enrollment));
        self::assertCount(15, $enrollment->getLessons());
        self::assertSame(14, $enrollment->countedLessons());
    }

    public function testNewBreakRemovesAllLessonsInItAndFillsUpAtTheEnd(): void
    {
        $planner = new LessonPlanner();
        $course = $this->course('2026-10-05', '2027-06-30');
        $enrollment = $this->enrollment($course);
        $planner->fillUp($enrollment);
        $enrollment->getLessons()->get(1)?->setCancelledBy(CancelledBy::Student);

        // A break over the first two Fridays: a held one and a cancelled one, both go
        $course->addBreak($this->break('2026-10-09', '2026-10-16'));
        self::assertCount(2, $planner->lessonsOnBreakDays($course));
        $result = $planner->applyBreaks($course);

        self::assertSame(2, $result->removed);
        self::assertSame(2, $result->added);
        self::assertCount(15, $enrollment->getLessons());
        self::assertNotContains('2026-10-09 15:30', $this->days($enrollment));
        // Fridays 2026-10-09 … 2027-01-15 were planned; the replacements come after the last one
        self::assertContains('2027-01-22 15:30', $this->days($enrollment));
        self::assertContains('2027-01-29 15:30', $this->days($enrollment));
    }

    public function testLessonsBetweenIncludesBothDays(): void
    {
        $planner = new LessonPlanner();
        $course = $this->course('2026-10-05', '2027-02-26');
        $planner->fillUp($this->enrollment($course));

        self::assertCount(2, $planner->lessonsBetween($course, new \DateTimeImmutable('2026-10-09'), new \DateTimeImmutable('2026-10-16')));
        self::assertCount(1, $planner->lessonsBetween($course, new \DateTimeImmutable('2026-10-16'), new \DateTimeImmutable('2026-10-16')));
    }

    /**
     * Question 7: deleting a break gives the lesson back on the freed day, the last one still to come goes.
     */
    public function testDeletedBreakGivesTheLessonBack(): void
    {
        $planner = new LessonPlanner();
        $course = $this->course('2026-10-05', '2027-06-30');
        $break = $this->break('2026-10-16', '2026-10-16');
        $course->addBreak($break);
        $enrollment = $this->enrollment($course);
        $planner->fillUp($enrollment);
        self::assertContains('2027-01-22 15:30', $this->days($enrollment));

        $course->removeBreak($break);
        $result = $planner->restoreBreakDays($course, $break, new \DateTimeImmutable('2026-10-01'));

        self::assertSame(1, $result->added);
        self::assertSame(1, $result->removed);
        self::assertCount(15, $enrollment->getLessons());
        self::assertContains('2026-10-16 15:30', $this->days($enrollment));
        self::assertNotContains('2027-01-22 15:30', $this->days($enrollment));
    }

    public function testDeletedBreakStillCoveredByAnotherOneGivesNothingBack(): void
    {
        $planner = new LessonPlanner();
        $course = $this->course('2026-10-05', '2027-06-30');
        $break = $this->break('2026-10-16', '2026-10-16');
        $course->addBreak($break);
        $course->addBreak($this->break('2026-10-12', '2026-10-18'));
        $planner->fillUp($this->enrollment($course));

        $course->removeBreak($break);

        self::assertSame(0, $planner->restoreBreakDays($course, $break, new \DateTimeImmutable('2026-10-01'))->added);
    }

    public function testMinimumIsTheRegularLessonsThatStarted(): void
    {
        $planner = new LessonPlanner();
        $enrollment = $this->enrollment($this->course('2026-10-05', '2027-02-26'));
        $planner->fillUp($enrollment);
        // Fridays 09.10., 16.10., 23.10. have started at 30.10. 10:00, also a cancelled one
        $enrollment->getLessons()->get(1)?->setCancelledBy(CancelledBy::Teacher);

        self::assertSame(3, $planner->minimumPlannedLessons($enrollment, new \DateTimeImmutable('2026-10-30 10:00')));
        self::assertSame(0, $planner->minimumPlannedLessons($enrollment, new \DateTimeImmutable('2026-10-01')));
    }

    public function testLoweringRemovesTheLastLessonsAlsoCancelledOnesButNoMakeUps(): void
    {
        $planner = new LessonPlanner();
        $enrollment = $this->enrollment($this->course('2026-10-05', '2027-02-26'));
        $planner->fillUp($enrollment);
        $lessons = $enrollment->getLessons()->toArray();
        $lessons[14]->setCancelledBy(CancelledBy::Student);
        $makeUp = new Lesson($enrollment, new \DateTimeImmutable('2027-01-20 15:30'))->setMakeUpFor($lessons[0]);
        $now = new \DateTimeImmutable('2026-10-30 10:00');

        $surplus = $planner->surplusLessons($enrollment, 12, $now);
        self::assertSame([$lessons[12], $lessons[13], $lessons[14]], $surplus, 'the three last, sorted by time');
        self::assertSame([], $planner->surplusLessons($enrollment, 15, $now));

        $result = $planner->changePlannedLessons($enrollment, 12, $now);
        self::assertSame(3, $result->removed);
        self::assertSame(0, $result->added);
        self::assertSame(12, $enrollment->getPlannedLessons());
        self::assertTrue($enrollment->getLessons()->contains($makeUp));
        self::assertCount(13, $enrollment->getLessons(), '12 regular + the make-up lesson');
    }

    public function testEndingRemovesAllFutureLessons(): void
    {
        $planner = new LessonPlanner();
        $enrollment = $this->enrollment($this->course('2026-10-05', '2027-02-26'));
        $planner->fillUp($enrollment);
        $now = new \DateTimeImmutable('2026-10-30 10:00');

        $planner->changePlannedLessons($enrollment, $planner->minimumPlannedLessons($enrollment, $now), $now);

        self::assertSame(['2026-10-09 15:30', '2026-10-16 15:30', '2026-10-23 15:30'], $this->days($enrollment));
        // Later deletions or breaks do not bring lessons back: the enrollment's own number counts
        self::assertSame(0, $planner->fillUp($enrollment));
    }

    public function testRaisingAddsLessonsAtTheEnd(): void
    {
        $planner = new LessonPlanner();
        $enrollment = $this->enrollment($this->course('2026-10-05', '2027-02-26'));
        $planner->fillUp($enrollment);

        $result = $planner->changePlannedLessons($enrollment, 16, new \DateTimeImmutable('2026-10-30 10:00'));

        self::assertSame(1, $result->added);
        self::assertSame('2027-01-22 15:30', $this->days($enrollment)[15]);
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
        // With a student: the planner leaves enrollments without one alone (personal data deleted, F3)
        $enrollment = new Enrollment($course, new Student())->setWeekday(5)->setStartTime(new \DateTimeImmutable('15:30'));
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
