#!/usr/bin/env bash
# Coleta só leitura do Gestão Click da STILO (produtos + estoque por loja). Não grava nada em lugar nenhum
# além do arquivo de saída abaixo. A credencial nunca aparece na tela.
set -euo pipefail
OUT=/home/ubuntu/stilo-gc-import/gc-produtos-$(TZ=America/Sao_Paulo date +%Y%m%d-%H%M).json
docker cp /home/ubuntu/stilo-gc-import/coletar-gc.mjs audit-app:/tmp/coletar-gc.mjs
docker exec audit-db psql -U auditapp -d audit_db -At -c "select \"credentialsEnc\" from \"ErpIntegration\" where id like '10df61de%' limit 1" \
  | docker exec -i audit-app node /tmp/coletar-gc.mjs > "$OUT"
docker exec -u root audit-app rm -f /tmp/coletar-gc.mjs
chown ubuntu:ubuntu "$OUT" 2>/dev/null || true
echo "PRONTO: $OUT ($(stat -c %s "$OUT") bytes)"
