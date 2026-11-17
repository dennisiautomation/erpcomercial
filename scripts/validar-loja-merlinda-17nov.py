#!/usr/bin/env python3
"""
Validação do MODELO EXCLUSIVO da loja online (MISS MERLINDA, 17/11/2026).

Roda contra um ambiente DESCARTÁVEL com cópia do banco de produção (rede interna, sem internet):
    BASE=http://10.213.80.13 MYSQL=mm-mysql APP=mm-app python3 scripts/validar-loja-merlinda-17nov.py

Pré-requisito no banco descartável: senha "teste123" para o admin (id 1), o dono da MISS MERLINDA (id 15,
empresa 4) e o dono da DONA DOURO (id 16, empresa 5) — a outra loja, para provar o isolamento. Os segredos cifrados
(empresa_gateways, plataforma_configuracoes) vêm com a APP_KEY da produção: zerar na cópia (nenhum provedor é chamado aqui).
"""
import os, re, subprocess, sys
import requests

BASE = os.environ.get("BASE", "http://10.213.80.13")
MYSQL = os.environ.get("MYSQL", "mm-mysql")
APP = os.environ.get("APP", "mm-app")
MM, DD = 4, 5
ok = falhas = 0


def check(nome, cond, extra=""):
    global ok, falhas
    if cond:
        ok += 1
        print(f"✅ {nome}")
    else:
        falhas += 1
        print(f"❌ {nome} {extra}")


def sql(q):
    r = subprocess.run(["docker", "exec", "-i", MYSQL, "mysql", "--default-character-set=utf8mb4", "-uroot", "-proot", "erp_teste", "-N", "-B"],
                       input=q, capture_output=True, text=True)
    return [l.split("\t") for l in r.stdout.rstrip("\n").split("\n")] if r.stdout.strip("\n") != "" or r.stdout == "\n" else []


def um(q):
    r = sql(q)
    return r[0][0] if r else None


class Nav:
    def __init__(self):
        self.s = requests.Session()
        self.token = ""

    def _guardar(self, r):
        m = re.search(r'name="csrf-token" content="([^"]+)"', r.text) or re.search(r'name="_token" value="([^"]+)"', r.text)
        if m:
            self.token = m.group(1)
        return r

    def get(self, p, **kw):
        return self._guardar(self.s.get(BASE + p, allow_redirects=False, **kw))

    def post(self, p, dados=None, metodo=None):
        d = {"_token": self.token, **(dados or {})}
        if metodo:
            d["_method"] = metodo
        return self._guardar(self.s.post(BASE + p, data=d, allow_redirects=False))

    def json(self, p, dados):
        r = self.s.post(BASE + p, json=dados, allow_redirects=False,
                        headers={"X-CSRF-TOKEN": self.token, "Accept": "application/json", "X-Requested-With": "XMLHttpRequest"})
        try:
            return r.status_code, r.json()
        except Exception:
            return r.status_code, {"_corpo": r.text[:300]}

    def entrar(self, email):
        self.get("/login")
        r = self.post("/login", {"email": email, "password": "teste123"})
        self.get("/app/dashboard")
        return r.status_code in (302, 303)


# ── estado inicial: sem loja nas duas empresas ──
sql(f"DELETE FROM lojas_online WHERE empresa_id IN ({MM},{DD}); UPDATE produtos SET loja_online=0, loja_online_destaque=0 WHERE empresa_id IN ({MM},{DD});")
subprocess.run(["docker", "exec", APP, "sh", "-c", f"rm -f /var/www/storage/app/public/loja-online/{MM}/merlinda-*"])

admin, dono, outro, publico = Nav(), Nav(), Nav(), Nav()
check("A01 admin entra", admin.entrar("admin@ia365.com.br"))
check("A02 dono da Miss Merlinda entra", dono.entrar("ferrugemtintas@gmail.com"))
check("A03 dono da Dona Douro entra", outro.entrar("donadouro26@gmail.com"))

# ── admin: libera as duas lojas e dá o exclusivo só à Miss Merlinda ──
for emp in (MM, DD):
    admin.get(f"/admin/empresas/{emp}")
    admin.post(f"/admin/empresas/{emp}/loja-online/toggle")
