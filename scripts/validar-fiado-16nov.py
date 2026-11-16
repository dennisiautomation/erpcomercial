#!/usr/bin/env python3
"""Validação ✅/❌ — Fiado / pagamento direto com a loja (16/11/2026).

Ambiente DESCARTÁVEL (banco novo com o seed demo; o script mexe à vontade):
    BASE=http://10.213.82.4 MYSQL=fi-mysql python3 scripts/validar-fiado-16nov.py

Cobre: botão renomeado (FIADO) no PDV; venda no fiado exige cliente; conta em aberto
com vencimento e parcelas; limite do cliente; split; caixa (venda e recebimento);
receber parcial e total (PDV e ficha); recusas; recibo; quem me deve; ficha;
contas a receber; cancelamento; balcão; pedido; cupom; perfis; isolamento.
"""
import datetime as DT, html as H, http.cookiejar, json, os, re, subprocess, urllib.error, urllib.parse, urllib.request

BASE = os.environ.get("BASE", "http://10.213.82.4")
MYSQL = os.environ.get("MYSQL", "fi-mysql")
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


def um(q):
    r = sql(q)
    return r[0][0] if r else None


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


class Sessao:
    def __init__(self, email, senha):
        self.cj = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cj), NoRedirect)
        self.token = None
        self.get("/login")
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

    def get_json(self, p):
        st, hd, b = self._req(urllib.request.Request(BASE + p, headers={"Accept": "application/json", "X-Requested-With": "XMLHttpRequest"}))
        try:
            return st, json.loads(b)
        except Exception:
            return st, b

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
        st, hd, b = self._req(urllib.request.Request(BASE + p, data=json.dumps(obj).encode(), headers={
            "Content-Type": "application/json", "Accept": "application/json", "X-Requested-With": "XMLHttpRequest", "X-CSRF-TOKEN": self.token}))
        try:
            j = json.loads(b)
            if isinstance(j, dict):
                j.pop("trace", None)
            return st, j
        except Exception:
            return st, {"raw": b[:300]}


def flash(body):
    m = re.findall(r'erp-alert-(danger|success)[^"]*"[^>]*>.*?<div class="flex-grow-1">(.*?)</div>', body, re.S)
    return H.unescape(" ".join(re.sub(r"<[^>]+>", "", x[1]).strip() for x in m))


def venda(s, pagamentos, cliente=None, produto=2, qtd=1, preco=599.90):
    return s.post_json("/app/pdv/venda", {
        "itens": [{"produto_id": produto, "quantidade": qtd, "preco_unitario": preco}],
        "pagamentos": pagamentos, "tabela_precos": False, "documento": "recibo", "cliente_id": cliente,
    })


def fiado(valor, venc=None, parcelas=1):
    p = {"forma": "crediario", "chave": "crediario", "nome": "FIADO", "valor": valor, "parcelas": parcelas}
    if venc:
        p["vencimento"] = venc
    return p


HOJE = DT.date.today()
iso = lambda d: d.isoformat()

# ---------------------------------------------------------------- preparo
sql("""DELETE FROM formas_pagamento; DELETE FROM fiado_recebimentos; DELETE FROM contas_receber; UPDATE clientes SET limite_credito=NULL;
DELETE FROM clientes WHERE id>1;
INSERT INTO clientes (id, empresa_id, tipo_pessoa, cpf_cnpj, nome_razao_social, status, created_at, updated_at) VALUES
 (2,1,'pf','062.456.789-09','Maria do Fiado','ativo',NOW(),NOW()),
 (3,1,'pf','158.729.340-06','João Limite','ativo',NOW(),NOW()),
 (4,1,'pf','111.222.333-96','Ana Pedido','ativo',NOW(),NOW());
UPDATE clientes SET limite_credito=1000 WHERE id=3;""")

dono = Sessao("dono@demo.com", "dono123")
# N S BORBA: renomeou o crediário para FIADO
st, hd, b = dono.get("/app/configuracoes/formas-pagamento")
fs = json.loads(re.search(r"let formas = (\[.*?\]);", b).group(1))
for f in fs:
    if f["chave"] == "crediario":
        f["nome"] = "FIADO"
