<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make-up rule (decision 2026-11-15): the minimum Sonja owes is always the course's planned lessons, so the
 * threshold (a distance from that number) is replaced by the default for new courses.
 *
 * Doctrine's diff proposed only the rename, because type and table match. The old value means something else
 * (threshold 0 would become 0 planned lessons), so it is replaced with the previous hard-coded default of 15.
 * Down restores threshold 0, which gives the same rule as now.
 */
final class Version20261115201517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace the make-up threshold with the default planned lessons (settings.planned_lessons)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE settings RENAME COLUMN make_up_threshold TO planned_lessons');
        $this->addSql('UPDATE settings SET planned_lessons = 15');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE settings RENAME COLUMN planned_lessons TO make_up_threshold');
        $this->addSql('UPDATE settings SET make_up_threshold = 0');
    }
}
