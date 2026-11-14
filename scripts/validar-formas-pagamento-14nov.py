#!/usr/bin/env python3
"""Validação ✅/❌ — Configurações → Formas de pagamento (14/11/2026).

Ambiente DESCARTÁVEL (banco novo com o seed demo; o script mexe à vontade):
    BASE=http://10.213.79.4 MYSQL=fp-mysql APP=fp-app python3 scripts/validar-formas-pagamento-14nov.py

Cobre: padrão idêntico ao PDV antigo sem gravar nada; salvar (ordem, nome, cor,
largura, desligar, criar); PDV/faturar/balcão usando a lista; venda grava o código
de sempre + o nome; forma desligada recusada; payload antigo aceito; caixa,
relatório, cupom; regras de validação; perfis; isolamento; Credishop; restaurar.
"""
import html as H, http.cookiejar, json, os, re, subprocess, sys, urllib.error, urllib.parse, urllib.request

BASE = os.environ.get("BASE", "http://10.213.79.4")
MYSQL = os.environ.get("MYSQL", "fp-mysql")
APP = os.environ.get("APP", "fp-app")
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
    r = subprocess.run(["docker", "exec", "-i", MYSQL, "mysql", "--default-character-set=utf8mb4", "-uerp", "-perp", "erp_teste", "-N", "-B"],
                       input=q, capture_output=True, text=True)
    if r.returncode:
        raise RuntimeError(r.stderr)
    return [l.split("\t") for l in r.stdout.strip().splitlines() if l]


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


class Sessao:
    def __init__(self, email, senha):
        self.cj = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cj), NoRedirect)
        self.token = None
        s, h, _ = self.get("/login")
        self.post("/login", {"email": email, "password": senha})
        self.post("/selecionar-unidade", {"unidade_id": "1"})

    def _req(self, req):
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

    def get(self, p):
        return self._req(urllib.request.Request(BASE + p, headers={"Accept": "text/html"}))

    def post(self, p, campos, metodo=None):
        if self.token is None:
            self.get("/login")
        dados = dict(campos)
        dados["_token"] = self.token
        if metodo:
            dados["_method"] = metodo
        return self._req(urllib.request.Request(BASE + p, data=urllib.parse.urlencode(dados, doseq=True).encode(), headers={"Accept": "text/html"}))

    def post_json(self, p, obj):
        if self.token is None:
            self.get("/app/dashboard")
        return self._req(urllib.request.Request(BASE + p, data=json.dumps(obj).encode(), headers={
            "Content-Type": "application/json", "Accept": "application/json", "X-Requested-With": "XMLHttpRequest", "X-CSRF-TOKEN": self.token}))

    def follow(self, st, hd):
        return self.get(hd.get("Location", "").replace(BASE, "")) if st in (301, 302, 303) else (st, hd, "")


def flash(s):
    st, hd, b = s.get("/app/configuracoes/formas-pagamento")
    m = re.findall(r'erp-alert-(danger|success)[^"]*"[^>]*>.*?<div class="flex-grow-1">(.*?)</div>', b, re.S)
    return H.unescape(" ".join(x[1].strip() for x in m)), b


def formas_da_tela(body):
    m = re.search(r"let formas = (\[.*?\]);", body)
    return json.loads(m.group(1)) if m else None


def botoes_pdv(body):
    return re.findall(r'<button class="btn-pay([^"]*)" data-forma="([^"]*)" data-chave="([^"]*)" data-nome="([^"]*)"', body)


def salvar(s, linhas):
    st, hd, b = s.post("/app/configuracoes/formas-pagamento", {"formas": json.dumps(linhas)}, metodo="PUT")
    return st, hd


def venda_pdv(s, pagamentos, produto=2, qtd=1, preco=599.90):
    return s.post_json("/app/pdv/venda", {
        "itens": [{"produto_id": produto, "quantidade": qtd, "preco_unitario": preco}],
        "pagamentos": pagamentos, "tabela_precos": False, "documento": "recibo",
    })


# ---------------------------------------------------------------- preparo
sql("DELETE FROM formas_pagamento; UPDATE empresas SET pdv_credishop=0; DELETE FROM contas_receber WHERE descricao='Teste Elo'; DELETE FROM ordens_servico WHERE numero=9901; DELETE FROM adquirente_taxas;")
ANTIGA = ["dinheiro", "cartao_credito", "cartao_debito", "pix", "boleto", "crediario", "transferencia", "vale"]
ANTIGOS_NOMES = ["Dinheiro", "Crédito", "Débito", "PIX", "Boleto", "Crediario", "Transf.", "Vale"]

dono = Sessao("dono@demo.com", "dono123")
st, hd, b = dono.get("/app/configuracoes/formas-pagamento")
check("1. dono abre a tela (200)", st == 200, st)
fs = formas_da_tela(b)
check("2. tela traz as 8 formas de fábrica, na ordem do PDV antigo", fs and [f["chave"] for f in fs] == ANTIGA, fs and [f["chave"] for f in fs])
check("3. nomes de fábrica = textos dos botões antigos", fs and [f["nome"] for f in fs] == ANTIGOS_NOMES)
check("4. tela avisa 'botões de fábrica' e NADA foi gravado", "botões de fábrica" in b and sql("SELECT COUNT(*) FROM formas_pagamento")[0][0] == "0")
check("5. Configurações da Loja tem o botão para a tela", "configuracoes/formas-pagamento" in dono.get("/app/configuracoes")[2])

