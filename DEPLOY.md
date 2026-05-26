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
cd /var/www
git clone <repo> risksignal
cd risksignal

composer install --no-dev --optimize-autoloader

cp .env.example .env        # or upload your .env directly
php artisan key:generate

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan storage:link
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

---

## 3. Queue Worker (Supervisor)

Supervisor keeps the queue worker running 24/7. Without it, jobs (portfolio processing, email, WhatsApp) won't execute.

### Install Supervisor

```bash
sudo apt install supervisor -y
```

### Create config file

Create `/etc/supervisor/conf.d/risksignal.conf`:

```ini
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

[program:risksignal-whatsapp]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/risksignal/artisan queue:work database --queue=whatsapp --sleep=5 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/risksignal/storage/logs/worker-whatsapp.log
stopwaitsecs=3600
```

### Activate

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start risksignal-default:*
sudo supervisorctl start risksignal-whatsapp:*

# Check status
sudo supervisorctl status
```

---

## 4. Cron — Laravel Scheduler

One cron entry runs all scheduled commands.

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
| 00:05 daily | `subscriptions:expire` | Mark expired subscriptions |
| Every 30 min | Closure | Fail stale pending payments (>30 min old) |
| 08:00 daily | `risk:generate` | Calculate risk scores + send email signals |
| 16:30 daily | `whatsapp:signal` | Send WhatsApp risk signal messages |

---

## 5. Environment Variables Checklist

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://risksignal.in

DB_DATABASE=risksignal
DB_USERNAME=risksignal_user
DB_PASSWORD=<strong-password>

QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=587
MAIL_USERNAME=support@risksignal.in
MAIL_PASSWORD=<your-smtp-password>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=support@risksignal.in

RAZORPAY_KEY=rzp_live_XXXXXXXXXX
RAZORPAY_SECRET=<live-secret>
RAZORPAY_WEBHOOK_SECRET=<webhook-secret>

WHATSAPP_TOKEN=<system-user-token>
WHATSAPP_PHONE_NUMBER_ID=<phone-number-id>
WHATSAPP_TEST_MODE=false

RISK_MARKET_MULTIPLIER=1.05
```

---

## 6. WhatsApp Setup (Meta Cloud API)

### Step-by-step

1. Create [business.facebook.com](https://business.facebook.com) account
2. Create app at [developers.facebook.com](https://developers.facebook.com) → Add **WhatsApp** product
3. Get **Phone Number ID** from WhatsApp → API Setup → `WHATSAPP_PHONE_NUMBER_ID`
4. Create **System User token** with `whatsapp_business_messaging` permission → `WHATSAPP_TOKEN`
5. Test: `php artisan tinker` → `app(\App\Services\WhatsApp\WhatsAppService::class)->sendText('9876543210', 'Hello')`

### Register Message Templates

Meta Business Manager → WhatsApp → Message Templates → Create Template

**Template 1: `daily_risk_signal`**
- Category: **Utility**
- Language: **en_IN**
- Body:
```
Hi {{1}}, your RiskSignal daily report is ready 📊

*Risk Score:* {{2}}/100 — *{{3}} Risk*

*Today's Action:* {{4}}

View your report: {{5}}
```

**Template 2: `welcome_risksignal`**
- Category: **Utility**
- Language: **en_IN**
- Body:
```
Welcome to RiskSignal, {{1}}! 🎉

Your *{{2}}* subscription is now active.

You'll receive:
• 📧 Daily email signal at 8:00 AM
• 📱 WhatsApp alert at 4:30 PM

Upload your first portfolio: {{3}}
```

---

## 7. Post-Launch Checklist

- [ ] `APP_DEBUG=false` in production `.env`
- [ ] `WHATSAPP_TEST_MODE=false` after templates approved
- [ ] Razorpay **live** keys set
- [ ] SSL certificate active
- [ ] Supervisor running
- [ ] Cron entry verified
- [ ] Both WhatsApp templates approved
- [ ] `php artisan queue:failed` is empty
- [ ] Full flow tested: payment → subscription → portfolio upload → email & WhatsApp signals

---

*Updated: 2026-05-26*
