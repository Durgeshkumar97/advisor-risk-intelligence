#!/bin/bash
set -e

echo "── RiskSignal Deploy ────────────────────────────────"

# Rollback point, captured BEFORE anything changes.
PREV_COMMIT=$(git rev-parse HEAD)
echo "Current commit: $PREV_COMMIT"

# Bring the site back up even if a step below fails, so a broken deploy never
# leaves the maintenance page pinned indefinitely. Which code is live at that
# point depends on where it failed — hence the explicit rollback hint rather
# than a claim about "old code".
#
# `|| true` matters: set -e applies inside the trap too, so without it a
# failing `artisan up` would abort the trap before the rollback hint printed —
# exactly the case where the operator most needs to read it.
trap 'php artisan up >/dev/null 2>&1 || true; echo "DEPLOY FAILED — attempted to bring the site back up. Verify it is serving, then roll back with: git checkout '"$PREV_COMMIT"' && php artisan migrate:rollback"' ERR

# Pre-migration backup. Deliberately blocking: migrate --force is irreversible
# in practice on shared hosting, and this is the only rollback that covers a
# destructive migration. If it fails, nothing has changed yet.
echo "── Backing up database ──────────────────────────────"
php artisan backup:database

php artisan down --retry=60 --refresh=15

git pull origin main
composer install --no-dev --optimize-autoloader

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan up

echo "── Deploy complete ──────────────────────────────────"
echo "Previous commit was: $PREV_COMMIT"
echo "To roll back: git checkout $PREV_COMMIT && php artisan migrate:rollback"
