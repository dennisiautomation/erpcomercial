#!/usr/bin/env python3
"""Validação ✅/❌ — o pedido repreça os itens quando muda a condição de pagamento (03/11/2026).

Caso (STILO): no pedido, trocar a condição para "À vista" não mudava o valor, como a forma faz
no PDV. Agora À vista/PIX = tabela Dinheiro/PIX, Cartão = crédito; prazo/boleto = preço cadastrado.

Ambiente DESCARTÁVEL (nunca produção). Roda o navegador no container do Playwright, na rede do cs-mysql:
    BASE=http://127.0.0.1:8297 DB_CT=cs-mysql python3 scripts/validar-pedido-preco-condicao-03nov.py
"""
import os
import subprocess
import sys

DB_CT = os.environ.get("DB_CT", "cs-mysql")
BASE = os.environ.get("BASE", "http://127.0.0.1:8297")
AQUI = os.path.dirname(os.path.abspath(__file__))
PW_IMG = os.environ.get("PW_IMG", "mcr.microsoft.com/playwright/python:v1.55.0-noble")


def sql(q):
    r = subprocess.run(["docker", "exec", DB_CT, "mysql", "-uerp", "-perp", "erp_teste", "-N", "-B", "-e", q],
                       capture_output=True, text=True)
    return r.stdout.strip()


def main():
    # Produto 3 no formato da STILO: preco_venda = PIX, cartão FIXO no débito/crédito
    sql("update empresas set cnpj='11.222.333/0001-81' where id=1")
    sql("update produtos set preco_venda=299.90 where id=3")
    sql("delete from produto_precos where produto_id=3")
    sql("insert into produto_precos (empresa_id, produto_id, modalidade, valor, created_at, updated_at) values "
        "(1,3,'debito',320,now(),now()),(1,3,'credito',330,now(),now())")
    sql("update configuracoes_loja set preco_base='avista' where empresa_id=1")
    # Pedido rascunho para a tela de editar
    sql("insert into pedidos (empresa_id, unidade_id, cliente_id, vendedor_id, numero, subtotal, desconto_percentual, desconto_valor, total, status, canal, created_at, updated_at) "
        "values (1, 1, 1, 2, (select coalesce(max(p.numero),0)+1 from pedidos p), 299.90, 0, 0, 299.90, 'rascunho', 'presencial', now(), now())")
    pid = sql("select max(id) from pedidos")
    sql(f"insert into pedido_itens (pedido_id, produto_id, descricao, quantidade, preco_unitario, desconto_percentual, desconto_valor, total, created_at, updated_at) "
        f"values ({pid}, 3, 'Teclado Mecanico Redragon Kumara', 1, 299.90, 0, 0, 299.90, now(), now())")

    r = subprocess.run(["docker", "run", "--rm", "--network", f"container:{DB_CT}",
                        "-v", f"{AQUI}:/s:ro", "-e", f"BASE={BASE}", "-e", f"PEDIDO={pid}",
                        PW_IMG, "sh", "-c", "pip install -q --break-system-packages playwright==1.55.0 >/dev/null 2>&1; "
                        "python3 /s/validar-pedido-preco-condicao-03nov.browser.py"],
                       capture_output=True, text=True)
    print(r.stdout, r.stderr[-2000:], sep="")
    ok, falha = r.stdout.count("✅"), r.stdout.count("❌")

    def chk(nome, cond, obtido=""):
        nonlocal ok, falha
        ok, falha = (ok + 1, falha) if cond else (ok, falha + 1)
        print(("✅ " if cond else "❌ ") + nome + ("" if cond else f"  (obtido: {obtido})"))

    if sql("select count(*) from configuracoes_loja where empresa_id=1 and unidade_id=1") == "0":
        sql("insert into configuracoes_loja (empresa_id, unidade_id, created_at, updated_at) values (1, 1, now(), now())")
    sql("update configuracoes_loja set preco_base='cartao' where empresa_id=1")
    r2 = subprocess.run(["docker", "run", "--rm", "--network", f"container:{DB_CT}",
                         "-v", f"{AQUI}:/s:ro", "-e", f"BASE={BASE}", "-e", f"PEDIDO={pid}", "-e", "MODO=cartao",
                         PW_IMG, "sh", "-c", "pip install -q --break-system-packages playwright==1.55.0 >/dev/null 2>&1; "
                         "python3 /s/validar-pedido-preco-condicao-03nov.browser.py"],
                        capture_output=True, text=True)
    sql("update configuracoes_loja set preco_base='avista' where empresa_id=1")
    print(r2.stdout, r2.stderr[-2000:], sep="")
    ok, falha = ok + r2.stdout.count("✅"), falha + r2.stdout.count("❌")

    print("== Gravação")
    gravado = sql("select i.preco_unitario, p.total, p.condicao_pagamento from pedidos p join pedido_itens i on i.pedido_id=p.id "
                  "where p.id=(select max(id) from pedidos) order by i.id limit 1")
    chk("pedido salvo com o preço do cartão (330 / Cartão)", gravado == "330.00\t330.00\tcartao", gravado)
    editado = sql(f"select i.preco_unitario, p.total, p.condicao_pagamento from pedidos p join pedido_itens i on i.pedido_id=p.id where p.id={pid}")
    chk("pedido editado gravou 330 no Cartão", editado == "330.00\t330.00\tcartao", editado)

    print(f"\nTOTAL {ok} ✅ / {falha} ❌")
    sys.exit(1 if falha or r.returncode not in (0, 1) else 0)


if __name__ == "__main__":
    main()
