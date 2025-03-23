<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Automatycznie wygenerowana migracja dla tabeli subskrybentów newslettera
 */
final class Version20250323161500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tworzy tabelę dla subskrybentów newslettera';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE newsletter_subscribers (
            id SERIAL NOT NULL,
            email VARCHAR(180) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX unique_email ON newsletter_subscribers (email)');
        $this->addSql('COMMENT ON COLUMN newsletter_subscribers.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE newsletter_subscribers');
    }
}