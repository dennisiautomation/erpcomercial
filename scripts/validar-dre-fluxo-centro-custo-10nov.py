#!/usr/bin/env python3
"""Validação ✅/❌ — DRE com Despesas com Pessoal e competência, fluxo programado e relatório por
centro de custo (10/11/2026), Fase 3. Tudo LÊ o Contas a Pagar.

Cenário (março/2029, empresa demo, 2 lojas, centros ADM e LOJA):
  conta                     plano     centro  valor   vence       competência  situação
  Salário Carlos            3.1.01    ADM     2000    05/04/2029  03/2029      paga 05/04 (2000)
  Vale Carlos               3.1.02    ADM     1000    20/03/2029  03/2029      paga 20/03 (1000)
  Guia INSS                 3.2.01    -        700    20/04/2029  03/2029      PENDENTE
  Vale-transporte           3.3.01    LOJA     200    01/03/2029  03/2029      paga 01/03 (210)
  Aluguel                   3.4.01    LOJA    3000    10/03/2029  -            paga 10/03
  Compra de mercadoria      2.1       LOJA    5000    15/03/2029  -            paga 15/03   (custo)
  Diversos (sem plano)      -         "Antigo" 150    12/03/2029  -            paga 12/03
  Ajuda de custo Ana        -         -        300    25/03/2029  -            paga 25/03   (funcionário)
  Energia (loja 2)          3.4.02    -        400    18/03/2029  -            paga 18/03
  Cancelada                 3.4.01    -        999    10/03/2029  03/2029      CANCELADA
  Aluguel fevereiro         3.4.01    LOJA    2800    10/02/2029  -            paga 10/02
  Receitas: 20000 recebida em 08/03 · 4000 lançada em 28/03, pendente (vence 28/04)

  CAIXA mar/2029:        receita 20000 · CMV 5000 · despesas 5060 (pessoal 1510) · resultado  9940
  COMPETÊNCIA mar/2029:  receita 24000 · CMV 5000 · despesas 7760 (pessoal 4210) · resultado 11240

Ambiente DESCARTÁVEL:
    BASE=http://172.17.0.x:8299 MYSQL=fp-mysql APP=fp-app python3 scripts/validar-dre-fluxo-centro-custo-10nov.py
"""
import html as htmlmod, http.cookiejar, os, re, subprocess, sys, urllib.error, urllib.parse, urllib.request

BASE = os.environ.get("BASE", "http://127.0.0.1:8299")
MYSQL = os.environ.get("MYSQL", "fp-mysql")
APP = os.environ.get("APP", "fp-app")
OK = FAIL = 0


def check(nome, cond, obtido=""):
    global OK, FAIL
    OK, FAIL = (OK + 1, FAIL) if cond else (OK, FAIL + 1)
    print(("✅ " if cond else "❌ ") + nome + ("" if cond else f"  (obtido: {str(obtido)[:500]})"))


def sql(q):
    r = subprocess.run(["docker", "exec", "-i", MYSQL, "mysql", "--default-character-set=utf8mb4", "-uerp", "-perp", "erp_teste", "-N", "-B"],
                       input=q, capture_output=True, text=True)
    if r.returncode:
        print("SQL ERRO:", r.stderr.strip()[-400:])
    return r.stdout.strip()


def tinker(php):
    return subprocess.run(["docker", "exec", APP, "php", "/var/www/artisan", "tinker", "--execute", php], capture_output=True, text=True).stdout


class Sessao:
    def __init__(self, email, senha):
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        h = self.get("/login")[1]
        self.post("/login", {"_token": self.tok(h), "email": email, "password": senha})
        h = self.get("/selecionar-unidade")[1]
        self.post("/selecionar-unidade", {"_token": self.tok(h), "unidade_id": 1})
        self.token = self.tok(self.get("/app/dashboard")[1])

    def get(self, p):
        try:
            with self.op.open(BASE + p) as r:
                return r.status, r.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", "replace")

    def post(self, p, campos):
        c = dict(campos)
        c.setdefault("_token", getattr(self, "token", ""))
        try:
            with self.op.open(urllib.request.Request(BASE + p, data=urllib.parse.urlencode(c).encode())) as r:
                return r.status, r.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", "replace")

    @staticmethod
    def tok(h):
        m = re.search(r'name="_token" value="([^"]+)"', h) or re.search(r'name="csrf-token" content="([^"]+)"', h)
        return m.group(1) if m else ""