check("A04 duas lojas liberadas", um(f"SELECT COUNT(*) FROM lojas_online WHERE empresa_id IN ({MM},{DD}) AND ativo=1") == "2")
r = admin.get(f"/admin/empresas/{MM}")
check("A05 card do admin mostra 'Modelo exclusivo' com a opção Miss Merlinda", "Modelo exclusivo" in r.text and ">Miss Merlinda<" in r.text)
r = admin.post(f"/admin/empresas/{MM}/loja-online/modelo-exclusivo", {"modelo_exclusivo": "inventado"})
check("A06 modelo exclusivo inexistente é recusado", um(f"SELECT IFNULL(modelo_exclusivo,'') FROM lojas_online WHERE empresa_id={MM}") == "")
r = dono.post(f"/admin/empresas/{MM}/loja-online/modelo-exclusivo", {"modelo_exclusivo": "merlinda"})
check("A07 lojista não consegue mexer na rota do admin", r.status_code in (302, 403) and um(f"SELECT IFNULL(modelo_exclusivo,'') FROM lojas_online WHERE empresa_id={MM}") == "", str(r.status_code))
admin.get(f"/admin/empresas/{MM}")
r = admin.post(f"/admin/empresas/{MM}/loja-online/modelo-exclusivo", {"modelo_exclusivo": "merlinda"})
linha = sql(f"SELECT modelo, modelo_exclusivo, JSON_LENGTH(blocos), JSON_UNQUOTE(JSON_EXTRACT(tema,'$.logo')), JSON_UNQUOTE(JSON_EXTRACT(config,'$.whatsapp')), "
            f"JSON_UNQUOTE(JSON_EXTRACT(config,'$.texto_rodape')), IFNULL(rascunho,'') FROM lojas_online WHERE empresa_id={MM}")[0]
check("A08 exclusivo aplicado: modelo e chave = merlinda", linha[0] == "merlinda" and linha[1] == "merlinda", str(linha[:2]))
check("A09 página inicial publicada com os blocos do modelo (9)", linha[2] == "9", linha[2])
check("A10 logo da loja já posta", linha[3] == f"loja-online/{MM}/merlinda-logo.png", linha[3])
check("A11 WhatsApp preenchido a partir do cadastro", linha[4] == "(99) 8542-0619", linha[4])
check("A12 rodapé com as cidades das lojas", "seis lojas em Teresina, Timon e Caxias" in (linha[5] or ""), linha[5])
arqs = subprocess.run(["docker", "exec", APP, "sh", "-c", f"ls /var/www/storage/app/public/loja-online/{MM}/ | grep -c '^merlinda-'"], capture_output=True, text=True).stdout.strip()
check("A13 as 18 imagens de partida copiadas para a pasta da loja", arqs == "18", arqs)
check("A14 a outra loja continua sem exclusivo", um(f"SELECT CONCAT(modelo,'|',IFNULL(modelo_exclusivo,'')) FROM lojas_online WHERE empresa_id={DD}") == "vitrine|")

# ── editor: só a Miss Merlinda vê o modelo ──
r = dono.get("/app/loja-online/editor")
check("E01 editor da Miss Merlinda abre", r.status_code == 200, str(r.status_code))
check("E02 editor lista o modelo Miss Merlinda como exclusivo", '"chave":"merlinda"' in r.text and '"exclusivo":true' in r.text)
check("E03 editor oferece o bloco 'Nossas lojas' e a fonte Gilda", '"lojas_fisicas"' in r.text and '"gilda"' in r.text)
r2 = outro.get("/app/loja-online/editor")
check("E04 editor da outra loja abre", r2.status_code == 200, str(r2.status_code))
check("E05 outra loja NÃO vê o modelo, o bloco nem a fonte", "merlinda" not in r2.text.lower() and '"lojas_fisicas"' not in r2.text and '"gilda"' not in r2.text)
st, j = outro.json("/app/loja-online/editor/modelo", {"modelo": "merlinda"})
check("E06 outra loja forçando o modelo pelo navegador é recusada (422)", st == 422, str(st))
st, j = outro.json("/app/loja-online/editor/rascunho", {"modelo": "merlinda", "tema": {}, "blocos": []})
check("E07 outra loja forçando o rascunho com o modelo é recusada (422)", st == 422, str(st))
st, j = outro.json("/app/loja-online/editor/rascunho", {"modelo": "vitrine", "tema": {}, "blocos": [{"tipo": "lojas_fisicas", "titulo": "x"}]})
check("E08 outra loja não grava o bloco exclusivo", st == 200 and all(b["tipo"] != "lojas_fisicas" for b in j.get("rascunho", {}).get("blocos", [])), str(st))
outro.json("/app/loja-online/editor/descartar", {})

