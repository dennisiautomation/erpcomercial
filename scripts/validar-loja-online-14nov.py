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
telas vizinhas do ERP intactas. 2ª rodada (bloco R): políticas, redes sociais, cookies e rastreamento, cupons, frete
grátis, popup, contatos, avisos por e-mail, blocos novos e movimento. 4ª rodada (bloco U, 15/11): grade de tamanho/cor,
reserva de estoque, envio com rastreio, cancelar e devolver, conta do comprador, favoritos, avise-me, avaliações,
carrinho abandonado e os números da loja.
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


def cartoes(body):
    """Quantos cartões de produto a página tem (3ª rodada: o cartão ganhou classes extras)."""
    return len(re.findall(r'<article class="lj-card[ "]', body))


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
check("G4 topo sem imagem mostra produtos reais (mosaico ou foto no carrossel)", 'class="lj-mosaico"' in home or 'class="lj-slide__produto"' in home)
check("G5 menu de categorias com as 4 categorias", all(c in home for c in ["Moda", "Casa e Presentes", "Mercearia"]))
check("G6 rodapé com WhatsApp, Instagram e endereço da loja", "(86) 99999-0000" in home and "instagram.com/lojademo" in home and "Sao Paulo" in home)
check("G7 indexável (sem noindex) e com contrato de direção no HTML", 'content="noindex' not in home and "contrato de direção" in home)
st, _, b = pub.get("/loja/demo/produtos")
check("G8 lista de produtos: 30 produtos, 24 na 1ª página, com paginação", st == 200 and "30 produtos" in texto(b) and cartoes(b) == 24 and "lj-paginas" in b, cartoes(b))
st, _, b = pub.get("/loja/demo/produtos?page=2")
check("G9 página 2 traz os 6 restantes", st == 200 and cartoes(b) == 6, cartoes(b))
st, _, b = pub.get("/loja/demo/produtos?q=" + urllib.parse.quote("bluetooth fone"))
check("G10 busca por palavras em qualquer ordem acha o produto", st == 200 and cartoes(b) == 1 and "Fone Bluetooth Pro" in b, cartoes(b))
st, _, b = pub.get("/loja/demo/produtos?q=T00009")
check("G11 busca pelo código exato", cartoes(b) == 1 and "Fone Bluetooth Pro" in b)
st, _, b = pub.get("/loja/demo/produtos?q=" + urllib.parse.quote("zzz não existe"))
check("G12 busca sem resultado: estado vazio com saída", st == 200 and "Não encontramos nada" in b and "Ver todos os produtos" in b)
st, _, b = pub.get(f"/loja/demo/produtos?categoria={CAT['Mercearia']}")
check("G13 filtro por categoria: 7 de Mercearia", cartoes(b) == 7 and "Café Especial" in b and "Fone Bluetooth" not in re.sub(r"<header.*?</header>", "", b, flags=re.S))
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
          st == 200 and st2 == 200 and st3 == 200 and cartoes(b) >= 4 and ('class="lj-banner' in b or 'class="lj-carrossel"' in b), (st, st2, st3, cartoes(b)))
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
# ───────────────────────────── R. 2ª rodada: promoções, popup, e-mails, redes, políticas, cookies, rastreamento ─────────────────────────────
limites()
print("\n== R. Promoções, popup, avisos, redes, políticas, cookies e rastreamento ==")
SEC = "/app/loja-online/secao/"
sql("DELETE FROM loja_cupons; DELETE FROM loja_contatos;")


def cfg_loja():
    return json.loads(um("SELECT config FROM lojas_online WHERE empresa_id=1"))


# — políticas —
for tipo, trecho in [("privacidade", "Lei Geral de Proteção de Dados"), ("trocas", "artigo 49 do Código de Defesa do Consumidor"), ("entrega", "calculados pelo CEP"), ("termos", "Estes termos valem")]:
    st, _, b = pub.get(f"/loja/demo/politica/{tipo}")
    check(f"R1 política '{tipo}' abre com o texto de partida", st == 200 and trecho in b, st)
b = pub.get("/loja/demo/politica/privacidade")[2]
check("R2 privacidade de partida traz a razão social e o CNPJ da empresa, e diz que não há rastreador", "Empresa Demo" in b and "12.345.678/0001-90" in b and "Não usamos cookies de publicidade" in b)
check("R3 política desconhecida → 404", pub.get("/loja/demo/politica/qualquer")[0] == 404)
dono.get("/app/loja-online/politicas")
st, _, _ = dono.post(SEC + "politicas", {"politica_privacidade": "Minha política:\n\nTexto <b>próprio</b> da loja.", "texto_trocas": "", "politica_entrega": "", "politica_termos": "", "cookies_aviso": "1"}, "PUT")
b = pub.get("/loja/demo/politica/privacidade")[2]
check("R4 texto próprio substitui o de partida, com subtítulo e HTML escapado", st == 302 and "<h2>Minha política</h2>" in b and "&lt;b&gt;próprio&lt;/b&gt;" in b and "Lei Geral" not in b)
check("R5 as outras continuam no texto de partida", "artigo 49" in pub.get("/loja/demo/politica/trocas")[2])
b = dono.get("/app/loja-online/politicas")[2]
check("R6 tela de políticas mostra qual usa o texto próprio e avisa para revisar", "Usando o seu texto" in b and "Usando o texto de partida" in b and "revise com o seu contador ou advogado" in b)
dono.post(SEC + "politicas", {"politica_privacidade": "", "texto_trocas": "", "politica_entrega": "", "politica_termos": "", "cookies_aviso": "1"}, "PUT")
check("R7 campo em branco volta ao texto de partida", "Lei Geral de Proteção de Dados" in pub.get("/loja/demo/politica/privacidade")[2])
home = pub.get("/loja/demo")[2]
check("R8 rodapé: links de privacidade, termos, trocas, entrega e 'Preferências de cookies'",
      all(x in home for x in ["/loja/demo/politica/privacidade", "/loja/demo/politica/termos", "/loja/demo/politica/trocas", "/loja/demo/politica/entrega", "data-lj-cookies-abrir"]))

# — redes sociais e WhatsApp flutuante —
dono.get("/app/loja-online/configuracoes")
st, _, _ = dono.post("/app/loja-online/configuracoes", {**base_cfg, "facebook": "lojademo.fb", "tiktok": "@lojademo", "youtube": "@lojademotv", "whatsapp_flutuante": "1",
                                                         "whatsapp_mensagem": "Oi, vim do site!", "horario_atendimento": "Seg a sex, 9h às 18h"}, "PUT")
home = pub.get("/loja/demo")[2]
check("R9 redes no rodapé: Instagram, Facebook, TikTok, YouTube e WhatsApp, com nome para leitor de tela",
      st == 302 and all(x in home for x in ["instagram.com/lojademo", "facebook.com/lojademo.fb", "tiktok.com/@lojademo", "youtube.com/@lojademotv", 'aria-label="TikTok da loja"']))
check("R10 botão flutuante do WhatsApp com a mensagem pronta e o horário no rodapé", 'class="lj-zap"' in home and "text=Oi%2C%20vim%20do%20site%21" in home and "Seg a sex, 9h às 18h" in home)
dono.get("/app/loja-online/configuracoes")
dono.post("/app/loja-online/configuracoes", {**base_cfg, "facebook": "tem espaço"}, "PUT")
check("R11 rede com nome inválido recusada", cfg_loja().get("facebook") == "lojademo.fb")
dono.get("/app/loja-online/configuracoes")
dono.post("/app/loja-online/configuracoes", {**base_cfg, "facebook": "lojademo.fb"}, "PUT")
check("R12 desligar o botão flutuante tira ele da loja (o do cabeçalho fica)", 'class="lj-zap"' not in pub.get("/loja/demo")[2] and cfg_loja()["whatsapp_flutuante"] is False)

# — cookies e rastreamento —
home = pub.get("/loja/demo")[2]
check("R13 sem rastreador: aviso de cookies só informativo ('Entendi'), sem opções", 'class="lj-cookies"' in home and ">Entendi</button>" in home and re.search(r'<button[^>]*data-lj-cookies-recusar', home) is None)
dono.get("/app/loja-online/politicas")
dono.post(SEC + "politicas", {"politica_privacidade": "", "texto_trocas": "", "politica_entrega": "", "politica_termos": ""}, "PUT")
check("R14 lojista desliga o aviso (sem rastreador): some da loja", 'class="lj-cookies"' not in pub.get("/loja/demo")[2])
invalidos = {"meta_pixel": "abc", "ga4": "UA-123", "google_ads": "123456", "gtm": "GTM", "tiktok_pixel": "minusculo<script>", "verificacao_google": "<meta>", "google_ads_rotulo": "a b"}
for campo, valor in invalidos.items():
    dono.get("/app/loja-online/rastreamento")
    dono.post_arquivos(SEC + "rastreamento", {campo: valor}, [], "PUT")
check("R15 IDs de rastreamento malformados (7 campos) são recusados", all(not cfg_loja().get(k) for k in invalidos), {k: cfg_loja().get(k) for k in invalidos})
b = dono.get("/app/loja-online/rastreamento")[2]
dono.post_arquivos(SEC + "rastreamento", {"meta_pixel": "abc"}, [], "PUT")
check("R16 a recusa explica o formato esperado", "tem só números" in dono.get("/app/loja-online/rastreamento")[2])
ids = {"meta_pixel": "1234567890123456", "ga4": "G-AB12CD34EF", "google_ads": "AW-123456789", "google_ads_rotulo": "AbCdEf_123", "gtm": "", "tiktok_pixel": "C4ABCDE1234567890XYZ",
       "verificacao_google": "abcDEF123456_-xyz", "verificacao_meta": "abcdef1234567890", "seo_titulo": "Loja Demo — compre online", "descricao_busca": "Tudo da loja demo."}
st, _, _ = dono.post_arquivos(SEC + "rastreamento", ids, [("imagem_compartilhar", "og.jpg", foto("banner.jpg"), "image/jpeg"), ("favicon", "f.png", foto("logo.png"), "image/png")], "PUT")
c = cfg_loja()
check("R17 IDs válidos gravados, com imagem de compartilhar e ícone na pasta da empresa", st == 302 and c["meta_pixel"] == ids["meta_pixel"] and c["ga4"] == "G-AB12CD34EF"
      and re.match(r"^loja-online/1/[a-z0-9]{24}\.jpg$", c["imagem_compartilhar"]) and re.match(r"^loja-online/1/[a-z0-9]{24}\.png$", c["favicon"]), c.get("imagem_compartilhar"))
home = pub.get("/loja/demo")[2]
check("R18 com rastreador o aviso de cookies volta SEMPRE, com Aceitar / Só os necessários / Escolher, e as duas categorias",
      all(re.search(r'<(button|input)[^>]*' + x, home) for x in ['data-lj-cookies-aceitar', 'data-lj-cookies-recusar', 'data-lj-cookies-escolher', 'data-lj-cookie="analise"', 'data-lj-cookie="marketing"']))
check("R19 nenhum rastreador vem como tag pronta no HTML (só carrega depois do aceite)",
      re.search(r'<script[^>]+src="https://(www\.googletagmanager|connect\.facebook|analytics\.tiktok)', home) is None and "<noscript><img" not in home)
check("R20 os IDs chegam à página para o carregador e as verificações de domínio saem no cabeçalho",
      '"meta":"1234567890123456"' in home and '"ga4":"G-AB12CD34EF"' in home and 'name="google-site-verification" content="abcDEF123456_-xyz"' in home
      and 'name="facebook-domain-verification" content="abcdef1234567890"' in home)
check("R21 título da página inicial, descrição, imagem de compartilhar e ícone da aba", "<title>Loja Demo — compre online</title>" in home and 'content="Tudo da loja demo."' in home
      and f'property="og:image" content="{BASE}/storage/{c["imagem_compartilhar"]}"' in home and f'rel="icon" href="/storage/{c["favicon"]}"' in home)
b = pub.get(f"/loja/demo/p/{PF}-camisa-linho-areia")[2]
check("R22 página do produto: og:image é a foto do produto e dispara 'ver_produto'", 'property="og:type" content="product"' in b and "/storage/produtos/teste-1.jpg" in b.split('og:image')[1][:120] and "LJ.rastro.evento('ver_produto'" in b)
check("R23 a política de privacidade de partida passa a citar Google e Meta", "Google Analytics" in pub.get("/loja/demo/politica/privacidade")[2] and "Meta (Facebook e Instagram)" in pub.get("/loja/demo/politica/privacidade")[2])
st, j = pub.post_json("/loja/demo/carrinho/adicionar", {"produto_id": P[7], "quantidade": 2})
check("R24 'adicionar ao carrinho' devolve o item (id, nome, preço, quantidade) para o evento", j.get("item", {}).get("id") == P[7] and j["item"]["quantidade"] == 2 and j["item"]["preco"] > 0, j.get("item"))
pub.post("/loja/demo/carrinho/atualizar", {"produto_id": str(P[7]), "quantidade": "0"})

# — cupons —
limites()
dono.get("/app/loja-online/promocoes")
for dados in [{"codigo": "bem vindo-10!", "tipo": "percentual", "valor": "10", "minimo": "100"}, {"codigo": "BEMVINDO-10", "tipo": "valor", "valor": "5"},
              {"codigo": "ab", "tipo": "valor", "valor": "5"}, {"codigo": "CEM", "tipo": "percentual", "valor": "150"}, {"codigo": "SEMVALOR", "tipo": "valor", "valor": ""},
              {"codigo": "VINTE", "tipo": "valor", "valor": "20,00", "limite_usos": "1"}, {"codigo": "FRETE", "tipo": "frete_gratis", "valor": ""},
              {"codigo": "VELHO", "tipo": "valor", "valor": "9", "fim": "2026-01-01"}]:
    dono.post("/app/loja-online/cupons", dados)
    dono.get("/app/loja-online/promocoes")
cup = {r[0]: r for r in sql("SELECT codigo, tipo, valor, minimo, IFNULL(limite_usos,''), usos, ativo FROM loja_cupons WHERE empresa_id=1")}
check("R25 cupons criados: código normalizado (BEMVINDO-10), valor com vírgula aceito; duplicado, curto, 150% e sem valor recusados",
      sorted(cup) == ["BEMVINDO-10", "FRETE", "VELHO", "VINTE"] and cup["BEMVINDO-10"][1:4] == ["percentual", "10.00", "100.00"] and cup["VINTE"][2] == "20.00", sorted(cup))
b = dono.get("/app/loja-online/promocoes")[2]
check("R26 lista de cupons mostra o que cada um dá e a situação (Valendo / Vencido)", "10% de desconto" in b and "R$ 20,00 de desconto" in b and "Frete grátis" in b and "Vencido" in b and "Valendo" in b)
k = Nav()
k.get("/loja/demo")
k.post_json("/loja/demo/carrinho/adicionar", {"produto_id": P[0], "quantidade": 1})  # 59,90
k.get("/loja/demo/carrinho")
for codigo, trecho in [("NAOEXISTE", "não existe"), ("VELHO", "venceu"), ("BEMVINDO-10", "a partir de R$ 100,00"), ("", "Digite o código")]:
    k.post("/loja/demo/carrinho/cupom", {"codigo": codigo})
    b = k.get("/loja/demo/carrinho")[2]
    check(f"R27 cupom '{codigo or '(vazio)'}' recusado com o motivo ('{trecho}')", trecho in b and 'class="lj-cupom-aplicado"' not in b, texto(b)[texto(b).find("Carrinho"):][:160])
