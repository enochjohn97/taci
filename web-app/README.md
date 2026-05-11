# TACI Petroleum - Web App Documentation

## Project Overview

A complete fuel station management system built with PHP Symfony, PostgreSQL, and Bootstrap 5. Handles fuel quotas, inventory, POS, memos, loyalty programs, and reporting with role-based access control.

## 🏗️ Architecture

### Technology Stack

- **Backend**: PHP 8.1+ with Symfony 6+
- **Database**: PostgreSQL 12+
- **Frontend**: Twig templates, Bootstrap 5, JavaScript (vanilla)
- **APIs**: Paystack for secure payments
- **Real-time**: Mercure for WebSocket notifications and PWA support
- **PDF/Excel**: TCPDF/Dompdf, PhpSpreadsheet

## 📁 Project Structure

```
web-app/
├── public/
│   ├── index.php              # Symfony entry point
│   ├── css/
│   │   ├── bootstrap.css
│   │   └── app.css
│   └── js/
│       ├── bootstrap.min.js
│       └── app.js
├── src/
│   ├── Controller/            # HTTP Controllers
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── FuelController.php
│   │   ├── InventoryController.php
│   │   ├── SalesController.php
│   │   ├── MemoController.php
│   │   ├── ReportController.php
│   │   ├── LoyaltyController.php
│   │   ├── NotificationController.php
│   │   └── AdminController.php
│   ├── Entity/                # Doctrine ORM Entities
│   │   ├── User.php
│   │   ├── FuelEntry.php
│   │   ├── FuelQuotaReport.php
│   │   ├── Product.php
│   │   ├── InventoryLog.php
│   │   ├── Sale.php
│   │   ├── SaleItem.php
│   │   ├── Memo.php
│   │   ├── MemoRecipient.php
│   │   ├── MemoAttachment.php
│   │   ├── Notification.php
│   │   ├── LoyaltyPoint.php
│   │   ├── Payment.php
│   │   └── AuditLog.php
│   ├── Repository/            # Database queries
│   ├── Service/               # Business logic
│   │   ├── FuelQuotaService.php
│   │   ├── InventoryService.php
│   │   ├── SalesService.php
│   │   ├── ReportService.php
│   │   ├── PdfGeneratorService.php
│   │   ├── PaymentService.php
│   │   ├── NotificationService.php
│   │   └── LoyaltyService.php
│   ├── Form/                  # Symfony Forms
│   ├── Security/              # Authentication & Roles
│   ├── Twig/                  # Custom Twig Extensions
│   ├── EventListener/         # Event Handlers
│   └── Kernel.php
├── templates/                 # Twig templates
│   ├── base.html.twig
│   ├── auth/
│   │   ├── login.html.twig
│   │   ├── role-select.html.twig
│   │   └── password-reset.html.twig
│   ├── dashboard/
│   │   ├── dashboard.html.twig
│   │   ├── analytics.html.twig
│   │   └── activity-feed.html.twig
│   ├── fuel/
│   │   ├── quota.html.twig
│   │   ├── stock.html.twig
│   │   └── sales.html.twig
│   ├── inventory/
│   │   ├── stock-management.html.twig
│   │   ├── alerts.html.twig
│   │   └── reports.html.twig
│   ├── sales/
│   │   ├── pos.html.twig
│   │   ├── receptionist.html.twig
│   │   ├── checkout.html.twig
│   │   └── receipt.html.twig
│   ├── memos/
│   │   ├── create.html.twig
│   │   ├── inbox.html.twig
│   │   ├── sent.html.twig
│   │   ├── view.html.twig
│   │   └── reply.html.twig
│   ├── reports/
│   │   ├── sales.html.twig
│   │   ├── inventory.html.twig
│   │   ├── profit-loss.html.twig
│   │   ├── fuel-quota.html.twig
│   │   └── export.html.twig
│   ├── loyalty/
│   │   ├── dashboard.html.twig
│   │   ├── customers.html.twig
│   │   ├── redeem.html.twig
│   │   └── campaigns.html.twig
│   ├── notifications/
│   │   ├── inbox.html.twig
│   │   ├── settings.html.twig
│   │   └── history.html.twig
│   ├── store/
│   │   ├── receptionist.html.twig
│   │   ├── inventory-input.html.twig
│   │   └── stock-report.html.twig
│   ├── admin/
│   │   ├── users.html.twig
│   │   ├── settings.html.twig
│   │   └── audit-logs.html.twig
│   └── components/
│       ├── sidebar.html.twig
│       ├── navbar.html.twig
│       ├── alerts.html.twig
│       └── modals.html.twig
├── config/
│   ├── packages/
│   │   ├── security.yaml      # Authentication config
│   │   ├── doctrine.yaml      # Database config
│   │   ├── paystack.yaml      # Payment config
│   │   └── session.yaml       # Session timeout config
│   ├── services.yaml          # Service definitions
│   ├── routes.yaml            # Route definitions
│   └── bundles.php
├── migrations/                # Database migrations
│   └── Version*.php
├── tests/
│   ├── Unit/
│   ├── Functional/
│   └── Integration/
├── .env.example               # Environment template
├── composer.json              # PHP dependencies
├── symfony.lock               # Dependency lock file
├── docker-compose.yml         # Docker setup (optional)
└── README.md                  # This file
```

