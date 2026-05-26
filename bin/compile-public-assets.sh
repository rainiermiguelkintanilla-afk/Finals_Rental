#!/usr/bin/env sh
set -eu

# Symfony asset mapper (hashed URLs under /assets/)
php bin/console asset-map:compile --env=prod --no-interaction

# Static paths used by templates (/fonts/, public/css/rentals.css → ../img/)
mkdir -p public/fonts public/img
cp -r assets/fonts/. public/fonts/
cp -r assets/img/. public/img/
