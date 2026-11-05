"""Validação ✅/❌ — Promoções por período (05/11/2026): assistente, duplicar, sugestão da última,
bloqueio de sobreposição, preço no PDV, encerrar.

Ambiente DESCARTÁVEL (cs-*), empresa demo 1 (Loja Centro), produtos 1 (Notebook) e 2 (Mouse). No fim
apaga as promoções que criou (armadilha 95: o banco cs-mysql é dividido com os outros roteiros).
    docker run --rm --network container:cs-mysql -e BASE=http://127.0.0.1:8297 -v $PWD/scripts:/s \
        mcr.microsoft.com/playwright/python:v1.55.0-noble python3 /s/validar-promocoes-05nov.browser.py
"""
import datetime
import os
import re
from playwright.sync_api import sync_playwright

BASE = os.environ["BASE"]
SHOTS = os.environ.get("SHOTS", "/s/out-promocoes-05nov")
os.makedirs(SHOTS, exist_ok=True)
OK = FAIL = 0
HOJE = datetime.date.today()
ESPERADO_TODOS = int(os.environ.get("ESPERADO_TODOS", "0"))  # produtos ativos com preço > 0 da empresa demo
SEM_PRECO = int(os.environ.get("SEM_PRECO", "0"))
NOME = "VAL05NOV Promo Teste"


def check(nome, cond, obtido=""):
    global OK, FAIL
    if cond:
        OK += 1
        print(f"✅ {nome}")
    else:
        FAIL += 1
        print(f"❌ {nome}  (obtido: {str(obtido)[:300]})")


def iso(d):
    return d.isoformat()


