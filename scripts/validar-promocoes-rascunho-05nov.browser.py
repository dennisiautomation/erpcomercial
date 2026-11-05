"""Validação ✅/❌ — Rascunho de promoção (05/11/2026): salvar no meio, outro atendente continua, aviso de
versão, publicar a partir do rascunho, editar agendada.

Ambiente DESCARTÁVEL (cs-*), empresa demo 1: dono@demo.com (Joao Silva) e gerente@demo.com (Maria Santos, Loja Centro).
Apaga no fim as promoções que criou (nome VAL05NOV-R*).
    docker run --rm --network container:cs-mysql -e BASE=http://127.0.0.1:8298 -v $PWD/scripts:/s \
        mcr.microsoft.com/playwright/python:v1.55.0-noble python3 /s/validar-promocoes-rascunho-05nov.browser.py
"""
import datetime
import os
import re
from playwright.sync_api import sync_playwright

B = os.environ["BASE"]
OK = FAIL = 0
H = datetime.date.today()
NOME = "VAL05NOV-R Black Friday"


def check(nome, cond, obtido=""):
    global OK, FAIL
    if cond:
        OK += 1
        print(f"✅ {nome}")
    else:
        FAIL += 1
        print(f"❌ {nome}  (obtido: {str(obtido)[:250]})")


def entrar(b, email, senha):
    ctx = b.new_context(viewport={"width": 1440, "height": 900})
    p = ctx.new_page()
    p.erros = []
    p.on("pageerror", lambda e: p.erros.append(str(e)))
    p.on("dialog", lambda d: d.accept())
    p.goto(f"{B}/login")
    p.fill("input[name=email]", email)
    p.fill("input[name=password]", senha)
    p.click("button[type=submit]")
    p.wait_for_load_state("networkidle")
    return p


def toast(p):
    p.wait_for_timeout(600)
    return p.locator("#erp-toasts").all_inner_texts()


