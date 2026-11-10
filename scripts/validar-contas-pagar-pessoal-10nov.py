#!/usr/bin/env python3
"""Validação ✅/❌ — Contas a Pagar para despesa de pessoal (10/11/2026), Fase 1.

Cobre: plano de contas padrão (idempotente), favorecido fornecedor / funcionário / imposto-outro,
plano de contas e centro de custo gravando o VÍNCULO, mês de competência, recorrência (12 lançados,
bimestral que dava 500, dia 31), editar só este mês × aplicar nas próximas, baixa com data/valor/forma,
estorno, isolamento entre empresas, telas (lista, detalhe, editar, DRE, fluxo, exportação).

Ambiente DESCARTÁVEL (banco erp_teste com migrate:fresh --seed):
    BASE=http://172.17.0.x:8299 MYSQL=fp-mysql python3 scripts/validar-contas-pagar-pessoal-10nov.py
"""
import datetime, http.cookiejar, os, re, subprocess, sys, urllib.error, urllib.parse, urllib.request

BASE = os.environ.get("BASE", "http://127.0.0.1:8299")
MYSQL = os.environ.get("MYSQL", "fp-mysql")
APP = os.environ.get("APP", "fp-app")
OK = FAIL = 0
HOJE = datetime.date.today()


def check(nome, cond, obtido=""):
    global OK, FAIL
    OK, FAIL = (OK + 1, FAIL) if cond else (OK, FAIL + 1)
    print(("✅ " if cond else "❌ ") + nome + ("" if cond else f"  (obtido: {str(obtido)[:300]})"))


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
        self.token = self.tok(self.get("/app/financeiro/contas-pagar/create")[1])

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


CP = "/app/financeiro/contas-pagar"


def total():
    return int(sql("select count(*) from contas_pagar where deleted_at is null"))