st, hd, b = dono.post("/app/configuracoes/formas-pagamento", {"formas": json.dumps(fs)}, metodo="PUT")
check("1. renomear Crediário para FIADO salva", um("SELECT nome FROM formas_pagamento WHERE chave='crediario'") == "FIADO")
check("2. editor mostra o tipo 'Direto com a loja (cliente paga depois)'", "Direto com a loja (cliente paga depois)" in dono.get("/app/configuracoes/formas-pagamento")[2])

dono.post("/app/caixa/abrir", {"valor_abertura": "100"})
caixa_id = um("SELECT id FROM caixas WHERE status='aberto' AND user_id=2 ORDER BY id DESC LIMIT 1")
st, hd, pdv = dono.get("/app/pdv")
check("3. PDV tem o botão FIADO (data-forma=crediario)", 'data-forma="crediario" data-chave="crediario" data-nome="FIADO"' in pdv)
check("4. PDV tem o botão 'Receber FIADO' e o modal de recebimento", 'id="btnReceberFiado"' in pdv and "Receber FIADO" in pdv and 'id="modalReceberFiado"' in pdv)
check("5. modal de pagamento tem vencimento e parcelas do fiado", 'id="fiadoVencimento"' in pdv and 'id="fiadoParcelas"' in pdv)
check("6. modal de recebimento oferece só formas de receber (sem FIADO/Vale/Boleto)",
      all(x in pdv.split('id="rfFormas"')[1].split("</div>")[0] for x in ['data-chave="dinheiro"', 'data-chave="pix"'])
      and 'data-chave="crediario"' not in pdv.split('id="rfFormas"')[1].split("</div>")[0]
      and 'data-chave="vale"' not in pdv.split('id="rfFormas"')[1].split("</div>")[0])

# ---------------------------------------------------------------- venda no fiado
n0 = um("SELECT COUNT(*) FROM vendas")
st, r = venda(dono, [fiado(599.90)])
check("7. FIADO sem cliente é recusado (422) e a venda não nasce", st == 422 and "precisa do cliente" in r.get("error", "") and um("SELECT COUNT(*) FROM vendas") == n0, (st, r))

st, r = venda(dono, [fiado(599.90)], cliente=2)
check("8. FIADO com cliente passa", st == 200 and r.get("success"), (st, r))
v1 = um("SELECT id FROM vendas ORDER BY id DESC LIMIT 1")
c = sql(f"SELECT status, valor, valor_pago, vencimento, forma_nome, cliente_id, pago_em FROM contas_receber WHERE venda_id={v1}")
check("9. conta EM ABERTO: pendente, R$ 599,90, pago 0, sem data de pagamento", c and c[0][0] == "pendente" and c[0][1] == "599.90" and c[0][2] == "0.00" and c[0][6] == "NULL", c)
check("10. vencimento padrão = hoje + 30 dias", c and c[0][3] == iso(HOJE + DT.timedelta(days=30)), c)
check("11. conta guarda o nome FIADO e o cliente", c and c[0][4] == "FIADO" and c[0][5] == "2", c)
mv = sql(f"SELECT tipo, forma_pagamento, forma_nome, valor FROM movimentacoes_caixa WHERE venda_id={v1}")
check("12. caixa: venda entra na linha FIADO (não é dinheiro)", mv == [["venda", "crediario", "FIADO", "599.90"]], mv)

st, r = venda(dono, [fiado(899.70, iso(HOJE + DT.timedelta(days=10)), 3)], cliente=2, qtd=3, preco=299.90, produto=3)
v2 = um("SELECT id FROM vendas ORDER BY id DESC LIMIT 1")
cs = sql(f"SELECT parcela, total_parcelas, valor, vencimento, descricao FROM contas_receber WHERE venda_id={v2} ORDER BY parcela")
d0 = HOJE + DT.timedelta(days=10)
def mais_meses(d, n):
    m = d.month - 1 + n
    y, m = d.year + m // 12, m % 12 + 1
    import calendar
    return DT.date(y, m, min(d.day, calendar.monthrange(y, m)[1]))
