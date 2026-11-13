#!/usr/bin/env python3
"""Validação ✅/❌ — Relatórios → Entradas e saídas do estoque (13/11/2026).

Cenário (março/2029, empresa demo, loja 1 "Loja Centro" com Principal + Depósito, loja 2 "Loja Norte"):
  nº  quando        loja  produto  tipo           antes→depois  quem      lançado por
  m1  02/03 10:00   1     P1       entrada        0→10          dono      manual
  m2  05/03         1     P1       saida          10→7          vendedor  Venda 900
  m3  06/03         1     P2       ajuste         0→5           gerente   manual      (para CIMA)
  m4  07/03         1     P2       ajuste         5→3           gerente   manual      (para BAIXO)
  m5  08/03         1/Dep P3       transferencia  0→4           dono      Transferência 5
  m6  09/03         2     P1       entrada        0→20          dono      NF-e 7
  m7  10/03         2     P1       saida          20→18,5       dono      Venda 901
  m8  11/03         1     P1       devolucao      7→8           vendedor  Troca 3
  m9  12/03         1     P3       perda 1        0→0 (antiga, sem saldo)  dono  manual
  m10 01/04 00:30   1     P1       entrada 100    fora do período
  m11 31/03 23:50   1     P2       saida          3→2           dono      Venda 902
  m12 05/03         empresa 2      entrada 999    (não pode aparecer)
  + 150 entradas de 1 em maio/2029 na loja 1 (paginação)

  Março, lojas 1+2: entrou 40 · saiu 8,5 · saldo +31,5 · 10 movimentações · 3 produtos
  Março, só loja 1: entrou 20 · saiu 7 · 8 movimentações

Ambiente DESCARTÁVEL (banco vazio semeado; o script APAGA e recria o cenário):
    BASE=http://10.213.77.4 MYSQL=rm-mysql python3 scripts/validar-relatorio-movimentacoes-estoque-13nov.py
"""
import html as htmlmod, http.cookiejar, io, os, re, subprocess, sys, urllib.error, urllib.parse, urllib.request, zipfile

BASE = os.environ.get("BASE", "http://10.213.77.4")
MYSQL = os.environ.get("MYSQL", "rm-mysql")
OK = FAIL = 0
ROTA = "/app/relatorios/movimentacoes-estoque"
MAR = "data_inicio=2029-03-01&data_fim=2029-03-31"
TODAS = "&lojas[]=1&lojas[]=2"


def check(nome, cond, obtido=""):
    global OK, FAIL
    OK, FAIL = (OK + 1, FAIL) if cond else (OK, FAIL + 1)
    print(("✅ " if cond else "❌ ") + nome + ("" if cond else f"  (obtido: {str(obtido)[:400]})"))


def sql(q):
    r = subprocess.run(["docker", "exec", "-i", MYSQL, "mysql", "--default-character-set=utf8mb4", "-uerp", "-perp", "erp_teste", "-N", "-B"],
                       input=q, capture_output=True, text=True)
    if r.returncode:
        print("SQL ERRO:", r.stderr.strip()[-600:])
        sys.exit(2)
    return r.stdout.strip()


class Sessao:
    def __init__(self, email, senha, unidade=1):
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        h = self.get("/login")[1]
        self.post("/login", {"_token": self.tok(h), "email": email, "password": senha})
        h = self.get("/selecionar-unidade")[1]
        self.post("/selecionar-unidade", {"_token": self.tok(h), "unidade_id": unidade})

    def bruto(self, p):
        try:
            with self.op.open(BASE + p) as r:
                return r.status, r.read(), dict(r.headers)
        except urllib.error.HTTPError as e:
            return e.code, e.read(), dict(e.headers)

    def get(self, p):
        st, corpo, _ = self.bruto(p)
        return st, corpo.decode("utf-8", "replace")

    def post(self, p, campos):
        try:
            with self.op.open(urllib.request.Request(BASE + p, data=urllib.parse.urlencode(campos).encode())) as r:
                return r.status, r.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", "replace")

    @staticmethod
    def tok(h):
        m = re.search(r'name="_token" value="([^"]+)"', h) or re.search(r'name="csrf-token" content="([^"]+)"', h)
        return m.group(1) if m else ""


