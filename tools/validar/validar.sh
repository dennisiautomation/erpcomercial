#!/usr/bin/env bash
# Rotina COMPLETA de validação do ERP Comercial numa imagem (28/10/2026) — passos 2–4 e 6 do CLAUDE.md num comando só.
#
#   tools/validar/validar.sh <imagem> antes        imagem que está NO AR: baseline (phpunit + lint + roteiros + smoke)
#   tools/validar/validar.sh <imagem> depois       imagem nova (tools/build.sh): tudo de novo, comparado ao antes → 0 ❌ novos
#   tools/validar/validar.sh erp-com-app:latest pos-deploy   (chamado pelo tools/deploy.sh: roteiros + smoke × depois)
#   opções: --so=phpunit,lint,roteiros,smoke   --prod <ref git da produção, padrão main>   --rotulo <nome>   --porta <n>   --sem-commit
#   `antes` com a imagem do container erp-com-app: se a produção tem código posto por tar POR CIMA da imagem (docker diff),
#   o script faz `docker commit --pause=false erp-com-app erp-com-app:no-ar-<data>` e valida ESSA imagem (o que está no ar de
#   verdade); --sem-commit usa a imagem crua. A baseline do "❌ novo" no depois é a pasta antes/ do mesmo rótulo.
#   variáveis: DUMP=<dump.sql.gz> (banco a partir de um dump em vez do seed demo) · PERFIS=admin,dono · ONLY=/app/pdv · MANTER=1
#
# Ambiente DESCARTÁVEL (tools/validar/ambiente.sh): mysql + pgvector + redis + app da imagem, sem queue/schedule, integrações
# externas mortas (Focus, InfinitePay, Melhor Envio, OpenAI, Asaas, Uber, ViaCEP, BrasilAPI, app.ia365 → 127.0.0.1), banco
# novo (seed demo) destruído no fim. A produção (erp-com-*) só é LIDA: contagem SELECT antes × depois prova que ficou intacta.
# Etapas: phpunit (php artisan test COMPLETO, dentro de um container da imagem com composer dev) · lint (php -l em todo PHP
# de app/routes/config/database/tests + TODAS as blades compiladas) · roteiros (tools/validar/roteiros.tsv) · smoke (todas
# as telas GET × 7 perfis, puppeteer, print por tela, versão nova e antiga quando há). Saída:
# /home/ubuntu/erp-validacao/<rotulo>/<fase>/relatorio.md (+ phpunit.txt, lint.txt, roteiros/, smoke.tsv, telas/*.png).
set -uo pipefail
REPO=$(cd "$(dirname "$0")/../.." && pwd)
D=$REPO/tools/validar
OUTBASE=/home/ubuntu/erp-validacao
IMG=${1:-}; FASE=${2:-depois}
[ $# -ge 2 ] && shift 2 || shift $#
SO=""; PROD_REF=""; ROTULO=""; PORTA=""; SEM_COMMIT=0
while [ $# -gt 0 ]; do case "$1" in --sem-commit) SEM_COMMIT=1;; --so=*) SO=${1#--so=};; --prod) PROD_REF=$2; shift;; --rotulo) ROTULO=$2; shift;; --porta) PORTA=$2; shift;; *) echo "opção desconhecida: $1"; exit 2;; esac; shift; done
[ -n "$IMG" ] || { sed -n 2,20p "$0"; exit 2; }
case "$FASE" in antes|depois|pos-deploy) ;; *) echo "fase deve ser antes|depois|pos-deploy"; exit 2;; esac
if [ -z "$SO" ]; then case "$FASE" in pos-deploy) SO=roteiros,smoke;; *) SO=phpunit,lint,roteiros,smoke;; esac; fi
faz() { case ",$SO," in *,$1,*) return 0;; *) return 1;; esac; }
cor() { printf '\033[%sm%s\033[0m\n' "$1" "$2"; }

