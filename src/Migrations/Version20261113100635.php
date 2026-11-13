<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * D4: deadline of the last reminder per invoice (invoice.reminded_for), so that each deadline is reported once.
 */
final class Version20261113100635 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reminder bookkeeping (invoice.reminded_for)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice ADD reminded_for DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice DROP reminded_for');
    }
}