# abre caixa e confere o PDV no padrão
dono.post("/caixa/abrir", {})
st, hd, b = dono.post("/app/caixa/abrir", {"valor_abertura": "100"})
st, hd, pdv = dono.get("/app/pdv")
bt = botoes_pdv(pdv)
check("6. PDV desenha os mesmos 8 botões, mesma ordem", [x[2] for x in bt] == ANTIGA, [x[2] for x in bt])
check("7. PDV: data-forma = código de sempre em cada botão", [x[1] for x in bt] == ANTIGA)
check("8. PDV sem Credishop para empresa sem a chave", "credishop" not in pdv.split('class="payment-grid"')[1][:3000])

# venda no padrão, payload novo e antigo
st, hd, r = venda_pdv(dono, [{"forma": "pix", "valor": 599.90, "chave": "pix", "nome": "PIX"}])
check("9. venda PDV com PIX (padrão) passa", st == 200 and '"success":true' in r.replace(" ", ""), (st, r[:200]))
st, hd, r = venda_pdv(dono, [{"forma": "dinheiro", "valor": 700}])
check("10. payload ANTIGO (só forma, aba aberta antes do deploy) passa", st == 200, (st, r[:200]))
v = sql("SELECT pagamento_detalhes FROM vendas ORDER BY id DESC LIMIT 1")[0][0]
check("11. venda guarda forma=dinheiro + forma_nome Dinheiro", '"forma": "dinheiro"' in v.replace('":"', '": "') and "Dinheiro" in v, v)

# ---------------------------------------------------------------- salvar configuração
nova = [
    {"chave": "pix", "nome": "PIX", "tipo": "pix", "icone": "bi-qr-code", "cor": "#a855f7", "largura": 1, "ativo": True},
    {"chave": "dinheiro", "nome": "Dinheiro", "tipo": "dinheiro", "icone": "bi-cash-stack", "cor": "#22c55e", "largura": 1, "ativo": True},
    {"chave": "", "nome": "Cartão Elo", "tipo": "cartao_credito", "icone": "bi-credit-card-fill", "cor": "#ec4899", "largura": 2, "ativo": True},
    {"chave": "cartao_credito", "nome": "Cartão", "tipo": "pix", "icone": "bi-credit-card", "cor": "#eab308", "largura": 1, "ativo": True},
    {"chave": "cartao_debito", "nome": "Débito", "tipo": "cartao_debito", "icone": "bi-credit-card-2-front", "cor": "#06b6d4", "largura": 1, "ativo": True},
    {"chave": "crediario", "nome": "Crediario", "tipo": "crediario", "icone": "bi-journal-text", "cor": "#ef4444", "largura": 1, "ativo": True},
    {"chave": "transferencia", "nome": "Transf.", "tipo": "transferencia", "icone": "bi-bank", "cor": "#3b82f6", "largura": 1, "ativo": True},
    {"chave": "vale", "nome": "Vale", "tipo": "vale", "icone": "bi-ticket-perforated", "cor": "#20c997", "largura": 1, "ativo": True},
    {"chave": "boleto", "nome": "Boleto", "tipo": "boleto", "icone": "bi-upc-scan", "cor": "#fd7e14", "largura": 1, "ativo": False},
]
st, hd = salvar(dono, nova)
msg, b = flash(dono)
check("12. salvar redireciona com sucesso", st == 302 and "salvas" in msg, (st, msg[:200]))
rows = sql("SELECT chave,nome,tipo,sistema,largura,ordem,ativo FROM formas_pagamento WHERE empresa_id=1 ORDER BY ordem")
check("13. 9 linhas gravadas na ordem enviada", [r[1] for r in rows] == ["PIX", "Dinheiro", "Cartão Elo", "Cartão", "Débito", "Crediario", "Transf.", "Vale", "Boleto"], rows)
elo = [r for r in rows if r[1] == "Cartão Elo"][0]
check("14. forma criada ganha chave fp_xxxxxxxx, tipo crédito, largura 2, não-sistema", re.fullmatch(r"fp_[a-z0-9]{8}", elo[0]) and elo[2] == "cartao_credito" and elo[4] == "2" and elo[3] == "0", elo)
cred = [r for r in rows if r[0] == "cartao_credito"][0]
check("15. do sistema: renomeia, mas o tipo NÃO muda (pedido de 'pix' ignorado)", cred[1] == "Cartão" and cred[2] == "cartao_credito", cred)
check("16. boleto gravado desligado", [r for r in rows if r[0] == "boleto"][0][6] == "0")
CH_ELO = elo[0]

st, hd, pdv = dono.get("/app/pdv")
bt = botoes_pdv(pdv)
check("17. PDV segue a nova ordem e esconde o desligado", [x[3] for x in bt] == ["PIX", "Dinheiro", "Cartão Elo", "Cartão", "Débito", "Crediario", "Transf.", "Vale"], [x[3] for x in bt])
belo = [x for x in bt if x[2] == CH_ELO][0]
check("18. botão novo: linha inteira e data-forma=cartao_credito", "pay-largo" in belo[0] and belo[1] == "cartao_credito", belo)
check("19. botão renomeado: 'Cartão' com data-forma=cartao_credito", [x for x in bt if x[2] == "cartao_credito"][0][1] == "cartao_credito")
check("20. cor e ícone configurados chegam ao PDV", 'bi-credit-card-fill" style="color: #ec4899"' in pdv)

