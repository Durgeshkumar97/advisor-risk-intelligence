# RiskSignal — Production Deployment Guide

Complete checklist for deploying RiskSignal to a Linux VPS (Ubuntu/Debian).

---

## 1. Server Requirements

| Requirement | Minimum | Notes |
|---|---|---|
| PHP | 8.2+ | `php8.2-cli php8.2-fpm php8.2-mysql php8.2-zip php8.2-xml php8.2-curl php8.2-mbstring` |
| MySQL | 8.0+ | or MariaDB 10.6+ |
| Nginx | any | with PHP-FPM |
| Supervisor | any | for queue workers |
| Composer | 2.x | |

---

## 2. Application Setup

```bash
# Clone and install
cd /var/www
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
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

---

## 3. Queue Worker (Supervisor)

Supervisor keeps the queue worker running 24/7. Without it, jobs (portfolio processing, email) won't execute.

### Install Supervisor

```bash
sudo apt install supervisor -y
```

### Create config file

Create `/etc/supervisor/conf.d/risksignal.conf`:

```ini
; ─── Default queue (email, portfolio processing) ─────────────────────────────
[program:risksignal-default]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/risksignal/artisan queue:work database --queue=default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/risksignal/storage/logs/worker-default.log
stopwaitsecs=3600
```

### Activate

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start risksignal-default:*

# Check status
sudo supervisorctl status
```

### After code deploy (restart workers)

```bash
sudo supervisorctl restart risksignal-default:*
```

---

## 4. Cron — Laravel Scheduler

One cron entry runs all scheduled commands — see the schedule overview below.

```bash
sudo crontab -e -u www-data
```

Add this single line:

```cron
* * * * * php /var/www/risksignal/artisan schedule:run >> /dev/null 2>&1
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

# WhatsApp (see § 6 below)
WHATSAPP_TOKEN=<system-user-token>
WHATSAPP_PHONE_NUMBER_ID=<phone-number-id>
WHATSAPP_TEST_MODE=false             # ← false in production

# Risk engine
RISK_MARKET_MULTIPLIER=1.05
```

---

## 6. WhatsApp Setup (Meta Cloud API) — DEPRECATED — not in use

### Step-by-step

1. **Create Meta Business Manager** at [business.facebook.com](https://business.facebook.com)
2. **Create an App** at [developers.facebook.com](https://developers.facebook.com)
   - App type: **Business**
   - Add product: **WhatsApp**
3. **Add a WhatsApp Business Account (WABA)**
   - Go to WhatsApp → API Setup
   - Note your **Phone Number ID** (numeric, e.g. `102345678901234`) → `WHATSAPP_PHONE_NUMBER_ID`
4. **Create a System User token** (never expires)
   - Meta Business Manager → Settings → System Users → Add System User (Admin)
   - Generate token → select permissions: `whatsapp_business_messaging`, `whatsapp_business_management`
   - Copy token → `WHATSAPP_TOKEN`
5. **Test the connection** (free — your phone)
   ```bash
   php artisan tinker
   app(\App\Services\WhatsApp\WhatsAppService::class)->sendText('9876543210', 'Hello from RiskSignal!');
   ```

### Register Message Templates

Go to **Meta Business Manager → WhatsApp → Message Templates → Create Template**

---

#### Template 1: `daily_risk_signal`

| Field | Value |
|---|---|
| Name | `daily_risk_signal` |
| Category | **Utility** |
| Language | English (India) — `en_IN` |

**Header** (optional): `📊 Daily Risk Signal`

**Body:**
```
Hi {{1}}, your RiskSignal daily report is ready 📊

*Risk Score:* {{2}}/100 — *{{3}} Risk*

*Today's Action:* {{4}}

View your full report and client scripts here:
{{5}}
```

**Variables:**
| # | Value |
|---|---|
| {{1}} | User's full name |
| {{2}} | Risk score (0–100) |
| {{3}} | Risk level (LOW / MEDIUM / HIGH) |
| {{4}} | Next action recommendation |
| {{5}} | Dashboard URL |

---

#### Template 2: `welcome_risksignal`

| Field | Value |
|---|---|
| Name | `welcome_risksignal` |
| Category | **Utility** |
| Language | English (India) — `en_IN` |

**Body:**
```
Welcome to RiskSignal, {{1}}! 🎉

Your *{{2}}* subscription is now active.

You'll receive:
• 📧 Daily email risk signal at 8:00 AM
• 📱 WhatsApp alert at 4:30 PM

Upload your first portfolio file to get started:
{{3}}
```

**Variables:**
| # | Value |
|---|---|
| {{1}} | User's full name |
| {{2}} | Plan name (e.g. "Professional") |
| {{3}} | Dashboard URL |

---

### Template Approval Timeline

- Meta typically approves templates within **24–48 hours**
- Status: **Pending → Approved / Rejected**
- Monitor at: Meta Business Manager → WhatsApp → Message Templates
- If rejected, tweak the body to be less promotional and resubmit

### While Waiting for Approval

Set `WHATSAPP_TEST_MODE=true` in `.env` — messages will be logged to `storage/logs/laravel.log` instead of sending. Your app works fully; WhatsApp just logs.

---

## 7. Nginx Config

```nginx
server {
    listen 80;
    server_name risksignal.in www.risksignal.in;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name risksignal.in www.risksignal.in;

    root /var/www/risksignal/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/risksignal.in/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/risksignal.in/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    client_max_body_size 10M;   # portfolio file uploads

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Never serve the portfolios storage disk publicly
    location ~* ^/storage/portfolios {
        deny all;
    }
}
```

### SSL (Let's Encrypt)

```bash
sudo apt install certbot python3-certbot-nginx -y
sudo certbot --nginx -d risksignal.in -d www.risksignal.in
```

---

## 8. Deploy Script (use after every code push)

Save as `deploy.sh` in the project root:

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

sudo supervisorctl restart risksignal-default:*
sudo supervisorctl restart risksignal-whatsapp:*

echo "── Deploy complete ──────────────────────────────────"
```

```bash
chmod +x deploy.sh
./deploy.sh
```

---

## 9. Health Checks

```bash
# Scheduler working?
php artisan schedule:list

# Queue workers running?
sudo supervisorctl status

# Process queued jobs manually (test)
php artisan queue:work --once

# Check failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all

# Test WhatsApp (in test mode)
php artisan tinker
>>> app(\App\Services\WhatsApp\WhatsAppService::class)->sendText('9876543210', 'Test from RiskSignal');

# Run risk generation manually
php artisan risk:generate

# Run WhatsApp signal manually
php artisan whatsapp:signal --dry-run
```

---

## 10. Post-Launch Checklist

- [ ] `APP_DEBUG=false` in production `.env`
- [ ] `WHATSAPP_TEST_MODE=false` after templates approved
- [ ] Razorpay **live** keys set (not `rzp_test_`)
- [ ] Razorpay webhook URL set to `https://risksignal.in/webhook/razorpay`
- [ ] SSL certificate active (certbot auto-renew)
- [ ] Supervisor running (`sudo supervisorctl status`)
- [ ] Cron entry verified (`sudo crontab -l -u www-data`)
- [ ] Both WhatsApp templates approved in Meta Business Manager
- [ ] `php artisan queue:failed` is empty
- [ ] Test full flow: payment → subscription → portfolio upload → risk score → email → WhatsApp

---

*Last updated: 2026-05-23*
