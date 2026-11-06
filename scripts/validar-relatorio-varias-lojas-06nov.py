#!/usr/bin/env python3
"""Validação ✅/❌ — Relatório de Vendas com VÁRIAS lojas marcadas (06/11/2026, pedido STILO/Pedro).

Antes: o filtro Loja aceitava uma loja ou "Todas". Agora `lojas[]` aceita quantas o perfil pode ver,
sem trocar a loja da sessão; `loja=todas` / `loja=ID` (links antigos) continuam.

Ambiente DESCARTÁVEL (nunca produção):
    BASE=http://127.0.0.1:8297 DB_CT=cs-mysql APP_CT=cs-app python3 scripts/validar-relatorio-varias-lojas-06nov.py
"""
import http.cookiejar
import os
import re
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request

BASE = os.environ.get("BASE", "http://127.0.0.1:8297")
DB_CT = os.environ.get("DB_CT", "cs-mysql")
OK = FAIL = 0
PERIODO = [("data_inicio", "2027-03-01"), ("data_fim", "2027-03-31")]


def check(nome, cond, obtido=""):
    global OK, FAIL
    OK, FAIL = (OK + 1, FAIL) if cond else (OK, FAIL + 1)
    print(("✅ " if cond else "❌ ") + nome + ("" if cond else f"  (obtido: {str(obtido)[:300]})"))


def sql(q):
    r = subprocess.run(["docker", "exec", "-i", DB_CT, "mysql", "-uerp", "-perp", "erp_teste", "-N", "-B"],
                       input=q, capture_output=True, text=True)
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

    def req(self, path, campos=None):
        data = urllib.parse.urlencode(campos, doseq=True).encode() if campos is not None else None
        try:
            with self.op.open(urllib.request.Request(BASE + path, data=data)) as r:
                return r.status, r.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", "replace")

    @staticmethod
    def token(html):
        m = re.search(r'name="_token" value="([^"]+)"', html) or re.search(r'name="csrf-token" content="([^"]+)"', html)
        return m.group(1) if m else ""


def rel(s, extra=()):
    st, html = s.req("/app/relatorios/vendas?" + urllib.parse.urlencode(PERIODO + list(extra)))
    m = re.search(r'kpi-label">Faturamento</div>\s*<div class="kpi-value[^"]*">R\$ ([\d.,]+)<', html)
    return st, (m.group(1) if m else None), html


