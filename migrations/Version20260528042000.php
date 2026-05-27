<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528042000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-user customer order number';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` ADD customer_order_no INT DEFAULT NULL');

        // Backfill existing records with sequence starting from 1 per user.
        $this->addSql(
            'UPDATE `order` o
             JOIN (
                SELECT id, ROW_NUMBER() OVER (
                    PARTITION BY created_by_id
                    ORDER BY created_at ASC, id ASC
                ) AS seq
                FROM `order`
             ) ranked ON ranked.id = o.id
             SET o.customer_order_no = ranked.seq'
        );

        $this->addSql('ALTER TABLE `order` MODIFY customer_order_no INT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_order_customer_no_per_user ON `order` (created_by_id, customer_order_no)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_order_customer_no_per_user ON `order`');
        $this->addSql('ALTER TABLE `order` DROP customer_order_no');
    }
}