def texto(h):
    return re.sub(r"\s+", " ", htmlmod.unescape(re.sub(r"<[^>]+>", " ", h))).strip()


def kpi(h, ident):
    m = re.search(r'id="kpi-%s">([^<]*)<' % ident, h)
    return m.group(1).strip() if m else None


def kpis(h):
    return tuple(kpi(h, k) for k in ("entrou", "saiu", "saldo", "movimentos"))


def tabela(h, ident):
    if f'id="{ident}"' not in h:
        return []
    corpo = h.split(f'id="{ident}"')[1].split("</table>")[0]
    corpo = corpo.split("<tbody>")[1].split("</tbody>")[0] if "<tbody>" in corpo else corpo
    return [[texto(td) for td in re.findall(r"<td[^>]*>(.*?)</td>", tr, re.S)] for tr in re.findall(r"<tr[^>]*>(.*?)</tr>", corpo, re.S)]


def planilha(conteudo):
    """Linhas do sheet1.xml de um .xlsx gerado pelo App\\Support\\Planilha."""
    z = zipfile.ZipFile(io.BytesIO(conteudo))
    x = z.read("xl/worksheets/sheet1.xml").decode("utf-8")
    linhas = []
    for row in re.findall(r"<row[^>]*>(.*?)</row>", x, re.S):
        cels = []
        for c in re.findall(r"<c[^>]*?(?:/>|>.*?</c>)", row, re.S):
            m = re.search(r"<t[^>]*>(.*?)</t>", c, re.S) or re.search(r"<v>(.*?)</v>", c, re.S)
            cels.append(htmlmod.unescape(m.group(1)) if m else "")
        linhas.append(cels)
    return linhas


