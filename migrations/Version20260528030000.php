<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add payment fields to order';
    }

    public function up(Schema $schema): void
    {
        // Note: table name is `order`, which must be quoted.
        $this->addSql("ALTER TABLE `order` ADD payment_status VARCHAR(32) NOT NULL DEFAULT 'unpaid'");
        $this->addSql('ALTER TABLE `order` ADD payment_provider VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` ADD payment_session_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE `order` ADD paid_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` DROP payment_status');
        $this->addSql('ALTER TABLE `order` DROP payment_provider');
        $this->addSql('ALTER TABLE `order` DROP payment_session_id');
        $this->addSql('ALTER TABLE `order` DROP paid_at');
    }
}

