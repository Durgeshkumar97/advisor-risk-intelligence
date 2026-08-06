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
