<?php

declare(strict_types=1);

namespace App\Tests\Story;

use App\Tests\Factory\CourseFactory;
use App\Tests\Factory\GuardianFactory;
use App\Tests\Factory\StudentFactory;
use App\Tests\Factory\UserFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/**
 * Invented data for the local database: `bin/console foundry:load-fixtures main`.
 * Never real people, see "Daten für Entwicklung und Tests" in docs/planung/datenmodell.md.
 * Extended step by step (docs/planung/umsetzung-admin.md).
 */
#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    public function build(): void
    {
        // Logins with UserFactory::PASSWORD (docs/entwicklung.md)
        UserFactory::new()->superAdmin()->create(['email' => 'mike@example.com']);
        UserFactory::new()->admin()->create(['email' => 'sonja@example.com']);

        // Adults, children with one guardian each, and two siblings sharing one
        StudentFactory::createMany(12);
        StudentFactory::new()->minor()->many(6)->create();
        $siblingsParent = GuardianFactory::createOne();
        StudentFactory::new()->minor()->many(2)->create(['guardian' => $siblingsParent, 'lastName' => 'Geschwister']);

        // Breaks entered here instead of fetched: loading fixtures must not depend on the OpenHolidays API
        CourseFactory::new()
            ->withBreak('Nationalfeiertag', '2026-10-26', '2026-10-26')
            ->withBreak('Herbstferien', '2026-10-27', '2026-10-31')
            ->withBreak('Allerseelen', '2026-11-02', '2026-11-02')
            ->withBreak('Mariä Empfängnis', '2026-12-08', '2026-12-08')
            ->withBreak('Weihnachtsferien', '2026-12-24', '2027-01-06')
            ->withBreak('Semesterferien', '2027-02-15', '2027-02-20')
            ->create();
    }
}
