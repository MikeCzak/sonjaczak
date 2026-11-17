<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Generated with doctrine:migrations:diff (umsetzung-admin.md F3b): an enrollment may lose its student when the
 * personal data is deleted and only the invoices are kept.
 */
final class Version20261117152927 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enrollment without student after the purge';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enrollment ALTER student_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Fails while enrollments without a student exist: they would have to be deleted first, with their invoices
        $this->addSql('ALTER TABLE enrollment ALTER student_id SET NOT NULL');
    }
}
