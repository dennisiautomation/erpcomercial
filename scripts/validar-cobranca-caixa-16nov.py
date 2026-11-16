#!/usr/bin/env python3
"""Validação ✅/❌ — COBRANÇA DA PLATAFORMA VINDA DO caixa.ia365 (16/11/2026).

Ambiente DESCARTÁVEL (banco só do teste, com o seed demo; o script mexe à vontade). NUNCA em produção:
    docker exec cb-app php artisan migrate:fresh --seed --force
    BASE=http://<ip do cb-app> MYSQL=ec-mysql APP=cb-app BANCO=erp_cobranca TOKEN=<CAIXA_IA365_TOKEN do container> \
        python3 scripts/validar-cobranca-caixa-16nov.py

O e-mail do ambiente é MAIL_MAILER=log: nada sai; o teste conta os "Subject:" no laravel.log.

Cobre: segredo; lista de empresas; vínculo; fatura emitida (sino + e-mail para dono, gerente, admin e
financeiro — e só eles); repetição não duplica; faixa por perfil; menu e tela Faturas; aviso de vencimento
e de atraso (uma vez cada); bloqueio com tolerância; tela de acesso suspenso com o botão de pagar; baixa que
libera na hora; baixa desfeita; histórico já pago não avisa; conferência cancela a que sumiu; desvincular;
empresa vinculada não gera fatura pelo ERP; admin (fatura manual, marcar paga, tolerância da vinculada);
isolamento entre empresas; validação do que chega.
"""
import datetime, email.header, http.cookiejar, json, os, re, subprocess, sys, urllib.error, urllib.parse, urllib.request

BASE = os.environ.get("BASE", "http://10.213.80.7")
MYSQL = os.environ.get("MYSQL", "ec-mysql")
APP = os.environ.get("APP", "cb-app")
BANCO = os.environ.get("BANCO", "erp_cobranca")
TOKEN = os.environ.get("TOKEN", "teste-segredo-caixa-erp")
API = "/api/integracao/v1/plataforma/caixa"
ok = falhas = 0
HOJE = datetime.date.today()


def dia(delta):
    return (HOJE + datetime.timedelta(days=delta)).isoformat()


def check(nome, cond, extra=""):
    global ok, falhas
    if cond:
        ok += 1
        print(f"✅ {nome}")
    else:
        falhas += 1
        print(f"❌ {nome}  {str(extra)[:400]}")


def sql(q):
    r = subprocess.run(["docker", "exec", "-i", MYSQL, "mysql", "--default-character-set=utf8mb4", "-uerp", "-perp", BANCO, "-N", "-B"],
                       input=q, capture_output=True, text=True)
    if r.returncode:
        raise RuntimeError(r.stderr)
    return [l.split("\t") for l in r.stdout.strip().splitlines() if l]


def um(q):
    r = sql(q)
    return r[0][0] if r else ""  # sem linha ou valor vazio


def php(codigo):
    boot = "<?php require '/var/www/vendor/autoload.php'; $app = require '/var/www/bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();\n"
    r = subprocess.run(["docker", "exec", "-i", APP, "php"], input=boot + codigo, capture_output=True, text=True)
    return (r.stdout + r.stderr).strip()


def artisan(*args):
    r = subprocess.run(["docker", "exec", APP, "php", "artisan", *args], capture_output=True, text=True)
    return r.stdout + r.stderr


def emails(assunto=None):
    """Quantos e-mails saíram (mailer log) — opcionalmente só os de um assunto (decodifica o cabeçalho)."""
    log = subprocess.run(["docker", "exec", APP, "cat", "storage/logs/laravel.log"], capture_output=True).stdout.decode("utf-8", "replace")
    assuntos = []
    for bruto in re.findall(r"^Subject: (.*(?:\r?\n[ \t].*)*)", log, re.M):
        assuntos.append(str(email.header.make_header(email.header.decode_header(re.sub(r"\r?\n[ \t]+", " ", bruto)))))
    return len([x for x in assuntos if assunto is None or assunto in x])


def destinatarios_do_log():
    r = subprocess.run(["docker", "exec", APP, "sh", "-c", "grep -a '^To: ' storage/logs/laravel.log || true"], capture_output=True, text=True).stdout
    return sorted(set(l[4:].strip() for l in r.splitlines()))


