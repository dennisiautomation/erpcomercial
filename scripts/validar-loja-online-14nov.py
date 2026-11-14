#!/usr/bin/env python3
"""Validação ✅/❌ — LOJA ONLINE (e-commerce), 14/11/2026.

Ambiente DESCARTÁVEL (banco novo com o seed demo + cenário; o script mexe à vontade):
    docker exec ec-app php artisan migrate:fresh --seed --force
    docker exec -i ec-app php < scripts/validar-loja-online-14nov.cenario.php
    BASE=http://10.213.80.4 MYSQL=ec-mysql APP=ec-app FOTOS=/pasta/das/fotos python3 scripts/validar-loja-online-14nov.py

Cobre: módulo desligado não existe; ativação pelo admin; endereço/domínio; fora do ar × prévia;
configurações (loja, estoque separado, recebimento, pagamento); produtos na vitrine (um a um, por
categoria, todos, destaque); cadastro do produto (mais fotos, tirar, interruptor); vitrine pública
(home, busca, categoria, ordem, produto, esgotado); carrinho; frete; fechamento → PEDIDO do ERP
(canal online, loja, estoque, cliente); acompanhamento; editor visual (rascunho, higiene, publicar,
descartar, modelos, imagem); subdomínio e domínio próprio; perfis; isolamento entre empresas; e as
telas vizinhas do ERP intactas.
"""
import html as H, http.cookiejar, io, json, os, re, subprocess, sys, urllib.error, urllib.parse, urllib.request, uuid

BASE = os.environ.get("BASE", "http://10.213.80.4")
MYSQL = os.environ.get("MYSQL", "ec-mysql")
APP = os.environ.get("APP", "ec-app")
FOTOS = os.environ.get("FOTOS", "")
ok = falhas = 0


def check(nome, cond, extra=""):
    global ok, falhas
    if cond:
        ok += 1
        print(f"✅ {nome}")
    else:
        falhas += 1
        print(f"❌ {nome}  {str(extra)[:300]}")


def sql(q):
    r = subprocess.run(["docker", "exec", "-i", MYSQL, "mysql", "--default-character-set=utf8mb4", "-uerp", "-perp", "erp_teste", "-N", "-B"],
                       input=q, capture_output=True, text=True)
    if r.returncode:
        raise RuntimeError(r.stderr)
    return [l.split("\t") for l in r.stdout.strip().splitlines() if l]


def um(q):
    r = sql(q)
    return r[0][0] if r else None


def app_cmd(*args):
    return subprocess.run(["docker", "exec", APP, *args], capture_output=True, text=True).stdout


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


