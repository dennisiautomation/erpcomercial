# Validação ✅/❌ da busca de produto na Nova Movimentação de Estoque (25/10/2026).
# Ambiente DESCARTÁVEL. Roda no container mcr.microsoft.com/playwright/python com --network host:
#   python3 validar-busca-movimentacao.py http://127.0.0.1:8097 <pasta-prints>
import sys, subprocess, json
from playwright.sync_api import sync_playwright
B = sys.argv[1]; OUT = sys.argv[2]
ok = falha = 0
def check(nome, cond, obtido=''):
    global ok, falha
    if cond: ok += 1; print('✅', nome)
    else: falha += 1; print('❌', nome, f'(obtido: {obtido})')
with sync_playwright() as p:
    b = p.chromium.launch(); pg = b.new_page(viewport={'width': 1400, 'height': 1000})
    pg.goto(B + '/login'); pg.fill('input[name=email]', 'dono@demo.com'); pg.fill('input[name=password]', 'dono123'); pg.click('button[type=submit]'); pg.wait_for_load_state()
    pg.goto(B + '/app/estoque/movimentacoes/create'); pg.wait_for_load_state()
    check('tela abre sem a lista suspensa de produtos', pg.locator('select[name="itens[0][produto_id]"]').count() == 0)
    t = pg.locator('.produto-texto').first; sug = pg.locator('.produto-sugestoes').first
    t.fill('Mo'); t.dispatch_event('input')
    check('com 2 letras não abre sugestão', not sug.is_visible(), pg.locator('.produto-dica').first.inner_text())
    t.fill('Mou'); t.dispatch_event('input'); pg.wait_for_timeout(100)
    itens = sug.locator('.list-group-item-action')
    check('com 3 letras mostra o Mouse', sug.is_visible() and itens.count() >= 1 and 'Mouse' in itens.first.inner_text(), sug.inner_text() if sug.is_visible() else '')
    pg.screenshot(path=OUT + '/busca-sugestoes.png')
    itens.first.dispatch_event('mousedown')
    hid = pg.locator('.produto-id').first.input_value()
    check('clicar escolhe o produto 2 (hidden preenchido)', hid == '2', hid)
    check('o texto vira "PROD002 · Mouse..."', t.input_value().startswith('PROD002 · Mouse'), t.input_value())
    pg.fill('input[name="itens[0][quantidade]"]', '3')
    pg.click('#btn-add-item'); pg.wait_for_timeout(100)
    t2 = pg.locator('.produto-texto').nth(1)
    check('item novo também é campo de busca e já vem com o foco', t2.evaluate('e => document.activeElement === e'))
    t2.type('7890000000003'); t2.press('Enter'); pg.wait_for_timeout(200)
    check('bipar o código de barras + Enter escolhe o Teclado (id 3) e NÃO envia o formulário',
          pg.locator('.produto-id').nth(1).input_value() == '3' and '/create' in pg.url, pg.locator('.produto-id').nth(1).input_value() + ' ' + pg.url)
    pg.fill('input[name="itens[1][quantidade]"]', '2')
    pg.click('#btn-add-item'); pg.wait_for_timeout(100)
    t3 = pg.locator('.produto-texto').nth(2)
    t3.fill('mecânico red'); t3.dispatch_event('input'); pg.wait_for_timeout(100)
    s3 = pg.locator('.produto-sugestoes').nth(2)
    check('busca ignora acento e aceita várias palavras ("mecânico red")', s3.is_visible() and 'Teclado' in s3.inner_text(), s3.inner_text() if s3.is_visible() else '')
    t3.fill('MX3'); t3.dispatch_event('input'); pg.wait_for_timeout(100)
    check('busca pelo SKU (MX3) acha o Mouse', 'Mouse' in s3.inner_text(), s3.inner_text())
    t3.fill('xyzxyz'); t3.dispatch_event('input'); pg.wait_for_timeout(100)
    check('termo sem resultado avisa "Nenhum produto"', 'Nenhum produto' in s3.inner_text())
    pg.fill('input[name="itens[2][quantidade]"]', '1')
    antes = pg.url
    pg.locator('#form-movimentacao button[type=submit]').first.click(); pg.wait_for_timeout(500)
    check('linha só digitada (sem escolher) bloqueia o envio e explica', pg.url == antes and 'Escolha o produto' in pg.locator('.produto-dica').nth(2).inner_text(), pg.locator('.produto-dica').nth(2).inner_text())
    pg.locator('.btn-remove-item').nth(2).click()
    pg.screenshot(path=OUT + '/busca-preenchida.png')
    with pg.expect_navigation(): pg.locator('#form-movimentacao button[type=submit]').first.click()
    check('salvar com 2 itens escolhidos redireciona', '/create' not in pg.url, pg.url)
    b.close()
print(f'\nResultado: {ok} ✅  {falha} ❌')
sys.exit(1 if falha else 0)
