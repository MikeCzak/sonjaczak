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
     * @return iterable<string, array{int, int, int, int}>
     */
    public static function cases(): iterable
    {
        // threshold, cancelled by Sonja, cancelled by student, expected make-up lessons (15 lessons planned)
        yield 'nothing cancelled' => [0, 0, 0, 0];
        yield 'student cancellations are lost' => [0, 0, 3, 0];
        yield 'one by Sonja, threshold 0' => [0, 1, 0, 1];
        yield 'one by Sonja, within threshold 1' => [1, 1, 0, 0];
        yield 'three by Sonja, threshold 1' => [1, 3, 0, 2];
    }

    #[DataProvider('cases')]
    public function testDue(int $threshold, int $bySonja, int $byStudent, int $expected): void
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

        self::assertSame($expected, new MakeUpRule($threshold)->due($enrollment));
    }

    public function testMakeUpLessonCounts(): void
    {
        $enrollment = new Enrollment(new Course()->setPlannedLessons(2));
        $cancelled = new Lesson($enrollment, new \DateTimeImmutable('2026-10-09 15:30'))->setStatus(LessonStatus::CancelledByTeacher);
        new Lesson($enrollment, new \DateTimeImmutable('2026-10-16 15:30'));
        self::assertSame(1, new MakeUpRule(0)->due($enrollment));

        new Lesson($enrollment, new \DateTimeImmutable('2026-10-20 15:30'))->setMakeUpFor($cancelled);
        self::assertSame(0, new MakeUpRule(0)->due($enrollment));
    }
}
