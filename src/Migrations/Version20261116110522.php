<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lesson status (decision 2026-11-16, umsetzung-admin.md C4 question 5): "planned" and "held" are no longer stored.
 * A lesson that exists is planned, "held" follows from the time. Only who cancelled is stored, NULL = not cancelled.
 *
 * Doctrine's diff proposed ADD cancelled_by + DROP status, which would lose every cancellation. So: add the new
 * column, copy the cancellations, then drop the old one.
 *
 * Down cannot tell held from planned any more: everything not cancelled becomes "planned". The column gets a
 * default first, otherwise ADD ... NOT NULL fails on a table with rows; the default is dropped again because the
 * old mapping had none.
 */
final class Version20261116110522 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace lesson.status with lesson.cancelled_by (NULL = not cancelled)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lesson ADD cancelled_by VARCHAR(10) DEFAULT NULL');
        $this->addSql("UPDATE lesson SET cancelled_by = 'student' WHERE status = 'cancelled_by_student'");
        $this->addSql("UPDATE lesson SET cancelled_by = 'teacher' WHERE status = 'cancelled_by_teacher'");
        $this->addSql('ALTER TABLE lesson DROP status');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE lesson ADD status VARCHAR(30) DEFAULT 'planned' NOT NULL");
        $this->addSql("UPDATE lesson SET status = 'cancelled_by_student' WHERE cancelled_by = 'student'");
        $this->addSql("UPDATE lesson SET status = 'cancelled_by_teacher' WHERE cancelled_by = 'teacher'");
        $this->addSql('ALTER TABLE lesson ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE lesson DROP cancelled_by');
    }
}
