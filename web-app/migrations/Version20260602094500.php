<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602094500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add payment method to transactions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE transactions ADD payment_method VARCHAR(30) DEFAULT 'cash' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transactions DROP payment_method');
    }
}
