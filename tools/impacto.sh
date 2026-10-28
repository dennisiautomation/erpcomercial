#!/usr/bin/env bash
# Mapa de impacto de uma branch do ERP Comercial (passo 1 da rotina) — ver tools/validar/impacto.py
#   tools/impacto.sh [branch] [--prod <ref>]      (padrão: branch atual × main = produção)
exec python3 "$(cd "$(dirname "$0")" && pwd)/validar/impacto.py" "$@"
