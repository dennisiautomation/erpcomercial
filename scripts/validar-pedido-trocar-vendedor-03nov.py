#!/usr/bin/env python3
"""Validação ✅/❌ — "Trocar vendedor" no pedido ainda não faturado (03/11/2026).

Caso (print da STILO): pedido #6 Confirmado no nome da Jessiane, sem como corrigir — a edição só vale
para rascunho. Agora dono/gerente trocam na tela do pedido; ao faturar a venda sai com o vendedor novo.

Ambiente DESCARTÁVEL (nunca produção):
    BASE=http://127.0.0.1:8297 DB_CT=cs-mysql python3 scripts/validar-pedido-trocar-vendedor-03nov.py
"""
import http.cookiejar
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

    def req(self, path, data=None):
        try:
            with self.op.open(urllib.request.Request(BASE + path, data=data)) as resp:
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

    def trocar(self, pid, vendedor):
        tok = self.token(self.get(f"/app/pedidos/{pid}"))
        return self.post_form(f"/app/pedidos/{pid}/vendedor", {"_token": tok, "vendedor_id": vendedor})


def pedido(status, vendedor=CAIXA):
    sql("insert into pedidos (empresa_id, unidade_id, vendedor_id, numero, subtotal, desconto_percentual, desconto_valor, total, status, canal, created_at, updated_at) "
        f"values (1, 1, {vendedor}, (select coalesce(max(p.numero),0)+1 from pedidos p), 30, 0, 0, 30, '{status}', 'whatsapp', now(), now())")
    pid = int(sql("select max(id) from pedidos"))
    sql(f"insert into pedido_itens (pedido_id, produto_id, quantidade, preco_unitario, desconto_percentual, desconto_valor, total, created_at, updated_at) "
        f"values ({pid}, 3, 1, 30, 0, 0, 30, now(), now())")
    return pid


def vendedor_de(pid):
    return int(sql(f"select vendedor_id from pedidos where id={pid}"))


def main():
    sql("update empresas set cnpj='11.222.333/0001-81' where id=1")
    pid = pedido("confirmado")
    total_antes = sql(f"select total, status from pedidos where id={pid}")

    print("== 1. Pedido CONFIRMADO no nome errado (o caso do print)")
    dono = Sessao("dono@demo.com", "dono123")
    st, html = dono.req(f"/app/pedidos/{pid}")
    check(f"tela do pedido abre (HTTP {st})", st == 200)
    check("dono vê o botão Trocar vendedor", "Trocar vendedor" in html)
    check("janela lista o Carlos e não lista quem já é (Ana)",
          re.search(rf'<option value="{VENDEDOR}">Carlos Oliveira', html) is not None and f'<option value="{CAIXA}">' not in html)

    print("== 2. Quem NÃO pode")
    for email, senha, nome in [("vendedor@demo.com", "vendedor123", "vendedor"), ("caixa@demo.com", "caixa123", "caixa")]:
        s = Sessao(email, senha)
        st, html = s.req(f"/app/pedidos/{pid}")
        check(f"{nome} abre o pedido mas não vê o botão (HTTP {st})", st == 200 and "Trocar vendedor" not in html, st)
        st, _ = s.trocar(pid, VENDEDOR)
        check(f"{nome} forjando o POST é barrado (HTTP {st})", st == 403 and vendedor_de(pid) == CAIXA, (st, vendedor_de(pid)))

    print("== 3. Troca")
    dono.trocar(pid, 99999)
    check("vendedor inexistente é recusado", vendedor_de(pid) == CAIXA, vendedor_de(pid))
    Sessao("gerente@demo.com", "gerente123").trocar(pid, VENDEDOR)
    check("gerente troca para o Carlos", vendedor_de(pid) == VENDEDOR, vendedor_de(pid))
    check("total e status do pedido iguais", sql(f"select total, status from pedidos where id={pid}") == total_antes)
    log = sql(f"select description, causer_id from activity_log where subject_type like '%Pedido' and subject_id={pid} and event='vendedor_trocado' order by id desc limit 1")
    check("auditoria registra de quem para quem e quem trocou", "Ana Costa para Carlos Oliveira" in log and log.endswith(str(GERENTE)), log)
    dono.trocar(pid, DONO)
    check("dono troca de novo (para ele mesmo)", vendedor_de(pid) == DONO, vendedor_de(pid))

    print("== 4. Rascunho também; faturado/cancelado não")
    pr = pedido("rascunho")
    dono.trocar(pr, VENDEDOR)
    check("rascunho troca", vendedor_de(pr) == VENDEDOR, vendedor_de(pr))
    for stt in ("faturado", "cancelado", "entregue"):
        px = pedido(stt)
        st, html = dono.req(f"/app/pedidos/{px}")
        dono.trocar(px, VENDEDOR)
        check(f"{stt}: sem botão e não troca", "Trocar vendedor" not in html and vendedor_de(px) == CAIXA, vendedor_de(px))

    print("== 5. Faturar leva o vendedor novo para a venda")
    caixa = Sessao("caixa@demo.com", "caixa123")
    t = caixa.token(caixa.get("/app/caixa/abrir"))
    caixa.post_form("/app/caixa/abrir", {"_token": t, "valor_abertura": "0"})
    pf = pedido("confirmado")
    dono.trocar(pf, VENDEDOR)
    tok = caixa.token(caixa.get(f"/app/pedidos/{pf}"))
    st, _ = caixa.post_form(f"/app/pedidos/{pf}/faturar", {"_token": tok, "forma_pagamento": "dinheiro", "documento": "recibo"})
    vend_venda = sql(f"select vendedor_id from vendas where pedido_id={pf}")
    check(f"caixa fatura e a venda nasce com o Carlos (HTTP {st})", vend_venda == str(VENDEDOR), vend_venda)

    print(f"\nTOTAL {OK} ✅ / {FAIL} ❌")
    sys.exit(1 if FAIL else 0)


if __name__ == "__main__":
    main()
