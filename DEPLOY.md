# RiskSignal — Production Deployment Guide

Deployment checklist for RiskSignal as actually run in production: **Hostinger shared hosting via hPanel** — not a self-managed VPS. There is no root/sudo access, no user-editable Nginx config, and no process manager (Supervisor) available. Every step below reflects that constraint; where a step would normally be a system-level change on a VPS, the hPanel-managed equivalent is noted instead.

---

## 1. Hosting Environment

| Aspect | What's actually available |
|---|---|
| PHP | Version/extensions selected via hPanel, not `apt` — currently PHP 8.3 in production (see hPanel → PHP Configuration) |
| Database | MySQL, managed via hPanel |
| Web server / SSL / CDN | Fully managed by Hostinger (their `hcdn` layer + hPanel) — no direct Nginx/Apache config access |
| Process manager | **None.** No Supervisor, no persistent daemons — see §3 |
| Composer | Available via hPanel's SSH/terminal access or a compatible local Composer |
| Shell access | Limited SSH without sudo — confirmed no `sudo` during earlier debugging (couldn't reload PHP-FPM directly; had to trigger `opcache_reset()` via a web-request route instead) |

---

## 2. Application Setup

```bash
# Clone and install (path is your hPanel account's domain directory,
# not /var/www — check hPanel → File Manager / SSH for the exact path,
# typically something like ~/domains/risksignal.in/public_html or similar)
cd <your-hPanel-domain-directory>
git clone <repo> risksignal
cd risksignal

composer install --no-dev --optimize-autoloader

# Environment
cp .env.example .env        # or upload your .env directly
php artisan key:generate

# Migrate
php artisan migrate --force

# Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Storage
php artisan storage:link
chmod -R 775 storage bootstrap/cache
```

Note: `chown -R www-data:www-data` does not apply here — there's no `www-data` system user under your control on shared hosting; PHP runs as your own hosting account user, and file ownership is already correct without a manual `chown` step.

---

## 3. Queue Processing — no Supervisor available

There is no Supervisor (or any process manager) on this hosting tier, so a persistent `queue:work` daemon is not an option. Queue processing instead runs via the cron-scheduled `queue:work --stop-when-empty` entry — see §4's schedule table. It starts, drains whatever's queued, and exits every minute, which is the actual (and only) queue-processing mechanism in this environment.

---

## 4. Cron — Laravel Scheduler

One cron entry runs all scheduled commands — see the schedule overview below. Add it through **hPanel → Advanced → Cron Jobs** (not `sudo crontab -e -u www-data` — there's no sudo, and no `www-data` user to schedule under; the cron entry runs as your own hosting account).

```cron
* * * * * php /path/to/risksignal/artisan schedule:run >> /dev/null 2>&1
```

### Schedule overview

| Time | Command | Purpose |
|---|---|---|
| Every minute | `queue:work --stop-when-empty` | Process queued jobs (portfolio processing, email) |
| 00:05 daily | `subscriptions:expire` | Mark expired subscriptions |
| 02:00 daily | `users:purge` | Permanently delete users soft-deleted past the 30-day retention window |
| 03:00 daily | `backup:database` | Daily database backup |
| Every 30 min | Closure | Fail stale pending payments (>30 min old) |
| Hourly | `portfolio:cleanup-temp-dirs` | Remove orphaned ZIP-extraction temp directories left behind by a killed job |
| 08:00 daily | `risk:generate` | Calculate risk scores + send email signals |

---

## 5. Environment Variables Checklist

Open `.env` and confirm every variable is set:

```ini
# App
APP_ENV=production
APP_DEBUG=false
APP_URL=https://risksignal.in        # ← your real domain

# Database
DB_DATABASE=risksignal
DB_USERNAME=risksignal_user          # ← dedicated DB user (not root)
DB_PASSWORD=<strong-password>

# Queue (must be 'database')
QUEUE_CONNECTION=database

# Session
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true           # HTTPS-only cookie; config defaults to true
SESSION_LIFETIME=120                 # advisor idle timeout, minutes
SESSION_ADMIN_LIFETIME=15            # /admin idle timeout, enforced by AdminOnly

# Reports
REPORTS_NOTIFY_EMAIL=founder@risksignal.in

# Market risk sync (market-risk:sync reads this CSV)
MARKET_RISK_CSV_PATH=/full/path/to/nifty500_enriched.csv

# Mail (Hostinger SMTP — already configured)
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=587
MAIL_USERNAME=support@risksignal.in
MAIL_PASSWORD=<your-smtp-password>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=support@risksignal.in

# Razorpay (switch to LIVE keys for production)
RAZORPAY_KEY=rzp_live_XXXXXXXXXX
RAZORPAY_SECRET=<live-secret>
RAZORPAY_WEBHOOK_SECRET=<webhook-secret>

# Risk engine
RISK_MARKET_MULTIPLIER=1.05
```

---

## 6. Web Server / SSL — fully managed, no config access

There is no user-editable Nginx (or Apache) config on this hosting tier — the web server, TLS termination, and CDN layer (`hcdn`) are entirely managed by Hostinger through hPanel. The Nginx server-block config previously documented here (including `add_header X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`) was never actually deployable in this environment — confirmed by checking live response headers, which don't carry any of them. There's no `/etc/letsencrypt/`, no `certbot`, and no `php8.2-fpm.sock` to point a config at.

**SSL** is provisioned and auto-renewed by Hostinger automatically (currently a Let's Encrypt certificate, managed through hPanel — not via a locally-run `certbot`).

**Security headers** (`X-Frame-Options`, `X-Content-Type-Options`, etc.) still need to be added somehow, since there's no server-config layer to set them at. The two realistic options on this hosting tier: hPanel may expose a custom-headers setting (check the panel), or set them from within the app itself via Laravel middleware appended to every response. Neither has been implemented yet — this is a known gap, not something this correction pass fixes.

**Storage protection** (`storage/portfolios` must never be served publicly) is currently enforced by `FileController` requiring auth on every download — see the main `CLAUDE.md` Storage section — not by an Nginx `deny` rule, since there's no Nginx config to add one to.

---

## 7. Deploy Script (use after every code push)

`deploy.sh` already exists in the project root (added in commit `3ece3af`) — this is its actual current content:

```bash
#!/bin/bash
set -e
echo "── RiskSignal Deploy ────────────────────────────────"
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
echo "── Deploy complete ──────────────────────────────────"
```

No `supervisorctl restart` lines — there's no Supervisor to restart (see §3). Run it with:

```bash
./deploy.sh
```

---

## 8. Health Checks

```bash
# Scheduler working?
php artisan schedule:list

# Process queued jobs manually (test)
php artisan queue:work --once

# Check failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all

# Run risk generation manually
php artisan risk:generate
```

(No `sudo supervisorctl status` — there's no Supervisor on this hosting tier; queue health is really just "did `queue:failed` stay empty," since processing runs via the cron-scheduled `queue:work --stop-when-empty` from §3/§4.)

---

## 9. Post-Launch Checklist

- [ ] `APP_DEBUG=false` in production `.env`
- [ ] Razorpay **live** keys set (not `rzp_test_`)
- [ ] Razorpay webhook URL set to `https://risksignal.in/webhook/razorpay`
- [ ] SSL certificate active (Hostinger-managed auto-renewal — check hPanel, not `certbot`)
- [ ] Cron entry verified via hPanel → Advanced → Cron Jobs (not `sudo crontab -l -u www-data`)
- [ ] `php artisan queue:failed` is empty
- [ ] Test full flow: payment → subscription → portfolio upload → risk score → email

---

*Last updated: 2026-08-07 — corrected to match confirmed production reality (Hostinger shared hosting via hPanel, not a self-managed VPS).*