check("13. FIADO em 3x: 3 contas de R$ 299,90", st == 200 and [x[2] for x in cs] == ["299.90"] * 3 and all(x[1] == "3" for x in cs), (st, r, cs))
check("14. vencimentos: o escolhido e +1 e +2 meses", [x[3] for x in cs] == [iso(d0), iso(mais_meses(d0, 1)), iso(mais_meses(d0, 2))], cs)
check("15. descrição 'Venda PDV #n - FIADO 1/3'", cs and cs[0][4].endswith("FIADO 1/3"), cs)

n0 = um("SELECT COUNT(*) FROM vendas")
st, r = venda(dono, [fiado(599.90, iso(HOJE - DT.timedelta(days=1)))], cliente=2)
check("16. vencimento no passado é recusado", st == 422 and "antes de hoje" in r.get("error", "") and um("SELECT COUNT(*) FROM vendas") == n0, (st, r))

# limite
st, r = venda(dono, [fiado(599.90)], cliente=3)
check("17. dentro do limite (R$ 1.000) passa", st == 200, (st, r))
n0 = um("SELECT COUNT(*) FROM vendas")
st, r = venda(dono, [fiado(599.90)], cliente=3)
check("18. passou do limite: recusado com o quanto ainda pode comprar", st == 422 and "passa do limite" in r.get("error", "") and "400,10" in r.get("error", "") and um("SELECT COUNT(*) FROM vendas") == n0, (st, r))
st, j = dono.get_json("/app/fiado/cliente/3/situacao")
check("19. situação do cliente (PDV): deve 599,90, limite 1000, pode 400,10", st == 200 and j["saldo"]["devendo"] == 599.9 and j["saldo"]["limite"] == 1000 and j["saldo"]["disponivel"] == 400.1, (st, j))

# split PIX + FIADO
st, r = venda(dono, [{"forma": "pix", "chave": "pix", "nome": "PIX", "valor": 300}, fiado(299.90)], cliente=2)
v3 = um("SELECT id FROM vendas ORDER BY id DESC LIMIT 1")
cc = sql(f"SELECT forma_pagamento, status, valor FROM contas_receber WHERE venda_id={v3} ORDER BY id")
check("20. split PIX 300 + FIADO 299,90: PIX pago, só 299,90 em aberto", st == 200 and cc == [["pix", "paga", "300.00"], ["crediario", "pendente", "299.90"]], (st, cc))

# conta antiga do crediário (PDV antes de hoje: já 'paga') não vira dívida
sql("INSERT INTO contas_receber (empresa_id, unidade_id, cliente_id, descricao, valor, valor_pago, vencimento, pago_em, forma_pagamento, parcela, total_parcelas, status, created_at, updated_at) VALUES (1,1,2,'Venda PDV #1 - Crediario (antigo)',50,50,CURDATE(),CURDATE(),'crediario',1,1,'paga',NOW(),NOW())")
st, j = dono.get_json("/app/fiado/cliente/2/situacao")
DEVE2 = round(599.90 + 899.70 + 299.90, 2)
check("21. Maria deve 599,90 + 899,70 + 299,90 (crediário antigo já pago não conta)", st == 200 and abs(j["saldo"]["devendo"] - DEVE2) < 0.005 and j["saldo"]["limite"] is None, j.get("saldo") if isinstance(j, dict) else j)
check("22. contas em aberto vêm da mais antiga para a mais nova (vencimento)", isinstance(j, dict) and [x["vencimento"] for x in j["contas"]] == sorted([x["vencimento"] for x in j["contas"]], key=lambda s: s[6:] + s[3:5] + s[:2]))

# ---------------------------------------------------------------- receber no PDV
esperado_antes = None
st, hd, fch = dono.get("/app/caixa/fechar")
m = re.search(r'Valor Esperado em Dinheiro.*?R\$ ([\d.,]+)', fch, re.S)
esperado_antes = float(m.group(1).replace(".", "").replace(",", ".")) if m else None

