# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:

- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).

---

## Commands

```bash
# Start all services concurrently (server, queue worker, log tailing, Vite)
composer dev

# Run tests (clears config cache first; uses in-memory SQLite)
composer test

# Run a single test file
php artisan test tests/Feature/IfaTrialLeadSubmissionTest.php

# Run a single test by name
php artisan test --filter=test_name

# Lint / fix code style (Laravel Pint)
./vendor/bin/pint

# Build frontend assets
npm run build

# Dev frontend (Vite HMR)
npm run dev

# Run migrations
php artisan migrate

# Full initial setup
composer setup
```

---

## Architecture

**RiskSignal** is a Laravel 12 / PHP 8.2 SaaS for Independent Financial Advisors (IFAs). It ingests portfolio files, scores risk, generates PDF reports, and manages subscriptions via Razorpay.

### Request → Processing Flow

1. **User uploads** a portfolio file (CSV, XLSX, XLS, PDF, or ZIP) via `PortfolioUploadController`.
2. `PortfolioUploadService` validates and stores the file on the `portfolios` disk (`storage/app/private/portfolios`), creating a `PortfolioFile` record with `status=pending`.
3. The `PortfolioFileUploaded` event fires, triggering `LogPortfolioFileUpload` listener, which dispatches the `ProcessPortfolioFile` job to the queue.
4. **`ProcessPortfolioFile` job** (the core worker):
    - Parses holdings with `RiskEngine/PortfolioParser`
    - Scores each asset with `RiskEngine/AssetRiskScorer`
    - Calculates composite portfolio risk with `RiskEngine/PortfolioRiskCalculator` (see formula below)
    - Wraps DB writes (assets + `RiskScore` + `PortfolioFile` status update) in a single transaction — PDF generation is also inside the transaction so a render failure rolls back everything
    - Emails the report after the transaction commits
    - ZIP uploads are extracted, each contained file becomes a new `PortfolioFile`, and a `Bus::batch()` processes them; `AssembleBundleZip` runs in the batch's `finally` callback

### Risk Engine Formula

`PortfolioRiskCalculator::calculate()` produces a 0–100 composite score:

| Factor        | Weight | Description                                                        |
| ------------- | ------ | ------------------------------------------------------------------ |
| Composition   | 30%    | Allocation-weighted avg of per-asset scores from `AssetRiskScorer` |
| Concentration | 25%    | Herfindahl-Hirschman Index (normalised)                            |
| Equity Ratio  | 25%    | % of portfolio in equity-class assets with score ≥ 25              |
| Drawdown      | 20%    | Unrealised loss mapped 0–30% loss → 0–100 score                    |

Final score is multiplied by `RISK_MARKET_MULTIPLIER` (env, default 1.05). Thresholds in `config/risk.php` control LOW/MEDIUM/HIGH labels (`RISK_LOW_THRESHOLD`, `RISK_HIGH_THRESHOLD`).

### Directory Map

```
app/
  Actions/          Single-use orchestrators (Intakes/, Payments/)
  DTOs/             Typed data transfer objects
  Enums/            PaymentStatus, SubscriptionStatus
  Events/           PaymentConfirmed, PortfolioFileUploaded
  Jobs/             ProcessPortfolioFile, AssembleBundleZip,
                    ProcessSuccessfulPayment, SendWhatsAppMessage
  Listeners/        ActivateSubscriptionListener, LogPortfolioFileUpload
  Http/
    Controllers/    Admin/, User/, auth/ sub-namespaces
    Middleware/     CheckSubscription ('paid'), EnsureActiveSubscription,
                    AdminOnly, Authenticate
  Models/           User, Subscription, Plan, Payment, Portfolio,
                    PortfolioFile, PortfolioAsset, RiskScore, ClientIntake, Lead
  Services/
    RiskEngine/     PortfolioParser, AssetRiskScorer, PortfolioRiskCalculator
    BillingService, RazorpayService, SubscriptionService,
    PortfolioUploadService, UserAccountRecoveryService
```

### Subscription & Auth Flow

