<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Enrollment;
use App\Lesson\LessonPlanner;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Enrollment on Friday 15:30 with its generated lessons (like EnrollmentController::new()).
 *
 * @extends PersistentObjectFactory<Enrollment>
 */
final class EnrollmentFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Enrollment::class;
    }

    #[\Override]
    protected function defaults(): array
    {
        return [
            'course' => CourseFactory::new(),
            'student' => StudentFactory::new(),
            'weekday' => 5,
            'startTime' => new \DateTimeImmutable('15:30'),
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (Enrollment $enrollment): void {
            // Keep the inverse side in sync, the course page reads it in the same request
            $enrollment->getCourse()->getEnrollments()->add($enrollment);
            new LessonPlanner()->fillUp($enrollment);
        });
    }
}