def api(caminho, corpo=None, token=TOKEN):
    req = urllib.request.Request(BASE + API + caminho, data=None if corpo is None else json.dumps(corpo).encode(), method="GET" if corpo is None else "POST")
    req.add_header("Accept", "application/json")
    if corpo is not None:
        req.add_header("Content-Type", "application/json")
    if token:
        req.add_header("Authorization", "Bearer " + token)
    try:
        with urllib.request.urlopen(req) as r:
            return r.status, json.loads(r.read().decode() or "{}")
    except urllib.error.HTTPError as e:
        corpo = e.read().decode("utf-8", "replace")
        try:
            return e.code, json.loads(corpo)
        except Exception:
            return e.code, {"_corpo": corpo[:300]}


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


class Nav:
    def __init__(self):
        self.cj = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cj), NoRedirect)
        self.token = None

    def _req(self, req):
        try:
            with self.op.open(req) as r:
                body, st, hd = r.read().decode("utf-8", "replace"), r.status, dict(r.headers)
        except urllib.error.HTTPError as e:
            body, st, hd = e.read().decode("utf-8", "replace"), e.code, dict(e.headers)
        m = re.search(r'name="csrf-token" content="([^"]+)"', body) or re.search(r'name="_token" value="([^"]+)"', body)
        if m:
            self.token = m.group(1)
        return st, hd, body

    def get(self, path):
        return self._req(urllib.request.Request(BASE + path))

    def segue(self, path, n=6):
        """GET seguindo redirecionamentos; devolve (status, caminho final, corpo)."""
        for _ in range(n):
            st, hd, body = self.get(path)
            if st in (301, 302, 303) and hd.get("Location"):
                path = urllib.parse.urlparse(hd["Location"]).path or "/"
                continue
            return st, path, body
        return st, path, body

    def post(self, path, dados=None, metodo=None):
        pares = [("_token", self.token or "")] + ([("_method", metodo)] if metodo else []) + list((dados or {}).items())
        return self._req(urllib.request.Request(BASE + path, data=urllib.parse.urlencode(pares).encode(), method="POST"))

    def login(self, email, senha):
        self.get("/login")
        st, hd, _ = self.post("/login", {"email": email, "password": senha})
        if st == 429:  # trava de muitas entradas pelo mesmo IP: o teste limpa o contador e tenta de novo
            artisan("cache:clear")
            self.get("/login")
            st, hd, _ = self.post("/login", {"email": email, "password": senha})
        return st, hd.get("Location", "")


def fatura(ref, situacao="aberta", vence=10, valor=710.0, empresa=1, **mais):
    corpo = {"empresa_id": empresa, "ref": ref, "situacao": situacao, "valor": valor, "vencimento": dia(vence),
             "descricao": f"Mensalidade ERP Comercial (ref {ref})", "competencia": dia(vence)[:7],
             "link": f"https://caixa.ia365.com.br/f/{ref}.abcdef0123456789abcdef", "pix_copia_cola": "00020126PIXTESTE" + str(ref),
             "linha_digitavel": "07790.00116 12345.678901 23456.789012 1 99990000071000", "tem_nota": True,
             "cliente_id": 77, "cliente_nome": "Cliente Demo no caixa"}
    corpo.update(mais)
    return api("/fatura", corpo)


def rotina():
    return artisan("plataforma:processar-cobrancas")


def f(ref, campo="status"):
    return um(f"select IFNULL({campo},'') from plataforma_faturas where caixa_ref={ref}")


def sinos(titulo=None):
    return int(um("select count(*) from notificacoes where tipo='fatura_plataforma'" + (f" and titulo='{titulo}'" if titulo else "")))


