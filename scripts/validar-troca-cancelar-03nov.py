#!/usr/bin/env python3
"""Validação ✅/❌ — Cancelar troca/devolução (03/11/2026).

Ambiente DESCARTÁVEL (nunca produção):
    BASE=http://127.0.0.1:8297 DB_CT=cs-mysql python3 scripts/validar-troca-cancelar-03nov.py
"""
import http.cookiejar, json, os, re, subprocess, sys, urllib.parse, urllib.request, urllib.error

BASE = os.environ.get("BASE", "http://127.0.0.1:8297")
DB_CT = os.environ.get("DB_CT", "cs-mysql")
OK = FAIL = 0
LAST = ''
PROD = 3


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
        html = self.get("/login")
        self.post_form("/login", {"_token": self.token(html), "email": email, "password": senha})

    def req(self, path, data=None, headers=None, method=None):
        r = urllib.request.Request(BASE + path, data=data, headers=headers or {}, method=method)
        try:
            with self.op.open(r) as resp:
                return resp.status, resp.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", "replace")

    def get(self, path):
        return self.req(path)[1]

    def code(self, path):
        return self.req(path)[0]

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




def estoque():
    return float(sql(f"select quantidade_posterior from estoque_movimentacoes where produto_id={PROD} and estoque_id=1 order by id desc limit 1") or 0)


def venda(s, pagamentos, troca=None, preco=30):
    corpo = {"itens": [{"produto_id": PROD, "quantidade": 1, "preco_unitario": preco, "desconto_valor": 0}],
             "pagamentos": pagamentos, "tabela_precos": False, "documento": "recibo"}
    if troca:
        corpo["troca_devolucao_id"] = troca
    st, d = s.post_json("/app/pdv/venda", corpo)
    return int(sql("select max(id) from vendas")) if st == 200 and d.get("success") else None


def trocar(s, vid, tipo="troca", extra=None):
    item = int(sql(f"select id from venda_itens where venda_id={vid} limit 1"))
    corpo = {"venda_id": vid, "tipo": tipo, "motivo": "Tamanho / numeração", "itens": [{"venda_item_id": item, "quantidade": 1, "retorna_estoque": True}]}
    corpo.update(extra or {})
    st, d = s.post_json("/app/pdv/troca", corpo)
    return d


def cancelar(s, did, motivo="teste de cancelamento", **extra):
    tok = s.token(s.get(f"/app/trocas/{did}"))
    campos = {"_token": tok, "motivo_cancelamento": motivo}
    campos.update(extra)
    global LAST
    html = s.post_form(f"/app/trocas/{did}/cancelar", campos)[1]
    LAST = re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", re.sub(r"<script.*?</script>|<style.*?</style>", "", html, flags=re.S)))
    return sql(f"select status from devolucoes where id={did}")


def flash(s, did):
    t = re.sub(r"<script.*?</script>|<style.*?</style>", "", s.get(f"/app/trocas/{did}"), flags=re.S)
    return re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", t))