def texto(h):
    return re.sub(r"\s+", " ", htmlmod.unescape(re.sub(r"<[^>]+>", " ", h))).strip()


def tabela(h, ident):
    """Linhas de uma <table id=...>: lista de (atributos do <tr>, [células em texto])."""
    if f'id="{ident}"' not in h:
        return []
    corpo = h.split(f'id="{ident}"')[1].split("</table>")[0]
    corpo = corpo.split("<tbody>")[1] if "<tbody>" in corpo else corpo
    out = []
    for attrs, tr in re.findall(r"<tr([^>]*)>(.*?)</tr>", corpo, re.S):
        out.append((attrs, [texto(td) for td in re.findall(r"<td[^>]*>(.*?)</td>", tr, re.S)]))
    return out


def dre(s, extra=""):
    st, h = s.get("/app/dre?data_inicio=2029-03-01&data_fim=2029-03-31" + extra)
    linhas = {}
    for attrs, cels in tabela(h, "tabelaDre"):
        m = re.search(r'data-chave="([^"]+)"', attrs)
        if m and len(cels) >= 4:
            linhas[m.group(1)] = cels  # [rótulo, valor, %, anterior]
    return st, h, linhas


def main():
    # ---- cenário
    sql("delete from contas_pagar; delete from contas_receber where descricao like 'F3 %'; delete from folha_itens; delete from funcionario_fichas;"
        "delete from plano_contas; delete from centros_custo;")
    if sql("select count(*) from unidades where empresa_id=1") == "1":
        tinker("$u=App\\Models\\Unidade::withoutGlobalScopes()->find(1)->replicate();$u->nome='Loja Dois';$u->cnpj='12345678000271';$u->save();")
    lojas = sql("select group_concat(id order by id) from unidades where empresa_id=1").split(",")
    L1, L2 = lojas[0], lojas[1] if len(lojas) > 1 else ""
    s = Sessao("dono@demo.com", "dono123")
    s.post("/app/plano-contas/padrao", {})
    pid = lambda cod: sql(f"select id from plano_contas where empresa_id=1 and codigo='{cod}'")
    P = {c: pid(c) for c in ("3.1.01", "3.1.02", "3.2.01", "3.3.01", "3.4.01", "3.4.02", "2.1", "3.1", "3.2", "3.3", "3.4")}
    sql("insert into centros_custo (empresa_id,codigo,nome,ativo,created_at,updated_at) values (1,'ADM','Administrativo',1,now(),now()),(1,'LOJA','Loja',1,now(),now())")
    ADM, LOJA = sql("select id from centros_custo where codigo='ADM'"), sql("select id from centros_custo where codigo='LOJA'")
    ids = dict(l.split("\t") for l in sql("select email,id from users").split("\n"))
    CARLOS, ANA = ids["vendedor@demo.com"], ids["caixa@demo.com"]
    check("preparo: plano de contas, 2 centros de custo e 2 lojas", all(P.values()) and ADM and LOJA and L2, (P, ADM, LOJA, lojas))

    def cp(desc, valor, venc, status, pago_em=None, valor_pago=0, plano=None, cc=None, cc_txt=None, comp=None, func=None, fav=None, forn=None, loja=None, cat=None, folha=None):
        n = lambda v: "null" if v in (None, "") else (f"'{v}'" if not str(v).replace(".", "").isdigit() or "-" in str(v) else str(v))
        sql("insert into contas_pagar (empresa_id,unidade_id,fornecedor_id,funcionario_id,favorecido_nome,descricao,valor,valor_pago,vencimento,competencia,pago_em,"
            "plano_conta_id,categoria,centro_custo_id,centro_custo,parcela,total_parcelas,folha_ref,status,created_at,updated_at) values "
            f"(1,{loja or L1},{n(forn)},{n(func)},{n(fav)},'{desc}',{valor},{valor_pago},'{venc}',{n(comp)},{n(pago_em)},{n(plano)},{n(cat)},{n(cc)},{n(cc_txt)},1,1,{n(folha)},'{status}',now(),now())")

    cp("F3 Salário Carlos", 2000, "2029-04-05", "paga", "2029-04-05", 2000, P["3.1.01"], ADM, "Administrativo", "2029-03-01", func=CARLOS, cat="Salários", folha="salario")
    cp("F3 Vale Carlos", 1000, "2029-03-20", "paga", "2029-03-20", 1000, P["3.1.02"], ADM, "Administrativo", "2029-03-01", func=CARLOS, cat="Adiantamento", folha="vale")
    cp("F3 Guia INSS", 700, "2029-04-20", "pendente", plano=P["3.2.01"], comp="2029-03-01", fav="INSS", cat="INSS")
    cp("F3 Vale-transporte", 200, "2029-03-01", "paga", "2029-03-01", 210, P["3.3.01"], LOJA, "Loja", "2029-03-01", fav="Operadora VT", cat="Vale-transporte")
    cp("F3 Aluguel", 3000, "2029-03-10", "paga", "2029-03-10", 3000, P["3.4.01"], LOJA, "Loja", forn=1, cat="Aluguel")
    cp("F3 Compra de mercadoria", 5000, "2029-03-15", "paga", "2029-03-15", 5000, P["2.1"], LOJA, "Loja", forn=1, cat="Compra de mercadorias")
    cp("F3 Diversos", 150, "2029-03-12", "paga", "2029-03-12", 150, cc_txt="Antigo", forn=1, cat="Outro")
    cp("F3 Ajuda de custo Ana", 300, "2029-03-25", "paga", "2029-03-25", 300, func=ANA)
    cp("F3 Energia loja 2", 400, "2029-03-18", "paga", "2029-03-18", 400, P["3.4.02"], forn=1, loja=L2, cat="Energia")
    cp("F3 Cancelada", 999, "2029-03-10", "cancelada", plano=P["3.4.01"], comp="2029-03-01", forn=1)
    cp("F3 Aluguel fevereiro", 2800, "2029-02-10", "paga", "2029-02-10", 2800, P["3.4.01"], LOJA, "Loja", forn=1, cat="Aluguel")
    sql(f"insert into contas_receber (empresa_id,unidade_id,cliente_id,descricao,valor,valor_pago,vencimento,pago_em,parcela,total_parcelas,status,created_at,updated_at) values "
        f"(1,{L1},1,'F3 Venda recebida',20000,20000,'2029-03-08','2029-03-08',1,1,'paga','2029-03-08 10:00:00','2029-03-08 10:00:00'),"
        f"(1,{L1},1,'F3 Venda a prazo',4000,0,'2029-04-28',null,1,1,'pendente','2029-03-28 10:00:00','2029-03-28 10:00:00')")
    check("preparo: 11 contas a pagar e 2 a receber", sql("select count(*) from contas_pagar") == "11" and sql("select count(*) from contas_receber where descricao like 'F3 %'") == "2")

    # ---- A. DRE por caixa (padrão)
    st, h, d = dre(s)
    check("A1 DRE abre no regime de caixa por padrão, com o aviso", st == 200 and "Por caixa:" in h and 'value="pagamento" selected' in h, st)
    v = lambda chave, col=1: d.get(chave, ["", "", "", ""])[col]
    check("A2 receita bruta 20.000,00 (só o recebido)", v("receita") == "R$ 20.000,00", v("receita"))
    check("A3 CMV 5.000,00 e lucro bruto 15.000,00", v("cmv") == "(R$ 5.000,00)" and v("lucro_bruto") == "R$ 15.000,00", (v("cmv"), v("lucro_bruto")))
    check("A4 despesas operacionais 5.060,00 (salário pago em abril e INSS pendente ficam fora)", v("despesas") == "(R$ 5.060,00)", v("despesas"))
    check("A5 linha 'Despesas com Pessoal' = 1.510,00 (vale 1000 + VT pago 210 + ajuda de custo 300)", d.get("pessoal", [""])[0] == "Despesas com Pessoal" and v("pessoal") == "(R$ 1.510,00)", d.get("pessoal"))
    check("A6 pessoal aberto por grupo: salários 1.000,00 e benefícios 210,00, com a conta embaixo",
          v(f"pessoal.g{P['3.1']}") == "(R$ 1.000,00)" and v(f"pessoal.c{P['3.1.02']}") == "(R$ 1.000,00)" and v(f"pessoal.g{P['3.3']}") == "(R$ 210,00)" and v(f"pessoal.c{P['3.3.01']}") == "(R$ 210,00)", {k: c[1] for k, c in d.items() if k.startswith("pessoal")})
    check("A7 pago a funcionário sem plano de contas entra em Pessoal (300,00), não em 'sem classificação'", v("pessoal.sem") == "(R$ 300,00)" and d.get("pessoal.sem", [""])[0] == "Pessoal sem plano de contas", d.get("pessoal.sem"))
    check("A8 encargos não aparecem (INSS ainda não foi pago)", f"pessoal.g{P['3.2']}" not in d)
    check("A9 grupo Ocupação 3.400,00 = aluguel 3.000,00 + energia 400,00", v(f"despesas.g{P['3.4']}") == "(R$ 3.400,00)" and v(f"despesas.c{P['3.4.01']}") == "(R$ 3.000,00)" and v(f"despesas.c{P['3.4.02']}") == "(R$ 400,00)", {k: c[1] for k, c in d.items() if k.startswith("despesas.")})
    check("A10 sem classificação só o que realmente não tem plano (150,00)", v("despesas.sem") == "(R$ 150,00)", v("despesas.sem"))
    check("A11 resultado líquido 9.940,00", v("resultado_liquido") == "R$ 9.940,00" and v("resultado_operacional") == "R$ 9.940,00", v("resultado_liquido"))
    check("A12 a soma dos detalhes fecha com o total das despesas", abs(1000 + 210 + 300 + 3000 + 400 + 150 - 5060) < 0.01 and len([k for k in d if k.startswith(("pessoal.c", "despesas.c", "pessoal.sem", "despesas.sem"))]) == 6, [k for k in d if ".c" in k or ".sem" in k])
    check("A13 período anterior alinhado pela LINHA: aluguel de fevereiro (2.800,00) na linha do aluguel", v(f"despesas.c{P['3.4.01']}", 3) == "(R$ 2.800,00)" and v(f"despesas.c{P['3.4.02']}", 3) == "-", (v(f"despesas.c{P['3.4.01']}", 3), v(f"despesas.c{P['3.4.02']}", 3)))
    check("A14 período anterior: linha de pessoal sem movimento mostra '-', não o valor de outra linha", v("pessoal", 3) == "-" and v("despesas", 3) == "(R$ 2.800,00)", (v("pessoal", 3), v("despesas", 3)))
    cards = texto(h.split('id="dreCards"')[1].split("{{-- DRE")[0][:6000]) if 'id="dreCards"' in h else ""
    check("A15 cartão 'Despesas com Pessoal' com o valor e o % da receita (7,6%)", "1.510,00" in cards and "Despesas com Pessoal (7,6% da receita)" in cards, cards[:300])
    check("A16 conta cancelada nunca entra", "999" not in texto(h.split('id="tabelaDre"')[1].split("</table>")[0]))

    # ---- B. DRE por competência
    st, h, d = dre(s, "&regime=competencia")
    check("B1 regime competência selecionado, com a explicação", st == 200 and "Por competência:" in h and 'value="competencia" selected' in h, st)
    check("B2 receita 24.000,00 (inclui a venda a prazo lançada em março)", v("receita") == "R$ 24.000,00", v("receita"))
    check("B3 despesas 7.760,00 e pessoal 4.210,00 (salário de março e INSS entram, mesmo pagos/vencendo em abril)", v("despesas") == "(R$ 7.760,00)" and v("pessoal") == "(R$ 4.210,00)", (v("despesas"), v("pessoal")))
    check("B4 salários 3.000,00 (salário 2.000 + vale 1.000), encargos 700,00, benefícios 210,00",
          v(f"pessoal.g{P['3.1']}") == "(R$ 3.000,00)" and v(f"pessoal.c{P['3.1.01']}") == "(R$ 2.000,00)" and v(f"pessoal.g{P['3.2']}") == "(R$ 700,00)" and v(f"pessoal.g{P['3.3']}") == "(R$ 210,00)", {k: c[1] for k, c in d.items() if k.startswith("pessoal")})
    check("B5 conta sem competência entra pelo vencimento (aluguel 3.000,00) e a de fevereiro não entra", v(f"despesas.c{P['3.4.01']}") == "(R$ 3.000,00)", v(f"despesas.c{P['3.4.01']}"))
    check("B6 resultado por competência 11.240,00", v("resultado_liquido") == "R$ 11.240,00", v("resultado_liquido"))
    check("B7 botões Por Unidade e Exportar levam o regime", h.count("regime=competencia") >= 2)

    # ---- C. filtro de loja, por unidade e exportação
    st, h, d = dre(s, f"&unidade_id={L2}")
    check("C1 DRE só da loja 2: despesas 400,00 (energia) e nada de pessoal", v("despesas") == "(R$ 400,00)" and "pessoal" not in d and v("receita") == "R$ 0,00", (v("despesas"), list(d)))
    st, h = s.get("/app/dre/por-unidade?data_inicio=2029-03-01&data_fim=2029-03-31")
    pu = {re.search(r'data-chave="([^"]+)"', a).group(1): c for a, c in tabela(h, "tabelaDreUnidades") if "data-chave" in a}
    check("C2 por unidade abre com as duas lojas em colunas", st == 200 and "Loja Dois" in h and len(pu.get("despesas", [])) == 3, (st, pu.get("despesas")))
    check("C3 por unidade: despesas 4.660,00 na loja 1 e 400,00 na loja 2", pu.get("despesas", ["", "", ""])[1:] == ["(R$ 4.660,00)", "(R$ 400,00)"], pu.get("despesas"))
    check("C4 por unidade alinha pela linha: energia só na coluna da loja 2, aluguel só na da loja 1",
          pu.get(f"despesas.c{P['3.4.02']}", ["", "", ""])[1:] == ["R$ 0,00", "(R$ 400,00)"] and pu.get(f"despesas.c{P['3.4.01']}", ["", "", ""])[1:] == ["(R$ 3.000,00)", "R$ 0,00"], (pu.get(f"despesas.c{P['3.4.02']}"), pu.get(f"despesas.c{P['3.4.01']}")))
    check("C5 por unidade: pessoal 1.510,00 só na loja 1", pu.get("pessoal", ["", "", ""])[1:] == ["(R$ 1.510,00)", "R$ 0,00"], pu.get("pessoal"))
    st, h = s.get("/app/dre/por-unidade?data_inicio=2029-03-01&data_fim=2029-03-31&regime=competencia")
    pu = {re.search(r'data-chave="([^"]+)"', a).group(1): c for a, c in tabela(h, "tabelaDreUnidades") if "data-chave" in a}
    check("C6 por unidade em competência: pessoal 4.210,00 na loja 1", pu.get("pessoal", ["", "", ""])[1:] == ["(R$ 4.210,00)", "R$ 0,00"], pu.get("pessoal"))
    st, csv = s.get("/app/dre/exportar?data_inicio=2029-03-01&data_fim=2029-03-31")
    check("C7 exportação CSV traz 'Despesas com Pessoal' e o resultado", st == 200 and 'Despesas com Pessoal";-1.510,00' in csv and '"= RESULTADO LIQUIDO";9.940,00' in csv, csv[-300:])
    st, csv = s.get("/app/dre/exportar?data_inicio=2029-03-01&data_fim=2029-03-31&regime=competencia")
    check("C8 exportação respeita o regime de competência", 'Despesas com Pessoal";-4.210,00' in csv and '"= RESULTADO LIQUIDO";11.240,00' in csv, csv[-300:])

    # ---- D. fluxo de caixa
    st, h = s.get("/app/financeiro/fluxo-caixa?data_inicio=2029-03-01&data_fim=2029-04-30")
    prog = tabela(h, "tabelaProgramado")
    t = texto(h)
    check("D1 fluxo mar–abr: saídas realizadas 12.060,00 e entradas 20.000,00", st == 200 and "12.060,00" in t and "20.000,00" in t, st)
    check("D2 Programado lista a guia do INSS (20/04/2029, 700,00, para INSS) e o total", len(prog) == 2 and prog[0][1][0].startswith("20/04/2029") and prog[0][1][1] == "INSS" and "700,00" in prog[0][1][4] and "1 conta" in prog[1][1][0], prog)
    check("D3 filtros de plano de contas e centro de custo aparecem", 'name="plano_conta_id"' in h and 'name="centro_custo_id"' in h and "fluxoAvisoFiltro" not in h)
    st, h = s.get(f"/app/financeiro/fluxo-caixa?data_inicio=2029-03-01&data_fim=2029-04-30&centro_custo_id={ADM}")
    t = texto(h)
    check("D4 filtrando o centro ADM: saídas 3.000,00 (salário + vale), nada programado, com o aviso", "R$ 3.000,00" in t and "fluxoAvisoFiltro" in h and "Nada programado" in t and "12.060,00" not in t, t[:200])
    check("D5 as entradas continuam todas com o filtro (20.000,00)", "20.000,00" in t)
    st, h = s.get(f"/app/financeiro/fluxo-caixa?data_inicio=2029-03-01&data_fim=2029-04-30&plano_conta_id={P['3.2.01']}")
    prog = tabela(h, "tabelaProgramado")
    check("D6 filtrando o plano INSS: nenhuma saída paga e 700,00 programado", len(prog) == 2 and "700,00" in prog[0][1][4] and "Saídas Realizadas" in texto(h) and "R$ 0,00" in texto(h), prog)
    sql("update contas_pagar set vencimento='2020-01-10' where descricao='F3 Guia INSS'")
    st, h = s.get("/app/financeiro/fluxo-caixa?data_inicio=2020-01-01&data_fim=2020-01-31")
    check("D7 conta programada com vencimento passado aparece marcada 'em atraso'", "em atraso" in texto(h.split('id="tabelaProgramado"')[1].split("</table>")[0]))
    sql("update contas_pagar set vencimento='2029-04-20' where descricao='F3 Guia INSS'")

    # ---- E. despesas por centro de custo
    R = "/app/financeiro/despesas-centro-custo?data_inicio=2029-03-01&data_fim=2029-03-31"

    def centros(extra=""):
        st, h = s.get(R + extra)
        linhas = tabela(h, "tabelaCentros")
        cab = {re.search(r'data-centro="([^"]*)"', a).group(1): c for a, c in linhas if "data-centro" in a}
        total = next((c for a, c in linhas if "table-dark" in a), [])
        return st, h, cab, total, linhas

    st, h, c, total, linhas = centros()
    check("E1 relatório abre (regime padrão: o que foi pago)", st == 200 and 'value="pagamento" selected' in h, st)
    check("E2 Administrativo: pessoal 1.000,00, total 1.000,00", c.get("Administrativo", [""] * 7)[1:5] == ["R$ 1.000,00", "-", "-", "R$ 1.000,00"], c.get("Administrativo"))
    check("E3 Loja: pessoal 210,00 + outras 3.000,00 + custos 5.000,00 = 8.210,00", c.get("Loja", [""] * 7)[1:5] == ["R$ 210,00", "R$ 3.000,00", "R$ 5.000,00", "R$ 8.210,00"], c.get("Loja"))
    check("E4 centro antigo em TEXTO ('Antigo') aparece com 150,00", c.get("Antigo", [""] * 7)[4] == "R$ 150,00", c.get("Antigo"))
    check("E5 'Sem centro de custo': pessoal 300,00 + outras 400,00 = 700,00", c.get("Sem centro de custo", [""] * 7)[1:5] == ["R$ 300,00", "R$ 400,00", "-", "R$ 700,00"], c.get("Sem centro de custo"))
    check("E6 total geral 10.060,00 (pessoal 1.510,00 · outras 3.550,00 · custos 5.000,00), 100%", total[1:6] == ["R$ 1.510,00", "R$ 3.550,00", "R$ 5.000,00", "R$ 10.060,00", "100,0%"], total)
    check("E7 percentual do centro Loja = 81,6%", c.get("Loja", [""] * 7)[5] == "81,6%", c.get("Loja"))
    det = [cel[0] for a, cel in linhas if "small" in a]
    check("E8 detalhe por conta do plano embaixo de cada centro", any(x.startswith("3.4.01 — Aluguel") for x in det) and any(x.startswith("2.1 — Compra de mercadorias") for x in det) and any("sem plano de contas" in x for x in det), det)
    st, h, c, total, _ = centros("&regime=competencia")
    check("E9 por competência: Administrativo 3.000,00 (salário + vale) e total 12.760,00", c.get("Administrativo", [""] * 7)[4] == "R$ 3.000,00" and total[4] == "R$ 12.760,00", (c.get("Administrativo"), total))
    check("E10 por competência: INSS pendente entra em 'Sem centro' (1.400,00) e 'Já pago' mostra só o pago (700,00)", c.get("Sem centro de custo", [""] * 7)[4] == "R$ 1.400,00" and c.get("Sem centro de custo", [""] * 7)[6] == "R$ 700,00", c.get("Sem centro de custo"))
    st, h, c, total, _ = centros("&regime=vencimento")
    check("E11 por vencimento em março: 10.060,00 (salário e INSS vencem em abril)", total[4] == "R$ 10.060,00", total)
    st, h, c, total, _ = centros(f"&unidade_id={L2}")
    check("E12 filtro de loja: loja 2 só tem a energia (400,00)", total[4] == "R$ 400,00" and list(c) == ["Sem centro de custo"], (total, list(c)))
    st, h = s.get("/app/financeiro/despesas-centro-custo?data_inicio=2031-01-01&data_fim=2031-01-31")
    check("E13 período vazio mostra o aviso, sem erro", st == 200 and "Nenhuma despesa no período" in h, st)

    # ---- F. acesso e menu
    st, h = s.get("/app/financeiro/contas-pagar")
    check("F1 menu Financeiro tem 'Despesas por Centro de Custo'", ">Despesas por Centro de Custo</a>" in h)
    vnd = Sessao("vendedor@demo.com", "vendedor123")
    st, _ = vnd.get(R)
    check("F2 vendedor não abre o relatório (mostra salário)", st in (302, 403), st)
    sql("insert into contas_pagar (empresa_id,favorecido_nome,descricao,valor,valor_pago,vencimento,pago_em,parcela,total_parcelas,status,created_at,updated_at) "
        "select id,'X','F3 DE OUTRA EMPRESA',77777,77777,'2029-03-10','2029-03-10',1,1,'paga',now(),now() from empresas where id<>1 limit 1")
    if sql("select count(*) from contas_pagar where descricao='F3 DE OUTRA EMPRESA'") == "1":
        st, h, c, total, _ = centros()
        _, _, d = dre(s)
        check("F3 conta de OUTRA empresa não entra no relatório nem no DRE", total[4] == "R$ 10.060,00" and d["despesas"][1] == "(R$ 5.060,00)", (total, d["despesas"]))
    else:
        tinker("$e=App\\Models\\Empresa::withoutGlobalScopes()->find(1)->replicate();$e->cnpj='99999999000199';$e->razao_social='Outra Empresa Ltda';$e->nome_fantasia='Outra';$e->save();")
        sql("insert into contas_pagar (empresa_id,favorecido_nome,descricao,valor,valor_pago,vencimento,pago_em,parcela,total_parcelas,status,created_at,updated_at) "
            "select id,'X','F3 DE OUTRA EMPRESA',77777,77777,'2029-03-10','2029-03-10',1,1,'paga',now(),now() from empresas where id<>1 limit 1")
        st, h, c, total, _ = centros()
        _, _, d = dre(s)
        check("F3 conta de OUTRA empresa não entra no relatório nem no DRE", sql("select count(*) from contas_pagar where descricao='F3 DE OUTRA EMPRESA'") == "1" and total[4] == "R$ 10.060,00" and d["despesas"][1] == "(R$ 5.060,00)", (total, d.get("despesas")))

    print(f"\n{OK} ✅ / {FAIL} ❌")
    sys.exit(1 if FAIL else 0)


if __name__ == "__main__":
    main()
