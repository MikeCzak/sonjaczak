<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Generated with doctrine:migrations:diff, then extended by hand: existing rows get a value before NOT NULL applies
 * (umsetzung-admin.md F3a).
 */
final class Version20261117142646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stored file name of invoice PDFs, creation time of students';
    }

    public function up(Schema $schema): void
    {
        // Existing PDFs (production: none; locally the fixtures) get the name Invoice::getFileName() builds:
        // [[:alnum:]] matches umlauts with the database's ctype en_US.utf8, like \p{L}\p{N} in PHP
        $this->addSql('ALTER TABLE invoice_pdf ADD file_name VARCHAR(255) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE invoice_pdf p SET file_name = 'HN' || i.number || '_GU_'
                || CASE c.term WHEN 'winter' THEN 'WiSe' ELSE 'SoSe' END || '_' || c.year || '_'
                || btrim(regexp_replace(s.last_name, '[^[:alnum:]-]+', '-', 'g'), '-')
                || CASE WHEN i.credits_id IS NULL THEN '' ELSE '_Gutschrift' END || '.pdf'
            FROM invoice i
                JOIN enrollment e ON e.id = i.enrollment_id
                JOIN course c ON c.id = e.course_id
                JOIN student s ON s.id = e.student_id
            WHERE i.id = p.invoice_id
            SQL);
        $this->addSql('ALTER TABLE invoice_pdf ALTER file_name SET NOT NULL');

        // Existing students count as created now: their deadline starts with this migration at the earliest
        $this->addSql('ALTER TABLE student ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE student ALTER created_at DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice_pdf DROP file_name');
        $this->addSql('ALTER TABLE student DROP created_at');
    }
}