# ───────────────────────── Cenário ─────────────────────────
print("\n== Cenário ==")
saida = php(r"""
use App\Models\{Empresa, User, Unidade};
\DB::table('plataforma_faturas')->delete();
\DB::table('notificacoes')->where('tipo', 'fatura_plataforma')->delete();
Empresa::withoutGlobalScopes()->where('id', 1)->update(['caixa_cliente_id' => null, 'caixa_cliente_nome' => null, 'caixa_vinculado_em' => null,
    'cobranca_periodicidade' => null, 'cobranca_valor' => null, 'cobranca_bloqueio_automatico' => false, 'cobranca_tolerancia_dias' => 5, 'cobranca_suspensa_em' => null]);
\DB::table('users')->where('is_admin', 1)->update(['pode_ver_financeiro' => 1]);
$dono = User::withoutGlobalScopes()->where('email', 'dono@demo.com')->first();
foreach ([['financeiro@demo.com', 'financeiro', 'Fina Financeiro'], ['adminloja@demo.com', 'admin', 'Ada Admin da Loja'], ['inativo@demo.com', 'gerente', 'Ina Inativa']] as [$email, $perfil, $nome]) {
    if (! User::withoutGlobalScopes()->where('email', $email)->exists()) {
        $u = $dono->replicate(); $u->email = $email; $u->name = $nome; $u->perfil = $perfil; $u->is_admin = false; $u->password = 'teste123';
        $u->status = $email === 'inativo@demo.com' ? 'inativo' : 'ativo'; $u->save();
        foreach (\DB::table('unidade_user')->where('user_id', $dono->id)->get() as $l) { \DB::table('unidade_user')->insert(['user_id' => $u->id, 'unidade_id' => $l->unidade_id]); }
    }
}
if (! Empresa::withoutGlobalScopes()->where('cnpj', '11.222.333/0001-81')->exists()) {
    $e = Empresa::withoutGlobalScopes()->find(1)->replicate(); $e->cnpj = '11.222.333/0001-81'; $e->razao_social = 'Outra Empresa Ltda'; $e->nome_fantasia = 'Outra'; $e->save();
    $un = Unidade::withoutGlobalScopes()->where('empresa_id', 1)->first()->replicate(); $un->empresa_id = $e->id; $un->cnpj = '11.222.333/0002-62'; $un->save();
    $u = $dono->replicate(); $u->email = 'dono@outra.com'; $u->empresa_id = $e->id; $u->password = 'outra123'; $u->save();
    \DB::table('unidade_user')->insert(['user_id' => $u->id, 'unidade_id' => $un->id]);
}
echo 'OUTRA=' . Empresa::withoutGlobalScopes()->where('cnpj', '11.222.333/0001-81')->value('id');
file_put_contents(storage_path('logs/laravel.log'), '');
""")
m = re.search(r"OUTRA=(\d+)", saida)
check("cenário montado (financeiro, admin da loja, inativo e 2ª empresa)", bool(m), saida)
OUTRA = int(m.group(1)) if m else 0
RESP = sorted(x[0] for x in sql("select email from users where empresa_id=1 and status='ativo' and perfil in ('dono','gerente','admin','financeiro')"))
check("responsáveis da loja = dono, gerente, admin e financeiro ativos", RESP == sorted(["dono@demo.com", "gerente@demo.com", "financeiro@demo.com", "adminloja@demo.com"]), RESP)
N = len(RESP)

# ───────────────────────── A. Segredo e lista ─────────────────────────
print("\n== A. Segredo e lista de empresas ==")
st, _ = api("/empresas", token="")
check("A1 sem segredo → 401", st == 401, st)
st, _ = api("/empresas", token="errado")
check("A2 segredo errado → 401", st == 401, st)
st, j = api("/empresas")
check("A3 lista as empresas com CNPJ só em dígitos", st == 200 and any(e["id"] == 1 for e in j.get("empresas", [])) and all(e["cnpj"].isdigit() or e["cnpj"] == "" for e in j["empresas"]), j)
st, j = api("/fatura", {"empresa_id": 1, "ref": 900, "situacao": "aberta", "valor": 10, "vencimento": dia(5)}, token="errado")
check("A4 fatura com segredo errado não entra", st == 401 and f(900) == "", st)
st, j = api("/fatura", {"empresa_id": 1, "ref": 900, "situacao": "talvez", "valor": 0, "vencimento": "ontem"})
check("A5 dado ruim → 422 em JSON, sem gravar", st == 422 and "errors" in j and f(900) == "", j)
st, j = api("/fatura", {"empresa_id": 99999, "ref": 900, "situacao": "aberta", "valor": 10, "vencimento": dia(5)})
check("A6 empresa que não existe → 404", st == 404, (st, j))