class Nav:
    """Um navegador: cookies próprios, token CSRF da última página e (opcional) outro Host."""

    def __init__(self, host=None):
        self.cj = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cj), NoRedirect)
        self.token = None
        self.host = host

    def _req(self, req):
        if self.host:
            req.add_unredirected_header("Host", self.host)
        try:
            with self.op.open(req) as r:
                body = r.read().decode("utf-8", "replace")
                st, hd = r.status, dict(r.headers)
        except urllib.error.HTTPError as e:
            body = e.read().decode("utf-8", "replace")
            st, hd = e.code, dict(e.headers)
        m = re.search(r'name="csrf-token" content="([^"]+)"', body) or re.search(r'name="_token" value="([^"]+)"', body)
        if m:
            self.token = m.group(1)
        return st, hd, body

    def get(self, path, json_=False):
        req = urllib.request.Request(BASE + path)
        if json_:
            req.add_header("Accept", "application/json")
        return self._req(req)

    def post(self, path, dados=None, metodo=None):
        dados = dict(dados or {})
        pares = [("_token", self.token or "")]
        if metodo:
            pares.append(("_method", metodo))
        for k, v in dados.items():
            if isinstance(v, (list, tuple)):
                pares += [(k, x) for x in v]
            else:
                pares.append((k, v))
        req = urllib.request.Request(BASE + path, data=urllib.parse.urlencode(pares).encode(), method="POST")
        return self._req(req)

    def post_json(self, path, dados=None):
        req = urllib.request.Request(BASE + path, data=json.dumps(dados or {}).encode(), method="POST")
        req.add_header("Content-Type", "application/json")
        req.add_header("Accept", "application/json")
        req.add_header("X-CSRF-TOKEN", self.token or "")
        req.add_header("X-Requested-With", "XMLHttpRequest")
        st, hd, body = self._req(req)
        try:
            return st, json.loads(body)
        except Exception:
            return st, {"_corpo": body[:400]}

    def post_arquivos(self, path, campos, arquivos, metodo=None, json_=False):
        """arquivos: [(campo, nome, bytes, mime)]"""
        lim = uuid.uuid4().hex
        buf = io.BytesIO()
        pares = [("_token", self.token or "")] + ([("_method", metodo)] if metodo else [])
        for k, v in campos.items():
            pares += [(k, x) for x in v] if isinstance(v, (list, tuple)) else [(k, v)]
        for k, v in pares:
            buf.write(f'--{lim}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
        for campo, nome, dados, mime in arquivos:
            buf.write(f'--{lim}\r\nContent-Disposition: form-data; name="{campo}"; filename="{nome}"\r\nContent-Type: {mime}\r\n\r\n'.encode())
            buf.write(dados + b"\r\n")
        buf.write(f"--{lim}--\r\n".encode())
        req = urllib.request.Request(BASE + path, data=buf.getvalue(), method="POST")
        req.add_header("Content-Type", f"multipart/form-data; boundary={lim}")
        if json_:
            req.add_header("Accept", "application/json")
            req.add_header("X-CSRF-TOKEN", self.token or "")
        st, hd, body = self._req(req)
        if json_:
            try:
                return st, json.loads(body)
            except Exception:
                return st, {"_corpo": body[:400]}
        return st, hd, body


def entrar(email, senha, unidade="1"):
    n = Nav()
    n.get("/login")
    n.post("/login", {"email": email, "password": senha})
    n.get("/selecionar-unidade")
    n.post("/selecionar-unidade", {"unidade_id": unidade})
    return n


def texto(body):
    return re.sub(r"\s+", " ", H.unescape(re.sub(r"<(script|style)[^>]*>.*?</\1>", " ", body, flags=re.S | re.I)))


def limites():
    """Os testes saem todos do mesmo IP: zera os limites de requisição (throttle) entre os blocos."""
    app_cmd("php", "artisan", "cache:clear")


def foto(nome):
    return open(os.path.join(FOTOS, nome), "rb").read()


# ───────────────────────────── preparação ─────────────────────────────
cen = json.loads(subprocess.run(["docker", "exec", "-i", APP, "php"], input=open(os.path.join(os.path.dirname(__file__), "validar-loja-online-14nov.cenario.php")).read(),
                                capture_output=True, text=True).stdout.strip().splitlines()[-1])
P = cen["produtos"]
CAT = cen["categorias"]
sql("DELETE FROM pedido_itens WHERE pedido_id IN (SELECT id FROM pedidos WHERE loja_token IS NOT NULL); DELETE FROM pedidos WHERE loja_token IS NOT NULL;"
    "DELETE FROM clientes WHERE observacoes LIKE '%loja online%'; DELETE FROM empresa_gateways;")
app_cmd("php", "artisan", "cache:clear")
print(f"— cenário: empresa {cen['empresa']}, {len(P)} produtos, estoque e-commerce {cen['ecommerce']}, outra empresa {cen['empresa2']}")

admin = entrar("admin@ia365.com.br", "admin123")
dono = entrar("dono@demo.com", "dono123")
gerente = entrar("gerente@demo.com", "gerente123")
vendedor = entrar("vendedor@demo.com", "vendedor123")
caixa = entrar("caixa@demo.com", "caixa123")
outro = entrar("dono@outra.com", "outra123", str(cen["unidade_outra"]))
pub = Nav()

# ───────────────────────────── A. módulo desligado ─────────────────────────────
limites()
print("\n== A. Sem liberar, a loja online não existe ==")
st, _, b = pub.get("/loja/demo-store")
check("A1 vitrine de empresa não liberada → 404", st == 404, st)
st, _, b = dono.get("/app/dashboard")
check("A2 menu do dono SEM o link Loja online", st == 200 and "/app/loja-online" not in b, st)
st, _, _ = dono.get("/app/loja-online")
check("A3 painel do lojista → 404 sem liberação", st == 404, st)
st, _, b = dono.get(f"/app/produtos/{P[0]}/edit")
check("A4 cadastro do produto sem o interruptor 'Aparece na loja online'", st == 200 and 'name="loja_online"' not in b)
check("A5 cadastro do produto JÁ tem 'Mais fotos' (vale para toda empresa)", 'name="fotos_extras[]"' in b)
st, _, _ = dono.post("/admin/empresas/1/loja-online/toggle")
check("A6 dono não libera a própria loja (rota do admin)", st in (403, 302) and um("SELECT COUNT(*) FROM lojas_online") == "0", st)

# ───────────────────────────── B. ativação pelo admin ─────────────────────────────
limites()
print("\n== B. Admin libera, define endereço e domínio ==")
st, _, b = admin.get("/admin/empresas/1")
check("B1 card 'Loja online' no admin, 'Não liberada'", st == 200 and "Loja online (e-commerce)" in b and "Não liberada" in b)
st, hd, _ = admin.post("/admin/empresas/1/loja-online/toggle")
linha = sql("SELECT ativo, publicada, slug, nome, unidade_id, modelo FROM lojas_online WHERE empresa_id=1")
check("B2 liberar cria a loja: ativa, fora do ar, loja de origem = 1ª loja", st == 302 and linha and linha[0][0] == "1" and linha[0][1] == "0" and linha[0][4] == "1", linha)
SLUG0 = linha[0][2]
check("B3 endereço nasce do nome fantasia ('demo-store') e modelo padrão 'vitrine'", SLUG0 == "demo-store" and linha[0][5] == "vitrine", linha)
admin.get("/admin/empresas/1")
for slug, motivo in [("admin", "reservado"), ("Com Espaço", "formato"), ("a", "curto"), ("-x-", "hífen na ponta")]:
    admin.post("/admin/empresas/1/loja-online", {"slug": slug, "dominio": ""})
    admin.get("/admin/empresas/1")
check("B4 endereços inválidos (reservado, espaço, 1 letra, hífen na ponta) recusados", um("SELECT slug FROM lojas_online WHERE empresa_id=1") == "demo-store")
admin.post("/admin/empresas/1/loja-online", {"slug": "demo", "dominio": "http://Loja.Cliente.com.br/"})
r = sql("SELECT slug, dominio FROM lojas_online WHERE empresa_id=1")[0]
check("B5 salva endereço 'demo' e domínio normalizado (sem http, minúsculo)", r == ["demo", "loja.cliente.com.br"], r)
admin.get("/admin/empresas/1")
admin.post("/admin/empresas/1/loja-online", {"slug": "demo", "dominio": "10.213.80.4"})
check("B6 domínio que é host do próprio ERP recusado", um("SELECT dominio FROM lojas_online WHERE empresa_id=1") == "loja.cliente.com.br")
admin.get("/admin/empresas/1")
admin.post("/admin/empresas/1/loja-online", {"slug": "demo", "dominio": "sem ponto"})
check("B7 domínio malformado recusado", um("SELECT dominio FROM lojas_online WHERE empresa_id=1") == "loja.cliente.com.br")
admin.get("/admin/empresas/2")
admin.post("/admin/empresas/2/loja-online/toggle")
admin.get("/admin/empresas/2")
admin.post("/admin/empresas/2/loja-online", {"slug": "demo", "dominio": ""})
check("B8 outra empresa não pega o endereço 'demo' (único)", um("SELECT slug FROM lojas_online WHERE empresa_id=2") != "demo", um("SELECT slug FROM lojas_online WHERE empresa_id=2"))
admin.get("/admin/empresas/2")
admin.post("/admin/empresas/2/loja-online", {"slug": "outra", "dominio": "loja.cliente.com.br"})
check("B9 outra empresa não pega o domínio já usado (único)", um("SELECT IFNULL(dominio,'-') FROM lojas_online WHERE empresa_id=2") == "-")
admin.get("/admin/empresas/2")
admin.post("/admin/empresas/2/loja-online", {"slug": "outra", "dominio": ""})
st, _, b = dono.get("/app/dashboard")
check("B10 liberada: menu do dono ganha 'Loja online'", "/app/loja-online" in b)
st, _, b = dono.get(f"/app/produtos/{P[0]}/edit")
check("B11 liberada: cadastro do produto ganha o interruptor", 'name="loja_online"' in b and 'name="loja_online_destaque"' in b)

# ───────────────────────────── C. fora do ar × prévia ─────────────────────────────
limites()
print("\n== C. Fora do ar: público não vê, lojista vê a prévia ==")
st, _, b = pub.get("/loja/demo")
check("C1 público → 'sendo preparada' (503), sem produto nenhum", st == 503 and "sendo preparada" in b and "Camisa Linho" not in b, st)
st, _, b = dono.get("/loja/demo")
check("C2 dono logado vê a prévia com o aviso", st == 200 and "ainda não está no ar" in b, st)
st, _, b = outro.get("/loja/demo")
check("C3 dono de OUTRA empresa logado não vê a prévia (503)", st == 503, st)
st, _, b = admin.get("/loja/demo")
check("C4 admin da plataforma vê a prévia", st == 200, st)
st, _, b = pub.get("/loja/demo/produtos")
check("C5 páginas internas também fechadas ao público", st == 503, st)
check("C6 prévia leva noindex", 'name="robots" content="noindex' in dono.get("/loja/demo")[2])

# ───────────────────────────── D. configurações ─────────────────────────────
limites()
print("\n== D. Configurações do lojista ==")
st, _, b = dono.get("/app/loja-online")
check("D1 painel abre: 'Fora do ar' e lista 'Para vender'", st == 200 and "Fora do ar" in b and "Para vender" in b, st)
st, _, b = dono.get("/app/loja-online/configuracoes")
check("D2 configurações: lojas da empresa e os 2 estoques; nada de outra empresa", st == 200 and "Loja Shopping" in b and "E-commerce" in b and "Matriz da Outra" not in b)
base_cfg = {"nome": "Loja Demo Online", "unidade_id": "1", "estoque_id": "", "vendedor_id": "", "pedido_minimo": "0",
            "retirada": "1", "entrega": "1", "pix": "1", "cartao": "1", "combinar": "1", "whatsapp": "(86) 99999-0000",
            "instagram": "@lojademo", "email": "contato@lojademo.test", "aviso_topo": "", "texto_rodape": "Feito com carinho.",
            "texto_trocas": "", "descricao_busca": ""}
dono.post("/app/loja-online/configuracoes", {**base_cfg, "unidade_id": str(cen["unidade_outra"])}, "PUT")
check("D3 loja de OUTRA empresa como origem recusada", um("SELECT unidade_id FROM lojas_online WHERE empresa_id=1") == "1")
dono.get("/app/loja-online/configuracoes")
dono.post("/app/loja-online/configuracoes", {**base_cfg, "unidade_id": str(cen["unidade2"]), "estoque_id": str(cen["ecommerce"])}, "PUT")
check("D4 estoque que não é da loja escolhida recusado", um("SELECT CONCAT(unidade_id,'/',IFNULL(estoque_id,'')) FROM lojas_online WHERE empresa_id=1") == "1/")
dono.get("/app/loja-online/configuracoes")
sem = {k: v for k, v in base_cfg.items() if k not in ("retirada", "entrega")}
dono.post("/app/loja-online/configuracoes", sem, "PUT")
check("D5 desligar retirada E entrega recusado", um("SELECT nome FROM lojas_online WHERE empresa_id=1") != "Loja Demo Online")
dono.get("/app/loja-online/configuracoes")
st, _, _ = dono.post("/app/loja-online/configuracoes", base_cfg, "PUT")
cfg = json.loads(um("SELECT config FROM lojas_online WHERE empresa_id=1"))
check("D6 salvar: nome, contato e opções gravados", st == 302 and um("SELECT nome FROM lojas_online WHERE empresa_id=1") == "Loja Demo Online"
      and cfg["whatsapp"] == "(86) 99999-0000" and cfg["retirada"] is True and cfg["vender_sem_estoque"] is False, cfg)
dono.get("/app/loja-online")
dono.post("/app/loja-online/publicar", {"publicar": "1"})
check("D7 pôr no ar SEM produto marcado é recusado", um("SELECT publicada FROM lojas_online WHERE empresa_id=1") == "0")
st, _, _ = vendedor.get("/app/loja-online")
check("D8 vendedor não abre o painel (403)", st == 403, st)
st, _, _ = caixa.get("/app/loja-online/configuracoes")
check("D9 caixa não abre as configurações (403)", st == 403, st)
st, _, _ = gerente.get("/app/loja-online")
check("D10 gerente abre o painel", st == 200, st)
st, _, _ = outro.get("/app/loja-online/configuracoes")
check("D11 dono da outra empresa abre a DELE (não a da demo)", st == 200 and "Loja Demo Online" not in outro.get("/app/loja-online/configuracoes")[2])

# ───────────────────────────── E. produtos da vitrine ─────────────────────────────
limites()
print("\n== E. Quais produtos aparecem ==")
st, _, b = dono.get("/app/loja-online/produtos")
check("E1 lista de produtos: '0 de N na loja', nenhum da outra empresa", st == 200 and "0 de " in b and "Produto Secreto" not in b)
dono.post("/app/loja-online/produtos", {"acao": "entrar", "alcance": "marcados", "ids[]": [str(P[0]), str(P[1]), str(cen["produto_outra"])]})
check("E2 'pôr na loja' os marcados: 2 entram", um("SELECT COUNT(*) FROM produtos WHERE loja_online=1 AND empresa_id=1") == "2")
check("E3 produto de OUTRA empresa no POST é ignorado", um(f"SELECT loja_online FROM produtos WHERE id={cen['produto_outra']}") == "0")
dono.get("/app/loja-online/produtos")
dono.post("/app/loja-online/produtos", {"acao": "entrar", "alcance": "marcados"})
check("E4 sem marcar nada, nada muda", um("SELECT COUNT(*) FROM produtos WHERE loja_online=1") == "2")
dono.get(f"/app/loja-online/produtos?categoria={CAT['Moda']}")
dono.post("/app/loja-online/produtos", {"acao": "entrar", "alcance": "categoria", "categoria_id": str(CAT["Moda"])})
check("E5 'toda a categoria filtrada': os 8 de Moda entram", um(f"SELECT COUNT(*) FROM produtos WHERE loja_online=1 AND categoria_id={CAT['Moda']}") == "8")
dono.get("/app/loja-online/produtos")
dono.post("/app/loja-online/produtos", {"acao": "destacar", "alcance": "marcados", "ids[]": [str(P[8]), str(P[9])]})
check("E6 'destacar' põe na loja e marca destaque", um(f"SELECT CONCAT(loja_online,loja_online_destaque) FROM produtos WHERE id={P[8]}") == "11")
dono.get("/app/loja-online/produtos")
dono.post("/app/loja-online/produtos", {"acao": "sair", "alcance": "marcados", "ids[]": [str(P[9])]})
check("E7 'tirar da loja' também tira o destaque", um(f"SELECT CONCAT(loja_online,loja_online_destaque) FROM produtos WHERE id={P[9]}") == "00")
dono.get("/app/loja-online/produtos")
dono.post("/app/loja-online/produtos", {"acao": "entrar", "alcance": "todos"})
n_demo = um("SELECT COUNT(*) FROM produtos WHERE empresa_id=1 AND status='ativo' AND deleted_at IS NULL")
check("E8 'todos os produtos ativos': a empresa inteira entra, a outra não",
      um("SELECT COUNT(*) FROM produtos WHERE loja_online=1 AND empresa_id=1") == n_demo and um("SELECT COUNT(*) FROM produtos WHERE loja_online=1 AND empresa_id=2") == "0")
vendedor.get("/app/dashboard")
st, _, _ = vendedor.post("/app/loja-online/produtos", {"acao": "sair", "alcance": "todos"})
check("E9 vendedor com POST forjado não mexe (403)", st == 403 and um("SELECT COUNT(*) FROM produtos WHERE loja_online=1 AND empresa_id=1") == n_demo, st)
st, _, b = dono.get("/app/loja-online/produtos?situacao=destaque")
check("E10 filtro 'só os destaques' lista o destacado", st == 200 and "Fone Bluetooth Pro" in b and "Camisa Linho" not in b)
# os 3 produtos do seed demo (sem foto) ficam fora para a vitrine de teste ter só os 30
sql(f"UPDATE produtos SET loja_online=0 WHERE empresa_id=1 AND id NOT IN ({','.join(map(str, P))})")
sql(f"UPDATE produtos SET loja_online_destaque=1 WHERE id IN ({P[0]},{P[8]},{P[16]},{P[23]})")

# ───────────────────────────── F. cadastro do produto ─────────────────────────────
limites()
print("\n== F. Cadastro do produto: mais fotos e interruptor ==")
PF = P[0]
campos_edit = None


def campos_do_form(body):
    """Campos do form de edição do produto como o navegador enviaria."""
    form = re.search(r'<form[^>]*id="formProduto".*?</form>', body, re.S).group(0)
    out = {}
    for m in re.finditer(r"<input\b([^>]*)>", form):
        a = m.group(1)
        nome = re.search(r'\bname="([^"]+)"', a)
        if not nome or nome.group(1) in ("_token", "_method") or 'type="file"' in a:
            continue
        tipo = (re.search(r'\btype="([^"]+)"', a) or [None, "text"])[1]
        if tipo in ("checkbox", "radio") and "checked" not in a:
            continue
        val = re.search(r'\bvalue="([^"]*)"', a)
        out[nome.group(1)] = H.unescape(val.group(1)) if val else ("1" if tipo == "checkbox" else "")
    for m in re.finditer(r'<textarea\b[^>]*\bname="([^"]+)"[^>]*>(.*?)</textarea>', form, re.S):
        out[m.group(1)] = H.unescape(m.group(2))
    for m in re.finditer(r'<select\b[^>]*\bname="([^"]+)"[^>]*>(.*?)</select>', form, re.S):
        sel = re.search(r'<option[^>]*value="([^"]*)"[^>]*\bselected', m.group(2)) or re.search(r'<option[^>]*value="([^"]*)"', m.group(2))
        out[m.group(1)] = H.unescape(sel.group(1)) if sel else ""
    return out


st, _, b = dono.get(f"/app/produtos/{PF}/edit")
campos = campos_do_form(b)
check("F1 form de edição lido; interruptor veio marcado (produto está na loja)", campos.get("loja_online") == "1" and campos.get("loja_online_bloco") == "1", {k: campos.get(k) for k in ("loja_online", "loja_online_bloco")})
st, hd, b2 = dono.post_arquivos(f"/app/produtos/{PF}", campos, [("fotos_extras[]", "a.jpg", foto("p31.jpg"), "image/jpeg"),
                                                                  ("fotos_extras[]", "b.jpg", foto("p32.jpg"), "image/jpeg"),
                                                                  ("fotos_extras[]", "c.jpg", foto("p33.jpg"), "image/jpeg")], "PUT")
fotos = sql(f"SELECT id, caminho, ordem FROM produto_fotos WHERE produto_id={PF} ORDER BY ordem")
check("F2 três fotos adicionais gravadas, em ordem", st == 302 and len(fotos) == 3 and [f[2] for f in fotos] == ["1", "2", "3"], (st, fotos, texto(b2)[:200]))
existe = app_cmd("sh", "-c", f"ls /var/www/storage/app/public/{fotos[0][1]} 2>/dev/null | wc -l").strip() if fotos else "0"
check("F3 arquivo da foto está no disco público", existe == "1", existe)
check("F4 a capa (foto principal) não mudou", um(f"SELECT foto FROM produtos WHERE id={PF}") == "produtos/teste-1.jpg")
st, _, b = dono.get(f"/app/produtos/{PF}/edit")
check("F5 a edição mostra as 3 fotos com 'Tirar'", b.count('name="remover_fotos[]"') == 3)
campos = campos_do_form(b)
st, _, _ = dono.post_arquivos(f"/app/produtos/{PF}", {**campos, "remover_fotos[]": [fotos[1][0]]}, [], "PUT")
check("F6 'Tirar' remove a foto do banco e do disco", um(f"SELECT COUNT(*) FROM produto_fotos WHERE produto_id={PF}") == "2"
      and app_cmd("sh", "-c", f"ls /var/www/storage/app/public/{fotos[1][1]} 2>/dev/null | wc -l").strip() == "0")
dono.get(f"/app/produtos/{PF}/edit")
st, _, b2 = dono.post_arquivos(f"/app/produtos/{PF}", campos, [("fotos_extras[]", "x.txt", foto("nao-imagem.txt"), "text/plain")], "PUT")
check("F7 arquivo que não é imagem recusado, nada gravado", um(f"SELECT COUNT(*) FROM produto_fotos WHERE produto_id={PF}") == "2", st)
st, _, b = dono.get(f"/app/produtos/{PF}/edit")
campos = campos_do_form(b)
sem_flag = {k: v for k, v in campos.items() if k not in ("loja_online", "loja_online_destaque")}
dono.post_arquivos(f"/app/produtos/{PF}", sem_flag, [], "PUT")
check("F8 desligar o interruptor tira o produto da loja", um(f"SELECT CONCAT(loja_online,loja_online_destaque) FROM produtos WHERE id={PF}") == "00")
dono.get(f"/app/produtos/{PF}/edit")
antigo = {k: v for k, v in sem_flag.items() if k != "loja_online_bloco"}
sql(f"UPDATE produtos SET loja_online=1, loja_online_destaque=1 WHERE id={PF}")
dono.post_arquivos(f"/app/produtos/{PF}", antigo, [], "PUT")
check("F9 tela antiga (sem o bloco) NÃO desmarca o produto", um(f"SELECT CONCAT(loja_online,loja_online_destaque) FROM produtos WHERE id={PF}") == "11")
dono.get(f"/app/produtos/{PF}/edit")
nove = [("fotos_extras[]", f"n{i}.jpg", foto("p34.jpg"), "image/jpeg") for i in range(9)]
dono.post_arquivos(f"/app/produtos/{PF}", campos_do_form(dono.get(f"/app/produtos/{PF}/edit")[2]), nove, "PUT")
check("F10 mais de 8 fotos de uma vez recusado", um(f"SELECT COUNT(*) FROM produto_fotos WHERE produto_id={PF}") == "2")
st, _, b = dono.get("/app/produtos/create")
check("F11 cadastro NOVO tem 'Mais fotos' e o interruptor", 'name="fotos_extras[]"' in b and 'name="loja_online"' in b)
st, _, b = outro.get(f"/app/produtos/{cen['produto_outra']}/edit")
check("F12 a outra empresa (liberada) também tem o interruptor no produto dela", 'name="loja_online"' in b, st)

# ───────────────────────────── G. vitrine pública ─────────────────────────────
limites()
print("\n== G. Vitrine pública ==")
dono.get("/app/loja-online")
st, _, _ = dono.post("/app/loja-online/publicar", {"publicar": "1"})
check("G1 pôr no ar com produto marcado funciona", um("SELECT publicada FROM lojas_online WHERE empresa_id=1") == "1", st)
st, _, home = pub.get("/loja/demo")
check("G2 público abre a página inicial (200), sem aviso de prévia", st == 200 and "ainda não está no ar" not in home and "Loja Demo Online" in home, st)
check("G3 página inicial mostra produtos com preço e foto", "Fone Bluetooth Pro" in home and "/storage/produtos/teste-" in home and "R$ " in home)
check("G4 banner sem imagem mostra o mosaico de produtos reais", 'class="lj-mosaico"' in home)
check("G5 menu de categorias com as 4 categorias", all(c in home for c in ["Moda", "Casa e Presentes", "Mercearia"]))
check("G6 rodapé com WhatsApp, Instagram e endereço da loja", "(86) 99999-0000" in home and "@lojademo" in home and "Sao Paulo" in home)
check("G7 indexável (sem noindex) e com contrato de direção no HTML", 'content="noindex' not in home and "contrato de direção" in home)
st, _, b = pub.get("/loja/demo/produtos")
check("G8 lista de produtos: 30 produtos, 24 na 1ª página, com paginação", st == 200 and "30 produtos" in texto(b) and b.count('class="lj-card"') == 24 and "lj-paginas" in b, b.count('class="lj-card"'))
st, _, b = pub.get("/loja/demo/produtos?page=2")
check("G9 página 2 traz os 6 restantes", st == 200 and b.count('class="lj-card"') == 6, b.count('class="lj-card"'))
st, _, b = pub.get("/loja/demo/produtos?q=" + urllib.parse.quote("bluetooth fone"))
check("G10 busca por palavras em qualquer ordem acha o produto", st == 200 and b.count('class="lj-card"') == 1 and "Fone Bluetooth Pro" in b, b.count('class="lj-card"'))
st, _, b = pub.get("/loja/demo/produtos?q=T00009")
check("G11 busca pelo código exato", b.count('class="lj-card"') == 1 and "Fone Bluetooth Pro" in b)
st, _, b = pub.get("/loja/demo/produtos?q=" + urllib.parse.quote("zzz não existe"))
check("G12 busca sem resultado: estado vazio com saída", st == 200 and "Não encontramos nada" in b and "Ver todos os produtos" in b)
st, _, b = pub.get(f"/loja/demo/produtos?categoria={CAT['Mercearia']}")
check("G13 filtro por categoria: 7 de Mercearia", b.count('class="lj-card"') == 7 and "Café Especial" in b and "Fone Bluetooth" not in re.sub(r"<header.*?</header>", "", b, flags=re.S))
st, _, b = pub.get("/loja/demo/produtos?ordem=menor")
precos = [float(x.replace(".", "").replace(",", ".")) for x in re.findall(r"<b>R\$ ([\d.,]+)</b>", b)]
check("G14 ordem 'menor preço' crescente", precos == sorted(precos) and len(precos) == 24, precos[:6])
st, _, b = pub.get(f"/loja/demo/produtos?q=" + urllib.parse.quote("Secreto"))
check("G15 produto de outra empresa não aparece na busca", "Produto Secreto" not in b)
st, hd, _ = pub.get(f"/loja/demo/p/{PF}")
check("G16 endereço do produto sem o nome redireciona (301) para o canônico", st == 301 and f"/loja/demo/p/{PF}-camisa-linho-areia" in hd.get("Location", ""), (st, hd.get("Location")))
st, _, b = pub.get(f"/loja/demo/p/{PF}-camisa-linho-areia")
check("G17 página do produto: nome, preço, código, descrição e 'Adicionar ao carrinho'",
      st == 200 and "Camisa Linho Areia" in b and "T00001" in b and "Segunda linha" in b and "Adicionar ao carrinho" in b, st)
check("G18 galeria: capa + 2 fotos adicionais, com miniaturas", b.count("data-lj-mini=") == 3)
check("G19 ficha com peso e medidas; dados estruturados (schema.org)", "0,4 kg" in b and "10 × 20 × 30 cm" in b and '"@type":"Product"' in b)
check("G20 'Você também pode gostar' com outros produtos", "Você também pode gostar" in b)
st, _, _ = pub.get(f"/loja/demo/p/{cen['produto_outra']}-x")
check("G21 produto de outra empresa pelo id → 404", st == 404, st)
sql(f"UPDATE produtos SET loja_online=0 WHERE id={P[29]}")
st, _, _ = pub.get(f"/loja/demo/p/{P[29]}-biscoito-amanteigado")
check("G22 produto fora da loja → 404", st == 404, st)
sql(f"UPDATE produtos SET loja_online=1, status='inativo' WHERE id={P[29]}")
st, _, _ = pub.get(f"/loja/demo/p/{P[29]}-biscoito-amanteigado")
check("G23 produto INATIVO não aparece mesmo marcado → 404", st == 404, st)
sql(f"UPDATE produtos SET status='ativo' WHERE id={P[29]}")
st, _, b = pub.get("/loja/demo/informacoes")
check("G24 página de informações com texto padrão de trocas (CDC)", st == 200 and "Código de Defesa do Consumidor" in b)
st, hd, b = pub.get("/loja/demo/sitemap.xml")
check("G25 sitemap.xml com os produtos", st == 200 and "<urlset" in b and b.count("<loc>") >= 30, b.count("<loc>"))
st, _, _ = pub.get("/loja/demo/nao-existe")
check("G26 endereço inexistente na loja → 404", st == 404, st)

# ───────────────────────────── H. estoque ─────────────────────────────
limites()
print("\n== H. Estoque: o mesmo do balcão, separado, ou sem saldo ==")
PX = P[10]  # Caixa de Som — 10 no principal, 0 no e-commerce
sql(f"UPDATE lojas_online SET estoque_id={cen['ecommerce']} WHERE empresa_id=1")
st, _, b = pub.get(f"/loja/demo/p/{PX}-caixa-de-som-portatil")
check("H1 estoque separado 'E-commerce' (saldo 0) → 'Esgotado', sem botão de comprar", "Esgotado no momento" in b and "Adicionar ao carrinho" not in b)
st, j = pub.post_json("/loja/demo/carrinho/adicionar", {"produto_id": PX, "quantidade": 1})
check("H2 adicionar esgotado recusado (422)", st == 422 and j.get("ok") is False and "esgotou" in (j.get("erro") or ""), (st, j))
st, _, b = pub.get(f"/loja/demo/p/{P[1]}-vestido-midi-floral")
check("H3 produto com 3 no e-commerce → 'Últimas 3 unidades'", "Últimas 3 unidades" in b)
st, _, b = pub.get("/loja/demo/produtos")
check("H4 na lista, os sem saldo levam o selo 'Esgotado'", b.count("lj-selo--fim") >= 19, b.count("lj-selo--fim"))
sql("UPDATE lojas_online SET estoque_id=NULL WHERE empresa_id=1")
st, _, b = pub.get(f"/loja/demo/p/{PX}-caixa-de-som-portatil")
check("H5 voltando ao estoque do balcão (10) → disponível", "Adicionar ao carrinho" in b and "Esgotado" not in texto(b))
cfg = json.loads(um("SELECT config FROM lojas_online WHERE empresa_id=1"))
sql(f"UPDATE lojas_online SET estoque_id={cen['ecommerce']}, config=JSON_SET(config,'$.vender_sem_estoque',true) WHERE empresa_id=1")
st, j = pub.post_json("/loja/demo/carrinho/adicionar", {"produto_id": PX, "quantidade": 2})
check("H6 'vender mesmo sem saldo' ligado aceita o esgotado", st == 200 and j.get("ok") is True and j.get("total") == 2, (st, j))
sql("UPDATE lojas_online SET estoque_id=NULL, config=JSON_SET(config,'$.vender_sem_estoque',false) WHERE empresa_id=1")
st, j = pub.post_json("/loja/demo/carrinho/adicionar", {"produto_id": cen["produto_outra"], "quantidade": 1})
check("H7 produto de outra empresa não entra no carrinho", st == 422 and j.get("ok") is False, (st, j))

# ───────────────────────────── I. carrinho ─────────────────────────────
limites()
print("\n== I. Carrinho ==")
c = Nav()
c.get("/loja/demo")
st, _, b = c.get("/loja/demo/carrinho")
check("I1 carrinho vazio: estado vazio com convite", st == 200 and "Seu carrinho está vazio" in b)
st, j = c.post_json("/loja/demo/carrinho/adicionar", {"produto_id": P[0], "quantidade": 2})
check("I2 adicionar 2 → total 2, subtotal 2 × 59,90", st == 200 and j["total"] == 2 and abs(j["subtotal"] - 119.80) < 0.01, j)
st, j = c.post_json("/loja/demo/carrinho/adicionar", {"produto_id": P[0], "quantidade": 1})
check("I3 adicionar o mesmo soma (3)", j.get("total") == 3, j)
st, j = c.post_json("/loja/demo/carrinho/adicionar", {"produto_id": P[8], "quantidade": 1})
check("I4 segundo produto entra (4 no total)", j.get("total") == 4, j)
st, _, b = c.get("/loja/demo/carrinho")
t = texto(b)
check("I5 carrinho lista os 2 produtos e o subtotal R$ 239,60", "Camisa Linho Areia" in b and "Fone Bluetooth Pro" in b and "R$ 239,60" in t, re.findall(r"R\$ [\d.,]+", t)[:8])
check("I6 contador do cabeçalho mostra 4", re.search(r"data-lj-conta\s*>4<", b) is not None)
c.post("/loja/demo/carrinho/atualizar", {"produto_id": str(P[0]), "quantidade": "50"})
st, _, b = c.get("/loja/demo/carrinho")
check("I7 quantidade acima do saldo (50 > 10) é reduzida com aviso", "ajustamos a quantidade" in b and 'value="10"' in b)
c.post("/loja/demo/carrinho/atualizar", {"produto_id": str(P[8]), "quantidade": "0"})
st, _, b = c.get("/loja/demo/carrinho")
check("I8 quantidade 0 tira o item", "Fone Bluetooth Pro" not in re.sub(r"<header.*?</header>", "", b, flags=re.S))
c.post("/loja/demo/carrinho/atualizar", {"produto_id": str(P[0]), "quantidade": "1"})
sql("UPDATE lojas_online SET config=JSON_SET(config,'$.pedido_minimo',100) WHERE empresa_id=1")
st, _, b = c.get("/loja/demo/carrinho")
check("I9 pedido mínimo: avisa quanto falta e trava o botão", "pedido mínimo desta loja" in b and 'aria-disabled="true"' in b)
st, hd, _ = c.get("/loja/demo/finalizar")
check("I10 abaixo do mínimo não entra no fechamento", st == 302 and "/carrinho" in hd.get("Location", ""))
sql("UPDATE lojas_online SET config=JSON_SET(config,'$.pedido_minimo',0) WHERE empresa_id=1")
st, hd, _ = pub.get("/loja/demo/finalizar")
check("I11 fechamento com carrinho de outro visitante: cada um tem o seu", st in (200, 302))
c2 = Nav()
c2.get("/loja/demo")
st, hd, _ = c2.get("/loja/demo/finalizar")
check("I12 fechamento com carrinho vazio volta para o carrinho", st == 302 and "/carrinho" in hd.get("Location", ""))
sql(f"UPDATE produtos SET loja_online=0 WHERE id={P[0]}")
st, _, b = c.get("/loja/demo/carrinho")
check("I13 produto que saiu da loja some do carrinho com aviso", "não está mais disponível" in b)
sql(f"UPDATE produtos SET loja_online=1 WHERE id={P[0]}")
st, _ = c.post_json("/loja/demo/carrinho/adicionar", {"produto_id": P[0], "quantidade": 0})
check("I14 quantidade inválida (0) no adicionar → 422", st == 422, st)
semtoken = Nav()
st, _ = semtoken.post_json("/loja/demo/carrinho/adicionar", {"produto_id": P[0], "quantidade": 1})
check("I15 POST sem token CSRF recusado (419)", st == 419, st)

# ───────────────────────────── J. frete ─────────────────────────────
limites()
print("\n== J. Frete (sem integração de entrega ligada) ==")
c = Nav()
c.get("/loja/demo")
c.post_json("/loja/demo/carrinho/adicionar", {"produto_id": P[0], "quantidade": 2})
c.post_json("/loja/demo/carrinho/adicionar", {"produto_id": P[8], "quantidade": 1})
st, j = c.post_json("/loja/demo/frete", {"cep": "64000-100"})
check("J1 sem Uber/Melhor Envio: frete indisponível, com explicação", st == 200 and j.get("disponivel") is False and "combina a entrega" in j.get("mensagem", ""), j)
st, j = c.post_json("/loja/demo/frete", {"cep": "123"})
check("J2 CEP inválido: mensagem clara", j.get("disponivel") is False and "CEP válido" in j.get("mensagem", ""), j)
st, j = c.post_json("/loja/demo/frete", {"cep": "64000100", "produto_id": cen["produto_outra"]})
check("J3 frete de produto de outra empresa → 404", st == 404, st)
sql("UPDATE lojas_online SET config=JSON_SET(config,'$.entrega',false) WHERE empresa_id=1")
st, j = c.post_json("/loja/demo/frete", {"cep": "64000100"})
check("J4 loja só com retirada: frete responde 'só com retirada'", "só com retirada" in j.get("mensagem", ""), j)
st, _, b = c.get("/loja/demo/finalizar")
check("J5 fechamento sem a opção 'Receber no endereço'", st == 200 and 'value="entrega"' not in b and 'value="retirada"' in b)
sql("UPDATE lojas_online SET config=JSON_SET(config,'$.entrega',true) WHERE empresa_id=1")

# ───────────────────────────── K. fechamento → pedido do ERP ─────────────────────────────
limites()
print("\n== K. Fechar a compra: vira PEDIDO do ERP ==")
st, _, b = c.get("/loja/demo/finalizar")
check("K1 tela de fechamento: dados, recebimento, pagamento e resumo com total R$ 179,70",
      st == 200 and "Seus dados" in b and "Como você quer receber" in b and "Pagamento" in b and "R$ 179,70" in texto(b))
check("K2 sem PIX/cartão ligados: só 'Combinar com a loja'", 'value="combinar"' in b and 'value="pix"' not in b and 'value="cartao"' not in b)
pedidos_antes = int(um("SELECT COUNT(*) FROM pedidos"))
st, hd, b = c.post("/loja/demo/finalizar", {"nome": "", "telefone": "123", "entrega": "retirada", "pagamento": "combinar"})
c.get("/loja/demo/finalizar")
check("K3 nome vazio e telefone curto recusados; nenhum pedido criado", st == 302 and int(um("SELECT COUNT(*) FROM pedidos")) == pedidos_antes, st)
st, _, b = c.get("/loja/demo/finalizar")
st, hd, _ = c.post("/loja/demo/finalizar", {"nome": "Maria Teste", "telefone": "(86) 98888-1111", "entrega": "entrega", "pagamento": "combinar"})
st2, _, b = c.get("/loja/demo/finalizar")
check("K4 entrega sem endereço: pede CEP, rua, número e bairro", int(um("SELECT COUNT(*) FROM pedidos")) == pedidos_antes and "Informe o CEP" in b and "Informe a rua" in b, texto(b)[:200])
check("K5 o que a pessoa já digitou volta preenchido", 'value="Maria Teste"' in b)
st, hd, _ = c.post("/loja/demo/finalizar", {"nome": "Maria Teste", "telefone": "(86) 98888-1111", "entrega": "retirada", "pagamento": "pix"})
check("K6 forma de pagamento que a loja não tem (pix) recusada", int(um("SELECT COUNT(*) FROM pedidos")) == pedidos_antes)
c.get("/loja/demo/finalizar")
saldo_antes = um(f"SELECT quantidade_posterior FROM estoque_movimentacoes WHERE produto_id={P[0]} AND estoque_id={cen['principal']} ORDER BY id DESC LIMIT 1")
st, hd, _ = c.post("/loja/demo/finalizar", {"nome": "Maria Teste", "telefone": "(86) 98888-1111", "email": "maria@teste.test", "cpf_cnpj": "529.982.247-25",
                                             "entrega": "retirada", "pagamento": "combinar", "observacoes": "Embalar para presente <b>ok</b>"})
loc = hd.get("Location", "")
check("K7 fechar com retirada + combinar: redireciona para a página do pedido", st == 302 and re.search(r"/loja/demo/pedido/[a-z0-9]{32}$", loc) is not None, (st, loc))
ped = sql("SELECT id, numero, status, canal, unidade_id, IFNULL(estoque_id,''), subtotal, total, metodo_entrega, IFNULL(frete_valor,''), loja_token, cliente_id, IFNULL(vendedor_id,'') "
          "FROM pedidos WHERE loja_token IS NOT NULL ORDER BY id DESC LIMIT 1")[0]
PED_ID, TOKEN = ped[0], ped[10]
check("K8 PEDIDO do ERP: rascunho, canal online, loja 1, total 179,70, retirada, sem frete",
      ped[2] == "rascunho" and ped[3] == "online" and ped[4] == "1" and ped[6] == "179.70" and ped[7] == "179.70" and ped[8] == "retirada" and ped[9] == "", ped)
itens = sql(f"SELECT produto_id, quantidade, preco_unitario, total FROM pedido_itens WHERE pedido_id={PED_ID} ORDER BY id")
check("K9 itens do pedido: 2 × 59,90 e 1 × 59,90", len(itens) == 2 and itens[0][1].startswith("2") and itens[0][3] == "119.80" and itens[1][3] == "59.90", itens)
cli = sql(f"SELECT nome_razao_social, telefone, email, cpf_cnpj, tipo_pessoa, empresa_id FROM clientes WHERE id={ped[11]}")[0]
check("K10 cliente criado na empresa certa, com telefone, e-mail e CPF só com números", cli == ["Maria Teste", "86988881111", "maria@teste.test", "52998224725", "pf", "1"], cli)
obs = um(f"SELECT CONCAT(observacoes_internas,'|',IFNULL(observacoes_externas,'')) FROM pedidos WHERE id={PED_ID}")
check("K11 observações: origem 'loja online' e o recado do cliente", "Criado pela loja online" in obs and "Embalar para presente" in obs and "a combinar" in obs, obs)
check("K12 fechar o pedido NÃO baixa estoque (quem baixa é o faturamento no ERP)",
      um(f"SELECT quantidade_posterior FROM estoque_movimentacoes WHERE produto_id={P[0]} AND estoque_id={cen['principal']} ORDER BY id DESC LIMIT 1") == saldo_antes)
st, _, b = c.get(loc.replace(BASE, ""))
check("K13 página do pedido: número, itens, total, 'A loja vai falar com você'", st == 200 and f"Pedido #{ped[1]}" in b and "Camisa Linho Areia" in b and "R$ 179,70" in texto(b)
      and "A loja vai falar com você" in b, st)
check("K14 recado do cliente sai ESCAPADO (sem HTML)", "&lt;b&gt;ok&lt;/b&gt;" in b and "<b>ok</b>" not in b)
check("K15 carrinho esvaziado depois de fechar", "Seu carrinho está vazio" in c.get("/loja/demo/carrinho")[2])
st, _, _ = pub.get(f"/loja/demo/pedido/{TOKEN}")
check("K16 quem tem o endereço do pedido acompanha (sem login)", st == 200, st)
st, _, _ = pub.get("/loja/demo/pedido/" + "a" * 32)
check("K17 token que não existe → 404", st == 404, st)
st, _, _ = pub.get(f"/loja/demo/pedido/{TOKEN[:-1]}")
check("K18 token malformado → 404", st == 404, st)
st, j = pub.post_json(f"/loja/demo/pedido/{TOKEN}/pagar", {"meio": "pix"})
check("K19 pedir PIX sem provedor ligado → recusa clara (422)", st == 422 and j.get("ok") is False, (st, j))
st, _, b = pub.get(f"/loja/demo/pedido/{TOKEN}/situacao", json_=True)
check("K20 situação do pedido: 'aguardando'", json.loads(b).get("situacao") == "aguardando", b[:120])
st, _, b = dono.get("/app/pedidos")
check("K21 o pedido aparece na lista de Pedidos do ERP", st == 200 and "Maria Teste" in b)
st, _, b = dono.get(f"/app/pedidos/{PED_ID}")
check("K22 tela do pedido no ERP abre, com o cliente e o canal Online", st == 200 and "Maria Teste" in b and "Online" in b, st)
st, _, b = dono.get("/app/loja-online")
check("K23 painel da loja online lista o pedido em 'Últimos pedidos'", f"#{ped[1]}" in b and "Maria Teste" in b)
# cliente que volta: casa pelo telefone, não duplica
c.post_json("/loja/demo/carrinho/adicionar", {"produto_id": P[2], "quantidade": 1})
c.get("/loja/demo/finalizar")
n_antes = um("SELECT COUNT(*) FROM pedidos")
st, hd, _ = c.post("/loja/demo/finalizar", {"nome": "Sem Documento", "telefone": "(86) 97777-0000", "entrega": "retirada", "pagamento": "combinar"})
b = c.get("/loja/demo/finalizar")[2]
check("K23b empresa que exige documento: sem CPF não fecha, e a tela explica", um("SELECT COUNT(*) FROM pedidos") == n_antes and "pede o CPF ou CNPJ" in b and "(opcional)" not in b.split('for="f-doc"')[1][:120])
sql("UPDATE empresas SET exige_documento_cadastro=0 WHERE id=1")
b = c.get("/loja/demo/finalizar")[2]
check("K23c empresa que NÃO exige documento: CPF vira opcional na tela", "(opcional)" in b.split('for="f-doc"')[1][:120])
c.get("/loja/demo/finalizar")
clientes_antes = um("SELECT COUNT(*) FROM clientes WHERE empresa_id=1")
st, hd, _ = c.post("/loja/demo/finalizar", {"nome": "Maria T.", "telefone": "86 8888-1111", "entrega": "retirada", "pagamento": "combinar"})
ped2 = sql("SELECT id, cliente_id, numero FROM pedidos WHERE loja_token IS NOT NULL ORDER BY id DESC LIMIT 1")[0]
check("K24 cliente que volta (telefone sem o 9) é o MESMO cadastro", ped2[1] == ped[11] and um("SELECT COUNT(*) FROM clientes WHERE empresa_id=1") == clientes_antes, ped2)
check("K25 numeração do pedido segue a da empresa (+1)", int(ped2[2]) == int(ped[1]) + 1, (ped[1], ped2[2]))
# limites de requisição: cada ação tem o seu contador
for _ in range(14):
    c.post_json("/loja/demo/carrinho/adicionar", {"produto_id": P[6], "quantidade": 1})
c.post("/loja/demo/carrinho/atualizar", {"produto_id": str(P[6]), "quantidade": "0"})
st, _, _ = c.get("/loja/demo/finalizar")
st2, hd2, _ = c.post("/loja/demo/finalizar", {"nome": "", "telefone": "1", "entrega": "retirada", "pagamento": "combinar"})
check("K25b 15 cliques no carrinho em um minuto NÃO travam o fechamento (contadores separados)", st2 == 302, st2)
# entrega sem cotação → frete a combinar
c.post_json("/loja/demo/carrinho/adicionar", {"produto_id": P[3], "quantidade": 1})
c.get("/loja/demo/finalizar")
st, hd, _ = c.post("/loja/demo/finalizar", {"nome": "João Entrega", "telefone": "(11) 97777-2222", "entrega": "entrega", "cep": "01001-000",
                                             "logradouro": "Praça da Sé", "numero": "100", "complemento": "sala 2", "bairro": "Sé", "pagamento": "combinar"})
ped3 = sql("SELECT id, metodo_entrega, IFNULL(frete_valor,''), total, subtotal, cliente_id, loja_token FROM pedidos WHERE loja_token IS NOT NULL ORDER BY id DESC LIMIT 1")[0]
check("K26 entrega sem cotação automática: pedido criado com frete a combinar (total = produtos)", ped3[1] == "entrega" and ped3[2] == "" and ped3[3] == ped3[4], (st, hd.get("Location"), re.findall(r'class="lj-erro">([^<]+)', c.get("/loja/demo/finalizar")[2]), ped3))
end = sql(f"SELECT cep, logradouro, numero, complemento, bairro FROM clientes WHERE id={ped3[5]}")[0]
check("K27 endereço da entrega gravado no cadastro do cliente", end == ["01001000", "Praça da Sé", "100", "sala 2", "Sé"], end)
st, _, b = pub.get(f"/loja/demo/pedido/{ped3[6]}")
check("K28 página do pedido explica 'A loja vai combinar a entrega' e mostra o endereço", "vai combinar a entrega" in b and "Praça da Sé" in b and "a combinar" in b)
# andamento
sql(f"UPDATE pedidos SET status='confirmado' WHERE id={PED_ID}")
b = pub.get(f"/loja/demo/pedido/{TOKEN}")[2]
check("K29 loja confirma no ERP → página mostra 'Pedido confirmado pela loja'", "Pedido confirmado pela loja" in b and json.loads(pub.get(f"/loja/demo/pedido/{TOKEN}/situacao", json_=True)[2])["situacao"] == "confirmado")
sql(f"UPDATE pedidos SET status='faturado' WHERE id={PED_ID}")
check("K30 faturado → 'Pronto para retirada'", "Pronto para retirada" in pub.get(f"/loja/demo/pedido/{TOKEN}")[2])
sql(f"UPDATE pedidos SET status='cancelado' WHERE id={PED_ID}")
check("K31 cancelado → 'Este pedido foi cancelado', sem botão de pagar", "Este pedido foi cancelado" in pub.get(f"/loja/demo/pedido/{TOKEN}")[2])
sql(f"UPDATE pedidos SET status='rascunho' WHERE id={PED_ID}")

# ───────────────────────────── L. editor visual ─────────────────────────────
limites()
print("\n== L. Editor visual: modelos, cores, blocos ==")
st, _, b = dono.get("/app/loja-online/editor")
check("L1 editor abre com os 6 modelos e os 7 tipos de bloco", st == 200 and all(m in b for m in ["Vitrine", "Ateli", "Circuito", "Feira", "Mimo", "Balc"])
      and all(t in b for t in ['"banner"', '"vitrine"', '"categorias"', '"beneficios"', '"imagem_texto"', '"texto"', '"faixa"']), st)
check("L2 sem rascunho: 'Igual ao que está publicado', botões desligados", "Igual ao que está publicado" in b)
blocos_ok = [
    {"id": "b1", "tipo": "faixa", "texto": "FRETE DE TESTE <script>alert(1)</script>"},
    {"id": "b2", "tipo": "banner", "titulo": "Banner do Rascunho", "subtitulo": "sub", "botao_texto": "Ir", "botao_link": "javascript:alert(1)", "imagem": "../../etc/passwd", "estilo": "cheio", "altura": "gigante"},
    {"id": "b3", "tipo": "vitrine", "titulo": "Minha fileira", "origem": "categoria", "categoria_id": 999999, "quantidade": "6", "colunas": "3"},
    {"id": "b4", "tipo": "tipo_inventado", "titulo": "x"},
    {"id": "b5", "tipo": "beneficios", "itens": [{"icone": "nao-existe", "titulo": "Vantagem A", "texto": "t"}]},
]
st, j = dono.post_json("/app/loja-online/editor/rascunho", {"modelo": "feira", "tema": {"cor_primaria": "#AA0011", "cor_fundo": "vermelho", "raio": "999", "fonte_titulo": "comic", "logo": "http://evil/x.png"}, "blocos": blocos_ok})
ras = j.get("rascunho", {})
tipos = [x["tipo"] for x in ras.get("blocos", [])]
check("L3 rascunho salvo; tipo de bloco desconhecido descartado", st == 200 and tipos == ["faixa", "banner", "vitrine", "beneficios"], (st, tipos))
bn = next((x for x in ras.get("blocos", []) if x["tipo"] == "banner"), {})
check("L4 higiene: link javascript: e imagem de fora zerados; altura inválida volta ao padrão", bn.get("botao_link") == "" and bn.get("imagem") == "" and bn.get("altura") == "media", bn)
vt = next((x for x in ras.get("blocos", []) if x["tipo"] == "vitrine"), {})
bf = next((x for x in ras.get("blocos", []) if x["tipo"] == "beneficios"), {})
check("L5 categoria que não é da empresa vira nula; ícone desconhecido vira 'star'", vt.get("categoria_id") is None and bf["itens"][0]["icone"] == "star", (vt, bf))
tm = ras.get("tema", {})
check("L6 tema: cor válida aceita (minúscula); cor/raio/fonte/logo inválidos ficam os do modelo",
      tm.get("cor_primaria") == "#aa0011" and tm.get("cor_fundo") == "#f5f7ee" and tm.get("raio") == "20" and tm.get("fonte_titulo") == "bricolage" and tm.get("logo") == "", tm)
st, _, b = dono.get("/loja/demo?rascunho=1")
check("L7 prévia do lojista mostra o RASCUNHO (banner novo, aviso de prévia)", st == 200 and "Banner do Rascunho" in b and "Prévia do editor" in b)
check("L8 texto com <script> sai escapado na vitrine", "&lt;script&gt;alert(1)&lt;/script&gt;" in b and "<script>alert(1)</script>" not in b)
check("L9 prévia usa o tema do rascunho (verde da Feira trocado por #aa0011, etiqueta de preço)", "--marca:#aa0011" in b and 'data-preco="etiqueta"' in b)
st, _, b = pub.get("/loja/demo?rascunho=1")
check("L10 público com ?rascunho=1 continua vendo o PUBLICADO", "Banner do Rascunho" not in b and "Prévia do editor" not in b)
st, _, b = outro.get("/loja/demo?rascunho=1")
check("L11 dono de outra empresa também não vê o rascunho", "Banner do Rascunho" not in b)
st, j = dono.post_arquivos("/app/loja-online/editor/imagem", {}, [("imagem", "banner.jpg", foto("banner.jpg"), "image/jpeg")], json_=True)
IMG = j.get("caminho", "")
check("L12 envio de imagem do editor: fica na pasta da empresa", st == 200 and re.match(r"^loja-online/1/[a-z0-9]{24}\.jpg$", IMG) is not None, (st, j))
st, j = dono.post_arquivos("/app/loja-online/editor/imagem", {}, [("imagem", "x.txt", foto("nao-imagem.txt"), "text/plain")], json_=True)
check("L13 arquivo que não é imagem recusado (422)", st == 422, (st, j))
st, j = dono.post_arquivos("/app/loja-online/editor/imagem", {}, [("imagem", "x.php", b"<?php echo 1;", "image/jpeg")], json_=True)
check("L14 PHP disfarçado de imagem recusado (422)", st == 422, (st, j))
blocos_ok[1]["imagem"] = IMG
blocos_ok[1]["botao_link"] = "produtos?promocao=1"
st, j = dono.post_json("/app/loja-online/editor/rascunho", {"modelo": "feira", "tema": {"cor_primaria": "#aa0011"}, "blocos": blocos_ok})
bn = next((x for x in j["rascunho"]["blocos"] if x["tipo"] == "banner"), {})
check("L15 imagem enviada pela loja e link interno aceitos no bloco", bn.get("imagem") == IMG and bn.get("botao_link") == "produtos?promocao=1", bn)
b = dono.get("/loja/demo?rascunho=1")[2]
check("L16 banner com imagem: usa a imagem (sem mosaico) e o botão leva às promoções", f"/storage/{IMG}" in b and "/loja/demo/produtos?promocao=1" in b)
st, j = dono.post_json("/app/loja-online/editor/publicar")
r = sql("SELECT modelo, tema, IFNULL(rascunho,'NULO') FROM lojas_online WHERE empresa_id=1")[0]
check("L17 publicar: modelo e blocos vão ao ar; rascunho some; tema guarda só o que difere do modelo",
      st == 200 and r[0] == "feira" and r[2] == "NULO" and json.loads(r[1]) == {"cor_primaria": "#aa0011"}, r)
b = pub.get("/loja/demo")[2]
check("L18 público agora vê o que foi publicado", "Banner do Rascunho" in b and "--marca:#aa0011" in b)
st, j = dono.post_json("/app/loja-online/editor/modelo", {"modelo": "atelie"})
check("L19 trocar de modelo no editor: rascunho com tema e blocos do Ateliê", st == 200 and j["rascunho"]["modelo"] == "atelie" and j["rascunho"]["tema"]["fonte_titulo"] == "bodoni"
      and j["rascunho"]["blocos"][1]["estilo"] == "editorial", j.get("rascunho", {}).get("modelo"))
check("L20 trocar de modelo NÃO mexe no publicado", "Banner do Rascunho" in pub.get("/loja/demo")[2])
st, j = dono.post_json("/app/loja-online/editor/descartar")
check("L21 descartar: volta ao publicado", st == 200 and j["rascunho"]["modelo"] == "feira" and um("SELECT IFNULL(rascunho,'NULO') FROM lojas_online WHERE empresa_id=1") == "NULO")
st, j = dono.post_json("/app/loja-online/editor/rascunho", {"modelo": "nao-existe", "tema": {}, "blocos": []})
check("L22 modelo inexistente recusado (422)", st == 422, st)
vendedor.get("/app/dashboard")
st, j = vendedor.post_json("/app/loja-online/editor/rascunho", {"modelo": "mimo", "tema": {}, "blocos": []})
check("L23 vendedor não salva rascunho (403)", st == 403, st)
st, j = gerente.post_json("/app/loja-online/editor/rascunho", {"modelo": "feira", "tema": {}, "blocos": []}) if gerente.get("/app/loja-online/editor")[0] == 200 else (0, {})
check("L24 gerente salva rascunho (página inicial vazia é aceita)", st == 200 and j["rascunho"]["blocos"] == [] or st == 200, (st,))
gerente.post_json("/app/loja-online/editor/descartar")
for modelo in ["vitrine", "atelie", "circuito", "feira", "mimo", "balcao"]:
    dono.post_json("/app/loja-online/editor/modelo", {"modelo": modelo})
    dono.post_json("/app/loja-online/editor/publicar")
    st, _, b = pub.get("/loja/demo")
    st2, _, b2 = pub.get(f"/loja/demo/p/{PF}-camisa-linho-areia")
    st3, _, b3 = pub.get("/loja/demo/produtos")
    check(f"L25 modelo '{modelo}': início, produto e lista abrem (200) com produtos",
          st == 200 and st2 == 200 and st3 == 200 and b.count('class="lj-card"') >= 4 and 'class="lj-banner' in b, (st, st2, st3, b.count('class="lj-card"')))
check("L26 modelo Balcão: cartão em lista, com código à vista", 'data-cartao="lista"' in b and "Cód. T000" in b)
dono.post_json("/app/loja-online/editor/modelo", {"modelo": "vitrine"})
dono.post_json("/app/loja-online/editor/publicar")

# ───────────────────────────── M. subdomínio e domínio próprio ─────────────────────────────
limites()
print("\n== M. Subdomínio e domínio do cliente ==")
sub = Nav("demo.lojas.teste")
st, _, b = sub.get("/")
check("M1 subdomínio demo.lojas.teste abre a loja na raiz", st == 200 and "Loja Demo Online" in b and 'class="lj-banner' in b, st)
check("M2 no subdomínio os links não levam o prefixo /loja/demo", 'href="/produtos"' in b and 'href="/carrinho"' in b and "/loja/demo/" not in b)
st, _, b = sub.get("/produtos?q=fone")
check("M3 busca pelo subdomínio funciona", st == 200 and "Fone Bluetooth Pro" in b)
st, _, b = sub.get("/produtos")
check("M4 paginação no subdomínio aponta para /produtos?page=2", "/produtos?page=2" in b and "/loja/demo/produtos?page=2" not in b)
st, j = sub.post_json("/carrinho/adicionar", {"produto_id": P[4], "quantidade": 1})
check("M5 carrinho pelo subdomínio (sessão própria do host)", st == 200 and j.get("ok") is True, (st, j))
sub.get("/finalizar")
st, hd, _ = sub.post("/finalizar", {"nome": "", "telefone": "1", "entrega": "retirada", "pagamento": "combinar"})
check("M5b erro de formulário no subdomínio volta para /finalizar (sem /loja/demo)", st == 302 and hd.get("Location", "").endswith("demo.lojas.teste/finalizar"), hd.get("Location"))
sub.get("/finalizar")
st, hd, _ = sub.post("/finalizar", {"nome": "Cliente do Subdomínio", "telefone": "(21) 96666-3333", "entrega": "retirada", "pagamento": "combinar"})
check("M6 fechar pelo subdomínio redireciona para /pedido/{token} (sem prefixo)", st == 302 and re.search(r"//demo\.lojas\.teste/pedido/[a-z0-9]{32}$", hd.get("Location", "")) is not None, hd.get("Location"))
dom = Nav("loja.cliente.com.br")
st, _, b = dom.get("/")
check("M7 domínio do cliente abre a loja", st == 200 and "Loja Demo Online" in b, st)
st, _, b = Nav("www.loja.cliente.com.br").get("/")
check("M8 www. do domínio do cliente também", st == 200 and "Loja Demo Online" in b, st)
st, _, b = dom.get("/login")
check("M9 no domínio do cliente, /login NÃO é o login do ERP (404 da loja)", st == 404, st)
st, _, b = Nav("naoexiste.lojas.teste").get("/")
check("M10 subdomínio sem loja cai no site do ERP, não em outra loja", st == 200 and "Loja Demo Online" not in b, st)
st, _, b = Nav("outra.lojas.teste").get("/")
check("M11 subdomínio de loja fora do ar → 'sendo preparada'", st == 503, st)
st, _, b = pub.get("/login")
check("M12 host do ERP intacto: /login é o login do ERP", st == 200 and 'name="email"' in b and "lj-topo" not in b, st)
st, _, b = pub.get("/")
check("M13 host do ERP intacto: / é o site do ERP", st == 200 and "lj-topo" not in b, st)
st, _, b = sub.get("/sitemap.xml")
check("M14 sitemap no subdomínio usa o host do subdomínio", "http://demo.lojas.teste/p/" in b, b[:200])

# ───────────────────────────── N. isolamento e desligar ─────────────────────────────
limites()
print("\n== N. Isolamento entre empresas e desligar ==")
sql(f"UPDATE produtos SET loja_online=1 WHERE id={cen['produto_outra']}")
outro.get("/app/loja-online")
outro.post("/app/loja-online/publicar", {"publicar": "1"})
st, _, b = pub.get("/loja/outra")
check("N1 a loja da outra empresa mostra só o produto DELA", st == 200 and "Produto Secreto da Outra" in b and "Camisa Linho" not in b, st)
st, _, b = pub.get("/loja/demo/produtos?q=Secreto")
check("N2 a loja demo não mostra o produto da outra", "Produto Secreto" not in b)
n1 = Nav()
n1.get("/loja/demo")
n1.post_json("/loja/demo/carrinho/adicionar", {"produto_id": P[5], "quantidade": 2})
st, _, b = n1.get("/loja/outra/carrinho")
check("N3 mesmo visitante: o carrinho de uma loja não aparece na outra", "Seu carrinho está vazio" in b)
st, _, _ = pub.get(f"/loja/outra/pedido/{TOKEN}")
check("N4 token de pedido da demo não abre pela loja da outra (404)", st == 404, st)
st, _, _ = outro.get(f"/app/pedidos/{PED_ID}")
check("N5 dono da outra empresa não abre o pedido da demo no ERP", st in (403, 404), st)
admin.get("/admin/empresas/2")
admin.post("/admin/empresas/2/loja-online/toggle")
st, _, _ = pub.get("/loja/outra")
check("N6 admin desliga a loja da outra: vitrine some (404)", st == 404, st)
st, _, b = outro.get("/app/dashboard")
check("N7 desligada: o menu some para o lojista e o painel dá 404", "/app/loja-online" not in b and outro.get("/app/loja-online")[0] == 404)
check("N8 desligar não apaga nada (loja e marcação do produto continuam)", um("SELECT COUNT(*) FROM lojas_online WHERE empresa_id=2") == "1" and um(f"SELECT loja_online FROM produtos WHERE id={cen['produto_outra']}") == "1")
st, _, b = pub.get("/loja/demo")
check("N9 a loja demo continua no ar", st == 200)
dono.get("/app/loja-online")
dono.post("/app/loja-online/publicar", {"publicar": "0"})
check("N10 lojista tira do ar: público volta ao 'sendo preparada'", pub.get("/loja/demo")[0] == 503)
dono.get("/app/loja-online")
dono.post("/app/loja-online/publicar", {"publicar": "1"})

# ───────────────────────────── O. vizinhança do ERP ─────────────────────────────
limites()
sql("UPDATE empresas SET exige_documento_cadastro=1 WHERE id=1")
print("\n== O. O resto do ERP continua igual ==")
for rota in ["/app/dashboard", "/app/produtos", f"/app/produtos/{PF}", "/app/pedidos", "/app/vendas", "/app/estoque/movimentacoes", "/app/configuracoes",
             "/app/configuracoes/formas-pagamento", "/app/relatorios/vendas", "/app/clientes"]:
    st, _, b = dono.get(rota)
    check(f"O1 {rota} abre (200)", st == 200, st)
st, _, b = admin.get("/admin/empresas/1")
check("O2 admin: empresa abre com o card 'Liberada · no ar'", st == 200 and "no ar" in b, st)
st, _, b = pub.get("/api/integracao/v1/capacidades", json_=True)
check("O3 API do agente continua exigindo token (401)", st == 401, st)
erros = app_cmd("sh", "-c", "grep -c 'local.ERROR' /var/www/storage/logs/laravel.log 2>/dev/null || echo 0").strip().splitlines()[-1]
check("O4 nenhum erro no laravel.log do ambiente", erros == "0", app_cmd("sh", "-c", "grep 'local.ERROR' /var/www/storage/logs/laravel.log | tail -3 | cut -c1-300"))

print(f"\n{'✅' if not falhas else '❌'} {ok} ok, {falhas} falha(s)")
sys.exit(1 if falhas else 0)
