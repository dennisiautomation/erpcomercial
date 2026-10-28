#!/usr/bin/env python3
"""Mapa de impacto de uma branch do ERP Comercial (28/10/2026) — passo 1 da rotina obrigatória do CLAUDE.md.
uso: tools/impacto.sh [branch] [--prod <ref>]   (padrão: branch atual × main, que é a produção)
Só leitura. Compara `git diff <prod>...<branch>` e escreve /home/ubuntu/erp-validacao/<rotulo>/impacto.md com:
arquivos; rotas (routes/*.php + route:list da branch, rodado num container da imagem no ar com sqlite em memória);
controllers e views (tela mudada, telas que a incluem, tela nova × antiga quando há ?visual=); migrations; jobs/crons/
commands/observers; webhooks e integrações (Focus, InfinitePay, Melhor Envio, Gersen, Asaas, Uber, Agente IA/OpenAI,
WhatsApp); perfis (middleware permission:modulo,acao × matriz do CheckPermission); empresas atingidas (SELECT na produção,
só leitura); alertas 🔴/🟡."""
import json, re, subprocess, sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
args = [a for a in sys.argv[1:]]
PROD = 'main'
if '--prod' in args:
    i = args.index('--prod'); PROD = args[i + 1]; del args[i:i + 2]
BRANCH = args[0] if args else subprocess.run(['git', '-C', str(REPO), 'rev-parse', '--abbrev-ref', 'HEAD'], capture_output=True, text=True).stdout.strip()


def sh(cmd, **kw):
    return subprocess.run(cmd, shell=True, capture_output=True, text=True, **kw).stdout


def git(*a):
    return subprocess.run(['git', '-C', str(REPO), *a], capture_output=True, text=True).stdout


prod_c = git('rev-parse', '--verify', f'{PROD}^{{commit}}').strip(); br_c = git('rev-parse', '--verify', f'{BRANCH}^{{commit}}').strip()
if not prod_c or not br_c:
    sys.exit(f'ref inválida: produção={PROD!r} branch={BRANCH!r}')
base = git('merge-base', prod_c, br_c).strip()
atras = int(git('rev-list', '--count', f'{br_c}..{prod_c}').strip() or 0)
status = git('diff', '--name-status', f'{base}', br_c).splitlines()
arquivos = [(l.split('\t')[0][0], l.split('\t')[-1]) for l in status if l.strip()]
todos = [f for _, f in arquivos]; novos = [f for s, f in arquivos if s == 'A']
diff_add = git('diff', '-U0', base, br_c, '--', 'app', 'routes', 'resources', 'config', 'database')
diff_add = '\n'.join(l for l in diff_add.splitlines() if l.startswith('+') and not l.startswith('+++'))


def ler(f):
    return git('show', f'{br_c}:{f}') if f in todos else (REPO / f).read_text(errors='ignore') if (REPO / f).exists() else ''


# --- fonte da branch (para includes/views/uso de classes)
arvore = git('ls-tree', '-r', '--name-only', br_c).splitlines()
src = {}
for f in arvore:
    if (f.startswith('app/') and f.endswith('.php')) or (f.startswith('resources/views/') and f.endswith('.blade.php')) or f.startswith('routes/'):
        src[f] = git('show', f'{br_c}:{f}')
view_nome = lambda f: f.replace('resources/views/', '').replace('.blade.php', '').replace('/', '.')
classe_de = lambda f: Path(f).stem

views = {view_nome(f) for f in todos if f.startswith('resources/views/')}
for _ in range(3):
    for f, t in src.items():
        if f.startswith('resources/views/') and any(re.search(r"@(include|includeIf|includeWhen|extends|component|each)\(\s*['\"]" + re.escape(v) + r"['\"]", t) or re.search(r'<x-' + re.escape(v.replace('components.', '')).replace('\\.', '[.-]') + r'[\s/>]', t) for v in views):
            views.add(view_nome(f))
