<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261111143430 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE customer ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD is_active TINYINT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE product ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04ADB03A8386 FOREIGN KEY (created_by_id) REFERENCES customer (id)');
        $this->addSql('CREATE INDEX IDX_D34A04ADB03A8386 ON product (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE customer DROP created_at, DROP is_active');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADB03A8386');
        $this->addSql('DROP INDEX IDX_D34A04ADB03A8386 ON product');
        $this->addSql('ALTER TABLE product DROP created_by_id');
    }
}
