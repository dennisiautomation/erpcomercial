"""Validação ✅/❌ do editor de etiqueta (roda no container do Playwright, ambiente descartável).

STILO 04/11/2026: desmarcar um item da lista não tirava o item (o clique na linha refazia a lista no meio
do clique e o navegador desfazia a desmarcação); aviso quando um item invade as barras; zona de silêncio
de 11 módulos no EAN do layout livre.
    BASE=http://127.0.0.1:8297 python3 validar-etiqueta-editor-04nov.browser.py   (dentro do container)
"""
import os, re, json
from playwright.sync_api import sync_playwright

BASE = os.environ["BASE"]
OK = FAIL = 0


def check(nome, cond, obtido=""):
    global OK, FAIL
    if cond:
        OK += 1
        print(f"✅ {nome}", flush=True)
    else:
        FAIL += 1
        print(f"❌ {nome}  (obtido: {str(obtido)[:200]})", flush=True)


with sync_playwright() as pw:
    b = pw.chromium.launch()
    ctx = b.new_context(viewport={"width": 1600, "height": 1100})
    page = ctx.new_page()
    erros = []
    page.on("pageerror", lambda e: erros.append(str(e)))
    page.goto(f"{BASE}/login")
    page.fill("input[name=email]", "dono@demo.com"); page.fill("input[name=password]", "dono123"); page.click("button[type=submit]")
    page.wait_for_load_state("networkidle")
    page.goto(f"{BASE}/app/etiquetas/layout-fixo/termica-tag-35x60"); page.wait_for_load_state("networkidle"); page.wait_for_timeout(500)
    cnt = lambda: page.evaluate("document.querySelectorAll('#etqEtiqueta .etq-item').length")
    tipos = lambda: page.evaluate("Array.from(document.querySelectorAll('#listaItens .etq-lista-item')).filter(l => l.querySelector('input').checked).map(l => l.textContent.trim())")
    n0 = cnt()
    check("editor abre com o layout salvo da STILO (9 itens)", n0 == 9, n0)
    check("aviso: 'Números do código de barras' está em cima das barras no layout da STILO",
          "Números do código de barras" in page.locator("#avisoBarras").inner_text() and page.locator("#avisoBarras").is_visible(), page.locator("#avisoBarras").inner_text())

    linha = page.locator("#listaItens .etq-lista-item", has_text=re.compile(r"^Preço$")).first
    bb = linha.locator("input[type=checkbox]").bounding_box()
    page.mouse.click(bb["x"] + bb["width"] / 2, bb["y"] + bb["height"] / 2); page.wait_for_timeout(300)
    check("desmarcar 'Preço' no checkbox TIRA o item (o bug)", cnt() == n0 - 1 and "Preço" not in tipos(), (cnt(), tipos()))
    linha = page.locator("#listaItens .etq-lista-item", has_text=re.compile(r"^Nome da loja$")).first
    bb = linha.locator("input[type=checkbox]").bounding_box()
    page.mouse.click(bb["x"] + bb["width"] / 2, bb["y"] + bb["height"] / 2); page.wait_for_timeout(300)
    check("desmarcar 'Nome da loja' também", cnt() == n0 - 2, cnt())
    bb = linha.locator("input[type=checkbox]").bounding_box()
    page.mouse.click(bb["x"] + bb["width"] / 2, bb["y"] + bb["height"] / 2); page.wait_for_timeout(300)
    check("marcar de novo volta o item", cnt() == n0 - 1, cnt())
    linha = page.locator("#listaItens .etq-lista-item", has_text=re.compile(r"^Descrição do produto$")).first
    bb = linha.bounding_box(); page.mouse.click(bb["x"] + bb["width"] - 30, bb["y"] + bb["height"] / 2); page.wait_for_timeout(300)
    check("clicar no NOME da linha seleciona (painel mostra o item) sem tirar", "Descrição do produto" in page.locator("#tituloPropriedades").inner_text() and cnt() == n0 - 1, page.locator("#tituloPropriedades").inner_text())

    # redimensionar a descrição pela alça (selecionada acima)
    desc = page.locator("#etqEtiqueta .etq-item.selecionado").first
    before = desc.bounding_box()
    alca = page.locator("#etqEtiqueta .etq-item.selecionado .etq-alca").first
    ab = alca.bounding_box()
    page.mouse.move(ab["x"] + 5, ab["y"] + 5); page.mouse.down(); page.mouse.move(ab["x"] - 60, ab["y"] + 40, steps=10); page.mouse.up(); page.wait_for_timeout(300)
    after = page.locator("#etqEtiqueta .etq-item.selecionado").first.bounding_box()
    check("alça redimensiona o item selecionado", abs(after["height"] - before["height"]) > 20 or abs(after["width"] - before["width"]) > 20, (before, after))
    # a alça solta em cima de outro item pode trocar a seleção; reseleciona pela lista
    titulo = page.locator("#tituloPropriedades").inner_text()
    if "Descrição do produto" not in titulo:
        linha = page.locator("#listaItens .etq-lista-item", has_text=re.compile(r"^Descrição do produto$")).first
        bb = linha.bounding_box(); page.mouse.click(bb["x"] + bb["width"] - 30, bb["y"] + bb["height"] / 2); page.wait_for_timeout(300)
    # tamanho da fonte pelo painel
    tam = page.locator("#corpoPropriedades input[data-prop=tamanho]")
    tam.fill("11"); tam.dispatch_event("input"); page.wait_for_timeout(200)
    fs = page.evaluate("getComputedStyle(document.querySelector('#etqEtiqueta .etq-item.selecionado')).fontSize")
    check("Tamanho (pt) muda a fonte no desenho", fs.endswith("px") and float(fs[:-2]) > 40, fs)
    # afastar os números das barras some com o aviso
    linha = page.locator("#listaItens .etq-lista-item", has_text=re.compile(r"^Números do código de barras$")).first
    bb = linha.bounding_box(); page.mouse.click(bb["x"] + bb["width"] - 30, bb["y"] + bb["height"] / 2); page.wait_for_timeout(200)
    y = page.locator("#corpoPropriedades input[data-prop=y]")
    y.fill("40"); y.dispatch_event("input"); page.wait_for_timeout(200)
    aviso = page.locator("#avisoBarras").inner_text() if page.locator("#avisoBarras").is_visible() else ""
    check("afastar os números (Topo 40 mm) tira os números do aviso", "Números do código de barras" not in aviso, aviso)

    page.click("#btnSalvar"); page.wait_for_load_state("networkidle"); page.wait_for_timeout(400)
    check("salvar volta para a lista de etiquetas", page.url.rstrip("/").endswith("/app/etiquetas"), page.url)
    page.goto(f"{BASE}/app/etiquetas/layout-fixo/termica-tag-35x60"); page.wait_for_load_state("networkidle"); page.wait_for_timeout(400)
    check("reabrir: 'Preço' continua fora e os demais (8) estão lá", cnt() == 8 and "Preço" not in tipos(), (cnt(), tipos()))
    y2 = page.evaluate("JSON.parse(document.getElementById('inputLayout').value || '{}')") if page.locator("#inputLayout").count() else None
    vb = page.evaluate("document.querySelector('#etqEtiqueta .etq-item svg')?.getAttribute('viewBox')")
    check("prévia do EAN-13 com zona de silêncio de 11 módulos (viewBox 234 de largura)", vb and vb.split()[2] == "234", vb)

    # impressão com layout livre: margem do SVG
    page.goto(f"{BASE}/app/etiquetas"); page.wait_for_load_state("networkidle")
    csrf = page.get_attribute("input[name=_token]", "value")
    body = page.evaluate("""async (csrf) => { const fd = new URLSearchParams(); fd.append('_token', csrf); fd.append('formato', 'termica-tag-35x60'); fd.append('produtos[3]', '1');
        const r = await fetch('/app/etiquetas/gerar', {method:'POST', body: fd}); return await r.text(); }""", csrf)
    p2 = ctx.new_page(); p2.route(f"{BASE}/__print", lambda route: route.fulfill(status=200, content_type="text/html", body=body)); p2.goto(f"{BASE}/__print"); p2.wait_for_timeout(800)
    vbp = p2.evaluate("document.querySelector('.etiqueta-livre svg.barcode-livre')?.getAttribute('viewBox')")
    check("impressão (layout livre): EAN-13 com viewBox 234 = 11 módulos de silêncio por lado", vbp and vbp.split()[2] == "234", vbp)
    check("impressão: sem 'Preço' (desmarcado) e com nome fantasia", body.count("R$ 45,00") > 0 and "Empresa Demo Ltda" not in body, body.count("R$ 45,00"))
    check("sem erro de JavaScript no editor", not erros, erros)
    print(f"\nTOTAL {OK} ✅ / {FAIL} ❌")
    b.close()
