#!/usr/bin/env python3
"""Validação ✅/❌ — tela "Pagamento e entrega" do lojista (16/11/2026).

Ambiente DESCARTÁVEL com o seed demo (o script apaga as integrações das empresas 1 e 99). NUNCA em produção:
    BASE=http://<ip do container> MYSQL=ec-mysql APP=hm-app BANCO=erp_homolog_teste python3 scripts/validar-pagamento-entrega-16nov.py

Não chama provedor nenhum: os botões "Testar" só são exercitados no caminho em que o ERP recusa antes
de sair (credencial faltando). O que NÃO fica provado aqui: conexão real com Sicredi, Asaas, InfinitePay,
Uber e a volta da autorização do Melhor Envio.

Cobre: quem entra (dono sim; gerente, vendedor, deslogado e admin da plataforma não); os cinco cartões;
salvar de cada provedor gravando na empresa do usuário; segredo cifrado e nunca devolvido na tela;
upload do certificado; "falta completar" × "ligado"; escolha do cartão com os dois ligados; IDs do Uber
invertidos; InfiniteTag inválida; Melhor Envio não liberado; ação inexistente; isolamento entre empresas;
a aba Integração do admin continua gravando e o lojista não alcança a rota do admin.
"""
import http.cookiejar, os, re, subprocess, sys, urllib.error, urllib.parse, urllib.request, uuid

BASE = os.environ.get("BASE", "http://10.213.80.8")
MYSQL = os.environ.get("MYSQL", "ec-mysql")
APP = os.environ.get("APP", "hm-app")
BANCO = os.environ.get("BANCO", "erp_homolog_teste")
T = "/app/configuracoes/pagamento-entrega"
ok = falhas = 0


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
    return r[0][0] if r else ""


def php(codigo):
    boot = "<?php require '/var/www/vendor/autoload.php'; $app = require '/var/www/bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();\n"
    r = subprocess.run(["docker", "exec", "-i", APP, "php"], input=boot + codigo, capture_output=True, text=True)
    return (r.stdout + r.stderr).strip()


def artisan(*args):
    r = subprocess.run(["docker", "exec", APP, "php", "artisan", *args], capture_output=True, text=True)
    return r.stdout + r.stderr


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

    def post(self, path, dados=None, arquivos=None):
        """POST de formulário; com `arquivos` ({campo: (nome, bytes)}) vai como multipart."""
        pares = [("_token", self.token or "")] + list((dados or {}).items())
        if not arquivos:
            return self._req(urllib.request.Request(BASE + path, data=urllib.parse.urlencode(pares).encode(), method="POST"))
        b = "----roteiro" + uuid.uuid4().hex
        corpo = b""
        for k, v in pares:
            corpo += f'--{b}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode()
        for k, (nome, conteudo) in arquivos.items():
            corpo += f'--{b}\r\nContent-Disposition: form-data; name="{k}"; filename="{nome}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode() + conteudo + b"\r\n"
        corpo += f"--{b}--\r\n".encode()
        req = urllib.request.Request(BASE + path, data=corpo, method="POST")
        req.add_header("Content-Type", "multipart/form-data; boundary=" + b)
        return self._req(req)

    def login(self, email, senha):
        self.get("/login")
        st, hd, _ = self.post("/login", {"email": email, "password": senha})
        if st == 429:
            artisan("cache:clear")
            self.get("/login")
            st, hd, _ = self.post("/login", {"email": email, "password": senha})
        return st, hd.get("Location", "")

    def tela(self):
        """A tela do lojista (já com a mensagem da última ação, que vem na sessão)."""
        return self.get(T)[2]


def gw(provedor, campo, empresa=1):
    return um(f"select IFNULL({campo},'') from empresa_gateways where empresa_id={empresa} and provedor='{provedor}'")


def cartao(html, provedor):
    m = re.search(r'data-provedor="%s".*?(?=data-provedor="|\Z)' % re.escape(provedor), html, re.S)
    return m.group(0) if m else ""


def estado(html, provedor):
    m = re.search(r'data-estado="(\w+)"', cartao(html, provedor))
    return m.group(1) if m else None