st, r = dono.post_json("/app/fiado/cliente/2/receber", {"valor": 100, "chave": "dinheiro"})
check("23. receber R$ 100 em dinheiro (PDV) passa e devolve o recibo", st == 200 and r.get("success") and "RECIBO DE PAGAMENTO" in r.get("recibo_html", ""), (st, str(r)[:300]))
rec = sql("SELECT numero, valor, forma_pagamento, saldo_antes, saldo_depois, caixa_id, contas FROM fiado_recebimentos ORDER BY id DESC LIMIT 1")
check("24. recebimento nº 1 gravado: 100, dinheiro, saldo antes/depois", rec and rec[0][0] == "1" and rec[0][1] == "100.00" and rec[0][2] == "dinheiro"
      and abs(float(rec[0][3]) - DEVE2) < 0.005 and abs(float(rec[0][4]) - (DEVE2 - 100)) < 0.005 and rec[0][5] == caixa_id, rec)
# a mais antiga é a de vencimento mais cedo: a 1/3 de D+10
p1 = sql(f"SELECT valor_pago, status FROM contas_receber WHERE venda_id={v2} AND parcela=1")
check("25. abateu a conta que vence primeiro (1/3, D+10): pago 100, segue em aberto", p1 == [["100.00", "pendente"]], p1)
mr = sql("SELECT tipo, forma_pagamento, valor, venda_id FROM movimentacoes_caixa WHERE tipo='recebimento' ORDER BY id DESC LIMIT 1")
check("26. caixa: entrada 'recebimento' em dinheiro, sem venda", mr == [["recebimento", "dinheiro", "100.00", "NULL"]], mr)
st, hd, fch = dono.get("/app/caixa/fechar")
m = re.search(r'Valor Esperado em Dinheiro.*?R\$ ([\d.,]+)', fch, re.S)
esp = float(m.group(1).replace(".", "").replace(",", ".")) if m else None
check("27. esperado em dinheiro na gaveta sobe R$ 100", esperado_antes is not None and esp is not None and abs(esp - esperado_antes - 100) < 0.005, (esperado_antes, esp))
check("28. fechamento mostra 'FIADO recebido de clientes' (nome da loja)", "FIADO recebido de clientes" in fch)
conf = fch.split("Conferência das demais formas")[1].split("</form>")[0] if "Conferência das demais formas" in fch else ""
check("29. conferência NÃO pede para contar o FIADO (não há o que contar)", 'name="contado[crediario]"' not in conf and 'name="contado[pix]"' in conf, conf[:200])

st, r = dono.post_json("/app/fiado/cliente/2/receber", {"valor": 99999, "chave": "pix"})
check("30. valor maior do que deve: recusado", st == 422 and "maior do que o cliente deve" in r.get("error", ""), (st, r))
for ch in ["crediario", "vale", "boleto"]:
    st, r = dono.post_json("/app/fiado/cliente/2/receber", {"valor": 10, "chave": ch})
    check(f"31. receber 'em {ch}' é recusado", st == 422, (st, r))
st, r = dono.post_json("/app/fiado/cliente/1/receber", {"valor": 10, "chave": "pix"})
check("32. cliente sem fiado: recusado", st == 422 and "não tem nada em aberto no FIADO" in r.get("error", ""), (st, r))

# PIX entra na conferência do PIX
st, r = dono.post_json("/app/fiado/cliente/2/receber", {"valor": 50, "chave": "pix", "observacoes": "pagou pelo celular"})
st, hd, fch = dono.get("/app/caixa/fechar")
mp = re.search(r'name="contado\[pix\]"[^>]*data-esperado="([\d.]+)"', fch)
pix_vendas = float(um(f"SELECT COALESCE(SUM(valor),0) FROM movimentacoes_caixa WHERE caixa_id={caixa_id} AND tipo='venda' AND forma_pagamento='pix'"))
check("33. conferência do PIX = vendas em PIX + fiado recebido em PIX", mp and abs(float(mp.group(1)) - (pix_vendas + 50)) < 0.005, (mp and mp.group(1), pix_vendas))

# ---------------------------------------------------------------- recibo, telas
rid = um("SELECT id FROM fiado_recebimentos ORDER BY id DESC LIMIT 1")
st, hd, rb = dono.get(f"/app/fiado/recibo/{rid}")
check("34. recibo imprime: cliente, valor, forma, quanto ainda deve, observação", st == 200 and "Maria do Fiado" in rb and "R$ 50,00" in rb and "PIX" in rb and "AINDA DEVE" in rb and "pagou pelo celular" in rb, st)