st, hd, r = venda_pdv(dono, [{"forma": "cartao_credito", "valor": 599.90, "chave": CH_ELO, "nome": "Cartão Elo", "parcelas": 1}])
check("21. venda PDV com a forma criada passa", st == 200, (st, r[:300]))
vid, det, fp = sql("SELECT id, pagamento_detalhes, forma_pagamento FROM vendas ORDER BY id DESC LIMIT 1")[0]
d = json.loads(det)[0]
check("22. venda grava forma=cartao_credito (nota/caixa/juros seguem iguais)", d["forma"] == "cartao_credito" and fp == "cartao_credito", d)
check("23. venda grava forma_nome 'Cartão Elo' e forma_chave", d.get("forma_nome") == "Cartão Elo" and d.get("forma_chave") == CH_ELO, d)
mc = sql(f"SELECT forma_pagamento, descricao FROM movimentacoes_caixa WHERE venda_id={vid}")
check("24. caixa: entra como cartão de crédito, descrição igual à de sempre", mc and mc[0][0] == "cartao_credito" and mc[0][1].startswith("Venda #"), mc)
st, hd, r = venda_pdv(dono, [{"forma": "cartao_credito", "valor": 599.90, "chave": "cartao_credito", "nome": "Cartão"}])
vid2 = sql("SELECT id FROM vendas ORDER BY id DESC LIMIT 1")[0][0]
mc2 = sql(f"SELECT descricao FROM movimentacoes_caixa WHERE venda_id={vid2}")
check("25. forma do sistema renomeada: descrição do caixa igual à de antes", mc2 and mc2[0][0] == f"Venda #{sql('SELECT numero FROM vendas WHERE id=' + vid2)[0][0]}", mc2)

n_antes = sql("SELECT COUNT(*) FROM vendas")[0][0]
st, hd, r = venda_pdv(dono, [{"forma": "boleto", "valor": 599.90, "chave": "boleto"}])
check("26. forma DESLIGADA é recusada pelo servidor (422, venda não nasce)", st == 422 and "desligada" in r and sql("SELECT COUNT(*) FROM vendas")[0][0] == n_antes, (st, r[:200]))
st, hd, r = venda_pdv(dono, [{"forma": "pix", "valor": 599.90, "chave": "fp_naoexiste"}])
check("27. chave inexistente/forjada é recusada", st == 422)
st, hd, r = venda_pdv(dono, [{"forma": "pix", "valor": 300, "chave": "pix"}, {"forma": "cartao_credito", "valor": 299.90, "chave": CH_ELO}])
d = json.loads(sql("SELECT pagamento_detalhes FROM vendas ORDER BY id DESC LIMIT 1")[0][0])
check("28. split PIX + Cartão Elo grava os dois nomes", st == 200 and [x.get("forma_nome") for x in d] == ["PIX", "Cartão Elo"], (st, d))

st, hd, cupom = dono.get(f"/app/pdv/cupom/{vid}") if False else (0, {}, "")
st, hd, show = dono.get(f"/app/vendas/{vid}")
check("29. tela da venda mostra 'Cartão Elo'", st == 200 and "Cartão Elo" in show, st)

# cupom não fiscal
rotas = subprocess.run(["docker", "exec", APP, "php", "artisan", "route:list", "--name=recibo"], capture_output=True, text=True).stdout
st, hd, rec = dono.get(f"/app/vendas/{vid}/recibo")
check("30. recibo/cupom mostra 'Cartão Elo'", st == 200 and "Cartão Elo" in rec, (st, rotas[:200]))

st, hd, rel = dono.get("/app/relatorios/vendas")
check("31. relatório de vendas: 'Cartão Elo' em linha própria", st == 200 and "<td>Cartão Elo</td>" in rel, st)

# ---------------------------------------------------------------- balcão
st, hd, bc = dono.get("/app/vendas/create")
opts = re.findall(r'<option value="([^"]*)"[^>]*>([^<]*)</option>', bc.split('name="forma_pagamento"')[1].split("</select>")[0])
check("32. venda balcão lista as formas ligadas, na ordem, sem Boleto", [o[1] for o in opts[1:]] == ["PIX", "Dinheiro", "Cartão Elo", "Cartão", "Débito", "Crediario", "Transf.", "Vale"], opts)
st, hd, b = dono.post("/app/vendas", {"itens[0][produto_id]": "3", "itens[0][quantidade]": "1", "itens[0][preco_unitario]": "299.90", "forma_pagamento": CH_ELO})
d = json.loads(sql("SELECT pagamento_detalhes FROM vendas ORDER BY id DESC LIMIT 1")[0][0])[0]
tp = sql("SELECT tipo, forma_pagamento FROM vendas ORDER BY id DESC LIMIT 1")[0]
check("33. balcão com 'Cartão Elo' grava cartao_credito + nome", tp == ["balcao", "cartao_credito"] and d.get("forma_nome") == "Cartão Elo", (st, tp, d))
n_antes = sql("SELECT COUNT(*) FROM vendas")[0][0]
st, hd, b = dono.post("/app/vendas", {"itens[0][produto_id]": "3", "itens[0][quantidade]": "1", "itens[0][preco_unitario]": "299.90", "forma_pagamento": "boleto"})
check("34. balcão recusa forma desligada", sql("SELECT COUNT(*) FROM vendas")[0][0] == n_antes)

