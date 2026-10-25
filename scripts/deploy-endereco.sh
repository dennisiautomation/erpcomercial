#!/usr/bin/env bash
# Deploy 30/09 — endereço novo (Av. Paulista, 1337 · Cj. 72, 7º andar · Bela Vista · São Paulo/SP · CEP 01311-200)
# no site público do ERP (landing v2 + v1 clássica + e-mail de nova demonstração).
#
#   bash /home/ubuntu/apps/erp-endereco/scripts/deploy-endereco.sh   (roda como ubuntu; docker não exige sudo aqui)
#
# 3 views Blade, sem migration, sem env, sem rebuild. Confere que produção ainda é a base
# (frete-servico-cep @69c13ba), guarda os originais, copia, view:clear (as views compiladas em
# storage/framework/views ainda têm o endereço antigo), opcache_reset() (validate_timestamps=Off —
# rito do §311) e confere que "/" já mostra "Paulista, 1337". Qualquer falha ⇒ volta sozinho.
set -euo pipefail

SRC=/home/ubuntu/apps/erp-endereco     # branch site/endereco-paulista
BASE=69c13ba
FILES="resources/views/site/landing-v2.blade.php resources/views/site/landing.blade.php resources/views/emails/nova-demonstracao.blade.php"
TS=$(date -u +%Y%m%d_%H%M)
BK=/home/ubuntu/backups/deploy-endereco-$TS
ERP="docker exec erp-com-app"

docker ps --format "{{.Names}}" | grep -qx erp-com-app || { echo "sem acesso ao docker / container erp-com-app não está de pé"; exit 1; }
mkdir -p "$BK"; chown ubuntu:ubuntu "$BK" 2>/dev/null || true
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
  $ERP php /var/www/artisan view:clear >/dev/null 2>&1 || true
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
for f in $FILES; do docker cp "erp-com-app:/var/www/$f" "$BK/$(basename "$f")"; done
chown -R ubuntu:ubuntu "$BK" 2>/dev/null || true
for f in $FILES; do
  docker cp "$SRC/$f" "erp-com-app:/var/www/$f"
  $ERP chown www-data:www-data "/var/www/$f" 2>/dev/null || true
done
# Blade não passa no php -l; a prova é compilar: view:clear + view:cache e lint do compilado
$ERP php /var/www/artisan view:clear >/dev/null || voltar
$ERP php /var/www/artisan view:cache >/dev/null || { echo "   ✗ view:cache falhou"; voltar; }
$ERP sh -c 'for c in $(grep -l "Paulista, 1337" /var/www/storage/framework/views/*.php); do php -l "$c" >/dev/null || exit 1; done' || { echo "   ✗ lint das views compiladas"; voltar; }
$ERP chown -R www-data:www-data /var/www/storage/framework/views 2>/dev/null || true
echo "   3 views copiadas, compiladas e lintadas (backup em $BK)"

echo "── 3/4 · opcache ───────────────────────────────────────────"
opcache_reset || { echo "   ✗ não consegui limpar o opcache"; voltar; }

echo "── 4/4 · conferência ───────────────────────────────────────"
sleep 2
c=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 http://127.0.0.1:8091/)
[ "$c" = "200" ] || { echo "   ✗ / respondeu $c"; voltar; }
curl -s --max-time 20 http://127.0.0.1:8091/ | grep -q "Paulista, 1337" || { echo "   ✗ a home ainda não mostra o endereço novo"; voltar; }
curl -s --max-time 20 http://127.0.0.1:8091/ | grep -q "Alameda" && { echo "   ✗ a home ainda mostra o endereço antigo"; voltar; }
echo "   ✓ / 200 · endereço novo no ar"
echo
echo "PRONTO. Rollback manual: for f in $FILES; do docker cp $BK/\$(basename \$f) erp-com-app:/var/www/\$f; done + view:clear + opcache_reset"
