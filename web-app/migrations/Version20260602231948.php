<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260602231948 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE "leave_requests_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "shift_handovers_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "shifts_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "tasks_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE "leave_requests" (id INT NOT NULL, staff_id INT NOT NULL, type VARCHAR(100) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, reason VARCHAR(255) NOT NULL, notes TEXT DEFAULT NULL, status VARCHAR(50) DEFAULT \'Pending\' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_45ADFEF2D4D57CD ON "leave_requests" (staff_id)');
        $this->addSql('CREATE TABLE "shift_handovers" (id INT NOT NULL, sender_id INT NOT NULL, message TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4BA2134FF624B39D ON "shift_handovers" (sender_id)');
        $this->addSql('CREATE TABLE "shifts" (id INT NOT NULL, name VARCHAR(100) NOT NULL, start_time TIME(0) WITHOUT TIME ZONE NOT NULL, end_time TIME(0) WITHOUT TIME ZONE NOT NULL, is_active BOOLEAN DEFAULT false NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE "tasks" (id INT NOT NULL, label VARCHAR(255) NOT NULL, is_done BOOLEAN DEFAULT false NOT NULL, type VARCHAR(50) DEFAULT \'task\' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE "leave_requests" ADD CONSTRAINT FK_45ADFEF2D4D57CD FOREIGN KEY (staff_id) REFERENCES "users" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "shift_handovers" ADD CONSTRAINT FK_4BA2134FF624B39D FOREIGN KEY (sender_id) REFERENCES "users" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE "leave_requests_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE "shift_handovers_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE "shifts_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE "tasks_id_seq" CASCADE');
        $this->addSql('ALTER TABLE "leave_requests" DROP CONSTRAINT FK_45ADFEF2D4D57CD');
        $this->addSql('ALTER TABLE "shift_handovers" DROP CONSTRAINT FK_4BA2134FF624B39D');
        $this->addSql('DROP TABLE "leave_requests"');
        $this->addSql('DROP TABLE "shift_handovers"');
        $this->addSql('DROP TABLE "shifts"');
        $this->addSql('DROP TABLE "tasks"');
        $this->addSql('ALTER TABLE "transactions" DROP payment_method');
    }
}
