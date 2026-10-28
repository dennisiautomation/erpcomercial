#!/usr/bin/env bash
# Hook PreToolUse (Bash) do Claude Code — ERP Comercial, 28/10/2026 (modelo: JL /root/jl-erp/tools/hooks e CRM Vacinas).
# Bloqueia, fora de tools/deploy.sh e tools/build.sh: `docker compose … up` da produção do ERP (erp-com-app /
# docker-compose.prod.yml / /root/erp), `docker build` / `docker compose build` do ERP, e `docker tag … erp-com-app:<tag>`
# (exceto tags de rollback pre-* — essas protegem). validar.sh/ambiente.sh (containers erp-val-*) continuam livres.
# Saída 2 = bloqueia e mostra a mensagem ao Claude. Não toca em comandos do JL-ERP nem do CRM (eles têm hook próprio).
ENTRADA=$(cat)
CMD=$(printf '%s' "$ENTRADA" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d.get("tool_input",{}).get("command",""))' 2>/dev/null)
CWD=$(printf '%s' "$ENTRADA" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("cwd",""))' 2>/dev/null)
# só o ERP Comercial
case "$CMD $CWD" in *jl-erp*|*crm-vacinas*) exit 0 ;; esac
case "$CMD $CWD" in *erp-com*|*erp-comercial*|*erpcomercial*|*/root/erp*|*wt/erp*|*/erp/*|*erp-validacao*) ;; *) exit 0 ;; esac
case "$CMD" in *tools/deploy.sh*|*tools/build.sh*|*tools/validar/*|*erp-val-*) exit 0 ;; esac

bloqueia() {
  cat >&2 <<MSG
✋ Bloqueado no ERP Comercial (regra do Dennis, 28/10/2026): $1
Fluxo obrigatório:  tools/impacto.sh → tools/validar/validar.sh <imagem-no-ar> antes → tools/build.sh → tools/validar/validar.sh <imagem-nova> depois (0 ❌ novos)
→ tools/deploy.sh <branch> --conferir (Claude)  →  tools/deploy.sh <branch> (Dennis). Ver CLAUDE.md (topo) e tools/README.md.
MSG
  exit 2
}
# docker tag … erp-com-app:<algo> (pre-* é rollback: liberado)
if printf '%s' "$CMD" | grep -Eq 'docker[[:space:]]+(image[[:space:]]+)?tag[^|;&]*[[:space:]]erp-com-app(:[^[:space:]]*)?([[:space:]]|$)'; then
  printf '%s' "$CMD" | grep -Eq 'docker[[:space:]]+(image[[:space:]]+)?tag[^|;&]*[[:space:]]erp-com-app:pre-' || bloqueia "docker tag … erp-com-app:<tag> fora do tools/build.sh/deploy.sh"
fi
# build da imagem do ERP (docker build / compose build)
if printf '%s' "$CMD" | grep -Eq 'docker[[:space:]]+(image[[:space:]]+)?build([[:space:]]|$)|docker[ -]compose[^|;&]*[[:space:]]build([[:space:]]|$)'; then
  bloqueia "build da imagem fora do tools/build.sh (que guarda a imagem do ar como pre-<rotulo>-<data> antes)"
fi
# docker compose … up da produção (não os descartáveis)
if printf '%s' "$CMD" | grep -Eq 'docker[ -]compose[^|;&]*[[:space:]]up([[:space:]]|$)'; then
  case "$CMD" in *homolog*|*treinamento*|*agente-ia*|*val-*|*qa-*) ;; *) bloqueia "docker compose … up da produção fora do tools/deploy.sh" ;; esac
fi
exit 0
