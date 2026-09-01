# TindaPOS

A point of sale and QR ordering system for small Philippine shops — a café where
customers scan a code on the table and order ahead, or a sari-sari store running
a counter. One application serves many shops, and the wording, palette, currency
and menu change to match whichever shop you are standing in.

There are three surfaces:

- **The customer's phone** — a menu reached by scanning a code, a basket that
  survives being put in a pocket, and a status page that buzzes when the order
  is ready. No app, no account, no card details.
- **The shop** — a till, an order queue, inventory, sales history and reports,
  for owners, admins and cashiers.
- **The platform console** — for whoever runs the installation, to create client
  shops and step into one when something needs fixing.

---

## How an order actually flows

```
customer scans        staff see it            payment starts
the code       ──>    on the queue     ──>    preparation      ──>   ready
  /s/{shop}           within 5s              (becomes a sale)      (phone buzzes)
```

An order is **not** a sale. It becomes one at the moment it is paid for at the
counter — that is when stock moves and revenue is recorded. Until then it is a
request that can be rejected, cancelled by the customer, or left to expire.

```
placed ──> paid ──> ready ──> collected
  │          └──────────────> collected    (nothing to prepare)
  ├──> rejected     (staff refused it, with a reason)
  ├──> cancelled    (customer changed their mind, before paying)
  └──> expired      (nobody came to the till — swept every 5 minutes)
```

Each order gets a queue number that resets daily per shop, and an unguessable
token that is the customer's address for it. There is no login on the customer
side; the token is the authorisation.

---

## Features

### Customer ordering (QR)

- A menu at `/s/{shop-slug}`, reached by scanning a printed card
- Sections with a sticky rail that moves you through the menu rather than
  filtering it, plus a search box for when you already know what you want
- Sold-out items stay visible and marked, so a missing favourite reads as
  "gone for today" rather than "no longer made"
- Variants (sizes) and modifier groups (extras), priced as you choose them
- A basket kept on the phone, per shop, that survives being backgrounded
- Opening hours: outside them the menu still reads, but says when you can order
- A status page that polls, announces itself to screen readers, and vibrates
  and chimes the moment the order is ready
- An estimate — "about 6 minutes to go" — counted down while it is being made
- Cancel before paying
- Link previews carry the shop's own name and logo when a menu URL is shared

### Point of sale

- Search by name, SKU or barcode; category filters; keyboard shortcuts
- Variants and modifiers, discounts, and five payment methods
- Automatic change calculation, with a receipt that prints
- A cart that survives a refresh
- Lays out for a tablet as well as a desktop terminal

### Order queue

- Today's orders in three columns: awaiting payment, being made, ready
- Settling an order turns it into a sale and moves stock, in one transaction
- A chime for genuinely new arrivals — it watches for an arrival, not for a
  count going up, so an order settled and another placed in the same moment
  still sounds
- The unacknowledged count is shared with every screen, so a cashier on the
  till still sees an order arrive

### Inventory

- Products with cost and selling price, categories, images
- Variants and modifier groups
- Stock tracking with configurable low-stock thresholds, and restocking with
  an audit log
- Deactivating rather than deleting, so sales history stays intact

### Sales and reports

- Filterable sales history with a detail view, reprint, and CSV export
- Voiding with a mandatory reason and stock restoration (managers only)
- Daily, date-range and top-product reports
- Every range report compares against the same span of days before it

### Shops, staff and the platform

- Staff accounts managed in-app: three roles, **Owner**, **Admin**, **Cashier**
- Store settings: name, address, currency symbol, receipt footer, opening
  hours, preparation time, accent colour, logo, and the ordering switch
- Cost prices and profit never reach a cashier — the figures are not computed
  for them, rather than computed and hidden in the markup
- A platform console above tenancy for creating client shops and impersonating
  one, with a banner that never lets you forget whose till you are in

---

## Design system

The interface is built on tokens rather than utility values, and that is
load-bearing rather than decorative.

**Type is expressed as roles, not sizes.** `text-ui` means "the workhorse size
for a control", not 13px. Each of the eight roles — `label meta ui body title
heading figure hero` — carries two values: one for a counter terminal and one
for a phone. A single `data-density` attribute on `<html>` switches the whole
scale, so a customer reading a menu gets larger text than a cashier at a
terminal, from the same components. No component asks which side of the counter
it is on.

**Colour is semantic.** Screens reach for `surface` `ink` `line` `action`
`accent` `identity` and the reserved order-state trios `wait` `ready` `stop`,
never a raw ramp step. That is what makes a palette change one file rather than
an audit — and what makes the dark theme a block of values rather than a second
stylesheet.

**Three themes**: light, dark and follow-the-device, stamped before first paint
so a late shift never gets a white flash on the way into a dark till.

**Per-shop branding**: a café picks one hex colour and the whole accent palette
is derived from it — tint, hairline, and a readable text variant — then applied
to the customer pages only. A cashier working two shops sees the same till in
both.

All of this is enforced rather than remembered:

```bash
npm run lint:tokens
```

fails on a raw Tailwind size, a raw palette colour, `text-white`, an inert
shadow, or a removed alias, and prints the role to use instead. It runs in CI.

---

## Vocabulary

A café has a **Menu**; a sari-sari store has **Inventory**. The same screens
say different words depending on the shop's type, resolved server-side from the
store in context. Stepping into a client café switches the wording to theirs.

---

## Tech stack

| Layer | Technology |
|-------|-----------|
| **Backend** | Laravel 12, PHP 8.2+ |
| **Frontend** | Vue 3 (Composition API + `<script setup>`) |
| **Bridge** | Inertia.js v2 (SPA-like, no API needed) |
| **Styling** | Tailwind CSS 3 over a CSS custom property token layer |
| **Auth** | Laravel Breeze + Sanctum, session-based, username not email |
| **Build** | Vite 7 |
| **Database** | SQLite (default) — MySQL 8+ / PostgreSQL also supported |
| **Icons** | Heroicons (Vue) |

Multi-tenancy is enforced by a global Eloquent scope plus middleware, not by
remembering to add a `where` clause. Live updates are polling, not websockets —
iOS only delivers web push to home-screen installs, so a notification would
silently never arrive for a large share of customers.

---

## Requirements

- PHP 8.2+ with the **GD** extension (image resizing)
- Composer 2+
- Node.js 18+ / npm 9+
- SQLite (bundled with PHP) — or MySQL 8.0+ / PostgreSQL

---

## Installation

```bash
git clone https://github.com/Guanez/TindaPOS.git
cd TindaPOS

