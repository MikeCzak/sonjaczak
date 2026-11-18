<?php

declare(strict_types=1);

namespace App\Course\Grid;

use App\Entity\Lesson;
use App\Entity\TrialLesson;

/**
 * One box on the time axis of a day (umsetzung-admin.md C4g): a lesson, a trial lesson, or a free time slot of an
 * enrollment where nothing takes place. Times are minutes after midnight; the template places the box with them.
 */
final class GridItem
{
    public const string LESSON = 'lesson';
    public const string TRIAL = 'trial';
    public const string FREE = 'free';

    /**
     * Stacking lane within the day, 0 = top. Items that overlap in time get different lanes.
     */
    public int $lane = 0;

    /**
     * Other items of the day that take place at the same time (only lessons and trial lessons that take place).
     *
     * @var list<GridItem>
     */
    public array $overlaps = [];

    /**
     * Free slot only: a double lesson for the neighbour before ("after" its lesson) or after it ("before").
     *
     * @var list<array{lesson: Lesson, position: 'before'|'after'}>
     */
    public array $doubleOffers = [];

    /**
     * @param int|null $number     lesson only: the how-manieth counting lesson of its enrollment (null if it does not count)
     * @param int      $makeUpsDue lesson only: shown on the enrollment's next lesson, 0 elsewhere
     */
    private function __construct(
        public readonly string $kind,
        public readonly int $startMinute,
        public readonly int $endMinute,
        public readonly ?Lesson $lesson = null,
        public readonly ?TrialLesson $trial = null,
        public readonly ?int $number = null,
        public readonly int $makeUpsDue = 0,
    ) {
    }

    public static function lesson(Lesson $lesson, ?int $number, int $makeUpsDue): self
    {
        $start = self::minuteOfDay($lesson->getStartsAt() ?? throw new \LogicException('Lesson without start.'));

        return new self(self::LESSON, $start, $start + $lesson->getDurationMinutes(), $lesson, number: $number, makeUpsDue: $makeUpsDue);
    }

    public static function trial(TrialLesson $trial): self
    {
        $start = self::minuteOfDay($trial->getStartsAt() ?? throw new \LogicException('Trial lesson without start.'));

        return new self(self::TRIAL, $start, $start + $trial->getDurationMinutes(), trial: $trial);
    }

    public static function free(int $startMinute, int $endMinute): self
    {
        return new self(self::FREE, $startMinute, $endMinute);
    }

    public static function minuteOfDay(\DateTimeImmutable $time): int
    {
        return (int) $time->format('G') * 60 + (int) $time->format('i');
    }

    /**
     * Something that takes place: a lesson that is not cancelled, or a trial lesson.
     */
    public function takesPlace(): bool
    {
        return match ($this->kind) {
            self::LESSON => !($this->lesson?->isCancelled() ?? false),
            self::TRIAL => true,
            default => false,
        };
    }

    public function overlapsTime(int $startMinute, int $endMinute): bool
    {
        // Back to back (15:50 ends, 15:50 starts) is no overlap
        return $this->startMinute < $endMinute && $startMinute < $this->endMinute;
    }

    public function startTime(): string
    {
        return \sprintf('%02d:%02d', intdiv($this->startMinute, 60), $this->startMinute % 60);
    }

    /**
     * Name for the overlap hint: the student, or nothing if the person was deleted (F3).
     */
    public function name(): string
    {
        $student = $this->lesson?->getEnrollment()->getStudent() ?? $this->trial?->getStudent();

        return $student?->getFullName() ?? '';
    }
}