def limpar():
    sql("delete from empresa_gateways where empresa_id in (1,99); delete from agente_ia_configs where empresa_id in (1,99);")
    subprocess.run(["docker", "exec", APP, "sh", "-c", "rm -rf /var/www/storage/app/private/gateways/1 /var/www/storage/app/private/gateways/99"])


print("\n== Cenário ==")
limpar()
artisan("cache:clear")
check("0 começa sem integração nas empresas de teste", um("select count(*) from empresa_gateways where empresa_id in (1,99)") == "0")
check("0 empresa de teste sem agente na plataforma (nada é avisado para fora)", um("select count(*) from agente_ia_configs") == "0")

# ───────────────────────── A. Quem entra ─────────────────────────
print("\n== A. Quem entra ==")
st, hd, _ = Nav().get(T)
check("A1 deslogado vai para o login", st == 302 and "/login" in hd.get("Location", ""), (st, hd.get("Location")))
dono, gerente, vendedor, adm = Nav(), Nav(), Nav(), Nav()
check("A2 dono entra", dono.login("dono@demo.com", "dono123")[0] == 302)
st, _, html = dono.get(T)
check("A3 dono abre a tela", st == 200 and "Pagamento e entrega" in html, st)
for p in ["pix", "asaas", "infinitepay", "melhor-envio", "uber"]:
    check(f"A4 cartão {p} na tela, desligado", estado(html, p) == "desligado", estado(html, p))
check("A5 sem os dois cartões ligados, a escolha de cartão não aparece", cartao(html, "cartao") == "")
check("A6 menu do dono tem Pagamento e entrega", "data-menu-pagamento-entrega" in html)
gerente.login("gerente@demo.com", "gerente123")
st, _, html_g = gerente.get(T)
check("A7 gerente não abre a tela", st == 403, st)
st, _, painel_g = gerente.get("/app/dashboard")
check("A8 menu do gerente não tem o item", st == 200 and "data-menu-pagamento-entrega" not in painel_g, st)
st, _, _ = gerente.post(T + "/asaas/salvar", {"api_key": "$aact_gerente", "ativo": "1"})
check("A9 gerente não grava", st == 403 and um("select count(*) from empresa_gateways where empresa_id=1") == "0", st)
vendedor.login("vendedor@demo.com", "vendedor123")
check("A10 vendedor não abre a tela", vendedor.get(T)[0] == 403)
adm.login("admin@ia365.com.br", "admin123")
st, hd, _ = adm.get(T)
check("A11 admin da plataforma é mandado para a lista de empresas", st == 302 and "/admin/empresas" in hd.get("Location", ""), (st, hd.get("Location")))

# ───────────────────────── B. PIX Sicredi ─────────────────────────
print("\n== B. PIX Sicredi ==")
st, hd, _ = dono.post(T + "/pix/testar")
html = dono.tela()
check("B1 testar sem credencial: recusa na hora, sem chamar o banco", st == 302 and "Configure credenciais" in html, st)
SEG = "segredo-sicredi-ROTEIRO-9731"
st, hd, _ = dono.post(T + "/pix/salvar", {"client_id": "cid-roteiro-555", "client_secret": SEG, "chave_pix": "12345678000199", "expiracao_segundos": "3600", "ativo": "1"})
check("B2 salvar volta para a tela do lojista, no cartão do PIX", st == 302 and hd.get("Location", "").endswith(T + "#pix"), hd.get("Location"))
html = dono.tela()
check("B3 grava na empresa do dono", gw("sicredi_pix", "chave_pix") == "12345678000199" and gw("sicredi_pix", "ativo") == "1" and gw("sicredi_pix", "expiracao_segundos") == "3600")
bruto = gw("sicredi_pix", "client_secret")
check("B4 segredo cifrado no banco", bruto != "" and SEG not in bruto and "cid-roteiro-555" not in gw("sicredi_pix", "client_id"), bruto[:30])
check("B5 segredo e Client ID não voltam na tela", SEG not in html and "cid-roteiro-555" not in html)
check("B6 campo avisa que está salvo", "•••• salvo" in cartao(html, "pix"))
check("B7 sem certificado: falta completar, com aviso", estado(html, "pix") == "incompleto" and "ainda faltam campos" in html, estado(html, "pix"))
st, _, _ = dono.post(T + "/pix/salvar", {"ativo": "1"}, {"certificado": ("cert.pem", b"-----BEGIN CERTIFICATE-----\nroteiro\n-----END CERTIFICATE-----\n"),
                                                         "chave_privada": ("chave.key", b"-----BEGIN PRIVATE KEY-----\nroteiro\n-----END PRIVATE KEY-----\n")})
