#!/usr/bin/env bash
set -euo pipefail

DOMAIN="${DOMAIN:-https://app.navkwabuild.com}"
APP_DIR="${APP_DIR:-/var/www/navkwabuild/current}"
BACKEND_DIR="$APP_DIR/backend"

frontend_status="$(curl -sS -o /dev/null -w "%{http_code}" "$DOMAIN/")"
api_status="$(curl -sS -o /dev/null -w "%{http_code}" -H "Accept: application/json" "$DOMAIN/api/v1/auth/me")"

echo "Frontend: $frontend_status"
echo "Protected API: $api_status"

if [[ "$frontend_status" != "200" ]]; then
  echo "Frontend health check failed." >&2
  exit 1
fi

if [[ "$api_status" != "401" ]]; then
  echo "API health check expected 401 from protected auth/me endpoint." >&2
  exit 1
fi

cd "$BACKEND_DIR"
php artisan navkwabuild:production-check --strict

if command -v supervisorctl >/dev/null 2>&1; then
  supervisorctl status 'navkwabuild-worker:*'
fi

echo "Navkwa Build health check passed."