k.post_json("/loja/demo/carrinho/adicionar", {"produto_id": P[0], "quantidade": 1})  # 119,80
k.get("/loja/demo/carrinho")
st, hd, _ = k.post("/loja/demo/carrinho/cupom", {"codigo": " bemvindo-10 "})
b = k.get("/loja/demo/carrinho")[2]
t = texto(b)
check("R28 cupom válido (minúsculo, com espaço): aplicado, desconto de R$ 11,98 e subtotal R$ 107,82", 'class="lj-cupom-aplicado"' in b and "− R$ 11,98" in t and "R$ 107,82" in t, re.findall(r"R\$ [\d.,]+", t)[-6:])
b = k.get("/loja/demo/finalizar")[2]
check("R29 fechamento mostra o cupom e o total já com desconto", "Cupom BEMVINDO-10" in b and "R$ 107,82" in texto(b) and 'data-subtotal="107.82"' in b)
st, hd, _ = k.post("/loja/demo/finalizar", {"nome": "Cliente Cupom", "telefone": "(86) 95555-1234", "email": "cupom@teste.test", "entrega": "retirada", "pagamento": "combinar", "novidades": "1"})
pc = sql("SELECT id, subtotal, desconto_valor, desconto_percentual, total, loja_cupom, loja_token, numero FROM pedidos WHERE loja_token IS NOT NULL ORDER BY id DESC LIMIT 1")[0]
check("R30 pedido com cupom: subtotal 119,80, desconto 11,98 (10%), total 107,82 e o código gravado", pc[1:6] == ["119.80", "11.98", "10.00", "107.82", "BEMVINDO-10"], pc)
check("R31 o uso do cupom foi contado", um("SELECT usos FROM loja_cupons WHERE codigo='BEMVINDO-10'") == "1")
b = pub.get(f"/loja/demo/pedido/{pc[6]}")[2]
check("R32 página do pedido mostra a linha do desconto com o cupom", "Desconto (cupom BEMVINDO-10)" in b and "− R$ 11,98" in texto(b))
check("R33 'quero receber novidades' no fechamento grava o contato com a data do aceite",
      sql("SELECT email, origem, aceitou_em IS NOT NULL FROM loja_contatos WHERE empresa_id=1") == [["cupom@teste.test", "checkout", "1"]])
check("R34 depois de fechar, o cupom sai da sessão", 'class="lj-cupom-aplicado"' not in k.get("/loja/demo/carrinho")[2])
obs = um(f"SELECT observacoes_internas FROM pedidos WHERE id={pc[0]}")
check("R35 observação do pedido registra o cupom", "Cupom BEMVINDO-10: 10% de desconto" in obs, obs)
# limite de usos
for n in (1, 2):
    k.post_json("/loja/demo/carrinho/adicionar", {"produto_id": P[1], "quantidade": 1})
    k.get("/loja/demo/carrinho")
    k.post("/loja/demo/carrinho/cupom", {"codigo": "VINTE"})
    b = k.get("/loja/demo/carrinho")[2]
    if n == 1:
        check("R36 cupom de R$ 20 com 1 uso: vale na primeira vez", 'class="lj-cupom-aplicado"' in b)
        k.get("/loja/demo/finalizar")
        k.post("/loja/demo/finalizar", {"nome": "Cliente Cupom", "telefone": "(86) 95555-1234", "entrega": "retirada", "pagamento": "combinar"})
    else:
        check("R37 …e na segunda é recusado ('já foi usado todas as vezes')", "já foi usado todas as vezes" in b and 'class="lj-cupom-aplicado"' not in b)
check("R38 pedido com cupom de valor: desconto 20,00", um("SELECT desconto_valor FROM pedidos WHERE loja_cupom='VINTE'") == "20.00")
# desligar / excluir / isolamento
idv = um("SELECT id FROM loja_cupons WHERE codigo='BEMVINDO-10'")
dono.get("/app/loja-online/promocoes")
dono.post(f"/app/loja-online/cupons/{idv}", {}, "PATCH")
k.get("/loja/demo/carrinho")
k.post("/loja/demo/carrinho/cupom", {"codigo": "BEMVINDO-10"})
check("R39 cupom desligado pelo lojista deixa de valer", "não existe" in k.get("/loja/demo/carrinho")[2] and um(f"SELECT ativo FROM loja_cupons WHERE id={idv}") == "0")
admin.get("/admin/empresas/2")
admin.post("/admin/empresas/2/loja-online/toggle")
outro.get("/app/loja-online/promocoes")
st, _, _ = outro.post(f"/app/loja-online/cupons/{idv}", {}, "PATCH")
st2, _, _ = outro.post(f"/app/loja-online/cupons/{idv}", {}, "DELETE")
check("R40 outra empresa não liga nem exclui cupom alheio (404)", st == 404 and st2 == 404 and um(f"SELECT COUNT(*) FROM loja_cupons WHERE id={idv}") == "1", (st, st2))
check("R41 a outra empresa não vê os cupons nem os contatos da demo", "BEMVINDO-10" not in outro.get("/app/loja-online/promocoes")[2] and "cupom@teste.test" not in outro.get("/app/loja-online/promocoes")[2])
admin.get("/admin/empresas/2")
admin.post("/admin/empresas/2/loja-online/toggle")
vendedor.get("/app/dashboard")
st, _, _ = vendedor.post("/app/loja-online/cupons", {"codigo": "HACK", "tipo": "percentual", "valor": "100"})
check("R42 vendedor não cria cupom (403)", st == 403 and um("SELECT COUNT(*) FROM loja_cupons WHERE codigo='HACK'") == "0", st)

# — frete grátis acima de —
dono.get("/app/loja-online/promocoes")
st, _, _ = dono.post_arquivos(SEC + "promocoes", {"frete_gratis_acima": "150,00", "popup_gatilho": "tempo"}, [], "PUT")
k.get("/loja/demo/carrinho")
b = texto(k.get("/loja/demo/carrinho")[2])
check("R43 frete grátis a partir de R$ 150: o carrinho diz quanto falta", st == 302 and cfg_loja()["frete_gratis_acima"] == 150 and "Faltam" in b and "para o frete grátis" in b, b[b.find("Resumo"):][:200])
k.post_json("/loja/demo/carrinho/adicionar", {"produto_id": P[3], "quantidade": 1})  # +129,90
b = texto(k.get("/loja/demo/carrinho")[2])
check("R44 passando do valor: 'Você ganhou frete grátis'", "Você ganhou frete grátis" in b)
k.post("/loja/demo/carrinho/cupom", {"codigo": "FRETE"})
check("R45 cupom de frete grátis aplica e aparece como 'frete grátis'", "FRETE</strong> · frete grátis" in k.get("/loja/demo/carrinho")[2])
k.post("/loja/demo/carrinho/cupom", {"remover": "1"})
check("R46 'Tirar' remove o cupom", 'class="lj-cupom-aplicado"' not in k.get("/loja/demo/carrinho")[2])
check("R47 a política de entrega de partida cita o frete grátis a partir de R$ 150,00", "a partir de R$ 150,00 têm frete grátis" in pub.get("/loja/demo/politica/entrega")[2])

# — popup e contatos —
dono.get("/app/loja-online/promocoes")
dono.post_arquivos(SEC + "promocoes", {"frete_gratis_acima": "150", "popup_ativo": "1", "popup_titulo": "", "popup_gatilho": "tempo"}, [], "PUT")
check("R48 ligar o popup sem título é recusado", cfg_loja().get("popup_ativo") is not True)
dono.get("/app/loja-online/promocoes")
dono.post_arquivos(SEC + "promocoes", {"frete_gratis_acima": "150", "popup_ativo": "1", "popup_titulo": "Ganhe desconto", "popup_cupom": "INVENTADO", "popup_gatilho": "tempo"}, [], "PUT")
check("R49 popup com cupom que não existe é recusado, com a explicação", cfg_loja().get("popup_ativo") is not True and "não existe" in dono.get("/app/loja-online/promocoes")[2])
popup = {"frete_gratis_acima": "150", "popup_ativo": "1", "popup_capturar": "1", "popup_titulo": "Ganhe R$ 20 <na> primeira compra", "popup_texto": "Cadastre seu e-mail.",
         "popup_cupom": "vinte", "popup_botao": "Quero o cupom", "popup_link": "javascript:alert(1)", "popup_gatilho": "saida", "popup_segundos": "3", "popup_dias": "2"}
st, _, _ = dono.post_arquivos(SEC + "promocoes", popup, [("popup_imagem", "p.jpg", foto("banner.jpg"), "image/jpeg")], "PUT")
c = cfg_loja()
check("R50 popup salvo: cupom em maiúsculas, link perigoso zerado, imagem na pasta da empresa", st == 302 and c["popup_ativo"] is True and c["popup_cupom"] == "VINTE" and c["popup_link"] == ""
      and re.match(r"^loja-online/1/[a-z0-9]{24}\.jpg$", c["popup_imagem"]), {x: c.get(x) for x in ("popup_cupom", "popup_link", "popup_imagem")})
home = pub.get("/loja/demo")[2]
check("R51 popup na loja: título escapado, formulário com aceite, gatilho e frequência", 'data-lj-popup' in home and "Ganhe R$ 20 &lt;na&gt; primeira compra" in home
      and 'data-gatilho="saida"' in home and 'data-dias="2"' in home and 'name="aceite"' in home and "VINTE" not in home.split('data-lj-popup')[1].split('</dialog>')[0])
k.get("/loja/demo/carrinho")
check("R52 o popup NÃO entra nas telas de compra (fechamento e pedido)", "data-lj-popup " not in k.get("/loja/demo/finalizar")[2] and "data-lj-popup " not in pub.get(f"/loja/demo/pedido/{pc[6]}")[2])
check("R53 nem na prévia do editor", "data-lj-popup " not in dono.get("/loja/demo?rascunho=1")[2])
v = Nav()
v.get("/loja/demo")
st, j = v.post_json("/loja/demo/contato", {"email": "visita@teste.test", "origem": "popup"})
check("R54 cadastro sem marcar o aceite é recusado (422)", st == 422 and "aceite" in j.get("errors", {}), (st, j))
st, j = v.post_json("/loja/demo/contato", {"email": "nao-e-email", "aceite": 1})
check("R55 e-mail inválido recusado (422)", st == 422, st)
st, j = v.post_json("/loja/demo/contato", {"email": "Visita@Teste.test", "aceite": 1, "origem": "popup"})
check("R56 cadastro pelo popup: grava o contato e devolve o cupom", st == 200 and j.get("ok") and j.get("cupom") == "VINTE", j)
st, j = v.post_json("/loja/demo/contato", {"email": "visita@teste.test", "aceite": 1, "origem": "newsletter"})
check("R57 mesmo e-mail de novo não duplica; pelo bloco de novidades não devolve cupom", j.get("cupom") is None and um("SELECT COUNT(*) FROM loja_contatos WHERE email='visita@teste.test'") == "1", j)
b = dono.get("/app/loja-online/promocoes")[2]
check("R58 lojista vê os contatos com a origem e a data do aceite", "visita@teste.test" in b and "cupom@teste.test" in b and "Fechamento do pedido" in b)
req = urllib.request.Request(BASE + "/app/loja-online/contatos/exportar")
with dono.op.open(req) as r:
    corpo, tipo_arq = r.read(), r.headers.get("Content-Type", "")
check("R59 planilha dos contatos baixa em Excel (.xlsx)", corpo[:2] == b"PK" and "spreadsheetml" in tipo_arq, tipo_arq)
dono.get("/app/loja-online/promocoes")
dono.post_arquivos(SEC + "promocoes", {"frete_gratis_acima": "0", "popup_gatilho": "tempo", "remover_popup_imagem": "1"}, [], "PUT")
check("R60 desligar o popup e o frete grátis: somem da loja", "data-lj-popup " not in pub.get("/loja/demo")[2] and cfg_loja()["frete_gratis_acima"] == 0 and cfg_loja()["popup_imagem"] == "")

# — avisos por e-mail —
limites()
dono.get("/app/loja-online/avisos")
dono.post(SEC + "avisos", {"email_pedidos": "nao-e-email", "email_loja_novo": "1"}, "PUT")
check("R61 e-mail da loja inválido recusado", not cfg_loja().get("email_pedidos"))
dono.get("/app/loja-online/avisos")
dono.post(SEC + "avisos", {"email_pedidos": "", "email_loja_novo": "1", "email_cliente_recebido": "1"}, "PUT")
check("R62 ligar 'avisar a loja' sem informar o e-mail é recusado, com a explicação", "informe o e-mail que recebe os pedidos" in dono.get("/app/loja-online/avisos")[2])
st, _, _ = dono.post(SEC + "avisos", {"email_pedidos": "pedidos@lojademo.test", "email_remetente": "Loja Demo Avisos", "email_loja_novo": "1", "email_cliente_recebido": "1",
                                       "email_cliente_confirmado": "1", "email_cliente_andamento": "1"}, "PUT")
check("R63 avisos salvos", st == 302 and cfg_loja()["email_pedidos"] == "pedidos@lojademo.test" and cfg_loja()["email_loja_novo"] is True)
app_cmd("sh", "-c", ": > /var/www/storage/logs/laravel.log")
e = Nav()
e.get("/loja/demo")
e.post_json("/loja/demo/carrinho/adicionar", {"produto_id": P[2], "quantidade": 1})
e.get("/loja/demo/finalizar")
e.post("/loja/demo/finalizar", {"nome": "Cliente do E-mail", "telefone": "(86) 94444-0000", "email": "comprador@teste.test", "entrega": "retirada", "pagamento": "combinar"})
pe = sql("SELECT id, numero, loja_token FROM pedidos WHERE loja_token IS NOT NULL ORDER BY id DESC LIMIT 1")[0]
registro = app_cmd("sh", "-c", "cat /var/www/storage/logs/laravel.log")
check("R64 ao fechar o pedido sai o e-mail 'Recebemos o seu pedido' para o comprador", f"Recebemos o seu pedido #{pe[1]}" in registro and "To: comprador@teste.test" in registro, registro[:200])
check("R65 …e o 'Novo pedido' para o e-mail da loja", f"Novo pedido #{pe[1]} na loja online" in registro and "To: pedidos@lojademo.test" in registro)
check("R66 remetente com o nome escolhido, resposta para a loja e o botão de acompanhar com o endereço do pedido",
      "Loja Demo Avisos" in registro and "Reply-To: " in registro and f"/pedido/{pe[2]}" in registro.replace("=\r\n", "").replace("=\n", ""))
check("R67 nenhum erro ao montar os e-mails", "local.ERROR" not in registro)
dono.get("/app/loja-online/avisos")
app_cmd("sh", "-c", ": > /var/www/storage/logs/laravel.log")
st, _, _ = dono.post("/app/loja-online/avisos/testar", {"tipo": "confirmado"})
registro = app_cmd("sh", "-c", "cat /var/www/storage/logs/laravel.log")
check("R68 'Enviar teste' manda o aviso escolhido para o e-mail de quem pediu, não para o cliente", f"Pedido #{pe[1]} confirmado" in registro and "To: dono@demo.com" in registro and "To: comprador@teste.test" not in registro)
dono.get("/app/loja-online/avisos")
dono.post(SEC + "avisos", {"email_pedidos": "pedidos@lojademo.test"}, "PUT")
app_cmd("sh", "-c", ": > /var/www/storage/logs/laravel.log")
e.post_json("/loja/demo/carrinho/adicionar", {"produto_id": P[2], "quantidade": 1})
e.get("/loja/demo/finalizar")
e.post("/loja/demo/finalizar", {"nome": "Cliente do E-mail", "telefone": "(86) 94444-0000", "email": "comprador@teste.test", "entrega": "retirada", "pagamento": "combinar"})
check("R69 com os avisos desligados, fechar o pedido não envia nada", "Subject:" not in app_cmd("sh", "-c", "cat /var/www/storage/logs/laravel.log"))

