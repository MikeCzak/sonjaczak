<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * B4: accounts can be deactivated. Existing accounts stay active (DEFAULT true).
 */
final class Version20261113091408 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Deactivatable accounts (app_user.active)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD active BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP active');
    }
}