sql(f"UPDATE contas_receber SET vencimento=DATE_SUB(CURDATE(), INTERVAL 5 DAY) WHERE venda_id={v1}")  # Maria com conta vencida
st, hd, ix = dono.get("/app/fiado")
check("35. 'Quem me deve' abre e lista Maria e João", st == 200 and "Maria do Fiado" in ix and "João Limite" in ix, st)
check("36. menu Vendas usa o nome da loja: 'FIADO — quem me deve'", "FIADO — quem me deve" in ix and "Fiado — quem me deve" not in ix)
st, hd, ix2 = dono.get("/app/fiado?vencidos=1")
check("37. filtro 'só vencidos' mostra Maria e esconde João", "Maria do Fiado" in ix2 and "João Limite" not in ix2)
st, hd, ix3 = dono.get("/app/fiado?busca=15872934006")
check("38. busca pelo CPF sem máscara acha o João", "João Limite" in ix3 and "Maria do Fiado" not in ix3)
st, hd, fi = dono.get("/app/fiado/cliente/2")
check("39. ficha da Maria: compras, pagamentos e formulário Receber", st == 200 and "Compras no FIADO" in fi and "Pagamentos recebidos" in fi and 'id="formReceber"' in fi and "Vencida" in fi, st)

saldo_m = float(um("SELECT SUM(valor-valor_pago) FROM contas_receber WHERE cliente_id=2 AND forma_pagamento='crediario' AND status IN ('pendente','vencida')"))
st, hd, b = dono.post("/app/fiado/cliente/2/receber", {"valor": f"{saldo_m:.2f}", "chave": "cartao_debito"})
st2, hd2, fi2 = dono.follow(st, hd) if hasattr(dono, "follow") else dono.get(hd.get("Location", "").replace(BASE, ""))
check("40. receber o restante pela ficha (débito) quita tudo", um("SELECT COUNT(*) FROM contas_receber WHERE cliente_id=2 AND forma_pagamento='crediario' AND status IN ('pendente','vencida')") == "0", (st, hd.get("Location")))
check("41. ficha avisa 'Recebido ... Ainda deve R$ 0,00' e oferece o recibo", "Ainda deve R$ 0,00" in flash(fi2) and "Imprimir recibo" in fi2, flash(fi2))
pg = sql(f"SELECT status, pago_em IS NOT NULL, valor=valor_pago FROM contas_receber WHERE venda_id={v2}")
check("42. contas quitadas ficam 'paga' com data e valor pago cheio", all(x == ["paga", "1", "1"] for x in pg), pg)
check("43. Maria some do 'Quem me deve'", "Maria do Fiado" not in dono.get("/app/fiado")[2])

# ---------------------------------------------------------------- contas a receber, cliente
cj = um("SELECT id FROM contas_receber WHERE cliente_id=3 AND status='pendente' LIMIT 1")
st, hd, b = dono.post(f"/app/financeiro/contas-receber/{cj}/baixar", {})
check("44. 'Dar baixa' no fiado manda para a ficha (não baixa sem caixa)", st == 302 and "/app/fiado/cliente/3" in hd.get("Location", "") and um(f"SELECT status FROM contas_receber WHERE id={cj}") == "pendente", (st, hd.get("Location")))
st, hd, cr = dono.get("/app/financeiro/contas-receber?cliente_id=3")
check("45. lista de contas a receber: fiado tem 'Receber no caixa'", "/app/fiado/cliente/3" in cr)
st, hd, cl = dono.get("/app/clientes/3")
check("46. ficha do cliente mostra limite e 'Deve R$ 599,90'", "R$ 1.000,00" in cl and "Deve R$ 599,90" in cl, st)
st, hd, ce = dono.get("/app/clientes/3/edit")
check("47. cadastro explica o limite ('Deixe 0 para não ter limite')", "Deixe 0 para não ter limite" in ce)

# ---------------------------------------------------------------- cupom
st, hd, cup = dono.get(f"/app/pdv/venda/{v1}/recibo")
st0 = st
vj = um("SELECT id FROM vendas WHERE cliente_id=3 ORDER BY id DESC LIMIT 1")
st, hd, cup = dono.get(f"/app/pdv/venda/{vj}/recibo")
venc_j = um(f"SELECT DATE_FORMAT(vencimento,'%d/%m/%Y') FROM contas_receber WHERE venda_id={vj}")
check("48. cupom da venda no fiado: 'FIADO — a pagar na loja', vencimento e linha de assinatura", st == 200 and "a pagar na loja" in cup and venc_j in cup and "João Limite" in cup, (st, venc_j))

