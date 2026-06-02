<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260602191446 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE "transfer_logs_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "trucks_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE "transfer_logs" (id INT NOT NULL, sale_id INT DEFAULT NULL, performed_by_id INT DEFAULT NULL, action VARCHAR(50) NOT NULL, previous_values TEXT DEFAULT NULL, new_values TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C87AEA3E4A7E4868 ON "transfer_logs" (sale_id)');
        $this->addSql('CREATE INDEX IDX_C87AEA3E2E65C292 ON "transfer_logs" (performed_by_id)');
        $this->addSql('CREATE TABLE "trucks" (id INT NOT NULL, registration_number VARCHAR(50) NOT NULL, capacity INT NOT NULL, current_fuel INT NOT NULL, status VARCHAR(50) NOT NULL, location VARCHAR(255) DEFAULT NULL, last_lat DOUBLE PRECISION DEFAULT NULL, last_lng DOUBLE PRECISION DEFAULT NULL, driver_name VARCHAR(100) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE "transfer_logs" ADD CONSTRAINT FK_C87AEA3E4A7E4868 FOREIGN KEY (sale_id) REFERENCES "sales" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "transfer_logs" ADD CONSTRAINT FK_C87AEA3E2E65C292 FOREIGN KEY (performed_by_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sales ADD receipt_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE "transfer_logs_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE "trucks_id_seq" CASCADE');
        $this->addSql('ALTER TABLE "transfer_logs" DROP CONSTRAINT FK_C87AEA3E4A7E4868');
        $this->addSql('ALTER TABLE "transfer_logs" DROP CONSTRAINT FK_C87AEA3E2E65C292');
        $this->addSql('DROP TABLE "transfer_logs"');
        $this->addSql('DROP TABLE "trucks"');
        $this->addSql('ALTER TABLE "sales" DROP receipt_path');
    }
}