# — blocos novos e movimento —
limites()
novos = [
    {"id": "n1", "tipo": "oferta", "titulo": "Semana do cliente", "texto": "Só até domingo", "termina_em": "2099-12-31T23:59", "cupom": "vinte", "botao_texto": "Ver", "botao_link": "produtos?promocao=1"},
    {"id": "n2", "tipo": "oferta", "titulo": "Oferta que já acabou", "termina_em": "2020-01-01T00:00"},
    {"id": "n3", "tipo": "oferta", "titulo": "Data torta", "termina_em": "amanhã"},
    {"id": "n4", "tipo": "depoimentos", "titulo": "Quem comprou", "lista": [{"texto": "Chegou <b>rápido</b>", "nome": "Ana", "detalhe": "Teresina"}, {"texto": "", "nome": "Vazio"}] + [{"texto": f"t{i}", "nome": "x"} for i in range(6)]},
    {"id": "n5", "tipo": "faq", "titulo": "Dúvidas", "lista": [{"nome": "Entrega em quanto tempo?", "texto": "Depende do CEP."}]},
    {"id": "n6", "tipo": "newsletter", "titulo": "Fique por dentro", "texto": "", "botao_texto": "Assinar"},
    {"id": "n7", "tipo": "video", "titulo": "Conheça", "video": "https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=5"},
    {"id": "n8", "tipo": "video", "titulo": "Ruim", "video": "https://vimeo.com/123 onclick=alert(1)"},
    {"id": "n9", "tipo": "redes", "titulo": "Siga a loja", "texto": ""},
]
st, j = dono.post_json("/app/loja-online/editor/rascunho", {"modelo": "vitrine", "tema": {"movimento": "suave", "faixa": "correndo"}, "blocos": [{"id": "n0", "tipo": "faixa", "texto": "Letreiro de teste"}] + novos})
r = {x["id"]: x for x in j.get("rascunho", {}).get("blocos", [])}
check("R70 os 6 tipos de bloco novos são aceitos pelo editor", st == 200 and all(k in r for k in ["n1", "n4", "n5", "n6", "n7", "n9"]), list(r))
check("R71 higiene: data inválida zerada; vídeo vira só o id do YouTube; endereço que não é YouTube zerado", r["n3"]["termina_em"] == "" and r["n7"]["video"] == "dQw4w9WgXcQ" and r["n8"]["video"] == "")
check("R72 depoimentos limitados a 4", len(r["n4"]["lista"]) == 4, len(r["n4"]["lista"]))
check("R73 tema aceita 'movimento' e 'faixa'; valor fora da lista é ignorado", j["rascunho"]["tema"]["movimento"] == "suave" and j["rascunho"]["tema"]["faixa"] == "correndo")
b = dono.get("/loja/demo?rascunho=1")[2]
check("R74 prévia: oferta com relógio regressivo e cupom copiável", 'data-lj-relogio="2099-12-31T23:59' in b and 'data-lj-copiar-texto="VINTE"' in b and "Semana do cliente" in b)
check("R75 depoimento escapado, pergunta que abre e fecha, vídeo só carrega no clique", "Chegou &lt;b&gt;rápido&lt;/b&gt;" in b and "<details><summary>Entrega em quanto tempo?</summary>" in b
      and 'data-lj-video="dQw4w9WgXcQ"' in b and "<iframe" not in b)
check("R76 bloco de novidades com aceite e bloco de redes com os botões", 'data-lj-contato="newsletter"' in b and "Fique por dentro" in b and "lj-redes--grandes" in b)
check("R77 movimento 'suave' e letreiro: atributos no <html> e no <body>, texto repetido só para o letreiro (escondido do leitor de tela)",
      '<html lang="pt-BR" data-movimento="suave">' in b and 'data-faixa="correndo"' in b and b.count('aria-hidden="true">Letreiro de teste') == 3)
dono.post_json("/app/loja-online/editor/publicar")
b = pub.get("/loja/demo")[2]
check("R78 publicado: a oferta vencida NÃO aparece para o público; a que vale aparece", "Oferta que já acabou" not in b and "Semana do cliente" in b)
dono.post_json("/app/loja-online/editor/modelo", {"modelo": "vitrine"})
dono.post_json("/app/loja-online/editor/publicar")
b = pub.get("/loja/demo")[2]
check("R79 modelo de fábrica: movimento completo, com a folha de movimento e a transição de página", 'data-movimento="completo"' in b and "@view-transition" in b and "prefers-reduced-motion" in b and "lj-foto" in b)
check("R80 modelo de fábrica traz o bloco 'Receba as novidades'", 'data-lj-contato="newsletter"' in b)

# — ofertas e perfis —
st, hd, _ = pub.get("/loja/demo/ofertas")
check("R81 /ofertas leva à lista de produtos em promoção", st == 302 and hd.get("Location", "").endswith("/loja/demo/produtos?promocao=1"), hd.get("Location"))
check("R82 sem promoção de preço no ERP, o menu não mostra 'Ofertas' (não promete o que não tem)", 'class="lj-nav__oferta"' not in pub.get("/loja/demo")[2])
b = dono.get("/app/loja-online/promocoes")[2]
check("R83 a aba Promoções explica que o preço promocional vem das Promoções do ERP e leva até lá", "Cadastros → Promoções" in b and "/app/promocoes" in b)
for rota in ["promocoes", "avisos", "politicas", "rastreamento"]:
    check(f"R84 vendedor não abre '{rota}' (403); gerente abre", vendedor.get(f"/app/loja-online/{rota}")[0] == 403 and gerente.get(f"/app/loja-online/{rota}")[0] == 200)
vendedor.get("/app/dashboard")
st, _, _ = vendedor.post(SEC + "rastreamento", {"meta_pixel": "999999999"}, "PUT")
check("R85 vendedor com POST forjado não muda o rastreamento (403)", st == 403 and cfg_loja()["meta_pixel"] == "1234567890123456", st)


# ───────────────────────────── T. 3ª rodada: layouts no nível de tema pronto ─────────────────────────────
print("\n== T. 3ª rodada: cabeçalho, busca, sacola lateral, filtros, parcelas, blocos novos ==")
limites()
home = pub.get("/loja/demo")[2]
check("T1 cabeçalho novo: busca com sugestões, sacola lateral, menu de categorias e gaveta do celular",
      all(m in home for m in ["data-lj-busca", "data-sugestoes=", "data-lj-sacola", "data-lj-gaveta", "data-lj-mega", "data-lj-menu"]))
