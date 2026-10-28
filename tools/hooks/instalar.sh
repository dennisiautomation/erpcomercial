#!/usr/bin/env bash
# Instala/atualiza o hook do ERP Comercial no Claude Code do usuário (28/10/2026): copia bloquear-deploy-direto.sh para
# ~/.claude/hooks/erp-comercial-bloquear-deploy-direto.sh (caminho estável, fora do worktree) e registra em
# ~/.claude/settings.json (PreToolUse › Bash) SEM mexer nos hooks do JL e do CRM. Idempotente; backup em settings.json.bak-<data>.
set -euo pipefail
AQUI=$(cd "$(dirname "$0")" && pwd)
DEST=$HOME/.claude/hooks/erp-comercial-bloquear-deploy-direto.sh
mkdir -p "$HOME/.claude/hooks"; cp "$AQUI/bloquear-deploy-direto.sh" "$DEST"; chmod +x "$DEST"
python3 - "$DEST" <<'PY'
import json, shutil, sys, datetime, os
p = f"{os.environ['HOME']}/.claude/settings.json"; dest = sys.argv[1]
shutil.copy(p, p + '.bak-' + datetime.date.today().strftime('%Y%m%d'))
d = json.load(open(p))
pre = d.setdefault('hooks', {}).setdefault('PreToolUse', [])
bash = next((x for x in pre if x.get('matcher') == 'Bash'), None)
if not bash: bash = {'matcher': 'Bash', 'hooks': []}; pre.append(bash)
antes = [h.get('command') for h in bash['hooks']]
bash['hooks'] = [h for h in bash['hooks'] if 'erp-comercial' not in h.get('command', '')]
bash['hooks'].append({'type': 'command', 'command': dest, 'timeout': 10})
json.dump(d, open(p, 'w'), indent=2, ensure_ascii=False); open(p, 'a').write('\n')
print('hooks PreToolUse/Bash agora:', *[h['command'] for h in bash['hooks']], sep='\n  ')
PY
echo "hook instalado em $DEST (vale para sessões novas do Claude Code; a sessão aberta recarrega hooks no próximo início)"
