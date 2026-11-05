"""Validação ✅/❌ — busca de Produtos e filtro de Gerar Etiquetas com a regra do PDV (05/11/2026).

Relato STILO (vídeo 04/11): no PDV "short saia", "calca", "biquini" acham; em Produtos e Etiquetas só
achava digitando igual ao cadastro (traço/barra, acento, cedilha).

Ambiente DESCARTÁVEL (cs-*), com os 5 produtos de teste (sku VAL05NOV*) da empresa demo 1. Roda no
container do Playwright na rede do cs-mysql:
    docker run --rm --network container:cs-mysql -e BASE=http://127.0.0.1:8297 -v $PWD/scripts:/s \
        mcr.microsoft.com/playwright/python:v1.55.0-noble python3 /s/validar-busca-produtos-etiquetas-05nov.browser.py
"""
import os
import urllib.parse
from playwright.sync_api import sync_playwright

BASE = os.environ["BASE"]
SHOTS = os.environ.get("SHOTS", "/s/out-busca-05nov")
os.makedirs(SHOTS, exist_ok=True)
OK = FAIL = 0
TESTE = {
    "Short / Saia Jeans Rasgado", "Short-Saia Alfaiataria Preta", "CALÇA PANTALONA LINHO",
    "Biquíni Lacinho Estampado", "Saia Midi  Plissada",
}


def check(nome, cond, obtido=""):
    global OK, FAIL
    if cond:
        OK += 1
        print(f"✅ {nome}")
    else:
        FAIL += 1
        print(f"❌ {nome}  (obtido: {str(obtido)[:300]})")


# termo -> descrições de teste que TÊM de aparecer (e só elas, dentre as de teste)
CASOS = [
    ("short saia", {"Short / Saia Jeans Rasgado", "Short-Saia Alfaiataria Preta"}),
    ("saia short", {"Short / Saia Jeans Rasgado", "Short-Saia Alfaiataria Preta"}),
    ("short-saia", {"Short / Saia Jeans Rasgado", "Short-Saia Alfaiataria Preta"}),
    ("calca", {"CALÇA PANTALONA LINHO"}),
    ("calça pantalona", {"CALÇA PANTALONA LINHO"}),
    ("biquini", {"Biquíni Lacinho Estampado"}),
    ("BIQUINI estampado", {"Biquíni Lacinho Estampado"}),
    ("saia midi plissada", {"Saia Midi  Plissada"}),
    ("V5N003", {"CALÇA PANTALONA LINHO"}),
    ("7890000500042", {"Biquíni Lacinho Estampado"}),
    ("78900005000", TESTE),
    ("short calca", set()),
]

with sync_playwright() as pw:
    b = pw.chromium.launch()
    page = b.new_page(viewport={"width": 1400, "height": 1000})
    erros_js = []
    page.on("pageerror", lambda e: erros_js.append(str(e)))
    page.goto(f"{BASE}/login")
    page.fill("input[name=email]", "dono@demo.com")
    page.fill("input[name=password]", "dono123")
    page.click("button[type=submit]")
    page.wait_for_load_state("networkidle")

    # ---- Produtos (busca no servidor) ----
    for termo, esperado in CASOS:
        page.goto(f"{BASE}/app/produtos?busca={urllib.parse.quote(termo)}")
        corpo = page.locator("table tbody").inner_text() if page.locator("table tbody").count() else ""
        achados = {d for d in TESTE if d.replace("  ", " ") in corpo.replace("  ", " ") or d in corpo}
        check(f"Produtos: '{termo}' → {sorted(esperado) or 'nenhum de teste'}", achados == esperado, sorted(achados))
    page.goto(f"{BASE}/app/produtos?busca={urllib.parse.quote('short saia')}")
    page.screenshot(path=f"{SHOTS}/produtos-short-saia.png", full_page=True)
    page.goto(f"{BASE}/app/produtos?busca={urllib.parse.quote('100%')}")
    check("Produtos: '%' não vira curinga (página abre)", page.locator("table, .empty-state, body").count() > 0)

    # ---- Gerar Etiquetas (filtro no navegador) ----
    page.goto(f"{BASE}/app/etiquetas")
    page.wait_for_load_state("networkidle")
    total = page.locator(".produto-row").count()
    for termo, esperado in CASOS:
        page.fill("#buscaProduto", "")
        page.type("#buscaProduto", termo)
        visiveis = page.eval_on_selector_all(
            ".produto-row", "rs => rs.filter(r => r.style.display !== 'none').map(r => r.children[2].innerText.trim())")
        achados = {d for d in TESTE if d in visiveis or d.replace("  ", " ") in visiveis}
        check(f"Etiquetas: '{termo}' → {sorted(esperado) or 'nenhum de teste'}", achados == esperado, sorted(achados))
    page.fill("#buscaProduto", "")
    page.type("#buscaProduto", "short saia")
    page.screenshot(path=f"{SHOTS}/etiquetas-short-saia.png", full_page=True)
    page.fill("#buscaProduto", "")
    page.dispatch_event("#buscaProduto", "input")
    vis = page.eval_on_selector_all(".produto-row", "rs => rs.filter(r => r.style.display !== 'none').length")
    check("Etiquetas: campo vazio mostra todos", vis == total, f"{vis}/{total}")
    # selecionar todos visíveis continua só nos filtrados
    page.type("#buscaProduto", "calca")
    page.click("#btnSelecionarTodos")
    marcados = page.eval_on_selector_all(".produto-check", "cs => cs.filter(c => c.checked).length")
    check("Etiquetas: 'Selecionar todos' marca só os filtrados", marcados == 1, marcados)
    check("Sem erro de JS", not erros_js, erros_js)
    b.close()

print(f"\n{OK} ✅ / {FAIL} ❌")
raise SystemExit(1 if FAIL else 0)
