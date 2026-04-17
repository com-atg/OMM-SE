#!/bin/sh
set -e

# ── Laravel bootstrap ─────────────────────────────────────────────────────────
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ── Hand off to supervisord ───────────────────────────────────────────────────
exec "$@"
