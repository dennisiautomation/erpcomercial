"""Validação ✅/❌ — vendedor com a chave `pode_editar_produtos` EDITA produto pela TELA (05/11/2026).

Bug (DONA DOURO, Mirella/Manoel): a tela de editar decidia o modo "só foto" pelo PERFIL (can), travava
os campos e mandava só a foto; o servidor (canUser) recusava descrição/preço vazios. Agora a tela usa canUser.
O roteiro de 04/11 testava o salvar por POST direto — aqui é o navegador, como a pessoa faz.

Ambiente DESCARTÁVEL (cs-*). O bash em volta prepara o banco (chave ligada, vínculo de loja retirado) e restaura.
    docker run --rm --network container:cs-mysql -e BASE=http://127.0.0.1:8298 -e FASE=com -v $PWD/scripts:/s \
        mcr.microsoft.com/playwright/python:v1.55.0-noble python3 /s/validar-editar-produto-chave-05nov.browser.py
FASE=com  → vendedor COM a chave; FASE=sem → vendedor SEM a chave (continua só foto).
"""
import os
from playwright.sync_api import sync_playwright

BASE = os.environ["BASE"]
FASE = os.environ.get("FASE", "com")
PRODUTO = os.environ.get("PRODUTO", "2")
NOVA = "MOUSE EDITADO PELA VENDEDORA TELA"
OK = FAIL = 0


def check(nome, cond, obtido=""):
    global OK, FAIL
    if cond:
        OK += 1
        print(f"✅ {nome}")
    else:
        FAIL += 1
        print(f"❌ {nome}  (obtido: {str(obtido)[:300]})")


with sync_playwright() as pw:
    b = pw.chromium.launch()
    page = b.new_page(viewport={"width": 1400, "height": 1000})
    erros_js = []
    page.on("pageerror", lambda e: erros_js.append(str(e)))
    page.on("dialog", lambda d: d.accept())

    page.goto(f"{BASE}/login")
    page.fill("input[name=email]", "vendedor@demo.com")
    page.fill("input[name=password]", "vendedor123")
    page.click("button[type=submit]")
    page.wait_for_load_state("networkidle")

    r = page.goto(f"{BASE}/app/produtos/{PRODUTO}/edit")
    page.wait_for_load_state("networkidle")
    check(f"[{FASE}] tela de editar abre (200)", r.status == 200, r.status)
    aviso_foto = page.locator("text=apenas a foto").count() > 0
    desc_off = page.locator("#formProduto [name=descricao]").is_disabled()

    if FASE == "com":
        check("[com] SEM o aviso 'apenas a foto'", not aviso_foto)
        check("[com] campo descrição liberado", not desc_off)
        check("[com] campo preço de venda liberado", not page.locator("#formProduto [name=preco_venda]").is_disabled())
        page.fill("#formProduto [name=descricao]", NOVA)
        # envia o formulário como o botão Salvar (campos desabilitados ficariam de fora, como no bug)
        with page.expect_navigation():
            page.evaluate("document.getElementById('formProduto').requestSubmit()")
        page.wait_for_load_state("networkidle")
        corpo = page.content()
        check("[com] salvou sem erro de validação", "obrigatório" not in corpo and "is-invalid" not in corpo, page.url)
        check("[com] depois de salvar a descrição nova aparece", NOVA in corpo, page.url)
        page.goto(f"{BASE}/app/produtos/{PRODUTO}")
        page.wait_for_load_state("networkidle")
        check("[com] tela do produto mostra o envio de foto", page.locator("input[name=foto]").count() > 0)

        page.goto(f"{BASE}/app/promocoes/nova")
        page.wait_for_load_state("networkidle")
        lojas = page.locator("input[name='unidades[]'], [data-loja], .promo-loja").count()
        check("[com] Promoções abre e oferece a loja", lojas >= 1, lojas)
    else:
        check("[sem] continua com o aviso 'apenas a foto'", aviso_foto)
        check("[sem] campo descrição travado", desc_off)

    check(f"[{FASE}] sem erro de JavaScript", not erros_js, erros_js)
    b.close()

print(f"\n{OK} ✅ / {FAIL} ❌")
raise SystemExit(1 if FAIL else 0)