# ───────────────────────── B. Vínculo ─────────────────────────
print("\n== B. Vínculo ==")
st, j = api("/vinculo", {"empresa_id": 1, "cliente_id": 77, "cliente_nome": "Cliente Demo no caixa"})
check("B1 vincular grava o cliente do caixa na empresa", st == 200 and um("select caixa_cliente_id from empresas where id=1") == "77", j)
st, j = api("/empresas")
check("B2 a lista passa a mostrar o vínculo", any(e["id"] == 1 and e["caixa_cliente_id"] == 77 for e in j["empresas"]), j)

# ───────────────────────── C. Fatura emitida ─────────────────────────
print("\n== C. Fatura emitida ==")
st, j = fatura(101, vence=10)
check("C1 fatura em aberto entra como pendente, origem caixa", st == 200 and j.get("criada") and f(101) == "pendente" and f(101, "origem") == "caixa", j)
check("C2 guarda link, PIX, linha do boleto e nota", f(101, "link").startswith("https://caixa.ia365.com.br/f/101.") and f(101, "pix_copia_cola").startswith("000201") and f(101, "tem_nota") == "1")
check(f"C3 sino 'Fatura disponível' para os {N} responsáveis", sinos("Fatura disponível") == N, sinos())
quem = sorted(x[0] for x in sql("select u.email from notificacoes n join users u on u.id=n.user_id where n.tipo='fatura_plataforma'"))
check("C4 o sino NÃO vai para caixa, vendedor nem usuário inativo", quem == RESP, quem)
check(f"C5 e-mail 'está disponível' para os {N} responsáveis", emails("está disponível") == N and emails() == N, emails())
check("C6 os e-mails foram para os responsáveis e só eles", destinatarios_do_log() == RESP, destinatarios_do_log())
st, j = fatura(101, vence=10)
check("C7 a mesma fatura de novo não duplica nem avisa de novo", st == 200 and not j.get("criada") and um("select count(*) from plataforma_faturas") == "1" and sinos() == N and emails() == N, (j, sinos(), emails()))
st, j = fatura(101, vence=12, valor=750.5)
check("C8 valor e vencimento mudados no caixa atualizam aqui (sem novo aviso)", f(101, "valor") == "750.50" and f(101, "vencimento") == dia(12) and emails() == N, (f(101, "valor"), f(101, "vencimento")))

# ───────────────────────── D. Faixa, menu e tela ─────────────────────────
print("\n== D. Faixa, menu e tela Faturas ==")
dono, gerente, cx, fin, outra = Nav(), Nav(), Nav(), Nav(), Nav()
dono.login("dono@demo.com", "dono123"); gerente.login("gerente@demo.com", "gerente123"); cx.login("caixa@demo.com", "caixa123")
fin.login("financeiro@demo.com", "teste123"); outra.login("dono@outra.com", "outra123")
st, onde, pg = dono.segue("/app/dashboard")
check("D1 dono vê a faixa azul com valor e botão", st == 200 and 'data-fatura-faixa="info"' in pg and "R$ 750,50" in pg and "data-fatura-ver" in pg, (st, onde))
check("D2 dono tem 'Faturas' no menu", "data-menu-faturas" in pg)
st, onde, pg = gerente.segue("/app/dashboard")
check("D3 gerente vê a faixa completa", 'data-fatura-faixa="info"' in pg and "R$ 750,50" in pg, (st, onde))
st, onde, pg = fin.segue("/app/dashboard")
check("D4 financeiro vê a faixa completa e o menu", 'data-fatura-faixa="info"' in pg and "data-menu-faturas" in pg, (st, onde))
st, onde, pg = cx.segue("/app/dashboard")
check("D5 caixa NÃO vê faixa nem menu com fatura só emitida", st == 200 and "data-fatura-faixa" not in pg and "data-menu-faturas" not in pg and "750,50" not in pg, (st, onde))
st, onde, pg = outra.segue("/app/dashboard")
check("D6 dono de OUTRA empresa não vê faixa", st == 200 and "data-fatura-faixa" not in pg, (st, onde))
st, _, pg = dono.segue("/app/faturas")
check("D7 tela Faturas: valor, link da fatura, PIX e boleto para copiar", st == 200 and "data-fatura-aberta" in pg and "R$ 750,50" in pg and "data-fatura-link" in pg and "data-fatura-pix" in pg and "data-fatura-boleto" in pg, st)
check("D8 na própria tela Faturas a faixa não repete", "data-fatura-faixa" not in pg)
st, _, _ = cx.segue("/app/faturas")
check("D9 caixa não abre a tela Faturas (403)", st == 403, st)
st, _, pg = outra.segue("/app/faturas")
check("D10 outra empresa abre a tela vazia ('Tudo em dia')", st == 200 and "data-faturas-em-dia" in pg and "750,50" not in pg, st)

