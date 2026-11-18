<?php

declare(strict_types=1);

namespace App\Course\Grid;

use App\Entity\Course;
use App\Entity\CourseBreak;
use App\Entity\Enrollment;
use App\Entity\Lesson;
use App\Entity\TrialLesson;
use App\Lesson\MakeUpRule;

/**
 * Builds the course grid (umsetzung-admin.md C4b; rows per day since C4f; time axis since C4g): one row per teaching
 * day (every weekday an enrollment has its slot on) and per other day with a lesson or trial lesson, grouped by
 * calendar week. Each row holds boxes at their time: lessons, trial lessons, and free slots of enrollments where
 * nothing takes place (with the offer of a double lesson).
 */
final readonly class CourseGrid
{
    public function __construct(
        private MakeUpRule $makeUpRule = new MakeUpRule(),
    ) {
    }

    /**
     * @param list<Enrollment>  $enrollments with their lessons, ideally loaded in one query
     * @param list<TrialLesson> $trials      trial lessons in the course's time span
     */
    public function build(Course $course, array $enrollments, array $trials, \DateTimeInterface $now): Grid
    {
        $lessons = [];
        foreach ($enrollments as $enrollment) {
            foreach ($enrollment->getLessons() as $lesson) {
                if (null !== $lesson->getStartsAt()) {
                    $lessons[] = $lesson;
                }
            }
        }
        $trials = array_values(array_filter($trials, static fn (TrialLesson $trial): bool => null !== $trial->getStartsAt()));

        $rows = $this->rows($course, $enrollments, $lessons, $trials, $now);
        $numbers = $this->numbers($enrollments);
        $dueOn = $this->makeUpsDue($enrollments, $now);

        foreach ($lessons as $lesson) {
            $id = spl_object_id($lesson);
            // rows() has a row for every day with a lesson
            $rows[$this->dayOf($lesson->getStartsAt())]->add(GridItem::lesson($lesson, $numbers[$id] ?? null, $dueOn[$id] ?? 0));
        }
        foreach ($trials as $trial) {
            $rows[$this->dayOf($trial->getStartsAt())]->add(GridItem::trial($trial));
        }

        $all = [];
        foreach ($rows as $row) {
            $this->addFreeSlots($row, $enrollments);
            $row->finish();
            array_push($all, ...$row->items, ...$row->cancelled);
        }

        return new Grid(GridAxis::around($all), $this->weeks($rows, $now));
    }

    /**
     * A free box where an enrollment's weekly slot is on this day and nothing takes place in it (the lesson was
     * cancelled, deleted or moved). Not on break days, and not before the enrollment's first or after its last
     * regular lesson (it had not started or has ended). Enrollments sharing a slot get one box.
     *
     * @param list<Enrollment> $enrollments
     */
    private function addFreeSlots(GridRow $row, array $enrollments): void
    {
        if ([] !== $row->breaks) {
            return;
        }
        $day = $row->day->format('Y-m-d');
        $added = [];
        foreach ($enrollments as $enrollment) {
            $startTime = $enrollment->getStartTime();
            [$first, $last] = $this->regularSpan($enrollment);
            if (null === $startTime || null === $first || $enrollment->getWeekday() !== $row->weekday() || $day < $first || $day > $last) {
                continue;
            }
            $start = GridItem::minuteOfDay($startTime);
            $end = $start + $enrollment->getLessonMinutes();
            if (!isset($added[$start.'-'.$end]) && !$row->isTaken($start, $end)) {
                $row->add(GridItem::free($start, $end));
                $added[$start.'-'.$end] = true;
            }
        }
    }

    /**
     * First and last day ("Y-m-d") of the enrollment's regular lessons (not make-up lessons), null if none.
     *
     * @return array{?string, ?string}
     */
    private function regularSpan(Enrollment $enrollment): array
    {
        $days = [];
        foreach ($enrollment->getLessons() as $lesson) {
            if (null === $lesson->getMakeUpFor() && null !== $lesson->getStartsAt()) {
                $days[] = $lesson->getStartsAt()->format('Y-m-d');
            }
        }

        return [] === $days ? [null, null] : [min($days), max($days)];
    }

    /**
     * "7/15": the how-manieth counting lesson (Lesson::counts(), all but cancellations by Sonja) of its enrollment,
     * in time order. Keyed by spl_object_id of the lesson; lessons that do not count have no number.
     *
     * @param list<Enrollment> $enrollments
     *
     * @return array<int, int>
     */
    private function numbers(array $enrollments): array
    {
        $numbers = [];
        foreach ($enrollments as $enrollment) {
            $number = 0;
            foreach ($this->inTimeOrder($enrollment) as $lesson) {
                if ($lesson->counts()) {
                    $numbers[spl_object_id($lesson)] = ++$number;
                }
            }
        }

        return $numbers;
    }

    /**
     * Where "Nachholstunde fällig" is shown (Mike 2026-11-18, option A): on the enrollment's next lesson that takes
     * place, so Sonja sees it in the current week; after the last one, on that last one. Keyed by spl_object_id.
     *
     * @param list<Enrollment> $enrollments
     *
     * @return array<int, int>
     */
    private function makeUpsDue(array $enrollments, \DateTimeInterface $now): array
    {
        $dueOn = [];
        foreach ($enrollments as $enrollment) {
            $due = $this->makeUpRule->due($enrollment);
            if (0 === $due) {
                continue;
            }
            $target = null;
            foreach ($this->inTimeOrder($enrollment) as $lesson) {
                if ($lesson->isCancelled()) {
                    continue;
                }
                $target = $lesson;
                if ($lesson->getStartsAt() >= $now) {
                    break;
                }
            }
            if (null !== $target) {
                $dueOn[spl_object_id($target)] = $due;
            }
        }

        return $dueOn;
    }

    /**
     * @return list<Lesson>
     */
    private function inTimeOrder(Enrollment $enrollment): array
    {
        $lessons = array_values(array_filter($enrollment->getLessons()->toArray(), static fn (Lesson $lesson): bool => null !== $lesson->getStartsAt()));
        usort($lessons, static fn (Lesson $a, Lesson $b): int => $a->getStartsAt() <=> $b->getStartsAt());

        return $lessons;
    }

    /**
     * @param array<string, GridRow> $rows ordered by day
     *
     * @return list<GridWeek>
     */
    private function weeks(array $rows, \DateTimeInterface $now): array
    {
        $byWeek = [];
        foreach ($rows as $row) {
            $byWeek[$this->monday($row->day)->format('Y-m-d')][] = $row;
        }
        $currentWeek = $this->monday(\DateTimeImmutable::createFromInterface($now))->format('Y-m-d');

        $weeks = [];
        foreach ($byWeek as $monday => $weekRows) {
            $weeks[] = new GridWeek(new \DateTimeImmutable($monday), $weekRows, $monday === $currentWeek);
        }

        return $weeks;
    }

    /**
     * Days from the course start to its end, widened if a lesson or trial lesson lies outside (e.g. a late make-up
     * lesson): every weekday an enrollment has its slot on, and every day with a lesson or trial lesson.
     *
     * @param list<Enrollment>  $enrollments
     * @param list<Lesson>      $lessons     with a start
     * @param list<TrialLesson> $trials      with a start
     *
     * @return array<string, GridRow> keyed by the day, "Y-m-d", ordered
     */
    private function rows(Course $course, array $enrollments, array $lessons, array $trials, \DateTimeInterface $now): array
    {
        $bounds = array_filter([$course->getStartsOn(), $course->getEndsOn()]);
        $itemDays = [];
        foreach ([...$lessons, ...$trials] as $item) {
            $startsAt = $item->getStartsAt() ?? throw new \LogicException('Filtered before.');
            $bounds[] = $startsAt;
            $itemDays[$startsAt->format('Y-m-d')] = true;
        }
        if ([] === $bounds) {
            return [];
        }

        $teachingWeekdays = [];
        foreach ($enrollments as $enrollment) {
            if (null !== $enrollment->getStartTime()) {
                $teachingWeekdays[$enrollment->getWeekday()] = true;
            }
        }
        $today = \DateTimeImmutable::createFromInterface($now)->format('Y-m-d');
        $last = max($bounds)->setTime(0, 0);
        $rows = [];
        for ($day = min($bounds)->setTime(0, 0); $day <= $last; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            if (isset($teachingWeekdays[(int) $day->format('N')]) || isset($itemDays[$key])) {
                $rows[$key] = new GridRow($day, $this->breaksOn($course, $day), $key === $today);
            }
        }

        return $rows;
    }

    /**
     * @return list<CourseBreak>
     */
    private function breaksOn(Course $course, \DateTimeImmutable $day): array
    {
        return array_values($course->getBreaks()->filter(static fn (CourseBreak $break): bool => $break->contains($day))->toArray());
    }

    private function dayOf(?\DateTimeImmutable $startsAt): string
    {
        return ($startsAt ?? throw new \LogicException('Filtered before.'))->format('Y-m-d');
    }

    private function monday(\DateTimeImmutable $day): \DateTimeImmutable
    {
        return $day->setTime(0, 0)->modify('-'.((int) $day->format('N') - 1).' days');
    }
}
