#!/usr/bin/env bash
set -euo pipefail

# Render forwards inbound traffic to the port in $PORT (default 10000).
# Apache ships listening on 80, so rebind both ports.conf and our vhost.
PORT="${PORT:-10000}"

sed -i "s/^\(Listen \)80$/\1${PORT}/" /etc/apache2/ports.conf 2>/dev/null || true
sed -i "s/^\(Listen \)\[::\]:80$/\1[::]:${PORT}/" /etc/apache2/ports.conf 2>/dev/null || true
sed -i "s/__PORT__/${PORT}/g" /etc/apache2/sites-available/000-default.conf

# A Render persistent disk (if attached) mounts an empty backend/uploads owned
# by root, so make sure the web user can write to it before serving traffic.
UPLOADS="/var/www/html/backend/uploads"
if [ -d "$UPLOADS" ]; then
    mkdir -p "$UPLOADS/resources" "$UPLOADS/images" "$UPLOADS/documents" "$UPLOADS/products"
    chown -R www-data:www-data "$UPLOADS" 2>/dev/null || true
    chmod -R 775 "$UPLOADS" 2>/dev/null || true
fi

echo "AgriConnect starting on port ${PORT}"
exec "$@"
