<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260323120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user email verification and token table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD verified TINYINT(1) NOT NULL DEFAULT 0');

        $this->addSql('CREATE TABLE email_verification_token (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(128) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', user_id INT NOT NULL, UNIQUE INDEX UNIQ_EMAIL_VERIFICATION_TOKEN (token), INDEX idx_email_verification_user_id (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE email_verification_token ADD CONSTRAINT FK_EMAIL_VERIFICATION_TOKEN_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_verification_token DROP FOREIGN KEY FK_EMAIL_VERIFICATION_TOKEN_USER');
        $this->addSql('DROP TABLE email_verification_token');
        $this->addSql('ALTER TABLE user DROP verified');
    }
}