# ───────────────────────── E. Vencimento e atraso ─────────────────────────
print("\n== E. Avisos de vencimento e de atraso ==")
rotina()
check("E1 rotina diária com fatura longe do vencimento não avisa nada", emails() == N, emails())
fatura(101, vence=2, valor=750.5)
rotina()
check(f"E2 a 2 dias do vencimento: e-mail 'vence em' para os {N}", emails("vence em") - emails("está disponível") == N, emails("vence em"))
check("E3 e sino 'Fatura vence em breve'", sinos("Fatura vence em breve") == N, sinos())
rotina(); rotina()
check("E4 rodar a rotina de novo não repete o aviso", sinos("Fatura vence em breve") == N and emails() == 2 * N, (sinos(), emails()))
_, _, pg = dono.segue("/app/dashboard")
check("E5 faixa fica amarela perto do vencimento", 'data-fatura-faixa="warning"' in pg, pg[:0])
fatura(101, vence=-1, valor=750.5)
rotina(); rotina()
check(f"E6 vencida: e-mail 'em atraso' para os {N}, uma vez", emails("em atraso") == N and emails() == 3 * N, emails())
_, _, pg = dono.segue("/app/dashboard")
check("E7 faixa do dono fica vermelha, sem botão de fechar", 'data-fatura-faixa="danger"' in pg and "venceu ontem" in pg and 'id="faturaBanner"' in pg and "btn-close" not in pg.split('id="faturaBanner"')[1].split("</div>\n    ")[0], "")
_, _, pg = cx.segue("/app/dashboard")
check("E8 caixa passa a ver só o aviso de atraso, SEM valor e sem botão", "data-fatura-restrita" in pg and "Avise o responsável" in pg and "750,50" not in pg and "data-fatura-ver" not in pg, "")
check("E9 sem bloqueio automático ligado, ninguém é suspenso", um("select IFNULL(cobranca_suspensa_em,'') from empresas where id=1") == "")

# ───────────────────────── F. Bloqueio ─────────────────────────
print("\n== F. Bloqueio e liberação ==")
sql("update empresas set cobranca_bloqueio_automatico=1, cobranca_tolerancia_dias=3 where id=1")
fatura(101, vence=-3, valor=750.5)
rotina()
check("F1 dentro da tolerância (3 dias) NÃO bloqueia", um("select IFNULL(cobranca_suspensa_em,'') from empresas where id=1") == "")
fatura(101, vence=-4, valor=750.5)
rotina(); rotina()
check("F2 passou da tolerância → empresa suspensa", um("select IFNULL(cobranca_suspensa_em,'') from empresas where id=1") != "")
check(f"F3 e-mail 'suspenso' para os {N}, uma vez", emails("suspenso") == N and emails() == 4 * N, emails())
st, onde, pg = dono.segue("/app/dashboard")
check("F4 dono cai na tela de acesso suspenso com o botão de pagar", onde == "/acesso-suspenso" and "data-pagar-fatura" in pg and "750,50" in pg, (st, onde))
st, onde, pg = cx.segue("/app/pdv")
check("F5 caixa também é bloqueado, sem ver valor nem botão", onde == "/acesso-suspenso" and "data-pagar-fatura" not in pg and "750,50" not in pg, (st, onde))
st, onde, pg = fin.segue("/app/faturas")
check("F6 financeiro bloqueado vê as pendências na tela de suspensão", onde == "/acesso-suspenso" and "data-pagar-fatura" in pg, (st, onde))
st, onde, _ = outra.segue("/app/dashboard")
check("F7 a outra empresa segue trabalhando", st == 200 and onde == "/app/dashboard", (st, onde))
st, j = fatura(101, "paga", vence=-4, valor=750.5, pago_em=dia(0))
check("F8 baixa do caixa: fatura paga e acesso liberado na hora", j.get("status") == "paga" and f(101, "pago_em") == dia(0) and um("select IFNULL(cobranca_suspensa_em,'') from empresas where id=1") == "", j)
check(f"F9 e-mail 'Pagamento confirmado' para os {N}", emails("Pagamento confirmado") == N and emails() == 5 * N, emails())
st, onde, pg = dono.segue("/app/dashboard")
check("F10 dono volta a entrar e a faixa some", st == 200 and onde == "/app/dashboard" and "data-fatura-faixa" not in pg, (st, onde))
st, _, pg = dono.segue("/app/faturas")
check("F11 tela Faturas: 'Tudo em dia' e a fatura no histórico de pagas", "data-faturas-em-dia" in pg and "data-faturas-pagas" in pg and "750,50" in pg, st)
fatura(101, "paga", vence=-4, valor=750.5, pago_em=dia(0))
check("F12 a mesma baixa de novo não avisa de novo", emails() == 5 * N, emails())

