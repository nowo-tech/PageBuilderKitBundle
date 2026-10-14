#!/bin/sh
set -e

# FRANKENPHP_MODE: classic | worker (REQ-DEMO-010). Default: worker.
MODE="${FRANKENPHP_MODE:-worker}"
case "$MODE" in
	classic)
		if [ -f /app/Caddyfile.dev ]; then
			cp /app/Caddyfile.dev /etc/caddy/Caddyfile
		elif [ -f /etc/frankenphp/Caddyfile.dev ]; then
			cp /etc/frankenphp/Caddyfile.dev /etc/frankenphp/Caddyfile
		fi
		;;
	worker)
		if [ -f /app/Caddyfile ]; then
			cp /app/Caddyfile /etc/caddy/Caddyfile
		fi
		;;
	*)
		echo "Unknown FRANKENPHP_MODE=$MODE (expected classic|worker)" >&2
		exit 1
		;;
esac
echo "FrankenPHP mode: $MODE"

git config --global --add safe.directory /app 2>/dev/null || true
git config --global --add safe.directory /var/page-builder-kit-bundle 2>/dev/null || true
mkdir -p /app/var/cache /app/var/log /app/var/data
chmod -R 777 /app/var 2>/dev/null || true

# Ensure MySQL schema exists when the persistent demo stack starts without `make link-bundle`
# (e.g. monorepo `update-deps` → `docker compose up -d`). MySQL is healthy via depends_on.
if [ -f /app/vendor/autoload.php ] && [ -f /app/bin/console ]; then
	php /app/bin/console doctrine:database:create --if-not-exists --no-interaction 2>/dev/null || true
	php /app/bin/console doctrine:schema:update --force --no-interaction || true
	php /app/bin/console assets:install public --symlink --no-interaction 2>/dev/null || true
fi

if [ -f /app/package.json ]; then
	echo "Building frontend assets (Vite + TypeScript / Pentatrion)..."
	cd /app
	CI=true pnpm install --frozen-lockfile || CI=true pnpm install
	pnpm run build
	echo "Vite build done."
fi

exec frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile
