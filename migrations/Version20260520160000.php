<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add API token fields to user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD api_token_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD api_token_created_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP api_token_hash');
        $this->addSql('ALTER TABLE user DROP api_token_created_at');
    }
}