classes = {classe_de(f) for f in todos if f.startswith('app/')}
usam = set()
for f, t in src.items():
    if not f.startswith('app/'):
        continue
    if any(re.search(r'\b' + re.escape(c) + r'\b', t) for c in classes if c != classe_de(f)):
        usam.add(classe_de(f))
    if any(re.search(r"view\(\s*['\"]" + re.escape(v) + r"['\"]", t) for v in views):
        usam.add(classe_de(f))
afetadas = classes | usam

# --- rotas da branch (route:list num container da imagem no ar, com o código da branch montado)
tmp = Path('/home/ubuntu/erp-validacao/.impacto-src'); tmp.mkdir(parents=True, exist_ok=True)
sh(f'rm -rf {tmp}/* && git -C {REPO} archive {br_c} app routes config bootstrap database | tar -x -C {tmp}')
img = sh("docker inspect erp-com-app --format '{{.Image}}'").strip() or 'erp-com-app:latest'
rj = sh(f"docker run --rm --entrypoint sh -v {tmp}/app:/var/www/app:ro -v {tmp}/routes:/var/www/routes:ro -v {tmp}/config:/var/www/config:ro {img} -c "
        "'cd /var/www && rm -f .env bootstrap/cache/config.php bootstrap/cache/routes-v7.php && composer dump-autoload -q 2>/dev/null; "
        "APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync LOG_CHANNEL=stderr php artisan route:list --json 2>/dev/null'")
try:
    rotas = json.loads(rj)
except Exception:
    rotas = []

# --- matriz de perfis
matriz = {}
cp = src.get('app/Http/Middleware/CheckPermission.php', '')
for m in re.finditer(r"'(\w+)'\s*=>\s*\[\s*((?:'[a-z]+'\s*=>\s*\[[^\]]*\],?\s*(?://[^\n]*\n)?)+)\]", cp):
    modulo = m.group(1); matriz[modulo] = {}
    for p in re.finditer(r"'([a-z]+)'\s*=>\s*\[([^\]]*)\]", m.group(2)):
        matriz[modulo][p.group(1)] = re.findall(r"'(\w+)'", p.group(2))


def perfis_da(r):
    mw = r.get('middleware', []); ps = set()
    uri = r.get('uri', '')
    if uri.startswith('admin'):
        return ['admin']
    for m in mw:
        if m.startswith('permission:'):
            mod, _, acao = m[11:].partition(','); acao = acao or 'ver'
            ps |= {p for p, acoes in matriz.get(mod, {}).items() if acao in acoes}
    if not ps:
        return ['todos logados'] if 'auth' in mw else ['público']
    return sorted(ps)


r_dir, r_ind = [], []
for r in rotas:
    act = r.get('action', ''); ctrl = act.split('@')[0].split('\\')[-1] if '@' in act else ''
    item = (r['method'].replace('|HEAD', ''), '/' + r['uri'].lstrip('/'), act.split('\\')[-1], ', '.join(perfis_da(r)))
    if ctrl and ctrl in classes:
        r_dir.append(item)
    elif ctrl and ctrl in afetadas:
        r_ind.append(item)
rotas_txt = [f for f in todos if f.startswith('routes/')]
rotas_add = [l[1:].strip() for l in diff_add.splitlines() if re.search(r'Route::(get|post|put|patch|delete|resource|match|any)\(', l)]

# --- telas (views) afetadas: nova × antiga
telas = sorted(views)
visual = {v for v in telas if re.search(r"visual\s*[=!]==?\s*['\"]classico|->visual|\?visual=", src.get('resources/views/' + v.replace('.', '/') + '.blade.php', ''))}
landing = any(v.startswith('site.') for v in telas)

