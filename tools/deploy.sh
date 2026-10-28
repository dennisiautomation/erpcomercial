#!/usr/bin/env bash
# Portão de deploy do ERP Comercial (28/10/2026) — a ÚNICA forma de subir uma imagem (regra do Dennis, 28/10).
#
#   tools/deploy.sh <branch> --conferir        só confere (não muda nada) — é o que o Claude roda
#   tools/deploy.sh <branch>                   confere, guarda a imagem do ar, sobe a validada e roda a rodada pós-deploy (Dennis / root)
#   tools/deploy.sh <branch> --aceitar-acesso  quando a mudança de acesso de perfil apontada pelo smoke É o pedido (documentar no docs.md)
#
# Só sobe se:
#   1. existir /home/ubuntu/erp-validacao/<branch>/depois/relatorio.md gerado por tools/validar/validar.sh <imagem> depois,
#      com as 4 etapas (phpunit, lint, roteiros, smoke) e com baseline antes/;
#   2. a imagem do relatório (ImagemID) existir e NÃO for a que já está no ar;
#   3. o código da branch for o mesmo validado (só *.md/docs podem ter mudado depois da rotina);
#   4. o relatório tiver TOTAL ❌ NOVOS = 0 e nenhuma mudança de acesso no smoke (ou --aceitar-acesso).
# Deploy: docker tag da imagem no ar → erp-com-app:pre-<branch>-<data> (rollback), tag da validada → erp-com-app:latest,
# `docker compose -f docker-compose.prod.yml up -d --no-build app` em $PROD_DIR (/root/erp: o entrypoint roda migrate --force),
# confere imagem no ar + /login + log do 1º minuto, roda validar.sh erp-com-app:latest pos-deploy e grava erp-validacao/deploys.log.
# Rollback: `docker tag erp-com-app:pre-<branch>-<data> erp-com-app:latest` + o mesmo `up -d --no-build app` (+ migrate:rollback se houve migration).
set -uo pipefail
REPO=$(cd "$(dirname "$0")/.." && pwd)
OUTBASE=/home/ubuntu/erp-validacao
LOG=$OUTBASE/deploys.log
PROD_DIR=${PROD_DIR:-/root/erp}
PORTA_PROD=${PORTA_PROD:-8091}
BRANCH=${1:-}; CONFERIR=0; ACEITA=0
shift || true
for a in "$@"; do case "$a" in --conferir) CONFERIR=1;; --aceitar-acesso) ACEITA=1;; *) echo "opção desconhecida: $a"; exit 2;; esac; done
cor() { printf '\033[%sm%s\033[0m\n' "$1" "$2"; }
die() { cor 31 "✋ DEPLOY BLOQUEADO: $*"; exit 1; }
[ -n "$BRANCH" ] || die "uso: tools/deploy.sh <branch> [--conferir] [--aceitar-acesso]"
ROTULO=$(echo "$BRANCH" | tr '/' '_')
DEPOIS=$OUTBASE/$ROTULO/depois/relatorio.md; ANTES=$OUTBASE/$ROTULO/antes/relatorio.md

# 1) relatório depois (com baseline antes e as 4 etapas)
[ -f "$DEPOIS" ] || die "rotina DEPOIS não rodou para $BRANCH: tools/validar/validar.sh <imagem> depois (esperado em $DEPOIS)"
[ -f "$ANTES" ] || die "rotina ANTES não rodou (baseline da imagem no ar): tools/validar/validar.sh <imagem-no-ar> antes"
IMG=$(sed -nE 's/^- ImagemID: `([0-9a-f]+)`.*/\1/p' "$DEPOIS"); [ -n "$IMG" ] || die "relatório depois sem ImagemID — rode o depois de novo"
VALIDADO=$(sed -nE 's/^- Commit: `([0-9a-f]+)`.*/\1/p' "$DEPOIS")
ETAPAS=$(sed -nE 's/^- Gerado: .* etapas: (.*)$/\1/p' "$DEPOIS")
for e in phpunit lint roteiros smoke; do case "$ETAPAS" in *$e*) ;; *) die "o depois rodou sem a etapa '$e' (etapas: $ETAPAS) — rode tools/validar/validar.sh <imagem> depois sem --so";; esac; done
IDADE=$(( ($(date +%s) - $(stat -c %Y "$DEPOIS")) / 3600 )); [ "$IDADE" -le 48 ] || cor 33 "⚠ o relatório depois tem ${IDADE}h — considere rodar de novo"