# ---------------------------------------------------------------- cancelamento
st, r = venda(dono, [fiado(299.90)], cliente=4, produto=3, preco=299.90)
vc = um("SELECT id FROM vendas ORDER BY id DESC LIMIT 1")
st, hd, b = dono.post(f"/app/vendas/{vc}", {}, metodo="DELETE")
check("49. cancelar venda no fiado ainda não paga: conta cancelada", um(f"SELECT status FROM contas_receber WHERE venda_id={vc}") == "cancelada" and um(f"SELECT status FROM vendas WHERE id={vc}") == "cancelada", (st, hd.get("Location")))
st, r = venda(dono, [fiado(299.90)], cliente=4, produto=3, preco=299.90)
vc2 = um("SELECT id FROM vendas ORDER BY id DESC LIMIT 1")
dono.post_json("/app/fiado/cliente/4/receber", {"valor": 50, "chave": "dinheiro"})
st, hd, b = dono.post(f"/app/vendas/{vc2}", {}, metodo="DELETE")
check("50. venda com fiado já pago em parte NÃO cancela por inteiro", um(f"SELECT status FROM vendas WHERE id={vc2}") == "concluida" and um(f"SELECT status FROM contas_receber WHERE venda_id={vc2}") == "pendente", (st, hd.get("Location")))

# troca de venda no fiado abate do que deve (regra antiga, agora com conta em aberto)
st, j = dono.get_json(f"/app/pdv/troca/venda/{vc2}")
check("51. troca da venda no fiado enxerga o que está em aberto (R$ 249,90)", st == 200 and abs(float(j.get("parcelas_abertas", j.get("venda", {}).get("parcelas_abertas", 0)) or 0) - 249.90) < 0.005, str(j)[:300])

# ---------------------------------------------------------------- balcão e pedido
n0 = um("SELECT COUNT(*) FROM vendas")
st, hd, b = dono.post("/app/vendas", {"itens[0][produto_id]": "3", "itens[0][quantidade]": "1", "itens[0][preco_unitario]": "299.90", "forma_pagamento": "crediario"})
check("52. balcão no FIADO sem cliente é recusado", um("SELECT COUNT(*) FROM vendas") == n0, (st, hd.get("Location")))
st, hd, b = dono.post("/app/vendas", {"cliente_id": "4", "itens[0][produto_id]": "3", "itens[0][quantidade]": "1", "itens[0][preco_unitario]": "299.90", "forma_pagamento": "crediario"})
vb = um("SELECT id FROM vendas ORDER BY id DESC LIMIT 1")
cb = sql(f"SELECT status, valor, vencimento, forma_nome FROM contas_receber WHERE venda_id={vb}")
check("53. balcão no FIADO com cliente: conta em aberto, vence em 30 dias", cb == [["pendente", "299.90", iso(HOJE + DT.timedelta(days=30)), "FIADO"]], cb)

st, hd, b = dono.post("/app/pedidos", {"cliente_id": "4", "condicao_pagamento": "crediario", "itens[0][produto_id]": "3", "itens[0][quantidade]": "1", "itens[0][preco_unitario]": "299.90"})
pid = um("SELECT id FROM pedidos ORDER BY id DESC LIMIT 1")
sql(f"UPDATE pedidos SET status='confirmado' WHERE id={pid}")
antes = int(um(f"SELECT COUNT(*) FROM contas_receber WHERE pedido_id={pid}"))
st, hd, b = dono.post(f"/app/pedidos/{pid}/faturar", {"documento": "nenhum", "forma_pagamento": "crediario"})
cp = sql(f"SELECT status, valor, valor_pago, vencimento FROM contas_receber WHERE pedido_id={pid}")
check("54. faturar pedido no FIADO: conta do pedido fica em aberto (não vira 'paga')", um(f"SELECT status FROM pedidos WHERE id={pid}") == "faturado"
      and cp and all(x[0] == "pendente" and x[2] == "0.00" for x in cp) and len(cp) == max(1, antes), (st, hd.get("Location"), cp, antes))
