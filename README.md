# TindaPOS

A modern, full-featured Point of Sale system built for Philippine **sari-sari stores** (neighborhood convenience shops). Designed for simplicity, speed, and real-world reliability.

---

## Features

### POS Terminal
- Fast product search by name, SKU, or barcode
- Category filter pills for quick navigation
- Real-time cart management with quantity controls
- Discount support and multiple payment methods (Cash, GCash, Maya, Card, Other)
- Automatic change calculation for cash payments
- Receipt view after each transaction
- Cart persistence across page refreshes (localStorage)

### Inventory Management
- Full CRUD for products with cost/selling price tracking
- Category-based organization
- Stock level monitoring with configurable low-stock thresholds
- Restock operations with audit logging
- Soft-delete (deactivate) products to preserve sales history

### Sales History
- Filterable sales list (date range, status, payment method)
- Sale detail modal with full item breakdown
- Sale voiding with mandatory reason and stock restoration
- Role-restricted void access (owner/admin only)

### Reports & Analytics
- **Daily Report**: Revenue, transactions, profit, discounts with hourly sales chart
- **Date Range Report**: Multi-day aggregate with daily breakdown table and payment method distribution
- **Top Products**: Ranked product performance by quantity sold

### Roles & Access Control
- Three roles: **Owner**, **Admin**, **Cashier**
- Role-based access control on routes and UI elements
- Cashiers see POS + Sales; Managers see Inventory, Reports, full Dashboard
- Cost price hidden from non-manager roles via API Resources
- Accounts are provisioned by seeder or tinker — there is no in-app user
  management screen yet (see [Roadmap](#roadmap))

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | Laravel 12, PHP 8.2+ |
| **Frontend** | Vue 3 (Composition API + `<script setup>`) |
| **Bridge** | Inertia.js v2 (SPA-like, no API needed) |
| **Styling** | Tailwind CSS 3 with custom design tokens |
| **Auth** | Laravel Breeze + Sanctum (session-based) |
| **Build** | Vite 7 |
| **Database** | SQLite (default) — MySQL 8+ / PostgreSQL also supported |
| **Icons** | Heroicons (Vue) |

---

## Requirements

- PHP 8.2+ with the **GD** extension (product image resizing)
- Composer 2+
- Node.js 18+ / npm 9+
- SQLite (bundled with PHP) — or MySQL 8.0+ / PostgreSQL if you prefer

---

## Installation

```bash
# 1. Clone the repository
git clone https://github.com/your-username/tindapos.git
cd tindapos

# 2. Install PHP dependencies
composer install

# 3. Install JS dependencies
npm install

# 4. Environment setup
cp .env.example .env
php artisan key:generate

# 5. Database — SQLite works out of the box:
#    DB_CONNECTION=sqlite
#    DB_DATABASE=database/database.sqlite
#    (create the file with: touch database/database.sqlite)
#
#    For MySQL instead, set DB_CONNECTION=mysql plus DB_DATABASE/USERNAME/PASSWORD.

# 6. Run migrations and seed demo data
php artisan migrate --seed

# 7. Link public storage (serves product images)
php artisan storage:link

# 8. Build frontend assets
npm run build       # Production
# OR
npm run dev         # Development (with HMR)

# 9. Start the server
php artisan serve
```

Visit **http://localhost:8000** and log in.

---

## Default Accounts

Sign in with the **username**, not the email address.

| Role | Username | Password |
|------|----------|----------|
| Owner | `owner` | `owner123` |
| Admin | `admin` | `owner123` |
| Cashier | `cashier` | `owner123` |

> **Change all default passwords before deploying to production.**

---

## Project Structure

```
app/
├── Enums/          # PHP 8.1 backed enums (UserRole)
├── Exceptions/     # Domain-specific exceptions
├── Http/
│   ├── Controllers/  # Thin controllers, service delegation
│   ├── Middleware/    # Auth, role-checking, Inertia
│   ├── Requests/     # Form Request validation classes
│   └── Resources/    # API Resources (role-based field visibility)
├── Models/         # Eloquent models with scopes & relationships
├── Providers/
└── Services/       # Business logic (SaleService, InventoryService, ReportService)

resources/js/
├── Components/     # Reusable Vue components (StatCard, StockBadge, etc.)
├── Composables/    # Shared logic (formatPeso, debounce, etc.)
├── Layouts/        # AppLayout with sidebar navigation
└── Pages/          # Inertia page components
    ├── Dashboard.vue
    ├── POS/
    ├── Sales/
    ├── Inventory/
    └── Reports/

database/
├── factories/      # Model factories for tests and demo data
├── migrations/     # Schema definitions
└── seeders/        # Demo data (users, categories, products)
```

---

## Security

- **CSRF Protection**: All mutations use Inertia's built-in CSRF token handling
- **Rate Limiting**: Checkout (30/min) and void (10/min) endpoints are throttled
- **Role-Based Access**: Middleware-enforced route protection for manager-only features
- **Data Exposure Control**: API Resources hide sensitive fields (cost_price) from non-managers
- **Input Validation**: Strict Form Request validation with bounds on all monetary/quantity fields
- **SQL Injection Prevention**: Eloquent ORM with parameterized queries throughout
- **Database Transactions**: Atomic checkout and void operations with `lockForUpdate()`
- **Custom Exceptions**: Domain-specific exception handling prevents information leakage

---

## Production Deployment Checklist

- [ ] Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`
- [ ] Change all default user passwords
- [ ] Set a strong `APP_KEY` (auto-generated via `php artisan key:generate`)
- [ ] Configure proper database credentials
- [ ] Run `npm run build` for optimized frontend assets
- [ ] Run `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] Run `php artisan storage:link` — product images 404 without it
- [ ] Set `APP_URL` to the public address; image and QR URLs are built from it
- [ ] Set up HTTPS (required for secure session cookies)
- [ ] Configure `SESSION_SECURE_COOKIE=true` in `.env`
- [ ] Set up database backups (daily recommended)
- [ ] Configure proper logging (`LOG_CHANNEL=daily` or external service)

---

## Roadmap

Not built yet — tracked here rather than implied elsewhere in this README:

- [ ] User management screen (create/deactivate cashiers, reset passwords)
- [ ] Configurable store settings (store name, receipt footer, tax rate)
- [ ] Receipt printing / PDF export
- [ ] CSV export for sales and reports
- [ ] Tablet-optimised POS layout
- [ ] Per-store branding on the customer menu (accent colour, logo)
- [ ] Menu sections with sticky headers, and a basket that survives a backgrounded phone

---

## License

This project is open-sourced software. See [LICENSE](LICENSE) for details.
