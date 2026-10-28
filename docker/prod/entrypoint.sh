#!/bin/bash
set -e

cd /var/www

# Garante .env presente — em produção o docker-compose injeta via env_file
if [ ! -f .env ] && [ -n "$APP_KEY" ]; then
    echo "[entrypoint] gerando .env a partir de variáveis do ambiente"
    # 28/10/2026: valores com espaço (APP_NAME=ERP Comercial) derrubaram o boot — "Failed to parse dotenv file".
    # Grava cada valor entre aspas duplas (escapando \ e ") — o Laravel lê igual; sem espaço ou com, nunca mais quebra.
    env | grep -E '^(APP_|DB_|REDIS_|CACHE_|SESSION_|QUEUE_|MAIL_|FOCUS_)' | while IFS= read -r linha; do
        k=${linha%%=*}; v=${linha#*=}; v=${v//\\/\\\\}; v=${v//\"/\\\"}
        printf '%s="%s"\n' "$k" "$v"
    done > .env
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

# Migrations + cache de config/rotas/views (SKIP_MIGRATE=1 = canário do tools/deploy.sh: só prova que a imagem sobe)
if [ "${SKIP_MIGRATE:-0}" = 1 ]; then echo "[entrypoint] SKIP_MIGRATE=1 — sem migrate (canário)"; else php artisan migrate --force; fi
php artisan config:cache
php artisan route:cache
php artisan view:cache
# storage:link pode falhar se já existir — ignoramos
[ -L /var/www/public/storage ] || php artisan storage:link

# Permissões
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Diretórios temporários do nginx: o worker roda como www-data, mas na imagem alpine
# /var/lib/nginx pertence ao usuário `nginx` com 0700 — sem isto QUALQUER upload maior
# que o buffer em memória morre em 500 ("client_body ... Permission denied") antes de
# chegar no PHP (importação de planilha, certificado A1, anexos de caixa).
mkdir -p /var/lib/nginx/tmp/client_body /var/lib/nginx/tmp/proxy \
         /var/lib/nginx/tmp/fastcgi /var/lib/nginx/tmp/uwsgi /var/lib/nginx/tmp/scgi
chown -R www-data:www-data /var/lib/nginx
chmod -R 0755 /var/lib/nginx

exec "$@"