# ───────────────────────── G. Baixa desfeita, histórico, cancelada ─────────────────────────
print("\n== G. Baixa desfeita, histórico e cancelamento ==")
fatura(101, "aberta", vence=-4, valor=750.5)
check("G1 baixa desfeita no caixa: volta a pendente, sem data de pagamento", f(101) == "pendente" and f(101, "pago_em") == "")
rotina()
check("G2 com bloqueio ligado, a rotina suspende de novo (sem repetir e-mail de suspensão)", um("select IFNULL(cobranca_suspensa_em,'') from empresas where id=1") != "" and emails("suspenso") == N, emails("suspenso"))
fatura(101, "paga", vence=-4, valor=750.5, pago_em=dia(0))
check("G3 paga de novo: libera e confirma de novo", um("select IFNULL(cobranca_suspensa_em,'') from empresas where id=1") == "" and emails("Pagamento confirmado") == 2 * N, emails("Pagamento confirmado"))
antes = emails()
st, j = fatura(50, "paga", vence=-40, valor=500, pago_em=dia(-38))
check("G4 histórico: fatura que já chega paga entra sem avisar ninguém", j.get("criada") and f(50) == "paga" and emails() == antes and sinos() == sinos(), (j, emails()))
st, j = fatura(60, "cancelada", vence=5)
check("G5 cancelada que o ERP nunca viu é ignorada", j.get("status") == "ignorada" and f(60) == "", j)
fatura(61, vence=5); fatura(61, "cancelada", vence=5)
check("G6 fatura em aberto cancelada no caixa fica cancelada aqui", f(61) == "cancelada")
_, _, pg = dono.segue("/app/dashboard")
check("G7 fatura cancelada não gera faixa", "data-fatura-faixa" not in pg)

# ───────────────────────── H. Conferência e desvincular ─────────────────────────
print("\n== H. Conferência e desvincular ==")
fatura(201, vence=8); fatura(202, vence=9); fatura(203, vence=-10, valor=300)
rotina()
check("H1 3 em aberto, uma estourada → suspensa", um("select IFNULL(cobranca_suspensa_em,'') from empresas where id=1") != "")
st, j = api("/conferir", {"empresas": [{"empresa_id": 1, "refs": [201, 202]}]})
check("H2 conferência: a que sumiu do caixa (203) é cancelada aqui", j.get("canceladas") == 1 and f(203) == "cancelada" and f(201) == "pendente" and f(202) == "pendente", j)
check("H3 e, sem pendência estourada, o acesso é liberado", um("select IFNULL(cobranca_suspensa_em,'') from empresas where id=1") == "")
_, _, pg = dono.segue("/app/dashboard")
check("H4 com 2 em aberto a faixa mostra a mais antiga e avisa que há mais", 'data-fatura-faixa="info"' in pg and "Há mais 1 em aberto" in pg, "")
st, j = api("/conferir", {"empresas": [{"empresa_id": OUTRA, "refs": []}]})
check("H5 conferir outra empresa não toca nas faturas desta", f(201) == "pendente" and j.get("canceladas") == 0, j)
php(r"App\Models\PlataformaFatura::create(['empresa_id'=>1,'competencia'=>'2026-01','descricao'=>'Manual do admin','valor'=>99,'vencimento'=>now()->addDays(20),'status'=>'pendente']);")
st, j = api("/vinculo", {"empresa_id": 1, "cliente_id": None})
check("H6 desvincular: tira o vínculo e cancela as pendentes vindas do caixa", um("select IFNULL(caixa_cliente_id,'') from empresas where id=1") == "" and f(201) == "cancelada" and f(202) == "cancelada", j)
check("H7 a fatura lançada à mão no admin continua de pé", um("select status from plataforma_faturas where descricao='Manual do admin'") == "pendente")

