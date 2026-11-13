<?php

declare(strict_types=1);

namespace App\Lesson;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\Enum\LessonStatus;
use App\Entity\Lesson;

/**
 * Generates the weekly lessons of an enrollment (docs/planung/datenmodell.md 3.5): from the start of the course on
 * the enrollment's weekday and time, skipping breaks, until the course's planned number is reached, at most until
 * the end of the course. Nothing is flushed; the caller does that.
 */
final class LessonPlanner
{
    /**
     * Adds planned lessons until the enrollment has plannedLessons regular lessons. Regular means every status, only
     * make-up lessons are left out: a lesson Sonja cancelled keeps its place here, whether it is made up is decided
     * by the make-up rule alone (MakeUpRule), not by filling up. Weeks that already have a regular lesson are skipped,
     * so calling it again only fills gaps.
     *
     * @return int number of added lessons
     */
    public function fillUp(Enrollment $enrollment): int
    {
        $course = $enrollment->getCourse();
        $startsOn = $course->getStartsOn();
        $endsOn = $course->getEndsOn();
        $time = $enrollment->getStartTime();
        if (null === $startsOn || null === $endsOn || null === $time) {
            return 0;
        }

        $weeksTaken = $this->weeksWithRegularLesson($enrollment);
        $regular = $enrollment->getLessons()->filter(static fn (Lesson $lesson): bool => null === $lesson->getMakeUpFor())->count();
        $missing = $course->getPlannedLessons() - $regular;
        $added = 0;

        for ($day = $this->firstWeekday($startsOn, $enrollment->getWeekday()); $missing > 0 && $day <= $endsOn; $day = $day->modify('+1 week')) {
            if ($course->isBreakDay($day) || isset($weeksTaken[$day->format('o-W')])) {
                continue;
            }
            new Lesson($enrollment, $day->setTime((int) $time->format('G'), (int) $time->format('i')));
            --$missing;
            ++$added;
        }

        return $added;
    }

    /**
     * After breaks were added: removes planned lessons that fall into a break and fills up again at the end.
     * Lessons already held or cancelled in a break stay and are returned as conflicts for Sonja to look at.
     */
    public function applyBreaks(Course $course): BreakResult
    {
        $result = new BreakResult();

        foreach ($course->getEnrollments() as $enrollment) {
            foreach ($enrollment->getLessons()->toArray() as $lesson) {
                $startsAt = $lesson->getStartsAt();
                if (null === $startsAt || !$course->isBreakDay($startsAt)) {
                    continue;
                }
                if (LessonStatus::Planned === $lesson->getStatus()) {
                    // orphanRemoval: removed from the collection, the row is deleted on flush
                    $enrollment->removeLesson($lesson);
                    ++$result->removed;
                } else {
                    $result->conflicts[] = $lesson;
                }
            }
            $result->added += $this->fillUp($enrollment);
        }

        return $result;
    }

    private function firstWeekday(\DateTimeImmutable $from, int $isoWeekday): \DateTimeImmutable
    {
        $from = $from->setTime(0, 0);
        $offset = ($isoWeekday - (int) $from->format('N') + 7) % 7;

        return $from->modify(\sprintf('+%d days', $offset));
    }

    /**
     * ISO weeks ("2026-41") that already have a lesson which is not a make-up lesson.
     *
     * @return array<string, true>
     */
    private function weeksWithRegularLesson(Enrollment $enrollment): array
    {
        $weeks = [];
        foreach ($enrollment->getLessons() as $lesson) {
            if (null === $lesson->getMakeUpFor() && null !== $lesson->getStartsAt()) {
                $weeks[$lesson->getStartsAt()->format('o-W')] = true;
            }
        }

        return $weeks;
    }
}