html = dono.tela()
check("B8 certificado e chave sobem para a pasta da empresa", gw("sicredi_pix", "cert_path") == "gateways/1/sicredi-cert.pem" and gw("sicredi_pix", "key_path") == "gateways/1/sicredi-key.pem", gw("sicredi_pix", "cert_path"))
check("B9 salvar em branco não apaga o segredo", gw("sicredi_pix", "client_secret") != "" and php("echo App\\Models\\EmpresaGateway::where('empresa_id',1)->where('provedor','sicredi_pix')->first()->client_secret;") == SEG)
check("B10 completo: ligado", estado(html, "pix") == "ativo" and "✓ enviado" in html, estado(html, "pix"))
st, _, _ = dono.post(T + "/pix/salvar", {"chave_pix": "12345678000199"})
check("B11 desmarcar 'Ligado' desliga", gw("sicredi_pix", "ativo") == "0" and estado(dono.tela(), "pix") == "desligado")

# ───────────────────────── C. Asaas ─────────────────────────
print("\n== C. Asaas ==")
dono.post(T + "/asaas/testar")
check("C1 testar sem chave: recusa na hora", "Cole a api_key" in dono.tela())
CHAVE = "$aact_ROTEIRO_chave_4410"
st, hd, _ = dono.post(T + "/asaas/salvar", {"api_key": CHAVE, "sandbox": "1", "ativo": "1"})
html = dono.tela()
check("C2 salvar volta para o cartão do Asaas", hd.get("Location", "").endswith(T + "#asaas"), hd.get("Location"))
check("C3 grava cifrado e ligado", gw("asaas", "ativo") == "1" and CHAVE not in gw("asaas", "client_secret") and gw("asaas", "client_secret") != "")
check("C4 chave não volta na tela", CHAVE not in html)
tok = um("select JSON_UNQUOTE(JSON_EXTRACT(config,'$.webhook_token')) from empresa_gateways where empresa_id=1 and provedor='asaas'")
check("C5 código do aviso gerado e mostrado com o endereço", len(tok) == 40 and tok in html and "/api/integracao/v1/webhooks/asaas" in html, tok)
check("C6 selo ligado; conta de testes marcada", estado(html, "asaas") == "ativo" and um("select JSON_EXTRACT(config,'$.sandbox') from empresa_gateways where empresa_id=1 and provedor='asaas'") == "true")
check("C7 um cartão só: escolha não aparece e ele já é o usado", cartao(html, "cartao") == "" and php("echo App\\Services\\Pagamento\\CartaoPedidoService::provedorEmUso(1);") == "asaas")

# ───────────────────────── D. InfinitePay e escolha do cartão ─────────────────────────
print("\n== D. InfinitePay e escolha do cartão ==")
st, hd, _ = dono.post(T + "/infinitepay/salvar", {"handle": "nome com espaço!", "ativo": "1"})
html = dono.tela()
check("D1 InfiniteTag inválida: recusa com explicação e nada grava", "A InfiniteTag só tem letras" in html and gw("infinitepay", "ativo") == "", st)
st, hd, _ = dono.post(T + "/infinitepay/salvar", {"handle": "$loja_roteiro", "ativo": "1"})
html = dono.tela()
tag = um("select JSON_UNQUOTE(JSON_EXTRACT(config,'$.handle')) from empresa_gateways where empresa_id=1 and provedor='infinitepay'")
check("D2 InfiniteTag salva sem o $", tag == "loja_roteiro" and estado(html, "infinitepay") == "ativo", tag)
check("D3 ligar a InfinitePay não tira o cartão que já valia", php("echo App\\Services\\Pagamento\\CartaoPedidoService::provedorEmUso(1);") == "asaas")
check("D4 com os dois ligados a escolha aparece", 'name="provedor_cartao"' in cartao(html, "cartao"))
st, hd, _ = dono.post(T + "/cartao/escolher", {"provedor_cartao": "infinitepay"})
check("D5 escolher InfinitePay vale", hd.get("Location", "").endswith(T + "#cartao") and php("echo App\\Services\\Pagamento\\CartaoPedidoService::provedorEmUso(1);") == "infinitepay", hd.get("Location"))
st, _, _ = dono.post(T + "/cartao/escolher", {"provedor_cartao": "inventado"})
check("D6 provedor de cartão inventado é recusado", php("echo App\\Services\\Pagamento\\CartaoPedidoService::provedorEmUso(1);") == "infinitepay")

