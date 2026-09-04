#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ACCOUNT_ROOT="$(dirname "$APP_ROOT")"
WEB_ROOT="$ACCOUNT_ROOT/public_html"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
BACKUP_ROOT="$ACCOUNT_ROOT/casherp-backups/main-domain-$STAMP"

if [[ "$APP_ROOT" != */app.casherp.com ]]; then
    echo "Refusing to run outside the app.casherp.com application directory." >&2
    exit 1
fi

if [[ ! -f "$APP_ROOT/artisan" || ! -f "$APP_ROOT/public/index.php" || ! -d "$WEB_ROOT" ]]; then
    echo "Expected CashERP application or public_html directory was not found." >&2
    exit 1
fi

mkdir -p "$BACKUP_ROOT"
tar -czf "$BACKUP_ROOT/public_html.tar.gz" -C "$ACCOUNT_ROOT" public_html
cp -a "$APP_ROOT/.env" "$BACKUP_ROOT/app.env"
cp -a "$APP_ROOT/.htaccess" "$BACKUP_ROOT/app.htaccess"

if command -v rsync >/dev/null 2>&1; then
    rsync -a "$APP_ROOT/public/" "$WEB_ROOT/"
else
    cp -a "$APP_ROOT/public/." "$WEB_ROOT/"
fi
cp "$APP_ROOT/deployment/main-domain/public_html.index.php" "$WEB_ROOT/index.php"
cp "$APP_ROOT/deployment/main-domain/public_html.htaccess" "$WEB_ROOT/.htaccess"
cp "$APP_ROOT/deployment/main-domain/app_legacy_redirect.htaccess" "$APP_ROOT/.htaccess"

php "$APP_ROOT/deployment/main-domain/set-domain-env.php"
cd "$APP_ROOT"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Backup: $BACKUP_ROOT"
echo "CashERP www-only application deployment completed."
echo "Application origin: https://www.casherp.com"
echo "app.casherp.com is redirect-only. After acceptance, remove its cPanel subdomain and DNS record without deleting APP_ROOT."
