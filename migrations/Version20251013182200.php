<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251013182200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE apartment (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL, bedrooms INT NOT NULL, bathrooms INT NOT NULL, rent_amount DOUBLE PRECISION NOT NULL, status VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, apartment_id INT NOT NULL, amount NUMERIC(10, 2) NOT NULL, payment_date DATE NOT NULL, due_date DATE NOT NULL, status VARCHAR(255) NOT NULL, payment_method VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, INDEX IDX_6D28840D9033212A (tenant_id), INDEX IDX_6D28840D176DFE85 (apartment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tenant (id INT AUTO_INCREMENT NOT NULL, apartment_id INT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(20) NOT NULL, move_in_date DATE NOT NULL, move_out_date DATE DEFAULT NULL, status VARCHAR(255) NOT NULL, INDEX IDX_4E59C462176DFE85 (apartment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D9033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D176DFE85 FOREIGN KEY (apartment_id) REFERENCES apartment (id)');
        $this->addSql('ALTER TABLE tenant ADD CONSTRAINT FK_4E59C462176DFE85 FOREIGN KEY (apartment_id) REFERENCES apartment (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D9033212A');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D176DFE85');
        $this->addSql('ALTER TABLE tenant DROP FOREIGN KEY FK_4E59C462176DFE85');
        $this->addSql('DROP TABLE apartment');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE tenant');
    }
}
