#!/usr/bin/env python3
"""Navegador ✅/❌ — filtro de VÁRIAS lojas no Relatório de Vendas (06/11/2026), como a pessoa usa.

Ambiente DESCARTÁVEL (cs-*). O bash em volta cria Loja Norte/Sul + 3 vendas e limpa depois:
    docker run --rm --network container:cs-mysql -e BASE=http://127.0.0.1:8298 -e NORTE=<id> -e SUL=<id> \
        -v $PWD/scripts:/s -v /tmp/out:/out mcr.microsoft.com/playwright/python:v1.55.0-noble \
        python3 /s/validar-relatorio-varias-lojas-06nov.browser.py
"""
import os
import sys
from playwright.sync_api import sync_playwright

BASE, NORTE, SUL = os.environ["BASE"], os.environ["NORTE"], os.environ["SUL"]
OK = FAIL = 0


def check(nome, cond, obtido=""):
    global OK, FAIL
    OK, FAIL = (OK + 1, FAIL) if cond else (OK, FAIL + 1)
    print(("✅ " if cond else "❌ ") + nome + ("" if cond else f"  (obtido: {obtido})"))


with sync_playwright() as pw:
    b = pw.chromium.launch()
    page = b.new_page(viewport={"width": 1280, "height": 900})
    erros = []
    page.on("pageerror", lambda e: erros.append(str(e)))
    page.goto(f"{BASE}/login")
    page.fill("input[name=email]", "dono@demo.com")
    page.fill("input[name=password]", "dono123")
    page.click("button[type=submit]")
    page.wait_for_load_state("networkidle")
    if "selecionar-unidade" in page.url:
        page.goto(f"{BASE}/selecionar-unidade")
    page.goto(f"{BASE}/app/relatorios/vendas?data_inicio=2027-03-01&data_fim=2027-03-31")
    rot = page.locator(".js-rotulo-lojas")
    nome1 = page.locator("label[for=loja-1]").inner_text().strip()
    check("rótulo inicial = loja atual", rot.inner_text().strip() == nome1, rot.inner_text())
    check("lista fechada no início", not page.locator("#filtro-lojas .dropdown-menu").is_visible())
    page.click("#campo-loja")
    check("clicar abre a lista de lojas", page.locator("#filtro-lojas .dropdown-menu").is_visible())
    page.check(f"#loja-{NORTE}")
    check("marcar a Norte: lista continua aberta", page.locator("#filtro-lojas .dropdown-menu").is_visible())
    check("rótulo = '2 lojas'", rot.inner_text().strip() == "2 lojas", rot.inner_text())
    page.screenshot(path="/out/1-lista-aberta.png")
    page.click("button:has-text('Filtrar')")
    page.wait_for_load_state("networkidle")
    kpi = page.locator(".kpi-label:has-text('Faturamento') + .kpi-value").inner_text()
    check("Filtrar com 2 lojas = R$ 333,00", "333,00" in kpi, kpi)
    check("depois de filtrar: rótulo segue '2 lojas'", rot.inner_text().strip() == "2 lojas", rot.inner_text())
    check("bloco 'Por loja' na tela", page.locator("h6:has-text('Por loja')").is_visible())
    page.screenshot(path="/out/2-filtrado-2-lojas.png", full_page=True)
    page.click("#campo-loja")
    page.click(".js-lojas-todas")
    check("'Marcar todas' → 'Todas as lojas'", rot.inner_text().strip() == "Todas as lojas", rot.inner_text())
    page.click(".js-lojas-limpar")
    check("'Limpar' → 'Loja atual' + aviso", rot.inner_text().strip() == "Loja atual" and page.locator(".js-lojas-aviso").is_visible(), rot.inner_text())
    page.click(".js-lojas-todas")
    page.click("button:has-text('Filtrar')")
    page.wait_for_load_state("networkidle")
    kpi = page.locator(".kpi-label:has-text('Faturamento') + .kpi-value").inner_text()
    check("Filtrar com todas = R$ 777,00", "777,00" in kpi, kpi)
    page.click("#campo-loja")
    page.click(".js-lojas-limpar")
    page.click("button:has-text('Filtrar')")
    page.wait_for_load_state("networkidle")
    kpi = page.locator(".kpi-label:has-text('Faturamento') + .kpi-value").inner_text()
    check("Filtrar sem nenhuma marcada = loja atual R$ 111,00", "111,00" in kpi, kpi)
    page.set_viewport_size({"width": 390, "height": 844})
    page.click("#campo-loja")
    larg = page.evaluate("document.documentElement.scrollWidth")
    check("celular (390): lista abre sem rolagem lateral", page.locator("#filtro-lojas .dropdown-menu").is_visible() and larg <= 390, larg)
    page.screenshot(path="/out/3-celular.png")
    check("nenhum erro de JavaScript", not erros, erros)
    b.close()
print(f"\n{OK} ✅ / {FAIL} ❌")
sys.exit(1 if FAIL else 0)
