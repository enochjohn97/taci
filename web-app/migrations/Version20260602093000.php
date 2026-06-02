<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create transactions table for staff POS payments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE transactions_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE transactions (id INT NOT NULL, staff_user_id INT NOT NULL, items JSON NOT NULL, subtotal DOUBLE PRECISION NOT NULL, tax DOUBLE PRECISION NOT NULL, discount DOUBLE PRECISION NOT NULL, total DOUBLE PRECISION NOT NULL, status VARCHAR(50) DEFAULT \'pending\' NOT NULL, reference VARCHAR(255) DEFAULT NULL, receipt_url VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_transactions_staff_user ON transactions (staff_user_id)');
        $this->addSql('CREATE INDEX idx_transactions_status ON transactions (status)');
        $this->addSql('CREATE INDEX idx_transactions_reference ON transactions (reference)');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_55AC3A8A7B2D0AE6 FOREIGN KEY (staff_user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE transactions_id_seq CASCADE');
        $this->addSql('ALTER TABLE transactions DROP CONSTRAINT FK_55AC3A8A7B2D0AE6');
        $this->addSql('DROP TABLE transactions');
    }
}
