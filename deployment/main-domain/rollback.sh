#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
    echo "Usage: rollback.sh /home/ACCOUNT/casherp-backups/main-domain-TIMESTAMP" >&2
    exit 1
fi

BACKUP_ROOT="$(cd "$1" && pwd)"
ACCOUNT_ROOT="$(dirname "$(dirname "$BACKUP_ROOT")")"
APP_ROOT="$ACCOUNT_ROOT/app.casherp.com"
WEB_ROOT="$ACCOUNT_ROOT/public_html"

if [[ "$BACKUP_ROOT" != "$ACCOUNT_ROOT"/casherp-backups/main-domain-* ]]; then
    echo "Refusing to use an unexpected backup path." >&2
    exit 1
fi

if [[ ! -f "$BACKUP_ROOT/public_html.tar.gz" || ! -f "$BACKUP_ROOT/app.env" || ! -f "$BACKUP_ROOT/app.htaccess" ]]; then
    echo "The selected backup is incomplete." >&2
    exit 1
fi

RESTORE_HOLD="$ACCOUNT_ROOT/public_html-before-rollback-$(date -u +%Y%m%dT%H%M%SZ)"
mv "$WEB_ROOT" "$RESTORE_HOLD"
tar -xzf "$BACKUP_ROOT/public_html.tar.gz" -C "$ACCOUNT_ROOT"
cp "$BACKUP_ROOT/app.env" "$APP_ROOT/.env"
cp "$BACKUP_ROOT/app.htaccess" "$APP_ROOT/.htaccess"

cd "$APP_ROOT"
php artisan optimize:clear
php artisan config:cache
php artisan view:cache

echo "Rollback completed. The replaced public_html is preserved at: $RESTORE_HOLD"
