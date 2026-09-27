#!/usr/bin/env bash
# Deploy 27/09 — Vendedor IA: frete escolhido no pedido + endereço pelo CEP (ERP só).
#
#   sudo bash /home/ubuntu/apps/erp-frete-cep/scripts/deploy-frete-cep.sh
#
# 2 arquivos PHP, sem migration, sem env, sem rebuild. Confere que produção ainda é a base
# (feat/cartao-infinitepay @7efb9d1), guarda os originais, copia, lint, opcache_reset()
# (validate_timestamps=Off — rito do §311) e confere /login. Qualquer falha ⇒ volta sozinho.
set -euo pipefail

SRC=/home/ubuntu/apps/erp-frete-cep     # branch frete-servico-cep
BASE=7efb9d1
FILES="app/Services/Entrega/MelhorEnvioService.php app/Http/Controllers/Api/IntegracaoAgenteController.php"
TS=$(date -u +%Y%m%d_%H%M)
BK=/home/ubuntu/backups/deploy-frete-cep-$TS
ERP="docker exec erp-com-app"

[ "$(id -u)" = "0" ] || { echo "rode com sudo"; exit 1; }
mkdir -p "$BK"; chown ubuntu:ubuntu "$BK"
cd "$SRC"

opcache_reset() {
  local nome="opcache-reset-$(head -c 12 /dev/urandom | base64 | tr -dc a-z0-9 | head -c 12).php"
  $ERP sh -c "printf '<?php header(\"Content-Type: text/plain\"); echo function_exists(\"opcache_reset\") && opcache_reset() ? \"opcache limpo\" : \"sem opcache\";' > /var/www/public/$nome"
  local r; r=$(curl -s --max-time 10 "http://127.0.0.1:8091/$nome" || echo "falhou")
  $ERP rm -f "/var/www/public/$nome"
  echo "   opcache: $r"
  [ "$r" = "opcache limpo" ]
}

voltar() {
  echo "── ✗ VOLTANDO os originais ─────────────────────────────────"
  for f in $FILES; do docker cp "$BK/$(basename "$f")" "erp-com-app:/var/www/$f"; done
  opcache_reset || true
  echo "   originais restaurados (backup em $BK)"; exit 1
}

echo "── 1/4 · produção ainda igual à base $BASE? ───────────────"
for f in $FILES; do
  a=$($ERP cat "/var/www/$f" | md5sum | cut -c1-32)
  b=$(git show "$BASE:$f" | md5sum | cut -c1-32)
  [ "$a" = "$b" ] || { echo "   ✗ $f mudou em produção desde a base — PARE"; exit 1; }
done
echo "   ok"

echo "── 2/4 · backup + cópia + lint ─────────────────────────────"
for f in $FILES; do
  docker cp "erp-com-app:/var/www/$f" "$BK/$(basename "$f")"
done
chown -R ubuntu:ubuntu "$BK"
for f in $FILES; do
  docker cp "$SRC/$f" "erp-com-app:/var/www/$f"
  $ERP chown www-data:www-data "/var/www/$f" 2>/dev/null || true
  $ERP php -l "/var/www/$f" >/dev/null || { echo "   ✗ lint $f"; voltar; }
done
echo "   2 arquivos copiados, lint ok (backup em $BK)"

echo "── 3/4 · opcache ───────────────────────────────────────────"
opcache_reset || { echo "   ✗ não consegui limpar o opcache"; voltar; }

echo "── 4/4 · conferência ───────────────────────────────────────"
sleep 2
c=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 http://127.0.0.1:8091/login)
[ "$c" = "200" ] || { echo "   ✗ /login respondeu $c"; voltar; }
$ERP grep -q "consultarCep" /var/www/app/Services/Entrega/MelhorEnvioService.php || voltar
echo "   ✓ /login 200 · código novo no ar"
echo
echo "PRONTO. Rollback manual: for f in $FILES; do docker cp $BK/\$(basename \$f) erp-com-app:/var/www/\$f; done + opcache_reset"