# ───────────────────────── I. Geração do ERP × vínculo ─────────────────────────
print("\n== I. Empresa vinculada não gera fatura pelo ERP ==")
sql("delete from plataforma_faturas where empresa_id=1")  # a competência do mês fica livre para a geração do ERP
sql("update empresas set cobranca_periodicidade='mensal', cobranca_valor=710, cobranca_dia_vencimento=20, cobranca_geracao='automatica', cobranca_bloqueio_automatico=0 where id=1")
api("/vinculo", {"empresa_id": 1, "cliente_id": 77, "cliente_nome": "Cliente Demo no caixa"})
rotina()
check("I1 vinculada + geração automática: o ERP NÃO gera fatura", um("select count(*) from plataforma_faturas where empresa_id=1 and origem='erp'") == "0")
api("/vinculo", {"empresa_id": 1, "cliente_id": None})
antes = emails("está disponível")
rotina()
check("I2 sem vínculo, a geração automática do ERP continua como era", um("select count(*) from plataforma_faturas where empresa_id=1 and origem='erp' and status='pendente'") == "1")
check(f"I3 e a fatura gerada pelo ERP também avisa os {N} por e-mail", emails("está disponível") - antes == N, emails("está disponível") - antes)
rotina()
check("I4 rodar de novo não gera a mesma competência duas vezes", um("select count(*) from plataforma_faturas where empresa_id=1 and origem='erp'") == "1")
_, _, pg = dono.segue("/app/faturas")
check("I5 fatura sem link (manual): a tela manda falar com a IA365", "data-fatura-aberta" in pg and "data-fatura-link" not in pg and "fale com a IA365" in pg)

# ───────────────────────── J. Admin ─────────────────────────
print("\n== J. Admin ==")
adm = Nav()
st, _ = adm.login("admin@ia365.com.br", "admin123")
check("J0 admin entra", st == 302, st)
api("/vinculo", {"empresa_id": 1, "cliente_id": 77, "cliente_nome": "Cliente Demo no caixa"})
fatura(301, vence=6, valor=820)
st, _, pg = adm.segue("/admin/financeiro")
check("J1 financeiro do admin abre e marca a fatura do caixa (sem botão de baixa manual)", st == 200 and "data-fatura-do-caixa" in pg, st)
manual = um("select id from plataforma_faturas where empresa_id=1 and origem='erp' and status='pendente'")
antes = emails("Pagamento confirmado")
st, hd, _ = adm.post(f"/admin/financeiro/faturas/{manual}/pagar", {"forma_pagamento": "pix"})
check("J2 marcar paga no admin (fatura manual) continua funcionando", st == 302 and um(f"select status from plataforma_faturas where id={manual}") == "paga", st)
check(f"J3 e avisa os {N} do pagamento confirmado", emails("Pagamento confirmado") - antes == N, emails("Pagamento confirmado") - antes)
# A empresa demo tem CNPJ fictício (o formulário do admin recusa, com razão): este trecho usa a 2ª empresa.
api("/vinculo", {"empresa_id": OUTRA, "cliente_id": 88, "cliente_nome": "Outra no caixa"})
st, _, pg = adm.segue(f"/admin/empresas/{OUTRA}/edit")
check("J4 cadastro da empresa no admin mostra o vínculo com o caixa", st == 200 and "data-vinculo-caixa" in pg and "Outra no caixa" in pg, st)
campos = dict(re.findall(r'<input[^>]*name="([^"\[\]]+)"[^>]*value="([^"]*)"', pg))
for nome, bloco in re.findall(r'<select[^>]*name="([^"\[\]]+)"[^>]*>(.*?)</select>', pg, re.S):
    sel = re.search(r'<option value="([^"]*)"[^>]*selected', bloco)
    campos[nome] = sel.group(1) if sel else ""
