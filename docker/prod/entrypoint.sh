#!/bin/bash
set -e

cd /var/www

# Garante .env presente — em produção o docker-compose injeta via env_file
if [ ! -f .env ] && [ -n "$APP_KEY" ]; then
    echo "[entrypoint] gerando .env a partir de variáveis do ambiente"
    env | grep -E '^(APP_|DB_|REDIS_|CACHE_|SESSION_|QUEUE_|MAIL_|FOCUS_)' > .env
fi

# APP_KEY obrigatória — se não veio, gera e avisa (uma vez)
if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null && [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Aguarda MySQL
echo "[entrypoint] aguardando MySQL em $DB_HOST:${DB_PORT:-3306}..."
until php -r "try { new PDO('mysql:host=$DB_HOST;port=${DB_PORT:-3306};dbname=$DB_DATABASE', '$DB_USERNAME', '$DB_PASSWORD'); exit(0); } catch (Exception \$e) { exit(1); }"; do
    sleep 2
done
echo "[entrypoint] MySQL ok"

# Migrations + cache de config/rotas/views
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link || true

# Permissões
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

exec "$@"