# ---------------------------------------------------------------- faturar pedido
st, hd, b = dono.post("/app/pedidos", {"cliente_id": "1", "condicao_pagamento": "cartao", "itens[0][produto_id]": "3", "itens[0][quantidade]": "1", "itens[0][preco_unitario]": "299.90"})
pid = sql("SELECT id FROM pedidos ORDER BY id DESC LIMIT 1")
check("35. pedido de teste criado", bool(pid), (st, hd.get("Location")))
pid = pid[0][0]
sql(f"UPDATE pedidos SET status='confirmado' WHERE id={pid}")
st, hd, ps = dono.get(f"/app/pedidos/{pid}")
sel = ps.split('id="faturarForma"')[1].split("</select>")[0]
fopts = re.findall(r'<option value="([^"]*)"( data-forma="[^"]*")?[^>]*>([^<]*)</option>', sel)
check("36. faturar lista as formas ligadas (com Cartão Elo, sem Boleto)", [o[2] for o in fopts[1:]] == ["PIX", "Dinheiro", "Cartão Elo", "Cartão", "Débito", "Crediario", "Transf.", "Vale"], fopts)
check("37. condição 'cartão' sugere o crédito do sistema (renomeado 'Cartão')", re.search(r'value="cartao_credito"[^>]*selected', sel) is not None)
st, hd, b = dono.post(f"/app/pedidos/{pid}/faturar", {"documento": "nenhum", "forma_pagamento": "boleto"})
check("38. faturar recusa forma desligada", sql(f"SELECT status FROM pedidos WHERE id={pid}")[0][0] == "confirmado")
st, hd, b = dono.post(f"/app/pedidos/{pid}/faturar", {"documento": "nenhum", "forma_pagamento": CH_ELO, "parcelas": "3"})
vp = sql(f"SELECT forma_pagamento, pagamento_detalhes FROM vendas WHERE pedido_id={pid}") if sql("SHOW COLUMNS FROM vendas LIKE 'pedido_id'") else sql("SELECT forma_pagamento, pagamento_detalhes FROM vendas WHERE tipo='pedido' ORDER BY id DESC LIMIT 1")
d = json.loads(vp[0][1])[0] if vp else {}
check("39. faturar com 'Cartão Elo': cartao_credito em 3x + nome", vp and vp[0][0] == "cartao_credito" and d.get("parcelas") == 3 and d.get("forma_nome") == "Cartão Elo", (st, hd.get("Location"), vp))

# ---------------------------------------------------------------- aba Pedidos: condição de pagamento
st, hd, pc = dono.get("/app/pedidos/create")
selc = pc.split('id="campo-condicao_pagamento"')[1].split("</select>")[0]
grupoF = selc.split('label="Formas de pagamento"')[1].split("</optgroup>")[0]
nomesF = re.findall(r'>([^<]+)</option>', grupoF)
check("P1. pedido: condição lista as formas ligadas (com Cartão Elo, sem Boleto)", nomesF == ["PIX", "Dinheiro", "Cartão Elo", "Cartão", "Débito", "Crediario", "Transf.", "Vale"], nomesF)
check("P2. pedido: prazos 30 / 30-60 / 30-60-90 continuam", 'label="A prazo"' in selc and "30/60/90 dias" in selc)
tab = json.loads(re.search(r"const tabelaDaCondicao = (\{.*?\});", pc).group(1))
check("P3. pedido: condição 'Cartão Elo' repreça como crédito", tab.get(CH_ELO) == tab.get("cartao_credito") and CH_ELO in tab, tab)
st, hd, b = dono.post("/app/pedidos", {"cliente_id": "1", "condicao_pagamento": CH_ELO, "itens[0][produto_id]": "3", "itens[0][quantidade]": "1", "itens[0][preco_unitario]": "299.90"})
pid2, cond2 = sql("SELECT id, condicao_pagamento FROM pedidos ORDER BY id DESC LIMIT 1")[0]
check("P4. pedido salvo com a condição 'Cartão Elo' (chave da forma)", cond2 == CH_ELO, cond2)
st, hd, ps2 = dono.get(f"/app/pedidos/{pid2}")
check("P5. tela do pedido mostra 'Cartão Elo' como condição", "Cartão Elo" in ps2.split("Informações do Pedido")[1][:3000] if "Informações do Pedido" in ps2 else False)
sql(f"UPDATE pedidos SET status='confirmado' WHERE id={pid2}")
st, hd, ps2 = dono.get(f"/app/pedidos/{pid2}")
sel2 = ps2.split('id="faturarForma"')[1].split("</select>")[0]
check("P6. faturar sugere a própria 'Cartão Elo'", re.search(rf'value="{CH_ELO}"[^>]*selected', sel2) is not None)
n_ped = sql("SELECT COUNT(*) FROM pedidos")[0][0]
dono.post("/app/pedidos", {"cliente_id": "1", "condicao_pagamento": "boleto_falso", "itens[0][produto_id]": "3", "itens[0][quantidade]": "1", "itens[0][preco_unitario]": "299.90"})
check("P7. condição inexistente é recusada", sql("SELECT COUNT(*) FROM pedidos")[0][0] == n_ped)
dono.post("/app/pedidos", {"cliente_id": "1", "condicao_pagamento": "a_vista", "itens[0][produto_id]": "3", "itens[0][quantidade]": "1", "itens[0][preco_unitario]": "299.90"})
pid3, cond3 = sql("SELECT id, condicao_pagamento FROM pedidos ORDER BY id DESC LIMIT 1")[0]
check("P8. valor antigo 'a_vista' (agente IA / pedido velho) continua aceito", cond3 == "a_vista", cond3)
st, hd, pe3 = dono.get(f"/app/pedidos/{pid3}/edit")
check("P9. editar pedido antigo mostra 'À vista' em 'Anterior', selecionado", 'label="Anterior"' in pe3 and re.search(r'value="a_vista"[^>]*selected[^>]*>À vista', pe3) is not None)
sql(f"UPDATE pedidos SET status='confirmado' WHERE id={pid3}")
st, hd, ps3 = dono.get(f"/app/pedidos/{pid3}")
check("P10. pedido 'à vista' sugere Dinheiro no faturar", re.search(r'value="dinheiro"[^>]*selected', ps3.split('id="faturarForma"')[1].split("</select>")[0]) is not None)