sql("INSERT INTO pedidos (empresa_id, unidade_id, numero, status, subtotal, total, created_at, updated_at) SELECT empresa_id, unidade_id, numero+1000, 'confirmado', subtotal, total, NOW(), NOW() FROM pedidos WHERE id=" + pid)
pid2 = um("SELECT id FROM pedidos ORDER BY id DESC LIMIT 1")
st, hd, b = dono.post(f"/app/pedidos/{pid2}/faturar", {"documento": "nenhum", "forma_pagamento": "crediario"})
check("55. faturar pedido SEM cliente no FIADO é recusado", um(f"SELECT status FROM pedidos WHERE id={pid2}") == "confirmado", (st, hd.get("Location")))

# ---------------------------------------------------------------- perfis e isolamento
caixa = Sessao("caixa@demo.com", "caixa123")
st, hd, fc = caixa.get("/app/fiado/cliente/3")
check("56. perfil caixa abre a ficha do cliente com o formulário Receber", st == 200 and 'id="formReceber"' in fc, st)
st, r = caixa.post_json("/app/fiado/cliente/3/receber", {"valor": 10, "chave": "dinheiro"})
check("57. perfil caixa recebe no PDV e entra no caixa DELE", st == 200 and um("SELECT c.user_id FROM fiado_recebimentos f JOIN caixas c ON c.id=f.caixa_id ORDER BY f.id DESC LIMIT 1") == "5", (st, r))
ger = Sessao("gerente@demo.com", "gerente123")
st, hd, fg = ger.get("/app/fiado/cliente/3")
check("58. gerente SEM caixa aberto: ficha pede para abrir o caixa", st == 200 and "Abrir caixa" in fg and 'id="formReceber"' not in fg, st)
st, r = ger.post_json("/app/fiado/cliente/3/receber", {"valor": 10, "chave": "dinheiro"})
check("58b. gerente SEM caixa aberto não recebe ('Abra o caixa')", st == 422 and "Abra o caixa" in r.get("error", ""), (st, r))
sql("""CREATE TEMPORARY TABLE IF NOT EXISTS t_emp SELECT * FROM empresas WHERE id=1; UPDATE t_emp SET id=99, cnpj='99.999.999/0001-99', nome_fantasia='Outra';
INSERT IGNORE INTO empresas SELECT * FROM t_emp;
INSERT IGNORE INTO clientes (id, empresa_id, tipo_pessoa, nome_razao_social, status, created_at, updated_at) VALUES (99,99,'pf','Cliente de outra empresa','ativo',NOW(),NOW());""")
st, j = dono.get_json("/app/fiado/cliente/99/situacao")
check("59. cliente de outra empresa: 404", st == 404, st)
st, r = dono.post_json("/app/fiado/cliente/99/receber", {"valor": 10, "chave": "dinheiro"})
check("60. receber de cliente de outra empresa: recusado", st in (404, 422), st)

# menu segue o nome do botão e some com o botão desligado
sql("UPDATE formas_pagamento SET nome='Conta da loja' WHERE chave='crediario'")
st, hd, ix = dono.get("/app/fiado")
check("61. renomeado para 'Conta da loja': menu e tela acompanham", "Conta da loja — quem me deve" in ix and "FIADO" not in ix)
sql("UPDATE formas_pagamento SET ativo=0 WHERE chave='crediario'")
st, hd, ix = dono.get("/app/dashboard")
check("62. botão desligado: o menu 'quem me deve' some", "quem me deve" not in ix, st)
sql("DELETE FROM formas_pagamento")
st, hd, ix = dono.get("/app/dashboard")
check("63. empresa com os botões de fábrica: menu 'Crediario — quem me deve'", "Crediario — quem me deve" in ix, st)
dono.get("/app/configuracoes/formas-pagamento")
dono.post("/app/configuracoes/formas-pagamento", {"formas": json.dumps(fs)}, metodo="PUT")  # volta o FIADO para o teste do navegador

print(f"\n{ok} ✅ / {falhas} ❌")
raise SystemExit(1 if falhas else 0)