def main():
    sql("delete from vendas where numero between 990001 and 990003")
    sql("delete from unidades where empresa_id=1 and nome in ('Loja Norte (teste)','Loja Sul (teste)')")
    for n in ("Norte", "Sul"):
        sql("insert into unidades (empresa_id, nome, cnpj, cep, logradouro, numero, bairro, cidade, uf, telefone, status, created_at, updated_at) "
            f"values (1, 'Loja {n} (teste)', '', '', '', '', '', '', 'SP', '', 'ativa', now(), now())")
    norte = int(sql("select id from unidades where empresa_id=1 and nome='Loja Norte (teste)'"))
    sul = int(sql("select id from unidades where empresa_id=1 and nome='Loja Sul (teste)'"))
    outras = sql(f"select count(*) from unidades where empresa_id=1 and status='ativa' and id not in (1,{norte},{sul})")
    sql("delete from unidade_user where user_id in (3, 4)")
    sql(f"insert into unidade_user (user_id, unidade_id, created_at, updated_at) values (3,1,now(),now()), (3,{norte},now(),now()), (4,1,now(),now())")
    sql("insert into vendas (empresa_id, unidade_id, vendedor_id, numero, subtotal, total, status, tipo, forma_pagamento, created_at, updated_at) values "
        "(1, 1, 3, 990001, 111, 111, 'concluida', 'pdv', 'dinheiro', '2027-03-15 10:00:00', '2027-03-15 10:00:00'), "
        f"(1, {norte}, 3, 990002, 222, 222, 'concluida', 'pdv', 'dinheiro', '2027-03-15 11:00:00', '2027-03-15 11:00:00'), "
        f"(1, {sul}, 3, 990003, 444, 444, 'concluida', 'pdv', 'dinheiro', '2027-03-15 12:00:00', '2027-03-15 12:00:00')")
    base_outras = sql("select coalesce(sum(total),0) from vendas where empresa_id=1 and status<>'cancelada' "
                      "and created_at between '2027-03-01' and '2027-03-31 23:59:59' and numero not between 990001 and 990003")
    try:
        print(f"== Dono (sessão na loja 1; lojas: 1=111, Norte {norte}=222, Sul {sul}=444; outras ativas: {outras}, vendas fora: {base_outras})")
        d = Sessao("dono@demo.com", "dono123")
        st, f, html = rel(d)
        check("sem filtro = loja da sessão (111)", st == 200 and f == "111,00", (st, f))
        check("tela tem caixas de marcar lojas[] com as 3 lojas",
              all(f'name="lojas[]" value="{i}"' in html for i in (1, norte, sul)), "")
        check("sem filtro: só a loja 1 vem marcada",
              re.search(r'value="1"\s+id="loja-1"[^>]*checked', html) and not re.search(rf'id="loja-{norte}"[^>]*checked', html), "")
        st, f, html = rel(d, [("lojas[]", 1), ("lojas[]", norte)])
        check("loja 1 + Norte = 333", st == 200 and f == "333,00", (st, f))
        check("2 lojas: bloco 'Por loja' aparece", "Por loja</h6>" in html)
        check("2 lojas: cabeçalho lista os nomes", "Loja Norte (teste)," in html or ", Loja Norte (teste)" in html, "")
        check("2 lojas: coluna Loja na lista de vendas", "<th>Loja</th>" in html)
        check("2 lojas: as duas vêm marcadas e a Sul não",
              re.search(rf'id="loja-{norte}"[^>]*checked', html) and not re.search(rf'id="loja-{sul}"[^>]*checked', html), "")
        st, f, _ = rel(d, [("lojas[]", norte), ("lojas[]", sul)])
        check("Norte + Sul = 666 (sem a loja da sessão)", st == 200 and f == "666,00", (st, f))
        st, f, html = rel(d, [("lojas[]", sul)])
        check("só a Sul = 444, sem bloco 'Por loja'", st == 200 and f == "444,00" and "Por loja</h6>" not in html, (st, f))
        st, f, _ = rel(d, [("lojas[]", 1), ("lojas[]", norte), ("lojas[]", sul)])
        esperado_todas = 777 + float(base_outras or 0)
        check("as 3 marcadas = 777", st == 200 and f == "777,00", (st, f))
        st, f, html = rel(d, [("loja", "todas")])
        check("link antigo loja=todas = todas as lojas ativas",
              st == 200 and f == f"{esperado_todas:,.2f}".replace(",", "X").replace(".", ",").replace("X", "."), (st, f, esperado_todas))
        check("loja=todas: cabeçalho 'Todas as lojas'", "Todas as lojas" in html)
        st, f, _ = rel(d, [("loja", norte)])
        check("link antigo loja=Norte = 222", st == 200 and f == "222,00", (st, f))
        st, f, _ = rel(d, [("lojas[]", 999999)])
        check("id inexistente é ignorado → loja da sessão (111)", st == 200 and f == "111,00", (st, f))
        st, f, _ = rel(d, [("lojas[]", norte), ("lojas[]", 999999)])
        check("Norte + id inexistente = só Norte (222)", st == 200 and f == "222,00", (st, f))
        st, html = d.req("/app/relatorios/vendas")
        check("dono: depois do relatório a sessão continua na loja 1 (sem filtro = loja 1 marcada)",
              re.search(r'id="loja-1"[^>]*checked', html) is not None, "")

        print("== Gerente vinculado a loja 1 + Norte (não à Sul)")
        g = Sessao("gerente@demo.com", "gerente123")
        st, f, html = rel(g)
        check("gerente: sem filtro = 111", st == 200 and f == "111,00", (st, f))
        check("gerente: a Sul NÃO aparece na lista", f'value="{sul}"' not in html and f'value="{norte}"' in html, "")
        st, f, _ = rel(g, [("lojas[]", 1), ("lojas[]", norte)])
        check("gerente: 1 + Norte = 333", st == 200 and f == "333,00", (st, f))
        st, f, _ = rel(g, [("lojas[]", norte), ("lojas[]", sul)])
        check("gerente: Norte + Sul (não vinculada) = só Norte 222", st == 200 and f == "222,00", (st, f))
        st, f, _ = rel(g, [("lojas[]", sul)])
        check("gerente: só a Sul (não vinculada) → loja da sessão 111", st == 200 and f == "111,00", (st, f))
        st, f, _ = rel(g, [("loja", "todas")])
        check("gerente: loja=todas = só as vinculadas 333", st == 200 and f == "333,00", (st, f))

        print("== Vendedor: preso à loja da sessão")
        v = Sessao("vendedor@demo.com", "vendedor123")
        st, f, html = rel(v, [("lojas[]", 1), ("lojas[]", norte), ("lojas[]", sul)])
        check("vendedor: marcar 3 lojas na URL = só a da sessão (111)", st == 200 and f == "111,00", (st, f))
        check("vendedor: sem o filtro de loja na tela", 'name="lojas[]"' not in html)
    finally:
        sql("delete from vendas where numero between 990001 and 990003")
        sql(f"delete from unidade_user where unidade_id in ({norte},{sul})")
        sql("delete from unidade_user where user_id in (3, 4)")
        sql("insert into unidade_user (user_id, unidade_id, created_at, updated_at) values (3,1,now(),now()), (4,1,now(),now())")
        sql(f"delete from unidades where id in ({norte},{sul})")
    print(f"\n{OK} ✅ / {FAIL} ❌")
    sys.exit(1 if FAIL else 0)


if __name__ == "__main__":
    main()
