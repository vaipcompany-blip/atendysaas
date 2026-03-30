#!/bin/sh
# Start PHP-FPM immediately.
php-fpm -D

# Run migrations in background so HTTP port becomes available quickly.
(
	MAX_ATTEMPTS=20
	ATTEMPT=1
	while [ "$ATTEMPT" -le "$MAX_ATTEMPTS" ]; do
		if php /var/www/html/scripts/migrate.php --quiet; then
			echo "[startup] migrations ok"
			exit 0
		fi

		echo "[startup] migration attempt $ATTEMPT/$MAX_ATTEMPTS failed; retrying in 3s"
		ATTEMPT=$((ATTEMPT + 1))
		sleep 3
	done

	echo "[startup] migrations did not complete after retries"
) &

# Keep container alive with nginx in foreground.
exec nginx -g 'daemon off;'
