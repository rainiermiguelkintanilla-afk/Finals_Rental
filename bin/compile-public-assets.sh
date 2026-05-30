#!/usr/bin/env sh
set -eu

# Railway build has no MySQL — use SQLite so console commands do not fail on cache warmup.
if [ -z "${DATABASE_URL:-}" ] || echo "${DATABASE_URL}" | grep -q 'railway.internal'; then
  export DATABASE_URL='sqlite:////tmp/rainier-build.db?serverVersion=3&charset=utf8'
fi

# Download JavaScript vendor assets declared in importmap.php
php bin/console importmap:install --no-interaction

# Symfony asset mapper (hashed URLs under /assets/)
php bin/console asset-map:compile --env=prod --no-interaction

# Static paths used by templates (/fonts/, public/css/rentals.css → ../img/)
mkdir -p public/fonts public/img
cp -r assets/fonts/. public/fonts/
cp -r assets/img/. public/img/