composer install
npm install

cp .env.example .env
php artisan key:generate

# SQLite works out of the box:
#   DB_CONNECTION=sqlite
#   DB_DATABASE=database/database.sqlite
#   (create it with: touch database/database.sqlite)

php artisan migrate --seed
php artisan storage:link      # images and logos 404 without this

npm run build                 # or: npm run dev

php artisan serve
```

Visit **http://localhost:8000** and sign in.

**Set `APP_URL` correctly before printing any QR code.** The code is built from
it, and a card generated against `localhost` prints and scans perfectly — it
just resolves to nothing on a customer's phone. That failure has no symptom
until someone is standing at a counter.

Expiring unpaid orders needs the scheduler running:

```bash
php artisan schedule:work     # or a real cron entry in production
```

---

## Default accounts

Sign in with the **username**, not an email address.

| Role | Username | Password |
|------|----------|----------|
| Owner | `owner` | `owner123` |
| Admin | `admin` | `owner123` |
| Cashier | `cashier` | `owner123` |

These are seeded for local development and are deliberately **not** shown on
the login screen. Change them before deploying anywhere.

---

## Testing

```bash
php artisan test          # feature and unit suites
npm run lint:tokens       # design system guard
```

The feature suite covers tenancy isolation, the order lifecycle, the queue,
public ordering, reachability of a printed QR, role-based data exposure,
branding, opening hours, vocabulary, density and currency.

---

## Project structure

```
app/
├── Enums/            # OrderStatus, UserRole
├── Exceptions/       # Domain exceptions, rendered to friendly page errors
├── Http/
│   ├── Controllers/  # Thin; business logic lives in services
│   ├── Middleware/    # Tenancy, roles, public store resolution, Inertia
│   ├── Requests/     # Form Request validation
│   └── Resources/    # Field visibility by audience (PublicMenuResource)
├── Models/
│   └── Concerns/     # BelongsToStore — the tenancy scope
├── Services/         # SaleService, OrderService, ReportService, AccentPalette
└── Support/          # StoreContext, OpeningHours, StoreVocabulary

resources/
├── css/app.css       # The token layer: every colour, size, radius, duration
└── js/
    ├── Components/   # Dialog, Receipt, StatCard, StockBadge…
    ├── Composables/  # currency, vocabulary, theme, queue alert
    ├── Layouts/      # AppLayout (shop), PlatformLayout (console)
    └── Pages/
        ├── Public/   # Menu, Status — the customer's phone
        ├── POS/ Orders/ Inventory/ Sales/ Reports/ Users/ Store/
        └── Platform/ # The landlord's console

scripts/check-tokens.mjs   # The design system guard
```

---

## Accessibility

- One dialog component for the whole app: focus goes in, stays in, and returns
  to the control that opened it
- Skip links, and the closed sidebar is `inert` rather than merely off-screen
- Every chart has a data-table equivalent
- The customer's status page announces itself through a live region
- Contrast is checked against WCAG AA — the lightest ink role is 5.59:1 on
  white, chosen over a lighter shade that measured 3.42:1 and failed

---

## Security

- **Tenancy**: a global scope plus middleware, so a query cannot forget it
- **Public pages**: the ordering routes are the only ones without `auth`, and
  the store is resolved from the URL by middleware that 404s a shop with
  ordering switched off
- **Order tokens**: unguessable, so one customer cannot walk another's order
- **Prices are never trusted from the client**: a basket arrives as ids and
  quantities, and every peso is recomputed from the shop's own menu
- **Data exposure**: cost-derived figures are not computed for roles that may
  not see them
- **Rate limiting** on checkout, void, order placement, cancellation and export
- **CSRF**, Form Request validation, Eloquent parameterisation throughout
- **Transactions** with `lockForUpdate()` on checkout, settle and void

---

## Production checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_URL` set to the public address — QR codes and images are built from it
- [ ] Change every seeded password
- [ ] Real database credentials, and daily backups
- [ ] `npm run build`
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] `php artisan storage:link`
- [ ] The scheduler running, or unpaid orders never expire
- [ ] HTTPS, and `SESSION_SECURE_COOKIE=true`
- [ ] `LOG_CHANNEL=daily` or an external service

---

## Not built

Tracked here rather than implied elsewhere in this README:

- [ ] Paying online — payment happens at the counter, by design, and changing
      that is a bigger decision than a feature
- [ ] Tax / VAT handling
- [ ] Scheduling an order for later, rather than as soon as possible
- [ ] Push notifications (see the note on polling above)
- [ ] Offline mode for a till on an unreliable connection
- [ ] A kitchen display separate from the counter queue
- [ ] Loyalty or customer accounts
- [ ] Multiple languages

---

## License

This project is open-sourced software. See [LICENSE](LICENSE) for details.
