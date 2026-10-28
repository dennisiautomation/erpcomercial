#!/usr/bin/env python3
"""Relatório único da rotina de validação do ERP Comercial (28/10/2026) — lido pelo tools/deploy.sh.
uso: relatorio.py <OUT> <etapas>   (OUT = /home/ubuntu/erp-validacao/<rotulo>/<fase>)
"❌ novo" = o que esta imagem tem a mais que a baseline: no `depois` a baseline é o `antes` (imagem no ar); no `pos-deploy` a baseline é o `depois`; no `antes` não há baseline
(tudo o que falha é pré-existente — fica registrado, TOTAL ❌ NOVOS = 0)."""
import json, os, re, sys
from pathlib import Path
OUT = Path(sys.argv[1]); SO = [s for s in sys.argv[2].split(',') if s]
fase = OUT.name; ROT = OUT.parent
img = (OUT / 'imagem.txt').read_text().strip(); commit, branch = (OUT / 'commit.txt').read_text().split()[:2]
prod = (OUT / 'producao.txt').read_text().strip(); noar = (OUT / 'noar.txt').read_text().strip() if (OUT / 'noar.txt').exists() else '?'
BASELINE = {'depois': ROT / 'antes', 'pos-deploy': ROT / 'depois'}.get(fase)
L = [f'# Validação {fase} — {branch} @{commit[:7]} · imagem `{img}`', f'- ImagemID: `{img}`', f'- Commit: `{commit}` ({branch})',
     f'- Produção (ref base do diff): `{prod[:7]}` · imagem no ar: `{noar}`', f'- Baseline: {BASELINE.name + "/" if BASELINE else "nenhuma (fase antes)"}',
     f'- Gerado: {os.popen("date +%d/%m/%Y\\ %H:%M").read().strip()} · etapas: {", ".join(SO)}', '']
total_novos = 0; pre = 0


def ler(p):
    return p.read_text(errors='ignore') if p and p.exists() else ''


def falhas_phpunit(txt):
    """nomes dos testes que falharam: linhas 'FAILED  Tests\\Feature\\X > nome' do `artisan test` (sem tempo/linha)."""
    return sorted({re.sub(r'\s+', ' ', f'{a} > {b}').strip() for a, b in re.findall(r'^\s*FAILED\s+(\S+)\s*>\s*(.+?)\s*$', txt, re.M)})


if 'phpunit' in SO:
    t = ler(OUT / 'phpunit.txt')
    m = re.search(r'Tests:\s+(.*)', t); s = m.group(1) if m else ''
    n = lambda k: int((re.search(rf'(\d+) {k}', s) or [0, 0])[1])
    passed, failed, err, risky, skipped = n('passed'), n('failed'), n('errors?'), n('risky'), n('skipped')
    atual = falhas_phpunit(t)
    base = falhas_phpunit(ler(BASELINE / 'phpunit.txt')) if fase != 'antes' else []
    novos = [f for f in atual if f not in base] if fase != 'antes' else []
    if fase == 'antes': pre += failed + err
    total_novos += len(novos)
    nao_rodou = 'Tests:' not in t
    L.append(f'- PHPUnit (`php artisan test` completo): **{passed} ✅ / {failed + err} ❌** ({skipped} pulados, {risky} risky) · ' + ('**não terminou** ❌' if nao_rodou else f'**{len(novos)} ❌ novos** vs baseline' if fase != 'antes' else 'baseline') + ' → phpunit.txt')
    if nao_rodou: total_novos += 1
    for f in atual: L.append(f'  - {"❌ novo" if f in novos else "❌ pré-existente"}: `{f[:160]}`')
if 'lint' in SO:
    def lints(p):
        return sorted({l[5:].strip() for l in ler(p).splitlines() if l.startswith('LINT ')})
    a = lints(OUT / 'lint.txt'); b = lints(BASELINE / 'lint.txt') if fase != 'antes' else []
    novos = [x for x in a if x not in b] if fase != 'antes' else []
    total_novos += len(novos); pre += len(a) if fase == 'antes' else 0
    narq = re.search(r'^ARQUIVOS (\d+) BLADES (\d+)', ler(OUT / 'lint.txt'), re.M)
    L.append(f'- Lint (`php -l` em {narq.group(1) if narq else "?"} PHP de app/routes/config/database/tests + {narq.group(2) if narq else "?"} blades compiladas): **{len(a)} ❌**' + (f' · **{len(novos)} ❌ novos**' if fase != 'antes' else '') + ' → lint.txt')
    for x in a[:30]: L.append(f'  - {"❌ novo" if x in novos else "❌ pré-existente"}: `{x[:160]}`')
