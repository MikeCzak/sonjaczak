<?php

declare(strict_types=1);

namespace App\Tests\Lesson;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\Enum\LessonStatus;
use App\Entity\Lesson;
use App\Lesson\MakeUpRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MakeUpRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function cases(): iterable
    {
        // cancelled by Sonja, cancelled by student, expected make-up lessons (15 lessons planned)
        yield 'nothing cancelled' => [0, 0, 0];
        yield 'student cancellations are lost' => [0, 3, 0];
        yield 'one by Sonja' => [1, 0, 1];
        yield 'three by Sonja and two by the student' => [3, 2, 3];
    }

    #[DataProvider('cases')]
    public function testDue(int $bySonja, int $byStudent, int $expected): void
    {
        $enrollment = new Enrollment(new Course()->setPlannedLessons(15));
        for ($i = 0; $i < 15; ++$i) {
            $lesson = new Lesson($enrollment, new \DateTimeImmutable('2026-10-09 15:30')->modify("+$i weeks"));
            $lesson->setStatus(match (true) {
                $i < $bySonja => LessonStatus::CancelledByTeacher,
                $i < $bySonja + $byStudent => LessonStatus::CancelledByStudent,
                default => LessonStatus::Held,
            });
        }

        self::assertSame($expected, new MakeUpRule()->due($enrollment));
    }

    public function testMoreLessonsThanPlannedAreAllowed(): void
    {
        $enrollment = new Enrollment(new Course()->setPlannedLessons(2));
        for ($i = 0; $i < 3; ++$i) {
            new Lesson($enrollment, new \DateTimeImmutable('2026-10-09 15:30')->modify("+$i weeks"));
        }

        self::assertSame(0, new MakeUpRule()->due($enrollment));
    }

    public function testMakeUpLessonCounts(): void
    {
        $enrollment = new Enrollment(new Course()->setPlannedLessons(2));
        $cancelled = new Lesson($enrollment, new \DateTimeImmutable('2026-10-09 15:30'))->setStatus(LessonStatus::CancelledByTeacher);
        new Lesson($enrollment, new \DateTimeImmutable('2026-10-16 15:30'));
        self::assertSame(1, new MakeUpRule()->due($enrollment));

        new Lesson($enrollment, new \DateTimeImmutable('2026-10-20 15:30'))->setMakeUpFor($cancelled);
        self::assertSame(0, new MakeUpRule()->due($enrollment));
    }
}
