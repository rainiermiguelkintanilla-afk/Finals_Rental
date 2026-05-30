<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Push tokens and user notification preferences';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE push_token (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, token VARCHAR(255) NOT NULL, platform VARCHAR(32) DEFAULT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_PUSH_TOKEN (token), INDEX IDX_979488AFA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE push_token ADD CONSTRAINT FK_979488AFA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD notify_email TINYINT(1) DEFAULT 1 NOT NULL, ADD notify_push TINYINT(1) DEFAULT 1 NOT NULL, ADD notify_payment_reminders TINYINT(1) DEFAULT 1 NOT NULL, ADD notify_maintenance TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE push_token DROP FOREIGN KEY FK_979488AFA76ED395');
        $this->addSql('DROP TABLE push_token');
        $this->addSql('ALTER TABLE user DROP notify_email, DROP notify_push, DROP notify_payment_reminders, DROP notify_maintenance');
    }
}
