# Inventory Management System

A Laravel inventory management system for tracking products, categories, warehouses, suppliers, distributors, stock receipts, stock issues, warehouse transfers, inventory adjustments, reports, notifications, and audit history.

The app supports English and Arabic, including RTL layout, and includes deployment hardening such as authentication, role-based authorization, CSRF protection, secure session defaults, security headers, login throttling, report validation, and stock race-condition protection.

## Features

- Product catalog with SKU and barcode support
- Categories, warehouses, suppliers, and distributors
- Stock In receipts for receiving inventory
- Stock Out issues for distributing inventory
- Warehouse-to-warehouse transfers
- Inventory adjustments for damage, loss, theft, expiry, stocktake corrections, and other corrections
- Stock movement ledger as the source of truth
- Low-stock notifications
- Activity log for auditable model changes
- Filterable reports with PDF and CSV export
- Report charts and analysis panels
- English and Arabic UI

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js and npm
- MySQL or MariaDB for production
- SQLite can be used for local automated tests
- A web server such as Apache, Nginx, Laravel Herd, Laravel Valet, or `php artisan serve`

For production stock safety, use a database engine with row-level locks such as MySQL/MariaDB InnoDB. Stock-changing requests use database transactions and `lockForUpdate()` to prevent concurrent requests from spending the same stock twice.

## Installation

Clone the repository:

```bash
git clone https://github.com/seif03seif03/inventory-management-system.git
cd inventory-management-system
```

Install PHP dependencies:

```bash
composer install
```

Install frontend dependencies:

```bash
npm install
```

Create your environment file:

```bash
cp .env.example .env
```

Generate the Laravel application key:

```bash
php artisan key:generate
```

Build frontend assets:

```bash
npm run build
```

Run the app locally:

```bash
php artisan serve
```

Then open `http://127.0.0.1:8000`.

## Database Setup

Update `.env` with your database connection:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inventory
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

Create the database before running migrations:

