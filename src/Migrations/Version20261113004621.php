<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * B2: sessions of the admin area (PdoSessionHandler). Table definition from PdoSessionHandler::configureSchema(),
 * picked up by make:migration through PdoSessionHandlerSchemaListener (symfony/doctrine-bridge).
 */
final class Version20261113004621 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sessions of the admin area (sessions)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sessions (sess_id VARCHAR(128) NOT NULL, sess_data BYTEA NOT NULL, sess_lifetime INT NOT NULL, sess_time INT NOT NULL, PRIMARY KEY (sess_id))');
        $this->addSql('CREATE INDEX sess_lifetime_idx ON sessions (sess_lifetime)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sessions');
    }
}