IMG_ID=$(docker image inspect "$IMG" --format '{{.Id}}' 2>/dev/null | cut -d: -f2 | cut -c1-12) || true
[ -n "$IMG_ID" ] || { cor 31 "imagem $IMG não existe"; exit 1; }
NOAR_CT=$(docker inspect erp-com-app --format '{{.Image}}' 2>/dev/null | cut -d: -f2 | cut -c1-12)
if [ "$FASE" = antes ] && [ "$IMG_ID" = "$NOAR_CT" ] && [ "$SEM_COMMIT" != 1 ]; then
  N_TAR=$(docker diff erp-com-app 2>/dev/null | grep -cE '^[AC] /var/www/(app|routes|resources|config|database|public)/.*\.[a-z]+$')
  if [ "${N_TAR:-0}" -gt 0 ]; then
    IMG=erp-com-app:no-ar-$(date +%Y%m%d-%H%M)
    cor 33 "⚠ a produção tem $N_TAR arquivo(s) de código postos por tar por cima da imagem $IMG_ID — gravando o que está NO AR como $IMG (docker commit --pause=false)"
    docker commit --pause=false erp-com-app "$IMG" >/dev/null || { cor 31 "docker commit falhou"; exit 1; }
    IMG_ID=$(docker image inspect "$IMG" --format '{{.Id}}' | cut -d: -f2 | cut -c1-12)
  fi
fi
COMMIT=$(git -C "$REPO" rev-parse HEAD); BRANCH=$(git -C "$REPO" rev-parse --abbrev-ref HEAD)
[ -n "$ROTULO" ] || ROTULO=$(echo "$BRANCH" | tr '/' '_')
PROD=$(git -C "$REPO" rev-parse --verify "${PROD_REF:-main}^{commit}" 2>/dev/null || echo "$COMMIT")
NOAR=$NOAR_CT
OUT=$OUTBASE/$ROTULO/$FASE; mkdir -p "$OUT"
rm -f "${OUT:?}/relatorio.md" "${OUT:?}"/producao-*.txt
faz phpunit && rm -f "${OUT:?}"/phpunit.*; faz lint && rm -f "${OUT:?}"/lint.*
faz roteiros && rm -rf "${OUT:?}/roteiros" "${OUT:?}/roteiros.tsv"; faz smoke && rm -rf "${OUT:?}/telas" "${OUT:?}"/smoke.* "${OUT:?}/smoke-diff.txt" "${OUT:?}/rotas.json"
echo "$IMG_ID" > "$OUT/imagem.txt"; echo "$COMMIT $BRANCH" > "$OUT/commit.txt"; echo "$PROD" > "$OUT/producao.txt"; echo "$NOAR" > "$OUT/noar.txt"
cor 36 "== validar $FASE · imagem $IMG_ID ($IMG) · checkout $BRANCH@${COMMIT:0:7} · produção ref ${PROD:0:7} (no ar $NOAR) · etapas: $SO"
cor 36 "   saída: $OUT"
[ "$FASE" = antes ] && [ "$IMG_ID" != "$NOAR" ] && [[ "$IMG" != erp-com-app:no-ar-* ]] && cor 33 "⚠ fase antes mas a imagem $IMG_ID não é a que está no ar ($NOAR)"
[ "$FASE" = depois ] && [ ! -f "$OUTBASE/$ROTULO/antes/relatorio.md" ] && cor 33 "⚠ não há antes/ para $ROTULO: tudo o que falhar vai contar como ❌ novo (rode validar.sh <imagem-no-ar> antes)"
git -C "$REPO" diff --name-only "$PROD...$COMMIT" > "$OUT/tocados.txt" 2>/dev/null || git -C "$REPO" diff --name-only "$PROD" "$COMMIT" > "$OUT/tocados.txt"

