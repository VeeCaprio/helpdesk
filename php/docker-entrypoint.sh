#!/bin/bash
set -euo pipefail

cd /var/www/html

echo "Starting HelpDesk PHP container..."

# ---------------------------------------------------------------------------
# 1. .env wajib sudah tersedia (dibuat dari .env.example, bukan di-overwrite)
# ---------------------------------------------------------------------------
if [ ! -f .env ]; then
    echo "ERROR: .env not found. Create it from .env.example before starting the container." >&2
    exit 1
fi

get_env() {
    grep -E "^$1=" .env | tail -n 1 | cut -d '=' -f 2- | sed -e 's/^"//' -e 's/"$//'
}

DB_HOST="$(get_env DB_HOST)"
DB_PORT="$(get_env DB_PORT)"
DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"

# ---------------------------------------------------------------------------
# 2. Tunggu MariaDB siap
# ---------------------------------------------------------------------------
echo "Waiting for database at ${DB_HOST}:${DB_PORT}..."
RETRIES=60
until nc -z "${DB_HOST}" "${DB_PORT}"; do
    RETRIES=$((RETRIES - 1))
    if [ "${RETRIES}" -le 0 ]; then
        echo "ERROR: Timed out waiting for the database." >&2
        exit 1
    fi
    sleep 2
done
echo "Database is ready."

# ---------------------------------------------------------------------------
# 3. Install dependency hanya bila vendor belum ada
# ---------------------------------------------------------------------------
if [ ! -f vendor/autoload.php ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# ---------------------------------------------------------------------------
# 4. Folder & permission Laravel
# ---------------------------------------------------------------------------
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         storage/app/public \
         bootstrap/cache
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# ---------------------------------------------------------------------------
# 5. App key (hanya bila kosong)
# ---------------------------------------------------------------------------
if ! grep -qE '^APP_KEY=.+$' .env; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

php artisan storage:link --force

# ---------------------------------------------------------------------------
# 6. Migrasi berjalan aman: TANPA migrate:fresh / tanpa penghapusan data
# ---------------------------------------------------------------------------
echo "Running pending migrations (no fresh, no data deletion)..."
php artisan migrate --force

# ---------------------------------------------------------------------------
# 7. Inisialisasi project TIDAK dijalankan otomatis.
#    Jalankan sekali secara eksplisit bila database baru:
#      docker compose exec php php artisan project:init
#    Ini mencegah seeding/perubahan data tak terduga setiap restart.
# ---------------------------------------------------------------------------
php artisan optimize:clear

service cron start || true

echo "HelpDesk container is ready."

exec "$@"
