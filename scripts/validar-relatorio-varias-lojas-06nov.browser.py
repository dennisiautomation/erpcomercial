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
    kpi_txt = lambda: page.locator(".kpi-label:has-text('Faturamento') + .kpi-value").inner_text()
    check("lojas visíveis na barra, sem clicar em nada",
          page.locator(f"label[for=loja-{NORTE}]").is_visible() and page.locator("label[for=loja-1]").is_visible())
    check("loja atual já vem marcada", page.is_checked("#loja-1") and not page.is_checked(f"#loja-{NORTE}"))
    check("contador inicial '(1 marcada)'", rot.inner_text().strip() == "(1 marcada)", rot.inner_text())
    page.click(f"label[for=loja-{NORTE}]")
    check("clicar na Norte marca", page.is_checked(f"#loja-{NORTE}"))
    check("contador '(2 marcadas)'", rot.inner_text().strip() == "(2 marcadas)", rot.inner_text())
    page.wait_for_timeout(300)
    cor = page.evaluate(f"(() => {{ const c = getComputedStyle(document.querySelector('label[for=loja-{NORTE}]')); return [c.backgroundColor, c.color, c.opacity]; }})()")
    check("marcada com o mouse em cima: fundo cheio, texto branco", cor[1] == "rgb(255, 255, 255)" and "79, 70, 229" in cor[0] and cor[2] == "1", cor)
    page.screenshot(path="/out/1-barra-2-lojas.png")
    page.click("button:has-text('Filtrar')")
    page.wait_for_load_state("networkidle")
    check("Filtrar com 2 lojas = R$ 333,00", "333,00" in kpi_txt(), kpi_txt())
    check("depois de filtrar: as 2 seguem marcadas", page.is_checked("#loja-1") and page.is_checked(f"#loja-{NORTE}") and not page.is_checked(f"#loja-{SUL}"))
    check("bloco 'Por loja' na tela", page.locator("h6:has-text('Por loja')").is_visible())
    page.screenshot(path="/out/2-filtrado-2-lojas.png", full_page=True)
    page.click(".js-lojas-todas")
    check("'Marcar todas' → '(todas marcadas)'", rot.inner_text().strip() == "(todas marcadas)", rot.inner_text())
    page.click(".js-lojas-limpar")
    check("'Limpar' → aviso de loja atual", "loja atual" in rot.inner_text(), rot.inner_text())
    page.click(".js-lojas-todas")
    page.click("button:has-text('Filtrar')")
    page.wait_for_load_state("networkidle")
    check("Filtrar com todas = R$ 777,00", "777,00" in kpi_txt(), kpi_txt())
    page.click(".js-lojas-limpar")
    page.click("button:has-text('Filtrar')")
    page.wait_for_load_state("networkidle")
    check("Filtrar sem nenhuma marcada = loja atual R$ 111,00", "111,00" in kpi_txt(), kpi_txt())
    page.set_viewport_size({"width": 390, "height": 844})
    page.reload()  # encolher com a página aberta deixa o menu lateral no estado do desktop
    page.wait_for_load_state("networkidle")
    larg = page.evaluate("document.documentElement.scrollWidth")
    check("celular (390): lojas visíveis sem rolagem lateral", page.locator(f"label[for=loja-{SUL}]").is_visible() and larg <= 390, larg)
    page.screenshot(path="/out/3-celular.png", full_page=True)
    check("nenhum erro de JavaScript", not erros, erros)
    b.close()
print(f"\n{OK} ✅ / {FAIL} ❌")
sys.exit(1 if FAIL else 0)
