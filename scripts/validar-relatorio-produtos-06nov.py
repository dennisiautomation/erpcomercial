#!/usr/bin/env python3
"""Validação ✅/❌ — Relatório de PRODUTOS VENDIDOS (06/11/2026, pedido STILO).

Cenário (março/2027, empresa 1; loja 1 = sessão, Norte e Sul criadas aqui):
  V1 loja 1  PDV   total 100: P1 2×50                    pago PIX 100
  V2 loja 1  PDV   total 180 (200 − desc. 20): P1 1×120 + P2 4×20   pago PIX 90 + Crédito 90   vendedor 4
  V3 Norte   PDV   total 50: P2 2×25, SAIU DA SUL         pago dinheiro 60, troco 10
  V4 Sul     pedido total 30: P2 1×30                      pago crediário 30
  V5 loja 1  CANCELADA 999                                 (fica fora)
Ambiente DESCARTÁVEL (nunca produção):
    BASE=http://172.17.0.21:8298 python3 scripts/validar-relatorio-produtos-06nov.py
"""
import http.cookiejar, io, json, os, re, subprocess, sys, urllib.error, urllib.parse, urllib.request, zipfile

BASE = os.environ.get("BASE", "http://172.17.0.21:8298")
DB_CT = os.environ.get("DB_CT", "cs-mysql")
OK = FAIL = 0
PER = [("data_inicio", "2027-03-01"), ("data_fim", "2027-03-31")]


def check(nome, cond, obtido=""):
    global OK, FAIL
    OK, FAIL = (OK + 1, FAIL) if cond else (OK, FAIL + 1)
    print(("✅ " if cond else "❌ ") + nome + ("" if cond else f"  (obtido: {str(obtido)[:300]})"))


def sql(q):
    r = subprocess.run(["docker", "exec", "-i", DB_CT, "mysql", "-uerp", "-perp", "erp_teste", "-N", "-B"], input=q, capture_output=True, text=True)
    if r.returncode:
        print("SQL ERRO:", r.stderr.strip()[-300:])
    return r.stdout.strip()


class Sessao:
    def __init__(self, email, senha, unidade=1):
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        html = self.req("/login")[1]
        self.req("/login", {"_token": self.token(html), "email": email, "password": senha})
        html = self.req("/selecionar-unidade")[1]
        self.req("/selecionar-unidade", {"_token": self.token(html), "unidade_id": unidade})

    def req(self, path, campos=None, raw=False):
        data = urllib.parse.urlencode(campos, doseq=True).encode() if campos is not None else None
        try:
            with self.op.open(urllib.request.Request(BASE + path, data=data)) as r:
                corpo = r.read()
                return r.status, (corpo if raw else corpo.decode("utf-8", "replace")), r.headers.get("Content-Type", "")
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", "replace"), ""

    @staticmethod
    def token(html):
        m = re.search(r'name="_token" value="([^"]+)"', html) or re.search(r'name="csrf-token" content="([^"]+)"', html)
        return m.group(1) if m else ""


def brl(html, ident):
    m = re.search(rf'id="{ident}">(?:R\$ )?([\d.,]+)<', html)
    return m.group(1) if m else None


def rel(s, extra=(), path="/app/relatorios/produtos"):
    st, html, _ = s.req(path + "?" + urllib.parse.urlencode(PER + list(extra)))
    return st, brl(html, "kpi-total"), brl(html, "kpi-pecas"), html


def linhas_tabela(html, ident):
    m = re.search(rf'<table[^>]*id="{ident}".*?<tbody>(.*?)</tbody>', html, re.S)
    if not m:
        return []
    out = []
    for tr in re.findall(r"<tr>(.*?)</tr>", m.group(1), re.S):
        cels = [re.sub(r"<[^>]+>", "", c).strip() for c in re.findall(r"<td[^>]*>(.*?)</td>", tr, re.S)]
        out.append([re.sub(r"\s+", " ", c) for c in cels])
    return out


