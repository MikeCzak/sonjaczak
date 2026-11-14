<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * No signature on invoices (decision 2026-11-14): drops settings.signature. Down restores only the empty column, a
 * stored signature would be lost.
 */
final class Version20261114022327 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the signature from the settings (settings.signature)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE settings DROP signature');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE settings ADD signature BYTEA DEFAULT NULL');
    }
}
