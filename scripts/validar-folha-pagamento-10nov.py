#!/usr/bin/env python3
"""Validação ✅/❌ — Folha de Pagamento (10/11/2026), Fase 2.

A folha guarda salário, vale, guias e benefícios e LANÇA no Contas a Pagar. Cobre: acesso (só dono
e financeiro), ficha do funcionário, guias/benefícios, prévia, lançar vários meses sem duplicar,
admissão/desligamento, dia 31, "atualizar pendentes", conta excluída não volta, série "esta e as
próximas", isolamento entre empresas.

Cenário (competência a partir de jan/2027):
  Carlos: salário 2500, vale 1000 no dia 20, paga dia 5 do mês seguinte, centro ADM
  Ana:    salário 1800, sem vale, paga dia 31 do PRÓPRIO mês, desligada em 15/03/2027
  Guia INSS (empresa) 700, dia 20 do mês seguinte · Vale-transporte do Carlos 200, dia 1 do próprio mês
  Plano de saúde 300 INATIVO (não lança)
  → por mês: vale 1000 + salário Carlos 1500 + salário Ana 1800 + INSS 700 + VT 200 = 5200 (5 contas)

Ambiente DESCARTÁVEL:
    BASE=http://172.17.0.x:8299 MYSQL=fp-mysql APP=fp-app python3 scripts/validar-folha-pagamento-10nov.py
"""
import http.cookiejar, os, re, subprocess, sys, urllib.error, urllib.parse, urllib.request

BASE = os.environ.get("BASE", "http://127.0.0.1:8299")
MYSQL = os.environ.get("MYSQL", "fp-mysql")
APP = os.environ.get("APP", "fp-app")
OK = FAIL = 0


def check(nome, cond, obtido=""):
    global OK, FAIL
    OK, FAIL = (OK + 1, FAIL) if cond else (OK, FAIL + 1)
    print(("✅ " if cond else "❌ ") + nome + ("" if cond else f"  (obtido: {str(obtido)[:400]})"))


def sql(q):
    r = subprocess.run(["docker", "exec", "-i", MYSQL, "mysql", "--default-character-set=utf8mb4", "-uerp", "-perp", "erp_teste", "-N", "-B"],
                       input=q, capture_output=True, text=True)
    if r.returncode:
        print("SQL ERRO:", r.stderr.strip()[-300:])
    return r.stdout.strip()


def linhas(q):
    out = sql(q)
    return [l.split("\t") for l in out.split("\n")] if out else []


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

    def post(self, p, campos, metodo=None):
        c = dict(campos)
        c.setdefault("_token", getattr(self, "token", ""))
        if metodo:
            c["_method"] = metodo
        try:
            with self.op.open(urllib.request.Request(BASE + p, data=urllib.parse.urlencode(c).encode())) as r:
                return r.status, r.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", "replace")

    @staticmethod
    def tok(h):
        m = re.search(r'name="_token" value="([^"]+)"', h) or re.search(r'name="csrf-token" content="([^"]+)"', h)
        return m.group(1) if m else ""


F = "/app/financeiro/folha"
CP = "/app/financeiro/contas-pagar"
FOLHA = "from contas_pagar where folha_ref is not null and deleted_at is null"


def contas(where="1=1", campos="descricao,valor,vencimento,competencia,status"):
    return linhas(f"select {campos} {FOLHA} and {where} order by competencia, vencimento, descricao")


