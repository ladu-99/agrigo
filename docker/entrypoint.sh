#!/usr/bin/env bash
set -euo pipefail

# Use the hosting environment's assigned port, or 10000 by default.
# Apache ships listening on 80, so rebind both ports.conf and our vhost.
PORT="${PORT:-10000}"

sed -i "s/^\(Listen \)80$/\1${PORT}/" /etc/apache2/ports.conf 2>/dev/null || true
sed -i "s/^\(Listen \)\[::\]:80$/\1[::]:${PORT}/" /etc/apache2/ports.conf 2>/dev/null || true
sed -i "s/__PORT__/${PORT}/g" /etc/apache2/sites-available/000-default.conf

# Ensure the web user can write to uploaded files before serving traffic.
UPLOADS="/var/www/html/backend/uploads"
if [ -d "$UPLOADS" ]; then
    mkdir -p "$UPLOADS/resources" "$UPLOADS/images" "$UPLOADS/documents" "$UPLOADS/products"
    chown -R www-data:www-data "$UPLOADS" 2>/dev/null || true
    chmod -R 775 "$UPLOADS" 2>/dev/null || true
fi

echo "AgriConnect starting on port ${PORT}"
exec "$@"
