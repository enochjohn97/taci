<?php
// migrations/Version20260407000000.php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create database schema';
    }

    public function up(Schema $schema): void
    {
        // Create users table
        $this->addSql('CREATE TABLE "users" (
            id SERIAL PRIMARY KEY,
            username VARCHAR(255) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT \'active\',
            last_login TIMESTAMP NULL,
            dark_mode_enabled BOOLEAN NOT NULL DEFAULT false,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $this->addSql('CREATE INDEX idx_username ON "users" (username)');
        $this->addSql('CREATE INDEX idx_email ON "users" (email)');

        // Create fuel_entries table
        $this->addSql('CREATE TABLE fuel_entries (
            id SERIAL PRIMARY KEY,
            liter_quantity FLOAT NOT NULL,
            unit_price FLOAT,
            entered_by INTEGER NOT NULL REFERENCES "users"(id) ON DELETE CASCADE,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        // Create products table
        $this->addSql('CREATE TABLE products (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            category VARCHAR(100) NOT NULL,
            barcode VARCHAR(100) NOT NULL UNIQUE,
            unit_price FLOAT NOT NULL,
            stock_quantity INTEGER NOT NULL,
            reorder_level INTEGER NOT NULL,
            cost_price FLOAT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $this->addSql('CREATE INDEX idx_barcode ON products (barcode)');

        // Create sales table
        $this->addSql('CREATE TABLE sales (
            id SERIAL PRIMARY KEY,
            cashier_id INTEGER NOT NULL REFERENCES "users"(id) ON DELETE CASCADE,
            total_amount FLOAT NOT NULL,
            discount_amount FLOAT NOT NULL DEFAULT 0,
            loyalty_points_used FLOAT NOT NULL DEFAULT 0,
            payment_method VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT \'completed\',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $this->addSql('CREATE INDEX idx_cashier ON sales (cashier_id)');
        $this->addSql('CREATE INDEX idx_created_at ON sales (created_at)');

        // Create sale_items table
        $this->addSql('CREATE TABLE sale_items (
            id SERIAL PRIMARY KEY,
            sale_id INTEGER NOT NULL REFERENCES sales(id) ON DELETE CASCADE,
            product_id INTEGER NOT NULL REFERENCES products(id),
            quantity INTEGER NOT NULL,
            unit_price FLOAT NOT NULL,
            subtotal FLOAT NOT NULL
        )');

        // Create inventory_logs table
        $this->addSql('CREATE TABLE inventory_logs (
            id SERIAL PRIMARY KEY,
            product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
            action_type VARCHAR(50) NOT NULL,
            quantity_changed INTEGER NOT NULL,
            stock_before INTEGER NOT NULL,
            stock_after INTEGER NOT NULL,
            performed_by INTEGER NOT NULL REFERENCES "users"(id) ON DELETE CASCADE,
            reference VARCHAR(255),
            notes TEXT,
            timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $this->addSql('CREATE INDEX idx_product_id ON inventory_logs (product_id)');
        $this->addSql('CREATE INDEX idx_performed_by ON inventory_logs (performed_by)');

        // Create notifications table
        $this->addSql('CREATE TABLE notifications (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES "users"(id) ON DELETE CASCADE,
            type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            link VARCHAR(255),
            is_read BOOLEAN NOT NULL DEFAULT false,
            read_at TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $this->addSql('CREATE INDEX idx_user_id ON notifications (user_id)');
        $this->addSql('CREATE INDEX idx_is_read ON notifications (is_read)');

        // Create memos table
        $this->addSql('CREATE TABLE memos (
            id SERIAL PRIMARY KEY,
            sender_id INTEGER NOT NULL REFERENCES "users"(id) ON DELETE CASCADE,
            subject VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT \'draft\',
            approval_notes TEXT,
            parent_memo_id INTEGER REFERENCES memos(id) ON DELETE SET NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $this->addSql('CREATE INDEX idx_sender ON memos (sender_id)');

        // Create memo_recipients table
        $this->addSql('CREATE TABLE memo_recipients (
            id SERIAL PRIMARY KEY,
            memo_id INTEGER NOT NULL REFERENCES memos(id) ON DELETE CASCADE,
            recipient_id INTEGER REFERENCES "users"(id) ON DELETE SET NULL,
            recipient_role VARCHAR(50),
            is_read BOOLEAN NOT NULL DEFAULT false,
            read_at TIMESTAMP
        )');

        // Create memo_attachments table
        $this->addSql('CREATE TABLE memo_attachments (
            id SERIAL PRIMARY KEY,
            memo_id INTEGER NOT NULL REFERENCES memos(id) ON DELETE CASCADE,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_type VARCHAR(50) NOT NULL,
            file_size INTEGER NOT NULL,
            uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        // Create payments table
        $this->addSql('CREATE TABLE payments (
            id SERIAL PRIMARY KEY,
            sale_id INTEGER NOT NULL REFERENCES sales(id) ON DELETE CASCADE,
            method VARCHAR(50) NOT NULL,
            amount FLOAT NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT \'pending\',
            reference VARCHAR(255),
            transaction_id VARCHAR(255),
            gateway_response TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP
        )');
        $this->addSql('CREATE INDEX idx_sale_id ON payments (sale_id)');

        // Create loyalty_points table
        $this->addSql('CREATE TABLE loyalty_points (
            id SERIAL PRIMARY KEY,
            customer_id INTEGER NOT NULL REFERENCES "users"(id) ON DELETE CASCADE,
            points_balance FLOAT NOT NULL DEFAULT 0,
            total_points_earned FLOAT NOT NULL DEFAULT 0,
            total_points_redeemed FLOAT NOT NULL DEFAULT 0,
            tier VARCHAR(50) NOT NULL DEFAULT \'bronze\',
            total_spend FLOAT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $this->addSql('CREATE INDEX idx_customer_id ON loyalty_points (customer_id)');

        // Create fuel_quota_reports table
        $this->addSql('CREATE TABLE fuel_quota_reports (
            id SERIAL PRIMARY KEY,
            fuel_entry_id INTEGER NOT NULL REFERENCES fuel_entries(id) ON DELETE CASCADE,
            day_type VARCHAR(50) NOT NULL,
            days_in_period INTEGER NOT NULL,
            daily_quota FLOAT NOT NULL,
            projected_revenue FLOAT NOT NULL,
            projected_cogs FLOAT NOT NULL,
            projected_profit FLOAT NOT NULL,
            profit_margin_percentage FLOAT NOT NULL,
            pdf_path VARCHAR(255) NOT NULL,
            excel_path VARCHAR(255),
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        // Create audit_logs table
        $this->addSql('CREATE TABLE audit_logs (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES "users"(id) ON DELETE CASCADE,
            action VARCHAR(255) NOT NULL,
            module VARCHAR(100) NOT NULL,
            description TEXT,
            old_values TEXT,
            new_values TEXT,
            ip_address VARCHAR(50),
            user_agent VARCHAR(500),
            timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $this->addSql('CREATE INDEX idx_user_id_audit ON audit_logs (user_id)');
        $this->addSql('CREATE INDEX idx_module ON audit_logs (module)');
        $this->addSql('CREATE INDEX idx_timestamp ON audit_logs (timestamp)');

        // Create login_attempts table
        $this->addSql('CREATE TABLE login_attempts (
            id SERIAL PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            ip_address VARCHAR(50) NOT NULL,
            successful BOOLEAN NOT NULL,
            attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $this->addSql('CREATE INDEX idx_username_login ON login_attempts (username)');
        $this->addSql('CREATE INDEX idx_ip_address ON login_attempts (ip_address)');
        $this->addSql('CREATE INDEX idx_attempted_at ON login_attempts (attempted_at)');
    }

    public function down(Schema $schema): void
    {
        // Drop tables in reverse order of creation
        $this->addSql('DROP TABLE IF EXISTS login_attempts');
        $this->addSql('DROP TABLE IF EXISTS audit_logs');
        $this->addSql('DROP TABLE IF EXISTS fuel_quota_reports');
        $this->addSql('DROP TABLE IF EXISTS loyalty_points');
        $this->addSql('DROP TABLE IF EXISTS payments');
        $this->addSql('DROP TABLE IF EXISTS memo_attachments');
        $this->addSql('DROP TABLE IF EXISTS memo_recipients');
        $this->addSql('DROP TABLE IF EXISTS memos');
        $this->addSql('DROP TABLE IF EXISTS notifications');
        $this->addSql('DROP TABLE IF EXISTS inventory_logs');
        $this->addSql('DROP TABLE IF EXISTS sale_items');
        $this->addSql('DROP TABLE IF EXISTS sales');
        $this->addSql('DROP TABLE IF EXISTS products');
        $this->addSql('DROP TABLE IF EXISTS fuel_entries');
        $this->addSql('DROP TABLE IF EXISTS "users"');
    }
}