# ───────────────────────── E. Melhor Envio ─────────────────────────
print("\n== E. Melhor Envio ==")
liberado = php("echo (App\\Services\\Entrega\\MelhorEnvioService::app()['configurado'] ?? false) ? 'sim' : 'nao';") == "sim"
st, hd, _ = dono.post(T + "/melhor-envio/salvar", {"pacote_altura": "12", "pacote_largura": "20", "pacote_comprimento": "30", "pacote_peso": "0.8", "servicos": "1, 2", "seguro": "1", "ativo": "1"})
html = dono.tela()
cfg = um("select config from empresa_gateways where empresa_id=1 and provedor='melhor_envio'")
check("E1 pacote padrão e serviços gravam", '"pacote_altura": 12' in cfg and '"servicos": "1,2"' in cfg and '"seguro": true' in cfg, cfg)
check("E2 ligado sem conta autorizada: avisa e fica desligado na tela", "ainda não foi autorizada" in html and estado(html, "melhor-envio") == "desligado", estado(html, "melhor-envio"))
st, hd, _ = dono.post(T + "/melhor-envio/conectar")
if liberado:
    check("E3 conectar manda para a autorização do Melhor Envio", st == 302 and "melhorenvio" in hd.get("Location", ""), hd.get("Location"))
else:
    html = dono.tela()
    check("E3 plataforma sem o Melhor Envio liberado: lojista fica na própria tela com a explicação",
          st == 302 and T in hd.get("Location", "") and "/admin" not in hd.get("Location", "") and "Fale com o suporte" in html and "data-me-nao-liberado" in html, hd.get("Location"))
dono.post(T + "/melhor-envio/testar")
check("E4 testar sem conta: recusa na hora", "ainda não conectado" in dono.tela())
st, hd, _ = dono.post(T + "/melhor-envio/desconectar")
check("E5 desconectar desliga", gw("melhor_envio", "ativo") == "0" and hd.get("Location", "").endswith(T + "#melhor-envio"), hd.get("Location"))
st, hd, _ = dono.get("/integracao/melhor-envio/callback?state=invalido&code=x")
check("E6 volta do Melhor Envio com autorização inválida: lojista cai na tela dele, não no admin", st == 302 and T in hd.get("Location", ""), (st, hd.get("Location")))
st, hd, _ = adm.get("/integracao/melhor-envio/callback?state=invalido&code=x")
check("E7 a mesma volta para o admin continua indo para as empresas", st == 302 and "/admin/empresas" in hd.get("Location", ""), (st, hd.get("Location")))

# ───────────────────────── F. Uber Direct ─────────────────────────
print("\n== F. Uber Direct ==")
CID, CUST, USEG = "haH0SezgAbCdEfGhIjKlMnOpQrStUvWx", "56d97aa0-8311-4c2e-9a10-0123456789ab", "segredo-uber-ROTEIRO-2218"
st, hd, _ = dono.post(T + "/uber/salvar", {"client_id": CUST, "client_secret": USEG, "customer_id": CID, "ativo": "1"})
html = dono.tela()
check("F1 IDs invertidos: recusa com explicação e nada grava", "INVERTIDOS" in html and gw("uber_direct", "ativo") == "", st)
st, hd, _ = dono.post(T + "/uber/salvar", {"client_id": CID, "client_secret": USEG, "customer_id": CUST, "ceps": "64000-64099", "hora_inicio": "8", "hora_fim": "17.5", "hora_inicio_sab": "9", "hora_fim_sab": "12", "ativo": "1"})
html = dono.tela()
cfg = um("select config from empresa_gateways where empresa_id=1 and provedor='uber_direct'")
check("F2 grava credenciais cifradas, CEPs e horários", gw("uber_direct", "ativo") == "1" and USEG not in gw("uber_direct", "client_secret") and '"ceps": "64000-64099"' in cfg and '"hora_fim": 17.5' in cfg, cfg)
check("F3 segredo e Client ID não voltam; ID do usuário sim", USEG not in html and CID not in html and CUST in html)
check("F4 selo ligado", estado(html, "uber") == "ativo", estado(html, "uber"))