# ---------------------------------------------------------------- relatórios e filtros cruzados
def ids_lista(url):
    st, hd, b = dono.get(url)
    return st, sorted(set(re.findall(r'/app/vendas/(\d+)"', b)))

com_elo = sorted(r[0] for r in sql(f"SELECT id FROM vendas WHERE JSON_CONTAINS(pagamento_detalhes, JSON_OBJECT('forma_chave', '{CH_ELO}'))"))
st, ids = ids_lista(f"/app/vendas?forma_pagamento={CH_ELO}")
check("R1. lista de Vendas filtrada por 'Cartão Elo' = exatamente as vendas com Elo (split incluso)", st == 200 and ids == com_elo and len(com_elo) >= 4, (ids, com_elo))
cred_sis = sorted(r[0] for r in sql("SELECT id FROM vendas WHERE JSON_CONTAINS(pagamento_detalhes, JSON_OBJECT('forma_chave','cartao_credito'))"))
st, ids = ids_lista("/app/vendas?forma_pagamento=cartao_credito")
check("R2. filtro 'Cartão' (crédito do sistema) NÃO puxa as vendas do 'Cartão Elo'", ids == cred_sis and not set(ids) & set(com_elo) - set(cred_sis), (ids, cred_sis))
st, ids = ids_lista("/app/vendas?forma_pagamento=pix")
pix = sorted(r[0] for r in sql("SELECT id FROM vendas WHERE JSON_CONTAINS(pagamento_detalhes, JSON_OBJECT('forma','pix'))"))
check("R3. filtro PIX acha a venda só-PIX e o split PIX + Elo", ids == pix and len(pix) >= 2, (ids, pix))
st, hd, lv = dono.get("/app/vendas")
optsv = re.findall(r'<option value="([^"]*)"[^>]*>([^<]*)</option>', lv.split('name="forma_pagamento"')[1].split("</select>")[0])
check("R4. filtro da lista mostra as formas da empresa, desligada marcada", ("boleto", "Boleto (desligada)") in optsv and (CH_ELO, "Cartão Elo") in optsv, optsv)
st, hd, rv = dono.get(f"/app/relatorios/vendas?forma={CH_ELO}")
linhas_rv = re.findall(r"<td>([^<]+)</td>\s*<td class=\"text-end text-muted\">(\d+)</td>", rv)
check("R5. relatório de Vendas filtrado por 'Cartão Elo': cabeçalho do impresso diz a forma", "Forma: Cartão Elo" in rv)
elo_linha = [l for l in linhas_rv if l[0] == "Cartão Elo"]
check("R6. relatório de Vendas: linha 'Cartão Elo' conta as vendas com Elo", elo_linha and int(elo_linha[0][1]) == len(com_elo), (linhas_rv, len(com_elo)))
st, hd, rp = dono.get(f"/app/relatorios/produtos?forma={CH_ELO}")
check("R7. Produtos vendidos: filtro aceita 'Cartão Elo'", st == 200 and re.search(rf'value="{CH_ELO}"[^>]*selected', rp) is not None, st)
quadro = rp.split("forma de pagamento")[-1] if False else rp
check("R8. Produtos vendidos filtrado: só a forma 'Cartão Elo' no rateio (sem PIX)", "Cartão Elo" in rp and not re.search(r"<td>PIX</td>", rp))
st, hd, rp2 = dono.get("/app/relatorios/produtos")
check("R9. Produtos vendidos sem filtro: PIX e 'Cartão Elo' em linhas separadas", re.search(r"<td>PIX</td>", rp2) and re.search(r"<td>Cartão Elo</td>", rp2))
check("R10. Produtos vendidos: renomeado aparece com o nome novo ('Cartão')", re.search(r"<td>Cartão</td>", rp2) is not None)

