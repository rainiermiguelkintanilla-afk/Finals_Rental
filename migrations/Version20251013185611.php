<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251013185611 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(500) NOT NULL, address VARCHAR(255) NOT NULL, price NUMERIC(10, 2) NOT NULL, image VARCHAR(255) DEFAULT NULL, total_properties INT NOT NULL, total_sqft INT NOT NULL, team_size INT NOT NULL, status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE sales_report (id INT AUTO_INCREMENT NOT NULL, apartment_id INT DEFAULT NULL, tenant_id INT DEFAULT NULL, sales_by VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, sales_type VARCHAR(50) NOT NULL, price NUMERIC(10, 2) NOT NULL, status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_E6DB258C176DFE85 (apartment_id), INDEX IDX_E6DB258C9033212A (tenant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE sales_report ADD CONSTRAINT FK_E6DB258C176DFE85 FOREIGN KEY (apartment_id) REFERENCES apartment (id)');
        $this->addSql('ALTER TABLE sales_report ADD CONSTRAINT FK_E6DB258C9033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sales_report DROP FOREIGN KEY FK_E6DB258C176DFE85');
        $this->addSql('ALTER TABLE sales_report DROP FOREIGN KEY FK_E6DB258C9033212A');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE sales_report');
    }
}