# ───────────────────────── G. Limites ─────────────────────────
print("\n== G. Limites ==")
check("G1 ação inexistente: 404", dono.post(T + "/asaas/apagar")[0] == 404)
check("G2 provedor inexistente: 404", dono.post(T + "/stripe/salvar", {"ativo": "1"})[0] == 404)
dono.post(T + "/asaas/salvar", {"api_key": "$aact_outra", "ativo": "1", "empresa_id": "99", "empresa": "99"})
check("G3 mandar outra empresa no formulário não alcança a outra empresa", um("select count(*) from empresa_gateways where empresa_id=99") == "0")
st, _, _ = dono.post("/admin/empresas/99/gateway-asaas", {"api_key": "$aact_invasor", "ativo": "1"})
check("G4 lojista não grava pela rota do admin", st in (302, 403) and um("select count(*) from empresa_gateways where empresa_id=99") == "0", st)
st, _, _ = dono.post("/admin/empresas/1/gateway-asaas", {"api_key": "$aact_invasor", "ativo": "0"})
check("G5 nem na própria empresa pela rota do admin", st in (302, 403) and gw("asaas", "ativo") == "1", st)
check("G6 só a empresa do dono tem integrações (5 provedores)", um("select count(*) from empresa_gateways where empresa_id=1") == "5" and um("select count(*) from empresa_gateways where empresa_id<>1") == "0")

# ───────────────────────── H. Admin continua igual ─────────────────────────
print("\n== H. Aba Integração do admin ==")
adm.get("/admin/empresas/99")  # pega o token do formulário do admin
st, hd, _ = adm.post("/admin/empresas/99/gateway-asaas", {"api_key": "$aact_admin_99", "sandbox": "0", "ativo": "1"})
check("H1 admin grava na empresa que escolheu e volta para a aba dela", st == 302 and "/admin/empresas/99" in hd.get("Location", "") and gw("asaas", "ativo", 99) == "1", (st, hd.get("Location")))
st, hd, _ = adm.post("/admin/empresas/99/gateway-infinitepay", {"handle": "tag99", "ativo": "1"})
check("H2 admin grava InfinitePay e volta para o admin", st == 302 and "/admin/empresas/99" in hd.get("Location", ""), hd.get("Location"))
st, hd, _ = adm.post("/admin/empresas/99/agente-ia/cartao", {"provedor_cartao": "infinitepay"})
check("H3 admin escolhe o cartão da empresa", st == 302 and "/admin/empresas/99" in hd.get("Location", "") and php("echo App\\Services\\Pagamento\\CartaoPedidoService::provedorEmUso(99);") == "infinitepay", hd.get("Location"))
st, hd, _ = adm.post("/admin/empresas/99/gateway-melhor-envio", {"pacote_altura": "5", "ativo": "0"})
check("H4 admin grava Melhor Envio", st == 302 and "/admin/empresas/99" in hd.get("Location", "") and gw("melhor_envio", "ativo", 99) == "0", hd.get("Location"))
st, _, html = adm.get("/admin/empresas/99")
check("H5 aba Integração do admin abre", st == 200 and "gateway-asaas" in html, st)
check("H6 o que o admin fez na 99 não mexeu na 1", gw("asaas", "ativo") == "1" and php("echo App\\Services\\Pagamento\\CartaoPedidoService::provedorEmUso(1);") == "infinitepay")
st, _, html = dono.get(T)
check("H7 tela do dono segue abrindo com tudo o que ele ligou", st == 200 and [estado(html, p) for p in ["pix", "asaas", "infinitepay", "melhor-envio", "uber"]] == ["desligado", "ativo", "ativo", "desligado", "ativo"],
      [estado(html, p) for p in ["pix", "asaas", "infinitepay", "melhor-envio", "uber"]])

limpar()
print(f"\n{'✅' if not falhas else '❌'} {ok} ok, {falhas} falha(s)")
sys.exit(1 if falhas else 0)
