# RiskSignal — Portfolio Risk Intelligence for Independent Financial Advisors

RiskSignal is a full-stack Laravel platform that lets Independent Financial Advisors (IFAs) upload client portfolios, automatically score their risk, and deliver professional PDF reports — for a single client or for an entire book of business in one upload.

It is built to remove the manual, spreadsheet-heavy work an advisor does today: parsing holdings, judging risk, writing up findings, and getting reports into clients' hands.

---

## Table of Contents

- [What RiskSignal Does](#what-risksignal-does)
- [Who It's For](#who-its-for)
- [Tech Stack](#tech-stack)
- [Quick Start](#quick-start)
- [Project Structure](#project-structure)
- [Core Features](#core-features)
- [Processing Pipeline](#processing-pipeline)
- [Database Schema](#database-schema)
- [Queue & Background Jobs](#queue--background-jobs)
- [API Routes](#api-routes)
- [Configuration](#configuration)
- [Deployment](#deployment)
- [Security](#security)
- [Troubleshooting](#troubleshooting)
- [Product Roadmap](#product-roadmap)
- [License](#license)

---

## What RiskSignal Does

1. **Ingests portfolios** — an advisor uploads a CSV, XLSX, XLS, or PDF. They can also upload a single ZIP containing one file per client.
2. **Analyzes risk automatically** — each holding is scored, and the portfolio gets an aggregate risk score with supporting metrics.
3. **Generates reports** — a professional PDF risk report is produced per client.
4. **Delivers results** — reports appear on the dashboard and can be emailed to the advisor (and, optionally, suppressed per the customer's preference). Multi-client uploads return a single bundled ZIP of all reports.
5. **Runs the business** — subscriptions and payments (Razorpay), lead intake, and an admin panel for customer and payment management.

---

## Who It's For

- **Independent Financial Advisors** managing multiple client portfolios who need fast, repeatable risk reporting.
- **Advisory firms** that want to process an entire client book from one upload and hand back a packaged set of reports.

---

## Tech Stack

| Layer          | Technology                                 |
| -------------- | ------------------------------------------ |
| Framework      | Laravel 12                                 |
| Language       | PHP 8.2                                    |
| Database       | MySQL                                      |
| Queue          | Database driver (Redis-ready)              |
| PDF generation | barryvdh/laravel-dompdf                    |
| Payments       | Razorpay                                   |
| Authentication | Laravel Breeze (session-based)             |
| Frontend       | Blade templates + Alpine.js + Tailwind CSS |
| Asset bundling | Vite                                       |
| HTTP client    | Axios                                      |

---

## Quick Start

### Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+
- MySQL
- Redis (optional — only if you switch the queue/cache driver to Redis)

### Installation

```bash
# 1. Clone the repository
git clone <repo-url>
cd portfolio-risk-ifa-v1

# 2. Install dependencies
composer install
npm install

# 3. Configure environment
cp .env.example .env
php artisan key:generate
# Edit .env: database credentials, mail, Razorpay keys

# 4. Run migrations
php artisan migrate

# 5. (Optional) seed sample data
php artisan db:seed
```

### Running Locally

You need three processes running together. Use three terminals:

```bash
# Terminal 1 — application server
php artisan serve

# Terminal 2 — queue worker (required for uploads, reports, email)
php artisan queue:work

# Terminal 3 — asset dev server
npm run dev
```

Then open `http://127.0.0.1:8000`.

> **Note:** uploads, risk scoring, PDF generation, and email all run on the queue. If the queue worker isn't running, files will stay stuck in the `pending` state.

---

## Project Structure

```
app/
  Http/Controllers/
    PortfolioUploadController.php     Handle uploads
    PortfolioController.php           Portfolio create/rename/delete
    FileController.php                Secure view/download of files & reports
    SubscriptionController.php        Subscription view & cancel
    PaymentController.php             Razorpay payment create/failure
    CheckoutController.php            Checkout & payment verification
    WebhookController.php             Razorpay server-to-server webhook
    User/DashboardController.php      Customer dashboard
    Admin/                            Admin panel controllers
  Jobs/
    ProcessPortfolioFile.php          Main pipeline: parse, score, report, email
  Mail/
    RiskReportMail.php                Per-report email delivery
  Services/RiskEngine/
    PortfolioParser.php               Parse CSV/XLSX/PDF into holdings
    AssetRiskScorer.php               Score each holding
    PortfolioRiskCalculator.php       Aggregate portfolio risk
  Models/
    Portfolio.php
    PortfolioFile.php
    PortfolioAsset.php
    RiskScore.php
    User.php
  DTOs/                               Upload data transfer objects
  Exceptions/                         Domain exceptions

database/migrations/                  Schema (MySQL)
resources/views/                      Blade templates
  user/dashboard.blade.php
  portfolio/upload.blade.php
  reports/risk-report.blade.php       PDF template
  emails/                             Mail templates
resources/js/                         Alpine.js entrypoint
resources/css/                        Tailwind styles
routes/web.php                        Web + admin routes
routes/auth.php                       Breeze auth routes
storage/app/portfolios/               Private file & report storage
```

---

## Core Features

### 1. Portfolio Upload & Processing

Advisors upload files at `/portfolio/upload`. Accepted formats: CSV, XLSX, XLS, PDF, and ZIP. Each upload is stored on a **private** disk and queued for processing.

### 2. Multi-Client ZIP Handling

An advisor can upload a single ZIP where each file represents one client. The system:

- Recursively scans the archive (handles files at the root or inside folders).
- Derives a client name from each filename (e.g. `ramesh_sharma.csv` → "Ramesh Sharma").
- Creates a **separate portfolio per client** and processes each independently.
- Handles edge cases: duplicate names are numbered, generic names fall back to `Client {n}`, and empty/corrupt files are skipped (not fatal).
- Packages every per-client PDF — plus a `_SUMMARY.txt` listing processed and skipped files — into **one bundle ZIP** named after the uploaded archive and date.

### 3. Risk Scoring Engine

Located in `app/Services/RiskEngine/`:

- **PortfolioParser** — normalizes holdings across CSV/XLSX/PDF.
- **AssetRiskScorer** — scores each holding and assigns a risk level.
- **PortfolioRiskCalculator** — computes the aggregate score, volatility, and drawdown, with supporting flags and a recommended next action.

### 4. PDF Report Generation

After scoring, a per-client PDF report is generated with DOMPDF and stored on the private disk. Reports include the overall risk score and level, volatility, drawdown, recommended action, and a breakdown of holdings with individual risk scores.

### 5. Email Delivery with Customer Opt-Out

Reports are emailed via a queued Mailable. A copy is always sent to the configured internal address; the customer copy is sent only if their `email_reports` preference is enabled (default on, toggleable from the profile page).

### 6. Subscriptions & Payments (Razorpay)

Plans: **Starter, Pro, Team**. Payments are created and verified through Razorpay, with a HMAC-verified server-to-server webhook. Users can view and cancel their subscription; access continues until the period ends.

### 7. Admin Panel

Role-gated under `/admin`:

- **Intakes** — manage IFA lead submissions and status.
- **Users** — view customers, send magic login links.
- **Payments** — review transactions.

---

## Processing Pipeline

```
Upload (CSV / XLSX / PDF / ZIP)
        │
        ▼
Stored on private 'portfolios' disk → PortfolioFile (status: pending)
        │
        ▼
ProcessPortfolioFile job (queued)
        │
        ├── ZIP?  → extract → one PortfolioFile per client → re-dispatch each
        │
        ▼
Parse holdings (PortfolioParser)
        │
        ▼
Score assets (AssetRiskScorer) → save PortfolioAsset rows
        │
        ▼
Aggregate (PortfolioRiskCalculator) → save RiskScore
        │
        ▼
Generate PDF report → save report_path
        │
        ▼
Queue email (RiskReportMail): always to internal address,
        and to customer if email_reports is on
        │
        ▼
status: processed   (or failed, with reason recorded in meta)
```

---

## Database Schema

Key tables (see `database/migrations/` for the authoritative schema):

**portfolio_files**

```
id, user_id, portfolio_id, original_name, stored_name, path,
report_path, mime_type, file_size,
status (pending | processing | processed | failed),
meta (json), processed_at, timestamps
```

**portfolios**

```
id, user_id, name, description, timestamps
```

**portfolio_assets**

```
id, portfolio_id, asset_type, symbol, name, isin,
quantity, buy_price, current_price, invested_value,
current_value, profit_loss, risk_score, risk_level, meta (json)
```

**risk_scores**

```
id, user_id, portfolio_id, score, volatility, drawdown,
generated_at, meta (json)
```

**users**

```
id, name, email, phone, role, is_admin,
email_reports, login_token, login_method,
onboarding_completed, last_login_at, timestamps
```

---

## Queue & Background Jobs

The application processes uploads asynchronously.

| Job                    | Purpose                                             | Timeout | Retries |
| ---------------------- | --------------------------------------------------- | ------- | ------- |
| `ProcessPortfolioFile` | Parse → score → report → email; also ZIP extraction | 300s    | 3       |
| Mail jobs              | Queued email delivery                               | —       | 3       |

**Development:**

```bash
php artisan queue:work
```

**Production:** run workers under Supervisor or systemd (see Deployment).

---

## API Routes

### User Routes (auth required)

| Method | Route                  | Purpose                 |
| ------ | ---------------------- | ----------------------- |
| GET    | `/dashboard`           | Customer dashboard      |
| GET    | `/portfolio/upload`    | Upload page             |
| POST   | `/portfolio/upload`    | Upload files            |
| DELETE | `/portfolio/file/{id}` | Delete an uploaded file |
| GET    | `/portfolios`          | List portfolios         |
| POST   | `/portfolios`          | Create portfolio        |
| PATCH  | `/portfolios/{id}`     | Rename portfolio        |
| DELETE | `/portfolios/{id}`     | Delete portfolio        |
| GET    | `/file/{id}`           | Download original file  |
| GET    | `/subscription`        | View subscription       |
| DELETE | `/subscription/cancel` | Cancel subscription     |
| GET    | `/profile`             | Edit profile            |
| PATCH  | `/profile`             | Update profile          |
| DELETE | `/profile`             | Delete account          |

### Public Routes

| Method | Route                 | Purpose                            |
| ------ | --------------------- | ---------------------------------- |
| GET    | `/`                   | Home                               |
| GET    | `/pricing`            | Pricing                            |
| POST   | `/ifa-submit`         | IFA lead submission                |
| GET    | `/checkout/{plan}`    | Checkout (starter/pro/team)        |
| POST   | `/payment/create`     | Initiate Razorpay payment          |
| POST   | `/payment/verify`     | Verify payment                     |
| POST   | `/payment/failure`    | Record client-side payment failure |
| POST   | `/webhook/razorpay`   | Razorpay webhook (HMAC verified)   |
| GET    | `/auto-login/{token}` | Magic-link login                   |

### Admin Routes (auth + admin)

| Method | Route                          | Purpose               |
| ------ | ------------------------------ | --------------------- |
| GET    | `/admin/dashboard`             | Admin dashboard       |
| GET    | `/admin/intakes`               | List leads            |
| GET    | `/admin/intakes/{id}`          | Lead detail           |
| POST   | `/admin/intakes/{id}/status`   | Update lead status    |
| GET    | `/admin/users`                 | List customers        |
| GET    | `/admin/users/{id}`            | Customer detail       |
| POST   | `/admin/users/{id}/login-link` | Send magic login link |
| GET    | `/admin/payments`              | List transactions     |

> Route names in `routes/web.php` are the source of truth; the table above reflects current routes and will drift if routes change.

---

## Deployment

### Server Prerequisites

- PHP 8.2 with: mbstring, xml, zip, gd, curl, pdo_mysql
- MySQL
- HTTPS / SSL (mandatory for payments)
- A process supervisor for the queue worker

### Steps

```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Then point the web server's document root at `public/`, configure the queue worker, and set the Razorpay webhook to `https://risksignal.in/webhook/razorpay`.

### Supervisor (Queue Worker)

```ini
[program:risksignal-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/risksignal/artisan queue:work --tries=3 --timeout=300
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/risksignal/storage/logs/queue.log
```

> On shared hosting (e.g. Hostinger) without Supervisor, schedule the queue via a cron-driven `queue:work --stop-when-empty` or use the platform's process manager.

---

## Security

- **Authentication** — Laravel Breeze, session-based, with CSRF protection.
- **Authorization** — admin routes gated by middleware; users can only access their own files and reports (ownership checks in `FileController`).
- **File storage** — private disk; files are never served by a public URL, only through authenticated controller routes.
- **Payments** — Razorpay webhook signatures verified via HMAC.
- **Rate limiting** — throttling on login, payment, and lead-submission endpoints.
- **SQL safety** — Eloquent's parameterized queries.
- **Secrets** — never commit `.env`; rotate `APP_KEY`, mail, and Razorpay credentials if they are ever exposed.

---

## Troubleshooting

**Files stuck in `pending` / not processing**

- Ensure a queue worker is running: `php artisan queue:work`
- Check the `failed_jobs` table and `storage/logs/laravel.log`

**Reports not generating**

- Confirm DOMPDF is installed: `composer show barryvdh/laravel-dompdf`
- Verify `storage/app/portfolios/` is writable

**Email not sending**

- Verify SMTP settings in `.env`
- Confirm the queue worker is running (email is queued)
- Quick test:
    ```bash
    php artisan tinker
    Mail::raw('SMTP test', fn($m) => $m->to('founder@risksignal.in')->subject('Test'));
    ```

**Razorpay webhook failing**

- Confirm the webhook URL is publicly reachable over HTTPS
- Check `RAZORPAY_WEBHOOK_SECRET` matches the Razorpay dashboard
- Review signature verification in `WebhookController`

**Route `[name]` not defined**

- Run `php artisan route:list` to see registered routes
- Clear caches: `php artisan route:clear && php artisan view:clear`

---

## Product Roadmap

The roadmap is staged so each phase ships independently. Earlier phases are intentionally simple to keep the product launchable.

### Phase 1 — Foundation (current)

- Single and multi-client (ZIP) uploads
- Rule-based risk scoring engine
- Per-client PDF reports + bundled ZIP
- Email delivery with customer opt-out
- Subscriptions, payments, admin panel

### Phase 2 — Plan Enforcement

- Per-plan client limits (hard limit, monthly reset)
- Clear messaging when an upload exceeds the plan allowance
- Usage tracking per advisor

### Phase 3 — AI Explanation Layer

- Plain-language, advisor-ready explanations of each portfolio's risk
- Recommended actions phrased for client conversations
- (Designed to start with a hosted language model and migrate to in-house models as data accumulates)

### Phase 4 — Market & News Intelligence

- Link holdings to relevant market events and company news
- Proactive warnings when an event is likely to affect a client's portfolio
- Guidance on whether to hold, watch, or act

### Phase 5 — Custom Risk Models

- In-house models for Indian and overseas equities and mutual funds
- Coverage across stocks, bonds, and funds, domestic and international
- Explainability built into model outputs

> Phases 3–5 depend on accumulating portfolio data and validated demand. They are deliberately deferred until the core product has paying users.

---

## License

Proprietary — all rights reserved. This code is not licensed for redistribution or reuse.

---

## Support

For issues or feature requests, contact the RiskSignal team.
