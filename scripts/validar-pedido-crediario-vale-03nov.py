#!/usr/bin/env python3
"""Validação ✅/❌ — Crediário e Vale no Faturar do pedido (03/11/2026).

Ambiente DESCARTÁVEL (nunca produção):
    BASE=http://127.0.0.1:8297 DB_CT=cs-mysql python3 scripts/validar-pedido-crediario-vale-03nov.py
"""
import http.cookiejar, os, re, subprocess, sys, urllib.parse, urllib.request, urllib.error

BASE = os.environ.get("BASE", "http://127.0.0.1:8297")
DB_CT = os.environ.get("DB_CT", "cs-mysql")
OK = FAIL = 0
SEQ = [0]
RUN = __import__("time").strftime("%H%M%S")


def check(nome, cond, obtido=""):
    global OK, FAIL
    if cond:
        OK += 1; print(f"✅ {nome}")
    else:
        FAIL += 1; print(f"❌ {nome}  (obtido: {obtido})")


def sql(q):
    r = subprocess.run(["docker", "exec", DB_CT, "mysql", "-uerp", "-perp", "erp_teste", "-N", "-B", "-e", q], capture_output=True, text=True)
    return r.stdout.strip()


class Sessao:
    def __init__(self, email, senha):
        self.cj = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cj))
        self.post("/login", {"_token": self.token(self.get("/login")), "email": email, "password": senha})

    def req(self, path, data=None):
        try:
            with self.op.open(urllib.request.Request(BASE + path, data=data)) as r:
                return r.status, r.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", "replace")

    def get(self, path):
        return self.req(path)[1]

    @staticmethod
    def token(html):
        m = re.search(r'name="_token" value="([^"]+)"', html) or re.search(r'name="csrf-token" content="([^"]+)"', html)
        return m.group(1) if m else ""

    def post(self, path, campos):
        return self.req(path, urllib.parse.urlencode(campos, doseq=True).encode())

    def faturar(self, pid, **campos):
        tok = self.token(self.get(f"/app/pedidos/{pid}"))
        d = {"_token": tok, "documento": "nenhum"}
        d.update(campos)
        return self.post(f"/app/pedidos/{pid}/faturar", d)


def pedido(total=30):
    sql("insert into pedidos (empresa_id, unidade_id, vendedor_id, numero, subtotal, desconto_percentual, desconto_valor, total, status, canal, created_at, updated_at) "
        f"values (1, 1, 5, (select coalesce(max(p.numero),0)+1 from pedidos p), {total}, 0, 0, {total}, 'confirmado', 'whatsapp', now(), now())")
    pid = int(sql("select max(id) from pedidos"))
    sql(f"insert into pedido_itens (pedido_id, produto_id, descricao, quantidade, preco_unitario, desconto_percentual, desconto_valor, total, created_at, updated_at) "
        f"values ({pid}, 3, 'Produto teste', 1, {total}, 0, 0, {total}, now(), now())")
    return pid


def vale(saldo, status="ativo", empresa=1, validade="null"):
    SEQ[0] += 1
    cod = "VT-" + "".join(__import__("random").choices("ABCDEFGHJKMNPQRSTUVWXYZ", k=4)) + "-" + "".join(__import__("random").choices("ABCDEFGHJKMNPQRSTUVWXYZ", k=4))
    sql("insert into vales (empresa_id, unidade_id, user_id, codigo, valor, saldo, validade, status, created_at, updated_at) "
        f"values ({empresa}, 1, 5, '{cod}', {saldo}, {saldo}, {validade}, '{status}', now(), now())")
    return cod


def status(pid):
    return sql(f"select status from pedidos where id={pid}")


def venda(pid, col):
    return sql(f"select {col} from vendas where pedido_id={pid}")