def cenario():
    V, T, N, D = (r"App\\Models\\Venda", r"App\\Models\\TransferenciaEstoque", r"App\\Models\\NFeEntrada", r"App\\Models\\Devolucao")
    maio = ",\n".join(
        f"(1,1,1,1,'entrada',1,{i},{i + 1},2,NULL,NULL,'carga {i}','2029-05-{(i % 28) + 1:02d} 12:00:00','2029-05-01 12:00:00')" for i in range(150))
    sql(f"""
SET FOREIGN_KEY_CHECKS=0;
DELETE FROM estoque_movimentacoes WHERE created_at >= '2029-01-01' OR empresa_id = 2;
DELETE FROM produtos WHERE id = 50; DELETE FROM estoques WHERE id IN (2,3,4); DELETE FROM unidades WHERE id IN (2,3);
DELETE FROM empresas WHERE id = 2; DELETE FROM unidade_user WHERE unidade_id IN (2,3);
CREATE TEMPORARY TABLE t AS SELECT * FROM empresas WHERE id = 1; UPDATE t SET id = 2, cnpj = '99999999000199', razao_social = 'OUTRA EMPRESA', nome_fantasia = 'OUTRA EMPRESA';
INSERT INTO empresas SELECT * FROM t; DROP TEMPORARY TABLE t;
CREATE TEMPORARY TABLE t AS SELECT * FROM unidades WHERE id = 1; UPDATE t SET id = 2, nome = 'Loja Norte'; INSERT INTO unidades SELECT * FROM t;
UPDATE t SET id = 3, nome = 'Loja Alheia', empresa_id = 2; INSERT INTO unidades SELECT * FROM t; DROP TEMPORARY TABLE t;
CREATE TEMPORARY TABLE t AS SELECT * FROM estoques WHERE id = 1; UPDATE t SET id = 2, codigo = 'DEPOSITO', nome = 'Depósito', is_padrao = 0; INSERT INTO estoques SELECT * FROM t;
UPDATE t SET id = 3, unidade_id = 2, codigo = 'PRINCIPAL', nome = 'Principal', is_padrao = 1; INSERT INTO estoques SELECT * FROM t;
UPDATE t SET id = 4, unidade_id = 3, empresa_id = 2; INSERT INTO estoques SELECT * FROM t; DROP TEMPORARY TABLE t;
CREATE TEMPORARY TABLE t AS SELECT * FROM produtos WHERE id = 1; UPDATE t SET id = 50, empresa_id = 2, descricao = 'PRODUTO DA OUTRA EMPRESA', categoria_id = NULL;
INSERT INTO produtos SELECT * FROM t; DROP TEMPORARY TABLE t;
INSERT INTO unidade_user (user_id, unidade_id) VALUES (2, 2);
INSERT INTO estoque_movimentacoes (empresa_id, unidade_id, estoque_id, produto_id, tipo, quantidade, quantidade_anterior, quantidade_posterior, user_id, origem_tipo, origem_id, observacoes, created_at, updated_at) VALUES
(1,1,1,1,'entrada',10,0,10,2,NULL,NULL,'compra inicial','2029-03-02 10:00:00','2029-03-02 10:00:00'),
(1,1,1,1,'saida',3,10,7,4,'{V}',900,NULL,'2029-03-05 11:00:00','2029-03-05 11:00:00'),
(1,1,1,2,'ajuste',5,0,5,3,NULL,NULL,'contagem','2029-03-06 09:00:00','2029-03-06 09:00:00'),
(1,1,1,2,'ajuste',2,5,3,3,NULL,NULL,'contagem <b>corrigida</b>','2029-03-07 09:00:00','2029-03-07 09:00:00'),
(1,1,2,3,'transferencia',4,0,4,2,'{T}',5,NULL,'2029-03-08 09:00:00','2029-03-08 09:00:00'),
(1,2,3,1,'entrada',20,0,20,2,'{N}',7,NULL,'2029-03-09 09:00:00','2029-03-09 09:00:00'),
(1,2,3,1,'saida',1.5,20,18.5,2,'{V}',901,NULL,'2029-03-10 09:00:00','2029-03-10 09:00:00'),
(1,1,1,1,'devolucao',1,7,8,4,'{D}',3,NULL,'2029-03-11 09:00:00','2029-03-11 09:00:00'),
(1,1,1,3,'perda',1,0,0,2,NULL,NULL,'quebrou','2029-03-12 09:00:00','2029-03-12 09:00:00'),
(1,1,1,1,'entrada',100,8,108,2,NULL,NULL,'fora do periodo','2029-04-01 00:30:00','2029-04-01 00:30:00'),
(1,1,1,2,'saida',1,3,2,2,'{V}',902,NULL,'2029-03-31 23:50:00','2029-03-31 23:50:00'),
(2,3,4,50,'entrada',999,0,999,2,NULL,NULL,'alheia','2029-03-05 09:00:00','2029-03-05 09:00:00');
INSERT INTO estoque_movimentacoes (empresa_id, unidade_id, estoque_id, produto_id, tipo, quantidade, quantidade_anterior, quantidade_posterior, user_id, origem_tipo, origem_id, observacoes, created_at, updated_at) VALUES
{maio};
SET FOREIGN_KEY_CHECKS=1;
""")


