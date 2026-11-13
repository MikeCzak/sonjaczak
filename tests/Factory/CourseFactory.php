<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Course;
use App\Entity\CourseBreak;
use App\Entity\Enum\Term;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Courses like Sonja's: Wintersemester 2026/27, 15 lessons of 50 minutes, 495 €.
 *
 * @extends PersistentObjectFactory<Course>
 */
final class CourseFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Course::class;
    }

    public function withBreak(string $label = 'Herbstferien', string $firstDay = '2026-10-27', string $lastDay = '2026-10-31'): static
    {
        return $this->afterInstantiate(static function (Course $course) use ($label, $firstDay, $lastDay): void {
            $course->addBreak(new CourseBreak($label, new \DateTimeImmutable($firstDay), new \DateTimeImmutable($lastDay)));
        });
    }

    #[\Override]
    protected function defaults(): array
    {
        return [
            'term' => Term::Winter,
            'year' => 2026,
            'startsOn' => new \DateTimeImmutable('2026-10-05'),
            'endsOn' => new \DateTimeImmutable('2027-02-26'),
            'room' => '1.12',
            'priceCents' => 49500,
            'plannedLessons' => 15,
            'lessonMinutes' => 50,
        ];
    }
}