```sql
CREATE DATABASE inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Run migrations:

```bash
php artisan migrate
```

Run migrations from scratch in local development:

```bash
php artisan migrate:fresh
```

## Seeder Instructions

Seed the database with roles, demo users, master data, and inventory activity:

```bash
php artisan db:seed
```

For a clean local demo database:

```bash
php artisan migrate:fresh --seed
```

The main `DatabaseSeeder` runs roles, users, categories, warehouses, suppliers, distributors, products, demo inventory reset, stock-in, stock-out, transfers, and adjustments.

Do not run demo seeders against production data unless you intentionally want demo records.

## Default Demo Accounts

All seeded demo accounts use this password:

```text
password
```

| Name | Email | Role | Notifications |
| --- | --- | --- | --- |
| Admin User | `admin@inventory.test` | Admin | Yes |
| Warehouse Manager | `manager@inventory.test` | Warehouse Manager | Yes |
| Warehouse Employee | `warehouse@inventory.test` | Warehouse Employee | No |
| Inventory Employee | `inventory@inventory.test` | Inventory Employee | Yes |
| Inventory Viewer | `viewer@inventory.test` | Viewer | No |

Change or remove seeded passwords before production use.

## Roles and Permissions

| Role | Access |
| --- | --- |
| Admin | Full system access, user management, activity logs, reports, master data, stock workflows, transfers, and adjustments |
| Warehouse Manager | Inventory operations, transfers, adjustments, reports, and operational pages; no user management |
| Warehouse Employee | Operational read access and standard inventory pages; restricted from sensitive transfer and adjustment writes |
| Inventory Employee | Inventory-oriented operational role seeded for workflow testing |
| Viewer | Read-oriented role seeded for demo and review use |

Server-side route middleware enforces sensitive permissions. UI visibility is not treated as security.

## Inventory Workflows

Create categories, products, warehouses, suppliers, and distributors before recording stock. Products can be deactivated when retired; records with stock history are protected from deletion so historical documents and reports remain accurate.

Use Stock In to receive goods from a supplier into one warehouse. A completed receipt creates a `stock_ins` document, child `stock_in_items`, and one IN `stock_movements` ledger row per item.

Use Stock Out when products leave a warehouse for a distributor. The system validates available stock on the server, groups duplicate product rows, and writes OUT movement rows only when all requested quantities are available.

Use Transfers to move products between two different warehouses. A completed transfer writes one OUT movement from the source warehouse and one IN movement to the destination warehouse for each item.

Use Adjustments to correct stock that no receipt, issue, or transfer explains. Each adjustment requires a reason and records the user who created it. Decreases cannot drive stock below zero.

Current stock is calculated from the ledger:

```text
current stock = SUM(IN quantity) - SUM(OUT quantity)
```

The application does not maintain a separate stock counter, which keeps reports, validation, and detail pages consistent.

## Reports

Reports are available for current stock, stock in, stock out, low stock, and stock movements.

Reports support filters, charts, analysis panels, CSV export, and PDF export. Export routes are authenticated and constrained to supported formats.

## Screenshots and Demo Information

Recommended screenshots for a GitHub release:

- Dashboard in English
- Dashboard in Arabic/RTL
- Product list with barcode column
- Stock In create form
- Stock Out create form
- Reports overview with charts
- Low-stock report
- Activity log

Place screenshots in `docs/screenshots/`.

Suggested README image links after adding screenshots:

```markdown
![Dashboard](docs/screenshots/dashboard.png)
![Reports](docs/screenshots/reports.png)
```

## Environment Variables

| Variable | Purpose | Production guidance |
| --- | --- | --- |
| `APP_NAME` | App display name | Set to your project name |
| `APP_ENV` | Runtime environment | `production` |
| `APP_KEY` | Laravel encryption key | Generate with `php artisan key:generate` |
| `APP_DEBUG` | Detailed error output | `false` |
| `APP_URL` | Public application URL | Use your HTTPS URL |
| `APP_LOCALE` | Default UI language | `en` or `ar` |
| `DB_CONNECTION` | Database driver | `mysql` recommended |
| `DB_HOST` | Database host | Your DB host |
| `DB_PORT` | Database port | Usually `3306` for MySQL |
| `DB_DATABASE` | Database name | Production database |
| `DB_USERNAME` | Database user | Dedicated least-privilege user |
| `DB_PASSWORD` | Database password | Strong secret, never committed |
| `SESSION_DRIVER` | Session backend | `database` |
| `SESSION_ENCRYPT` | Encrypt session payloads | `true` |
| `SESSION_SECURE_COOKIE` | HTTPS-only session cookies | `true` |
| `SESSION_HTTP_ONLY` | Block JavaScript cookie access | `true` |
| `SESSION_SAME_SITE` | CSRF cookie behavior | `strict` |
| `CACHE_STORE` | Cache backend | `database` or Redis |
| `QUEUE_CONNECTION` | Queue backend | `database` or Redis |
| `MAIL_*` | Email settings | Configure if notifications move to email |

Never commit `.env`. Use `.env.example` for safe placeholder configuration only.

## Security Notes

- Authentication is required for application routes.
- User management and activity logs are admin-only.
- Sensitive stock operations use server-side authorization.
- Forms use Laravel CSRF protection.
- Eloquent query binding is used for user input.
- Report filters are validated before querying.
- Login attempts are rate limited.
- Security headers are applied through middleware.
- Session cookies default to secure production settings.
- Stock-changing workflows use transactions and row locks.

## Development Commands

Run tests:

```bash
php artisan test
```

Validate Composer configuration:

```bash
composer validate --strict --no-check-publish
```

Run dependency audits:

```bash
composer audit --locked
npm audit --omit=dev
```

## Deployment Checklist

- Set `APP_ENV=production`
- Set `APP_DEBUG=false`
- Set `APP_URL` to the HTTPS production URL
- Generate a unique `APP_KEY`
- Configure a production MySQL/MariaDB database
- Run `composer install --no-dev --optimize-autoloader`
- Run `npm ci && npm run build`
- Run `php artisan migrate --force`
- Run `php artisan config:cache`
- Run `php artisan route:cache`
- Run `php artisan view:cache`
- Configure HTTPS at the web server or proxy
- Point the web server document root to `public/`
- Replace demo accounts and passwords

## Release

Current release target:

```text
v1.0.0
```

Use GitHub releases to attach screenshots, deployment notes, and any database migration warnings for operators.
