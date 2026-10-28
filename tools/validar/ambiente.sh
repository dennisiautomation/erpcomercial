#!/usr/bin/env bash
# Ambiente DESCARTÁVEL do ERP Comercial para a rotina de validação (28/10/2026) — nada encosta na produção.
#   tools/validar/ambiente.sh subir <imagem> <nome> <porta> <OUT> [imagem-antes porta-antes]
#   tools/validar/ambiente.sh derrubar <nome>
# Sobe: <nome>-mysql (mysql:8.0, bancos erp_teste + erp_comercial_test), <nome>-vector (pgvector, alias `vector`),
# <nome>-redis (alias `redis`), <nome>-app (a imagem dada; só php-fpm + nginx — SEM queue:work e SEM schedule:work) e,
# se pedido, <nome>-antes (imagem no ar, mesmo banco, para os roteiros que comparam produção × branch).
# Integrações mortas: Focus, OpenAI, Melhor Envio, InfinitePay, Asaas, Uber, ViaCEP, BrasilAPI e app.ia365 apontados para
# 127.0.0.1 (--add-host) + tokens falsos; fila redis sem worker; MAIL_MAILER=log. O .env e o config.php de PRODUÇÃO que
# vêm dentro da imagem são apagados antes do boot: o app nasce só das variáveis abaixo (APP_KEY gerada na hora). Uma imagem feita
# por `docker commit` do container de produção traz também o ENV do compose (tokens, senhas): TODAS essas chaves são sobrescritas aqui.
# Banco: DUMP=<arquivo.sql(.gz)> importa em erp_teste; sem DUMP, migrate + seed demo (admin/dono/gerente/vendedor/caixa) + seed-smoke.
set -uo pipefail
AQUI=$(cd "$(dirname "$0")" && pwd)
NET=erp-val-net
SUBNET=${ERP_VAL_SUBNET:-10.57.0.0/24}   # pool automático do Docker esgotado no sp1 → sub-rede fixa (como o JL)
MORTOS="api.focusnfe.com.br homologacao.focusnfe.com.br api.openai.com melhorenvio.com.br sandbox.melhorenvio.com.br \
api.checkout.infinitepay.io api.asaas.com api-sandbox.asaas.com api.uber.com auth.uber.com viacep.com.br brasilapi.com.br \
app.ia365.com.br erp.ia365.com.br erpcomercial.ia365.com.br"
cor() { printf '\033[%sm%s\033[0m\n' "$1" "$2"; }
esperar_http() { local url=$1 n=${2:-60} c=000; for i in $(seq 1 "$n"); do c=$(curl -s -o /dev/null -m 5 -w '%{http_code}' "$url" || true); [ "$c" = 200 ] && return 0; sleep 2; done; echo "$c"; return 1; }

app_env() { # <nome> <porta> → variáveis do app (um -e por linha)
  local NOME=$1 PORTA=$2
  cat <<ENV
APP_NAME=${VAL_APP_NAME:-ERP Validacao}
APP_ENV=production
APP_KEY=$APP_KEY
APP_DEBUG=true
APP_URL=http://127.0.0.1:$PORTA
APP_TIMEZONE=America/Sao_Paulo
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
LOG_CHANNEL=stderr
LOG_LEVEL=debug
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=erp_teste
DB_USERNAME=erp
DB_PASSWORD=erp
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=false
FILESYSTEM_DISK=local
MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=9
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=
MAIL_FROM_ADDRESS=validacao@localhost
MAIL_FROM_NAME=ERP-Validacao
DB_ROOT_PASSWORD=val
DB_EXTERNAL_PORT=0
REDIS_EXTERNAL_PORT=0
VECTOR_DB_EXTERNAL_PORT=0
APP_PORT=$PORTA
FOCUS_NFE_AMBIENTE=homologacao
FOCUS_MASTER_TOKEN=val-sem-focus
FOCUS_WEBHOOK_BASE_URL=http://127.0.0.1:$PORTA
OPENAI_API_KEY=val-sem-ia
MELHOR_ENVIO_BASE_URL=http://127.0.0.1:9
INFINITEPAY_BASE_URL=http://127.0.0.1:9
VECTOR_DB_HOST=vector
VECTOR_DB_PORT=5432
VECTOR_DB_DATABASE=agente_busca
VECTOR_DB_USERNAME=agente
VECTOR_DB_PASSWORD=
ENV
}

subir_app() { # <imagem> <container> <porta> <envfile>
  local IMG=$1 CT=$2 PORTA=$3 ENVF=$4 hosts=()
  for h in $MORTOS; do hosts+=(--add-host "$h:127.0.0.1"); done
  docker run -d --name "$CT" --network $NET --network-alias "$CT" -p "127.0.0.1:$PORTA:80" --env-file "$ENVF" "${hosts[@]}" \
    -v "$AQUI/supervisord-val.conf:/etc/supervisor/conf.d/supervisord.conf:ro" \
    --entrypoint sh "$IMG" -c 'rm -f /var/www/.env /var/www/bootstrap/cache/config.php /var/www/bootstrap/cache/routes-v7.php
      exec /usr/local/bin/entrypoint.sh /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf' >/dev/null
}

