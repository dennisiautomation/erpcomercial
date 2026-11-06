#!/usr/bin/env python3
"""Validação ✅/❌ — lista /app/vendas: filtro Tipo com os tipos reais + Canal + cartões que respeitam os filtros (06/11/2026).

Antes: Tipo oferecia PDV/Balcão/Online (online não é tipo; faltavam Pedido e Importada) e os cartões do topo
ignoravam tipo/status/busca. Cenário (março/2027, loja 1):
  990001 PDV presencial 10 · 990002 Pedido WhatsApp 20 · 990003 Pedido online 40 · 990004 Balcão 80
  990005 Importada 160 · 990006 PDV CANCELADA 320
Ambiente DESCARTÁVEL:  BASE=http://172.17.0.21:8298 python3 scripts/validar-vendas-filtro-tipo-06nov.py
"""
import http.cookiejar, os, re, subprocess, sys, urllib.error, urllib.parse, urllib.request

BASE = os.environ.get("BASE", "http://172.17.0.21:8298")
OK = FAIL = 0
PER = [("data_inicio", "2027-03-01"), ("data_fim", "2027-03-31")]


def check(nome, cond, obtido=""):
    global OK, FAIL
    OK, FAIL = (OK + 1, FAIL) if cond else (OK, FAIL + 1)
    print(("✅ " if cond else "❌ ") + nome + ("" if cond else f"  (obtido: {str(obtido)[:300]})"))


def sql(q):
    r = subprocess.run(["docker", "exec", "-i", "cs-mysql", "mysql", "-uerp", "-perp", "erp_teste", "-N", "-B"], input=q, capture_output=True, text=True)
    if r.returncode:
        print("SQL ERRO:", r.stderr.strip()[-300:])
    return r.stdout.strip()


class Sessao:
    def __init__(self, email, senha):
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        h = self.get("/login")
        self.post("/login", {"_token": self.tok(h), "email": email, "password": senha})
        h = self.get("/selecionar-unidade")
        self.post("/selecionar-unidade", {"_token": self.tok(h), "unidade_id": 1})

    def get(self, p):
        try:
            with self.op.open(BASE + p) as r:
                return r.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            return e.read().decode("utf-8", "replace")

    def post(self, p, c):
        try:
            self.op.open(urllib.request.Request(BASE + p, data=urllib.parse.urlencode(c).encode()))
        except urllib.error.HTTPError:
            pass

    @staticmethod
    def tok(h):
        m = re.search(r'name="_token" value="([^"]+)"', h) or re.search(r'name="csrf-token" content="([^"]+)"', h)
        return m.group(1) if m else ""


def lista(s, extra=()):
    h = s.get("/app/vendas?" + urllib.parse.urlencode(PER + list(extra)))
    nums = sorted(set(int(n) for n in re.findall(r">\s*#(99000\d)\s*<", h)))
    cards = re.findall(r'<div class="fw-bold fs-[56]">([^<]+)</div>', h)
    return nums, [c.strip() for c in cards], h


def main():
    sql("delete from vendas where numero between 990001 and 990006")
    linhas = [(990001, "pdv", "'presencial'", 10, "concluida"), (990002, "pedido", "'whatsapp'", 20, "concluida"),
              (990003, "pedido", "'online'", 40, "concluida"), (990004, "balcao", "null", 80, "concluida"),
              (990005, "importada", "null", 160, "concluida"), (990006, "pdv", "'presencial'", 320, "cancelada")]
    for n, tipo, canal, tot, st in linhas:
        sql("insert into vendas (empresa_id, unidade_id, vendedor_id, numero, subtotal, total, status, tipo, canal, forma_pagamento, created_at, updated_at) "
            f"values (1, 1, 3, {n}, {tot}, {tot}, '{st}', '{tipo}', {canal}, 'pix', '2027-03-15 10:00:00', '2027-03-15 10:00:00')")
    try:
        d = Sessao("dono@demo.com", "dono123")
        nums, cards, h = lista(d)
        check("sem filtro: as 6 vendas na lista", nums == list(range(990001, 990007)), nums)
        check("cartões sem filtro: 5 concluídas, R$ 310,00, 1 cancelada", cards[:2] == ["5", "R$ 310,00"] and cards[-1] == "1", cards)
        opc = re.findall(r'<option value="([a-z]*)"[^>]*>([^<]+)</option>', h[h.index('id="campo-tipo"'):h.index('id="campo-canal"')])
        check("Tipo oferece PDV, Pedido, Balcão, Importada (sem 'Online')", [o[0] for o in opc] == ["", "pdv", "pedido", "balcao", "importada"], opc)
        check("tem o filtro Canal (Presencial/WhatsApp/Online)", 'name="canal"' in h and ">WhatsApp<" in h)
        for tipo, esperado, fat in [("pdv", [990001, 990006], "R$ 10,00"), ("pedido", [990002, 990003], "R$ 60,00"),
                                    ("balcao", [990004], "R$ 80,00"), ("importada", [990005], "R$ 160,00")]:
            nums, cards, h = lista(d, [("tipo", tipo)])
            check(f"Tipo {tipo}: lista {esperado} e cartão {fat}", nums == esperado and cards[1] == fat, (nums, cards))
        check("Tipo fica selecionado depois de filtrar", re.search(r'<option value="importada"\s+selected', h))
        nums, cards, _ = lista(d, [("tipo", "online")])
        check("tipo inválido 'online' é ignorado (lista tudo)", len(nums) == 6, nums)
        nums, cards, _ = lista(d, [("canal", "whatsapp")])
        check("Canal WhatsApp: só 990002 / R$ 20,00", nums == [990002] and cards[1] == "R$ 20,00", (nums, cards))
        nums, cards, _ = lista(d, [("canal", "online")])
        check("Canal Online: só 990003", nums == [990003], nums)
        nums, cards, _ = lista(d, [("tipo", "pedido"), ("canal", "presencial")])
        check("Pedido + Presencial: nenhuma", nums == [], nums)
        nums, cards, _ = lista(d, [("status", "cancelada")])
        check("Status cancelada: lista só 990006, cartões 0 / R$ 0,00 / 1", nums == [990006] and cards[:2] == ["0", "R$ 0,00"] and cards[-1] == "1", (nums, cards))
        nums, cards, _ = lista(d, [("busca", "990004")])
        check("Busca 990004: cartão R$ 80,00 (antes o cartão ignorava a busca)", nums == [990004] and cards[1] == "R$ 80,00", (nums, cards))
        nums, _, h = lista(d, [("tipo", "pedido")])
        check("badge PEDIDO + canal embaixo (WhatsApp)", "PEDIDO</span>" in h and re.search(r'<div class="small text-muted"><i class="bi bi-whatsapp me-1"></i>WhatsApp</div>', h))
        _, _, h = lista(d, [("tipo", "importada")])
        check("badge IMPORTADA", "IMPORTADA</span>" in h)
    finally:
        sql("delete from vendas where numero between 990001 and 990006")
    print(f"\n{OK} ✅ / {FAIL} ❌")
    sys.exit(1 if FAIL else 0)


if __name__ == "__main__":
    main()