## 🔐 Role-Based Access Control (RBAC)

### Super Admin

- ✓ Full access to all modules
- ✓ Approve/decline memos, orders, transfers
- ✓ Set fuel prices
- ✓ User management
- ✓ System settings
- ✓ Audit logs

### Sub Admin

- ✓ Dashboard (read-only analytics)
- ✓ Fuel Management (input liters, view quota)
- ✓ Memos (send/receive)
- ✓ Reports (view only)
- ✓ Notifications

### Staff

- ✓ Store module (receptionist)
- ✓ Inventory input
- ✓ Memos (receive only)
- ✓ Notifications

## 🗄️ Database Schema

### Users Table

```sql
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'sub_admin', 'staff') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP
);
```

### Fuel Entries Table

```sql
CREATE TABLE fuel_entries (
    id SERIAL PRIMARY KEY,
    liter_quantity INTEGER NOT NULL,
    entered_by INT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Products Table

```sql
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    barcode VARCHAR(100) UNIQUE,
    unit_price DECIMAL(10, 2) NOT NULL,
    stock_qty INTEGER NOT NULL,
    reorder_level INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Sales Table

```sql
CREATE TABLE sales (
    id SERIAL PRIMARY KEY,
    cashier_id INT REFERENCES users(id),
    total_amount DECIMAL(12, 2) NOT NULL,
    payment_method VARCHAR(50), -- cash, card, transfer, ussd
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Memos Table

```sql
CREATE TABLE memos (
    id SERIAL PRIMARY KEY,
    sender_id INT REFERENCES users(id),
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('draft', 'sent', 'read', 'approved', 'declined') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Notifications Table

```sql
CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id),
    message TEXT NOT NULL,
    link VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 🚀 Getting Started

### Prerequisites

- PHP 8.1+
- PostgreSQL 12+
- Composer
- Node.js (for asset compilation - optional)

### Installation

1. **Clone/Setup Repository**

```bash
cd web-app
composer install
```

2. **Configure Environment**

```bash
cp .env.example .env
# Edit .env with your database credentials, API keys, etc.
```

3. **Database Setup**

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load  # (optional, for demo data)
```

4. **Create Admin User**

```bash
php bin/console app:create-user superadmin superadmin@123 super_admin
php bin/console app:create-user subadmin subadmin@123 sub_admin
php bin/console app:create-user staff staff@123 staff
```

5. **Run Development Server**

```bash
symfony server:start
# Or with Symfony CLI not installed:
php -S localhost:8000 -t public
```

6. **Access Application**

- Landing Page: http://localhost:8000/
- Web App: http://localhost:8000/web-app/login
- Role Selection: http://localhost:8000/web-app/role-select

### Login Credentials

- **Super Admin**: `superadmin` / `superadmin@123`
- **Sub Admin**: `subadmin` / `subadmin@123`
- **Staff**: `staff` / `staff@123`

## 📦 Key Features Implementation

### 1. Fuel Quota Engine

- Location: `src/Service/FuelQuotaService.php`
- Computes daily fuel allocation across configurable periods
- Factors in good/bad days with multipliers
- Generates PDF reports with projections

### 2. Store Module

- POS system with barcode scanning
- Real-time inventory deduction
- Receipt generation (PDF)
- Profit/loss calculation

### 3. Barcode Integration

- QuaggaJS for browser-based scanning
- Barcode generation using picqer library
- Barcode display on receipts and labels

### 4. Payment Integration

- Paystack API with full card, transfer, and USSD support
- Multiple payment methods (cash, card, USSD, bank transfers)
- Secure webhook verification
- Automatic payment status tracking

### 5. Memo System

- Rich text editor (Quill.js)
- File attachments (PDF, DOCX, XLSX, images)
- Memo threading/replies
- Super Admin approval workflow

### 6. Inventory Management

- Real-time stock tracking
- Low stock alerts with notifications
- Automatic deduction on sales
- Audit trail for all changes
- Barcode label printing

### 7. Real-time Notifications

- Mercure protocol for WebSocket updates
- Browser push notifications via Service Worker PWA
- In-app notification center
- Notification history and audit trail
- Offline-first design with background sync

### 8. Loyalty Program

- Points per liter/transaction
- Customer tiers (Bronze, Silver, Gold, Platinum)
- Tier-based discounts
- Automated tier upgrades
- Promotional campaigns

### 9. Reports & Analytics

- Sales reports (daily, weekly, monthly, yearly)
- Profit/loss analysis
- Fuel quota vs. actual sales comparison
- User activity logs
- Multi-format export (PDF, Excel, CSV, DOCX)

### 10. Security

- Symfony Security Bundle
- CSRF protection
- Bcrypt password hashing
- SQL injection prevention (Doctrine ORM)
- Rate limiting on login
- SSL/TLS enforcement
- Session timeout

## 🔧 Configuration

### Environment Variables (.env)

```
DATABASE_URL="postgresql://user:password@localhost:5432/taci_db"
PAYSTACK_PUBLIC_KEY="pk_live_your_key"
PAYSTACK_SECRET_KEY="sk_live_your_key"
MERCURE_URL="http://localhost:3000"
MERCURE_PUBLIC_URL="http://localhost:3000"
MERCURE_JWT_SECRET="your-256-bit-secret"
MAILER_DSN="smtp://user:password@smtp.gmail.com:587"
JWT_SECRET="your-256-bit-jwt-secret"
SESSION_TIMEOUT=3600
```

### Services Configuration

Edit `config/services.yaml` to register custom services:

```yaml
services:
  App\Service\FuelQuotaService:
    arguments:
      - '@Doctrine\ORM\EntityManagerInterface'
      - "@service_container"
```

## 📱 Frontend

### Bootstrap 5

- Pre-built responsive components
- Dark mode support (CSS variables)
- Mobile-first design

### JavaScript Utilities

- Form validation
- Barcode scanning (QuaggaJS)
- Chart.js for analytics
- DataTables for data management

### Custom Styling

- CSS variables for theming
- Dark mode toggle
- Responsive grid system
- Accessibility-first approach

## 🚢 Deployment

### Production Checklist

- [ ] Update `.env` with production credentials
- [ ] Set `APP_ENV=prod` and `APP_DEBUG=false`
- [ ] Run migrations: `php bin/console doctrine:migrations:migrate --env=prod`
- [ ] Clear cache: `php bin/console cache:clear --env=prod`
- [ ] Enable HTTPS/SSL
- [ ] Configure database backups
- [ ] Set up email for password resets
- [ ] Configure CDN for static assets (optional)

### Docker Deployment

```bash
docker-compose up -d
```

See `docker-compose.yml` for configuration.

## 🧪 Testing

Run tests:

```bash
php bin/phpunit
```

## 📚 API Documentation

### Authentication Endpoints

- `POST /api/login` — User login
- `POST /api/logout` — User logout
- `POST /api/refresh-token` — Refresh JWT token

### Fuel Management

- `GET /api/fuel/quota` — Get fuel quota data
- `POST /api/fuel/quota` — Create/update quota
- `GET /api/fuel/stock` — Get current stock

### Sales

- `POST /api/sales` — Create sale transaction
- `GET /api/sales/{id}` — Get sale details
- `POST /api/sales/{id}/receipt` — Generate receipt

### Memos

- `POST /api/memos` — Create memo
- `GET /api/memos` — Get user memos
- `POST /api/memos/{id}/approve` — Approve memo (Super Admin only)

### Reports

- `GET /api/reports/sales` — Sales report
- `GET /api/reports/inventory` — Inventory report
- `GET /api/reports/export` — Export to PDF/Excel

## 🤝 Contributing

Follow PSR-12 coding standards:

```bash
php vendor/bin/phpcs --standard=PSR12 src/
```

## 📄 License

© 2026 TACI Petroleum Company Limited. All Rights Reserved.

## 🔗 Related Documentation

- [Landing Page README](../landing-page/README.md)
- [Symfony Documentation](https://symfony.com/doc)
- [Doctrine ORM Documentation](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/)
- [PostgreSQL Documentation](https://www.postgresql.org/docs/)

---

**Built for Excellence.** ⚡
