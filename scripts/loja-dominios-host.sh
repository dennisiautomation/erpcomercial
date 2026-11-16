#!/usr/bin/env bash
# Domínio próprio das lojas online — rotina do SERVIDOR (16/11/2026).
#
# Roda no host (não no container), como root, pelo cron a cada 10 minutos:
#   */10 * * * * APP=erp-com-app PORTA=8091 EMAIL=suporte@exemplo.com /caminho/loja-dominios-host.sh >> /var/log/loja-dominios.log 2>&1
#
# Para cada domínio que o lojista já apontou (o app confere o DNS), cria o
# bloco do nginx, emite o certificado com o certbot e avisa o app que está no ar.
# NÃO é instalada pelo deploy: instalar é decisão de quem cuida do servidor.
# Depois de instalar, ligar LOJA_ONLINE_CERTIFICADO_AUTOMATICO=true no .env do app.
set -euo pipefail

APP="${APP:?informe o container do app (APP=...)}"
PORTA="${PORTA:?informe a porta local do app (PORTA=...)}"
EMAIL="${EMAIL:?informe o e-mail do certbot (EMAIL=...)}"
NGINX_DIR="${NGINX_DIR:-/etc/nginx/sites-enabled}"

docker exec "$APP" php artisan loja:dominios --pendentes | while read -r DOMINIO; do
    # só nome de domínio simples — nada que venha do banco vai para o shell sem passar por aqui
    [[ "$DOMINIO" =~ ^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$ ]] || continue
    ARQ="$NGINX_DIR/loja-$DOMINIO.conf"
    if [ ! -f "$ARQ" ]; then
        cat > "$ARQ" <<CONF
server {
    listen 80;
    server_name $DOMINIO;
    client_max_body_size 20m;
    location / {
        proxy_pass http://127.0.0.1:$PORTA;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
CONF
        if ! nginx -t 2>/dev/null; then rm -f "$ARQ"; echo "$(date '+%F %T') $DOMINIO: nginx recusou o bloco"; continue; fi
        systemctl reload nginx
    fi
    if certbot --nginx -n --agree-tos -m "$EMAIL" --redirect -d "$DOMINIO"; then
        docker exec "$APP" php artisan loja:dominios --ativar="$DOMINIO"
        echo "$(date '+%F %T') $DOMINIO: no ar"
    else
        echo "$(date '+%F %T') $DOMINIO: certbot falhou (tenta de novo na próxima rodada)"
    fi
done