- **Checkout** is unauthenticated: `CheckoutController` shows plan pages; `PaymentController` creates a Razorpay order; `CheckoutController::success()` verifies the HMAC signature, fires `PaymentConfirmed`, which triggers `ActivateSubscriptionListener`.
- Users are created by `CreateUserFromPaymentAction` during first-time checkout; returning users get their subscription extended.
- The `paid` middleware (`CheckSubscription`) is permissive: allows access during active, trial, or a 3-day grace period. `EnsureActiveSubscription` is the strict gate (active-only).
- Admin panel lives under `/admin`, gated by the `AdminOnly` middleware (separate from the regular `auth` guard).
- Magic-link auto-login is supported: admins can issue a `login_token` to a user, who hits `/auto-login/{token}` to authenticate without a password.

### Storage

- All portfolio files and generated PDF reports are stored on the `portfolios` disk (`storage/app/private/portfolios`). This disk is **never publicly served** — all downloads go through `FileController` which enforces auth.
- Report PDFs are generated with DOMPDF from the `resources/views/reports/risk-report.blade.php` template.

### Payments (Razorpay)

- `RazorpayService` wraps the Razorpay SDK for order creation and signature verification.
- Webhook endpoint at `/webhook/razorpay` (`WebhookController`) handles server-to-server payment confirmation with HMAC verification.
- Payment status lifecycle is managed via `PaymentStatus` enum.

### Testing

Tests use Pest 3, in-memory SQLite, and synchronous queues. The test env is configured in `phpunit.xml`.

## Model + Effort Routing Policy

Automatically decide the right model tier for EACH subtask before
starting it. Never default to the highest tier "to be safe." Never use Fable, under any circumstance, for this project. State which tier you're
using and why, in one line, before starting any non-trivial subtask.

**Haiku** — mechanical/high-volume work: reading files, grepping,
listing directories, simple lint fixes, running a command and reporting
raw output.

**Sonnet** — default for actual work, use unless there's a clear reason
to escalate: implementing an already-confirmed design, writing tests for
known behavior, routine bug fixes with a single clear cause, standard
refactors following an existing pattern, bounded investigation/reporting.

**Opus** — escalate only when Sonnet genuinely struggles, state the
reason: a Sonnet fix failed revert-and-confirm verification, tracing a
race condition or multi-file interaction with no obvious single cause,
a genuine design decision with real tradeoffs, security-sensitive logic
(auth/payments/PII) with real-consequence edge cases.

**Effort level** — set independently of model tier. Low/default for
Haiku and most Sonnet tasks. Higher effort only for Opus escalations or
Sonnet tasks with genuine multi-STEP reasoning (not just multi-file).
Never set high effort by default "just in case."

**Hard rules:**

1. Never use Fable, ever. If a task seems to need Fable-level capability,
   break it into smaller, better-scoped subtasks for Opus instead.
2. Never start on Opus "to be safe" — start at the tier the task shape
   calls for, escalate only with a concrete stated reason.
3. If unsure between two tiers, pick the cheaper one first.
4. At the end of multi-step work, briefly summarize which tiers were
   used and why.

## New Session Orientation

**Always run these first, before any work:**

```bash
cat today.md          # current open tasks and session state
git log --oneline -8  # recent commits
git status            # uncommitted changes
```

State what you see before asking what to do next.

---

## Production Infrastructure

- **Server:** Hostinger shared hosting
- **SSH:** `ssh -p 65002 u458948686@147.93.109.168`
- **Production path:** `~/domains/risksignal.in/project`
- **Deploy:** `./deploy.sh` from project root (see DEPLOY.md)
- **DNS:** Cloudflare — laura/theo.ns.cloudflare.com
- **Monitoring:** UptimeRobot on https://www.risksignal.in
- **Error tracking:** Sentry (live on both local and production)

---

## Completed Work — Do Not Re-Do

- Full security audit: complete (Critical/High/Medium all fixed)
- DNSSEC + Cloudflare migration: complete and stable
- Security headers (CSP, Referrer-Policy, Permissions-Policy): live in .htaccess
- Guardr score: A+ 96/100
- risk_service (Python/FastAPI ML microservice): engineering complete, not yet deployed — gated on business validation