migrations = [f for f in novos if f.startswith('database/migrations/')]
agendados = sorted({classe_de(f) for f, t in src.items() if f.startswith(('app/Console', 'app/Jobs', 'app/Listeners', 'app/Observers')) and (classe_de(f) in classes or any(re.search(r'\b' + re.escape(c) + r'\b', t) for c in classes))})
console = [f for f in todos if f == 'routes/console.php']
INTEG = {'Focus NFe (NF-e/NFC-e/NFS-e, webhooks Focus)': r'Focus|NFe|NFCe|NFSe|Fiscal|NotaFiscal|Manifestacao|Certificado|Sefaz',
         'InfinitePay (link de cartão, webhook)': r'InfinitePay|LinkCartao|CartaoPedido', 'Melhor Envio / Uber Direct (frete)': r'MelhorEnvio|Entrega|Uber|Frete',
         'Gersen / API de integração (tokens)': r'Gersen|IntegracaoToken|IntegracaoAgente|Api/', 'Asaas (cobrança da plataforma)': r'Asaas|Cobranca|FinanceiroPlataforma',
         'Agente IA / OpenAI / app.ia365 (WhatsApp)': r'AgenteIa|Embedding|Agente|Ia365|WhatsApp|Sincroniz', 'Caixa / PDV / pagamento': r'Caixa|Pdv|Pagamento|Troca|Vale|Split'}
integ = {k: [f for f in todos if re.search(v, f)] for k, v in INTEG.items()}
integ = {k: v for k, v in integ.items() if v}
webhooks = [f for f in todos if 'Webhook' in f or 'webhook' in f]

# --- empresas atingidas (produção, SELECT só leitura)
def prod_sql(q):
    return sh("docker exec erp-com-mysql sh -c 'mysql -uroot -p\"$MYSQL_ROOT_PASSWORD\" -N -B erp_comercial -e \"" + q.replace('"', '\\"') + "\" 2>/dev/null'")
empresas = [l.split('\t') for l in prod_sql("select e.id, e.nome_fantasia, e.status, e.plano, (select count(*) from configuracoes_fiscais c where c.empresa_id=e.id and c.emissao_fiscal_ativa=1), (select count(*) from empresa_gateways g where g.empresa_id=e.id), (select count(*) from agente_ia_configs a where a.empresa_id=e.id), (select count(*) from integracao_tokens t where t.empresa_id=e.id) from empresas e order by e.id").splitlines() if l.strip()]
def atingidas():
    if not empresas:
        return ['- (sem acesso de leitura ao banco de produção — liste à mão)']
    L = []
    for e in empresas:
        id_, nome, st, plano, fiscal, gw, ia, tok = e[:8]
        motivos = []
        if any(k.startswith('Focus') for k in integ) and fiscal != '0': motivos.append(f'fiscal ativo em {fiscal} loja(s)')
        if any(k.startswith('InfinitePay') or k.startswith('Asaas') for k in integ) and gw != '0': motivos.append('gateway de pagamento')
        if any(k.startswith('Agente') for k in integ) and ia != '0': motivos.append('agente IA')
        if any(k.startswith('Gersen') for k in integ) and tok != '0': motivos.append('token de integração')
        geral = bool(telas or r_dir or r_ind or migrations) and not integ
        if motivos or geral or not integ:
            L.append(f'- {id_} {nome} ({st}, {plano})' + (': ' + ', '.join(motivos) if motivos else ': telas/rotas gerais'))
    return L or ['- nenhuma empresa usa a integração tocada (confira: ' + ', '.join(integ) + ')']

alertas = []
if any(k.startswith(('Focus', 'InfinitePay', 'Asaas', 'Caixa')) for k in integ):
    alertas.append('🔴 Área fiscal/pagamento/caixa: nunca nota, PIX, cartão ou mensagem real em teste; roteiro tem de cobrir valores e status antes × depois; o validar.sh mata essas integrações no descartável.')
