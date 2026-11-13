<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * C6: settings, one row with id 1 (docs/planung/datenmodell.md 3.9). No row is inserted here: until Sonja saves the
 * settings, the code uses the defaults of App\Entity\Settings.
 */
final class Version20261113094819 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Settings (settings)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE settings (id INT NOT NULL, theme VARCHAR(20) NOT NULL, lesson_minutes SMALLINT NOT NULL, make_up_threshold SMALLINT NOT NULL, sender_name VARCHAR(100) DEFAULT NULL, sender_title VARCHAR(50) DEFAULT NULL, iban VARCHAR(34) DEFAULT NULL, bic VARCHAR(11) DEFAULT NULL, bank_name VARCHAR(100) DEFAULT NULL, invoice_place VARCHAR(100) DEFAULT NULL, vat_note TEXT DEFAULT NULL, signature BYTEA DEFAULT NULL, sender_address_street VARCHAR(255) DEFAULT NULL, sender_address_postal_code VARCHAR(10) DEFAULT NULL, sender_address_city VARCHAR(100) DEFAULT NULL, sender_address_country VARCHAR(60) DEFAULT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE settings');
    }
}
