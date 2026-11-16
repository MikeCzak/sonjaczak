<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Planned number of lessons per enrollment (umsetzung-admin.md C7), copied from the course like the price.
 *
 * Doctrine's diff proposed ADD planned_lessons SMALLINT NOT NULL, which fails on a table with rows (no value for the
 * existing ones). So: add it nullable, copy the course's number, then set NOT NULL.
 */
final class Version20261116173612 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add enrollment.planned_lessons (copied from the course) and planned_lessons_reason';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enrollment ADD planned_lessons SMALLINT DEFAULT NULL');
        $this->addSql('UPDATE enrollment SET planned_lessons = course.planned_lessons FROM course WHERE course.id = enrollment.course_id');
        $this->addSql('ALTER TABLE enrollment ALTER planned_lessons SET NOT NULL');
        $this->addSql('ALTER TABLE enrollment ADD planned_lessons_reason VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enrollment DROP planned_lessons');
        $this->addSql('ALTER TABLE enrollment DROP planned_lessons_reason');
    }
}