if webhooks: alertas.append('🔴 Webhook mudou: validar com payload gravado (Http::fake / curl) e conferir assinatura/idempotência.')
if rotas_txt: alertas.append('🟡 routes/ mudou: confira middleware permission: — o smoke × perfis acusa mudança de acesso e o deploy.sh só aceita com --aceitar-acesso.')
if 'app/Http/Middleware/CheckPermission.php' in todos: alertas.append('🟡 Matriz de perfis mudou: toda mudança de acesso aparece no smoke × perfis.')
if migrations: alertas.append(f'🟡 {len(migrations)} migration(s) nova(s): o entrypoint da imagem roda `migrate --force` no boot — backup do banco antes do deploy; rollback precisa de `migrate:rollback`.')
if console or agendados: alertas.append('🟡 Cron/fila/observer tocado: conferir `schedule:list` no descartável e o log do queue após o deploy.')
if atras: alertas.append(f'🟡 A branch está {atras} commit(s) atrás de {PROD}: rebase/merge antes de validar, senão o "depois" valida código velho.')
if any(f in ('docker/prod/Dockerfile', 'docker-compose.prod.yml', 'docker/prod/php.ini', 'docker/prod/nginx.conf', 'docker/prod/supervisord.conf') for f in todos): alertas.append('🔴 Dockerfile/compose/php.ini/nginx mudou: build obrigatório (tools/build.sh) e tag pre- da imagem no ar antes.')
if 'docs.md' not in todos: alertas.append('🟡 docs.md não está no diff: a entrega exige docs.md no MESMO commit.')

rotulo = BRANCH.replace('/', '_')
L = [f'# Impacto — {BRANCH} @{br_c[:7]} × produção {PROD} @{prod_c[:7]}', '',
     f'{len(todos)} arquivo(s) no diff ({len(novos)} novos) · base {base[:7]} · branch {atras} commit(s) atrás da produção', '',
     '## Arquivos', ''] + [f'- {s} {f}' for s, f in arquivos] + ['', '## Alertas', ''] + ([f'- {a}' for a in alertas] or ['- nenhum']) + ['',
     f'## Rotas DIRETAS ({len(r_dir)}) — controller mudado (perfis com acesso)', ''] + [f'- {m} {u}  ({a}) · {p}' for m, u, a, p in sorted(set(r_dir), key=lambda x: x[1])] + ['',
     f'## Rotas INDIRETAS ({len(r_ind)}) — controller usa classe/view mudada', ''] + [f'- {m} {u}  ({a}) · {p}' for m, u, a, p in sorted(set(r_ind), key=lambda x: x[1])] + ['',
     '## Rotas declaradas/alteradas em routes/*.php', ''] + ([f'- {l}' for l in rotas_add] or ['- nenhuma linha Route:: adicionada']) + ['',
     f'## Telas (views Blade) afetadas ({len(telas)}) — mudadas + quem as inclui', ''] + ([f'- {v}' + (' · tem versão nova E antiga (?visual=) — validar as duas' if v in visual else '') for v in telas] or ['- nenhuma']) + ['',
     '## Migrations', ''] + ([f'- {m}' for m in migrations] or ['- nenhuma']) + ['',
     '## Jobs / crons / commands / observers que tocam no código mudado', ''] + ([f'- {a}' for a in agendados] + [f'- {c} (scheduler)' for c in console] or ['- nenhum']) + ['',
     '## Webhooks e integrações', ''] + ([f'- {k}: ' + ', '.join(v) for k, v in integ.items()] + [f'- webhook: {w}' for w in webhooks] or ['- nenhuma integração externa tocada']) + ['',
     '## Perfis', ''] + [f'- {p}: {n} rota(s) direta(s)' for p, n in sorted(__import__("collections").Counter(x for _, _, _, ps in r_dir for x in ps.split(', ')).items())] + ['',
     '## Empresas atingidas (produção, só leitura)', ''] + atingidas() + ['',
     'Próximo passo: tools/validar/validar.sh <imagem-no-ar> antes → tools/build.sh → tools/validar/validar.sh <imagem-nova> depois → tools/deploy.sh <branch> --conferir.']
txt = '\n'.join(L)
print(txt)
out = Path('/home/ubuntu/erp-validacao') / rotulo; out.mkdir(parents=True, exist_ok=True)
(out / 'impacto.md').write_text(txt + '\n')
