#!/bin/sh
# Wait for database and run migrations with retry.
MAX_ATTEMPTS=20
ATTEMPT=1

while [ "$ATTEMPT" -le "$MAX_ATTEMPTS" ]; do
	if php /var/www/html/scripts/migrate.php --quiet; then
		echo "[startup] migrations ok"
		break
	fi

	echo "[startup] migration attempt $ATTEMPT/$MAX_ATTEMPTS failed; retrying in 3s"
	ATTEMPT=$((ATTEMPT + 1))
	sleep 3
done

if [ "$ATTEMPT" -gt "$MAX_ATTEMPTS" ]; then
	echo "[startup] migrations did not complete after retries; starting services anyway"
fi

# Start PHP-FPM in the background.
php-fpm -D

# Start nginx in the foreground (keeps container alive).
nginx -g 'daemon off;'