dono.get("/app/loja-online/editor")
st, j = dono.json("/app/loja-online/editor/modelo", {"modelo": "atelie"})
check("E09 Miss Merlinda pode experimentar um modelo público", st == 200 and j["rascunho"]["modelo"] == "atelie", str(st))
st, j = dono.json("/app/loja-online/editor/modelo", {"modelo": "merlinda"})
blocos = j.get("rascunho", {}).get("blocos", [])
check("E10 e voltar ao exclusivo com as imagens dela", st == 200 and any(s.get("imagem", "").startswith(f"loja-online/{MM}/merlinda-capa") for b in blocos for s in b.get("lista", [])), str(st))
tema = dict(j["rascunho"]["tema"]); tema["cor_destaque"] = "#7a1040"
blocos.append({"tipo": "texto", "titulo": "Recado da loja", "texto": "Teste do editor", "alinhamento": "centro"})
st, j = dono.json("/app/loja-online/editor/rascunho", {"modelo": "merlinda", "tema": tema, "blocos": blocos})
check("E11 editor grava cor e bloco novo no rascunho", st == 200 and j["rascunho"]["tema"]["cor_destaque"] == "#7a1040" and any(b["tipo"] == "lojas_fisicas" for b in j["rascunho"]["blocos"]), str(st))
r = dono.get("/loja/miss-merlinda?rascunho=1")
check("E12 prévia do editor mostra a mudança", "Recado da loja" in r.text and "#7a1040" in r.text)
r = dono.get("/loja/miss-merlinda")
check("E13 loja publicada ainda não mudou", "Recado da loja" not in r.text)
st, j = dono.json("/app/loja-online/editor/publicar", {})
r = dono.get("/loja/miss-merlinda")
check("E14 Publicar leva a mudança para a loja", st == 200 and "Recado da loja" in r.text, str(st))
check("E15 tema publicado guarda só o que difere do modelo", um(f"SELECT JSON_UNQUOTE(JSON_EXTRACT(tema,'$.cor_destaque')) FROM lojas_online WHERE empresa_id={MM}") == "#7a1040")

# ── vitrine (prévia: loja fora do ar, dono logado) ──
r = dono.get("/loja/miss-merlinda")
h = r.text
check("V01 vitrine abre na prévia", r.status_code == 200, str(r.status_code))
check("V02 visual exclusivo carregado (data-modelo + folha própria)", 'data-modelo="merlinda"' in h and "MODELO EXCLUSIVO" not in h and "--rosa:#fedbe5" in h)
check("V03 fonte Gilda Display carregada", "family=Gilda+Display" in h)
check("V04 logo no topo", f"/storage/loja-online/{MM}/merlinda-logo.png" in h)
check("V05 capa com as 3 fotos em arco", all(f"merlinda-capa-{i}.jpg" in h for i in (1, 2, 3)))
check("V06 produtos de exemplo na prévia, marcados", h.count("lj-selo lj-selo--exemplo") >= 8 and "Produtos de exemplo, só na prévia" in h)
check("V07 exemplo não tem botão de carrinho nem de favorito", 'data-produto="-1"' in h and 'data-lj-fav="-1"' not in h and 'name="produto_id" value="-1"' not in h)
for nome in ("Matriz", "Timon", "Caxias", "Riverside", "Dom Severino", "Dirceu"):
    check(f"V08 'Nossas lojas' traz {nome}", nome in h)