# ---------------------------------------------------------------- caixa
cx = sql("SELECT caixa_id FROM vendas WHERE caixa_id IS NOT NULL ORDER BY id DESC LIMIT 1")[0][0]
mv = sql(f"SELECT forma_pagamento, forma_chave, forma_nome FROM movimentacoes_caixa WHERE venda_id={vid}")
check("C1. movimentação do caixa guarda o botão (código + chave + nome)", mv and mv[0] == ["cartao_credito", CH_ELO, "Cartão Elo"], mv)
st, hd, cs = dono.get(f"/app/caixa/{cx}")
bloco = cs.split("Vendas por forma de pagamento")[1][:4000] if "Vendas por forma de pagamento" in cs else ""
check("C2. tela do caixa: 'Cartão Elo' em linha própria, separado de 'Cartão'", st == 200 and "Cartão Elo" in bloco and re.search(r">\s*Cartão\s*<", bloco) is not None, st)
elo_mov = float(sql(f"SELECT COALESCE(SUM(valor),0) FROM movimentacoes_caixa WHERE caixa_id={cx} AND forma_chave='{CH_ELO}' AND tipo='venda'")[0][0])
check("C3. valor da linha 'Cartão Elo' = soma das movimentações Elo", f"{elo_mov:,.2f}".replace(",", "X").replace(".", ",").replace("X", ".") in bloco, elo_mov)
st, hd, cf = dono.get("/app/caixa/fechar")
conf = cf.split("Conferência das demais formas")[1][:6000] if "Conferência das demais formas" in cf else ""
check("C4. fechar caixa: conferência tem campo próprio para 'Cartão Elo'", f'name="contado[{CH_ELO}]"' in conf, st)
check("C5. fechar caixa: dinheiro não vira campo de conferência (é a gaveta)", 'name="contado[dinheiro]"' not in cf)
st, hd, cp = dono.get(f"/app/caixa/{cx}/comprovante")
check("C6. comprovante do caixa lista 'Cartão Elo'", st == 200 and "Cartão Elo" in cp, st)

# ---------------------------------------------------------------- contas a receber
st, hd, crc = dono.get("/app/financeiro/contas-receber/create")
check("CR1. nova conta a receber oferece as formas da empresa (com Cartão Elo, sem Boleto)", f'value="{CH_ELO}"' in crc and 'value="boleto"' not in crc.split('forma-pagamento-cards')[1][:6000], st)
dono.post("/app/financeiro/contas-receber", {"cliente_id": "1", "descricao": "Teste Elo", "valor": "100", "parcelas": "2", "primeiro_vencimento": "2026-12-01", "forma_pagamento": CH_ELO})
cr = sql("SELECT forma_pagamento, forma_chave, forma_nome FROM contas_receber WHERE descricao='Teste Elo'")
check("CR2. conta a receber grava cartao_credito + chave + nome nas 2 parcelas", len(cr) == 2 and all(r == ["cartao_credito", CH_ELO, "Cartão Elo"] for r in cr), cr)
st, hd, cri = dono.get(f"/app/financeiro/contas-receber?forma={CH_ELO}")
n_elo_cr = int(sql(f"SELECT COUNT(*) FROM contas_receber WHERE forma_chave='{CH_ELO}'")[0][0])
check("CR3. filtro 'Cartão Elo' em Contas a Receber traz só as contas Elo", st == 200 and "Teste Elo" in cri and cri.count("Cartão Elo") >= 2 and n_elo_cr >= 2, (st, n_elo_cr))
st, hd, cri2 = dono.get("/app/financeiro/contas-receber?forma=cartao_credito")
check("CR4. filtro 'Cartão' (crédito do sistema) não traz a conta Elo", "Teste Elo" not in cri2)

# ---------------------------------------------------------------- máquina de cartão (adquirentes)
st, hd, aq = dono.get("/app/adquirentes")
check("M1. Máquinas de Cartão: 'Cartão Elo' aparece como forma com regra própria", st == 200 and f'value="{CH_ELO}"' in aq, st)
dono.post("/app/adquirentes", {"nome": "Maquina Elo", "forma": CH_ELO, "parcelas_de": "1", "parcelas_ate": "12", "taxa_percentual": "4.5", "prazo_dias": "30", "ativo": "1"})
dono.post("/app/adquirentes", {"nome": "Maquina Geral", "forma": "cartao_credito", "parcelas_de": "1", "parcelas_ate": "12", "taxa_percentual": "2", "prazo_dias": "30", "ativo": "1"})
check("M2. regra da 'Cartão Elo' gravada", sql(f"SELECT COUNT(*) FROM adquirente_taxas WHERE forma='{CH_ELO}'")[0][0] == "1")
st, hd, r = venda_pdv(dono, [{"forma": "cartao_credito", "valor": 599.90, "chave": CH_ELO, "parcelas": 2}])
vm = sql("SELECT id FROM vendas ORDER BY id DESC LIMIT 1")[0][0]
crm = sql(f"SELECT taxa_percentual, forma_nome FROM contas_receber WHERE venda_id={vm}")
check("M3. venda 'Cartão Elo' usa a taxa da máquina dela (4,5%), não a geral", st == 200 and len(crm) == 2 and all(x[0] == "4.50" and x[1] == "Cartão Elo" for x in crm), (st, crm))
st, hd, r = venda_pdv(dono, [{"forma": "cartao_credito", "valor": 599.90, "chave": "cartao_credito", "parcelas": 1}])
vm2 = sql("SELECT id FROM vendas ORDER BY id DESC LIMIT 1")[0][0]
check("M4. venda no 'Cartão' do sistema segue a regra geral (2%)", sql(f"SELECT taxa_percentual FROM contas_receber WHERE venda_id={vm2}") == [["2.00"]])
sql("DELETE FROM adquirente_taxas")