# produção intocada: contagem SELECT (só leitura) antes e depois da rodada
contar_prod() { docker exec erp-com-mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -N -B erp_comercial -e "select '"'"'empresas'"'"',count(*) from empresas union all select '"'"'users'"'"',count(*) from users union all select '"'"'clientes'"'"',count(*) from clientes union all select '"'"'produtos'"'"',count(*) from produtos union all select '"'"'vendas'"'"',count(*) from vendas union all select '"'"'notas_fiscais'"'"',count(*) from notas_fiscais union all select '"'"'orcamentos'"'"',count(*) from orcamentos union all select '"'"'pedidos'"'"',count(*) from pedidos union all select '"'"'configuracoes_fiscais'"'"',count(*) from configuracoes_fiscais" 2>/dev/null' || echo "sem acesso"; }
contar_prod > "$OUT/producao-antes.txt"

NOME=erp-val-$(echo "$ROTULO-$FASE" | tr -c 'a-zA-Z0-9-\n' '-' | cut -c1-28); NOME=${NOME%-}
[ -n "$PORTA" ] || { PORTA=8097; while ss -ltn | grep -qE ":$PORTA |:$((PORTA+1)) "; do PORTA=$((PORTA+2)); done; }
ANTES_IMG=""   # app "antes" dos roteiros auto-antes = a imagem validada no antes/ (commit do container, se houve), senão a do container
if faz roteiros && [ "$FASE" != antes ] && grep -qE $'\tauto-antes\t' "$D/roteiros.tsv"; then
  ANTES_IMG=$(cat "$OUTBASE/$ROTULO/antes/imagem.txt" 2>/dev/null || true); docker image inspect "${ANTES_IMG:-x}" >/dev/null 2>&1 || ANTES_IMG=$NOAR
fi
trap '"$D/ambiente.sh" derrubar "$NOME" >/dev/null 2>&1' EXIT
"$D/ambiente.sh" subir "$IMG" "$NOME" "$PORTA" "$OUT" $ANTES_IMG ${ANTES_IMG:+$((PORTA+1))} || { cor 31 "ambiente não subiu"; exit 1; }
APP=$NOME-app; DB_CT=$NOME-mysql; BASE=http://127.0.0.1:$PORTA; ANTES_URL=$BASE; [ -n "$ANTES_IMG" ] && ANTES_URL=http://127.0.0.1:$((PORTA+1))

# ---------------------------------------------------------------- phpunit COMPLETO (container da imagem + vendor dev cacheado por composer.lock)
rodar_phpunit() { # <imagem> <arquivo-saida>
  local img=$1 saida=$2 lock vend tests=$NOME-tests
  lock=$(docker run --rm --entrypoint sh "$img" -c 'md5sum /var/www/composer.lock' | cut -c1-12)
  vend=$OUTBASE/.vendor/$lock; mkdir -p "$vend" "$OUTBASE/.composer"
  docker rm -f "$tests" >/dev/null 2>&1
  docker run -d --name "$tests" --network erp-val-net -v "$vend:/var/www/vendor" -v "$OUTBASE/.composer:/tmp/composer" -e COMPOSER_HOME=/tmp/composer \
    -e VECTOR_DB_HOST=vector -e VECTOR_DB_DATABASE=agente_busca_test -e VECTOR_DB_USERNAME=agente -e VECTOR_DB_PASSWORD= \
    -e APP_ENV=testing -e APP_DEBUG=true -e APP_URL=http://localhost -e DB_CONNECTION=mysql -e DB_HOST=mysql -e DB_PORT=3306 -e DB_DATABASE=erp_comercial_test \
    -e DB_USERNAME=erp_user -e DB_PASSWORD=erp_password -e DB_ROOT_PASSWORD=val -e CACHE_STORE=array -e SESSION_DRIVER=array -e QUEUE_CONNECTION=sync \
    -e MAIL_MAILER=array -e MAIL_HOST=127.0.0.1 -e MAIL_PASSWORD= -e MAIL_FROM_NAME=ERP-Teste -e REDIS_HOST=redis -e REDIS_PASSWORD=null -e LOG_CHANNEL=stderr \
    -e FOCUS_MASTER_TOKEN=val-sem-focus -e OPENAI_API_KEY=val-sem-ia -e MELHOR_ENVIO_BASE_URL=http://127.0.0.1:9 -e INFINITEPAY_BASE_URL=http://127.0.0.1:9 \
    --add-host api.focusnfe.com.br:127.0.0.1 --add-host homologacao.focusnfe.com.br:127.0.0.1 --add-host api.openai.com:127.0.0.1 \
    --entrypoint sh "$img" -c 'sleep infinity' >/dev/null
  docker exec "$tests" sh -c 'cd /var/www && rm -f .env bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/packages.php bootstrap/cache/services.php
    [ -x vendor/bin/phpunit ] || composer install --no-interaction --prefer-dist --no-progress -q 2>&1 | tail -3
    [ -x vendor/bin/phpunit ] || { echo "composer install não trouxe o phpunit"; exit 1; }
    composer dump-autoload -q 2>/dev/null; php artisan package:discover --ansi >/dev/null 2>&1
    printf "APP_KEY=base64:%s\n" "$(head -c 32 /dev/urandom | base64)" > .env
    env -i PATH="$PATH" HOME=/root COLUMNS=220 VECTOR_DB_HOST=vector VECTOR_DB_DATABASE=agente_busca_test VECTOR_DB_USERNAME=agente VECTOR_DB_PASSWORD= php artisan test --no-ansi 2>&1' > "$saida"; echo $? > "${saida%.txt}.exit"
  docker rm -f "$tests" >/dev/null 2>&1
}
if faz phpunit; then
  cor 36 "-- phpunit (php artisan test completo, na imagem $IMG_ID)"
  rodar_phpunit "$IMG" "$OUT/phpunit.txt"
  grep -E '^\s*Tests:|FAILED' "$OUT/phpunit.txt" | head -40 | sed 's/^/   /'
fi
# ---------------------------------------------------------------- lint: todo PHP + TODAS as blades compiladas (view:cache sozinho não linta)
if faz lint; then
  cor 36 "-- lint (php -l em app/routes/config/database/tests + blades compiladas)"
  docker exec "$APP" sh -c 'cd /var/www && rm -rf storage/framework/views/*.php && php artisan view:cache >/dev/null 2>&1
    n=0; b=0
    for f in $(find app routes config database tests -name "*.php" 2>/dev/null); do n=$((n+1)); php -l "$f" >/dev/null 2>&1 || { echo "LINT $f: $(php -l "$f" 2>&1 | grep -v "^Errors parsing" | head -1)"; }; done
    for f in storage/framework/views/*.php; do b=$((b+1)); php -l "$f" >/dev/null 2>&1 || { v=$(grep -m1 -oE "PATH .*" "$f" | cut -c6-); echo "LINT blade ${v:-$f}: $(php -l "$f" 2>&1 | grep -v "^Errors parsing" | head -1)"; }; done
    echo "ARQUIVOS $n BLADES $b"' > "$OUT/lint.txt" 2>&1
  echo "   $(grep -c '^LINT ' "$OUT/lint.txt") erro(s) · $(grep '^ARQUIVOS' "$OUT/lint.txt")"
fi
# ---------------------------------------------------------------- roteiros das entregas anteriores
if faz roteiros; then
  cor 36 "-- roteiros (tools/validar/roteiros.tsv) · BASE=$BASE ANTES=$ANTES_URL"
  mkdir -p "$OUT/roteiros" "$OUT/roteiros/xml"; : > "$OUT/roteiros.tsv"
  [ -f "$REPO/scripts/validar-entrada-nfe-xmls.py" ] && python3 "$REPO/scripts/validar-entrada-nfe-xmls.py" "$OUT/roteiros/xml" >/dev/null 2>&1
  grep -vE '^(#|$)' "$D/roteiros.tsv" | while IFS= read -r linha; do   # tabs vazias contam (IFS=tab colapsaria)
    script=$(printf '%s' "$linha" | cut -f1); modo=$(printf '%s' "$linha" | cut -f2); args=$(printf '%s' "$linha" | cut -f3); desc=$(printf '%s' "$linha" | cut -f4)
    [ -f "$REPO/scripts/$script" ] || { printf '%s\tausente\t0\t0\t%s\n' "$script" "não existe nesta branch" >> "$OUT/roteiros.tsv"; continue; }
    case "$modo" in auto|auto-antes) ;; *) printf '%s\tmanual\t0\t0\t%s\n' "$script" "$desc" >> "$OUT/roteiros.tsv"; continue;; esac
    log=$OUT/roteiros/${script%.*}.log; ROUT=$OUT/roteiros/${script%.*}; RXML=$OUT/roteiros/xml; mkdir -p "$ROUT"
    case "$script" in
      *.py) timeout 900 docker run --rm --network host -v "$REPO/scripts:/scripts:ro" -v "$ROUT:/out" mcr.microsoft.com/playwright/python:v1.55.0-noble sh -c "pip install -q playwright==1.55.0 >/dev/null 2>&1; python3 /scripts/$script $BASE /out" > "$log" 2>&1; ex=$? ;;
      *) BASE=$BASE ANTES=$ANTES_URL DB_CT=$DB_CT APP_CT=$APP OUT=$ROUT XML=$RXML timeout 900 bash "$REPO/scripts/$script" $args > "$log" 2>&1; ex=$? ;;
    esac
    # só linhas de item: a linha de total ("52 ✅ / 2 ❌") não conta
    okn=$(grep -c '^✅' "$log"); fail=$(grep -c '^❌' "$log"); st=ok; { [ "$ex" != 0 ] || [ "$fail" != 0 ]; } && st=falha
    printf '%s\t%s\t%s\t%s\texit %s · %s\n' "$script" "$st" "$okn" "$fail" "$ex" "$desc" >> "$OUT/roteiros.tsv"
    printf '   %s %s: %s ✅ / %s ❌ (exit %s)\n' "$([ $st = ok ] && echo ✅ || echo ❌)" "$script" "$okn" "$fail" "$ex"
  done
fi
# ---------------------------------------------------------------- smoke: todas as telas × perfis (puppeteer)
if faz smoke; then
  cor 36 "-- smoke de todas as telas × perfis (puppeteer, ~10 min)"
  docker exec "$APP" php artisan route:list --json > "$OUT/rotas.json" 2>/dev/null
  ANTES_TSV=""; case "$FASE" in depois) ANTES_TSV=$OUTBASE/$ROTULO/antes/smoke.tsv;; pos-deploy) ANTES_TSV=$OUTBASE/$ROTULO/depois/smoke.tsv;; esac
  SMOKE_ANTES=$ANTES_TSV BASE=$BASE DB_CT=$DB_CT ROTAS=$OUT/rotas.json OUT=$OUT PERFIS=${PERFIS:-} ONLY=${ONLY:-} node "$D/smoke-telas.mjs" > "$OUT/smoke.log" 2>&1; echo $? > "$OUT/smoke.exit"
  grep -E '^smoke:|^  (❌|⚠)' "$OUT/smoke.log" | head -40 | sed 's/^/   /'
fi
docker logs "$APP" > "$OUT/app.log" 2>&1
contar_prod > "$OUT/producao-depois.txt"; diff "$OUT/producao-antes.txt" "$OUT/producao-depois.txt" > "$OUT/producao-diff.txt" || true
if [ "${MANTER:-0}" = 1 ]; then trap - EXIT; cor 33 "MANTER=1: ambiente $NOME fica no ar em $BASE (derrube com: tools/validar/ambiente.sh derrubar $NOME)"; else "$D/ambiente.sh" derrubar "$NOME" >/dev/null; trap - EXIT; fi

ETAPAS=""; [ -f "$OUT/phpunit.txt" ] && ETAPAS=$ETAPAS,phpunit; [ -f "$OUT/lint.txt" ] && ETAPAS=$ETAPAS,lint
[ -f "$OUT/roteiros.tsv" ] && ETAPAS=$ETAPAS,roteiros; [ -f "$OUT/smoke.json" ] && ETAPAS=$ETAPAS,smoke; ETAPAS=${ETAPAS#,}
python3 "$D/relatorio.py" "$OUT" "$ETAPAS" && cat "$OUT/relatorio.md"