# 2) imagem
docker image inspect "$IMG" >/dev/null 2>&1 || die "a imagem validada $IMG não existe mais localmente — tools/build.sh + depois de novo"
NOAR=$(docker inspect erp-com-app --format '{{.Image}}' | cut -d: -f2 | cut -c1-12)
[ "$NOAR" != "$IMG" ] || die "a imagem $IMG já está no ar"

# 3) código da branch = código validado
HEAD=$(git -C "$REPO" rev-parse --verify "$BRANCH^{commit}" 2>/dev/null) || die "branch $BRANCH não existe neste repo"
if [ "$HEAD" != "$VALIDADO" ]; then
  DIFF=$(git -C "$REPO" diff --name-only "$VALIDADO" "$HEAD" -- . ':(exclude)*.md' ':(exclude)docs/**' 2>/dev/null)
  [ -z "$DIFF" ] || die "código mudou depois da validação (${VALIDADO:0:7} → ${HEAD:0:7}): $(echo "$DIFF" | tr '\n' ' ') — tools/build.sh + validar depois de novo"
  cor 33 "commit validado ${VALIDADO:0:7} ≠ ponta ${HEAD:0:7}, só documentação mudou — ok"
fi

# 4) resultado
TOTAL=$(sed -nE 's/^## TOTAL ❌ NOVOS: \*\*([0-9]+)\*\*.*/\1/p' "$DEPOIS"); [ -n "$TOTAL" ] || die "não li o TOTAL ❌ NOVOS de $DEPOIS"
[ "$TOTAL" = 0 ] || die "depois com $TOTAL ❌ novo(s) — veja $DEPOIS"
ACESSO=$(sed -nE 's/^- Smoke × antes: \*\*[0-9]+ ❌ erros novos · ([0-9]+) mudanças de acesso\*\*.*/\1/p' "$DEPOIS")
[ -n "$ACESSO" ] || die "o depois não comparou o smoke com o antes — rode antes e depois com a etapa smoke"
if [ "$ACESSO" != 0 ] && [ "$ACEITA" != 1 ]; then grep '^  - ACESSO' "$DEPOIS" | head -20; die "smoke: $ACESSO mudança(s) de acesso de perfil (acima). Se foi o pedido, documente no docs.md e rode com --aceitar-acesso"; fi
grep -q 'Produção intocada.*⚠' "$DEPOIS" && cor 33 "⚠ contagem da produção mudou durante a rodada (uso normal?) — ver producao-diff.txt"
PHP=$(sed -nE 's/^- PHPUnit[^*]*\*\*([0-9]+ ✅ \/ [0-9]+ ❌)\*\*.*/\1/p' "$DEPOIS"); SMK=$(sed -nE 's/^- Smoke \(puppeteer[^*]*\*\*([0-9]+ ✅ \/ [0-9]+ ❌)\*\*.*/\1/p' "$DEPOIS")
cor 32 "✅ rotina conferida: $BRANCH @${HEAD:0:7} · imagem $IMG (no ar: $NOAR) · phpunit $PHP · smoke $SMK · 0 ❌ novos · $ACESSO mudança(s) de acesso"
[ "$CONFERIR" = 1 ] && { echo "   (--conferir: nada foi alterado; o deploy é do Dennis: sudo tools/deploy.sh $BRANCH)"; exit 0; }