check("V09 WhatsApp das lojas vem do cadastro", "wa.me/5599981194627" in h and "wa.me/5586995774085" in h)
check("V10 endereço sem número 'SN' não mostra 'SN'", ", SN" not in h)
check("V11 faixa com as cidades", "Teresina, Timon e Caxias" in h)
for a in ("merlinda-capa-1.jpg", "merlinda-logo.png", "merlinda-ex-1.jpg", "merlinda-lojas.jpg"):
    rr = requests.get(f"{BASE}/storage/loja-online/{MM}/{a}")
    check(f"V12 imagem {a} servida", rr.status_code == 200 and len(rr.content) > 5000, str(rr.status_code))
r = publico.get("/loja/miss-merlinda")
check("V13 público não vê a loja fora do ar (503)", r.status_code == 503, str(r.status_code))
r = outro.get("/loja/miss-merlinda")
check("V14 dono de outra empresa não vê a prévia (503)", r.status_code == 503, str(r.status_code))
r = outro.get(f"/loja/{um(f'SELECT slug FROM lojas_online WHERE empresa_id={DD}')}")
check("V15 vitrine da outra loja sem nada do modelo exclusivo", r.status_code == 200 and "data-modelo" not in r.text and "merlinda" not in r.text.lower() and "Gilda" not in r.text, str(r.status_code))

# ── produtos reais: exemplos somem; pôr no ar ──
ids = [l[0] for l in sql(f"SELECT id FROM produtos WHERE empresa_id={MM} AND deleted_at IS NULL AND status='ativo' ORDER BY id DESC LIMIT 4")]
dono.get("/app/loja-online/produtos")
r = dono.post("/app/loja-online/produtos", {"acao": "entrar", "alcance": "marcados", "ids[]": ids})
check("P01 dono marca 4 produtos reais pela tela", um(f"SELECT COUNT(*) FROM produtos WHERE empresa_id={MM} AND loja_online=1") == "4")
r = dono.get("/loja/miss-merlinda")
check("P02 com produto real, os exemplos somem da prévia", "lj-selo lj-selo--exemplo" not in r.text and "Produtos de exemplo, só na prévia" not in r.text)
dono.get("/app/loja-online")
r = dono.post("/app/loja-online/publicar", {"publicar": "1"})
check("P03 pôr no ar", um(f"SELECT publicada FROM lojas_online WHERE empresa_id={MM}") == "1")
r = publico.get("/loja/miss-merlinda")
check("P04 público vê a loja com o visual exclusivo", r.status_code == 200 and 'data-modelo="merlinda"' in r.text, str(r.status_code))
check("P05 público nunca vê exemplo", "lj-selo lj-selo--exemplo" not in r.text and ">Exemplo<" not in r.text)
for p in ("/loja/miss-merlinda/produtos", "/loja/miss-merlinda/carrinho", "/loja/miss-merlinda/politica/trocas", "/loja/miss-merlinda/favoritos"):
    rr = publico.get(p)
    check(f"P06 {p} abre com o visual exclusivo", rr.status_code == 200 and 'data-modelo="merlinda"' in rr.text, str(rr.status_code))
prod = re.search(r'href="(/loja/miss-merlinda/p/[^"]+)"', publico.get("/loja/miss-merlinda/produtos").text)
if prod:
    rr = publico.get(prod.group(1))
    check("P07 página do produto abre", rr.status_code == 200 and 'data-modelo="merlinda"' in rr.text, str(rr.status_code))

# ── todas as telas do painel da loja ──
for aba in ("", "/produtos", "/editor", "/configuracoes", "/promocoes", "/avisos", "/politicas", "/rastreamento", "/resultados",
            "/carrinhos", "/pedidos", "/avaliacoes", "/dominio", "/grade"):
    rr = dono.get("/app/loja-online" + aba)
    check(f"C01 painel {aba or '/'} abre", rr.status_code == 200, str(rr.status_code))
