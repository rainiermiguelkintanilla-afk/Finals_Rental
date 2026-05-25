<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251014055208 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE lease (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, apartment_id INT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, monthly_rent NUMERIC(10, 2) NOT NULL, status VARCHAR(255) NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_E6C774959033212A (tenant_id), INDEX IDX_E6C77495176DFE85 (apartment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE lease ADD CONSTRAINT FK_E6C774959033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id)');
        $this->addSql('ALTER TABLE lease ADD CONSTRAINT FK_E6C77495176DFE85 FOREIGN KEY (apartment_id) REFERENCES apartment (id)');
        $this->addSql('ALTER TABLE tenant DROP FOREIGN KEY FK_4E59C462176DFE85');
        $this->addSql('DROP INDEX IDX_4E59C462176DFE85 ON tenant');
        $this->addSql('ALTER TABLE tenant ADD address LONGTEXT DEFAULT NULL, ADD emergency_contact VARCHAR(20) DEFAULT NULL, ADD date_of_birth DATE DEFAULT NULL, DROP apartment_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lease DROP FOREIGN KEY FK_E6C774959033212A');
        $this->addSql('ALTER TABLE lease DROP FOREIGN KEY FK_E6C77495176DFE85');
        $this->addSql('DROP TABLE lease');
        $this->addSql('ALTER TABLE tenant ADD apartment_id INT NOT NULL, DROP address, DROP emergency_contact, DROP date_of_birth');
        $this->addSql('ALTER TABLE tenant ADD CONSTRAINT FK_4E59C462176DFE85 FOREIGN KEY (apartment_id) REFERENCES apartment (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_4E59C462176DFE85 ON tenant (apartment_id)');
    }
}
