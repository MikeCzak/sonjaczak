<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\TrialLesson;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * A trial lesson shortly before the winter term 2026/27.
 *
 * @extends PersistentObjectFactory<TrialLesson>
 */
final class TrialLessonFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return TrialLesson::class;
    }

    #[\Override]
    protected function defaults(): array
    {
        return [
            'student' => StudentFactory::new(),
            'startsAt' => new \DateTimeImmutable('2026-09-30 16:00'),
            'durationMinutes' => 50,
            'note' => 'Stimmumfang, „Someone Like You“',
        ];
    }
}