with sync_playwright() as pw:
    b = pw.chromium.launch()
    ana = entrar(b, "dono@demo.com", "dono123")

    # Ana começa: nome + 1 produto, sem datas nem preço, e salva rascunho
    ana.goto(f"{B}/app/promocoes/nova")
    check("botão Salvar rascunho aparece", ana.locator("#promo-salvar-rascunho").is_visible())
    ana.fill("#promo-nome", NOME)
    ana.click("#promo-avancar")  # avisa que faltam datas
    ana.click('#promo-passos button[data-ir="1"]')
    ana.click("#promo-salvar-rascunho")
    ana.wait_for_function("document.querySelector('#promo-rascunho-info').innerText.includes('Rascunho salvo')", timeout=6000)
    check("rascunho salvo sem datas e sem produto", "Rascunho salvo por Joao Silva" in ana.inner_text("#promo-rascunho-info"), ana.inner_text("#promo-rascunho-info"))
    check("endereço passa a ser o do rascunho (/editar)", re.search(r"/app/promocoes/\d+/editar$", ana.url), ana.url)
    url_rasc = ana.url
    # passo 3: adiciona Mouse e salva de novo
    ana.evaluate("document.querySelectorAll('#promo-passos button')[2].click()")
    ana.fill("#promo-busca", "mouse")
    ana.wait_for_selector("#promo-resultados [data-add]", timeout=6000)
    ana.click("#promo-resultados [data-add]")
    ana.click("#promo-salvar-rascunho")
    ana.wait_for_timeout(800)
    check("salvar de novo atualiza o mesmo rascunho", ana.url == url_rasc and "Rascunho salvo" in ana.inner_text("#promo-rascunho-info"))

    # Lista
    ana.goto(f"{B}/app/promocoes")
    lst = ana.inner_text("table")
    check("lista mostra o rascunho, quem salvou e Continuar", NOME in lst and "Rascunho" in lst and "Salvo por Joao Silva" in lst and "Continuar" in lst, lst)

    # PDV não muda (rascunho não vale)
    r = ana.request.get(f"{B}/app/pdv/produto/mouse").json()
    mouse = next((x for x in r if "Mouse" in x["descricao"]), {})
    check("rascunho não muda o preço no PDV", not mouse.get("promocao") and abs(float(mouse["precos"]["dinheiro_pix"]) - 599.90) < 0.01, mouse.get("precos"))

    # Maria (gerente) continua o rascunho da Ana
    maria = entrar(b, "gerente@demo.com", "gerente123")
    maria.goto(f"{B}/app/promocoes")
    maria.click(f"tr:has-text('{NOME}') a:has-text('Continuar')")
    maria.wait_for_load_state("networkidle")
    check("Maria abre o rascunho: título e quem salvou", "Continuar rascunho" in maria.content() and "Salvo por Joao Silva" in maria.inner_text("#promo-rascunho-info"))
    check("Maria vê o nome que a Ana digitou", maria.input_value("#promo-nome") == NOME, maria.input_value("#promo-nome"))
    maria.evaluate("document.querySelectorAll('#promo-passos button')[2].click()")
    check("Maria vê o produto que a Ana adicionou", "Mouse" in maria.inner_text("#promo-lista"), maria.inner_text("#promo-lista"))

    # Ana ainda com a tela antiga aberta; Maria salva primeiro
    ana.goto(url_rasc)
    maria.evaluate("document.querySelectorAll('#promo-passos button')[0].click()")
    maria.fill("#promo-inicio", (H + datetime.timedelta(days=20)).isoformat())
    maria.dispatch_event("#promo-inicio", "change")
    maria.wait_for_timeout(1100)  # a versão é o horário do último salvamento
    maria.click("#promo-salvar-rascunho")
    maria.wait_for_function("document.querySelector('#promo-rascunho-info').innerText.includes('Maria Santos')", timeout=6000)
    check("Maria salva: 'Rascunho salvo por Maria Santos'", "Maria Santos" in maria.inner_text("#promo-rascunho-info"))
    ana.fill("#promo-nome", NOME + " (Ana)")
    ana.click("#promo-salvar-rascunho")
    avisos = " ".join(toast(ana))
    check("Ana com tela antiga recebe o aviso e não sobrescreve", "Maria Santos salvou" in avisos, avisos)

    # Maria termina e publica
    maria.evaluate("document.querySelectorAll('#promo-passos button')[1].click()")
    if not maria.locator(".promo-loja:checked").count():
        maria.click("#promo-todas-lojas")
    maria.click("#promo-avancar"); maria.click("#promo-avancar")
    maria.fill("#promo-pct-todos", "15"); maria.click("#promo-aplicar-pct")
    maria.click("#promo-avancar")
    check("botão final é Salvar promoção", "Salvar promoção" in maria.inner_text("#promo-salvar"))
    maria.click("#promo-salvar")
    maria.wait_for_load_state("networkidle")
    titulo = maria.locator("h4").first.inner_text()
    check("publicada: abre a promoção como Agendada", "Agendada" in maria.content() and NOME in titulo, maria.url)
    check("o nome é o da Maria — a tela antiga da Ana não sobrescreveu", "(Ana)" not in titulo, titulo)
    id_promo = re.search(r"/app/promocoes/(\d+)", maria.url).group(1)
    check("mesmo registro do rascunho", url_rasc.rstrip("/editar").endswith("/" + id_promo) or f"/{id_promo}/editar" in url_rasc, (url_rasc, id_promo))

    # Editar agendada
    maria.click("a:has-text('Editar')")
    maria.wait_for_load_state("networkidle")
    check("Editar abre 'Editar promoção', sem 'Salvar rascunho'", "Editar promoção" in maria.content() and maria.locator("#promo-salvar-rascunho").count() == 0)
    maria.evaluate("document.querySelectorAll('#promo-passos button')[3].click()")
    maria.fill("#promo-pct-todos", "25"); maria.click("#promo-aplicar-pct")
    maria.click('#promo-passos button[data-ir="5"]')
    check("botão final é Salvar alterações", "Salvar alterações" in maria.inner_text("#promo-salvar"))
    maria.click("#promo-salvar")
    maria.wait_for_load_state("networkidle")
    check("alteração salva (25% no Mouse = R$ 449,92)", "R$ 449,92" in maria.content() and "salva com 1 produto" in maria.content(), maria.url)

    check("sem erro de JS (Ana)", not ana.erros, ana.erros)
    check("sem erro de JS (Maria)", not maria.erros, maria.erros)
    b.close()

print(f"\n{OK} ✅ / {FAIL} ❌")