cmd=${1:-}; shift || true
case "$cmd" in
subir)
  IMG=$1; NOME=$2; PORTA=$3; OUT=$4; IMG_ANTES=${5:-}; PORTA_ANTES=${6:-}
  mkdir -p "$OUT"
  "$0" derrubar "$NOME" >/dev/null
  docker network inspect $NET >/dev/null 2>&1 || docker network create --subnet "$SUBNET" $NET >/dev/null || { cor 31 "não criou a rede $NET"; exit 1; }
  # --- mysql (dois bancos) ---
  docker run -d --name "$NOME-mysql" --network $NET --network-alias mysql -e MYSQL_ROOT_PASSWORD=val -e TZ=America/Sao_Paulo \
    -v "$AQUI/mysql-init.sql:/docker-entrypoint-initdb.d/00-init.sql:ro" mysql:8.0 --default-time-zone=-03:00 --max_allowed_packet=256M >/dev/null
  # --- pgvector (alias vector; banco agente_busca + agente_busca_test p/ phpunit) ---
  docker run -d --name "$NOME-vector" --network $NET --network-alias vector -e POSTGRES_DB=agente_busca -e POSTGRES_USER=agente \
    -e POSTGRES_HOST_AUTH_METHOD=trust pgvector/pgvector:pg16 >/dev/null
  # --- redis (cache/sessão/fila sem worker) ---
  docker run -d --name "$NOME-redis" --network $NET --network-alias redis redis:7-alpine >/dev/null
  for i in $(seq 1 90); do docker exec "$NOME-mysql" mysqladmin ping --protocol=tcp -h127.0.0.1 -uroot -pval >/dev/null 2>&1 && break; sleep 2; done
  docker exec "$NOME-mysql" mysql -uroot -pval -e 'select 1' >/dev/null 2>&1 || { cor 31 "mysql de validação não subiu"; docker logs "$NOME-mysql" 2>&1 | tail -5; exit 1; }
  for i in $(seq 1 30); do docker exec "$NOME-vector" pg_isready -U agente >/dev/null 2>&1 && break; sleep 2; done
  docker exec "$NOME-vector" psql -U agente -d agente_busca -c 'CREATE DATABASE agente_busca_test' >/dev/null 2>&1 || true
  # --- dump (opcional) ---
  if [ -n "${DUMP:-}" ]; then
    [ -f "$DUMP" ] || { cor 31 "DUMP=$DUMP não existe"; exit 1; }
    cor 33 "   importando $DUMP em erp_teste…"
    case "$DUMP" in *.gz) gunzip -c "$DUMP";; *) cat "$DUMP";; esac | docker exec -i "$NOME-mysql" mysql -uroot -pval erp_teste 2>/dev/null || { cor 31 "falha ao importar o dump"; exit 1; }
  fi
  # --- app ---
  APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
  umask 077; app_env "$NOME" "$PORTA" > "$OUT/app.env"; umask 022
  subir_app "$IMG" "$NOME-app" "$PORTA" "$OUT/app.env"
  if ! esperar_http "http://127.0.0.1:$PORTA/login" 90; then cor 31 "app NÃO subiu (/login):"; docker logs "$NOME-app" 2>&1 | tail -20; exit 1; fi
  if [ -z "${DUMP:-}" ]; then
    docker exec "$NOME-app" php artisan db:seed --force > "$OUT/seed.log" 2>&1 || { cor 31 "db:seed falhou:"; tail -10 "$OUT/seed.log"; exit 1; }
  fi
  docker exec -i "$NOME-app" php artisan tinker --execute "$(sed '1{/^<?php/d}' "$AQUI/seed-smoke.php")" >> "$OUT/seed.log" 2>&1 || true
  grep -q 'SEED-SMOKE ok' "$OUT/seed.log" || cor 33 "   ⚠ seed-smoke não confirmou (financeiro/consulta podem faltar no smoke) — ver $OUT/seed.log"
  # --- app "antes" (imagem no ar, mesmo banco) para roteiros auto-antes ---
  if [ -n "$IMG_ANTES" ]; then
    # a imagem no ar pode ter o entrypoint antigo (sem aspas no .env): o container antes sobe sem espaço no APP_NAME
    app_env "$NOME" "$PORTA_ANTES" | sed 's/^APP_NAME=.*/APP_NAME=ERPValidacaoAntes/' > "$OUT/antes.env"; chmod 600 "$OUT/antes.env"
    subir_app "$IMG_ANTES" "$NOME-antes" "$PORTA_ANTES" "$OUT/antes.env"
    esperar_http "http://127.0.0.1:$PORTA_ANTES/login" 60 >/dev/null || cor 33 "   ⚠ app antes (imagem no ar) não respondeu /login 200"
  fi
  # garantias: nenhum worker/cron, .env de produção fora
  docker exec "$NOME-app" sh -c 'pgrep -f "queue:work|schedule:work" >/dev/null' && { cor 31 "queue/schedule RODANDO no ambiente de validação — abortando"; exit 1; }
  docker exec "$NOME-app" sh -c 'grep -qE "^DB_HOST=\"?mysql\"?$" /var/www/.env && ! grep -q "erp-com-mysql" /var/www/.env' || { cor 31 ".env do app de validação aponta para fora do descartável — abortando"; exit 1; }
  rm -f "$OUT/app.env" "$OUT/antes.env"
  cor 32 "   $NOME-app pronto em http://127.0.0.1:$PORTA (imagem $(docker inspect "$NOME-app" --format '{{.Image}}' | cut -c8-19)) · mysql $NOME-mysql (erp/erp erp_teste) · sem queue/schedule · integrações mortas"
  ;;
derrubar)
  NOME=$1
  docker rm -f "$NOME-app" "$NOME-antes" "$NOME-tests" "$NOME-mysql" "$NOME-vector" "$NOME-redis" >/dev/null 2>&1 || true
  echo "$NOME: containers removidos (banco descartável destruído)"
  ;;
*) echo "uso: ambiente.sh subir <imagem> <nome> <porta> <OUT> [imagem-antes porta-antes] | derrubar <nome>"; exit 2 ;;
esac