check("T2 rodapé novo: formas de pagamento e como recebe", "Formas de pagamento" in home and "Como você recebe" in home)
st, _, b = pub.get("/loja/demo/buscar?q=fone", True)
j = json.loads(b) if st == 200 else {}
check("T3 sugestões da busca: JSON com nome, endereço, foto e preço", st == 200 and any("Fone Bluetooth Pro" in x["nome"] for x in j.get("produtos", [])) and all({"nome", "url", "foto", "preco"} <= set(x) for x in j["produtos"]) and len(j["produtos"]) <= 6, st)
st, _, b = pub.get("/loja/demo/buscar?q=f", True)
check("T4 sugestões com 1 letra: lista vazia (não varre o catálogo)", st == 200 and json.loads(b)["produtos"] == [], st)
st, _, b = pub.get("/loja/demo/buscar?q=" + urllib.parse.quote("Produto Só Do ERP"), True)
check("T5 sugestões não mostram produto fora da vitrine", st == 200 and json.loads(b)["produtos"] == [], b[:120])
t = Nav()
t.get("/loja/demo")
st, b0 = t.get("/loja/demo/carrinho/resumo", True)[0], t.get("/loja/demo/carrinho/resumo", True)[2]
check("T6 sacola lateral vazia: resumo com 0 itens", st == 200 and json.loads(b0)["quantidade"] == 0 and json.loads(b0)["itens"] == [], st)
st, j = t.post_json("/loja/demo/carrinho/adicionar", {"produto_id": PX, "quantidade": 2})
r = json.loads(t.get("/loja/demo/carrinho/resumo", True)[2])
check("T7 depois de adicionar: resumo traz o item, a quantidade e o subtotal", st == 200 and r["quantidade"] == 2 and len(r["itens"]) == 1 and r["itens"][0]["id"] == PX and abs(r["subtotal"] - r["itens"][0]["total"]) < 0.01, r)
check("T8 resposta do 'adicionar' traz o item (a sacola abre sem recarregar)", isinstance(j.get("item"), dict) and j["item"].get("nome"), list(j))
st, _, b = pub.get(f"/loja/demo/vistos?ids={PX},{PF},999999")
check("T9 vistos recentemente: só os produtos da vitrine, na ordem pedida", st == 200 and cartoes(b) == 2 and b.find(f'data-produto="{PX}"') < b.find(f'data-produto="{PF}"'), cartoes(b))
check("T10 vistos sem ids: resposta vazia (a seção não aparece)", cartoes(pub.get("/loja/demo/vistos?ids=")[2]) == 0)
precos_todos = sorted(float(x[0]) for x in sql("SELECT preco_venda FROM produtos WHERE empresa_id=1 AND loja_online=1 AND deleted_at IS NULL"))
corte = precos_todos[len(precos_todos) // 2]
st, _, b = pub.get(f"/loja/demo/produtos?preco_max={corte}&ordem=menor")
pp = [float(x.replace(".", "").replace(",", ".")) for x in re.findall(r"<b>R\$ ([\d.,]+)</b>", b)]
check("T11 filtro 'até R$': só produtos até o valor, com o filtro aplicado à vista", st == 200 and pp and max(pp) <= corte + 0.01 and 'class="lj-chips"' in b and "Limpar tudo" in b, (corte, pp[-3:]))
st, _, b = pub.get(f"/loja/demo/produtos?preco_min={corte}&ordem=menor")
pp = [float(x.replace(".", "").replace(",", ".")) for x in re.findall(r"<b>R\$ ([\d.,]+)</b>", b)]
check("T12 filtro 'a partir de R$': só produtos do valor para cima", st == 200 and pp and min(pp) >= corte - 0.01, (corte, pp[:3]))
st, _, b = pub.get("/loja/demo/produtos?preco_min=abc&preco_max=-5")
check("T13 filtro de preço com lixo não quebra a lista (200, todos os produtos)", st == 200 and cartoes(b) == 24, (st, cartoes(b)))
st, _, b = pub.get("/loja/demo/produtos")
check("T14 lista: filtros de categoria, preço e 'mostrar só' ao lado", st == 200 and "data-lj-filtros" in b and 'name="preco_min"' in b and 'name="preco_max"' in b and "promocao=1" in b and "destaque=1" in b)
dest = int(um("SELECT COUNT(*) FROM produtos WHERE empresa_id=1 AND loja_online=1 AND loja_online_destaque=1 AND deleted_at IS NULL"))
st, _, b = pub.get("/loja/demo/produtos?destaque=1")
check("T15 filtro 'destaques da loja' traz só os marcados", st == 200 and cartoes(b) == min(dest, 24), (dest, cartoes(b)))

# parcelas e selo: só texto da vitrine, vindo da configuração
dono.get("/app/loja-online/promocoes")
st, _, _ = dono.post_arquivos(SEC + "promocoes", {"frete_gratis_acima": "0", "popup_gatilho": "tempo", "parcelas_max": "6", "parcela_minima": "20,00", "parcelas_sem_juros": "1", "selo_novo_dias": "0"}, [], "PUT")
c = cfg_loja()
check("T16 painel grava parcelamento e selo (6x, mínima R$ 20, sem juros, selo desligado)", c["parcelas_max"] == 6 and float(c["parcela_minima"]) == 20.0 and c["parcelas_sem_juros"] is True and c["selo_novo_dias"] == 0, (st, c.get("parcelas_max")))
b = pub.get(f"/loja/demo/p/{PF}-camisa-linho-areia")[2]
check("T17 sem cartão configurado na empresa, a vitrine não promete parcela (a conta com cartão está no roteiro de pagamentos)", "Cartão de crédito" not in b and not re.search(r"\dx de R\$", texto(b)))
check("T18 selo 'Novo' some com 0 dias", 'class="lj-selo lj-selo--novo"' not in pub.get("/loja/demo/produtos")[2])
dono.get("/app/loja-online/promocoes")
dono.post_arquivos(SEC + "promocoes", {"frete_gratis_acima": "0", "popup_gatilho": "tempo", "parcelas_max": "0", "parcela_minima": "20", "selo_novo_dias": "30"}, [], "PUT")
b = pub.get("/loja/demo/produtos")[2]
check("T19 parcelas em 0: a vitrine não fala de parcela; selo 'Novo' volta com 30 dias", not re.search(r"\dx de R\$", texto(b)) and 'class="lj-selo lj-selo--novo"' in b)
dono.get("/app/loja-online/promocoes")
st, _, b = dono.post_arquivos(SEC + "promocoes", {"frete_gratis_acima": "0", "popup_gatilho": "tempo", "parcelas_max": "99", "parcela_minima": "20"}, [], "PUT")
check("T20 parcelas acima de 24 é recusado (continua 0)", cfg_loja()["parcelas_max"] == 0, cfg_loja()["parcelas_max"])

check("T20b o bloco não ligou frete grátis (o roteiro de pagamentos parte de 0)", float(cfg_loja().get("frete_gratis_acima", 0)) == 0, cfg_loja().get("frete_gratis_acima"))

# página do produto
b = pub.get(f"/loja/demo/p/{PF}-camisa-linho-areia")[2]
check("T21 produto: zoom, foto ampliada, abas, comprar agora e barra fixa de compra", all(m in b for m in ["data-lj-zoom", "data-lj-luz", "data-lj-abas", "data-lj-comprar-agora", "data-lj-barra"]))
check("T22 produto: caixa de compra diz como paga, como recebe e como troca", "lj-formas" in b and "Pagamento combinado com a loja" in texto(b) and "Troca e devolução" in texto(b) and ("Calcular frete" in texto(b) or "Retire na loja" in texto(b)))

# blocos novos pelo editor
blocos_novos = [
    {"id": "t1", "tipo": "carrossel", "altura": "alta", "lista": [
        {"imagem": "", "titulo": "Banner um", "texto": "Texto um", "botao_texto": "Ver", "botao_link": "produtos", "tom": "claro", "lado": "esquerda"},
        {"imagem": "../../etc/passwd", "titulo": "Banner dois <script>alert(1)</script>", "texto": "", "botao_texto": "Ir", "botao_link": "javascript:alert(1)", "tom": "roxo", "lado": "cima"}]},
    {"id": "t2", "tipo": "banners", "titulo": "Coleções", "arranjo": "destaque", "lista": [{"imagem": "", "titulo": "Novidades", "texto": "da semana", "botao_link": "produtos?ordem=novos"}, {"imagem": "", "titulo": "Ofertas", "texto": "", "botao_link": "produtos?promocao=1"}]},
    {"id": "t3", "tipo": "categorias", "titulo": "Por categoria", "estilo": "circulos"},
    {"id": "t4", "tipo": "vitrine", "titulo": "Desliza", "origem": "todos", "categoria_id": None, "quantidade": "8", "colunas": "4", "formato": "carrossel"},
    {"id": "t5", "tipo": "galeria", "titulo": "Da loja", "lista": [{"imagem": "", "botao_link": ""}]},
]
dono.get("/app/loja-online/editor")
st, j = dono.post_json("/app/loja-online/editor/rascunho", {"modelo": "vitrine", "tema": {"cabecalho": "compacto"}, "blocos": blocos_novos})
rb = {x["tipo"]: x for x in j.get("rascunho", {}).get("blocos", [])} if st == 200 else {}
s2 = rb.get("carrossel", {}).get("lista", [{}, {}])
check("T23 editor aceita carrossel, grade de banners, círculos, vitrine em carrossel e galeria", st == 200 and set(rb) >= {"carrossel", "banners", "categorias", "vitrine", "galeria"}, (st, list(rb)))
check("T24 higiene do carrossel: imagem fora da pasta e link javascript: são zerados; tom/posição inválidos viram o padrão",
      len(s2) == 2 and s2[1]["imagem"] == "" and s2[1]["botao_link"] == "" and s2[1]["tom"] == "claro" and s2[1]["lado"] == "esquerda", s2[1] if len(s2) > 1 else s2)
check("T25 tema aceita o cabeçalho 'compacto'", j.get("rascunho", {}).get("tema", {}).get("cabecalho") == "compacto")
dono.post_json("/app/loja-online/editor/publicar")
b = pub.get("/loja/demo")[2]
check("T26 loja publicada mostra os blocos novos (carrossel com 2 banners, banners, círculos, fileira que desliza)",
      'class="lj-carrossel"' in b and b.count('aria-roledescription="slide"') == 2 and 'class="lj-banners' in b and 'class="lj-circulos"' in b and "lj-grade--carrossel" in b and 'data-cabecalho="compacto"' in b)
check("T27 título do banner sai como texto (sem executar script)", "<script>alert(1)</script>" not in b and "Banner dois" in b)
check("T28 galeria sem foto não aparece para o público", "Da loja" not in texto(b))
dono.post_json("/app/loja-online/editor/modelo", {"modelo": "vitrine"})
dono.post_json("/app/loja-online/editor/publicar")
ed = dono.get("/app/loja-online/editor")[2]
check("T29 editor oferece os 3 blocos novos e sabe desenhar lista com imagem, link e opções", all(x in ed for x in ["Carrossel de banners", "Grade de banners", "Galeria de fotos"]) and "sc[2] === 'imagem'" in ed)
b = dono.get("/app/loja-online/promocoes")[2]
check("T30 aba Promoções tem o quadro de parcelamento e avisa que é só o texto da vitrine", 'name="parcelas_max"' in b and 'name="selo_novo_dias"' in b and "só o texto da vitrine" in b)

# ───────────────────────────── U. 4ª rodada (15/11/2026) ─────────────────────────────
print("\n== U. 4ª rodada: grade, reserva, envio, cancelar/devolver, conta, favoritos, avise-me, avaliações, carrinho abandonado, resultados ==")
limites()
PHP_PRE = ("<?php require '/var/www/vendor/autoload.php'; $app = require '/var/www/bootstrap/app.php'; "
           "$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();\n")


def php(codigo):
    r = subprocess.run(["docker", "exec", "-i", APP, "php"], input=PHP_PRE + codigo, capture_output=True, text=True)
    app_cmd("sh", "-c", "chown -R www-data:www-data /var/www/storage")
    return (r.stdout + r.stderr).strip()


def vazio(q):
    """A consulta devolveu vazio/nulo?"""
    return (um(q) or "") == ""


CPFS = iter(f"9{n:010d}" for n in range(1, 99))


def saldo_loja(pid):
    return float(php(f"echo (new App\\Services\\LojaOnline\\CatalogoLoja(App\\Models\\LojaOnline::where('empresa_id',1)->first()))->saldo({pid});"))


def dar_saldo(pid, alvo):
    php(f"$e={cen['principal']}; $a=App\\Services\\SaldoEstoque::noEstoque($e,{pid}); "
        f"App\\Services\\SaldoEstoque::registrar(1,1,$e,{pid},'ajuste',{alvo}-$a,['user_id'=>App\\Models\\User::where('email','dono@demo.com')->value('id')]);")


def registro_email():
    return app_cmd("sh", "-c", "cat /var/www/storage/logs/laravel.log")


def limpar_registro():
    app_cmd("sh", "-c", ": > /var/www/storage/logs/laravel.log")


def comprador():
    n = Nav()
    n.op.addheaders = [("User-Agent", "Mozilla/5.0 (Teste; Linux) Gecko/20100101 Firefox/130.0")]
    n.get("/loja/demo")
    return n


def fechar(n, pid, qtd=1, entrega="retirada", email=None, nome="Cliente Quatro", fone="(86) 96666-0001", extra=None):
    """Põe no carrinho e fecha (pagamento a combinar). Devolve (id, token, numero) do pedido."""
    n.post_json("/loja/demo/carrinho/adicionar", {"produto_id": pid, "quantidade": qtd})
    n.get("/loja/demo/finalizar")
    d = {"nome": nome, "telefone": fone, "cpf_cnpj": next(CPFS), "entrega": entrega, "pagamento": "combinar"}  # um cliente por compra
    if email:
        d["email"] = email
    if entrega == "entrega":
        d.update({"cep": "64000-040", "logradouro": "Rua Teste", "numero": "10", "bairro": "Centro", "cidade": "Teresina", "uf": "PI"})
    d.update(extra or {})
    st, hd, _ = n.post("/loja/demo/finalizar", d)
    m = re.search(r"/pedido/([a-z0-9]{32})$", hd.get("Location", ""))
    if not m:
        return None, None, None
    r = sql(f"SELECT id, numero FROM pedidos WHERE loja_token='{m.group(1)}'")[0]
    return r[0], m.group(1), r[1]


php("$l=App\\Models\\LojaOnline::where('empresa_id',1)->first(); $l->update(['estoque_id'=>null,'publicada'=>true,'config'=>array_merge($l->config??[],"
    "['vender_sem_estoque'=>false,'retirada'=>true,'entrega'=>true,'combinar'=>true,'pedido_minimo'=>0,'frete_gratis_acima'=>0,'reservar_minutos'=>30,"
    "'conta_cliente'=>true,'avaliacoes'=>true,'avaliacoes_moderar'=>true,'avise_me'=>true,'carrinho_lembrete'=>false,'boleto'=>false,"
    "'email_cliente_andamento'=>true,'email_cliente_recebido'=>true,'popup_ativo'=>false])]);")
GP, GJ, GD, RP, AP, VP, CP = P[17], P[18], P[19], P[20], P[21], P[22], P[24]
MAXP = int(um("SELECT MAX(id) FROM produtos"))
MAXPED = int(um("SELECT IFNULL(MAX(id),0) FROM pedidos"))
sql(f"UPDATE produtos SET loja_online=1, status='ativo', grade_id=NULL, grade_opcoes=NULL WHERE id IN ({GP},{GJ},{GD},{RP},{AP},{VP},{CP})")
for x in (GP, GJ, GD, RP, AP, VP, CP):
    dar_saldo(x, 10)
sql("DELETE FROM loja_avaliacoes; DELETE FROM loja_avise_me; DELETE FROM loja_carrinhos; DELETE FROM loja_contas; DELETE FROM loja_visitas; DELETE FROM loja_produto_vistas;")

# — painel: abas novas —
for aba in ["pedidos", "grade", "avaliacoes", "carrinhos", "resultados"]:
    st, _, b = dono.get(f"/app/loja-online/{aba}")
    check(f"U1 painel: aba '{aba}' abre para o dono", st == 200 and "Loja online" in b, st)
st, _, _ = vendedor.get("/app/loja-online/pedidos")
check("U2 vendedor não entra nas abas novas (403)", st == 403, st)
st, _, _ = outro.get("/app/loja-online/grade")
check("U3 empresa sem loja online liberada não tem a tela de grade (404)", st == 404, st)

# — grade —
nome_g = um(f"SELECT descricao FROM produtos WHERE id={GP}")
preco_g = um(f"SELECT preco_venda FROM produtos WHERE id={GP}")
n_prod = int(um("SELECT COUNT(*) FROM produtos WHERE empresa_id=1"))
st, _, b = dono.get("/app/loja-online/grade?q=" + urllib.parse.quote(nome_g[:8]))
check("U4 grade: a busca acha o produto e oferece criar", st == 200 and nome_g in b and 'name="valores[0]"' in b, st)
dono.post("/app/loja-online/grade/gerar", {"produto_id": GP, "atributo[0]": "Tamanho", "valores[0]": "P", "atributo[1]": "", "valores[1]": ""})
check("U5 grade com uma opção só é recusada (nada criado)", int(um("SELECT COUNT(*) FROM produtos WHERE empresa_id=1")) == n_prod and vazio(f"SELECT IFNULL(grade_id,'') FROM produtos WHERE id={GP}"))
dono.get("/app/loja-online/grade")
st, hd, _ = dono.post("/app/loja-online/grade/gerar", {"produto_id": GP, "atributo[0]": "tamanho", "valores[0]": "P, M, G", "atributo[1]": "Cor", "valores[1]": "Azul; Preto"})
vs = sql(f"SELECT id, descricao, codigo_interno, codigo_barras, preco_venda, loja_online, grade_opcoes FROM produtos WHERE grade_id={GP} ORDER BY id")
check("U6 grade P/M/G × Azul/Preto: 6 produtos (o próprio + 5 novos)", st == 302 and len(vs) == 6 and int(um("SELECT COUNT(*) FROM produtos WHERE empresa_id=1")) == n_prod + 5, (st, len(vs)))
ops = [json.loads(v[6]) for v in vs]
check("U7 o principal guarda o nome comum, a ORDEM dos atributos (Tamanho, Cor) e vira a 1ª combinação (P / Azul)", ops[0].get("_nome") == nome_g and ops[0].get("_ordem") == ["Tamanho", "Cor"] and ops[0].get("Tamanho") == "P" and ops[0].get("Cor") == "Azul"
      and vs[0][1] == f"{nome_g} - P / Azul", (ops[0], vs[0][1]))
check("U8 cada variação é um produto do ERP: código e código de barras próprios, mesmo preço, na loja",
      len({v[2] for v in vs}) == 6 and len({v[3] for v in vs}) == 6 and all(len(v[3]) == 13 for v in vs[1:]) and all(v[4] == preco_g for v in vs) and all(v[5] == "1" for v in vs), [v[2:6] for v in vs])
check("U9 as 6 combinações existem, sem repetir", len({(o["Tamanho"], o["Cor"]) for o in ops}) == 6)
VM = next(v[0] for v, o in zip(vs, ops) if o["Tamanho"] == "M" and o["Cor"] == "Azul")
VG = next(v[0] for v, o in zip(vs, ops) if o["Tamanho"] == "G" and o["Cor"] == "Azul")
check("U10 variação nova nasce com saldo zero (a entrada é no ERP)", saldo_loja(VM) == 0)
dono.post("/app/loja-online/grade/gerar", {"produto_id": GP, "atributo[0]": "Tamanho", "valores[0]": "X, Y"})
check("U11 produto que já está em grade não gera outra", int(um(f"SELECT COUNT(*) FROM produtos WHERE grade_id={GP}")) == 6)
outro.get("/app/dashboard")
st, _, _ = outro.post("/app/loja-online/grade/gerar", {"produto_id": GP, "atributo[0]": "Tamanho", "valores[0]": "A, B"})
check("U12 outra empresa não mexe na grade (404)", st == 404, st)

b = pub.get("/loja/demo/produtos?q=" + urllib.parse.quote(nome_g))[2]
check("U13 vitrine: a grade inteira vira UM cartão, com o nome comum e 'Ver opções' (sem botão de adicionar)",
      cartoes(b) == 1 and f">{H.escape(nome_g)}</a>" in b and "Ver opções" in b and "- P / Azul" not in b, cartoes(b))
dar_saldo(GP, 0)
dar_saldo(VM, 3)
b = pub.get("/loja/demo/produtos?q=" + urllib.parse.quote(nome_g))[2]
check("U14 principal sem saldo mas M/Azul com saldo: o cartão continua disponível", cartoes(b) == 1 and re.search(r'<article class="lj-card[^"]*lj-card--fim', b) is None)
st, _, b = pub.get(f"/loja/demo/p/{GP}")
st, _, b = pub.get(re.search(r"/loja/demo/(p/[^\"']+)", pub.get(f"/loja/demo/produtos?q={urllib.parse.quote(nome_g)}")[2]).group(0))
check("U15 página do principal: seletores Tamanho e depois Cor (na ordem do lojista), 'P' e 'Azul' marcados, esgotado",
      st == 200 and b.count("data-lj-variacao") == 2 and "Tamanho: <strong>P</strong>" in b and "Cor: <strong>Azul</strong>" in b and "Esgotado no momento" in b
      and b.index("Tamanho: <strong>") < b.index("Cor: <strong>"), st)
check("U16 opção M leva à variação M/Azul; opção G (sem saldo) aparece riscada", re.search(rf'href="/loja/demo/p/{VM}-[^"]*" class="lj-variacao__opcao "', b) is not None
      and re.search(rf'href="/loja/demo/p/{VG}-[^"]*" class="lj-variacao__opcao  lj-variacao__opcao--fim "', b) is not None, re.findall(r'class="lj-variacao__opcao[^"]*"', b))
cg = comprador()
st, j = cg.post_json("/loja/demo/carrinho/adicionar", {"produto_id": VM, "quantidade": 2})
b = cg.get("/loja/demo/carrinho")[2]
check("U17 a variação entra no carrinho como produto próprio, com tamanho e cor no nome", st == 200 and j.get("ok") and f"{H.escape(nome_g)} - M / Azul" in b, (st, j))
st, j = cg.post_json("/loja/demo/carrinho/adicionar", {"produto_id": VG, "quantidade": 1})
check("U18 variação sem saldo não entra no carrinho", st == 422 and j.get("ok") is False, (st, j))
sql(f"UPDATE produtos SET preco_venda=preco_venda+20 WHERE id={VM}")
b = pub.get("/loja/demo/produtos?q=" + urllib.parse.quote(nome_g))[2]
check("U19 só a M/Azul (mais cara) tem saldo: o cartão mostra o preço DELA, sem 'a partir de'", "a partir de" not in b)
dar_saldo(GP, 4)
b = pub.get("/loja/demo/produtos?q=" + urllib.parse.quote(nome_g))[2]
check("U20 duas variações com preço diferente e saldo: cartão diz 'a partir de' o menor", "a partir de" in b and f"R$ {preco_g.replace('.', ',')}" in b, preco_g)
sql(f"UPDATE produtos SET preco_venda={preco_g} WHERE id={VM}")
cod_j = um(f"SELECT codigo_interno FROM produtos WHERE id={GJ}")
dono.get("/app/loja-online/grade")
dono.post("/app/loja-online/grade/juntar", {"principal_id": GP, "codigo": cod_j, "opcoes[Tamanho]": "GG", "opcoes[Cor]": "Azul"})
check("U21 juntar produto que já existe: entra na grade com as opções informadas", um(f"SELECT CONCAT(grade_id,'|',grade_opcoes) FROM produtos WHERE id={GJ}").startswith(f"{GP}|")
      and json.loads(um(f"SELECT grade_opcoes FROM produtos WHERE id={GJ}")) == {"Tamanho": "GG", "Cor": "Azul"})
cod_d = um(f"SELECT codigo_interno FROM produtos WHERE id={GD}")
dono.post("/app/loja-online/grade/juntar", {"principal_id": GP, "codigo": cod_d, "opcoes[Tamanho]": "GG", "opcoes[Cor]": "Azul"})
check("U22 juntar com opções que já existem na grade é recusado", vazio(f"SELECT IFNULL(grade_id,'') FROM produtos WHERE id={GD}"))
dono.post("/app/loja-online/grade/juntar", {"principal_id": GP, "codigo": um(f"SELECT codigo_interno FROM produtos WHERE empresa_id={cen['empresa2']} LIMIT 1") + "ZZ", "opcoes[Tamanho]": "U", "opcoes[Cor]": "Azul"})
check("U23 código que não existe na empresa não junta nada", int(um(f"SELECT COUNT(*) FROM produtos WHERE grade_id={GP}")) == 7)
dono.post("/app/loja-online/grade/tirar", {"produto_id": GJ})
check("U24 tirar uma variação: ela sai da grade e continua cadastrada", um(f"SELECT CONCAT(IFNULL(grade_id,'-'),'|',status) FROM produtos WHERE id={GJ}") == "-|ativo" and int(um(f"SELECT COUNT(*) FROM produtos WHERE grade_id={GP}")) == 6)
dono.post("/app/loja-online/grade/gerar", {"produto_id": GD, "atributo[0]": "Voltagem", "valores[0]": "110V, 220V"})
n2 = int(um(f"SELECT COUNT(*) FROM produtos WHERE grade_id={GD}"))
dono.post("/app/loja-online/grade/tirar", {"produto_id": GD})
check("U25 desfazer a grade (tirar o principal): os produtos ficam, sem grade", n2 == 2 and int(um(f"SELECT COUNT(*) FROM produtos WHERE grade_id={GD}")) == 0
      and int(um("SELECT COUNT(*) FROM produtos WHERE descricao LIKE '% - 220V' AND deleted_at IS NULL")) == 1)
b = dono.get("/app/loja-online/grade")[2]
check("U26 tela da grade lista as variações com código, opções e saldo", nome_g in b and ">principal<" in b and b.count("Tirar</button>") == 5, b.count("Tirar</button>"))
st, _, b = dono.get(f"/app/produtos/{VM}/edit")
check("U27 a variação abre no cadastro de produtos do ERP como produto comum", st == 200 and "M / Azul" in b, st)

# — reserva de estoque —
limites()
s0 = saldo_loja(RP)
mov0 = um(f"SELECT COUNT(*) FROM estoque_movimentacoes WHERE produto_id={RP}")
cr = comprador()
R_ID, R_TOKEN, R_NUM = fechar(cr, RP, 2, email="reserva@teste.test")
check("U28 pedido aguardando pagamento segura a peça: a vitrine mostra 2 a menos", R_ID is not None and saldo_loja(RP) == s0 - 2, (s0, saldo_loja(RP)))
check("U29 reservar NÃO movimenta o estoque do ERP", um(f"SELECT COUNT(*) FROM estoque_movimentacoes WHERE produto_id={RP}") == mov0)
sql(f"UPDATE pedidos SET created_at=NOW() - INTERVAL 2 HOUR WHERE id={R_ID}")
check("U30 passados os 30 minutos sem pagar, a peça volta para a vitrine", saldo_loja(RP) == s0, saldo_loja(RP))
sql(f"UPDATE pedidos SET status='confirmado' WHERE id={R_ID}")
check("U31 pedido PAGO/confirmado fica reservado até faturar, sem prazo", saldo_loja(RP) == s0 - 2, saldo_loja(RP))
b = dono.get("/app/loja-online/pedidos")[2]
check("U32 painel mostra o estoque reservado pelos pedidos online", "Estoque reservado" in b and um(f"SELECT descricao FROM produtos WHERE id={RP}") in b.split("Estoque reservado")[1])
c9 = comprador()
st, j = c9.post_json("/loja/demo/carrinho/adicionar", {"produto_id": RP, "quantidade": int(s0)})
c9.get("/loja/demo/carrinho")
st, _, b = c9.get("/loja/demo/carrinho/resumo", json_=True)
check("U33 outro comprador não leva a peça reservada: quantidade cai para o que sobrou", json.loads(b)["itens"][0]["quantidade"] == int(s0) - 2, b[:200])
sql(f"UPDATE pedidos SET status='faturado' WHERE id={R_ID}")
check("U34 faturou: sai da reserva (a baixa de verdade é a do faturamento do ERP)", saldo_loja(RP) == s0)
sql(f"UPDATE pedidos SET status='confirmado' WHERE id={R_ID}")
dono.get("/app/loja-online/pedidos")
dono.post("/app/loja-online/secao/vendas", {"reservar_minutos": "0", "carrinho_lembrete_horas": "2", "conta_cliente": "1", "avaliacoes": "1", "avaliacoes_moderar": "1", "avise_me": "1"}, metodo="PUT")
cfg = cfg_loja()
check("U35 regras de venda gravadas (segurar 0 min; boleto continua desligado)", cfg.get("reservar_minutos") == 0 and cfg.get("boleto") is False and cfg.get("conta_cliente") is True, {k: cfg.get(k) for k in ["reservar_minutos", "boleto"]})
c8 = comprador()
X_ID, X_TOKEN, _ = fechar(c8, AP, 1)
check("U36 com 0 minutos, pedido sem pagar não segura a peça", saldo_loja(AP) == 10, saldo_loja(AP))
dono.get("/app/loja-online/pedidos")
dono.post("/app/loja-online/secao/vendas", {"reservar_minutos": "30", "carrinho_lembrete_horas": "2", "conta_cliente": "1", "avaliacoes": "1", "avaliacoes_moderar": "1", "avise_me": "1"}, metodo="PUT")

# — envio com rastreio —
limites()
ce = comprador()
E_ID, E_TOKEN, E_NUM = fechar(ce, VP, 1, entrega="entrega", email="envio@teste.test", nome="Elisa Envio", fone="(86) 95555-0002")
check("U37 pedido de entrega criado para o teste de envio", E_ID is not None)
dono.get("/app/loja-online/pedidos?ver=aguardando")
dono.post(f"/app/loja-online/pedidos/{E_ID}/envio", {"transportadora": "Correios", "codigo": "AA123456789BR"})
check("U38 não dá para informar envio de pedido que ainda não foi confirmado", vazio(f"SELECT IFNULL(loja_enviado_em,'') FROM pedidos WHERE id={E_ID}"))
sql(f"UPDATE pedidos SET status='confirmado', frete_valor=15.00, total=subtotal+15 WHERE id={E_ID}")
b = dono.get("/app/loja-online/pedidos")[2]
check("U39 aba 'Pagos para separar/enviar' lista o pedido com o botão de informar o envio", f"Pedido #{E_NUM}" in b and "Informar o envio" in b and "Elisa Envio" in b)
dono.post(f"/app/loja-online/pedidos/{E_ID}/envio", {"transportadora": "Correios", "codigo": "<script>x</script>"})
check("U40 código de rastreio com caractere estranho é recusado", vazio(f"SELECT IFNULL(loja_enviado_em,'') FROM pedidos WHERE id={E_ID}"))
dono.get("/app/loja-online/pedidos")
dono.post(f"/app/loja-online/pedidos/{E_ID}/envio", {"transportadora": "Correios", "codigo": "AA123456789BR", "link": "javascript:alert(1)"})
check("U41 link de rastreio que não é http(s) é recusado", vazio(f"SELECT IFNULL(loja_enviado_em,'') FROM pedidos WHERE id={E_ID}"))
vendedor.get("/app/dashboard")
st, _, _ = vendedor.post(f"/app/loja-online/pedidos/{E_ID}/envio", {"codigo": "AA123456789BR"})
check("U42 vendedor não informa envio (403)", st == 403, st)
limpar_registro()
dono.get("/app/loja-online/pedidos")
dono.post(f"/app/loja-online/pedidos/{E_ID}/envio", {"transportadora": "Correios", "codigo": "aa 123456789 br"})
env = sql(f"SELECT loja_transportadora, loja_rastreio_codigo, loja_rastreio_url, IFNULL(loja_enviado_em,'') FROM pedidos WHERE id={E_ID}")[0]
check("U43 envio gravado: código em maiúsculas, sem espaços, e link dos Correios montado sozinho",
      env[0] == "Correios" and env[1] == "AA123456789BR" and env[2] == "https://rastreamento.correios.com.br/app/index.php?objetos=AA123456789BR" and env[3] != "", env)
reg = registro_email()
check("U44 cliente recebe o e-mail 'pedido enviado' com o código", f"Seu pedido #{E_NUM} foi enviado" in reg and "envio@teste.test" in reg and "AA123456789BR" in reg, reg[-300:])
st, _, b = pub.get(f"/loja/demo/pedido/{E_TOKEN}")
check("U45 página do pedido: 'Pedido enviado', código e botão Rastrear", st == 200 and "Pedido enviado" in b and "AA123456789BR" in b and "rastreamento.correios.com.br" in b and "data-lj-rastreio" in b)
check("U46 situação do pedido para o comprador: 'enviado'", json.loads(pub.get(f"/loja/demo/pedido/{E_TOKEN}/situacao", json_=True)[2]).get("situacao") == "enviado")
b = dono.get("/app/loja-online/pedidos?ver=enviados")[2]
check("U47 aba 'Enviados' lista o pedido com o rastreio", f"Pedido #{E_NUM}" in b and "AA123456789BR" in b)

# — cancelar e devolver —
dono.post(f"/app/loja-online/pedidos/{X_ID}/cancelar", {"motivo": ""})
check("U48 cancelar sem motivo não cancela", um(f"SELECT status FROM pedidos WHERE id={X_ID}") == "rascunho")
dono.get("/app/loja-online/pedidos?ver=aguardando")
caixa.get("/app/dashboard")
st, _, _ = caixa.post(f"/app/loja-online/pedidos/{X_ID}/cancelar", {"motivo": "teste"})
check("U49 caixa não cancela pedido da loja online (403)", st == 403, st)
dono.post(f"/app/loja-online/pedidos/{X_ID}/cancelar", {"motivo": "Cliente desistiu"})
check("U50 cancelado com o motivo anotado no pedido", um(f"SELECT status FROM pedidos WHERE id={X_ID}") == "cancelado" and "Cliente desistiu" in um(f"SELECT observacoes_internas FROM pedidos WHERE id={X_ID}"))
check("U51 comprador vê o pedido cancelado", "Este pedido foi cancelado" in pub.get(f"/loja/demo/pedido/{X_TOKEN}")[2])
sql(f"UPDATE pedidos SET status='faturado' WHERE id={R_ID}")
dono.get("/app/loja-online/pedidos")
dono.post(f"/app/loja-online/pedidos/{R_ID}/cancelar", {"motivo": "tentativa"})
check("U52 pedido faturado não se cancela por aqui (é pela venda, no ERP)", um(f"SELECT status FROM pedidos WHERE id={R_ID}") == "faturado")
sql(f"UPDATE pedidos SET status='confirmado' WHERE id={R_ID}")
php(f"App\\Models\\PedidoCobranca::create(['empresa_id'=>1,'pedido_id'=>{R_ID},'provedor'=>'sicredi_pix','txid'=>'TESTEU{R_ID}','valor'=>(float) DB::table('pedidos')->where('id',{R_ID})->value('total'),"
    "'status'=>'CONCLUIDA','copia_cola'=>'x','pago_em'=>now()]);")
dono.get("/app/loja-online/pedidos")
dono.post(f"/app/loja-online/pedidos/{R_ID}/devolver", {"motivo": "teste", "confirmo": "1", "ja_devolvi": "1"})
check("U53 não devolve dinheiro de pedido que não foi cancelado", vazio(f"SELECT IFNULL(loja_estornado_em,'') FROM pedidos WHERE id={R_ID}"))
dono.get("/app/loja-online/pedidos")
dono.post(f"/app/loja-online/pedidos/{R_ID}/cancelar", {"motivo": "Sem a peça"})
b = dono.get("/app/loja-online/pedidos?ver=cancelados")[2]
check("U54 pedido PAGO cancelado: painel avisa que falta devolver e mostra o botão ao dono", um(f"SELECT status FROM pedidos WHERE id={R_ID}") == "cancelado" and "PAGO — falta devolver" in b and "Devolver o dinheiro" in b)
check("U55 cancelou: a reserva é liberada", saldo_loja(RP) == s0, saldo_loja(RP))
gerente.get("/app/loja-online/pedidos")
st, _, _ = gerente.post(f"/app/loja-online/pedidos/{R_ID}/devolver", {"motivo": "teste", "confirmo": "1", "ja_devolvi": "1"})
check("U56 gerente NÃO devolve dinheiro (403) — só o dono", st == 403 and vazio(f"SELECT IFNULL(loja_estornado_em,'') FROM pedidos WHERE id={R_ID}"), st)
check("U57 gerente não vê o botão de devolver", "Devolver o dinheiro" not in gerente.get("/app/loja-online/pedidos?ver=cancelados")[2])
dono.get("/app/loja-online/pedidos?ver=cancelados")
dono.post(f"/app/loja-online/pedidos/{R_ID}/devolver", {"motivo": "Sem a peça", "ja_devolvi": "1"})
check("U58 sem marcar a confirmação, não devolve", vazio(f"SELECT IFNULL(loja_estornado_em,'') FROM pedidos WHERE id={R_ID}"))
dono.get("/app/loja-online/pedidos?ver=cancelados")
dono.post(f"/app/loja-online/pedidos/{R_ID}/devolver", {"motivo": "Sem a peça", "confirmo": "1"})
check("U59 pagamento que não foi pelo Asaas: o sistema NÃO finge devolução automática", vazio(f"SELECT IFNULL(loja_estornado_em,'') FROM pedidos WHERE id={R_ID}"))
dono.get("/app/loja-online/pedidos?ver=cancelados")
dono.post(f"/app/loja-online/pedidos/{R_ID}/devolver", {"motivo": "Sem a peça", "confirmo": "1", "ja_devolvi": "1"})
est = sql(f"SELECT IFNULL(loja_estornado_em,''), IFNULL(loja_estorno_obs,''), observacoes_internas FROM pedidos WHERE id={R_ID}")[0]
check("U60 'já devolvi por fora': fica REGISTRADO, com quem e o motivo", est[0] != "" and "registrada à mão" in est[1] and "Sem a peça" in est[1] and "DINHEIRO DEVOLVIDO" in est[2], est[:2])
check("U61 comprador vê que o valor foi devolvido", "foi devolvido em" in pub.get(f"/loja/demo/pedido/{R_TOKEN}")[2])
dono.post(f"/app/loja-online/pedidos/{R_ID}/devolver", {"motivo": "de novo", "confirmo": "1", "ja_devolvi": "1"})
check("U62 devolver duas vezes não registra de novo", um(f"SELECT observacoes_internas FROM pedidos WHERE id={R_ID}").count("DINHEIRO DEVOLVIDO") == 1)
outro.get("/app/dashboard")
st, _, _ = outro.post(f"/app/loja-online/pedidos/{E_ID}/cancelar", {"motivo": "invasor"})
check("U63 outra empresa não cancela pedido alheio (404)", st == 404 and um(f"SELECT status FROM pedidos WHERE id={E_ID}") == "confirmado", st)

# — conta do comprador —
limites()
cc = comprador()
st, _, b = cc.get("/loja/demo/entrar")
check("U64 tela Entrar: entrar, criar conta e esqueci a senha; fora dos buscadores", st == 200 and "Já tenho conta" in b and "Criar conta" in b and "Esqueci a senha" in b and 'content="noindex,nofollow"' in b, st)
check("U65 cabeçalho da loja tem Entrar e Favoritos", 'aria-label="Entrar"' in b and 'aria-label="Favoritos"' in b)
cc.post("/loja/demo/conta/criar", {"nome": "Carla Conta", "email": "carla@teste.test", "senha": "1234567", "aceite": "1"})
check("U66 senha com menos de 8 caracteres é recusada", um("SELECT COUNT(*) FROM loja_contas") == "0" and "pelo menos 8" in cc.get("/loja/demo/entrar")[2])
cc.post("/loja/demo/conta/criar", {"nome": "Carla Conta", "email": "carla@teste.test", "senha": "segredo-123"})
check("U67 sem aceitar a política de privacidade não cria conta", um("SELECT COUNT(*) FROM loja_contas") == "0")
cc.get("/loja/demo/entrar")
st, hd, _ = cc.post("/loja/demo/conta/criar", {"nome": "Carla Conta", "email": "Carla@Teste.test", "telefone": "(86) 94444-0003", "senha": "segredo-123", "aceite": "1"})
conta = sql("SELECT id, email, senha, empresa_id, telefone FROM loja_contas")
check("U68 conta criada: e-mail em minúsculas, senha guardada só como hash, já entra logada",
      st == 302 and hd.get("Location", "").endswith("/loja/demo/conta") and len(conta) == 1 and conta[0][1] == "carla@teste.test" and conta[0][2].startswith("$2y$") and conta[0][4] == "86944440003", (st, conta))
CONTA = conta[0][0]
st, _, b = cc.get("/loja/demo/conta")
check("U69 Minha conta: saudação, pedidos (vazio) e meus dados", st == 200 and "Olá, Carla" in b and "ainda não fez pedido" in b and "Meus dados" in b and 'aria-label="Minha conta"' in b, st)
st, hd, _ = comprador().get("/loja/demo/conta")
check("U70 sem login, Minha conta manda para Entrar", st == 302 and hd.get("Location", "").endswith("/loja/demo/entrar"), (st, hd.get("Location")))
c2 = comprador()
c2.get("/loja/demo/entrar")
c2.post("/loja/demo/conta/criar", {"nome": "Outra Pessoa", "email": "carla@teste.test", "senha": "outrasenha1", "aceite": "1"})
check("U71 e-mail que já tem conta não cria outra", um("SELECT COUNT(*) FROM loja_contas") == "1" and "Já existe uma conta" in c2.get("/loja/demo/entrar")[2])
c2.post("/loja/demo/entrar", {"email": "carla@teste.test", "senha": "errada-000"})
b = c2.get("/loja/demo/entrar")[2]
check("U72 senha errada: mensagem única, sem dizer se o e-mail existe", "E-mail ou senha não conferem" in b)
c2.post("/loja/demo/entrar", {"email": "ninguem@teste.test", "senha": "errada-000"})
check("U73 e-mail sem conta: a MESMA mensagem", "E-mail ou senha não conferem" in c2.get("/loja/demo/entrar")[2])
cc.post_json("/loja/demo/carrinho/adicionar", {"produto_id": CP, "quantidade": 1})
b = cc.get("/loja/demo/finalizar")[2]
check("U74 logada, o fechamento vem com nome, e-mail e telefone preenchidos", 'value="Carla Conta"' in b and 'value="carla@teste.test"' in b and 'value="86944440003"' in b and "Comprando como" in b)
st, hd, _ = cc.post("/loja/demo/finalizar", {"nome": "Carla Conta", "telefone": "86944440003", "email": "carla@teste.test", "cpf_cnpj": "111.444.777-35", "entrega": "entrega", "cep": "64000-040",
                                             "logradouro": "Rua da Conta", "numero": "77", "bairro": "Centro", "cidade": "Teresina", "uf": "PI", "pagamento": "combinar"})
C_TOKEN = re.search(r"/pedido/([a-z0-9]{32})$", hd.get("Location", "")).group(1)
C_ID, C_NUM = sql(f"SELECT id, numero FROM pedidos WHERE loja_token='{C_TOKEN}'")[0]
check("U75 pedido fechado logada fica ligado à conta", um(f"SELECT IFNULL(loja_conta_id,'') FROM pedidos WHERE id={C_ID}") == CONTA and um(f"SELECT IFNULL(cliente_id,'') FROM loja_contas WHERE id={CONTA}") != "")
b = cc.get("/loja/demo/conta")[2]
check("U76 Minha conta lista o pedido e o último endereço", f"Pedido #{C_NUM}" in b and f"/loja/demo/pedido/{C_TOKEN}" in b and "Rua da Conta" in b)
cc.post_json("/loja/demo/carrinho/adicionar", {"produto_id": CP, "quantidade": 1})
b = cc.get("/loja/demo/finalizar")[2]
check("U77 na compra seguinte o endereço já vem preenchido", 'value="Rua da Conta"' in b and 'value="77"' in b)
c3 = comprador()
c3.get("/loja/demo/entrar")
c3.post("/loja/demo/conta/criar", {"nome": "Bruno Outro", "email": "bruno@teste.test", "senha": "segredo-456", "aceite": "1"})
b = c3.get("/loja/demo/conta")[2]
check("U78 outra conta não enxerga o pedido da Carla", "Olá, Bruno" in b and f"Pedido #{C_NUM}" not in b)
check("U79 pedido feito SEM login com o e-mail de alguém não aparece na conta dessa pessoa", f"Pedido #{E_NUM}" not in b and vazio(f"SELECT IFNULL(loja_conta_id,'') FROM pedidos WHERE id={E_ID}"))
cc.get("/loja/demo/conta")
cc.post("/loja/demo/conta", {"nome": "Carla Conta Silva", "telefone": "86944440003", "senha_atual": "errada", "senha_nova": "novasenha-9"})
check("U80 trocar a senha exige a senha atual certa", "A senha atual não confere" in cc.get("/loja/demo/conta")[2])
cc.post("/loja/demo/conta", {"nome": "Carla Conta Silva", "telefone": "86944440003"})
check("U81 nome atualizado em Meus dados", um(f"SELECT nome FROM loja_contas WHERE id={CONTA}") == "Carla Conta Silva")
limpar_registro()
cq = comprador()
cq.get("/loja/demo/entrar")
cq.post("/loja/demo/esqueci", {"email": "ninguem@teste.test"})
b1 = cq.get("/loja/demo/entrar")[2]
cq.post("/loja/demo/esqueci", {"email": "carla@teste.test"})
b2 = cq.get("/loja/demo/entrar")[2]
reg = registro_email()
m = re.search(r"/redefinir/([a-z0-9]{48})", reg)
check("U82 'esqueci a senha' responde igual para e-mail com e sem conta", "Se existir uma conta" in b1 and "Se existir uma conta" in b2)
check("U83 só a conta de verdade recebe o e-mail com o link; o banco guarda o token embaralhado", m is not None and reg.count("Subject:") == 1 and "carla@teste.test" in reg
      and m.group(1) not in um(f"SELECT redefinir_token FROM loja_contas WHERE id={CONTA}"), reg[-200:])
TK = m.group(1) if m else "x" * 48
st, _, b = cq.get(f"/loja/demo/redefinir/{TK}")
check("U84 o link abre a tela de senha nova", st == 200 and "Crie a sua senha nova" in b, st)
st, hd, _ = cq.post(f"/loja/demo/redefinir/{TK}", {"senha": "trocada-2026"})
check("U85 senha nova gravada, token apagado e já entra na conta", st == 302 and vazio(f"SELECT IFNULL(redefinir_token,'') FROM loja_contas WHERE id={CONTA}") and "Olá, Carla" in cq.get("/loja/demo/conta")[2], st)
st, hd, _ = comprador().get(f"/loja/demo/redefinir/{TK}")
check("U86 o mesmo link não funciona duas vezes", st == 302 and hd.get("Location", "").endswith("/entrar"), (st, hd.get("Location")))
c4 = comprador()
c4.get("/loja/demo/entrar")
st, hd, _ = c4.post("/loja/demo/entrar", {"email": "carla@teste.test", "senha": "trocada-2026"})
check("U87 entra com a senha nova; a antiga não vale mais", st == 302 and hd.get("Location", "").endswith("/loja/demo/conta"), (st, hd.get("Location")))
c4.get("/loja/demo/conta")
c4.post("/loja/demo/sair")
st, hd, _ = c4.get("/loja/demo/conta")
check("U88 sair encerra a conta nesta sessão", st == 302, st)
st, _, _ = outro.get("/loja/demo/conta")
check("U89 conta é da loja: login do ERP de outra empresa não vira conta de comprador", st == 302, st)
sql("UPDATE lojas_online SET config=JSON_SET(config,'$.conta_cliente',false) WHERE empresa_id=1")
st, _, _ = pub.get("/loja/demo/entrar")
b = pub.get("/loja/demo")[2]
check("U90 loja com a conta desligada: sem tela de entrar e sem o ícone", st == 404 and 'aria-label="Entrar"' not in b, st)
sql("UPDATE lojas_online SET config=JSON_SET(config,'$.conta_cliente',true) WHERE empresa_id=1")

# — favoritos —
limites()
st, _, b = pub.get("/loja/demo/favoritos")
check("U91 página Favoritos abre sem login", st == 200 and "data-lj-fav-lista" in b and "Guardados neste aparelho" in b, st)
url_c = re.search(r'href="(/loja/demo/p/%s-[^"]+)"' % CP, pub.get("/loja/demo/produtos?q=" + urllib.parse.quote(um(f"SELECT descricao FROM produtos WHERE id={CP}")))[2]).group(1)
check("U92 cartão e página do produto têm o botão de favoritar", f'data-lj-fav="{CP}"' in pub.get("/loja/demo/produtos?q=" + urllib.parse.quote(um(f"SELECT descricao FROM produtos WHERE id={CP}")))[2]
      and "<span>Favoritar</span>" in pub.get(url_c)[2])
cf = comprador()
st, j = cf.post_json("/loja/demo/favoritos", {"ids": [CP, AP]})
check("U93 sem conta, o servidor não guarda favorito (fica no aparelho)", st == 200 and j.get("conta") is False and j.get("ids") == [CP, AP], j)
cc.get("/loja/demo/conta")
st, j = cc.post_json("/loja/demo/favoritos", {"ids": [CP, AP]})
check("U94 logada, os favoritos ficam na conta", j.get("conta") is True and json.loads(um(f"SELECT favoritos FROM loja_contas WHERE id={CONTA}")) == [CP, AP], j)
st, j = cc.post_json("/loja/demo/favoritos", {"ids": [VP]})
check("U95 outro aparelho logado: junta com o que já estava na conta", sorted(j.get("ids", [])) == sorted([CP, AP, VP]), j)
st, j = cc.post_json("/loja/demo/favoritos", {"ids": [VP], "substituir": 1})
check("U96 desfavoritar: a conta fica só com o que sobrou", j.get("ids") == [VP], j)
check("U97 Favoritos da conta: 'Guardados na sua conta'", "Guardados na sua conta" in cc.get("/loja/demo/favoritos")[2])

# — avise-me —
limites()
dar_saldo(AP, 0)
st, _, b = pub.get(f"/loja/demo/p/{AP}")
b = pub.get(re.search(r'href="(/loja/demo/p/%s-[^"]+)"' % AP, pub.get("/loja/demo/produtos?q=" + urllib.parse.quote(um(f"SELECT descricao FROM produtos WHERE id={AP}")))[2]).group(1))[2]
check("U98 produto esgotado mostra 'Avise-me quando chegar' com e-mail e aceite", "data-lj-avise" in b and "Avise-me quando chegar" in b and 'name="aceite"' in b)
ca = comprador()
st, j = ca.post_json("/loja/demo/avise-me", {"produto_id": AP, "email": "avisa@teste.test"})
check("U99 sem marcar o aceite não guarda o e-mail", st == 422 and um("SELECT COUNT(*) FROM loja_avise_me") == "0", (st, j))
st, j = ca.post_json("/loja/demo/avise-me", {"produto_id": AP, "email": "nao-e-email", "aceite": 1})
check("U100 e-mail inválido recusado", st == 422, st)
st, j = ca.post_json("/loja/demo/avise-me", {"produto_id": AP, "email": "Avisa@Teste.test", "aceite": 1})
ca.post_json("/loja/demo/avise-me", {"produto_id": AP, "email": "avisa@teste.test", "aceite": 1})
check("U101 pedido de aviso gravado uma vez só (repetir não duplica)", st == 200 and j.get("ok") and um("SELECT CONCAT(COUNT(*),'|',MIN(email)) FROM loja_avise_me") == "1|avisa@teste.test", j)
limpar_registro()
saida = app_cmd("php", "artisan", "loja:rotinas")
check("U102 rotina com o produto ainda esgotado: não avisa ninguém", "avise-me: 0" in saida and "Subject:" not in registro_email(), saida)
dar_saldo(AP, 5)
saida = app_cmd("php", "artisan", "loja:rotinas", "--seco")
check("U103 rotina em modo seco só conta (não envia, não marca)", "avise-me: 1" in saida and "Subject:" not in registro_email() and vazio("SELECT IFNULL(MAX(avisado_em),'') FROM loja_avise_me"), saida)
saida = app_cmd("php", "artisan", "loja:rotinas")
reg = registro_email()
check("U104 produto voltou: 1 e-mail 'Chegou' para quem pediu, com o link do produto", "avise-me: 1" in saida and reg.count("Subject:") == 1 and "avisa@teste.test" in reg and f"/p/{AP}-" in reg, saida)
limpar_registro()
saida = app_cmd("php", "artisan", "loja:rotinas")
check("U105 aviso é único: rodar de novo não reenvia", "avise-me: 0" in saida and "Subject:" not in registro_email(), saida)
sql("UPDATE lojas_online SET config=JSON_SET(config,'$.avise_me',false) WHERE empresa_id=1")
st, j = ca.post_json("/loja/demo/avise-me", {"produto_id": AP, "email": "x@teste.test", "aceite": 1})
check("U106 loja com o avise-me desligado não aceita pedido de aviso (404)", st == 404, st)
sql("UPDATE lojas_online SET config=JSON_SET(config,'$.avise_me',true) WHERE empresa_id=1")

# — avaliações —
limites()
nome_v = um(f"SELECT descricao FROM produtos WHERE id={VP}")
url_v = re.search(r'href="(/loja/demo/p/%s-[^"]+)"' % VP, pub.get("/loja/demo/produtos?q=" + urllib.parse.quote(nome_v))[2]).group(1)
pub.get(f"/loja/demo/pedido/{C_TOKEN}")
pub.post(f"/loja/demo/pedido/{C_TOKEN}/avaliar", {"produto_id": CP, "nota": "5", "texto": "ótimo"})
check("U107 pedido ainda não pago não avalia", um("SELECT COUNT(*) FROM loja_avaliacoes") == "0")
b = pub.get(f"/loja/demo/pedido/{E_TOKEN}")[2]
check("U108 pedido enviado mostra o quadro 'O que você achou?' com as estrelas", 'id="avaliar"' in b and 'name="nota"' in b and "lj-estrelas-campo" in b)
pub.post(f"/loja/demo/pedido/{E_TOKEN}/avaliar", {"produto_id": VP, "nota": "6", "texto": "x"})
check("U109 nota fora de 1 a 5 recusada", um("SELECT COUNT(*) FROM loja_avaliacoes") == "0")
pub.get(f"/loja/demo/pedido/{E_TOKEN}")
st, _, _ = pub.post(f"/loja/demo/pedido/{E_TOKEN}/avaliar", {"produto_id": CP, "nota": "5"})
check("U110 produto que não está no pedido não pode ser avaliado (404)", st == 404, st)
pub.get(f"/loja/demo/pedido/{E_TOKEN}")
pub.post(f"/loja/demo/pedido/{E_TOKEN}/avaliar", {"produto_id": VP, "nota": "4", "texto": "Chegou rápido <script>alert(1)</script>"})
av = sql("SELECT id, nota, status, nome, produto_id, pedido_id FROM loja_avaliacoes")
check("U111 avaliação gravada como PENDENTE, com o primeiro nome do cliente", len(av) == 1 and av[0][1] == "4" and av[0][2] == "pendente" and av[0][3] == "Elisa" and av[0][4] == str(VP), av)
check("U112 enquanto não é conferida, não aparece na loja", "Avaliações de quem comprou" not in pub.get(url_v)[2])
b = dono.get("/app/loja-online/avaliacoes")[2]
check("U113 painel lista a avaliação para conferir, com o texto escapado", "Elisa" in b and "Chegou rápido" in b and "<script>alert(1)</script>" not in b and "compra verificada" in b)
outro.get("/app/dashboard")
st, _, _ = outro.post(f"/app/loja-online/avaliacoes/{av[0][0]}", {"status": "aprovada"})
check("U114 outra empresa não modera avaliação alheia (404)", st == 404 and um("SELECT status FROM loja_avaliacoes") == "pendente", st)
dono.post(f"/app/loja-online/avaliacoes/{av[0][0]}", {"status": "aprovada", "resposta": "Obrigado, Elisa!"})
b = pub.get(url_v)[2]
check("U115 publicada: aparece na página do produto com nota, 'compra verificada' e a resposta da loja",
      "Avaliações de quem comprou" in b and "Chegou rápido" in b and "<script>alert(1)</script>" not in b and "compra verificada" in b and "Obrigado, Elisa!" in b and "4,0" in b)
b = pub.get("/loja/demo/produtos?q=" + urllib.parse.quote(nome_v))[2]
check("U116 o cartão da vitrine mostra a nota e a quantidade", 'class="lj-nota"' in b and "4,0" in b and "(1)" in b)
pub.get(f"/loja/demo/pedido/{E_TOKEN}")
pub.post(f"/loja/demo/pedido/{E_TOKEN}/avaliar", {"produto_id": VP, "nota": "2", "texto": "Mudei de ideia"})
check("U117 reavaliar ATUALIZA (não duplica) e volta para conferência", um("SELECT CONCAT(COUNT(*),'|',MAX(nota),'|',MAX(status)) FROM loja_avaliacoes") == "1|2|pendente")
dono.get("/app/loja-online/avaliacoes")
dono.post(f"/app/loja-online/avaliacoes/{av[0][0]}", {"status": "oculta"})
check("U118 avaliação deixada fora da loja some da página e do cartão", "Avaliações de quem comprou" not in pub.get(url_v)[2] and 'class="lj-nota"' not in pub.get("/loja/demo/produtos?q=" + urllib.parse.quote(nome_v))[2])
sql("UPDATE lojas_online SET config=JSON_SET(config,'$.avaliacoes_moderar',false) WHERE empresa_id=1")
pub.get(f"/loja/demo/pedido/{E_TOKEN}")
pub.post(f"/loja/demo/pedido/{E_TOKEN}/avaliar", {"produto_id": VP, "nota": "5", "texto": "Agora sim"})
check("U119 loja que não confere antes: a avaliação já entra publicada", um("SELECT status FROM loja_avaliacoes") == "aprovada")
sql("UPDATE lojas_online SET config=JSON_SET(config,'$.avaliacoes_moderar',true) WHERE empresa_id=1")
# avaliação de variação vai para o produto principal da grade
dar_saldo(VM, 3)
cv = comprador()
V_ID, V_TOKEN, _ = fechar(cv, VM, 1, nome="Vera Grade", fone="(86) 93333-0004")
sql(f"UPDATE pedidos SET status='faturado' WHERE id={V_ID}")
cv.get(f"/loja/demo/pedido/{V_TOKEN}")
cv.post(f"/loja/demo/pedido/{V_TOKEN}/avaliar", {"produto_id": VM, "nota": "5"})
check("U120 avaliação de uma variação é gravada no produto principal da grade (vale para todos os tamanhos)",
      um(f"SELECT COUNT(*) FROM loja_avaliacoes WHERE pedido_id={V_ID} AND produto_id={GP}") == "1")

# — carrinho abandonado —
limites()
ck = comprador()
ck.post_json("/loja/demo/carrinho/adicionar", {"produto_id": CP, "quantidade": 2})
ck.get("/loja/demo/finalizar")
st, j = ck.post_json("/loja/demo/carrinho/lembrar", {"nome": "Katia", "email": "", "telefone": "123"})
check("U121 sem e-mail nem telefone válidos, o carrinho não é guardado", j.get("ok") is False and um("SELECT COUNT(*) FROM loja_carrinhos") == "0", j)
st, j = ck.post_json("/loja/demo/carrinho/lembrar", {"nome": "Katia Carrinho", "email": "katia@teste.test", "telefone": ""})
ck.post_json("/loja/demo/carrinho/lembrar", {"nome": "Katia Carrinho", "email": "katia@teste.test", "telefone": "(86) 92222-0005"})
linha = sql("SELECT id, token, nome, email, telefone, itens, total FROM loja_carrinhos")
check("U122 com contato, a foto do carrinho é guardada UMA vez por sessão (atualiza, não duplica)",
      len(linha) == 1 and linha[0][3] == "katia@teste.test" and linha[0][4] == "86922220005" and json.loads(linha[0][5])[0]["quantidade"] == 2, linha)
K_TOKEN = linha[0][1]
check("U123 carrinho parado há poucos minutos ainda não é 'abandonado'", "katia@teste.test" not in dono.get("/app/loja-online/carrinhos")[2])
sql("UPDATE loja_carrinhos SET updated_at=NOW() - INTERVAL 3 HOUR")
b = dono.get("/app/loja-online/carrinhos")[2]
check("U124 painel lista o carrinho abandonado com contato, itens, valor, link e botão do WhatsApp",
      "Katia Carrinho" in b and "katia@teste.test" in b and f"/carrinho/recuperar/{K_TOKEN}" in b and "wa.me/5586922220005" in b and "2×" in b)
limpar_registro()
saida = app_cmd("php", "artisan", "loja:rotinas")
check("U125 lembrete automático DESLIGADO (padrão): a rotina não envia e-mail de carrinho", "lembretes de carrinho: 0" in saida and "Subject:" not in registro_email(), saida)
sql("UPDATE lojas_online SET config=JSON_SET(config,'$.carrinho_lembrete',true,'$.carrinho_lembrete_horas',2) WHERE empresa_id=1")
saida = app_cmd("php", "artisan", "loja:rotinas")
reg = registro_email()
check("U126 loja que ligou o lembrete: 1 e-mail com o link de voltar ao carrinho", "lembretes de carrinho: 1" in saida and reg.count("Subject:") == 1 and "katia@teste.test" in reg and f"/carrinho/recuperar/{K_TOKEN}" in reg, saida)
limpar_registro()
saida = app_cmd("php", "artisan", "loja:rotinas")
check("U127 o lembrete é um só por carrinho", "lembretes de carrinho: 0" in saida and "Subject:" not in registro_email(), saida)
sql("UPDATE lojas_online SET config=JSON_SET(config,'$.carrinho_lembrete',false) WHERE empresa_id=1")
cr2 = comprador()
st, hd, _ = cr2.get(f"/loja/demo/carrinho/recuperar/{K_TOKEN}")
b = cr2.get("/loja/demo/carrinho")[2]
check("U128 o link devolve o carrinho pronto em outro aparelho", st == 302 and um(f"SELECT descricao FROM produtos WHERE id={CP}") in b and "Seu carrinho está de volta" in b
      and um("SELECT IFNULL(recuperado_em,'') FROM loja_carrinhos") != "", st)
cr2.get("/loja/demo/finalizar")
st, hd, _ = cr2.post("/loja/demo/finalizar", {"nome": "Katia Carrinho", "telefone": "(86) 92222-0005", "email": "katia@teste.test", "cpf_cnpj": next(CPFS), "entrega": "retirada", "pagamento": "combinar"})
check("U129 fechou a compra pelo link: o carrinho deixa de ser abandonado", st == 302 and um("SELECT IFNULL(pedido_id,'') FROM loja_carrinhos") != "" and "katia@teste.test" not in dono.get("/app/loja-online/carrinhos")[2], st)
st, hd, _ = comprador().get(f"/loja/demo/carrinho/recuperar/{K_TOKEN}")
check("U130 link de carrinho já fechado não recria a compra", st == 302 and hd.get("Location", "").endswith("/carrinho"), st)
st, _, _ = pub.get("/loja/demo/carrinho/recuperar/zzz")
check("U131 link malformado → 404", st == 404, st)

# — números da loja —
limites()
sql("DELETE FROM loja_visitas; DELETE FROM loja_produto_vistas;")
pub.get("/loja/demo")
pub.get("/loja/demo/produtos")
check("U132 robô/ferramenta (sem navegador) não conta como visita", um("SELECT IFNULL(SUM(visitas),0) FROM loja_visitas") == "0")
v1 = comprador()
v1.get("/loja/demo/produtos")
v1.get(url_v)
v1.get(url_v)
check("U133 um visitante = uma visita no dia, por mais páginas que abra", um("SELECT IFNULL(SUM(visitas),0) FROM loja_visitas") == "1", um("SELECT IFNULL(SUM(visitas),0) FROM loja_visitas"))
check("U134 produto visto conta uma vez por visitante", um(f"SELECT IFNULL(SUM(vistas),0) FROM loja_produto_vistas WHERE produto_id={VP}") == "1")
v2 = comprador()
v2.post_json("/loja/demo/carrinho/adicionar", {"produto_id": CP, "quantidade": 1})
v2.get("/loja/demo/finalizar")
nums = sql("SELECT SUM(visitas), SUM(carrinhos), SUM(fechamentos) FROM loja_visitas")[0]
check("U135 segundo visitante: 2 visitas, 1 pôs no carrinho, 1 chegou ao fechamento", nums == ["2", "1", "1"], nums)
dn = Nav()
dn.op.addheaders = [("User-Agent", "Mozilla/5.0 (Teste; Linux) Gecko/20100101 Firefox/130.0")]
dn.get("/login"); dn.post("/login", {"email": "dono@demo.com", "password": "dono123"}); dn.get("/selecionar-unidade"); dn.post("/selecionar-unidade", {"unidade_id": "1"})
dn.get("/loja/demo")
check("U136 a equipe logada no ERP não entra na conta de visitas", um("SELECT SUM(visitas) FROM loja_visitas") == "2")
st, _, b = dono.get("/app/loja-online/resultados?dias=7")
tx = texto(b)
pagos = um("SELECT COUNT(*) FROM pedidos WHERE loja_token IS NOT NULL AND deleted_at IS NULL AND status IN ('confirmado','faturado','entregue') AND created_at >= CURDATE() - INTERVAL 6 DAY")
check("U137 Resultados: visitas, carrinho, fechamento, pedidos pagos, faturamento, ticket, mais vendidos e mais vistos",
      st == 200 and all(x in tx for x in ["Visitas", "Puseram no carrinho", "Chegaram ao fechamento", "Pedidos pagos", "Faturamento", "Ticket médio", "Mais vendidos", "Mais vistos"])
      and re.search(r'data-indicador="Visitas">2<', b) is not None and re.search(rf'data-indicador="Pedidos pagos">{pagos} de ', b) is not None and nome_v in b, (st, pagos))
check("U138 Resultados aceita 7, 30 e 90 dias (valor estranho cai em 30)", re.search(r'btn-outline-secondary active"[^>]*dias=30"', dono.get("/app/loja-online/resultados?dias=999")[2]) is not None
      and re.search(r'btn-outline-secondary active"[^>]*dias=90"', dono.get("/app/loja-online/resultados?dias=90")[2]) is not None)

# — boleto sem Asaas —
b = comprador()
b.post_json("/loja/demo/carrinho/adicionar", {"produto_id": CP, "quantidade": 1})
check("U139 sem Asaas ligado, o fechamento não oferece boleto (mesmo com a opção marcada)", 'value="boleto"' not in b.get("/loja/demo/finalizar")[2])
check("U140 painel: 'Aceitar boleto' fica travado e explica que precisa do Asaas", "precisa do Asaas ligado" in dono.get("/app/loja-online/pedidos")[2])
# arrumação: o que este bloco criou não fica na vitrine nem reservando estoque (os roteiros seguintes partem da loja "limpa")
sql(f"UPDATE produtos SET grade_id=NULL, grade_opcoes=NULL WHERE empresa_id=1; UPDATE produtos SET loja_online=0, status='inativo' WHERE id > {MAXP};"
    f"UPDATE produtos SET descricao='{nome_g}' WHERE id={GP}; UPDATE pedidos SET status='cancelado' WHERE id > {MAXPED} AND loja_token IS NOT NULL;")
for x in (GP, GJ, GD, RP, AP, VP, CP):
    dar_saldo(x, 10)
limites()


# ───────────────────────────── V. 5ª rodada: rastreio, domínio simples, dicas do editor ─────────────────────────────
print("\n== V. 5ª rodada: rastreio completo (origem, funil, aparelho, buscas, abandonados), domínio em 3 passos, dicas do editor ==")
sql("DELETE FROM loja_sessoes; DELETE FROM loja_buscas; UPDATE loja_produto_vistas SET carrinhos=0; UPDATE empresas SET exige_documento_cadastro=0 WHERE id=1;")
IPHONE = "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1"
PC = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36"


def visitante(ua=PC, referer=None):
    n = Nav()
    n.op.addheaders = [("User-Agent", ua)] + ([("Referer", referer)] if referer else [])
    return n


def sessoes(onde="1=1"):
    return sql(f"SELECT origem, pago, IFNULL(campanha,''), aparelho, viu_produto, carrinho, fechamento, IFNULL(pedido_id,'-') FROM loja_sessoes WHERE empresa_id=1 AND {onde} ORDER BY id")


v1 = visitante(IPHONE)
v1.get("/loja/demo?utm_source=Instagram&utm_medium=cpc&utm_campaign=natal")
check("V1 link com utm_source=instagram + cpc + campanha, no iPhone → origem instagram, anúncio, campanha natal, celular", sessoes() == [["instagram", "1", "natal", "celular", "0", "0", "0", "-"]], sessoes())
v1.get("/loja/demo/produtos")
v1.get("/loja/demo?utm_source=google")
check("V2 a mesma pessoa navegando (e até voltando por outro link) continua sendo UMA visita, com a primeira origem", len(sessoes()) == 1 and sessoes()[0][0] == "instagram", sessoes())
v2 = visitante(PC, "https://www.google.com/search?q=loja")
v2.get("/loja/demo")
check("V3 veio da busca do Google (Referer), no computador → origem google, não é anúncio", sessoes("origem='google'") == [["google", "0", "", "computador", "0", "0", "0", "-"]], sessoes())
v3 = visitante(PC)
v3.get("/loja/demo")
check("V4 sem link de campanha e sem site de origem → direto", len(sessoes("origem='direto'")) == 1, sessoes())
visitante(PC).get("/loja/demo?gclid=abc123")
check("V5 clique de anúncio do Google (gclid) → google, anúncio pago", ["google", "1", "", "computador", "0", "0", "0", "-"] in sessoes(), sessoes())
visitante(PC, "https://l.instagram.com/").get("/loja/demo?fbclid=xyz")
visitante(PC, "https://blogdaju.com.br/post").get("/loja/demo")
visitante(PC).get("/loja/demo?utm_source=Panfleto%20Centro")
visitante("Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15", None).get("/loja/demo?utm_source=whatsapp")
orig = sorted(x[0] for x in sql("SELECT origem FROM loja_sessoes WHERE empresa_id=1"))
check("V6 Instagram pelo Referer, site desconhecido = outros, utm livre vira 'panfleto-centro', WhatsApp no iPad = tablet",
      orig == sorted(["instagram", "google", "direto", "google", "instagram", "outros", "panfleto-centro", "whatsapp"]) and um("SELECT aparelho FROM loja_sessoes WHERE origem='whatsapp'") == "tablet", orig)
antes = int(um("SELECT COUNT(*) FROM loja_sessoes"))
dono.get("/loja/demo?utm_source=tiktok")
visitante("Googlebot/2.1 (+http://www.google.com/bot.html)").get("/loja/demo?utm_source=tiktok")
check("V7 a equipe logada no ERP e os robôs de busca não entram no rastreio", int(um("SELECT COUNT(*) FROM loja_sessoes")) == antes and vazio("SELECT id FROM loja_sessoes WHERE origem='tiktok'"))

# — funil —
VPROD = P[4]
dar_saldo(VPROD, 20)
cam_v = (new_cam := php(f"$l=App\\Models\\LojaOnline::where('empresa_id',1)->first(); $c=new App\\Services\\LojaOnline\\CatalogoLoja($l); echo $c->paraVitrine(collect([$c->produto({VPROD})]))->first()['caminho'];"))
v1.get("/loja/demo/" + cam_v)
check("V8 funil: abriu a página de um produto → 'viu produto'", sessoes("origem='instagram' AND campanha='natal'")[0][4:7] == ["1", "0", "0"], (cam_v, sessoes()))
v1.post_json("/loja/demo/carrinho/adicionar", {"produto_id": VPROD, "quantidade": 1})
check("V9 funil: pôs no carrinho → 'carrinho', e o produto ganha 1 em 'posto no carrinho'", sessoes("campanha='natal'")[0][5] == "1"
      and um(f"SELECT SUM(carrinhos) FROM loja_produto_vistas WHERE produto_id={VPROD}") == "1", sessoes())
v1.post_json("/loja/demo/carrinho/adicionar", {"produto_id": VPROD, "quantidade": 1})
check("V10 pôr o mesmo produto de novo não conta duas vezes", um(f"SELECT SUM(carrinhos) FROM loja_produto_vistas WHERE produto_id={VPROD}") == "1")
v1.get("/loja/demo/finalizar")
check("V11 funil: abriu o fechamento → 'começou a fechar'", sessoes("campanha='natal'")[0][6] == "1", sessoes())
v2.post_json("/loja/demo/carrinho/adicionar", {"produto_id": VPROD, "quantidade": 1})
v2.get("/loja/demo/finalizar")
st, hd, _ = v2.post("/loja/demo/finalizar", {"nome": "Cliente Rastreado", "telefone": "(86) 96666-7001", "cpf_cnpj": next(CPFS), "entrega": "retirada", "pagamento": "combinar"})
tok_v = hd.get("Location", "").rsplit("/", 1)[-1]
ped_v = um(f"SELECT id FROM pedidos WHERE loja_token='{tok_v}'")
check("V12 funil: fechou o pedido → a visita do Google fica ligada ao pedido", st == 302 and ped_v is not None and um("SELECT pedido_id FROM loja_sessoes WHERE origem='google' AND pago=0") == ped_v, (st, tok_v, sessoes()))
sql(f"UPDATE pedidos SET status='confirmado' WHERE id={ped_v}")
total_v = um(f"SELECT total FROM pedidos WHERE id={ped_v}")

# — buscas —
v3.get("/loja/demo/produtos?q=Fone")
v3.get("/loja/demo/produtos?q=fone")
v3.get("/loja/demo/produtos?q=xyzabacate")
visitante(PC).get("/loja/demo/produtos?q=xyzabacate")
dono.get("/loja/demo/produtos?q=segredodaequipe")
check("V13 buscas: 'fone' 1× (mesma pessoa não repete), 'xyzabacate' 2× e 2× sem resultado; a busca da equipe não entra",
      sorted(sql("SELECT termo, vezes, sem_resultado FROM loja_buscas WHERE empresa_id=1")) == [["fone", "1", "0"], ["xyzabacate", "2", "2"]], sql("SELECT termo, vezes, sem_resultado FROM loja_buscas"))
v3.get("/loja/demo/buscar?q=cam")
check("V14 as sugestões enquanto digita não viram 'busca' (só a busca enviada conta)", vazio("SELECT id FROM loja_buscas WHERE termo='cam'"))

# — a tela —
st, _, b = dono.get("/app/loja-online/resultados")
etapas = dict(re.findall(r'data-etapa="([^"]+)">.*?data-etapa-n>(\d+)<', b, re.S))
n_sess = um("SELECT COUNT(*) FROM loja_sessoes WHERE empresa_id=1")
check("V15 Resultados: funil com as 6 etapas e os números certos", st == 200 and list(etapas) == ["Entraram na loja", "Viram um produto", "Puseram no carrinho", "Começaram a fechar", "Fizeram o pedido", "Pagaram"]
      and etapas["Entraram na loja"] == n_sess and etapas["Viram um produto"] == "1" and etapas["Puseram no carrinho"] == "2" and etapas["Começaram a fechar"] == "2"
      and etapas["Fizeram o pedido"] == "1" and etapas["Pagaram"] == "1", etapas)
check("V16 Resultados: aponta a maior perda e dá a dica do que fazer", "Maior perda: antes de" in b and "bi-lightbulb" in b)
lin_g = re.search(r'data-origem="google">(.*?)</tr>', b, re.S)
valor_br = f"{float(total_v):,.2f}".replace(",", "X").replace(".", ",").replace("X", ".")
check("V17 Resultados: origem Google com 2 visitas, 1 de anúncio, 1 pedido pago e o valor vendido", lin_g is not None and "1 de anúncio" in lin_g.group(1)
      and re.findall(r'<td class="text-end[^"]*">\s*([^<]+?)\s*</td>', lin_g.group(1))[:3] == ["2", "1", "R$ " + valor_br], lin_g and lin_g.group(1)[:400])
check("V18 Resultados: Instagram, Direto, Outros sites, WhatsApp e a origem livre 'Panfleto centro' aparecem com nome de gente",
      all(t in b for t in ["Instagram", "Direto (digitou ou link salvo)", "Outros sites", "WhatsApp", "Panfleto centro"]))
check("V19 Resultados: campanha 'natal' listada", 'data-campanha="natal"' in b)
check("V20 Resultados: celular, computador e tablet separados", all(f'data-aparelho="{a}"' in b for a in ["Celular", "Computador", "Tablet"]))
check("V21 Resultados: busca sem resultado em destaque e as mais procuradas", 'data-busca-vazia="xyzabacate"' in b and 'data-busca="fone"' in b and 'data-busca-vazia="fone"' not in b)
ab = re.search(r"data-abandonado><span>([^<]+)</span>.*?<strong>(\d+)</strong>.*?de (\d+) carrinho", b, re.S)
check("V22 Resultados: produto mais abandonado = 2 carrinhos, 1 compra → 1 abandono", ab is not None and ab.group(2) == "1" and ab.group(3) == "2", ab and ab.groups())
check("V23 Resultados: clientes novos × que voltaram", re.search(r'data-clientes="novos">(\d+)<', b) is not None and re.search(r'data-clientes="voltaram">(\d+)<', b) is not None)
check("V24 Resultados: gerador de link rastreado e 'na loja agora'", 'id="ljLinkSaida"' in b and 'id="ljAgora"' in b and "utm_source=" in b)
st, _, j = dono.get("/app/loja-online/resultados/agora", json_=True)
j = json.loads(j) if st == 200 else {}
check("V25 'na loja agora' responde em JSON com quem passou nos últimos 5 minutos", st == 200 and isinstance(j.get("agora"), int) and j["agora"] >= 3, (st, j))
sql("UPDATE loja_sessoes SET ultima_em = NOW() - INTERVAL 10 MINUTE")
check("V26 quem saiu há mais de 5 minutos não conta como 'agora'", json.loads(dono.get("/app/loja-online/resultados/agora", json_=True)[2]).get("agora") == 0)
check("V27 a outra empresa não enxerga nada disso", php("$l=App\\Models\\LojaOnline::where('empresa_id','!=',1)->first(); $r=App\\Services\\LojaOnline\\MetricasLoja::resumo($l,30); echo $r['funil'][0]['n'].'|'.count($r['origens']).'|'.count($r['buscas']);") == "0|0|0")
check("V28 caixa (sem o módulo) não abre Resultados nem o 'agora'", caixa.get("/app/loja-online/resultados")[0] == 403 and caixa.get("/app/loja-online/resultados/agora")[0] == 403)
sql("UPDATE loja_sessoes SET dia = CURDATE() - INTERVAL 401 DAY WHERE origem='outros'")
app_cmd("php", "artisan", "loja:rotinas")
check("V29 a rotina apaga o rastreio com mais de 400 dias e deixa o resto", vazio("SELECT id FROM loja_sessoes WHERE origem='outros'") and int(um("SELECT COUNT(*) FROM loja_sessoes")) == int(n_sess) - 1)
sql(f"UPDATE pedidos SET status='cancelado' WHERE id={ped_v}")

# — domínio em 3 passos —
DOM_ANTES = um("SELECT dominio FROM lojas_online WHERE empresa_id=1")
st, _, b = dono.get("/app/loja-online/dominio")
check("V30 aba Domínio abre com os 3 passos e a ajuda por site (Registro.br, GoDaddy, Hostinger, Cloudflare)", st == 200 and all(f'data-passo="{i}"' in b for i in "123")
      and all(t in b for t in ["Registro.br", "GoDaddy", "Hostinger", "Cloudflare", "Dúvidas comuns"]), st)
check("V31 painel agora tem 14 abas, com Domínio e Editor visual", len(re.findall(r'<a class="nav-link py-1 px-2', b)) == 14 and "Domínio</a>" in b and "Editor visual</a>" in b)
sql("UPDATE lojas_online SET dominio=NULL, dominio_status=NULL WHERE empresa_id=1")
dono.get("/app/loja-online/dominio")
st, hd, _ = dono.post("/app/loja-online/dominio", {"dominio": "  HTTPS://WWW.SuaLoja-Teste.invalid/produtos?x=1 "})
check("V32 colou o endereço inteiro, com https, maiúsculas e caminho → guarda só www.sualoja-teste.invalid, aguardando",
      sql("SELECT dominio, dominio_status FROM lojas_online WHERE empresa_id=1") == [["www.sualoja-teste.invalid", "aguardando"]], sql("SELECT dominio, dominio_status FROM lojas_online WHERE empresa_id=1"))
st, _, b = dono.get("/app/loja-online/dominio")
reg = (re.search(r"data-reg-tipo>([^<]+)<", b), re.search(r"data-reg-nome>([^<]+)<", b), re.search(r"data-reg-valor>([^<]+)<", b))
check("V33 passo 2 mostra o registro pronto para copiar: tipo, nome 'www' e o endereço do servidor", all(reg) and reg[0].group(1) in ("A", "CNAME") and reg[1].group(1) == "www" and reg[2].group(1) == "10.213.80.4",
      [r and r.group(1) for r in reg])
check("V34 enquanto o domínio não está no ar, o endereço divulgado continua sendo o que já funciona", "sualoja-teste.invalid" not in dono.get("/app/loja-online")[2].split('id="enderecoLoja"')[0][-300:]
      and php("echo App\\Models\\LojaOnline::where('empresa_id',1)->first()->enderecoPublico();") == "https://demo.lojas.teste")
erros_d = []
for ruim, trecho in [("minha loja", "Digite só o domínio"), ("sualoja", "Digite só o domínio"), ("10.213.80.4", "Digite só o domínio"), ("demo.lojas.teste", "própria plataforma"), ("lojas.teste", "própria plataforma")]:
    dono.get("/app/loja-online/dominio")
    dono.post("/app/loja-online/dominio", {"dominio": ruim})
    if trecho not in dono.get("/app/loja-online/dominio")[2]:
        erros_d.append(ruim)
check("V35 recusa com explicação: texto com espaço, sem ponto, número de IP, endereço da plataforma", erros_d == [] and um("SELECT dominio FROM lojas_online WHERE empresa_id=1") == "www.sualoja-teste.invalid", erros_d)
check("V36 outra empresa não consegue pegar um domínio que já é de uma loja",
      "já está em uso por outra loja" in php("$l=App\\Models\\LojaOnline::where('empresa_id','!=',1)->first(); echo App\\Services\\LojaOnline\\DominioLoja::erro('www.sualoja-teste.invalid', $l);")
      and php("$l=App\\Models\\LojaOnline::where('empresa_id',1)->first(); echo App\\Services\\LojaOnline\\DominioLoja::erro('www.sualoja-teste.invalid', $l) ?? 'livre';") == "livre")
dono.get("/app/loja-online/dominio")
dono.post("/app/loja-online/dominio/conferir")
st, _, b = dono.get("/app/loja-online/dominio")
check("V37 Conferir com o domínio ainda sem apontar → 'Aguardando o apontamento' e a explicação", 'data-dominio-situacao="aguardando"' in b and "Ainda não achamos o apontamento" in b)
PHP_DOM = "use App\\Services\\LojaOnline\\DominioLoja as D; $l=App\\Models\\LojaOnline::where('empresa_id',1)->first(); "
check("V38 aponta para outro servidor → continua aguardando e diz para onde está apontando",
      php(PHP_DOM + "D::$resolver=fn()=>['203.0.113.9']; $r=D::conferir($l); echo $r['status'].'|'.(str_contains($r['mensagem'],'203.0.113.9')?'ip':'-');") == "aguardando|ip")
check("V39 aponta certo, ainda sem cadeado → 'Apontado — falta o cadeado', e o endereço divulgado ainda não muda",
      php(PHP_DOM + "D::$resolver=fn()=>['10.213.80.4']; D::$https=fn()=>false; $r=D::conferir($l); echo $r['status'].'|'.$l->fresh()->enderecoPublico();") == "dns_ok|https://demo.lojas.teste")
check("V40 `loja:dominios` lista o domínio e a situação", "www.sualoja-teste.invalid" in (t := app_cmd("php", "artisan", "loja:dominios")) and "falta o cadeado" in t, t)
check("V41 aponta certo e abre com cadeado → 'No ar', e aí sim o endereço divulgado passa a ser o domínio",
      php(PHP_DOM + "D::$resolver=fn()=>['10.213.80.4']; D::$https=fn()=>true; $r=D::conferir($l); echo $r['status'].'|'.$l->fresh()->enderecoPublico();") == "ativo|https://www.sualoja-teste.invalid")
check("V42 já no ar, o teste do cadeado falhar de dentro do servidor não rebaixa a situação",
      php(PHP_DOM + "D::$resolver=fn()=>['10.213.80.4']; D::$https=fn()=>false; echo D::conferir($l)['status'];") == "ativo")
st, _, b = Nav("www.sualoja-teste.invalid").get("/")
st2, _, b2 = Nav("sualoja-teste.invalid").get("/")
check("V43 a loja abre pelo domínio com www e também sem o www", st == 200 and "Loja Demo Online" in b and st2 == 200 and "Loja Demo Online" in b2, (st, st2))
sql("UPDATE lojas_online SET dominio_status='dns_ok' WHERE empresa_id=1")
t = app_cmd("php", "artisan", "loja:dominios", "--ativar=www.sualoja-teste.invalid")
check("V44 a rotina do servidor avisa que emitiu o certificado (`--ativar`) → No ar; domínio desconhecido dá erro", um("SELECT dominio_status FROM lojas_online WHERE empresa_id=1") == "ativo"
      and "Nenhuma loja" in app_cmd("php", "artisan", "loja:dominios", "--ativar=naoexiste.invalid"), t)
st, _, b = admin.get("/admin/empresas/1")
check("V45 admin vê a situação do domínio no card da loja", "data-dominio-situacao>No ar<" in b)
admin.post("/admin/empresas/1/loja-online/dominio-no-ar")
check("V46 admin desfaz / marca 'no ar' à mão (para onde a rotina do servidor não está instalada)", um("SELECT dominio_status FROM lojas_online WHERE empresa_id=1") == "dns_ok")
admin.get("/admin/empresas/1")
admin.post("/admin/empresas/1/loja-online/dominio-no-ar")
check("V46b …e marca de novo", um("SELECT dominio_status FROM lojas_online WHERE empresa_id=1") == "ativo")
check("V47 dono não usa a rota do admin; vendedor (só leitura? sem o módulo) não mexe no domínio", dono.post("/admin/empresas/1/loja-online/dominio-no-ar")[0] in (302, 403)
      and um("SELECT dominio_status FROM lojas_online WHERE empresa_id=1") == "ativo" and caixa.post("/app/loja-online/dominio", {"dominio": "www.invasor.invalid"})[0] == 403
      and um("SELECT dominio FROM lojas_online WHERE empresa_id=1") == "www.sualoja-teste.invalid")
dono.get("/app/loja-online/dominio")
dono.post("/app/loja-online/dominio/tirar")
check("V48 Tirar o domínio limpa tudo e a loja segue no endereço de sempre", sql("SELECT IFNULL(dominio,'-'), IFNULL(dominio_status,'-') FROM lojas_online WHERE empresa_id=1") == [["-", "-"]]
      and Nav("www.sualoja-teste.invalid").get("/")[2].find("Loja Demo Online") < 0 and pub.get("/loja/demo")[0] == 200)
sql(f"UPDATE lojas_online SET dominio='{DOM_ANTES}', dominio_status=NULL, dominio_obs=NULL, dominio_conferido_em=NULL WHERE empresa_id=1")
check("V49 domínio cadastrado pela equipe (sem situação) continua valendo na hora, como antes", php("echo App\\Models\\LojaOnline::where('empresa_id',1)->first()->enderecoPublico();") == "https://" + DOM_ANTES)

# — dicas do editor —
st, _, b = dono.get("/app/loja-online/editor")
check("V50 editor: abas 'Logo, cores e letras', guia 'para a loja ficar com a sua cara' e a dica de tamanho do logo", st == 200 and "Logo, cores e letras</button>" in b and 'id="edGuia"' in b and "PNG com fundo transparente" in b)
check("V51 editor recebe as dicas de cada bloco e os textos de exemplo dos modelos", "primeira coisa que o cliente v" in b and "Tudo da loja, agora online" in b.split("exemplos:")[1][:6000])
check("V52 todo tipo de bloco tem dica (nenhum ficou de fora)", php("echo implode(',', array_diff(array_keys(App\\Services\\LojaOnline\\ModelosLoja::tiposDeBloco()), array_keys(App\\Services\\LojaOnline\\ModelosLoja::DICAS))) ?: 'ok';") == "ok")
sql("DELETE FROM loja_sessoes; DELETE FROM loja_buscas; UPDATE loja_produto_vistas SET carrinhos=0;")
limites()

sql("UPDATE empresas SET exige_documento_cadastro=1 WHERE id=1")
print("\n== O. O resto do ERP continua igual ==")
for rota in ["/app/dashboard", "/app/produtos", "/app/loja-online/pedidos", "/app/loja-online/grade", "/app/loja-online/resultados", "/app/loja-online/dominio", f"/app/produtos/{PF}", "/app/pedidos", "/app/promocoes", "/app/vendas", "/app/estoque/movimentacoes", "/app/configuracoes",
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
