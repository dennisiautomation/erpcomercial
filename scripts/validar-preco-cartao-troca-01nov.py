#!/usr/bin/env python3
"""Validação ✅/❌ — "preço cadastrado = cartão" com preço fixo no cartão + troca (01/11/2026).

Caso real: STILO VINTE ligou "preço do cartão" e os produtos antigos (preço cadastrado = PIX,
preço FIXO no cartão +10%) passaram a aparecer com o PIX no campo do cartão, e a troca de uma peça
paga em dinheiro dava "Pagamento insuficiente" (tela no preço do dinheiro, servidor no do cartão).

Ambiente DESCARTÁVEL (nunca produção): banco de demonstração próprio.
    BASE=http://127.0.0.1:8197 DB_CT=pct-mysql APP_CT=pct-depois python3 scripts/validar-preco-cartao-troca-01nov.py
"""
import http.cookiejar
import json
import os
import re
import subprocess
import sys
import urllib.parse
import urllib.request

BASE = os.environ.get("BASE", "http://127.0.0.1:8197")
DB_CT = os.environ.get("DB_CT", "pct-mysql")
OK = FAIL = 0

# Produto "clássico" (como os 515 da STILO): cadastrado = PIX, fixo no cartão
A_ID, A_PIX, A_CARTAO = 3, 25.00, 27.50
# Produto cadastrado já na regra do cartão: cadastrado = cartão, fixo no PIX
B_ID, B_PIX, B_CARTAO = 2, 30.00, 33.00


def check(nome, cond, obtido=""):
    global OK, FAIL
    if cond:
        OK += 1
        print(f"✅ {nome}")
    else:
        FAIL += 1
        print(f"❌ {nome}  (obtido: {obtido})")


def sql(q):
    r = subprocess.run(["docker", "exec", DB_CT, "mysql", "-uerp", "-perp", "erp_teste", "-N", "-B", "-e", q],
                       capture_output=True, text=True)
    return r.stdout.strip()


class Sessao:
    def __init__(self, email, senha):
        self.cj = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cj))
        html = self.get("/login")
        self.post_form("/login", {"_token": self.token(html), "email": email, "password": senha})

    def req(self, path, data=None, headers=None, method=None):
        r = urllib.request.Request(BASE + path, data=data, headers=headers or {}, method=method)
        try:
            with self.op.open(r) as resp:
                return resp.status, resp.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", "replace")

    def get(self, path):
        return self.req(path)[1]

    def code(self, path):
        return self.req(path)[0]

    @staticmethod
    def token(html):
        m = re.search(r'name="_token" value="([^"]+)"', html) or re.search(r'name="csrf-token" content="([^"]+)"', html)
        return m.group(1) if m else ""

    def post_form(self, path, campos):
        return self.req(path, urllib.parse.urlencode(campos, doseq=True).encode())

    def post_json(self, path, corpo):
        tok = self.token(self.get("/app/pdv"))
        st, txt = self.req(path, json.dumps(corpo).encode(), {
            "Content-Type": "application/json", "Accept": "application/json",
            "X-Requested-With": "XMLHttpRequest", "X-CSRF-TOKEN": tok})
        try:
            return st, json.loads(txt)
        except ValueError:
            return st, {"_raw": txt[:300]}


def preparar(preco_base):
    sql("delete from produto_precos; delete from vales; delete from devolucao_itens; delete from devolucoes;")
    sql(f"update produtos set preco_venda={A_PIX}, preco_custo=0, markup=0, status='ativo' where id={A_ID}")
    sql(f"update produtos set preco_venda={B_CARTAO}, preco_custo=0, markup=0, status='ativo' where id={B_ID}")
    sql("insert into produto_precos (empresa_id, produto_id, modalidade, valor, created_at, updated_at) values "
        f"(1,{A_ID},'debito',{A_CARTAO},now(),now()),(1,{A_ID},'credito',{A_CARTAO},now(),now()),"
        f"(1,{B_ID},'dinheiro_pix',{B_PIX},now(),now())")
    if not sql("select id from configuracoes_loja where unidade_id=1"):
        sql("insert into configuracoes_loja (empresa_id, unidade_id, created_at, updated_at) values (1,1,now(),now())")
    sql(f"update configuracoes_loja set preco_base='{preco_base}', desconto_avista_percentual=0, "
        "regra_preco_split='cartao_maior', percentual_debito=0, percentual_credito=0, troca_prazo_dias=30, troca_sobra='vale'")
    sql("update estoques set quantidade=100") if sql("show tables like 'estoques'") else None
    sql("update estoque_saldos set quantidade=100") if sql("show tables like 'estoque_saldos'") else None


