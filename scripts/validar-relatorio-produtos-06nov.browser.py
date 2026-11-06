#!/usr/bin/env python3
"""Navegador ✅/❌ — Relatório de Produtos vendidos (06/11/2026), como a pessoa usa.

Cenário do validar-relatorio-produtos-06nov.py (SO_PREPARAR=1 cria, SO_LIMPAR=1 apaga):
    docker run --rm --network container:cs-mysql -e BASE=http://127.0.0.1:8298 -e NORTE=<id> -e SUL=<id> \
        -v $PWD/scripts:/s -v /tmp/out:/out mcr.microsoft.com/playwright/python:v1.55.0-noble \
        sh -c "pip install -q playwright==1.55.0; python3 /s/validar-relatorio-produtos-06nov.browser.py"
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
    page = b.new_page(viewport={"width": 1280, "height": 900}, accept_downloads=True)
    erros = []
    page.on("pageerror", lambda e: erros.append(str(e)))
    page.goto(f"{BASE}/login")
    page.fill("input[name=email]", "dono@demo.com")
    page.fill("input[name=password]", "dono123")
    page.click("button[type=submit]")
    page.wait_for_load_state("networkidle")
    page.goto(f"{BASE}/app/dashboard")
    page.click("a.nav-toggle:has-text('Relatórios')")
    page.wait_for_timeout(500)
    page.click("#menuRelatorios a.nav-link:has-text('Produtos vendidos')")
    page.wait_for_load_state("networkidle")
    check("menu Relatórios → Produtos vendidos abre a tela", "/app/relatorios/produtos" in page.url, page.url)
    page.goto(f"{BASE}/app/relatorios/produtos?data_inicio=2027-03-01&data_fim=2027-03-31")
    total = lambda: page.locator("#kpi-total").inner_text()
    check("abre com a loja atual = R$ 280,00", "280,00" in total(), total())
    check("as duas fileiras de lojas visíveis",
          page.locator(f"label[for=loja-{NORTE}]").is_visible() and page.locator(f"label[for=saida-{SUL}]").is_visible())
    cont_saida = page.locator("#filtro-saida .js-contador")
    check("saída começa '(todas marcadas)'", cont_saida.inner_text().strip() == "(todas marcadas)", cont_saida.inner_text())
    page.click(f"label[for=loja-{NORTE}]")
    page.click(f"label[for=loja-{SUL}]")
    check("lojas da venda: '(3 marcadas)'", page.locator("#filtro-lojas .js-contador").inner_text().strip() == "(todas marcadas)",
          page.locator("#filtro-lojas .js-contador").inner_text())
    page.click("button:has-text('Filtrar')")
    page.wait_for_load_state("networkidle")
    check("Filtrar com as 3 lojas = R$ 360,00", "360,00" in total(), total())
    page.click("#filtro-saida .js-limpar")
    check("Limpar saída mostra o aviso", "vale todas" in cont_saida.inner_text(), cont_saida.inner_text())
    page.click(f"label[for=saida-{SUL}]")
    page.wait_for_timeout(200)
    cor = page.evaluate(f"(() => {{ const c = getComputedStyle(document.querySelector('label[for=saida-{SUL}]')); return [c.backgroundColor, c.color]; }})()")
    check("pílula marcada cheia com texto branco", cor[1] == "rgb(255, 255, 255)" and "79, 70, 229" in cor[0], cor)
    page.screenshot(path="/out/1-barra.png")
    page.click("button:has-text('Filtrar')")
    page.wait_for_load_state("networkidle")
    check("só o que saiu da Sul = R$ 80,00", "80,00" in total(), total())
    page.screenshot(path="/out/2-saida-sul.png", full_page=True)
    page.click("a.nav-link:has-text('Item a item')")
    page.wait_for_load_state("networkidle")
    linhas = page.locator("#tabela-itens tbody tr")
    check("aba Item a item mantém o filtro (2 itens da Sul)", linhas.count() == 2 and "80,00" in total(), linhas.count())
    check("item da Norte mostra 'Saiu de: Loja Sul'", page.locator("#tabela-itens tbody tr:has-text('Loja Norte (teste)') td").nth(3).inner_text().strip() == "Loja Sul (teste)")
    page.screenshot(path="/out/3-itens.png", full_page=True)
    with page.expect_download() as dl:
        page.click("a:has-text('Planilha')")
    nome = dl.value.suggested_filename
    check("botão Planilha baixa .xlsx", nome.endswith(".xlsx"), nome)
    page.goto(f"{BASE}/app/relatorios/produtos?data_inicio=2027-03-01&data_fim=2027-03-31&lojas[]=1&lojas[]={NORTE}&lojas[]={SUL}")
    page.fill("#produtoFiltroBusca", "Mouse")
    page.wait_for_timeout(1200)
    sug = page.locator(".autocomplete-item, .autocomplete-results div, .dropdown-item").filter(has_text="Mouse").first
    if sug.count():
        sug.click()
        page.click("button:has-text('Filtrar')")
        page.wait_for_load_state("networkidle")
        check("filtro de produto pelo autocomplete (Mouse) = R$ 152,00", "152,00" in total(), total())
    else:
        check("autocomplete de produto mostra sugestão", False, page.content()[-400:])
    page.set_viewport_size({"width": 390, "height": 844})
    page.reload()
    page.wait_for_load_state("networkidle")
    larg = page.evaluate("document.documentElement.scrollWidth")
    check("celular (390): sem rolagem lateral", larg <= 390, larg)
    page.screenshot(path="/out/4-celular.png", full_page=True)
    check("nenhum erro de JavaScript", not erros, erros)
    b.close()
print(f"\n{OK} ✅ / {FAIL} ❌")
sys.exit(1 if FAIL else 0)
