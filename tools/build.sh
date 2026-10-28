#!/usr/bin/env bash
# Build da imagem de uma branch do ERP Comercial para a rotina de validação (28/10/2026).
#   tools/build.sh [rotulo]        → erp-com-app:<rotulo>-<AAAAMMDD-HHMM>  (rotulo padrão: branch atual)
# Antes do build, marca a imagem NO AR como erp-com-app:pre-<rotulo>-<AAAAMMDD> (regra do CLAUDE.md: a imagem do ar nunca
# pode virar órfã). O build usa o mesmo Dockerfile do docker-compose.prod.yml (docker/prod/Dockerfile, contexto = este
# checkout) e NÃO move a tag :latest — quem move é o tools/deploy.sh, depois do validar.sh depois com 0 ❌ novos.
set -euo pipefail
REPO=$(cd "$(dirname "$0")/.." && pwd)
ROTULO=${1:-$(git -C "$REPO" rev-parse --abbrev-ref HEAD | tr '/' '_')}
cor() { printf '\033[%sm%s\033[0m\n' "$1" "$2"; }
[ -f "$REPO/docker/prod/Dockerfile" ] || { cor 31 "docker/prod/Dockerfile não existe em $REPO"; exit 1; }
[ -z "$(git -C "$REPO" status --porcelain -- app routes config database resources public composer.json composer.lock docker)" ] || cor 33 "⚠ há mudanças não commitadas no checkout — a imagem vai levá-las (o deploy.sh exige commit = validado)"
NOAR=$(docker inspect erp-com-app --format '{{.Image}}' 2>/dev/null | cut -d: -f2 | cut -c1-12 || true)
if [ -n "$NOAR" ]; then
  PRE=erp-com-app:pre-$ROTULO-$(date +%Y%m%d)
  docker image inspect "$PRE" >/dev/null 2>&1 && PRE=$PRE-$(date +%H%M)
  docker tag "$NOAR" "$PRE"; cor 33 "imagem no ar $NOAR guardada como $PRE (rollback)"
fi
TAG=erp-com-app:$ROTULO-$(date +%Y%m%d-%H%M)
cor 36 "build $TAG (docker/prod/Dockerfile, contexto $REPO, commit $(git -C "$REPO" rev-parse --short HEAD))"
DOCKER_BUILDKIT=1 docker build -f "$REPO/docker/prod/Dockerfile" -t "$TAG" "$REPO"
ID=$(docker image inspect "$TAG" --format '{{.Id}}' | cut -d: -f2 | cut -c1-12)
cor 32 "✅ $TAG ($ID) — agora: tools/validar/validar.sh $TAG depois"
