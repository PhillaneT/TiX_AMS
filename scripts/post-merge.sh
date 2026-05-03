#!/bin/bash
# Post-merge setup for the AjanaNova AMS Laravel app.
# Runs automatically after a project task is merged into main.
# Must be idempotent, non-interactive (stdin closed), and fast (<3 min).
set -e

APP_DIR="ams/ams"

if [ ! -f "$APP_DIR/artisan" ]; then
    echo "post-merge: $APP_DIR/artisan not found — skipping Laravel setup."
    exit 0
fi

cd "$APP_DIR"

# 1. Install PHP dependencies only when needed:
#    - vendor/ missing, OR
#    - composer.lock changed since last install (newer than vendor/installed.json)
need_composer=0
if [ ! -d vendor ]; then
    need_composer=1
elif [ -f composer.lock ] && [ composer.lock -nt vendor/composer/installed.json ]; then
    need_composer=1
fi

if [ "$need_composer" = "1" ]; then
    echo "post-merge: composer install (vendor missing or composer.lock newer)"
    composer install --no-interaction --prefer-dist --no-progress
else
    echo "post-merge: composer install skipped (vendor up to date)"
fi

# 2. Apply any new database migrations. Fail fast on real errors so a bad
#    schema does not silently ship.
echo "post-merge: php artisan migrate --force"
php artisan migrate --force

# 3. Ensure public storage symlink exists (idempotent with --force).
if [ -d storage/app/public ]; then
    echo "post-merge: php artisan storage:link"
    php artisan storage:link --force >/dev/null
fi

# 4. Refresh framework caches so view/route/config changes take effect.
echo "post-merge: clearing and rebuilding view/config/route caches"
php artisan view:clear   >/dev/null
php artisan config:clear >/dev/null
php artisan route:clear  >/dev/null
php artisan view:cache   >/dev/null
php artisan config:cache >/dev/null
php artisan route:cache  >/dev/null

echo "post-merge: done."
