#!/usr/bin/env python3
"""Validação ✅/❌ — filtro ORIGEM (Loja / WhatsApp / Online / Histórico) na lista de Vendas e nos dois relatórios (06/11/2026).

Substitui Tipo + Canal (quase redundantes). Cenário (março/2027, loja 1):
  990001 PDV presencial 10 · 990002 Pedido WhatsApp 20 · 990003 Pedido online 40 · 990004 Balcão presencial 80
  990005 Importada 160 · 990006 PDV CANCELADA 320 · 990007 Pedido presencial 5 · 990008 PDV sem canal 1
Loja = 990001, 990004, 990006, 990007, 990008 (concluídas 96).
Ambiente DESCARTÁVEL:  BASE=http://172.17.0.21:8298 python3 scripts/validar-vendas-origem-06nov.py
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
    cards = [c.strip() for c in re.findall(r'<div class="fw-bold fs-[56]">([^<]+)</div>', h)]
    return nums, cards, h


def fat_relatorio(s, extra=()):
    h = s.get("/app/relatorios/vendas?" + urllib.parse.urlencode(PER + [("lojas[]", 1)] + list(extra)))
    m = re.search(r'kpi-label">Faturamento</div>\s*<div class="kpi-value[^"]*">R\$ ([\d.,]+)<', h)
    return (m.group(1) if m else None), h


def main():
    sql("delete from vendas where numero between 990001 and 990008")
    linhas = [(990001, "pdv", "'presencial'", 10, "concluida"), (990002, "pedido", "'whatsapp'", 20, "concluida"),
              (990003, "pedido", "'online'", 40, "concluida"), (990004, "balcao", "'presencial'", 80, "concluida"),
              (990005, "importada", "null", 160, "concluida"), (990006, "pdv", "'presencial'", 320, "cancelada"),
              (990007, "pedido", "'presencial'", 5, "concluida"), (990008, "pdv", "null", 1, "concluida")]
    for n, tipo, canal, tot, st in linhas:
        sql("insert into vendas (empresa_id, unidade_id, vendedor_id, numero, subtotal, total, status, tipo, canal, forma_pagamento, created_at, updated_at) "
            f"values (1, 1, 3, {n}, {tot}, {tot}, '{st}', '{tipo}', {canal}, 'pix', '2027-03-15 10:00:00', '2027-03-15 10:00:00')")
    try:
        d = Sessao("dono@demo.com", "dono123")
        print("== Lista /app/vendas")
        nums, cards, h = lista(d)
        check("sem filtro: as 8 vendas, 7 concluídas R$ 316,00, 1 cancelada", len(nums) == 8 and cards[:2] == ["7", "R$ 316,00"] and cards[-1] == "1", (nums, cards))
        opc = re.findall(r'<option value="([a-z]*)"[^>]*>([^<]+)</option>', h[h.index('id="campo-origem"'):h.index('id="campo-data_inicio"')])
        check("Origem: Todas, Loja, WhatsApp, Online, Histórico importado", [o[1].strip() for o in opc] == ["Todas", "Loja", "WhatsApp", "Online", "Histórico importado"], opc)
        check("não há mais os filtros Tipo e Canal", 'name="tipo"' not in h and 'name="canal"' not in h)
        casos = [("loja", [990001, 990004, 990006, 990007, 990008], "R$ 96,00"), ("whatsapp", [990002], "R$ 20,00"),
                 ("online", [990003], "R$ 40,00"), ("importada", [990005], "R$ 160,00")]
        for origem, esperado, fat in casos:
            nums, cards, h = lista(d, [("origem", origem)])
            check(f"lista origem {origem}: {esperado} / {fat}", nums == esperado and cards[1] == fat, (nums, cards))
        check("origem fica selecionada", re.search(r'<option value="importada"\s+selected', h))
        nums, _, _ = lista(d, [("origem", "xyz")])
        check("origem inválida = todas", len(nums) == 8, nums)
        _, _, h = lista(d, [("origem", "whatsapp")])
        check("selo WhatsApp + 'Pedido' embaixo", re.search(r'bi-whatsapp me-1"></i>WhatsApp</span>\s*<div class="small text-muted">Pedido</div>', h), "")
        _, _, h = lista(d, [("origem", "loja")])
        check("selo Loja + 'Balcão' / 'PDV' embaixo", ">Balcão</div>" in h and ">PDV</div>" in h and "bi-shop me-1\"></i>Loja</span>" in h)
        _, _, h = lista(d, [("origem", "importada")])
        check("selo Histórico sem tipo embaixo", "bi-archive me-1\"></i>Histórico</span>" in h and ">Importada" not in h)
        check("cabeçalho da coluna = Origem", '<th class="text-center">Origem</th>' in h)

        print("== Relatório de Vendas (loja 1)")
        for origem, fat in [("", "316,00"), ("loja", "96,00"), ("whatsapp", "20,00"), ("online", "40,00"), ("importada", "160,00"), ("pedidos", "65,00"), ("vendas", "251,00")]:
            f, h = fat_relatorio(d, [("origem", origem)] if origem else [])
            check(f"relatório origem '{origem or 'todas'}' = {fat}", f == fat, f)
        opc = re.findall(r'<option value="([a-z]*)"', h[h.index('id="campo-origem"'):h.index('id="campo-vendedor_id"')])
        check("relatório: opções Loja/WhatsApp/Online/Histórico", opc == ["", "loja", "whatsapp", "online", "importada"], opc)
        print("== Produtos vendidos")
        h = d.get("/app/relatorios/produtos?" + urllib.parse.urlencode(PER + [("origem", "whatsapp")]))
        opc = re.findall(r'<option value="([a-z]*)"', h[h.index('id="campo-origem"'):h.index('id="campo-forma"')])
        check("produtos: mesmas opções e WhatsApp selecionado", opc == ["", "loja", "whatsapp", "online", "importada"] and re.search(r'<option value="whatsapp"\s+selected', h), opc)
        check("produtos: cabeçalho do impresso diz 'Origem: WhatsApp'", "Origem: WhatsApp" in h)
    finally:
        sql("delete from vendas where numero between 990001 and 990008")
    print(f"\n{OK} ✅ / {FAIL} ❌")
    sys.exit(1 if FAIL else 0)


if __name__ == "__main__":
    main()