with sync_playwright() as pw:
    b = pw.chromium.launch()
    page = b.new_page(viewport={"width": 1400, "height": 1000})
    erros_js = []
    page.on("pageerror", lambda e: erros_js.append(str(e)))
    dialogos = []
    page.on("dialog", lambda d: (dialogos.append(d.message), d.accept()))

    page.goto(f"{BASE}/login")
    page.fill("input[name=email]", "dono@demo.com")
    page.fill("input[name=password]", "dono123")
    page.click("button[type=submit]")
    page.wait_for_load_state("networkidle")

    # Menu
    page.goto(f"{BASE}/app/dashboard")
    check("menu Cadastros tem Promoções", page.locator("a.nav-link", has_text="Promoções").count() == 1)

    # ---- 1ª promoção pelo assistente ----
    page.goto(f"{BASE}/app/promocoes/nova")
    check("assistente abre no passo 1", page.locator('.promo-passo[data-passo="1"]').is_visible())
    page.click("#promo-avancar")
    check("passo 1 sem dados não avança (avisa)", page.locator('.promo-passo[data-passo="1"]').is_visible() and dialogos and "nome" in dialogos[-1], dialogos[-1:] )
    page.fill("#promo-nome", NOME)
    page.fill("#promo-inicio", iso(HOJE))
    page.dispatch_event("#promo-inicio", "change")
    check("fim vem igual ao início", page.input_value("#promo-fim") == iso(HOJE), page.input_value("#promo-fim"))
    page.fill("#promo-fim", iso(HOJE + datetime.timedelta(days=3)))
    page.dispatch_event("#promo-fim", "change")
    page.click("#promo-avancar")
    check("foi para o passo 2 (lojas)", page.locator('.promo-passo[data-passo="2"]').is_visible())
    n = len(dialogos)
    page.click("#promo-avancar")
    check("sem loja não avança", page.locator('.promo-passo[data-passo="2"]').is_visible() and len(dialogos) == n + 1)
    page.click("#promo-todas-lojas")
    check("marcar todas marca a Loja Centro", page.locator(".promo-loja:checked").count() == 1)
    page.click("#promo-avancar")

    # passo 3: busca + adicionar
    page.fill("#promo-busca", "mouse logitech")
    page.wait_for_selector("#promo-resultados [data-add]", timeout=5000)
    check("busca acha o Mouse (palavras)", "Mouse Logitech" in page.inner_text("#promo-resultados"))
    page.click("#promo-resultados [data-add]")
    check("Mouse entrou na lista", page.inner_text("#promo-qtd") == "1" and "Mouse" in page.inner_text("#promo-lista"))
    check("resultado mostra 'na lista'", "na lista" in page.inner_text("#promo-resultados"))
    page.fill("#promo-busca", "notebook")
    page.wait_for_function("document.querySelector('#promo-resultados').innerText.includes('Notebook')", timeout=5000)
    page.click("#promo-add-todos")
    check("adicionar todos põe o Notebook", page.inner_text("#promo-qtd") == "2", page.inner_text("#promo-qtd"))
    page.click("#promo-avancar")

    # passo 4: preços
    check("passo 4 mostra os 2 produtos", page.locator("#promo-tabela-precos tr").count() == 2)
    n = len(dialogos)
    page.click("#promo-avancar")
    check("sem preço não avança", len(dialogos) == n + 1 and "falta" in dialogos[-1], dialogos[-1:])
    page.fill("#promo-pct-todos", "10")
    page.click("#promo-aplicar-pct")
    txt = page.inner_text("#promo-tabela-precos")
    check("10% em todos: Mouse fica por R$ 539,91", "R$ 539,91" in txt, txt)
    # Notebook em preço fixo
    linha_nb = page.locator("#promo-tabela-precos tr", has_text="Notebook")
    linha_nb.locator("select[data-tipo]").select_option("preco")
    linha_nb.locator("input[data-valor]").fill("4500")
    check("Notebook por R$ 4.500,00", "R$ 4.500,00" in linha_nb.inner_text(), linha_nb.inner_text())
    # Cartão (05/11, STILO): Notebook R$ 4.700 no cartão; Mouse 5% sobre o cartão normal
    check("cartão começa na regra da loja", "regra da loja" in linha_nb.inner_text(), linha_nb.inner_text())
    linha_nb.locator("select[data-cartao-tipo]").select_option("preco")
    linha_nb.locator("input[data-cartao-valor]").fill("4700")
    check("Notebook cartão por R$ 4.700,00", "R$ 4.700,00" in linha_nb.inner_text(), linha_nb.inner_text())
    linha_ms = page.locator("#promo-tabela-precos tr", has_text="Mouse")
    linha_ms.locator("select[data-cartao-tipo]").select_option("percentual")
    n = len(dialogos)
    page.click("#promo-avancar")
    check("cartão escolhido sem valor não avança", len(dialogos) == n + 1 and "cartão" in dialogos[-1], dialogos[-1:])
    linha_ms.locator("input[data-cartao-valor]").fill("5")
    check("Mouse cartão 5% = R$ 569,91", "R$ 569,91" in linha_ms.inner_text(), linha_ms.inner_text())
    page.click("#promo-avancar")
    rev = page.inner_text("#promo-revisao")
    check("revisão: nome, período e loja", NOME in rev and "Loja Centro" in rev and HOJE.strftime("%d/%m/%Y") in rev, rev)
    check("revisão: de/por", "R$ 599,90" in rev and "R$ 539,91" in rev, rev)
    check("revisão: cartão R$ 4.700,00 e R$ 569,91", "R$ 4.700,00" in rev and "R$ 569,91" in rev, rev)
    page.screenshot(path=f"{SHOTS}/1-revisao.png", full_page=True)
    page.click("#promo-salvar")
    page.wait_for_load_state("networkidle")
    check("salvou e abriu a promoção", "/app/promocoes/" in page.url and "criada com 2 produto" in page.content(), page.url)
    check("tela da promoção: Em andamento + R$ 539,91", "Em andamento" in page.content() and "R$ 539,91" in page.content())
    check("tela da promoção: cartão R$ 4.700,00 e R$ 569,91", "R$ 4.700,00" in page.content() and "R$ 569,91" in page.content())
    id1 = re.search(r"/app/promocoes/(\d+)", page.url).group(1)
    page.screenshot(path=f"{SHOTS}/2-promocao.png", full_page=True)

    # ---- PDV: busca já traz o preço da promoção ----
    r = page.request.get(f"{BASE}/app/pdv/produto/{'mouse logitech'}").json()
    mouse = next((p for p in r if "Mouse" in p["descricao"]), {})
    check("PDV: Mouse com aviso de promoção", mouse.get("promocao") == NOME, mouse.get("promocao"))
    check("PDV: Mouse R$ 539,91 no Dinheiro/PIX", abs(float(mouse.get("precos", {}).get("dinheiro_pix", 0)) - 539.91) < 0.001, mouse.get("precos"))
    check("PDV: Mouse R$ 569,91 no crédito", abs(float(mouse.get("precos", {}).get("credito", 0)) - 569.91) < 0.001, mouse.get("precos"))

    # ---- Duplicar com o mesmo período = bloqueado ----
    page.click("a:has-text('Duplicar')")
    page.wait_for_load_state("networkidle")
    check("duplicar abre com o nome da original", page.input_value("#promo-nome") == NOME)
    page.fill("#promo-inicio", iso(HOJE + datetime.timedelta(days=1)))
    page.dispatch_event("#promo-inicio", "change")
    page.fill("#promo-fim", iso(HOJE + datetime.timedelta(days=2)))
    page.dispatch_event("#promo-fim", "change")
    page.click("#promo-avancar")
    check("duplicar: loja já vem marcada", page.locator(".promo-loja:checked").count() == 1)
    page.click("#promo-avancar")
    check("duplicar: 2 produtos já na lista", page.inner_text("#promo-qtd") == "2")
    page.click("#promo-avancar")
    check("duplicar: preços da original (10% e R$ 4.500)", "R$ 539,91" in page.inner_text("#promo-tabela-precos") and "R$ 4.500,00" in page.inner_text("#promo-tabela-precos"))
    check("duplicar: cartão da original (R$ 4.700 e 5%)", "R$ 4.700,00" in page.inner_text("#promo-tabela-precos") and "R$ 569,91" in page.inner_text("#promo-tabela-precos"))
    page.click("#promo-avancar")
    page.click("#promo-salvar")
    page.wait_for_load_state("networkidle")
    erro = page.inner_text("body")
    check("sobreposição bloqueada com o nome da promoção", "já está na promoção" in erro and NOME in erro, erro[:300])
    check("aviso de erro aparece uma vez só", erro.count("Mouse Logitech MX Master 3 já está na promoção") == 1, erro.count("já está na promoção"))
    check("volta no passo 4 com a lista mantida", page.locator('.promo-passo[data-passo="4"]').is_visible() and page.locator("#promo-tabela-precos tr").count() == 2)
    page.screenshot(path=f"{SHOTS}/3-conflito.png", full_page=True)

    # Muda para depois do fim → salva como agendada, com origem
    page.click('#promo-passos button[data-ir="1"]')
    page.fill("#promo-inicio", iso(HOJE + datetime.timedelta(days=10)))
    page.dispatch_event("#promo-inicio", "change")
    page.fill("#promo-fim", iso(HOJE + datetime.timedelta(days=12)))
    page.dispatch_event("#promo-fim", "change")
    page.fill("#promo-nome", NOME + " 2")
    page.click('#promo-passos button[data-ir="5"]')
    page.click("#promo-salvar")
    page.wait_for_load_state("networkidle")
    check("duplicada salva como Agendada", "Agendada" in page.content() and "duplicada de" in page.content(), page.url)
    id2 = re.search(r"/app/promocoes/(\d+)", page.url).group(1)

    # ---- Nova: sugere a lista da última ----
    page.goto(f"{BASE}/app/promocoes/nova")
    page.fill("#promo-nome", NOME + " 3")
    page.fill("#promo-inicio", iso(HOJE + datetime.timedelta(days=20)))
    page.dispatch_event("#promo-inicio", "change")
    page.click("#promo-avancar")
    page.click("#promo-todas-lojas")
    page.click("#promo-avancar")
    check("sugere a lista da última promoção", "Usar a lista da última promoção" in page.inner_text("#promo-sugestao") and NOME + " 2" in page.inner_text("#promo-sugestao"))
    page.click("#promo-usar-ultima")
    page.wait_for_function("document.querySelector('#promo-qtd').innerText === '2'", timeout=5000)
    check("usar esta lista: 2 produtos", page.inner_text("#promo-qtd") == "2")
    page.click("#promo-avancar")
    check("usar esta lista: veio com os preços", "R$ 4.500,00" in page.inner_text("#promo-tabela-precos"))

    # ---- Adicionar todos os produtos da loja (sem buscar) ----
    page.goto(f"{BASE}/app/promocoes/nova")
    page.fill("#promo-nome", NOME + " 4")
    page.fill("#promo-inicio", iso(HOJE + datetime.timedelta(days=30)))
    page.dispatch_event("#promo-inicio", "change")
    page.click("#promo-avancar"); page.click("#promo-todas-lojas"); page.click("#promo-avancar")
    n = len(dialogos)
    page.click("#promo-add-catalogo")
    page.wait_for_function("document.querySelector('#promo-qtd').innerText !== '0'", timeout=8000)
    page.wait_for_timeout(300)
    qtd = int(page.inner_text("#promo-qtd"))
    check(f"todos os produtos com preço entraram ({qtd})", qtd == ESPERADO_TODOS, f"{qtd} x {ESPERADO_TODOS}")
    check("aviso de quantos entraram e quantos ficaram de fora", len(dialogos) == n + 1 and f"{ESPERADO_TODOS} produto(s) adicionados" in dialogos[-1]
          and (SEM_PRECO == 0 or f"{SEM_PRECO} sem preço" in dialogos[-1]), dialogos[-1:])
    page.click("#promo-avancar")
    page.fill("#promo-pct-todos", "10"); page.click("#promo-aplicar-pct")
    page.fill("#promo-pct-cartao-todos", "5"); page.click("#promo-aplicar-pct-cartao")
    check("% em lote no à vista e no cartão para todos", page.locator("#promo-tabela-precos select[data-cartao-tipo]").evaluate_all("els => els.every(e => e.value === 'percentual')"))
    page.click("#promo-cartao-regra")
    check("'Cartão pela regra da loja em todos' volta para a regra", page.locator("#promo-tabela-precos select[data-cartao-tipo]").evaluate_all("els => els.every(e => e.value === '')"))

    # ---- Encerrar a 1ª (modal do ERP) → PDV volta ao normal ----
    page.goto(f"{BASE}/app/promocoes/{id1}")
    page.click("button:has-text('Encerrar agora')")
    modal_btn = page.locator(".modal.show button.btn-danger, .modal.show button.btn-primary, .modal.show .btn-erp-primary").first
    modal_btn.wait_for(timeout=5000)
    modal_btn.click()
    page.wait_for_load_state("networkidle")
    check("encerrada: 'Encerrada antes do fim'", "Encerrada antes do fim" in page.content())
    r = page.request.get(f"{BASE}/app/pdv/produto/{'mouse logitech'}").json()
    mouse = next((p for p in r if "Mouse" in p["descricao"]), {})
    check("PDV: Mouse voltou a R$ 599,90 e sem aviso", abs(float(mouse.get("precos", {}).get("dinheiro_pix", 0)) - 599.90) < 0.001 and not mouse.get("promocao"), mouse)

    # ---- Lista ----
    page.goto(f"{BASE}/app/promocoes")
    lst = page.inner_text("table")
    check("lista mostra as 2 promoções e as situações", NOME in lst and "Agendada" in lst and "Encerrada antes do fim" in lst, lst)
    page.screenshot(path=f"{SHOTS}/4-lista.png", full_page=True)

    # ---- Excluir a agendada (modal) ----
    page.goto(f"{BASE}/app/promocoes/{id2}")
    page.click("form[action$='/promocoes/%s'] button" % id2)
    modal_btn = page.locator(".modal.show button.btn-danger, .modal.show button.btn-primary, .modal.show .btn-erp-primary").first
    modal_btn.wait_for(timeout=5000)
    modal_btn.click()
    page.wait_for_load_state("networkidle")
    check("agendada excluída", "excluída" in page.content() and page.url.rstrip("/").endswith("/app/promocoes"), page.url)

    check("sem erro de JS", not erros_js, erros_js)
    b.close()

print(f"\n{OK} ✅ / {FAIL} ❌")
print("IDS", id1, id2)
