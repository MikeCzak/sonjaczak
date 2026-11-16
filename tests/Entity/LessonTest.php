<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\Enum\CancelledBy;
use App\Entity\Enum\LessonState;
use App\Entity\Lesson;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The derived state of a lesson: only the cancellation is stored, "held" follows from the time.
 */
final class LessonTest extends TestCase
{
    /**
     * @return iterable<string, array{?CancelledBy, string, LessonState}>
     */
    public static function cases(): iterable
    {
        // Lesson on Friday 2026-10-09 15:30, 50 minutes, so it ends at 16:20
        yield 'before the start' => [null, '2026-10-09 15:00', LessonState::Planned];
        yield 'in progress is not held yet' => [null, '2026-10-09 16:00', LessonState::Planned];
        yield 'held exactly at the end' => [null, '2026-10-09 16:20', LessonState::Held];
        yield 'cancelled by the student stays cancelled' => [CancelledBy::Student, '2026-10-10 09:00', LessonState::CancelledByStudent];
        yield 'cancelled by Sonja in advance' => [CancelledBy::Teacher, '2026-10-01 09:00', LessonState::CancelledByTeacher];
    }

    #[DataProvider('cases')]
    public function testState(?CancelledBy $cancelledBy, string $now, LessonState $expected): void
    {
        $lesson = new Lesson(new Enrollment(new Course()), new \DateTimeImmutable('2026-10-09 15:30'), 50);
        $lesson->setCancelledBy($cancelledBy);

        self::assertSame($expected, $lesson->state(new \DateTimeImmutable($now)));
    }

    public function testOnlyCancellationBySonjaDoesNotCount(): void
    {
        $lesson = new Lesson(new Enrollment(new Course()), new \DateTimeImmutable('2026-10-09 15:30'), 50);

        self::assertTrue($lesson->counts());
        self::assertTrue($lesson->setCancelledBy(CancelledBy::Student)->counts());
        self::assertFalse($lesson->setCancelledBy(CancelledBy::Teacher)->counts());
    }
}