def main():
    sql("update empresas set cnpj='11.222.333/0001-81' where id=1")
    caixa = Sessao("caixa@demo.com", "caixa123")
    if sql("select count(*) from caixas where status='aberto' and user_id=5") == "0":
        caixa.post("/app/caixa/abrir", {"_token": caixa.token(caixa.get("/app/caixa/abrir")), "valor_abertura": "0"})

    print("== 1. Modal mostra as duas formas novas")
    p0 = pedido()
    html = caixa.get(f"/app/pedidos/{p0}")
    check("opção Crediário no Faturar", 'value="crediario"' in html)
    check("opção Vale no Faturar", 'value="vale"' in html)
    check("campo do código do vale", 'name="vale_codigo"' in html)

    print("== 2. Crediário (igual ao PDV: conta já paga)")
    st, _ = caixa.faturar(p0, forma_pagamento="crediario")
    check("pedido faturado", status(p0) == "faturado", status(p0))
    check("venda forma crediario", venda(p0, "forma_pagamento") == "crediario", venda(p0, "forma_pagamento"))
    vid = venda(p0, "id")
    check("1 entrada no caixa em crediario de 30", sql(f"select forma_pagamento,valor from movimentacoes_caixa where venda_id={vid}") == "crediario\t30.00")
    check("conta a receber paga em crediario", sql(f"select forma_pagamento,status,valor from contas_receber where venda_id={vid}") == "crediario\tpaga\t30.00")

    print("== 3. Vale cobre tudo")
    p1, v1 = pedido(30), vale(50)
    caixa.faturar(p1, forma_pagamento="vale", vale_codigo=v1)
    check("pedido faturado", status(p1) == "faturado", status(p1))
    check("saldo do vale 50 → 20, segue ativo", sql(f"select saldo,status from vales where codigo='{v1}'") == "20.00\tativo")
    check("uso 'venda' de 30 ligado à venda", sql(f"select tipo,valor from vale_usos where venda_id={venda(p1,'id')}") == "venda\t30.00")
    check("venda forma vale; detalhe leva código e saldo restante",
          venda(p1, "forma_pagamento") == "vale" and v1 in venda(p1, "pagamento_detalhes") and "20" in venda(p1, "pagamento_detalhes"), venda(p1, "pagamento_detalhes"))

    print("== 4. Vale com saldo MENOR sem complemento: recusa e nada muda")
    p2, v2 = pedido(30), vale(10)
    caixa.faturar(p2, forma_pagamento="vale", vale_codigo=v2)
    check("pedido segue confirmado", status(p2) == "confirmado", status(p2))
    check("saldo intacto (10)", sql(f"select saldo from vales where codigo='{v2}'") == "10.00")
    check("nenhuma venda criada", venda(p2, "count(*)") == "0")

    print("== 5. Vale + complemento PIX")
    caixa.faturar(p2, forma_pagamento="vale", vale_codigo=v2.lower(), forma_complemento="pix")
    check("pedido faturado", status(p2) == "faturado", status(p2))
    vid2 = venda(p2, "id")
    check("venda 'misto' com vale 10 + pix 20", venda(p2, "forma_pagamento") == "misto", venda(p2, "forma_pagamento"))
    check("2 entradas no caixa (vale 10, pix 20)", sql(f"select group_concat(forma_pagamento,':',valor order by id) from movimentacoes_caixa where venda_id={vid2}") == "vale:10.00,pix:20.00",
          sql(f"select group_concat(forma_pagamento,':',valor order by id) from movimentacoes_caixa where venda_id={vid2}"))
    check("contas a receber somam 30 (2 linhas pagas)", sql(f"select count(*),sum(valor),min(status) from contas_receber where venda_id={vid2}") == "2\t30.00\tpaga",
          sql(f"select count(*),sum(valor),min(status) from contas_receber where venda_id={vid2}"))
    check("vale esgotado vira 'utilizado'", sql(f"select saldo,status from vales where codigo='{v2}'") == "0.00\tutilizado")

    print("== 6. Vale inválido")
    for nome, cod in [("inexistente", "VT-XXXX-NOPE"), ("cancelado", vale(50, "cancelado")), ("vencido", vale(50, validade="'2020-01-01'"))]:
        pp = pedido()
        caixa.faturar(pp, forma_pagamento="vale", vale_codigo=cod)
        check(f"vale {nome}: recusa e pedido segue confirmado", status(pp) == "confirmado", status(pp))
    if sql("select count(*) from empresas where id=999") == "0":
        sql("create temporary table te as select * from empresas where id=1; update te set id=999, cnpj='99.999.999/0001-99'; insert into empresas select * from te")
        sql("create temporary table tu as select * from unidades where id=1; update tu set id=999, empresa_id=999; insert into unidades select * from tu")
    voutra = vale(50, empresa=999)
    pp = pedido()
    caixa.faturar(pp, forma_pagamento="vale", vale_codigo=voutra)
    check("vale de OUTRA empresa: recusa", status(pp) == "confirmado" and sql(f"select saldo from vales where codigo='{voutra}'") == "50.00", status(pp))
    pp = pedido()
    caixa.faturar(pp, forma_pagamento="vale", vale_codigo="")
    check("vale sem código: recusa", status(pp) == "confirmado", status(pp))

    print("== 7. Forma forjada")
    pp = pedido()
    caixa.faturar(pp, forma_pagamento="credishop")
    check("credishop não existe no Faturar", status(pp) == "confirmado", status(pp))

    print("== 8. Cancelar a venda do pedido devolve o saldo ao vale")
    dono = Sessao("dono@demo.com", "dono123")
    tok = dono.token(dono.get(f"/app/vendas/{venda(p1,'id')}"))
    st, _ = dono.post(f"/app/vendas/{venda(p1,'id')}", {"_token": tok, "_method": "DELETE"})
    check("saldo do vale volta a 50", sql(f"select saldo,status from vales where codigo='{v1}'") == "50.00\tativo", sql(f"select saldo,status from vales where codigo='{v1}'"))
    check("estorno registrado em vale_usos", sql(f"select count(*) from vale_usos where venda_id={venda(p1,'id')} and tipo='estorno'") == "1")

    print("== 9. Regressão: dinheiro e crédito 3x como antes")
    pa = pedido(); caixa.faturar(pa, forma_pagamento="dinheiro")
    check("dinheiro: 1 pagamento, venda dinheiro", venda(pa, "forma_pagamento") == "dinheiro")
    pb = pedido(); caixa.faturar(pb, forma_pagamento="cartao_credito", parcelas="3")
    check("crédito 3x grava parcelas", '"parcelas": 3' in venda(pb, "pagamento_detalhes") or '"parcelas":3' in venda(pb, "pagamento_detalhes"), venda(pb, "pagamento_detalhes"))
    print(f"\nTOTAL {OK} ✅ / {FAIL} ❌")
    sys.exit(1 if FAIL else 0)


main()
