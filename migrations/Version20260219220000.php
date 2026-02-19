<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260219220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create books table in PostgreSQL';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE books_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE books (
            id INT NOT NULL DEFAULT nextval(\'books_id_seq\'),
            title VARCHAR(255) NOT NULL,
            author VARCHAR(255) NOT NULL,
            isbn VARCHAR(20) DEFAULT NULL,
            published_date DATE DEFAULT NULL,
            active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4A1B2A92CC1CF4E6 ON books (isbn)');
        $this->addSql('CREATE INDEX idx_books_active ON books (active)');
        $this->addSql('CREATE INDEX idx_books_author ON books (author)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_4A1B2A92CC1CF4E6');
        $this->addSql('DROP INDEX idx_books_active');
        $this->addSql('DROP INDEX idx_books_author');
        $this->addSql('DROP TABLE books');
        $this->addSql('DROP SEQUENCE books_id_seq');
    }
}