# ---------------------------------------------------------------- ordem de serviço
os_ok = sql("SHOW TABLES LIKE 'ordens_servico'")
if os_ok:
    sql("""INSERT INTO ordens_servico (empresa_id, unidade_id, cliente_id, numero, defeito_relatado, status, valor_produtos, valor_servicos, desconto, total, created_at, updated_at)
           VALUES (1, 1, 1, 9901, 'teste', 'concluida', 0, 150, 0, 150, NOW(), NOW())""")
    osid = sql("SELECT id FROM ordens_servico WHERE numero=9901")[0][0]
    st, hd, osv = dono.get(f"/app/ordens-servico/{osid}")
    check("OS1. OS concluída: converter pergunta a forma (lista da empresa, sem Vale)", st == 200 and 'name="forma_pagamento"' in osv and f'value="{CH_ELO}"' in osv and 'value="vale"' not in osv.split('id="osFormaPagamento"')[1].split("</select>")[0], st)
    dono.post(f"/app/ordens-servico/{osid}/converter-venda", {"forma_pagamento": CH_ELO})
    vo = sql("SELECT forma_pagamento, pagamento_detalhes FROM vendas WHERE observacoes LIKE 'Gerada a partir da OS #9901%'")
    check("OS2. venda da OS grava 'Cartão Elo' (antes era sempre dinheiro)", vo and vo[0][0] == "cartao_credito" and "Cartão Elo" in vo[0][1], vo)
else:
    check("OS1. tabela ordens_servico existe", False)

# ---------------------------------------------------------------- exportação e API do Gersen
st, hd, csvv = dono.get("/app/export/vendas?formato=csv")
check("E1. exportação de vendas tem a coluna com o nome ('Cartão Elo', 'PIX + Cartão Elo')", st == 200 and "forma_pagamento_nome" in csvv and "PIX + Cartão Elo" in csvv, (st, csvv[:200]))
st, hd, csvc = dono.get("/app/export/contas-receber?formato=csv")
check("E2. exportação de contas a receber tem o nome da forma", st == 200 and "Cartão Elo" in csvc, st)
tok = subprocess.run(["docker", "exec", APP, "php", "artisan", "tinker", "--execute",
                      "[$t,$c]=\\App\\Models\\IntegracaoToken::gerar(1,'teste',2); echo 'TOKEN='.$c;"], capture_output=True, text=True).stdout
m = re.search(r"TOKEN=(gsn_\w+)", tok)
if m:
    import datetime
    hoje = datetime.date.today().isoformat()
    req = urllib.request.Request(f"{BASE}/api/integracao/v1/vendas?loja_id=1&inicio={hoje}&fim={hoje}", headers={"Authorization": "Bearer " + m.group(1), "Accept": "application/json"})
    try:
        with urllib.request.urlopen(req) as rr:
            api = json.loads(rr.read().decode())
    except urllib.error.HTTPError as e:
        api = {"erro": e.code, "b": e.read().decode()[:200]}
    dados = api.get("dados", [])
    elo_api = [d for d in dados if "Cartão Elo" in (d.get("forma_pagamento_nome") or "")]
    check("G1. API do Gersen manda forma_pagamento_nome e as partes do pagamento", elo_api and elo_api[0].get("forma_pagamento") == "cartao_credito" and any(p.get("forma_nome") == "Cartão Elo" for p in elo_api[0].get("pagamentos", [])), str(api)[:300])
else:
    check("G1. token de teste do Gersen criado", False, tok[-300:])

# ---------------------------------------------------------------- regras de validação
def tenta(linhas):
    salvar(dono, linhas)
    return flash(dono)[0]

