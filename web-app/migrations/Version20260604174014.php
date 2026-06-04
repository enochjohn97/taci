<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260604174014 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fuel_entries ADD pump_number INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE fuel_entries ADD fuel_type VARCHAR(50) DEFAULT \'PMS\' NOT NULL');
        $this->addSql('ALTER TABLE fuel_entries ADD payment_method VARCHAR(50) DEFAULT \'Cash\' NOT NULL');
        $this->addSql('ALTER TABLE fuel_entries ADD vehicle_plate VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE fuel_entries ADD attendant VARCHAR(255) DEFAULT \'Unknown\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE "fuel_entries" DROP pump_number');
        $this->addSql('ALTER TABLE "fuel_entries" DROP fuel_type');
        $this->addSql('ALTER TABLE "fuel_entries" DROP payment_method');
        $this->addSql('ALTER TABLE "fuel_entries" DROP vehicle_plate');
        $this->addSql('ALTER TABLE "fuel_entries" DROP attendant');
    }
}
