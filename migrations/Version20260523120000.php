<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link users to tenants and client rentals to users for customer API RBAC';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD tenant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D6499033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6499033212A ON user (tenant_id)');
        $this->addSql('ALTER TABLE client_rentals ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE client_rentals ADD CONSTRAINT FK_CLIENT_RENTALS_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_CLIENT_RENTALS_USER ON client_rentals (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_rentals DROP FOREIGN KEY FK_CLIENT_RENTALS_USER');
        $this->addSql('DROP INDEX IDX_CLIENT_RENTALS_USER ON client_rentals');
        $this->addSql('ALTER TABLE client_rentals DROP user_id');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D6499033212A');
        $this->addSql('DROP INDEX UNIQ_8D93D6499033212A ON user');
        $this->addSql('ALTER TABLE user DROP tenant_id');
    }
}