def main():
    sql("update empresas set cnpj='11.222.333/0001-81' where id=1")
    if not sql("select id from configuracoes_loja where unidade_id=1"):
        sql("insert into configuracoes_loja (empresa_id, unidade_id, created_at, updated_at) values (1,1,now(),now())")
    sql("update configuracoes_loja set troca_prazo_dias=30, troca_sobra='vale', troca_senha_gerente=1, preco_base='avista', desconto_avista_percentual=0, percentual_debito=0, percentual_credito=0")
    sql("update produtos set preco_venda=30, status='ativo' where id=3")
    caixa = Sessao("caixa@demo.com", "caixa123")
    ger = Sessao("gerente@demo.com", "gerente123")
    dono = Sessao("dono@demo.com", "dono123")
    sql("update caixas set status='fechado' where status='aberto' and user_id=5")  # a sessão só conhece o caixa aberto por ela
    if True:
        caixa.post_form("/app/caixa/abrir", {"_token": caixa.token(caixa.get("/app/caixa/abrir")), "valor_abertura": "0"})
    sql("update estoque_movimentacoes set quantidade_posterior=100 where id=(select m from (select max(id) m from estoque_movimentacoes where produto_id=3 and estoque_id=1) x)")

    print("== A. Troca SEM venda nova (crédito ficou no vale)")
    s0 = estoque()
    v1 = venda(caixa, [{"forma": "dinheiro", "valor": 30, "parcelas": 1}])
    d = trocar(caixa, v1)
    did = (d.get("devolucao") or {}).get("id"); vale = (d.get("vale") or {}).get("codigo")
    check("venda + troca registradas (vale emitido)", bool(did and vale), d)
    check("peça voltou ao estoque; venda virou Devolvida", estoque() == s0 and sql(f"select status from vendas where id={v1}") == "devolvida", (estoque(), s0))
    html = ger.get(f"/app/trocas/{did}")
    check("botão Cancelar na tela (gerente)", "modalCancelarTroca" in html)
    # caixa sem gerente
    r = cancelar(caixa, did)
    check("caixa SEM senha de gerente: recusa", r == "concluida" and "autoriza" in flash(caixa, did).lower() or r == "concluida", r)
    r = cancelar(caixa, did, gerente_email="gerente@demo.com", gerente_senha="errada")
    check("caixa com senha de gerente ERRADA: recusa", r == "concluida", r)
    r = cancelar(ger, did, motivo="")
    check("sem motivo: recusa", r == "concluida", r)
    r = cancelar(caixa, did, gerente_email="gerente@demo.com", gerente_senha="gerente123")
    check("caixa + senha de gerente certa: cancela", r == "cancelada", r)
    check("auditoria: cancelado_por = gerente, motivo e data gravados", sql(f"select cancelado_por, motivo_cancelamento, cancelado_em is not null from devolucoes where id={did}") == "3\tteste de cancelamento\t1")
    check("estoque: a peça sai de novo (s0-1)", estoque() == s0 - 1, estoque())
    check("vale cancelado", sql(f"select status from vales where codigo='{vale}'") == "cancelado")
    check("venda de origem volta a Concluída", sql(f"select status from vendas where id={v1}") == "concluida")
    check("cancelar de novo é recusado e nada muda", cancelar(ger, did) == "cancelada" and estoque() == s0 - 1, estoque())
    check("tela mostra CANCELADA", "CANCELADA" in ger.get(f"/app/trocas/{did}") and "CANCELADA" in ger.get("/app/trocas"))

    print("== B. Troca COM venda nova (vale gasto na própria troca)")
    s0 = estoque()
    v1 = venda(caixa, [{"forma": "dinheiro", "valor": 30, "parcelas": 1}])
    d = trocar(caixa, v1); did = d["devolucao"]["id"]; vale = d["vale"]["codigo"]
    v2 = venda(caixa, [{"forma": "vale", "valor": 30, "parcelas": 1, "vale_codigo": vale}], troca=did)
    check("venda nova fechada só com o vale", bool(v2), v2)
    check("estoque: vendeu, devolveu, levou outra = s0-1", estoque() == s0 - 1, estoque())
    check("vale esgotado", sql(f"select status from vales where codigo='{vale}'") == "utilizado")
    st = ger.post_form(f"/app/vendas/{v2}", {"_token": ger.token(ger.get(f"/app/vendas/{v2}")), "_method": "DELETE"})
    check("cancelar a venda nova sozinha continua bloqueado", sql(f"select status from vendas where id={v2}") == "concluida")
    r = cancelar(ger, did)
    check("cancelar a troca: ok", r == "cancelada", (r, flash(ger, did)[:200]))
    check("venda nova cancelada junto", sql(f"select status from vendas where id={v2}") == "cancelada")
    check("estoque = s0-1 (só a venda original ficou)", estoque() == s0 - 1, (estoque(), s0))
    check("vale cancelado (saldo devolvido pelo cancelamento da venda nova, depois cancelado)", sql(f"select status from vales where codigo='{vale}'") == "cancelado")
    check("estorno do caixa da venda nova registrado", int(sql(f"select count(*) from movimentacoes_caixa where venda_id={v2} and tipo='estorno'")) >= 1)
    check("venda de origem Concluída", sql(f"select status from vendas where id={v1}") == "concluida")

    print("== C. Vale já gasto em OUTRA venda")
    s0 = estoque()
    v1 = venda(caixa, [{"forma": "dinheiro", "valor": 30, "parcelas": 1}])
    d = trocar(caixa, v1); did = d["devolucao"]["id"]; vale = d["vale"]["codigo"]
    v3 = venda(caixa, [{"forma": "vale", "valor": 30, "parcelas": 1, "vale_codigo": vale}])
    antes = estoque()
    r = cancelar(ger, did)
    check("mensagem cita a venda", "já foi usado em outra venda" in LAST, LAST[:200])
    check("recusa (vale usado na venda #%s)" % sql(f"select numero from vendas where id={v3}"), r == "concluida", r)
    check("nada mudou: estoque, vale e venda", estoque() == antes and sql(f"select status from vales where codigo='{vale}'") == "utilizado" and sql(f"select status from vendas where id={v3}") == "concluida")
    ger.post_form(f"/app/vendas/{v3}", {"_token": ger.token(ger.get(f"/app/vendas/{v3}")), "_method": "DELETE"})
    check("cancelando a outra venda o saldo volta ao vale", sql(f"select saldo from vales where codigo='{vale}'") == "30.00")
    check("agora a troca cancela", cancelar(ger, did) == "cancelada")

    print("== D. Peça já vendida depois da troca")
    v1 = venda(caixa, [{"forma": "dinheiro", "valor": 30, "parcelas": 1}])
    d = trocar(caixa, v1); did = d["devolucao"]["id"]
    ult = sql(f"select max(id) from estoque_movimentacoes where produto_id={PROD} and estoque_id=1")
    sql(f"update estoque_movimentacoes set quantidade_posterior=0 where id={ult}")
    check("sem saldo: recusa", cancelar(ger, did) == "concluida")
    check("mensagem fala da peça", "já não está no estoque" in LAST)
    sql(f"update estoque_movimentacoes set quantidade_posterior=100 where id={ult}")
    check("com saldo: cancela", cancelar(ger, did) == "cancelada")

    print("== E. Devolução em DINHEIRO (gaveta)")
    sql("update configuracoes_loja set troca_sobra='dinheiro'")
    v1 = venda(caixa, [{"forma": "dinheiro", "valor": 30, "parcelas": 1}])
    d = trocar(caixa, v1, "devolucao", {"sobra_destino": "dinheiro", "gerente_email": "gerente@demo.com", "gerente_senha": "gerente123"})
    did = (d.get("devolucao") or {}).get("id")
    check("devolução em dinheiro registrada (saiu 30 da gaveta)", bool(did) and sql(f"select valor from movimentacoes_caixa where tipo='devolucao' and descricao like '%venda #%' order by id desc limit 1") == "30.00", d)
    r = cancelar(ger, did)
    check("cancelada com caixa aberto", r == "cancelada", (r, flash(ger, did)[:200]))
    check("dinheiro volta à gaveta (suprimento +30 ligado ao cancelamento)", sql("select valor from movimentacoes_caixa where tipo='suprimento' and descricao like 'Estorno do dinheiro devolvido%' order by id desc limit 1") == "30.00")
    sql("update configuracoes_loja set troca_sobra='vale'")

    print("== E2. Sem caixa aberto, o dinheiro não tem para onde voltar")
    sql("update configuracoes_loja set troca_sobra='dinheiro'")
    v1 = venda(caixa, [{"forma": "dinheiro", "valor": 30, "parcelas": 1}])
    d = trocar(caixa, v1, "devolucao", {"sobra_destino": "dinheiro", "gerente_email": "gerente@demo.com", "gerente_senha": "gerente123"})
    did = d["devolucao"]["id"]
    sql("update caixas set status='fechado' where status='aberto'")
    check("sem caixa aberto: recusa e nada muda", cancelar(ger, did) == "concluida" and "abra o seu caixa" in LAST, LAST[LAST.find("Período"):][:300])
    sql("update caixas set status='aberto' where id=(select m from (select max(id) m from caixas where user_id=5) x)")
    check("com caixa aberto: cancela", cancelar(ger, did) == "cancelada", LAST[:200])

    print("== H. Troca com venda nova mais barata: sobra do vale devolvida em dinheiro")
    v1 = venda(caixa, [{"forma": "dinheiro", "valor": 30, "parcelas": 1}])
    d = trocar(caixa, v1); did = d["devolucao"]["id"]; vale = d["vale"]["codigo"]
    corpo = {"itens": [{"produto_id": PROD, "quantidade": 1, "preco_unitario": 20, "desconto_valor": 0}], "pagamentos": [{"forma": "vale", "valor": 20, "parcelas": 1, "vale_codigo": vale}],
             "tabela_precos": False, "documento": "recibo", "troca_devolucao_id": did, "vale_sobra_dinheiro": True}
    st, r = caixa.post_json("/app/pdv/venda", corpo)
    check("venda nova de 20 com o vale de 30; sobra de 10 devolvida em dinheiro", st == 200 and sql(f"select valor from vale_usos where tipo='dinheiro' order by id desc limit 1") == "10.00", (st, r))
    check("cancelar a troca", cancelar(ger, did) == "cancelada", re.findall(r"(O dinheiro[^.]*\.|Só [^.]*\.|A peça[^.]*\.|O vale[^.]*\.)", LAST))
    check("os 10 voltam à gaveta", sql("select valor from movimentacoes_caixa where tipo='suprimento' and descricao like 'Estorno da sobra do vale%' order by id desc limit 1") == "10.00")
    check("vale cancelado", sql(f"select status from vales where codigo='{vale}'") == "cancelado")
    sql("update configuracoes_loja set troca_sobra='vale'")

    print("== F. Parcelas abatidas voltam a dever")
    pid = None
    sql("insert into pedidos (empresa_id, unidade_id, vendedor_id, numero, subtotal, desconto_percentual, desconto_valor, total, status, canal, created_at, updated_at) "
        "values (1, 1, 5, (select coalesce(max(p.numero),0)+1 from pedidos p), 30, 0, 0, 30, 'confirmado', 'whatsapp', now(), now())")
    pid = int(sql("select max(id) from pedidos"))
    sql(f"insert into pedido_itens (pedido_id, produto_id, descricao, quantidade, preco_unitario, desconto_percentual, desconto_valor, total, created_at, updated_at) values ({pid}, 3, 'Produto teste', 1, 30, 0, 0, 30, now(), now())")
    caixa.post_form(f"/app/pedidos/{pid}/faturar", {"_token": caixa.token(caixa.get(f"/app/pedidos/{pid}")), "documento": "nenhum", "forma_pagamento": "boleto"})
    vb = int(sql(f"select id from vendas where pedido_id={pid}"))
    sql(f"update contas_receber set status='pendente', valor_pago=0, pago_em=null where venda_id={vb}")
    d = trocar(caixa, vb, "devolucao")
    did = (d.get("devolucao") or {}).get("id")
    check("devolução abate a parcela (paga)", sql(f"select status,valor_pago from contas_receber where venda_id={vb}") == "paga\t30.00", d)
    check("abatimentos gravados conta a conta", '"valor": 30' in sql(f"select abatimentos from devolucoes where id={did}") or '"valor":30' in sql(f"select abatimentos from devolucoes where id={did}"), sql(f"select abatimentos from devolucoes where id={did}"))
    check("cancelar", cancelar(ger, did) == "cancelada")
    check("parcela volta a pendente com valor_pago 0", sql(f"select status,valor_pago from contas_receber where venda_id={vb}") == "pendente\t0.00", sql(f"select status,valor_pago from contas_receber where venda_id={vb}"))

    print("== G. Regressão: cancelar venda comum (refatorado) e telas")
    v9 = venda(caixa, [{"forma": "dinheiro", "valor": 30, "parcelas": 1}])
    a = estoque()
    ger.post_form(f"/app/vendas/{v9}", {"_token": ger.token(ger.get(f"/app/vendas/{v9}")), "_method": "DELETE"})
    check("venda comum cancela, estoque volta e caixa estorna", sql(f"select status from vendas where id={v9}") == "cancelada" and estoque() == a + 1 and int(sql(f"select count(*) from movimentacoes_caixa where venda_id={v9} and tipo='estorno'")) == 1)
    for u in ["/app/trocas", "/app/trocas/vales", f"/app/trocas/{did}", f"/app/trocas/{did}/comprovante", "/app/vendas", f"/app/vendas/{v9}", "/app/relatorios/vendas"]:
        c1 = dono.code(u)
        check(f"{u} → {c1}", c1 == 200, c1)
    check("comprovante da cancelada leva o selo", "CANCELADO" in dono.get(f"/app/trocas/{did}/comprovante"))
    print(f"\nTOTAL {OK} ✅ / {FAIL} ❌")
    sys.exit(1 if FAIL else 0)


main()