base = [dict(x, chave=(CH_ELO if x["nome"] == "Cartão Elo" else x["chave"])) for x in nova]
m = tenta(base + [{"chave": "", "nome": "pix", "tipo": "pix", "icone": "bi-qr-code", "cor": "#000000", "largura": 1, "ativo": True}])
check("40. nome repetido (PIX × pix) é recusado", "duas formas" in m, m[:200])
m = tenta([dict(x, ativo=False) for x in base])
check("41. todas desligadas é recusado", "pelo menos uma" in m, m[:200])
m = tenta(base + [{"chave": "", "nome": "Vale 2", "tipo": "vale", "icone": "bi-gift", "cor": "#000000", "largura": 1, "ativo": True}])
check("42. forma nova do tipo vale é recusada", "funciona" in m, m[:200])
m = tenta(base[:1] + [dict(base[1], nome="  ")] + base[2:])
check("43. nome vazio é recusado", "precisa de um nome" in m, m[:200])
check("44. nada disso mexeu no que estava gravado", [r[1] for r in sql("SELECT chave,nome FROM formas_pagamento WHERE empresa_id=1 ORDER BY ordem")] == [x["nome"] for x in base])
salvar(dono, [x for x in base if x["chave"] != "vale"])
check("45. forma do sistema que some da lista não é apagada", sql("SELECT COUNT(*) FROM formas_pagamento WHERE empresa_id=1 AND chave='vale'")[0][0] == "1")
salvar(dono, [x for x in base if x["chave"] != CH_ELO])
check("46. forma criada que sai da lista é apagada; venda antiga mantém o nome", sql(f"SELECT COUNT(*) FROM formas_pagamento WHERE chave='{CH_ELO}'")[0][0] == "0" and "Cartão Elo" in dono.get(f"/app/vendas/{vid}")[2])
st, hd, r = venda_pdv(dono, [{"forma": "cartao_credito", "valor": 599.90, "chave": CH_ELO}])
check("47. PDV aberto antes da exclusão: forma apagada é recusada", st == 422)
salvar(dono, base)  # volta a ter uma forma criada (nova chave)

# ---------------------------------------------------------------- perfis
ger = Sessao("gerente@demo.com", "gerente123")
st, hd, b = ger.get("/app/configuracoes/formas-pagamento")
check("48. gerente abre a tela", st == 200, st)
st, hd = salvar(ger, base)
check("49. gerente salva", st == 302 and "salvas" in flash(ger)[0])
for email, senha in (("vendedor@demo.com", "vendedor123"), ("caixa@demo.com", "caixa123")):
    s = Sessao(email, senha)
    st, hd, b = s.get("/app/configuracoes/formas-pagamento")
    check(f"50. {email.split('@')[0]} NÃO abre a tela", st in (302, 403) and "fpAtivas" not in b, st)
    antes = sql("SELECT GROUP_CONCAT(nome ORDER BY ordem) FROM formas_pagamento WHERE empresa_id=1")[0][0]
    salvar(s, [dict(x, nome=x["nome"] + "X") for x in base])
    check(f"51. {email.split('@')[0]} NÃO grava por POST forjado", sql("SELECT GROUP_CONCAT(nome ORDER BY ordem) FROM formas_pagamento WHERE empresa_id=1")[0][0] == antes)

# ---------------------------------------------------------------- isolamento e Credishop
out = subprocess.run(["docker", "exec", APP, "php", "artisan", "tinker", "--execute",
                      "echo json_encode(app(\\App\\Services\\FormaPagamentoService::class)->ativas(999)->pluck('nome'), JSON_UNESCAPED_UNICODE);"],
                     capture_output=True, text=True)
check("52. outra empresa (sem configuração) continua com os botões de fábrica", '["Dinheiro","Crédito","Débito","PIX","Boleto","Crediario","Transf.","Vale"]' in out.stdout, (out.stdout[-300:], out.stderr[-300:]))
sql("UPDATE empresas SET pdv_credishop=1 WHERE id=1")
st, hd, pdv = dono.get("/app/pdv")
bt = botoes_pdv(pdv)
check("53. Credishop ligado depois do save aparece no fim, linha inteira", bt and bt[-1][2] == "credishop" and "pay-largo" in bt[-1][0], bt[-2:])
st, hd, r = venda_pdv(dono, [{"forma": "credishop", "valor": 599.90, "chave": "credishop"}])
d = json.loads(sql("SELECT pagamento_detalhes FROM vendas ORDER BY id DESC LIMIT 1")[0][0])[0]
check("54. venda Credishop grava 'credishop' como sempre", st == 200 and d["forma"] == "credishop", (st, d))
sql("UPDATE empresas SET pdv_credishop=0 WHERE id=1")
st, hd, r = venda_pdv(dono, [{"forma": "credishop", "valor": 599.90, "chave": "credishop"}])
check("55. Credishop desligado no admin: some e é recusado", st == 422 and "credishop" not in dono.get("/app/pdv")[2].split('class="payment-grid"')[1][:4000])

# ---------------------------------------------------------------- restaurar
st, hd, b = dono.post("/app/configuracoes/formas-pagamento/restaurar", {})
check("56. restaurar apaga a configuração", sql("SELECT COUNT(*) FROM formas_pagamento WHERE empresa_id=1")[0][0] == "0")
st, hd, pdv = dono.get("/app/pdv")
check("57. PDV volta exatamente aos 8 de fábrica", [x[2] for x in botoes_pdv(pdv)] == ANTIGA)
st, hd, r = venda_pdv(dono, [{"forma": "boleto", "valor": 599.90, "chave": "boleto"}])
check("58. boleto volta a funcionar", st == 200, (st, r[:200]))

log = subprocess.run(["docker", "exec", APP, "sh", "-c", "grep -c 'local.ERROR' storage/logs/laravel.log 2>/dev/null; true"], capture_output=True, text=True).stdout.strip()
check("59. laravel.log sem ERROR", log in ("0", ""), log)

print(f"\n{ok} ✅ / {falhas} ❌")
sys.exit(1 if falhas else 0)