def main():
    # ---- estado limpo + uma segunda empresa para o teste de isolamento
    sql("delete from contas_pagar; delete from plano_contas; delete from centros_custo;"
        "delete from users where email='outra@empresa.test'; delete from empresas where cnpj='99999999000199';")
    # cópia da empresa demo (a tabela tem muitos campos obrigatórios) só com CNPJ e nome trocados
    subprocess.run(["docker", "exec", APP, "php", "/var/www/artisan", "tinker", "--execute",
                    "$e=App\\Models\\Empresa::withoutGlobalScopes()->find(1)->replicate();"
                    "$e->cnpj='99999999000199';$e->razao_social='Outra Empresa Ltda';$e->nome_fantasia='Outra';$e->save();"],
                   capture_output=True, text=True)
    emp2 = sql("select id from empresas where cnpj='99999999000199'")
    sql(f"insert into users (empresa_id,name,email,password,perfil,status,is_admin,created_at,updated_at) "
        f"values ({emp2},'Fulano de Outra','outra@empresa.test','x','vendedor','ativo',0,now(),now());"
        f"insert into plano_contas (empresa_id,codigo,nome,tipo,natureza,ativo,created_at,updated_at) "
        f"values ({emp2},'9.9','Conta da outra','despesa','analitica',1,now(),now());")
    func_outra = sql("select id from users where email='outra@empresa.test'")
    plano_outra = sql(f"select id from plano_contas where empresa_id={emp2}")
    check("preparo: segunda empresa criada para o teste de isolamento", emp2 and func_outra and plano_outra, (emp2, func_outra, plano_outra))

    s = Sessao("dono@demo.com", "dono123")
    check("login do dono e token da tela", bool(s.token))

    # ---- A. sem plano de contas a tela mostra a categoria antiga e o atalho
    st, h = s.get(CP + "/create")
    check("A1 Nova conta abre (200)", st == 200, st)
    check("A2 mostra as três opções de favorecido", all(x in h for x in ('value="fornecedor"', 'value="funcionario"', 'value="outro"')))
    check("A3 sem plano de contas: categoria antiga + botão Criar contas padrão", 'name="categoria"' in h and "plano-contas/padrao" in h)
    nomes = dict(linhas("select email,name from users"))
    opcoes = re.findall(r'<option value="\d+"[^>]*>([^<]+)</option>', h.split('id="funcionario_id"')[1].split("</select>")[0])
    check("A4 lista de funcionários traz a equipe da empresa (não o admin da plataforma)",
          nomes["vendedor@demo.com"] in opcoes and nomes["dono@demo.com"] in opcoes and nomes["admin@ia365.com.br"] not in opcoes, opcoes)
    check("A5 funcionário de outra empresa não aparece", "Fulano de Outra" not in h)

    # ---- B. plano de contas padrão
    s.post("/app/plano-contas/padrao", {})
    n1 = int(sql("select count(*) from plano_contas where empresa_id=1"))
    check("B1 plano padrão criado (38 contas)", n1 == 38, n1)
    s.post("/app/plano-contas/padrao", {})
    n2 = int(sql("select count(*) from plano_contas where empresa_id=1"))
    check("B2 rodar de novo não duplica", n2 == n1, n2)
    pai = sql("select p.codigo from plano_contas c join plano_contas p on p.id=c.parent_id where c.empresa_id=1 and c.codigo='3.1.01'")
    check("B3 Salários (3.1.01) fica dentro de Pessoal — salários (3.1)", pai == "3.1", pai)
    tipos = sql("select group_concat(distinct tipo order by tipo) from plano_contas where empresa_id=1 and codigo like '3.%'")
    check("B4 grupo 3 é todo despesa", tipos == "despesa", tipos)
    check("B5 outra empresa não ganhou conta", sql(f"select count(*) from plano_contas where empresa_id={emp2}") == "1")
    st, h = s.get("/app/plano-contas")
    check("B6 tela do plano de contas abre com o botão", st == 200 and "Criar contas padrão" in h and "Vale-transporte" in h, st)

    pid = lambda cod: sql(f"select id from plano_contas where empresa_id=1 and codigo='{cod}'")
    P_SAL, P_INSS, P_VT, P_GRUPO = pid("3.1.01"), pid("3.2.01"), pid("3.3.01"), pid("3.1")
    sql("insert into centros_custo (empresa_id,codigo,nome,ativo,created_at,updated_at) values (1,'ADM','Administrativo',1,now(),now()),"
        f"({emp2},'X','Centro da outra',1,now(),now());")
    CC = sql("select id from centros_custo where empresa_id=1")
    CC_OUTRA = sql(f"select id from centros_custo where empresa_id={emp2}")

    st, h = s.get(CP + "/create")
    check("B7 com plano de contas a tela troca a categoria pela lista do plano", 'name="plano_conta_id"' in h and "3.1.01 — Salários" in h and 'name="categoria"' not in h)
    check("B8 só conta lançável na lista (grupo 3.1 não aparece como opção)", f'value="{P_GRUPO}"' not in h)
    check("B9 centro de custo vira lista", 'name="centro_custo_id"' in h and "Administrativo" in h and "Centro da outra" not in h)

    # ---- C. salário recorrente para funcionário
    FUNC = sql("select id from users where email='vendedor@demo.com'")
    antes = total()
    s.post(CP, {"favorecido_tipo": "funcionario", "funcionario_id": FUNC, "descricao": "Salário", "valor": "2500.00",
                "vencimento": "2027-01-05", "competencia": "2026-12", "plano_conta_id": P_SAL, "centro_custo_id": CC,
                "forma_pagamento": "pix", "recorrente": "1", "recorrencia_tipo": "mensal"})
    sal = linhas("select id,funcionario_id,ifnull(fornecedor_id,'N'),plano_conta_id,categoria,centro_custo_id,centro_custo,"
                 "vencimento,competencia,valor,parcela,total_parcelas,serie_id,status,unidade_id from contas_pagar where descricao='Salário' order by parcela")
    check("C1 recorrente lança os próximos 12 meses", total() - antes == 12 and len(sal) == 12, total() - antes)
    check("C2 favorecido é o funcionário, sem fornecedor", all(r[1] == FUNC and r[2] == "N" for r in sal), sal[:1])
    check("C3 grava o VÍNCULO com o plano de contas e o nome na categoria", all(r[3] == P_SAL and r[4] == "Salários" for r in sal), sal[:1])
    check("C4 grava o vínculo com o centro de custo e o nome", all(r[5] == CC and r[6] == "Administrativo" for r in sal), sal[:1])
    check("C5 vencimentos todo dia 5, jan→dez/2027", [r[7] for r in sal] == [f"2027-{m:02d}-05" for m in range(1, 13)], [r[7] for r in sal])
    check("C6 competência anda junto (dez/2026 → nov/2027)", sal[0][8] == "2026-12-01" and sal[1][8] == "2027-01-01" and sal[11][8] == "2027-11-01", [r[8] for r in sal])
    check("C7 todas com o mesmo valor, pendentes e na mesma série", all(r[9] == "2500.00" and r[13] == "pendente" for r in sal) and len({r[12] for r in sal}) == 1 and sal[0][12] not in ("NULL", ""), sal[:1])
    check("C8 loja da sessão gravada", all(r[14] == "1" for r in sal))

    # ---- D. guia de imposto bimestral (o enum recusava: 500)
    antes = total()
    st, _ = s.post(CP, {"favorecido_tipo": "outro", "favorecido_nome": "INSS", "descricao": "Guia INSS", "valor": "700",
                        "vencimento": "2027-01-20", "plano_conta_id": P_INSS, "recorrente": "1", "recorrencia_tipo": "bimestral"})
    inss = linhas("select favorecido_nome,ifnull(fornecedor_id,'N'),ifnull(funcionario_id,'N'),vencimento,recorrencia_tipo,ifnull(competencia,'N') from contas_pagar where descricao='Guia INSS' order by parcela")
    check("D1 recorrência bimestral salva (antes dava erro 500)", st == 200 and total() - antes == 12, (st, total() - antes))
    check("D2 favorecido livre 'INSS', sem fornecedor e sem funcionário", bool(inss) and all(r[0] == "INSS" and r[1] == "N" and r[2] == "N" for r in inss), inss[:1])
    check("D3 de dois em dois meses", [r[3] for r in inss[:3]] == ["2027-01-20", "2027-03-20", "2027-05-20"], [r[3] for r in inss[:3]])
    check("D4 sem competência informada fica vazia", bool(inss) and inss[0][5] == "N", inss[:1])

    # ---- E. dia 31 não pula mês
    s.post(CP, {"favorecido_tipo": "outro", "favorecido_nome": "Vale-transporte", "descricao": "VT dia 31", "valor": "300",
                "vencimento": "2027-01-31", "plano_conta_id": P_VT, "recorrente": "1", "recorrencia_tipo": "mensal"})
    vt = [r[0] for r in linhas("select vencimento from contas_pagar where descricao='VT dia 31' order by parcela")]
    check("E1 vencimento dia 31: fevereiro cai no dia 28 e março volta ao 31", vt[:4] == ["2027-01-31", "2027-02-28", "2027-03-31", "2027-04-30"], vt[:4])

    # ---- F. validações e isolamento
    def recusa(nome, campos):
        antes = total()
        base = {"descricao": "NAO DEVE GRAVAR", "valor": "10", "vencimento": "2027-02-01"}
        base.update(campos)
        s.post(CP, base)
        check(nome, total() == antes, total() - antes)

    recusa("F1 'Imposto / outro' sem nome é recusado", {"favorecido_tipo": "outro", "favorecido_nome": ""})
    recusa("F2 'Funcionário' sem escolher é recusado", {"favorecido_tipo": "funcionario"})
    recusa("F3 'Fornecedor' sem escolher é recusado", {"favorecido_tipo": "fornecedor"})
    recusa("F4 sem tipo e sem fornecedor (tela antiga) é recusado", {})
    recusa("F5 funcionário de OUTRA empresa é recusado", {"favorecido_tipo": "funcionario", "funcionario_id": func_outra})
    recusa("F6 plano de contas de OUTRA empresa é recusado", {"favorecido_tipo": "outro", "favorecido_nome": "X", "plano_conta_id": plano_outra})
    recusa("F7 centro de custo de OUTRA empresa é recusado", {"favorecido_tipo": "outro", "favorecido_nome": "X", "centro_custo_id": CC_OUTRA})
    recusa("F8 conta-grupo do plano (não lançável) é recusada", {"favorecido_tipo": "outro", "favorecido_nome": "X", "plano_conta_id": P_GRUPO})
    recusa("F9 competência inválida é recusada", {"favorecido_tipo": "outro", "favorecido_nome": "X", "competencia": "novembro"})

    # ---- G. fornecedor continua como era (parcelas) e a tela antiga também
    s.post(CP, {"favorecido_tipo": "fornecedor", "fornecedor_id": "1", "descricao": "Compra parcelada", "valor": "100", "vencimento": "2027-03-10", "parcelas": "3"})
    par = linhas("select valor,vencimento,fornecedor_id,ifnull(funcionario_id,'N'),recorrente,serie_id from contas_pagar where descricao='Compra parcelada' order by parcela")
    check("G1 fornecedor + 3 parcelas: 33,33 / 33,33 / 33,34", [r[0] for r in par] == ["33.33", "33.33", "33.34"], par)
    check("G2 parcelas mês a mês, com fornecedor e mesma série", [r[1] for r in par] == ["2027-03-10", "2027-04-10", "2027-05-10"] and all(r[2] == "1" and r[3] == "N" for r in par) and len({r[5] for r in par}) == 1, par)
    s.post(CP, {"fornecedor_id": "1", "descricao": "Post antigo", "valor": "50", "vencimento": "2027-03-10", "categoria": "Aluguel", "centro_custo": "Loja"})
    ant = linhas("select fornecedor_id,categoria,centro_custo,ifnull(plano_conta_id,'N'),ifnull(serie_id,'N') from contas_pagar where descricao='Post antigo'")
    check("G3 envio no formato antigo (só fornecedor + categoria em texto) continua valendo", ant == [["1", "Aluguel", "Loja", "N", "N"]], ant)
    s.post(CP, {"favorecido_tipo": "funcionario", "funcionario_id": FUNC, "fornecedor_id": "1", "favorecido_nome": "lixo", "descricao": "Um favorecido só", "valor": "10", "vencimento": "2027-03-10"})
    um = linhas("select ifnull(fornecedor_id,'N'),funcionario_id,ifnull(favorecido_nome,'N') from contas_pagar where descricao='Um favorecido só'")
    check("G4 só UM favorecido fica gravado (o tipo escolhido)", um == [["N", FUNC, "N"]], um)

    # ---- H. editar
    ids = [r[0] for r in sal]
    st, h = s.get(f"{CP}/{ids[0]}/edit")
    check("H1 Editar abre com os dados da conta", st == 200 and 'value="2500.00"' in h and 'value="2026-12"' in h and "Aplicar também nas próximas 11" in h, st)
    edit = {"favorecido_tipo": "funcionario", "funcionario_id": FUNC, "descricao": "Salário", "vencimento": "2027-01-05",
            "competencia": "2026-12", "plano_conta_id": P_SAL, "centro_custo_id": CC, "forma_pagamento": "pix"}
    s.post(f"{CP}/{ids[0]}", {**edit, "valor": "2600.00", "aplicar_proximas": "0"}, "PUT")
    vals = [r[0] for r in linhas("select valor from contas_pagar where descricao='Salário' order by parcela")]
    check("H2 ajustar só este mês não mexe nos outros", vals == ["2600.00"] + ["2500.00"] * 11, vals)

    # paga a parcela 5 antes do reajuste: conta paga não pode ser reajustada
    s.post(f"{CP}/{ids[4]}/baixar", {"valor_pago": "2500.00", "pago_em": HOJE.isoformat(), "forma_pagamento": "pix"})
    s.post(f"{CP}/{ids[2]}", {**edit, "valor": "2800.00", "vencimento": "2027-03-05", "competencia": "2027-02", "aplicar_proximas": "1"}, "PUT")
    r = linhas("select valor,status,vencimento,competencia from contas_pagar where descricao='Salário' order by parcela")
    esperado = ["2600.00", "2500.00", "2800.00", "2800.00", "2500.00"] + ["2800.00"] * 7
    check("H3 reajuste daqui para a frente: muda esta e as próximas PENDENTES; anteriores e a já paga ficam", [x[0] for x in r] == esperado, [x[0] for x in r])
    check("H4 o reajuste não mexe no vencimento nem na competência das próximas", r[3][2] == "2027-04-05" and r[3][3] == "2027-03-01" and r[11][2] == "2027-12-05", (r[3], r[11]))
    s.post(f"{CP}/{ids[1]}", {"favorecido_tipo": "funcionario", "funcionario_id": func_outra, "descricao": "INVASAO", "valor": "1", "vencimento": "2027-02-05"}, "PUT")
    check("H5 editar com funcionário de outra empresa é recusado", sql(f"select descricao from contas_pagar where id={ids[1]}") == "Salário")
    s.post(f"{CP}/{ids[4]}", {**edit, "valor": "1.00"}, "PUT")
    check("H6 conta PAGA não é editada", sql(f"select valor from contas_pagar where id={ids[4]}") == "2500.00")
    st, h = s.get(f"{CP}/{ids[4]}/edit")
    check("H7 tela de editar de conta paga volta para o detalhe com o aviso", "estorne a baixa" in h, st)

    # ---- I. baixa
    ontem = (HOJE - datetime.timedelta(days=1)).isoformat()
    amanha = (HOJE + datetime.timedelta(days=1)).isoformat()
    s.post(f"{CP}/{ids[0]}/baixar", {"valor_pago": "2650.50", "pago_em": ontem, "forma_pagamento": "transferencia"})
    b = linhas(f"select status,valor_pago,pago_em,forma_pagamento,valor from contas_pagar where id={ids[0]}")
    check("I1 baixa grava o valor pago, a data e a forma informados", b == [["paga", "2650.50", ontem, "transferencia", "2600.00"]], b)
    s.post(f"{CP}/{ids[1]}/baixar", {"valor_pago": "2500", "pago_em": amanha})
    check("I2 baixa com data no futuro é recusada", sql(f"select status from contas_pagar where id={ids[1]}") == "pendente")
    s.post(f"{CP}/{ids[1]}/baixar", {"valor_pago": "0", "pago_em": ontem})
    check("I3 baixa com valor zero é recusada", sql(f"select status from contas_pagar where id={ids[1]}") == "pendente")
    s.post(f"{CP}/{ids[1]}/baixar", {})
    b = linhas(f"select status,valor_pago,pago_em,forma_pagamento from contas_pagar where id={ids[1]}")
    check("I4 baixa sem informar nada = valor cheio, hoje (como era)", b == [["paga", "2500.00", HOJE.isoformat(), "pix"]], b)
    s.post(f"{CP}/{ids[0]}/baixar", {"valor_pago": "1", "pago_em": ontem})
    check("I5 dar baixa de novo em conta paga não muda nada", sql(f"select valor_pago from contas_pagar where id={ids[0]}") == "2650.50")

    # ---- J. estorno
    s.post(f"{CP}/{ids[1]}/estornar", {})
    b = linhas(f"select status,valor_pago,ifnull(pago_em,'N') from contas_pagar where id={ids[1]}")
    check("J1 estorno devolve a conta para pendente e limpa o pagamento", b == [["pendente", "0.00", "N"]], b)
    s.post(f"{CP}/{ids[3]}/estornar", {})
    check("J2 estornar conta pendente não faz nada", sql(f"select status,valor from contas_pagar where id={ids[3]}") == "pendente\t2800.00")

    # ---- K. telas
    st, h = s.get(CP + "?vencimento_inicio=2027-01-01&vencimento_fim=2027-01-31")
    check("K1 lista abre e mostra funcionário e imposto como favorecido", st == 200 and nomes["vendedor@demo.com"] in h and "INSS" in h and "Vale-transporte" in h, st)
    check("K2 lista tem Editar e a janela de baixa", "/edit" in h and 'id="modalBaixa"' in h and "data-baixar=" in h)
    st, h = s.get(CP + f"?plano_conta_id={P_INSS}")
    check("K3 filtro por plano de contas", st == 200 and "Guia INSS" in h and "VT dia 31" not in h and ">Salário<" not in h, st)
    st, h = s.get(CP + f"?centro_custo_id={CC}")
    check("K4 filtro por centro de custo", st == 200 and "Guia INSS" not in h and "Salário" in h, st)
    sql("insert into contas_pagar (empresa_id,unidade_id,favorecido_nome,descricao,valor,vencimento,parcela,total_parcelas,status,created_at,updated_at)"
        " values (1,1,'FGTS','Guia vencida',10,'2026-01-10',1,1,'pendente',now(),now())")
    st, h = s.get(CP + "?status=vencida")
    check("K5 filtro Vencida mostra a pendente com vencimento passado (antes vinha vazio)", "Guia vencida" in h and "Compra parcelada" not in h, st)
    st, h = s.get(f"{CP}/{ids[0]}")
    check("K6 detalhe da conta paga: funcionário, plano, competência, valor pago e Estornar", st == 200 and all(x in h for x in ("Funcionário", "3.1.01 — Salários", "12/2026", "2.650,50", "Estornar baixa")), st)
    st, h = s.get(f"{CP}/{ids[3]}")
    check("K7 detalhe da pendente: Dar baixa, Editar e a série de 12", st == 200 and "Dar baixa" in h and "/edit" in h and h.count("/12</td>") >= 12, st)
    st, h = s.get(f"/app/dre?data_inicio={(HOJE - datetime.timedelta(days=3)).isoformat()}&data_fim={HOJE.isoformat()}")
    check("K8 DRE separa 'Salários' (antes caía em 'sem classificação')", st == 200 and "Salários" in h and "sem classificacao" not in h, st)
    st, h = s.get("/app/financeiro/fluxo-caixa?data_inicio=2027-01-01&data_fim=2027-01-31")
    check("K9 fluxo de caixa abre com as contas programadas", st == 200, st)
    st, h = s.get("/app/export/contas-pagar?formato=csv")
    check("K10 exportação traz o favorecido e a competência", st == 200 and "favorecido_label" in h and nomes["vendedor@demo.com"] in h and "INSS" in h, st)

    # ---- L. excluir e isolamento de leitura
    s.post(f"{CP}/{ids[11]}", {}, "DELETE")
    check("L1 excluir conta pendente", sql(f"select deleted_at is not null from contas_pagar where id={ids[11]}") == "1")
    sql(f"insert into contas_pagar (empresa_id,favorecido_nome,descricao,valor,vencimento,parcela,total_parcelas,status,created_at,updated_at)"
        f" values ({emp2},'X','Conta da outra empresa',10,'2027-01-10',1,1,'pendente',now(),now())")
    outra = sql("select id from contas_pagar where descricao='Conta da outra empresa'")
    st, h = s.get(f"{CP}/{outra}")
    check("L2 conta de outra empresa não abre (404)", st == 404, st)
    s.post(f"{CP}/{outra}/baixar", {})
    check("L3 conta de outra empresa não recebe baixa", sql(f"select status from contas_pagar where id={outra}") == "pendente")

    # ---- M. perfil sem financeiro
    v = Sessao("vendedor@demo.com", "vendedor123")
    st, _ = v.get(CP)
    antes = total()
    v.post(CP, {"favorecido_tipo": "outro", "favorecido_nome": "X", "descricao": "VENDEDOR", "valor": "10", "vencimento": "2027-02-01"})
    check("M1 vendedor não entra em Contas a Pagar nem lança", st in (302, 403) or total() == antes and st != 200, st)
    check("M2 nada gravado pelo vendedor", total() == antes)

    print(f"\n{OK} ✅ / {FAIL} ❌")
    sys.exit(1 if FAIL else 0)


if __name__ == "__main__":
    main()