def main():
    # ---- estado limpo
    sql("delete from contas_pagar; delete from folha_itens; delete from funcionario_fichas; delete from plano_contas; delete from centros_custo;"
        "delete from users where email='outra@empresa.test'; delete from empresas where cnpj='99999999000199';")
    subprocess.run(["docker", "exec", APP, "php", "/var/www/artisan", "tinker", "--execute",
                    "$e=App\\Models\\Empresa::withoutGlobalScopes()->find(1)->replicate();"
                    "$e->cnpj='99999999000199';$e->razao_social='Outra Empresa Ltda';$e->nome_fantasia='Outra';$e->save();"],
                   capture_output=True, text=True)
    emp2 = sql("select id from empresas where cnpj='99999999000199'")
    sql(f"insert into users (empresa_id,name,email,password,perfil,status,is_admin,created_at,updated_at) values ({emp2},'Fulano de Outra','outra@empresa.test','x','vendedor','ativo',0,now(),now());"
        f"insert into plano_contas (empresa_id,codigo,nome,tipo,natureza,ativo,created_at,updated_at) values ({emp2},'9.9','Conta da outra','despesa','analitica',1,now(),now());"
        f"insert into folha_itens (empresa_id,tipo,nome,valor,dia_vencimento,vence_mes_seguinte,ativo,created_at,updated_at) values ({emp2},'encargo','ITEM DA OUTRA',999,10,1,1,now(),now());"
        "insert into centros_custo (empresa_id,codigo,nome,ativo,created_at,updated_at) values (1,'ADM','Administrativo',1,now(),now());")
    func_outra = sql("select id from users where email='outra@empresa.test'")
    plano_outra = sql(f"select id from plano_contas where empresa_id={emp2}")
    item_outra = sql(f"select id from folha_itens where empresa_id={emp2}")
    CC = sql("select id from centros_custo where empresa_id=1")
    ids = dict(linhas("select email,id from users"))
    nomes = dict(linhas("select email,name from users"))
    CARLOS, ANA = ids["vendedor@demo.com"], ids["caixa@demo.com"]
    nCarlos, nAna = nomes["vendedor@demo.com"], nomes["caixa@demo.com"]
    check("preparo: segunda empresa, centro de custo e funcionários", all([emp2, func_outra, plano_outra, item_outra, CC, CARLOS, ANA]))

    # ---- A. acesso
    s = Sessao("dono@demo.com", "dono123")
    st, h = s.get(F)
    check("A1 dono abre a Folha de Pagamento (200)", st == 200 and "Folha de Pagamento" in h, st)
    check("A2 menu do dono mostra o link da folha", 'financeiro/folha"' in h and ">Folha de Pagamento</a>" in h)
    g = Sessao("gerente@demo.com", "gerente123")
    st, hg = g.get(F)
    check("A3 gerente NÃO abre a folha (403)", st == 403, st)
    st, hg = g.get(CP)
    check("A4 menu do gerente não mostra o link da folha (e ele segue vendo o Contas a Pagar)", st == 200 and ">Folha de Pagamento</a>" not in hg, st)
    st, _ = g.get(f"{F}/funcionarios/{CARLOS}")
    check("A5 gerente não abre a ficha de salário de ninguém", st == 403, st)
    g.post(f"{F}/funcionarios/{CARLOS}", {"salario": "9999", "dia_pagamento": "5"}, "PUT")
    g.post(f"{F}/lancar", {"inicio": "2027-01", "meses": "1"})
    check("A6 gerente não grava ficha nem lança folha", sql("select count(*) from funcionario_fichas") == "0" and sql("select count(*) from contas_pagar") == "0")
    v = Sessao("vendedor@demo.com", "vendedor123")
    st, _ = v.get(F)
    check("A7 vendedor não abre a folha", st in (302, 403), st)
    check("A8 folha vazia: tabela de funcionários lista a equipe e nada a lançar", nCarlos in h and "Sem salário" in h and "Nada a lançar" in h)

    # ---- B. plano de contas + ficha
    s.post("/app/plano-contas/padrao", {})
    pid = lambda cod: sql(f"select id from plano_contas where empresa_id=1 and codigo='{cod}'")
    P_SAL, P_VALE, P_INSS, P_VT = pid("3.1.01"), pid("3.1.02"), pid("3.2.01"), pid("3.3.01")

    st, h = s.get(f"{F}/funcionarios/{CARLOS}")
    check("B1 ficha abre com os padrões (dia 5, vale dia 20)", st == 200 and nCarlos in h and 'id="dia_pagamento"' in h and 'value="5"' in h and 'value="20"' in h, st)
    ficha_carlos = {"cargo": "Vendedor", "admissao": "2026-01-10", "salario": "2500.00", "dia_pagamento": "5", "paga_mes_seguinte": "1",
                    "vale_valor": "1000.00", "vale_dia": "20", "centro_custo_id": CC, "na_folha": "1"}
    s.post(f"{F}/funcionarios/{CARLOS}", {**ficha_carlos, "vale_valor": "3000"}, "PUT")
    check("B2 vale maior que o salário é recusado", sql("select count(*) from funcionario_fichas") == "0")
    s.post(f"{F}/funcionarios/{CARLOS}", ficha_carlos, "PUT")
    f = linhas(f"select empresa_id,cargo,salario,vale_valor,vale_dia,dia_pagamento,paga_mes_seguinte,centro_custo_id,na_folha,admissao from funcionario_fichas where user_id={CARLOS}")
    check("B3 ficha do Carlos gravada", f == [["1", "Vendedor", "2500.00", "1000.00", "20", "5", "1", CC, "1", "2026-01-10"]], f)
    s.post(f"{F}/funcionarios/{CARLOS}", ficha_carlos, "PUT")
    check("B4 salvar de novo atualiza a mesma ficha (não cria outra)", sql(f"select count(*) from funcionario_fichas where user_id={CARLOS}") == "1")
    s.post(f"{F}/funcionarios/{ANA}", {"cargo": "Caixa", "salario": "1800", "dia_pagamento": "31", "paga_mes_seguinte": "0", "vale_valor": "0",
                                        "vale_dia": "20", "demissao": "2027-03-15", "na_folha": "1"}, "PUT")
    check("B5 ficha da Ana (paga no próprio mês, desligada em março)", linhas(f"select salario,paga_mes_seguinte,demissao from funcionario_fichas where user_id={ANA}") == [["1800.00", "0", "2027-03-15"]])
    st, _ = s.get(f"{F}/funcionarios/{func_outra}")
    s.post(f"{F}/funcionarios/{func_outra}", ficha_carlos, "PUT")
    check("B6 funcionário de OUTRA empresa: ficha não abre nem grava", st == 404 and sql(f"select count(*) from funcionario_fichas where user_id={func_outra}") == "0", st)
    s.post(f"{F}/funcionarios/{ids['admin@ia365.com.br']}", ficha_carlos, "PUT")
    check("B7 admin da plataforma não vira funcionário da folha", sql("select count(*) from funcionario_fichas") == "2")

    # ---- C. guias e benefícios
    st, h = s.get(f"{F}/itens/novo")
    check("C1 tela de nova guia abre com o plano de contas", st == 200 and "3.2.01 — INSS" in h and "Fulano de Outra" not in h, st)
    inss = {"tipo": "encargo", "nome": "Guia INSS", "favorecido_nome": "INSS", "valor": "700", "dia_vencimento": "20", "vence_mes_seguinte": "1", "plano_conta_id": P_INSS, "ativo": "1"}
    s.post(f"{F}/itens", inss)
    s.post(f"{F}/itens", {"tipo": "beneficio", "nome": "Vale-transporte", "funcionario_id": CARLOS, "valor": "200", "dia_vencimento": "1", "vence_mes_seguinte": "0", "plano_conta_id": P_VT, "ativo": "1"})
    s.post(f"{F}/itens", {"tipo": "beneficio", "nome": "Plano de saúde", "valor": "300", "dia_vencimento": "10", "vence_mes_seguinte": "0", "ativo": "0"})
    it = linhas("select nome,tipo,ifnull(funcionario_id,'N'),ifnull(favorecido_nome,'N'),valor,dia_vencimento,vence_mes_seguinte,ativo from folha_itens where empresa_id=1 order by nome")
    check("C2 três itens gravados (INSS da empresa, VT do Carlos, plano de saúde inativo)",
          it == [["Guia INSS", "encargo", "N", "INSS", "700.00", "20", "1", "1"], ["Plano de saúde", "beneficio", "N", "N", "300.00", "10", "0", "0"], ["Vale-transporte", "beneficio", CARLOS, "N", "200.00", "1", "0", "1"]], it)
    s.post(f"{F}/itens", {**inss, "nome": "INVASAO", "plano_conta_id": plano_outra})
    s.post(f"{F}/itens", {**inss, "nome": "INVASAO", "funcionario_id": func_outra})
    check("C3 item com plano de contas ou funcionário de OUTRA empresa é recusado", sql("select count(*) from folha_itens where nome='INVASAO'") == "0")
    st, _ = s.get(f"{F}/itens/{item_outra}")
    s.post(f"{F}/itens/{item_outra}", {**inss, "nome": "INVADIDO"}, "PUT")
    s.post(f"{F}/itens/{item_outra}", {}, "DELETE")
    check("C4 item de OUTRA empresa não abre, não edita e não exclui", st == 404 and sql(f"select concat(nome,'|',deleted_at is null) from folha_itens where id={item_outra}") == "ITEM DA OUTRA|1", st)

    # ---- D. prévia
    st, h = s.get(F + "?inicio=2027-01&meses=4")
    previa = h.split('id="tabelaPrevia"')[1].split("</table>")[0] if 'id="tabelaPrevia"' in h else ""
    check("D1 prévia de jan/2027 tem as 5 contas e o total de R$ 5.200,00", previa.count("<tr>") + previa.count('<tr class') == 7 and "5.200,00" in previa, previa.count("<tr"))
    check("D2 prévia: vale 20/01, salário do Carlos 05/02 (1.500), Ana 31/01, INSS 20/02, VT 01/01",
          all(x in previa for x in ("20/01/2027", "05/02/2027", "1.500,00", "31/01/2027", "20/02/2027", "01/01/2027")) and "Plano de saúde" not in previa)
    check("D3 prévia liga salário e vale ao plano de contas sozinha", "3.1.01 — Salários" in previa and "3.1.02 — Adiantamento" in previa)
    resumo = h.split('id="tabelaResumo"')[1].split("</table>")[0]
    check("D4 resumo: 4 meses, abril com 4 contas (Ana desligada em março) e nada lançado", resumo.count("Não lançado") == 4 and "0/5" in resumo and "0/4" in resumo and "3.400,00" in resumo, "")
    check("D5 nada foi gravado só por abrir a tela", sql("select count(*) from contas_pagar") == "0")

    # ---- E. lançar
    st, h = s.post(f"{F}/lancar", {"inicio": "2027-01", "meses": "4", "atualizar_pendentes": "0"})
    tot = int(sql(f"select count(*) {FOLHA}"))
    check("E1 lançar 4 meses cria 19 contas (5+5+5+4) e avisa", tot == 19 and "19 contas lançadas" in h, (tot, st))
    jan = contas("competencia='2027-01-01'", "descricao,valor,vencimento,ifnull(funcionario_id,'N'),ifnull(favorecido_nome,'N'),plano_conta_id,categoria,ifnull(centro_custo_id,'N'),folha_ref,status,recorrente,unidade_id")
    esperado = sorted([
        [f"Vale-transporte 01/2027 — {nCarlos}", "200.00", "2027-01-01", CARLOS, "N", P_VT, "Vale-transporte", "N", None, "pendente", "1", "1"],
        [f"Vale 01/2027 — {nCarlos}", "1000.00", "2027-01-20", CARLOS, "N", P_VALE, "Adiantamento de salário (vale)", CC, "vale", "pendente", "1", "1"],
        [f"Salário 01/2027 — {nAna}", "1800.00", "2027-01-31", ANA, "N", P_SAL, "Salários", "N", "salario", "pendente", "1", "1"],
        [f"Salário 01/2027 — {nCarlos}", "1500.00", "2027-02-05", CARLOS, "N", P_SAL, "Salários", CC, "salario", "pendente", "1", "1"],
        ["Guia INSS 01/2027", "700.00", "2027-02-20", "N", "INSS", P_INSS, "INSS", "N", None, "pendente", "1", "1"],
    ], key=lambda r: r[0])
    obt = sorted([[c if i != 8 or not c.startswith("item:") else None for i, c in enumerate(r)] for r in jan], key=lambda r: r[0])
    check("E2 contas de janeiro: favorecido, valor, vencimento, plano, centro, loja e origem certos", obt == esperado, obt)
    ana = [r[0] for r in linhas(f"select vencimento {FOLHA} and funcionario_id={ANA} order by competencia")]
    check("E3 Ana: dia 31 vira 28/02; março é o último mês (desligada)", ana == ["2027-01-31", "2027-02-28", "2027-03-31"], ana)
    series = linhas(f"select folha_ref, ifnull(funcionario_id,'N'), count(*), count(distinct serie_id) {FOLHA} group by 1,2 order by 1,2")
    check("E4 cada linha da folha é UMA série ao longo dos meses", all(r[3] == "1" for r in series) and len(series) == 5 and int(sql(f"select count(distinct serie_id) {FOLHA}")) == 5, series)
    st, h = s.post(f"{F}/lancar", {"inicio": "2027-01", "meses": "4"})
    check("E5 lançar de novo NÃO duplica", int(sql(f"select count(*) {FOLHA}")) == 19 and "já estava lançado" in h, sql(f"select count(*) {FOLHA}"))
    st, h = s.get(F + "?inicio=2027-01&meses=4")
    resumo = h.split('id="tabelaResumo"')[1].split("</table>")[0]
    check("E6 resumo mostra os 4 meses como Lançado", resumo.count(">Lançado<") == 4 and "5/5" in resumo and "4/4" in resumo and "já está todo lançado" in h)
    st, h = s.get(CP + "?competencia=2027-01&folha=1")
    check("E7 Contas a Pagar filtrado pelo mês da folha mostra as 5 contas", st == 200 and h.count("data-baixar=") == 5 and "Guia INSS 01/2027" in h and "02/2027" not in h.split("<tbody>")[1].split("</tbody>")[0].replace("/02/2027", ""), h.count("data-baixar="))
    st, h = s.get("/app/financeiro/fluxo-caixa?data_inicio=2027-01-01&data_fim=2027-01-31")
    check("E8 fluxo de caixa de janeiro prevê R$ 3.000,00 a pagar (VT 200 + vale 1000 + Ana 1800)", st == 200 and "3.000,00" in h, st)

    # ---- F. ajuste manual × atualizar pendentes
    idc = lambda desc: sql(f"select id {FOLHA} and descricao='{desc}'")
    inss_fev, sal_jan, sal_fev = idc("Guia INSS 02/2027"), idc(f"Salário 01/2027 — {nCarlos}"), idc(f"Salário 02/2027 — {nCarlos}")
    s.post(f"{CP}/{inss_fev}", {"favorecido_tipo": "outro", "favorecido_nome": "INSS", "descricao": "Guia INSS 02/2027", "valor": "755.30",
                                 "vencimento": "2027-03-20", "competencia": "2027-02", "plano_conta_id": P_INSS}, "PUT")
    check("F1 ajuste da guia de fevereiro no Contas a Pagar (755,30) e continua sendo conta da folha", sql(f"select concat(valor,'|',folha_ref is not null) from contas_pagar where id={inss_fev}") == "755.30|1")
    s.post(f"{CP}/{sal_jan}/baixar", {"valor_pago": "1500.00", "pago_em": "2026-11-09", "forma_pagamento": "pix"})
    s.post(f"{F}/funcionarios/{CARLOS}", {**ficha_carlos, "salario": "2700.00"}, "PUT")
    s.post(f"{F}/lancar", {"inicio": "2027-01", "meses": "4", "atualizar_pendentes": "0"})
    check("F2 mudar o salário e lançar SEM marcar atualizar não mexe no que já está lançado",
          sql(f"select group_concat(valor order by competencia) {FOLHA} and folha_ref='salario' and funcionario_id={CARLOS}") == "1500.00,1500.00,1500.00,1500.00" and sql(f"select valor from contas_pagar where id={inss_fev}") == "755.30")
    st, h = s.post(f"{F}/lancar", {"inicio": "2027-01", "meses": "4", "atualizar_pendentes": "1"})
    check("F3 COM atualizar: pendentes do Carlos vão para 1.700; a de janeiro, já PAGA, fica em 1.500",
          sql(f"select group_concat(valor order by competencia) {FOLHA} and folha_ref='salario' and funcionario_id={CARLOS}") == "1500.00,1700.00,1700.00,1700.00", h[-200:])
    check("F4 atualizar pendentes sobrescreve o ajuste manual da guia (avisado na tela) e não cria conta", sql(f"select valor from contas_pagar where id={inss_fev}") == "700.00" and int(sql(f"select count(*) {FOLHA}")) == 19)
    check("F5 a tela avisa quantas foram atualizadas", "pendentes tiveram o valor atualizado" in h)

    # ---- G. conta excluída não volta; estender o período
    vt_mar = idc(f"Vale-transporte 03/2027 — {nCarlos}")
    s.post(f"{CP}/{vt_mar}", {}, "DELETE")
    s.post(f"{F}/lancar", {"inicio": "2027-01", "meses": "4"})
    check("G1 conta da folha excluída de propósito não é lançada de novo", int(sql(f"select count(*) {FOLHA}")) == 18 and sql(f"select count(*) from contas_pagar where id={vt_mar} and deleted_at is not null") == "1")
    st, h = s.post(f"{F}/lancar", {"inicio": "2027-01", "meses": "6"})
    check("G2 estender para 6 meses só acrescenta maio e junho (4 contas cada)", int(sql(f"select count(*) {FOLHA}")) == 26 and "8 contas lançadas" in h, sql(f"select count(*) {FOLHA}"))
    check("G3 maio já nasce com o salário novo (1.700)", sql(f"select valor {FOLHA} and descricao='Salário 05/2027 — {nCarlos}'") == "1700.00")

    # ---- H. "esta e as próximas" nas contas da folha (série por vencimento)
    s.post(f"{CP}/{sal_fev}", {"favorecido_tipo": "funcionario", "funcionario_id": CARLOS, "descricao": f"Salário 02/2027 — {nCarlos}", "valor": "1750.00",
                                "vencimento": "2027-03-05", "competencia": "2027-02", "plano_conta_id": P_SAL, "centro_custo_id": CC, "aplicar_proximas": "1"}, "PUT")
    r = linhas(f"select valor,descricao,competencia {FOLHA} and folha_ref='salario' and funcionario_id={CARLOS} order by competencia")
    check("H1 editar fevereiro com 'aplicar nas próximas' leva o valor até junho; janeiro (paga) fica", [x[0] for x in r] == ["1500.00"] + ["1750.00"] * 5, [x[0] for x in r])
    check("H2 cada conta da folha mantém a SUA descrição (mês) e a sua competência", r[5][1] == f"Salário 06/2027 — {nCarlos}" and r[5][2] == "2027-06-01", r[5])

    # ---- I. fora da folha / desligar item
    s.post(f"{F}/funcionarios/{CARLOS}", {**ficha_carlos, "salario": "2700.00", "na_folha": "0"}, "PUT")
    st, h = s.post(f"{F}/lancar", {"inicio": "2027-07", "meses": "1"})
    jul = contas("competencia='2027-07-01'", "descricao,valor")
    check("I1 Carlos fora da folha: julho só lança a guia da empresa (nem salário, nem vale, nem o VT dele)", jul == [["Guia INSS 07/2027", "700.00"]], jul)
    inss_id = sql("select id from folha_itens where nome='Guia INSS'")
    s.post(f"{F}/itens/{inss_id}", {**inss, "valor": "820"}, "PUT")
    check("I2 editar a guia na folha não muda as contas já lançadas", sql(f"select valor {FOLHA} and descricao='Guia INSS 07/2027'") == "700.00" and sql(f"select valor from folha_itens where id={inss_id}") == "820.00")
    s.post(f"{F}/itens/{inss_id}", {}, "DELETE")
    antes = int(sql(f"select count(*) {FOLHA}"))
    st, h = s.post(f"{F}/lancar", {"inicio": "2027-08", "meses": "1"})
    check("I3 excluir a guia: as contas dela continuam e agosto não lança nada (com aviso)", int(sql(f"select count(*) {FOLHA}")) == antes == 27 and "Nada para lançar" in h, (antes, h[-150:]))
    check("I4 nenhuma conta da folha foi parar em outra empresa", sql(f"select count(*) from contas_pagar where empresa_id<>1") == "0")

    # ---- J. telas
    st, h = s.get(F + "?inicio=2027-01&meses=6")
    check("J1 tela da folha mostra Carlos fora da folha, Ana desligada e os itens", st == 200 and "Desligado em 15/03/2027" in h and "Plano de saúde" in h and ">Não<" in h, st)
    st, h = s.get(f"{CP}/{sal_fev}")
    check("J2 detalhe de um salário mostra a série inteira (6 meses) e a competência", st == 200 and "02/2027" in h and h.count("<tr class=") + h.count("<tr>") >= 6, st)
    st, h = s.get("/app/dre?data_inicio=2026-11-01&data_fim=2026-11-30")
    check("J3 DRE de nov/2026 mostra o salário pago em 'Salários'", st == 200 and "Salários" in h and "1.500,00" in h, st)
    fin = Sessao("dono@demo.com", "dono123")
    sql("update users set perfil='financeiro' where email='gerente@demo.com'")
    fin = Sessao("gerente@demo.com", "gerente123")
    st, h = fin.get(F)
    check("J4 perfil financeiro abre a folha", st == 200 and "Lançar no Contas a Pagar" in h, st)
    sql("update users set perfil='gerente' where email='gerente@demo.com'")

    print(f"\n{OK} ✅ / {FAIL} ❌")
    sys.exit(1 if FAIL else 0)


if __name__ == "__main__":
    main()
