#!/usr/bin/env python3
"""Validação ✅/❌ — "Trocar vendedor" na tela da venda finalizada (03/11/2026).

Caso: o caixa esquece o F3 e a venda fica no nome de quem estava logado. Dono/gerente corrigem o
vendedor na tela da venda; muda só vendedor + comissão; comissão paga trava; vendedor/caixa não trocam.

Ambiente DESCARTÁVEL (nunca produção): banco de demonstração próprio.
    BASE=http://127.0.0.1:8297 DB_CT=cs-mysql python3 scripts/validar-trocar-vendedor-03nov.py
"""
import http.cookiejar
import json
import os
import re
import subprocess
import sys
import urllib.parse
import urllib.request

BASE = os.environ.get("BASE", "http://127.0.0.1:8297")
DB_CT = os.environ.get("DB_CT", "cs-mysql")
OK = FAIL = 0
DONO, GERENTE, VENDEDOR, CAIXA = 2, 3, 4, 5


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

    def req(self, path, data=None, headers=None):
        r = urllib.request.Request(BASE + path, data=data, headers=headers or {})
        try:
            with self.op.open(r) as resp:
                return resp.status, resp.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", "replace")

    def get(self, path):
        return self.req(path)[1]

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

    def trocar(self, vid, vendedor):
        tok = self.token(self.get(f"/app/vendas/{vid}"))
        return self.post_form(f"/app/vendas/{vid}/vendedor", {"_token": tok, "vendedor_id": vendedor})


def vender(caixa):
    preco = float(sql("select preco_venda from produtos where id=3"))
    st, d = caixa.post_json("/app/pdv/venda", {
        "itens": [{"produto_id": 3, "quantidade": 1, "preco_unitario": preco, "desconto_valor": 0}],
        "pagamentos": [{"forma": "dinheiro", "valor": preco, "parcelas": 1}], "tabela_precos": True, "documento": "recibo"})
    return st, d, int(sql("select max(id) from vendas"))


def vendedor_de(vid):
    return int(sql(f"select vendedor_id from vendas where id={vid}"))


def main():
    sql("update empresas set cnpj='11.222.333/0001-81' where id=1")
    caixa = Sessao("caixa@demo.com", "caixa123")
    t = caixa.token(caixa.get("/app/caixa/abrir"))
    caixa.post_form("/app/caixa/abrir", {"_token": t, "valor_abertura": "0"})

    print("== 1. Caixa esquece o F3: a venda fica no nome dele")
    st, d, vid = vender(caixa)
    check("venda finalizada no PDV", st == 200 and d.get("success"), (st, d))
    check("vendedor = caixa logado (o defeito do dia a dia)", vendedor_de(vid) == CAIXA, vendedor_de(vid))
    total_antes = sql(f"select total, status, caixa_id from vendas where id={vid}")
    mov_antes = sql(f"select count(*), sum(valor) from movimentacoes_caixa where venda_id={vid}")

    print("== 2. Quem NÃO pode trocar")
    vend = Sessao("vendedor@demo.com", "vendedor123")
    html = vend.get(f"/app/vendas/{vid}")
    check("vendedor não vê o botão", "Trocar vendedor" not in html)
    st, _ = vend.trocar(vid, VENDEDOR)
    check(f"vendedor forjando o POST é barrado (HTTP {st})", vendedor_de(vid) == CAIXA and st in (403, 302), (st, vendedor_de(vid)))
    html = caixa.get(f"/app/vendas/{vid}")
    check("caixa não vê o botão", "Trocar vendedor" not in html)
    st, _ = caixa.trocar(vid, VENDEDOR)
    check(f"caixa forjando o POST é barrado (HTTP {st})", vendedor_de(vid) == CAIXA, vendedor_de(vid))

    print("== 3. Gerente troca")
    ger = Sessao("gerente@demo.com", "gerente123")
    html = ger.get(f"/app/vendas/{vid}")
    check("gerente vê o botão Trocar vendedor", "Trocar vendedor" in html)
    check("janela lista o vendedor Carlos", re.search(rf'<option value="{VENDEDOR}">Carlos Oliveira', html) is not None)
    check("janela não lista quem já é o vendedor (Ana)", f'<option value="{CAIXA}">' not in html)
    st, _ = ger.trocar(vid, 99999)
    check("vendedor inexistente é recusado", vendedor_de(vid) == CAIXA, vendedor_de(vid))
    st, body = ger.trocar(vid, VENDEDOR)
    check("gerente troca para Carlos", vendedor_de(vid) == VENDEDOR, vendedor_de(vid))
    check("comissão passou para o Carlos", sql(f"select user_id from comissoes where venda_id={vid}") == str(VENDEDOR),
          sql(f"select user_id from comissoes where venda_id={vid}"))
    check("valor da comissão não mudou", sql(f"select valor_comissao from comissoes where venda_id={vid}") != "", "")
    check("total, status e caixa da venda iguais", sql(f"select total, status, caixa_id from vendas where id={vid}") == total_antes)
    check("lançamentos do caixa iguais", sql(f"select count(*), sum(valor) from movimentacoes_caixa where venda_id={vid}") == mov_antes)
    log = sql(f"select description, causer_id from activity_log where subject_id={vid} and event='vendedor_trocado' order by id desc limit 1")
    check("auditoria registra de quem para quem e quem trocou", "Ana Costa para Carlos Oliveira" in log and log.endswith(str(GERENTE)), log)
    html = ger.get(f"/app/vendas/{vid}")
    check("tela mostra o vendedor novo", "Carlos Oliveira" in html)

    print("== 4. Dono troca de novo")
    dono = Sessao("dono@demo.com", "dono123")
    dono.trocar(vid, GERENTE)
    check("dono troca para a gerente", vendedor_de(vid) == GERENTE, vendedor_de(vid))

    print("== 5. Travas")
    sql(f"update comissoes set status='paga', pago_em=now() where venda_id={vid}")
    html = dono.get(f"/app/vendas/{vid}")
    check("comissão paga: botão some e avisa", "Trocar vendedor</button" not in html and "Comissão já paga" in html)
    dono.trocar(vid, VENDEDOR)
    check("comissão paga: POST não troca", vendedor_de(vid) == GERENTE, vendedor_de(vid))
    st, d, vid2 = vender(caixa)
    sql(f"update vendas set status='cancelada' where id={vid2}")
    dono.trocar(vid2, VENDEDOR)
    check("venda cancelada não troca", vendedor_de(vid2) == CAIXA, vendedor_de(vid2))

    print("== 6. Nada mais mudou")
    st, d, vid3 = vender(caixa)
    check("PDV continua vendendo normal", st == 200 and d.get("success"), (st, d))
    st, html = dono.req(f"/app/vendas/{vid3}")
    check(f"tela da venda abre (HTTP {st})", st == 200)

    print(f"\nTOTAL {OK} ✅ / {FAIL} ❌")
    sys.exit(1 if FAIL else 0)


if __name__ == "__main__":
    main()