def main():
    cenario()
    dono = Sessao("dono@demo.com", "dono123")

    # ---- A. tela abre, menu, padrão ----
    st, h = dono.get(ROTA)
    check("A1 dono abre o relatório (200)", st == 200, st)
    check("A2 título e os dois botões de exportar", "Entradas e saídas do estoque" in h and 'id="btn-excel"' in h and 'id="btn-pdf"' in h)
    check("A3 link 'Entradas e saídas' no menu Relatórios", 'relatorios/movimentacoes-estoque"' in h and ">Entradas e saídas</a>" in h)
    check("A4 padrão é o mês atual (data início = dia 1)", re.search(r'name="data_inicio"[^>]*value="\d{4}-\d{2}-01"', h) is not None)
    check("A5 pílulas das 2 lojas e dos 7 tipos", h.count('name="lojas[]"') == 2 and h.count('name="tipos[]"') == 7, (h.count('name="lojas[]"'), h.count('name="tipos[]"')))

    # ---- B. totais de março ----
    st, h = dono.get(f"{ROTA}?{MAR}{TODAS}")
    check("B1 março, 2 lojas: entrou 40 · saiu 8,500 · saldo +31,500 · 10 movimentações", kpis(h) == ("40", "8,500", "+31,500", "10"), kpis(h))
    celulas = [c for tb in ("tabela-tipos", "tabela-lojas", "tabela-resumo") for l in tabela(h, tb) for c in l]
    check("B2 a outra empresa não aparece (999 / nomes)", "999" not in celulas and "OUTRA EMPRESA" not in h and "Loja Alheia" not in h, celulas)
    t = {l[0]: l for l in tabela(h, "tabela-tipos")}
    check("B3 por tipo: Ajuste 2 mov, entrou 5, saiu 2", t.get("Ajuste", [])[1:] == ["2", "5", "2"], t.get("Ajuste"))
    check("B4 por tipo: Perda antiga (sem saldo gravado) conta como saída de 1", t.get("Perda", [])[1:] == ["1", "0", "1"], t.get("Perda"))
    check("B5 por tipo: Transferência para cima conta como entrada 4", t.get("Transferência", [])[1:] == ["1", "4", "0"], t.get("Transferência"))
    lj = {l[0]: l for l in tabela(h, "tabela-lojas")}
    check("B6 por loja: Centro 8 mov 20/7 · Norte 2 mov 20/1,500", lj.get("Loja Centro", [])[1:] == ["8", "20", "7"] and lj.get("Loja Norte", [])[1:] == ["2", "20", "1,500"], lj)
    r = {l[0]: l for l in tabela(h, "tabela-resumo")}
    check("B7 resumo: PROD001 entrou 31 saiu 4,500 saldo +26,500 em 5 mov", r.get("PROD001", [])[3:] == ["31", "4,500", "+26,500", "5"], r.get("PROD001"))
    check("B8 resumo: PROD002 5/3/+2 em 3 mov · PROD003 4/1/+3 em 2 mov", r.get("PROD002", [])[3:] == ["5", "3", "+2", "3"] and r.get("PROD003", [])[3:] == ["4", "1", "+3", "2"], (r.get("PROD002"), r.get("PROD003")))
    check("B9 resumo ordenado por 'mais saiu' (PROD001 primeiro)", [l[0] for l in tabela(h, "tabela-resumo")] == ["PROD001", "PROD002", "PROD003"])
    st, h = dono.get(f"{ROTA}?{MAR}{TODAS}&ordem=nome")
    check("B10 ordenar por nome", [l[0] for l in tabela(h, "tabela-resumo")] == ["PROD002", "PROD001", "PROD003"], [l[1] for l in tabela(h, "tabela-resumo")])
    st, h = dono.get(f"{ROTA}?{MAR}")
    check("B11 sem marcar loja vale a da sessão (Centro): 20 / 7 / 8 mov", kpis(h)[:2] == ("20", "7") and kpis(h)[3] == "8", kpis(h))
    st, h = dono.get(f"{ROTA}?data_inicio=2029-03-31&data_fim=2029-03-31{TODAS}")
    check("B12 o dia final entra inteiro (31/03 23:50) e 01/04 00:30 fica fora", kpis(h) == ("0", "1", "-1", "1"), kpis(h))

    # ---- C. filtros ----
    f = lambda extra: kpis(dono.get(f"{ROTA}?{MAR}{TODAS}{extra}")[1])
    check("C1 só entradas: 40 / 0 / 5 mov", f("&sentido=entrada") == ("40", "0", "+40", "5"), f("&sentido=entrada"))
    check("C2 só saídas: 0 / 8,500 / 5 mov", f("&sentido=saida") == ("0", "8,500", "-8,500", "5"), f("&sentido=saida"))
    check("C3 tipo Ajuste: 5 / 2 / 2 mov", f("&tipos[]=ajuste") == ("5", "2", "+3", "2"), f("&tipos[]=ajuste"))
    check("C4 tipos Entrada + Saída: 30 / 5,500 / 5 mov", f("&tipos[]=entrada&tipos[]=saida") == ("30", "5,500", "+24,500", "5"), f("&tipos[]=entrada&tipos[]=saida"))
    check("C5 Ajuste + só saídas: 0 / 2 / 1 mov", f("&tipos[]=ajuste&sentido=saida") == ("0", "2", "-2", "1"), f("&tipos[]=ajuste&sentido=saida"))
    check("C6 tipo inválido é ignorado", f("&tipos[]=xxx") == ("40", "8,500", "+31,500", "10"), f("&tipos[]=xxx"))
    check("C7 lançado por Venda: 0 / 5,500 / 3 mov", f("&origem=venda") == ("0", "5,500", "-5,500", "3"), f("&origem=venda"))
    check("C8 lançado por manual: 15 / 3 / 4 mov", f("&origem=manual") == ("15", "3", "+12", "4"), f("&origem=manual"))
    check("C9 lançado por NF-e: 20 / 0 / 1 mov", f("&origem=nfe") == ("20", "0", "+20", "1"), f("&origem=nfe"))
    check("C10 categoria 2: 9 / 4 / 5 mov", f("&categoria_id=2") == ("9", "4", "+5", "5"), f("&categoria_id=2"))
    check("C11 produto 1: 31 / 4,500 / 5 mov", f("&produto_id=1") == ("31", "4,500", "+26,500", "5"), f("&produto_id=1"))
    check("C12 usuário vendedor (4): 1 / 3 / 2 mov", f("&usuario_id=4") == ("1", "3", "-2", "2"), f("&usuario_id=4"))
    check("C13 produto de outra empresa é ignorado (não filtra nem vaza)", f("&produto_id=50") == ("40", "8,500", "+31,500", "10"), f("&produto_id=50"))
    check("C14 só loja Norte: 20 / 1,500 / 2 mov", kpis(dono.get(f"{ROTA}?{MAR}&lojas[]=2")[1]) == ("20", "1,500", "+18,500", "2"))
    check("C15 loja de outra empresa (3) é ignorada → loja da sessão", kpis(dono.get(f"{ROTA}?{MAR}&lojas[]=3")[1])[3] == "8")
    st, h = dono.get(f"{ROTA}?data_inicio=2029-03-31&data_fim=2029-03-01{TODAS}")
    check("C16 datas invertidas: troca e responde igual", st == 200 and kpis(h) == ("40", "8,500", "+31,500", "10"), (st, kpis(h)))
    st, h = dono.get(f"{ROTA}?data_inicio=abc&data_fim=99/99/9999")
    check("C17 data inválida não dá erro (200)", st == 200, st)
    st, h = dono.get(f"{ROTA}?data_inicio=2031-01-01&data_fim=2031-01-31")
    check("C18 período sem nada: zeros e mensagem", st == 200 and kpis(h) == ("0", "0", "0", "0") and "Nenhuma movimentação com esses filtros" in h, kpis(h))

    # ---- D. movimento a movimento ----
    st, h = dono.get(f"{ROTA}?{MAR}{TODAS}&ver=itens")
    it = tabela(h, "tabela-itens")
    check("D1 lista com as 10 movimentações, a mais nova primeiro", len(it) == 10 and it[0][0] == "31/03/2029 23:50" and it[-1][0] == "02/03/2029 10:00", (len(it), it[0][:1] if it else ""))
    por_data = {l[0][:5]: l for l in it}
    check("D2 saída de venda: coluna Saiu 3, saldo 10 → 7, 'Venda #900'", por_data["05/03"][4:7] == ["", "3", "10 → 7"] and por_data["05/03"][8] == "Venda #900", por_data["05/03"])
    check("D3 ajuste para cima na coluna Entrou, para baixo na Saiu", por_data["06/03"][4:6] == ["5", ""] and por_data["07/03"][4:6] == ["", "2"], (por_data["06/03"], por_data["07/03"]))
    check("D4 lançamento manual / Transferência #5 / NF-e #7 / Troca #3", por_data["02/03"][8] == "Lançamento manual" and por_data["08/03"][8] == "Transferência entre lojas #5"
          and por_data["09/03"][8] == "Entrada por NF-e #7" and por_data["11/03"][8] == "Troca / devolução #3", [por_data[d][8] for d in ("02/03", "08/03", "09/03", "11/03")])
    check("D5 estoque que não é o principal aparece junto da loja (Depósito)", "Depósito" in por_data["08/03"][1] and "Principal" not in por_data["02/03"][1], (por_data["08/03"][1], por_data["02/03"][1]))
    vendedor = sql("SELECT name FROM users WHERE id = 4")
    check("D6 usuário que lançou e observação na linha", por_data["05/03"][7] == vendedor and por_data["02/03"][9] == "compra inicial", (por_data["05/03"][7], por_data["02/03"][9]))
    check("D7 observação com <b> não vira tag", "<b>corrigida</b>" not in h and "&lt;b&gt;corrigida" in h)
    check("D8 decimal: 20 → 18,500", por_data["10/03"][5:7] == ["1,500", "20 → 18,500"], por_data["10/03"])
    st, h = dono.get(f"{ROTA}?{MAR}{TODAS}&ver=itens&sentido=entrada")
    check("D9 só entradas na lista: 5 linhas, nenhuma com Saiu", len(tabela(h, "tabela-itens")) == 5 and all(l[5] == "" for l in tabela(h, "tabela-itens")))

    # ---- E. paginação, PDF e Excel ----
    MAIO = "data_inicio=2029-05-01&data_fim=2029-05-31"
    st, h = dono.get(f"{ROTA}?{MAIO}&ver=itens")
    check("E1 maio: 150 movimentações, 100 na 1ª página", kpi(h, "movimentos") == "150" and len(tabela(h, "tabela-itens")) == 100, (kpi(h, "movimentos"), len(tabela(h, "tabela-itens"))))
    check("E2 link da página 2 mantém os filtros", "page=2" in h and "ver=itens" in h.split("page=2")[0][-300:], "")
    st, h = dono.get(f"{ROTA}?{MAIO}&ver=itens&page=2")
    check("E3 página 2 com as 50 restantes", len(tabela(h, "tabela-itens")) == 50, len(tabela(h, "tabela-itens")))
    st, h = dono.get(f"{ROTA}?{MAIO}&ver=itens&imprimir=1")
    check("E4 PDF/imprimir: as 150 numa página só + abre a impressão + cabeçalho do papel", len(tabela(h, "tabela-itens")) == 150 and "window.print()" in h and "rel-print-header" in h and "page=2" not in h, len(tabela(h, "tabela-itens")))
    check("E5 PDF em A4 deitado e sem menu no papel", "A4 landscape" in h and ".sidebar" in h)
    st, h = dono.get(f"{ROTA}?{MAR}{TODAS}&imprimir=1")
    check("E6 PDF do resumo: 3 produtos e o período no cabeçalho", len(tabela(h, "tabela-resumo")) == 3 and "01/03/2029 a 31/03/2029" in h)
    st, corpo, cab = dono.bruto(f"{ROTA}?{MAR}{TODAS}&ver=itens&planilha=1")
    tipo = cab.get("Content-Type", "") + cab.get("content-type", "")
    disp = cab.get("Content-Disposition", "") + cab.get("content-disposition", "")
    check("E7 Excel das movimentações: .xlsx com nome do período", st == 200 and corpo[:2] == b"PK" and "spreadsheetml" in tipo and "movimentacoes-estoque-2029-03-01-a-2029-03-31.xlsx" in disp, (st, tipo, disp))
    p = planilha(corpo) if corpo[:2] == b"PK" else []
    check("E8 Excel: cabeçalho + 10 linhas, 14 colunas", len(p) == 11 and len(p[0]) == 14 and p[0][:3] == ["Data", "Loja", "Estoque"], (len(p), p[:1]))
    l5 = next((l for l in p if l and l[0].startswith("05/03/2029")), [])
    check("E9 Excel: linha da venda (Centro, Principal, PROD001, Saída, 0, 3, 10, 7, Venda #900)", l5[1:4] == ["Loja Centro", "Principal", "PROD001"] and l5[6:11] == ["Saída", "0", "3", "10", "7"] and l5[12] == "Venda #900", l5)
    st, corpo, cab = dono.bruto(f"{ROTA}?{MAR}{TODAS}&planilha=1")
    p = planilha(corpo) if corpo[:2] == b"PK" else []
    check("E10 Excel do resumo: cabeçalho + 3 produtos; PROD001 31 / 4.5 / 26.5 / 5", len(p) == 4 and p[1][0] == "PROD001" and p[1][3:] == ["31", "4.5", "26.5", "5"], p[:2])
    st, corpo, cab = dono.bruto(f"{ROTA}?{MAIO}&ver=itens&planilha=1")
    check("E11 Excel traz tudo, sem paginação (150 + cabeçalho)", corpo[:2] == b"PK" and len(planilha(corpo)) == 151, st)
    st, corpo, cab = dono.bruto(f"{ROTA}?{MAR}{TODAS}&ver=itens&planilha=1&sentido=saida")
    check("E12 Excel respeita o filtro (só saídas: 5 linhas)", corpo[:2] == b"PK" and len(planilha(corpo)) == 6)

    # ---- F. quem vê o quê ----
    ger = Sessao("gerente@demo.com", "gerente123")
    st, h = ger.get(f"{ROTA}?{MAR}{TODAS}")
    check("F1 gerente (vinculado só à Centro) pedindo as 2 lojas vê só a Centro: 20 / 7 / 8 mov", st == 200 and kpis(h)[:2] == ("20", "7") and kpis(h)[3] == "8", (st, kpis(h)))
    check("F2 gerente de uma loja só não tem pílulas de loja nem a Norte na tela", 'name="lojas[]"' not in h and "Loja Norte" not in h)
    st, corpo, cab = ger.bruto(f"{ROTA}?{MAR}&lojas[]=2&ver=itens&planilha=1")
    check("F3 Excel do gerente forçando a loja Norte não traz a Norte", corpo[:2] == b"PK" and all("Loja Norte" not in l for l in planilha(corpo)) and len(planilha(corpo)) == 9, st)
    ven = Sessao("vendedor@demo.com", "vendedor123")
    st, h = ven.get(f"{ROTA}?{MAR}{TODAS}")
    check("F4 vendedor (vê Estoque) abre, preso à loja da sessão", st == 200 and kpis(h)[3] == "8" and 'name="lojas[]"' not in h, (st, kpis(h)))
    cx = Sessao("caixa@demo.com", "caixa123")
    st, h = cx.get(ROTA)
    check("F5 caixa (sem Estoque) recebe 403", st == 403, st)
    st, corpo, cab = cx.bruto(f"{ROTA}?planilha=1")
    check("F6 caixa também não baixa o Excel (403)", st == 403, st)
    st, h = cx.get("/app/relatorios/vendas")
    check("F7 caixa segue abrindo o Relatório de Vendas, sem o link novo no menu", st == 200 and "relatorios/movimentacoes-estoque" not in h, st)
    adm = Sessao("admin@ia365.com.br", "admin123")
    st, h = adm.get(f"{ROTA}?{MAR}")
    check("F8 admin da plataforma (sem empresa) abre com os números da loja (não zerado)", st == 200 and kpis(h)[3] == "8", (st, kpis(h)))
    anon = Sessao("ninguem@x.com", "x")
    st, h = anon.get(ROTA)
    check("F9 sem login não abre o relatório", "Entradas e saídas do estoque" not in h, st)

    # ---- G. nada mudou em volta ----
    for nome, rota in (("Vendas", "/app/relatorios/vendas"), ("Produtos vendidos", "/app/relatorios/produtos"), ("Estoque", "/app/relatorios/estoque"),
                       ("Movimentações de Estoque", "/app/estoque/movimentacoes"), ("Dashboard", "/app/dashboard")):
        st, h = dono.get(rota)
        check(f"G tela '{nome}' segue abrindo (200) com o link novo no menu", st == 200 and ">Entradas e saídas</a>" in h, st)
    antes = sql("SELECT COUNT(*), COALESCE(SUM(quantidade),0) FROM estoque_movimentacoes")
    dono.get(f"{ROTA}?{MAR}{TODAS}&ver=itens"); dono.bruto(f"{ROTA}?{MAR}{TODAS}&planilha=1")
    check("G6 o relatório só lê: nenhuma movimentação criada ou alterada", sql("SELECT COUNT(*), COALESCE(SUM(quantidade),0) FROM estoque_movimentacoes") == antes)

    print(f"\n{OK} ✅ / {FAIL} ❌")
    sys.exit(1 if FAIL else 0)


if __name__ == "__main__":
    main()
