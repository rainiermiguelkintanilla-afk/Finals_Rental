<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251015100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update payment foreign keys to cascade on apartment/tenant delete';
    }

    public function up(Schema $schema): void
    {
        // Drop existing FKs if they exist, then recreate with ON DELETE CASCADE
        // apartment_id FK
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D176DFE85');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D176DFE85 FOREIGN KEY (apartment_id) REFERENCES apartment (id) ON DELETE CASCADE');

        // tenant_id FK (if present on table)
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D9033212A');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D9033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Revert to default NO ACTION delete behavior
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D176DFE85');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D176DFE85 FOREIGN KEY (apartment_id) REFERENCES apartment (id)');

        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D9033212A');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D9033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id)');
    }
}