def precos_pdv(s, pid):
    st, d = s.req(f"/app/pdv/produto/PROD00{pid}", headers={"Accept": "application/json"})
    try:
        d = json.loads(d)
        d = d[0] if isinstance(d, list) else d.get("produto", d)
        p = d.get("precos") or {}
        return {k: round(float(p[k]), 2) for k in ("dinheiro_pix", "debito", "credito")}
    except Exception as e:  # noqa
        return {"erro": str(e)}


def linha_lista(s, codigo):
    html = s.get("/app/produtos?busca=" + codigo)
    i = html.find(f">{codigo}<")  # a célula da tabela, não o campo de busca
    trecho = re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", html[i:i + 6000])) if i >= 0 else ""
    return trecho[:300]


def campos_edicao(s, pid):
    html = s.get(f"/app/produtos/{pid}/edit")
    def val(nome):
        m = re.search(rf'<input[^>]*name="{nome}"[^>]*>', html)
        if not m:
            return None
        v = re.search(r'value="([^"]*)"', m.group(0))
        return v.group(1) if v else ""
    return {"preco_venda": val("preco_venda"), "preco_pix": val("preco_pix"), "preco_cartao": val("preco_cartao")}


def num(v):
    try:
        return round(float(str(v).replace(",", ".")), 2)
    except (TypeError, ValueError):
        return None


def precos_banco(pid):
    base = num(sql(f"select preco_venda from produtos where id={pid}"))
    ov = dict(l.split("\t") for l in sql(f"select modalidade, valor from produto_precos where produto_id={pid}").splitlines() if l)
    return base, {k: num(v) for k, v in ov.items()}


def salvar_edicao(s, pid, campos):
    html = s.get(f"/app/produtos/{pid}/edit")
    form = {}
    for m in re.finditer(r'<input[^>]*>', html):
        tag = m.group(0)
        n = re.search(r'name="([^"]+)"', tag)
        if not n or 'type="file"' in tag or 'type="checkbox"' in tag or 'type="radio"' in tag:
            continue
        v = re.search(r'value="([^"]*)"', tag)
        form[n.group(1)] = v.group(1) if v else ""
    for m in re.finditer(r'<select[^>]*name="([^"]+)"[^>]*>(.*?)</select>', html, re.S):
        sel = re.search(r'<option[^>]*value="([^"]*)"[^>]*selected', m.group(2)) or re.search(r'<option[^>]*value="([^"]*)"', m.group(2))
        form[m.group(1)] = sel.group(1) if sel else ""
    for m in re.finditer(r'<textarea[^>]*name="([^"]+)"[^>]*>(.*?)</textarea>', html, re.S):
        form[m.group(1)] = m.group(2)
    um = re.search(r'name="unidade_medida"[^>]*value="([^"]+)"[^>]*checked|value="([^"]+)"[^>]*name="unidade_medida"[^>]*checked', html)
    form["unidade_medida"] = (um.group(1) or um.group(2)) if um else "UN"
    form.update(campos)
    form["_method"] = "PUT"
    return s.post_form(f"/app/produtos/{pid}", form)[0]


def venda(s, pid, preco, pagamentos, troca=None):
    corpo = {"itens": [{"produto_id": pid, "quantidade": 1, "preco_unitario": preco, "desconto_valor": 0}],
             "pagamentos": pagamentos, "tabela_precos": True, "documento": "recibo"}
    if troca:
        corpo["troca_devolucao_id"] = troca
    return s.post_json("/app/pdv/venda", corpo)


