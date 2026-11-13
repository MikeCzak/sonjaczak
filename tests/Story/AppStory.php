<?php

declare(strict_types=1);

namespace App\Tests\Story;

use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/**
 * Invented data for the local database: `bin/console foundry:load-fixtures main`.
 * Never real people, see "Daten für Entwicklung und Tests" in docs/planung/datenmodell.md.
 */
#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    public function build(): void
    {
        // Filled with factories from step C1 on (docs/planung/umsetzung-admin.md)
    }
}
