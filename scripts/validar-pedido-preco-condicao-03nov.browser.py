"""Parte do navegador de validar-pedido-preco-condicao-03nov.py (roda no container do Playwright)."""
import os

from playwright.sync_api import sync_playwright

BASE = os.environ["BASE"]
PEDIDO = os.environ["PEDIDO"]
MODO = os.environ.get("MODO", "avista")


def check(nome, cond, obtido=""):
    print(("✅ " if cond else "❌ ") + nome + ("" if cond else f"  (obtido: {obtido})"), flush=True)


def preco(page, linha=0):
    return page.locator("#itensBody tr .item-preco").nth(linha).input_value()


def condicao(page, valor):
    page.select_option("#campo-condicao_pagamento", valor)


with sync_playwright() as pw:
    page = pw.chromium.launch().new_page(viewport={"width": 1400, "height": 1000})
    erros = []
    page.on("pageerror", lambda e: erros.append(str(e)))
    page.goto(f"{BASE}/login")
    page.fill("input[name=email]", "dono@demo.com")
    page.fill("input[name=password]", "dono123")
    page.click("button[type=submit]")
    page.wait_for_load_state("networkidle")

    if MODO == "cartao":
        print("== 3. Loja que cadastra o preço do CARTÃO (como a loja 9 da STILO)")
        page.goto(f"{BASE}/app/pedidos/create")
        busca = page.locator("#itensBody tr .produto-busca").first
        busca.type("Teclado")
        page.locator("#itensBody tr .produto-resultados a", has_text="Teclado").first.dispatch_event("click")
        check("sem condição = preço cadastrado do cartão 330.00", preco(page) == "330.00", preco(page))
        condicao(page, "a_vista")
        check("À vista → 299.90", preco(page) == "299.90", preco(page))
        condicao(page, "boleto")
        check("Boleto → preço cadastrado 330.00", preco(page) == "330.00", preco(page))
        check("sem erro de JavaScript", not erros, erros)
        raise SystemExit(0)

    print("== 1. Novo pedido (loja na regra de sempre: cadastrado = PIX)")
    page.goto(f"{BASE}/app/pedidos/create")
    busca = page.locator("#itensBody tr .produto-busca").first
    for termo in ("Teclado", "Te"):  # 2+ letras: busca do PDV; vazio: lista da busca geral
        busca.fill("")
        busca.type(termo)
        page.locator("#itensBody tr .produto-resultados a", has_text="Teclado").first.dispatch_event("click")
        check(f"produto escolhido ('{termo}') sem condição = preço cadastrado 299.90", preco(page) == "299.90", preco(page))
    condicao(page, "cartao")
    check("Cartão → 330.00 (crédito)", preco(page) == "330.00", preco(page))
    check("total da tela acompanha", "330,00" in page.inner_text("#totalDisplay"), page.inner_text("#totalDisplay"))
    condicao(page, "a_vista")
    check("À vista → 299.90 (o caso do Dennis)", preco(page) == "299.90", preco(page))
    condicao(page, "pix")
    check("PIX → 299.90", preco(page) == "299.90", preco(page))
    condicao(page, "30_dias")
    check("30 dias → preço cadastrado 299.90", preco(page) == "299.90", preco(page))
    condicao(page, "cartao")
    busca.fill("")
    busca.type("Tecl")
    page.locator("#itensBody tr .produto-resultados a", has_text="Teclado").first.dispatch_event("click")
    check("produto escolhido com Cartão já marcado entra a 330.00", preco(page) == "330.00", preco(page))
    page.evaluate("document.getElementById('clienteId').value = 1")
    page.locator("form button[type=submit].btn-lg").click()
    page.wait_for_load_state("networkidle")
    check("pedido salvo (volta para a lista)", page.url.rstrip("/").endswith("/app/pedidos") or "/app/pedidos/" in page.url and "create" not in page.url, page.url)

    print("== 2. Editar pedido rascunho (item já gravado a 299.90)")
    page.goto(f"{BASE}/app/pedidos/{PEDIDO}/edit")
    check("abre com o preço gravado 299.90", preco(page) == "299.90", preco(page))
    condicao(page, "cartao")
    check("Cartão → 330.00 no item já existente", preco(page) == "330.00", preco(page))
    condicao(page, "a_vista")
    check("À vista → 299.90", preco(page) == "299.90", preco(page))
    condicao(page, "cartao")
    page.locator("form button[type=submit]").last.click()
    page.wait_for_load_state("networkidle")

    check("sem erro de JavaScript nas telas", not erros, erros)