def limpar():
    sql("delete from venda_itens where venda_id in (select id from vendas where numero between 990001 and 990005)")
    sql("delete from vendas where numero between 990001 and 990005")
    sql("delete from unidade_user where user_id in (3, 4)")
    sql("insert into unidade_user (user_id, unidade_id, created_at, updated_at) values (3,1,now(),now()), (4,1,now(),now())")
    sql("delete from unidade_user where unidade_id in (select id from unidades where nome in ('Loja Norte (teste)','Loja Sul (teste)'))")
    sql("delete from unidades where empresa_id=1 and nome in ('Loja Norte (teste)','Loja Sul (teste)')")


def main():
    if os.environ.get("SO_LIMPAR"):
        limpar()
        return
    sql("delete from venda_itens where venda_id in (select id from vendas where numero between 990001 and 990005)")
    sql("delete from vendas where numero between 990001 and 990005")
    sql("delete from unidades where empresa_id=1 and nome in ('Loja Norte (teste)','Loja Sul (teste)')")
    for n in ("Norte", "Sul"):
        sql("insert into unidades (empresa_id, nome, cnpj, cep, logradouro, numero, bairro, cidade, uf, telefone, status, created_at, updated_at) "
            f"values (1, 'Loja {n} (teste)', '', '', '', '', '', '', 'SP', '', 'ativa', now(), now())")
    norte = int(sql("select id from unidades where nome='Loja Norte (teste)'"))
    sul = int(sql("select id from unidades where nome='Loja Sul (teste)'"))
    centro = sql("select nome from unidades where id=1")
    p1, p2 = 1, 2
    cat1, cat2 = sql("select categoria_id from produtos where id=1"), sql("select categoria_id from produtos where id=2")
    nome_p2 = sql("select descricao from produtos where id=2")
    sql("delete from unidade_user where user_id in (3, 4)")
    sql(f"insert into unidade_user (user_id, unidade_id, created_at, updated_at) values (3,1,now(),now()), (3,{norte},now(),now()), (4,1,now(),now())")
    pg = lambda *p: json.dumps([{"forma": f, "valor": v} for f, v in p]).replace("'", "")
    vendas = [
        (990001, 1, 3, 100, 100, 0, 0, "pix", pg(("pix", 100)), "pdv", "concluida", "10:00"),
        (990002, 1, 4, 200, 180, 20, 0, "pix", pg(("pix", 90), ("cartao_credito", 90)), "pdv", "concluida", "11:00"),
        (990003, norte, 3, 50, 50, 0, 10, "dinheiro", pg(("dinheiro", 60)), "pdv", "concluida", "12:00"),
        (990004, sul, 3, 30, 30, 0, 0, "crediario", pg(("crediario", 30)), "pedido", "concluida", "13:00"),
        (990005, 1, 3, 999, 999, 0, 0, "pix", pg(("pix", 999)), "pdv", "cancelada", "14:00"),
    ]
    for num, un, vend, sub, tot, desc, troco, fp, det, tipo, stt, hora in vendas:
        sql("insert into vendas (empresa_id, unidade_id, vendedor_id, numero, subtotal, desconto_valor, total, troco, forma_pagamento, pagamento_detalhes, status, tipo, created_at, updated_at) "
            f"values (1, {un}, {vend}, {num}, {sub}, {desc}, {tot}, {troco}, '{fp}', '{det}', '{stt}', '{tipo}', '2027-03-15 {hora}:00', '2027-03-15 {hora}:00')")
    vid = {n: int(sql(f"select id from vendas where numero={n}")) for n in range(990001, 990006)}
    itens = [(990001, p1, 2, 50, 100, "null"), (990002, p1, 1, 120, 120, "null"), (990002, p2, 4, 20, 80, "null"),
             (990003, p2, 2, 25, 50, str(sul)), (990004, p2, 1, 30, 30, "null"), (990005, p1, 1, 999, 999, "null")]
    for num, prod, q, preco, tot, origem in itens:
        sql("insert into venda_itens (venda_id, produto_id, unidade_origem_id, descricao, quantidade, preco_unitario, desconto_valor, desconto_percentual, total, created_at, updated_at) "
            f"values ({vid[num]}, {prod}, {origem}, (select descricao from produtos where id={prod}), {q}, {preco}, 0, 0, {tot}, now(), now())")
    todas = [("lojas[]", 1), ("lojas[]", norte), ("lojas[]", sul)]
    if os.environ.get("SO_PREPARAR"):  # o roteiro do navegador usa o mesmo cenário
        print(f"NORTE={norte} SUL={sul}")
        return
    try:
        d = Sessao("dono@demo.com", "dono123")
        print("== Dono — padrão = loja da sessão")
        st, tot, pecas, html = rel(d)
        check("abre (200) e soma a loja 1 = 280 (V1 100 + V2 180, cancelada fora)", st == 200 and tot == "280,00", (st, tot))
        check("peças da loja 1 = 7", pecas == "7", pecas)
        res = {l[1]: l for l in linhas_tabela(html, "tabela-resumo")}
        check("resumo: P1 qtd 3 total 208 (desconto da venda rateado: 100 + 108)", res.get(sql("select descricao from produtos where id=1"), [None] * 7)[3:7:3] == ["3", "R$ 208,00"], res)
        check("resumo: P2 qtd 4 total 72", res.get(nome_p2, [None] * 7)[3:7:3] == ["4", "R$ 72,00"], res)
        formas = {l[0]: l[1] for l in linhas_tabela(html, "tabela-formas")}
        check("formas loja 1: PIX 190 + Crédito 90 (V2 dividida meio a meio)", formas == {"PIX": "R$ 190,00", "Cartão de Crédito": "R$ 90,00"}, formas)
        check("2 fileiras de lojas na barra (venda e saída)", 'name="lojas[]"' in html and 'name="lojas_saida[]"' in html)
        check("saída: todas marcadas por padrão", all(re.search(rf'id="saida-{i}"[^>]*checked', html) for i in (1, norte, sul)))
        check("menu tem 'Produtos vendidos'", "Produtos vendidos</a>" in html)

        print("== Lojas da venda e loja de onde saiu")
        st, tot, pecas, html = rel(d, todas)
        check("3 lojas = 360 (= faturamento das 4 vendas válidas)", tot == "360,00", tot)
        st2, html_v, _ = d.req("/app/relatorios/vendas?" + urllib.parse.urlencode(PER + todas))
        fat = re.search(r'kpi-label">Faturamento</div>\s*<div class="kpi-value[^"]*">R\$ ([\d.,]+)<', html_v)
        check("bate com o Relatório de Vendas das mesmas lojas", fat and fat.group(1) == tot, (fat and fat.group(1), tot))
        saida = {l[0]: l[2] for l in linhas_tabela(html, "tabela-saida")}
        check(f"por saída: {centro} 280, Sul 80 (V3 saiu da Sul + V4), Norte fora", saida == {centro: "R$ 280,00", "Loja Sul (teste)": "R$ 80,00"}, saida)
        st, tot, pecas, _ = rel(d, todas + [("lojas_saida[]", sul)])
        check("só o que SAIU da Sul = 80, 3 peças", tot == "80,00" and pecas == "3", (tot, pecas))
        st, tot, pecas, html = rel(d, todas + [("lojas_saida[]", norte)])
        check("só o que saiu da Norte = 0 (a venda da Norte saiu da Sul)", tot == "0,00" and "Nenhum produto vendido" in html, tot)
        check("filtro de saída fica marcado só na Norte", re.search(rf'id="saida-{norte}"[^>]*checked', html) and not re.search(r'id="saida-1"[^>]*checked', html))
        st, tot, _, _ = rel(d, [("lojas[]", norte), ("lojas[]", sul)])
        check("lojas da venda Norte + Sul = 80", tot == "80,00", tot)

        print("== Forma, origem, produto, categoria, vendedor")
        st, tot, pecas, html = rel(d, [("forma", "cartao_credito")])
        check("forma Crédito (loja 1) = 90 (metade da V2)", tot == "90,00", tot)
        res = {l[1]: l for l in linhas_tabela(html, "tabela-resumo")}
        check("forma Crédito: P2 qtd 2 / R$ 36 (metade)", res.get(nome_p2, [None] * 7)[3:7:3] == ["2", "R$ 36,00"], res)
        st, tot, _, _ = rel(d, todas + [("forma", "dinheiro")])
        check("forma Dinheiro (todas) = 50 (60 pagos − 10 de troco)", tot == "50,00", tot)
        st, tot, _, _ = rel(d, todas + [("origem", "pedidos")])
        check("origem Pedidos (todas) = 30", tot == "30,00", tot)
        st, tot, pecas, _ = rel(d, todas + [("produto_id", p2)])
        check("produto P2 (todas) = 72 + 50 + 30 = 152, 7 peças", tot == "152,00" and pecas == "7", (tot, pecas))
        esperado_cat = "208,00" if cat1 != cat2 else "280,00"
        st, tot, _, _ = rel(d, [("categoria_id", cat1)])
        check(f"categoria do P1 (loja 1) = {esperado_cat}", tot == esperado_cat, (tot, cat1, cat2))
        st, tot, _, _ = rel(d, [("vendedor_id", 4)])
        check("vendedor 4 (loja 1) = 180 (V2)", tot == "180,00", tot)
        st, tot, _, _ = rel(d, [("produto_id", 999999)])
        check("produto de outra empresa/inexistente é ignorado = 280", tot == "280,00", tot)

        print("== Item a item e planilha")
        st, _, _, html = rel(d, todas + [("ver", "itens")])
        it = linhas_tabela(html, "tabela-itens")
        check("item a item: 5 linhas (cancelada fora)", len(it) == 5, len(it))
        v3 = [l for l in it if "990003" in l[1]]
        check("V3: loja da venda Norte, saiu da Sul, Dinheiro", v3 and v3[0][2] == "Loja Norte (teste)" and v3[0][3] == "Loja Sul (teste)" and v3[0][8] == "Dinheiro", v3)
        v2 = [l for l in it if "990002" in l[1]]
        check("V2: forma 'PIX + Cartão de Crédito'", v2 and v2[0][8] == "PIX + Cartão de Crédito", v2[:1])
        st, corpo, ctype = d.req("/app/relatorios/produtos?" + urllib.parse.urlencode(PER + todas + [("planilha", 1)]), raw=True)
        ok_xlsx = st == 200 and "spreadsheetml" in ctype
        n_linhas = 0
        if ok_xlsx:
            xml = zipfile.ZipFile(io.BytesIO(corpo)).read("xl/worksheets/sheet1.xml").decode()
            n_linhas = xml.count("<row")
        check("planilha .xlsx com cabeçalho + 5 itens", ok_xlsx and n_linhas == 6, (st, ctype, n_linhas))

        print("== Gerente (1 + Norte) e vendedor")
        g = Sessao("gerente@demo.com", "gerente123")
        st, tot, _, html = rel(g, [("loja", "todas")])
        check("gerente: loja=todas = só as vinculadas (280 + 50 = 330)", tot == "330,00", tot)
        st, tot, _, _ = rel(g, todas)
        check("gerente: marcar a Sul na URL não entra (330)", tot == "330,00", tot)
        check("gerente: Sul não aparece em 'Lojas da venda'", 'name="lojas[]" value="%d"' % sul not in html)
        v = Sessao("vendedor@demo.com", "vendedor123")
        st, tot, _, html = rel(v, todas)
        check("vendedor: sem acesso (403) ou preso à loja 1 (280)", st == 403 or (st == 200 and tot == "280,00"), (st, tot))
    finally:
        sql("delete from venda_itens where venda_id in (select id from vendas where numero between 990001 and 990005)")
        sql("delete from vendas where numero between 990001 and 990005")
        sql(f"delete from unidade_user where unidade_id in ({norte},{sul})")
        sql("delete from unidade_user where user_id in (3, 4)")
        sql("insert into unidade_user (user_id, unidade_id, created_at, updated_at) values (3,1,now(),now()), (4,1,now(),now())")
        sql(f"delete from unidades where id in ({norte},{sul})")
    print(f"\n{OK} ✅ / {FAIL} ❌")
    sys.exit(1 if FAIL else 0)


if __name__ == "__main__":
    main()
