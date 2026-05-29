<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add mobile push notification tokens';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE push_token (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            token VARCHAR(512) NOT NULL,
            platform VARCHAR(32) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_PUSH_TOKEN_USER_ID (user_id),
            UNIQUE INDEX uniq_push_token_token (token),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE push_token ADD CONSTRAINT FK_PUSH_TOKEN_USER_ID FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE push_token DROP FOREIGN KEY FK_PUSH_TOKEN_USER_ID');
        $this->addSql('DROP TABLE push_token');
    }
}