dono.get("/app/loja-online/configuracoes")
form = dono.get("/app/loja-online/configuracoes").text
check("C02 configurações já trazem o WhatsApp", "(99) 8542-0619" in form)
def campos_do_form(html, acao):
    """Lê o formulário como o navegador mandaria (inputs, checkboxes marcados, selects, textareas)."""
    m = re.search(r'<form[^>]*action="[^"]*' + re.escape(acao) + r'"[^>]*>(.*?)</form>', html, re.S)
    corpo, d = (m.group(1) if m else ""), {}
    for tag in re.findall(r"<input[^>]*>", corpo):
        nome = re.search(r'name="([^"]+)"', tag)
        if not nome or nome.group(1) in ("_token", "_method") or 'type="file"' in tag:
            continue
        tipo = (re.search(r'type="([^"]+)"', tag) or [None, "text"])[1]
        valor = (re.search(r'value="([^"]*)"', tag) or [None, ""])[1]
        if tipo in ("checkbox", "radio") and "checked" not in tag:
            continue
        d[nome.group(1)] = valor
    for nome, opcoes in re.findall(r'<select[^>]*name="([^"]+)"[^>]*>(.*?)</select>', corpo, re.S):
        sel = re.search(r'<option[^>]*value="([^"]*)"[^>]*selected', opcoes) or re.search(r'<option[^>]*value="([^"]*)"', opcoes)
        d[nome] = sel.group(1) if sel else ""
    for nome, texto in re.findall(r'<textarea[^>]*name="([^"]+)"[^>]*>(.*?)</textarea>', corpo, re.S):
        d[nome] = texto
    return d


pg = dono.get("/app/loja-online/promocoes").text
dados = campos_do_form(pg, "secao/promocoes")
dados["frete_gratis_acima"] = "299"
r = dono.post("/app/loja-online/secao/promocoes", dados, metodo="PUT")
check("C03 salvar uma seção (promoções) funciona", um(f"SELECT JSON_EXTRACT(config,'$.frete_gratis_acima') FROM lojas_online WHERE empresa_id={MM}") in ("299", "299.0", "\"299\""), str(r.status_code))
check("C04 salvar seção não apaga o que o modelo preencheu", um(f"SELECT JSON_UNQUOTE(JSON_EXTRACT(config,'$.whatsapp')) FROM lojas_online WHERE empresa_id={MM}") == "(99) 8542-0619")

# ── admin tira o exclusivo ──
admin.get(f"/admin/empresas/{MM}")
admin.post(f"/admin/empresas/{MM}/loja-online/modelo-exclusivo", {"modelo_exclusivo": ""})
check("X01 sem exclusivo: loja volta ao modelo padrão", um(f"SELECT CONCAT(modelo,'|',IFNULL(modelo_exclusivo,'')) FROM lojas_online WHERE empresa_id={MM}") == "vitrine|")
r = dono.get("/app/loja-online/editor")
check("X02 e o modelo some do editor dela", r.status_code == 200 and '"chave":"merlinda"' not in r.text)
r = publico.get("/loja/miss-merlinda")
check("X03 vitrine segue no ar, sem a folha exclusiva", r.status_code == 200 and "data-modelo" not in r.text, str(r.status_code))
admin.get(f"/admin/empresas/{MM}")
admin.post(f"/admin/empresas/{MM}/loja-online/modelo-exclusivo", {"modelo_exclusivo": "merlinda"})
check("X04 religar o exclusivo reaplica o modelo", um(f"SELECT modelo FROM lojas_online WHERE empresa_id={MM}") == "merlinda")
check("X05 religar não sobrescreve o WhatsApp que a loja já tem", um(f"SELECT JSON_UNQUOTE(JSON_EXTRACT(config,'$.whatsapp')) FROM lojas_online WHERE empresa_id={MM}") == "(99) 8542-0619")

log = subprocess.run(["docker", "exec", APP, "sh", "-c", "grep -c 'production.ERROR\\|local.ERROR' /var/www/storage/logs/laravel.log 2>/dev/null || echo 0"], capture_output=True, text=True).stdout.strip()
print(f"\nerros no laravel.log: {log}")
print(f"\n{ok} ✅ / {falhas} ❌")
sys.exit(1 if falhas else 0)
