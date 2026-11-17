<?php

declare(strict_types=1);

namespace App\Lesson;

use App\Entity\Course;
use App\Entity\CourseBreak;
use App\Entity\Enrollment;
use App\Entity\Lesson;

/**
 * Generates the weekly lessons of an enrollment (docs/planung/datenmodell.md 3.5): from the start of the course on
 * the enrollment's weekday and time, skipping breaks, until the enrollment's planned number is reached, at most until
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
        return $this->fill($enrollment, null);
    }

    /**
     * Like fillUp(), but only after the last regular lesson: used when a lesson was deleted on purpose (course grid,
     * umsetzung-admin.md C4 question 10). fillUp() would put it back into the week it was just deleted from.
     *
     * @return int number of added lessons
     */
    public function fillUpAtEnd(Enrollment $enrollment): int
    {
        $last = null;
        foreach ($enrollment->getLessons() as $lesson) {
            if (null === $lesson->getMakeUpFor() && null !== $lesson->getStartsAt() && (null === $last || $lesson->getStartsAt() > $last)) {
                $last = $lesson->getStartsAt();
            }
        }

        return $this->fill($enrollment, $last?->setTime(0, 0)->modify('+1 day'));
    }

    private function fill(Enrollment $enrollment, ?\DateTimeImmutable $from): int
    {
        $course = $enrollment->getCourse();
        $startsOn = $course->getStartsOn();
        $endsOn = $course->getEndsOn();
        $time = $enrollment->getStartTime();
        // Without a person only the invoices are kept (F3): no new lessons, e.g. when breaks of an old course change
        if (null === $startsOn || null === $endsOn || null === $time || $enrollment->isPersonDeleted()) {
            return 0;
        }

        $weeksTaken = $this->weeksWithRegularLesson($enrollment);
        $missing = $enrollment->getPlannedLessons() - $this->regularCount($enrollment);
        $added = 0;

        $from = null !== $from && $from > $startsOn ? $from : $startsOn;
        for ($day = $this->firstWeekday($from, $enrollment->getWeekday()); $missing > 0 && $day <= $endsOn; $day = $day->modify('+1 week')) {
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
     * After breaks were added: every lesson on a break day goes, whether past, cancelled or with a note (Mike
     * 2026-11-16: entering a break afterwards means nothing took place; Sonja confirmed a popup listing them,
     * umsetzung-admin.md C4 question 6). The enrollments are filled up at the end.
     */
    public function applyBreaks(Course $course): BreakResult
    {
        $result = new BreakResult();

        foreach ($course->getEnrollments() as $enrollment) {
            foreach ($enrollment->getLessons()->toArray() as $lesson) {
                $startsAt = $lesson->getStartsAt();
                if (null !== $startsAt && $course->isBreakDay($startsAt)) {
                    // orphanRemoval: removed from the collection, the row is deleted on flush
                    $enrollment->removeLesson($lesson);
                    ++$result->removed;
                }
            }
            $result->added += $this->fillUpAtEnd($enrollment);
        }

        return $result;
    }

    /**
     * Lessons that applyBreaks() would delete: those on a break day. Before the new breaks are saved, for the popup.
     *
     * @return list<Lesson>
     */
    public function lessonsOnBreakDays(Course $course): array
    {
        return $this->lessonsWhere($course, static fn (\DateTimeImmutable $startsAt): bool => $course->isBreakDay($startsAt));
    }

    /**
     * Lessons from the first to the last day (both included), for the popup before a single break is saved.
     *
     * @return list<Lesson>
     */
    public function lessonsBetween(Course $course, \DateTimeImmutable $firstDay, \DateTimeImmutable $lastDay): array
    {
        $from = $firstDay->setTime(0, 0);
        $until = $lastDay->setTime(0, 0)->modify('+1 day');

        return $this->lessonsWhere($course, static fn (\DateTimeImmutable $startsAt): bool => $startsAt >= $from && $startsAt < $until);
    }

    /**
     * After a break was deleted (question 7): the enrollments get their lesson back on the freed days, at their
     * usual time, unless the week already has a regular lesson or the day is still in another break. Each enrollment
     * keeps its planned number: for every lesson that comes back, the last one still to come goes.
     */
    public function restoreBreakDays(Course $course, CourseBreak $break, \DateTimeInterface $now): BreakResult
    {
        $result = new BreakResult();
        $first = $break->getFirstDay();
        $last = $break->getLastDay();
        $startsOn = $course->getStartsOn();
        $endsOn = $course->getEndsOn();
        if (null === $first || null === $last || null === $startsOn || null === $endsOn) {
            return $result;
        }

        foreach ($course->getEnrollments() as $enrollment) {
            $time = $enrollment->getStartTime();
            if (null === $time || $enrollment->isPersonDeleted()) {
                continue;
            }
            $weeksTaken = $this->weeksWithRegularLesson($enrollment);
            $created = [];
            for ($day = $this->firstWeekday(max($first, $startsOn), $enrollment->getWeekday()); $day <= $last && $day <= $endsOn; $day = $day->modify('+1 week')) {
                if ($course->isBreakDay($day) || isset($weeksTaken[$day->format('o-W')])) {
                    continue;
                }
                $created[] = new Lesson($enrollment, $day->setTime((int) $time->format('G'), (int) $time->format('i')));
                ++$result->added;
            }
            foreach ($created as $ignored) {
                if ($this->regularCount($enrollment) > $enrollment->getPlannedLessons() && $this->removeLastFutureLesson($enrollment, $now, $created)) {
                    ++$result->removed;
                }
            }
        }

        return $result;
    }

    /**
     * Removes the regular lesson furthest in the future that is not cancelled (a double lesson or a lesson that came
     * back takes its place). False if there is none still to come.
     *
     * @param list<Lesson> $except lessons that must stay (the new one, the one it belongs to)
     */
    public function removeLastFutureLesson(Enrollment $enrollment, \DateTimeInterface $now, array $except = []): bool
    {
        $last = null;
        foreach ($enrollment->getLessons() as $lesson) {
            $startsAt = $lesson->getStartsAt();
            if (\in_array($lesson, $except, true) || null !== $lesson->getMakeUpFor() || $lesson->isCancelled() || null === $startsAt || $startsAt <= $now) {
                continue;
            }
            if (null === $last || $startsAt > $last->getStartsAt()) {
                $last = $lesson;
            }
        }
        if (null === $last) {
            return false;
        }
        $enrollment->removeLesson($last);

        return true;
    }

    /**
     * The lowest planned number Sonja can set (umsetzung-admin.md C7, question 4): the regular lessons that already
     * started, whatever their state. Setting exactly this ends the enrollment.
     */
    public function minimumPlannedLessons(Enrollment $enrollment, \DateTimeInterface $now): int
    {
        return \count(array_filter(
            $enrollment->getLessons()->toArray(),
            static fn (Lesson $lesson): bool => null === $lesson->getMakeUpFor() && null !== $lesson->getStartsAt() && $lesson->getStartsAt() <= $now,
        ));
    }

    /**
     * The regular lessons that go when the enrollment gets this planned number (C7): the ones furthest in the future,
     * cancelled or not, until the regular lessons fit the number. Empty when raising. Make-up lessons stay; they are
     * not part of the planned number. Nothing is removed here, for the confirmation step.
     *
     * @return list<Lesson> sorted by time
     */
    public function surplusLessons(Enrollment $enrollment, int $plannedLessons, \DateTimeInterface $now): array
    {
        $future = array_values(array_filter(
            $enrollment->getLessons()->toArray(),
            static fn (Lesson $lesson): bool => null === $lesson->getMakeUpFor() && null !== $lesson->getStartsAt() && $lesson->getStartsAt() > $now,
        ));
        usort($future, static fn (Lesson $a, Lesson $b): int => $a->getStartsAt() <=> $b->getStartsAt());
        $surplus = max(0, $this->regularCount($enrollment) - $plannedLessons);

        return array_slice($future, max(0, \count($future) - $surplus));
    }

    /**
     * Sets the planned number and adjusts the lessons (C7): lowering removes surplusLessons(), raising adds lessons
     * after the last one (fillUpAtEnd()).
     */
    public function changePlannedLessons(Enrollment $enrollment, int $plannedLessons, \DateTimeInterface $now): BreakResult
    {
        $result = new BreakResult();
        foreach ($this->surplusLessons($enrollment, $plannedLessons, $now) as $lesson) {
            // orphanRemoval: removed from the collection, the row is deleted on flush
            $enrollment->removeLesson($lesson);
            ++$result->removed;
        }
        $enrollment->setPlannedLessons($plannedLessons);
        $result->added = $this->fillUpAtEnd($enrollment);

        return $result;
    }

    /**
     * @param callable(\DateTimeImmutable): bool $matches
     *
     * @return list<Lesson>
     */
    private function lessonsWhere(Course $course, callable $matches): array
    {
        $lessons = [];
        foreach ($course->getEnrollments() as $enrollment) {
            foreach ($enrollment->getLessons() as $lesson) {
                $startsAt = $lesson->getStartsAt();
                if (null !== $startsAt && $matches($startsAt)) {
                    $lessons[] = $lesson;
                }
            }
        }
        usort($lessons, static fn (Lesson $a, Lesson $b): int => $a->getStartsAt() <=> $b->getStartsAt());

        return $lessons;
    }

    private function regularCount(Enrollment $enrollment): int
    {
        return $enrollment->getLessons()->filter(static fn (Lesson $lesson): bool => null === $lesson->getMakeUpFor())->count();
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