# ---------------------------------------------------------------- deploy (Dennis / root)
[ -f "$PROD_DIR/docker-compose.prod.yml" ] || die "$PROD_DIR/docker-compose.prod.yml não encontrado (PROD_DIR=…)"
PRE=erp-com-app:pre-$ROTULO-$(date +%Y%m%d); docker image inspect "$PRE" >/dev/null 2>&1 && PRE=$PRE-$(date +%H%M)
docker tag "$NOAR" "$PRE" || die "docker tag de rollback falhou"
cor 33 "rollback: $PRE (= $NOAR)"
docker tag "$IMG" erp-com-app:latest || die "docker tag latest falhou"
T0=$(date -u +%Y-%m-%dT%H:%M:%S)
LOG_ANTES=$(docker exec erp-com-app sh -c 'stat -c %s /var/www/storage/logs/laravel.log 2>/dev/null || echo 0')
cor 33 "subindo $IMG ($BRANCH) com docker compose -f docker-compose.prod.yml up -d --no-build app em $PROD_DIR …"
(cd "$PROD_DIR" && DEPLOY_VALIDADO=1 docker compose -f docker-compose.prod.yml up -d --no-build app) || die "compose up falhou (rollback: docker tag $PRE erp-com-app:latest + up -d --no-build app)"

# ---------------------------------------------------------------- depois do deploy (sem pedir)
sleep 10
AGORA=$(docker inspect erp-com-app --format '{{.Image}}' | cut -d: -f2 | cut -c1-12)
[ "$AGORA" = "$IMG" ] || die "container subiu com $AGORA, esperado $IMG — confira (rollback: $PRE)"
HTTP=000; for i in $(seq 1 30); do HTTP=$(curl -s -m 15 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORTA_PROD/login" || true); [ "$HTTP" = 200 ] && break; sleep 3; done
cor 33 "no ar: $AGORA · /login $HTTP · aguardando 60 s para ler o log…"; sleep 60
ERROS=$( { docker logs --since "$T0" erp-com-app 2>&1; docker exec erp-com-app sh -c "tail -c +$((LOG_ANTES + 1)) /var/www/storage/logs/laravel.log 2>/dev/null"; } | grep -E 'production\.(ERROR|CRITICAL|EMERGENCY)|PHP Fatal|\[error\]' | cut -c1-200)
N_ERROS=$(printf '%s' "$ERROS" | grep -c . || true)
[ "$N_ERROS" = 0 ] || { cor 33 "⚠ $N_ERROS linha(s) de erro no log da produção desde a troca:"; printf '%s\n' "$ERROS" | head -10; }
cor 36 "-- rodada pós-deploy na imagem do ar (roteiros + smoke × depois)"
"$REPO/tools/validar/validar.sh" erp-com-app:latest pos-deploy --rotulo "$ROTULO" > "$OUTBASE/$ROTULO/pos-deploy.log" 2>&1 || true
POS=$OUTBASE/$ROTULO/pos-deploy/relatorio.md
POS_TOTAL=$(sed -nE 's/^## TOTAL ❌ NOVOS: \*\*([0-9]+)\*\*.*/\1/p' "$POS" 2>/dev/null); POS_TOTAL=${POS_TOTAL:-?}
echo "$(date '+%d/%m/%Y %H:%M') $BRANCH ${HEAD:0:7} $NOAR -> $IMG rollback=$PRE login=$HTTP erros_log=$N_ERROS pos_deploy_novos=$POS_TOTAL" >> "$LOG"
if [ "$POS_TOTAL" = 0 ] && [ "$HTTP" = 200 ]; then
  cor 32 "✅ NO AR: $IMG · /login $HTTP · pós-deploy 0 ❌ novos ($POS) · rollback $PRE · registre no docs.md (topo + seção, hora BRT e UTC)"
else
  cor 31 "⚠ NO AR ($IMG), MAS a rodada pós-deploy acusou $POS_TOTAL ❌ novo(s) ou /login=$HTTP — veja $POS. Rollback (decisão do Dennis): docker tag $PRE erp-com-app:latest + up -d --no-build app"; exit 1
fi
