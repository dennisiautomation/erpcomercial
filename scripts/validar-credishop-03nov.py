#!/usr/bin/env python3
"""Validação ✅/❌ — forma de pagamento Credishop no PDV, só para empresa com a chave (03/11/2026).

Pedido do Dennis: incluir Credishop no PDV apenas para a STILO VINTE. Chave nova
`empresas.pdv_credishop` (nasce desligada); Credishop conta como cartão de crédito no preço,
no acréscimo, na NFC-e (tPag 03) e no cupom automático.

Ambiente DESCARTÁVEL (nunca produção): banco de demonstração próprio.
    BASE=http://127.0.0.1:8297 DB_CT=cs-mysql APP_CT=cs-app python3 scripts/validar-credishop-03nov.py
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
APP_CT = os.environ.get("APP_CT", "cs-app")
OK = FAIL = 0

PID, PIX, CARTAO = 3, 25.00, 27.50


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


def tinker(php):
    r = subprocess.run(["docker", "exec", APP_CT, "php", "/var/www/artisan", "tinker", "--execute", php],
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


def venda(s, preco, pagamentos):
    corpo = {"itens": [{"produto_id": PID, "quantidade": 1, "preco_unitario": preco, "desconto_valor": 0}],
             "pagamentos": pagamentos, "tabela_precos": True, "documento": "recibo"}
    return s.post_json("/app/pdv/venda", corpo)


def preparar():
    sql("delete from produto_precos")
    sql(f"update produtos set preco_venda={PIX}, preco_custo=0, markup=0, status='ativo' where id={PID}")
    sql("insert into produto_precos (empresa_id, produto_id, modalidade, valor, created_at, updated_at) values "
        f"(1,{PID},'debito',{CARTAO},now(),now()),(1,{PID},'credito',{CARTAO},now(),now())")
    if not sql("select id from configuracoes_loja where unidade_id=1"):
        sql("insert into configuracoes_loja (empresa_id, unidade_id, created_at, updated_at) values (1,1,now(),now())")
    sql("update configuracoes_loja set preco_base='avista', desconto_avista_percentual=0, "
        "regra_preco_split='cartao_maior', percentual_debito=0, percentual_credito=0")


def ultima_venda():
    linha = sql("select id, forma_pagamento, total from vendas order by id desc limit 1").split("\t")
    return int(linha[0]), linha[1], float(linha[2])


def main():
    preparar()
    sql("update empresas set pdv_credishop=0 where id=1")
    # o seeder grava um CNPJ que não passa na validação do admin
    sql("update empresas set cnpj='11.222.333/0001-81' where id=1")
    dono = Sessao("dono@demo.com", "dono123")
    caixa = Sessao("caixa@demo.com", "caixa123")
    t = caixa.token(caixa.get("/app/caixa/abrir"))
    caixa.post_form("/app/caixa/abrir", {"_token": t, "valor_abertura": "0"})

    print("== 1. Empresa SEM a chave (todas as outras): nada muda")
    html = caixa.get("/app/pdv")
    check("PDV abre (botões de pagamento presentes)", 'data-forma="dinheiro"' in html, html[:120])
    check("PDV sem botão Credishop", 'data-forma="credishop"' not in html)
    st, d = venda(caixa, CARTAO, [{"forma": "credishop", "valor": CARTAO, "parcelas": 1}])
    check("venda com Credishop forjada é recusada (422)", st == 422, (st, d))
    st, d = venda(caixa, PIX, [{"forma": "dinheiro", "valor": PIX, "parcelas": 1}])
    check("venda no dinheiro continua normal (25,00)", st == 200 and d.get("success"), (st, d))
    st, d = venda(caixa, CARTAO, [{"forma": "cartao_credito", "valor": CARTAO, "parcelas": 1}])
    check("venda no crédito continua normal (27,50)", st == 200 and d.get("success"), (st, d))

    print("== 2. Admin liga a chave na empresa")
    admin = Sessao("admin@ia365.com.br", "admin123")
    html = admin.get("/admin/empresas/1/edit")
    check("admin: chave 'Credishop' aparece desligada", 'id="pdv_credishop"' in html and
          not re.search(r'id="pdv_credishop"[^>]*checked', html, re.S))
    form = {}
    for m in re.finditer(r'<input[^>]*>', html):
        tag = m.group(0)
        n = re.search(r'name="([^"]+)"', tag)
        if not n or 'type="file"' in tag:
            continue
        if ('type="checkbox"' in tag or 'type="radio"' in tag) and 'checked' not in tag:
            continue
        v = re.search(r'value="([^"]*)"', tag)
        form[n.group(1)] = v.group(1) if v else ""
    for m in re.finditer(r'<select[^>]*name="([^"]+)"[^>]*>(.*?)</select>', html, re.S):
        sel = re.search(r'<option[^>]*value="([^"]*)"[^>]*selected', m.group(2)) or re.search(r'<option[^>]*value="([^"]*)"', m.group(2))
        form[m.group(1)] = sel.group(1) if sel else ""
    for m in re.finditer(r'<textarea[^>]*name="([^"]+)"[^>]*>(.*?)</textarea>', html, re.S):
        form[m.group(1)] = m.group(2)
    form["_method"] = "PUT"
    form["pdv_credishop"] = "1"
    st, _ = admin.post_form("/admin/empresas/1", form)
    check(f"admin salva com a chave ligada (HTTP {st})", sql("select pdv_credishop from empresas where id=1") == "1",
          sql("select pdv_credishop from empresas where id=1"))
    outras = sql("select count(*) from empresas where id<>1 and pdv_credishop=1")
    check("nenhuma outra empresa ligou", outras in ("0", ""), outras)

    print("== 3. Empresa COM a chave: Credishop no PDV")
    html = caixa.get("/app/pdv")
    check("PDV com botão Credishop", 'data-forma="credishop"' in html)
    check("JS: Credishop usa a tabela do crédito", "credishop: 'credito'" in html)
    st, d = venda(caixa, CARTAO, [{"forma": "credishop", "valor": CARTAO, "parcelas": 1}])
    check("venda no Credishop finaliza", st == 200 and d.get("success"), (st, d))
    vid, forma, total = ultima_venda()
    check("venda gravada com forma 'credishop'", forma == "credishop", forma)
    check(f"Credishop cobra o preço do cartão ({CARTAO:.2f})", abs(total - CARTAO) < 0.005, total)
    mov = sql(f"select forma_pagamento, valor from movimentacoes_caixa where venda_id={vid}")
    check("lançamento no caixa com forma credishop", mov.startswith("credishop\t27.50"), mov)
    cr = sql(f"select forma_pagamento from contas_receber where venda_id={vid}")
    check("conta a receber gerada como credishop", "credishop" in cr, cr)
    st, d = venda(caixa, PIX, [{"forma": "credishop", "valor": PIX, "parcelas": 1}])
    check("Credishop pelo preço do dinheiro é recusado (pagamento insuficiente)", st == 422 or not d.get("success"), (st, d))
    st, d = venda(caixa, CARTAO, [{"forma": "dinheiro", "valor": 10, "parcelas": 1},
                                   {"forma": "credishop", "valor": CARTAO - 10, "parcelas": 1}])
    check("split dinheiro + Credishop fecha no preço do cartão (cartao_maior)", st == 200 and d.get("success"), (st, d))

    print("== 4. Fiscal, rótulos e fechamento")
    out = tinker(f"echo json_encode(app(App\\Services\\FocusNFe\\FiscalPayloadBuilder::class)->formasPagamento(App\\Models\\Venda::withoutGlobalScopes()->find({vid})));")
    check("NFC-e: Credishop vai como forma 03 (cartão de crédito)", '"forma_pagamento":"03"' in out, out[:200])
    check("NFC-e: bloco de cartão (bandeira) preenchido", '"bandeira_operadora"' in out, out[:200])
    out = tinker("echo App\\Models\\MovimentacaoCaixa::FORMAS_LABELS['credishop'] ?? 'x';")
    check("rótulo 'Credishop' nos relatórios de caixa", out.endswith("Credishop"), out)
    cid = sql("select id from caixas where status='aberto' order by id desc limit 1")
    html = caixa.get(f"/app/caixa/{cid}/fechar")
    check("fechamento do caixa lista Credishop para conferir", "Credishop" in html, re.sub(r"\s+", " ", html)[:150])
    st, html = caixa.req(f"/app/pdv/venda/{vid}/recibo")
    check(f"recibo da venda mostra Credishop (HTTP {st})", "Credishop" in html)

    print("== 5. Desligar a chave volta ao normal")
    sql("update empresas set pdv_credishop=0 where id=1")
    html = caixa.get("/app/pdv")
    check("PDV sem botão depois de desligar", 'data-forma="credishop"' not in html)
    st, d = venda(caixa, CARTAO, [{"forma": "credishop", "valor": CARTAO, "parcelas": 1}])
    check("venda Credishop recusada de novo (422)", st == 422, (st, d))

    print(f"\nTOTAL {OK} ✅ / {FAIL} ❌")
    sys.exit(1 if FAIL else 0)


if __name__ == "__main__":
    main()