for nome, txt in re.findall(r'<textarea[^>]*name="([^"\[\]]+)"[^>]*>(.*?)</textarea>', pg, re.S):
    campos[nome] = txt
campos.pop("_token", None); campos.pop("_method", None)
campos.update({"cnpj": "11.222.333/0001-81", "regime_cobranca": "padrao"})  # o raspador pega o último rádio e outro campo de CNPJ
campos.update({"cobranca_periodicidade": "", "cobranca_valor": "", "cobranca_dia_vencimento": "", "cobranca_proxima_renovacao": "", "cobranca_bloqueio_automatico": "1", "cobranca_tolerancia_dias": "7"})
st, hd, corpo = adm.post(f"/admin/empresas/{OUTRA}", campos, metodo="PUT")
linha = sql(f"select IFNULL(cobranca_periodicidade,'-'), cobranca_bloqueio_automatico, cobranca_tolerancia_dias, IFNULL(caixa_cliente_id,'-') from empresas where id={OUTRA}")[0]
_, _, pg = adm.segue(f"/admin/empresas/{OUTRA}/edit")
check("J5 empresa vinculada SEM periodicidade guarda tolerância (7) e bloqueio ligado", st == 302 and linha == ["-", "1", "7", "88"], (st, linha, re.findall(r'<li>([^<]+)</li>', pg)[:4]))
fatura(401, vence=-7, valor=820, empresa=OUTRA, cliente_id=88, cliente_nome="Outra no caixa")
rotina()
check("J6 7 dias de atraso com tolerância 7: ainda não bloqueia", um(f"select IFNULL(cobranca_suspensa_em,'') from empresas where id={OUTRA}") == "")
fatura(401, vence=-8, valor=820, empresa=OUTRA, cliente_id=88, cliente_nome="Outra no caixa")
rotina()
check("J7 8 dias de atraso com tolerância 7 → suspensa (mesmo sem periodicidade no ERP)", um(f"select IFNULL(cobranca_suspensa_em,'') from empresas where id={OUTRA}") != "")
st, onde, _ = outra.segue("/app/dashboard")
check("J8 e o dono dela cai na tela de acesso suspenso", onde == "/acesso-suspenso", (st, onde))
check("J9 a empresa demo, sem atraso, não foi afetada", um("select IFNULL(cobranca_suspensa_em,'') from empresas where id=1") == "")
fatura(401, vence=-8, valor=820, empresa=OUTRA, situacao="paga", pago_em=dia(0), cliente_id=88, cliente_nome="Outra no caixa")
st, onde, _ = outra.segue("/app/dashboard")
check("J10 paga pelo caixa → liberada e o dono entra", um(f"select IFNULL(cobranca_suspensa_em,'') from empresas where id={OUTRA}") == "" and onde == "/app/dashboard", (st, onde))

# ───────────────────────── K. Telas vizinhas ─────────────────────────
print("\n== K. Telas vizinhas intactas ==")
for nome, nav, caminho in [("dashboard do dono", dono, "/app/dashboard"), ("produtos", dono, "/app/produtos"), ("meu plano", dono, "/app/plano"),
                           ("PDV do caixa", cx, "/app/pdv"), ("admin dashboard", adm, "/admin/dashboard"), ("admin empresas", adm, "/admin/empresas")]:
    st, onde, pg = nav.segue(caminho)
    check(f"K {nome} abre (200)", st == 200, (st, onde))
erros = subprocess.run(["docker", "exec", APP, "sh", "-c", "grep -a -c 'local.ERROR' storage/logs/laravel.log || true"], capture_output=True, text=True).stdout.strip()
check("K nenhum erro no log do sistema durante o roteiro", erros == "0", erros)

print(f"\n{ok} ✅ / {falhas} ❌")
sys.exit(1 if falhas else 0)
