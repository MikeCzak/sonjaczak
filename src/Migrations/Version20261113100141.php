<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * D3: credit notes point to the invoice they correct (invoice.credits_id). Nullable: only credit notes have it.
 */
final class Version20261113100141 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Credit notes (invoice.credits_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice ADD credits_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_9065174423DEF1B2 FOREIGN KEY (credits_id) REFERENCES invoice (id)');
        $this->addSql('CREATE INDEX IDX_9065174423DEF1B2 ON invoice (credits_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice DROP CONSTRAINT FK_9065174423DEF1B2');
        $this->addSql('DROP INDEX IDX_9065174423DEF1B2');
        $this->addSql('ALTER TABLE invoice DROP credits_id');
    }
}