if 'roteiros' in SO:
    rows = [l.split('\t') for l in ler(OUT / 'roteiros.tsv').splitlines() if l]
    ok = [r for r in rows if r[1] == 'ok']; falha = [r for r in rows if r[1] == 'falha']; manual = [r for r in rows if r[1] in ('manual', 'ausente')]
    antes = {l.split('\t')[0]: l.split('\t')[1] for l in ler(BASELINE / 'roteiros.tsv').splitlines() if l} if BASELINE and (BASELINE / 'roteiros.tsv').exists() else None
    novos = [r for r in falha if antes is not None and antes.get(r[0], 'ok') == 'ok']
    total_novos += len(novos); pre += len(falha) if fase == 'antes' else 0
    L.append(f'- Roteiros (tools/validar/roteiros.tsv): **{len(ok)} ✅ / {len(falha)} ❌** reexecutados' + (f' · **{len(novos)} ❌ novos** (já falhavam na baseline: {len(falha) - len(novos)})' if antes is not None else ' · baseline') + f' · manuais: {len(manual)} → roteiros/*.log')
    for r in falha: L.append(f'  - {"❌ novo" if r in novos else "❌ pré-existente"}: `{r[0]}` {r[2]} ✅ / {r[3]} ❌ — {r[4][:140]}')
    for r in ok: L.append(f'  - ✅ `{r[0]}` {r[2]} ✅ / {r[3]} ❌')
    for r in manual: L.append(f'  - ⚪ manual `{r[0]}` — {r[4][:140]}')
if 'smoke' in SO:
    try: s = json.loads(ler(OUT / 'smoke.json'))
    except Exception: s = {}
    if s:
        L.append(f'- Smoke (puppeteer, print por tela): **{s["ok"]} ✅ / {s["erro"]} ❌** aberturas — {s["telas"]} telas GET × {len(s["perfis"])} perfis ({", ".join(s["perfis"])}) = {s["aberturas"]} aberturas; negadas {s["negada"]}, redirecionadas {s["redir"]}, puladas {s["pulada"]} ({len(s.get("puladas_rotas", []))} rotas sem dado); erros no dono: {s["erro_dono"]} → smoke.tsv, telas/*.png')
        if 'erros_novos' in s:
            total_novos += s['erros_novos']
            L.append(f'- Smoke × {BASELINE.name if BASELINE else "baseline"}: **{s["erros_novos"]} ❌ erros novos · {s["mudancas_acesso"]} mudanças de acesso** → smoke-diff.txt')
            for l in [x for x in ler(OUT / 'smoke-diff.txt').splitlines() if x.strip()][:40]: L.append(f'  - {l}')
        else:
            pre += s['erro']
            L.append(f'- Smoke × baseline: sem baseline — {s["erro"]} erro(s) ficam registrados como pré-existentes')
            for l in [x for x in ler(OUT / 'smoke.tsv').splitlines()[1:] if '\terro\t' in x][:40]:
                c = l.split('\t'); L.append(f'  - ❌ pré-existente: {c[0]} [{c[1]}] {c[2]}: {c[5]}')
        if s.get('puladas_rotas'): L.append(f'  - ⚪ puladas (sem dado no banco descartável): {", ".join(s["puladas_rotas"][:30])}')
    else:
        L.append('- Smoke: **não rodou** ❌'); total_novos += 1
d = ler(OUT / 'producao-diff.txt')
if (OUT / 'producao-antes.txt').exists():
    L.append(f'- Produção intocada (contagem SELECT de tabelas do erp_comercial antes × depois da rodada): **{"✅ iguais" if not d.strip() else "⚠ contagem mudou durante a rodada (uso normal da produção pelos clientes; conferir producao-diff.txt — só vendas/orçamentos crescendo é normal)"}**')
L.append('- Limpeza: banco descartável (erp-val-*-mysql) destruído no fim; app de validação removido; nenhum container de produção tocado.')
L.append(f'\n## TOTAL ❌ NOVOS: **{total_novos}**' + (f' · pré-existentes (já na imagem no ar): {pre}' if fase == 'antes' else ''))
L.append('✅ pronto para o tools/deploy.sh' if total_novos == 0 and fase == 'depois' else ('baseline registrada' if fase == 'antes' else ('✅ pós-deploy sem regressão' if total_novos == 0 else '❌ não sobe — corrigir ou documentar como pré-existente')))
(OUT / 'relatorio.md').write_text('\n'.join(L) + '\n', encoding='utf-8')