def main():
    dono = Sessao("dono@demo.com", "dono123")
    caixa = Sessao("caixa@demo.com", "caixa123")
    t = caixa.token(caixa.get("/app/caixa/abrir"))
    caixa.post_form("/app/caixa/abrir", {"_token": t, "valor_abertura": "0"})

    # ------------------------------------------------------------------ regra de sempre
    print("== 1. Loja na regra de sempre (preço cadastrado = à vista): nada muda")
    preparar("avista")
    p = precos_pdv(caixa, A_ID)
    check(f"[à vista] PDV produto clássico: PIX {A_PIX} / cartão {A_CARTAO}",
          p == {"dinheiro_pix": A_PIX, "debito": A_CARTAO, "credito": A_CARTAO}, p)
    l = linha_lista(dono, "PROD003")
    check("[à vista] lista: R$ 25,00 + Fixo Cartão R$ 27,50", "R$ 25,00" in l and "Cartão R$ 27,50" in l, l)
    c = campos_edicao(dono, A_ID)
    check("[à vista] edição clássico: à vista 25 · cartão 27,50",
          num(c["preco_venda"]) == A_PIX and num(c["preco_cartao"]) == A_CARTAO, c)
    c = campos_edicao(dono, B_ID)
    check("[à vista] edição do produto cadastrado no cartão: à vista 30 · cartão 33",
          num(c["preco_venda"]) == B_PIX and num(c["preco_cartao"]) == B_CARTAO, c)
    l = linha_lista(dono, "PROD002")
    check("[à vista] lista do produto cadastrado no cartão: R$ 30,00 + Cartão R$ 33,00",
          "R$ 30,00" in l and "R$ 33,00" in l and l.find("R$ 30,00") < l.find("R$ 33,00"), l)
    st = salvar_edicao(dono, A_ID, {})
    check(f"[à vista] salvar o clássico sem mexer: preços iguais (HTTP {st})",
          precos_pdv(caixa, A_ID) == {"dinheiro_pix": A_PIX, "debito": A_CARTAO, "credito": A_CARTAO}, precos_pdv(caixa, A_ID))
    st, d = venda(caixa, A_ID, A_PIX, [{"forma": "dinheiro", "valor": A_PIX, "parcelas": 1}])
    check("[à vista] venda no dinheiro sai a 25,00", st == 200 and d.get("success"), (st, d))

    # ------------------------------------------------------------------ regra do cartão
    print("== 2. Loja em 'preço do cartão' com produtos antigos (fixo no cartão)")
    preparar("cartao")
    p = precos_pdv(caixa, A_ID)
    check(f"[cartão] PDV produto clássico: PIX {A_PIX} / cartão {A_CARTAO} (o que se cobra não muda)",
          p == {"dinheiro_pix": A_PIX, "debito": A_CARTAO, "credito": A_CARTAO}, p)
    p = precos_pdv(caixa, B_ID)
    check(f"[cartão] PDV produto cadastrado no cartão: PIX {B_PIX} / cartão {B_CARTAO}",
          p == {"dinheiro_pix": B_PIX, "debito": B_CARTAO, "credito": B_CARTAO}, p)
    l = linha_lista(dono, "PROD003")
    check("[cartão] lista do clássico: R$ 27,50 (cartão) + Dinheiro/PIX R$ 25,00",
          "R$ 27,50" in l and "PIX R$ 25,00" in l and l.find("R$ 27,50") < l.find("R$ 25,00"), l)
    m = re.search(r'data-url="[^"]*/produtos/3/preco"[^>]*data-valor="([^"]+)"', dono.get("/app/produtos?busca=PROD003"))
    check("[cartão] lista: o preço editável inline é o do cartão (27,50)", m and num(m.group(1)) == A_CARTAO, m and m.group(1))
    c = campos_edicao(dono, A_ID)
    check("[cartão] edição do clássico: cartão 27,50 · PIX 25,00",
          num(c["preco_venda"]) == A_CARTAO and num(c["preco_pix"]) == A_PIX, c)
    st = salvar_edicao(dono, A_ID, {})
    check(f"[cartão] salvar o clássico sem mexer: preços iguais (HTTP {st})",
          precos_pdv(caixa, A_ID) == {"dinheiro_pix": A_PIX, "debito": A_CARTAO, "credito": A_CARTAO}, precos_pdv(caixa, A_ID))
    st = salvar_edicao(dono, A_ID, {"preco_venda": "30.00"})
    check(f"[cartão] trocar o cartão para 30: cartão 30 e PIX continua 25 (HTTP {st})",
          precos_pdv(caixa, A_ID) == {"dinheiro_pix": A_PIX, "debito": 30.0, "credito": 30.0}, precos_pdv(caixa, A_ID))
    sql("update configuracoes_loja set preco_base='avista'")
    p = precos_pdv(caixa, A_ID)
    check("[cartão→à vista] o mesmo produto numa loja à vista: PIX 25 / cartão 30 (lojas concordam)",
          p == {"dinheiro_pix": A_PIX, "debito": 30.0, "credito": 30.0}, p)
    preparar("cartao")
    tk = dono.token(dono.get("/app/produtos"))
    st, txt = dono.req("/app/produtos/3/preco", urllib.parse.urlencode({"preco_venda": "29.00", "_token": tk, "_method": "PATCH"}).encode(),
                       {"Accept": "application/json", "X-Requested-With": "XMLHttpRequest", "X-CSRF-TOKEN": tk})
    check(f"[cartão] preço inline 29: cartão 29, PIX 25 (HTTP {st})",
          precos_pdv(caixa, A_ID) == {"dinheiro_pix": A_PIX, "debito": 29.0, "credito": 29.0}, precos_pdv(caixa, A_ID))
    preparar("cartao")
    busca = json.loads(dono.get("/app/search/produtos?q=PROD003") or "[]")
    check("[cartão] busca de produto (orçamento/pedido/balcão) traz o preço do cartão 27,50",
          busca and num(busca[0].get("preco_venda")) == A_CARTAO, busca[:1])

    print("== 3. Troca na loja em 'preço do cartão'")
    st, d = venda(caixa, A_ID, A_PIX, [{"forma": "dinheiro", "valor": A_PIX, "parcelas": 1}])
    check("[cartão] venda original no DINHEIRO sai a 25,00", st == 200 and d.get("success"), (st, d))
    vid = int(sql("select max(id) from vendas"))
    item = int(sql(f"select id from venda_itens where venda_id={vid}"))
    st, d = caixa.post_json("/app/pdv/troca", {"venda_id": vid, "tipo": "troca", "motivo": "Tamanho / numeração",
                                               "itens": [{"venda_item_id": item, "quantidade": 1, "retorna_estoque": True}]})
    vale = (d.get("vale") or {})
    check("[cartão] troca da peça paga no dinheiro: crédito 25,00", st == 200 and vale.get("saldo") == A_PIX, (st, d))
    # Exatamente o que o PDV manda quando o crédito cobre tudo: só o vale da troca
    st, d = venda(caixa, A_ID, A_PIX, [{"forma": "vale", "valor": A_PIX, "parcelas": 1, "vale_codigo": vale.get("codigo")}],
                  troca=(d.get("devolucao") or {}).get("id"))
    check("[cartão] leva a MESMA peça só com o crédito (dinheiro → preço do dinheiro): fecha sem 'falta'",
          st == 200 and d.get("success"), (st, d.get("error") or d))

    st, d = venda(caixa, A_ID, A_CARTAO, [{"forma": "cartao_credito", "valor": A_CARTAO, "parcelas": 1}])
    check("[cartão] venda original no CARTÃO sai a 27,50", st == 200 and d.get("success"), (st, d))
    vid = int(sql("select max(id) from vendas"))
    item = int(sql(f"select id from venda_itens where venda_id={vid}"))
    st, d = caixa.post_json("/app/pdv/troca", {"venda_id": vid, "tipo": "troca", "motivo": "Tamanho / numeração",
                                               "itens": [{"venda_item_id": item, "quantidade": 1, "retorna_estoque": True}]})
    vale = (d.get("vale") or {})
    check("[cartão] troca da peça paga no cartão: crédito 27,50", st == 200 and vale.get("saldo") == A_CARTAO, (st, d))
    st, d2 = venda(caixa, A_ID, A_CARTAO, [{"forma": "vale", "valor": A_CARTAO, "parcelas": 1, "vale_codigo": vale.get("codigo")}],
                   troca=(d.get("devolucao") or {}).get("id"))
    check("[cartão] leva a MESMA peça só com o crédito (cartão → preço do cartão): fecha",
          st == 200 and d2.get("success"), (st, d2.get("error") or d2))
    tot = num(sql("select total from vendas order by id desc limit 1"))
    check("[cartão] venda da troca gravada a 27,50 (sem sobra nem falta)", tot == A_CARTAO, tot)

    st, d = caixa.post_json("/app/pdv/troca", {"tipo": "troca", "motivo": "Tamanho / numeração",
                                               "itens": [{"produto_id": A_ID, "quantidade": 1, "retorna_estoque": True}]})
    check("[cartão] troca de peça SEM venda vale o preço cadastrado = cartão (27,50)",
          st == 200 and (d.get("vale") or {}).get("saldo") == A_CARTAO, (st, d.get("vale") or d))

    print("== 4. Regressão: telas, mesmo código HTTP")
    preparar("cartao")
    for u in ["/app/produtos", f"/app/produtos/{A_ID}/edit", "/app/produtos/create", f"/app/produtos/{A_ID}",
              "/app/configuracoes", "/app/pdv", "/app/orcamentos/create", "/app/etiquetas", "/app/trocas"]:
        c1, c2 = dono.code(u), caixa.code(u)
        check(f"{u}: dono {c1} · caixa {c2}", c1 in (200, 302) and c2 in (200, 302, 403), (c1, c2))

    print(f"\nTOTAL: {OK} ✅ / {FAIL} ❌")
    sys.exit(1 if FAIL else 0)


if __name__ == "__main__":
    main()
