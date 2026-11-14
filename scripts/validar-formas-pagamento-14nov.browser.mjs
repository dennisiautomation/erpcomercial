// Validação ✅/❌ no NAVEGADOR — Configurações → Formas de pagamento (14/11/2026).
// Rodar DEPOIS do .py (ele deixa o caixa do dono aberto e a empresa no padrão de fábrica).
//   BASE=http://10.213.79.4 MYSQL=fp-mysql CHROME=/caminho/chrome-headless-shell NODE_PATH=/home/ubuntu/caixa-v2/node_modules PRINTS=/pasta \
//     node scripts/validar-formas-pagamento-14nov.browser.mjs
import { createRequire } from 'module';
import { execFileSync } from 'child_process';
const require = createRequire(process.env.NODE_PATH + '/');
const puppeteer = require('puppeteer-core');

const BASE = process.env.BASE || 'http://10.213.79.4';
const MYSQL = process.env.MYSQL || 'fp-mysql';
const PRINTS = process.env.PRINTS || '';
const ROTA = '/app/configuracoes/formas-pagamento';
let ok = 0, fail = 0;
const check = (nome, cond, obtido = '') => { cond ? ok++ : fail++; console.log((cond ? '✅ ' : '❌ ') + nome + (cond ? '' : `  (obtido: ${String(obtido).slice(0, 300)})`)); };
const nav = (page, acao) => Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), acao()]);
const print = async (page, nome) => { if (PRINTS) await page.screenshot({ path: `${PRINTS}/${nome}.png`, fullPage: true }); };
const sql = q => execFileSync('docker', ['exec', '-i', MYSQL, 'mysql', '--default-character-set=utf8mb4', '-uerp', '-perp', 'erp_teste', '-N', '-B'], { input: q }).toString().trim();
const sleep = ms => new Promise(r => setTimeout(r, ms));

sql('DELETE FROM formas_pagamento; UPDATE empresas SET pdv_credishop=0;');

const browser = await puppeteer.launch({ executablePath: process.env.CHROME, args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 1366, height: 1400 }); // alto: a área Desligadas fica abaixo da prévia
const erros = [];
page.on('pageerror', e => erros.push(e.message));
page.on('console', m => { if (m.type() === 'error' && !/favicon|ERR_|Failed to load resource/.test(m.text())) erros.push(m.text()); });

await page.goto(BASE + '/login', { waitUntil: 'networkidle2' });
await page.type('input[name=email]', 'dono@demo.com');
await page.type('input[name=password]', 'dono123');
await nav(page, () => page.click('button[type=submit]'));
if (page.url().includes('selecionar-unidade')) {
    await nav(page, () => page.evaluate(() => { const f = document.querySelector('form[action*="selecionar-unidade"]') || document.querySelector('main form, form'); (f.querySelector('button[type=submit], button')).click(); }));
}

const nomes = sel => page.$$eval(sel + ' .fp-btn .fp-nome', els => els.map(e => e.textContent.trim()));
async function arrastar(origem, destino, lado = 'antes') {
    const a = await (await page.$(origem)).boundingBox();
    const b = await (await page.$(destino)).boundingBox();
    await page.mouse.move(a.x + a.width / 2, a.y + a.height / 2);
    await page.mouse.down();
    await page.mouse.move(a.x + a.width / 2 + 6, a.y + a.height / 2 + 6, { steps: 4 });
    // 'antes' = metade esquerda/alto do alvo; 'centro' = meio (área vazia)
    const alvoX = lado === 'antes' ? b.x + 6 : b.x + b.width / 2;
    const alvoY = lado === 'antes' ? b.y + 6 : b.y + b.height / 2;
    await page.mouse.move(alvoX, alvoY, { steps: 25 });
    await sleep(250);
    await page.mouse.up();
    await sleep(300);
}

// ---- entrada pela tela de Configurações
await page.goto(BASE + '/app/configuracoes', { waitUntil: 'networkidle2' });
check('1 Configurações da Loja tem o botão "Formas de pagamento"', !!(await page.$(`a[href$="${ROTA}"]`)));
await nav(page, () => page.click(`a[href$="${ROTA}"]`));
check('2 a tela abre com a prévia do PDV', !!(await page.$('#fpAtivas')));
check('3 prévia mostra os 8 de fábrica, na ordem', JSON.stringify(await nomes('#fpAtivas')) === JSON.stringify(['Dinheiro', 'Crédito', 'Débito', 'PIX', 'Boleto', 'Crediario', 'Transf.', 'Vale']), await nomes('#fpAtivas'));
check('4 "Desligadas" vazia com a dica de arrastar', await page.$eval('#fpDesligadas', e => e.children.length === 0 && getComputedStyle(e, '::before').content.includes('Arraste')));
check('5 SortableJS carregou', await page.evaluate(() => !!window.Sortable));
await print(page, '01-padrao');

// ---- arrastar PIX para o começo
await arrastar('#fpAtivas .fp-btn[data-chave="pix"]', '#fpAtivas .fp-btn[data-chave="dinheiro"]');
const ordem1 = await nomes('#fpAtivas');
check('6 arrastar PIX para antes de Dinheiro', ordem1[0] === 'PIX' && ordem1[1] === 'Dinheiro', ordem1);
check('7 aparece "Alterações não salvas"', await page.$eval('#fpSujo', e => !e.hidden));

// ---- arrastar Boleto para Desligadas
await arrastar('#fpAtivas .fp-btn[data-chave="boleto"]', '#fpDesligadas', 'centro');
check('8 arrastar Boleto para Desligadas', (await nomes('#fpDesligadas')).includes('Boleto') && !(await nomes('#fpAtivas')).includes('Boleto'), [await nomes('#fpAtivas'), await nomes('#fpDesligadas')]);

// ---- editar o Crédito
await page.click('#fpAtivas .fp-btn[data-chave="cartao_credito"]');
check('9 clicar abre o editor com o nome', (await page.$eval('#fpNome', e => e.value)) === 'Crédito');
check('10 forma do sistema: "Funciona como" travado + aviso', await page.$eval('#fpTipo', e => e.disabled) && await page.$eval('#fpSistemaAviso', e => !e.hidden));
check('11 forma do sistema não tem Excluir', await page.$eval('#fpExcluir', e => e.hidden));
await page.$eval('#fpNome', e => e.select());
await page.type('#fpNome', 'Cartão');
check('12 digitar o nome muda o botão na hora', (await page.$eval('#fpAtivas .fp-btn[data-chave="cartao_credito"] .fp-nome', e => e.textContent)) === 'Cartão');
await page.click('#fpCores button[data-cor="#ec4899"]');
check('13 trocar a cor pinta o ícone na prévia', (await page.$eval('#fpAtivas .fp-btn[data-chave="cartao_credito"] i', e => e.style.color)).includes('236, 72, 153'));
await page.click('label[for="fpLargura2"]');
check('14 "Linha inteira" alarga o botão', await page.$eval('#fpAtivas .fp-btn[data-chave="cartao_credito"]', e => e.classList.contains('largo')));
await page.click('#fpIcones button[data-icone="bi-star"]');
check('15 trocar o ícone', await page.$eval('#fpAtivas .fp-btn[data-chave="cartao_credito"] i', e => e.classList.contains('bi-star')));
await page.click('#fpDesce');
const ordem2 = await nomes('#fpAtivas');
check('16 botão "Depois" move uma casa (teclado/sem arrastar)', ordem2.indexOf('Cartão') === ordem2.indexOf('Débito') + 1, ordem2);

// ---- nova forma
await page.click('#fpNova');
check('17 "Nova forma" cria botão com etiqueta NOVA e abre o editor', (await page.$$eval('#fpAtivas .fp-tag', e => e.length)) === 1 && (await page.$eval('#fpNome', e => e.value)) === 'Nova forma');
check('18 nova: "Funciona como" livre e sem a opção Vale', !(await page.$eval('#fpTipo', e => e.disabled)) && await page.$eval('#fpTipo option[value="vale"]', o => o.hidden));
await page.$eval('#fpNome', e => e.select());
await page.type('#fpNome', 'Cartão Elo');
await page.select('#fpTipo', 'cartao_credito');
check('19 nova: tem Excluir', await page.$eval('#fpExcluir', e => !e.hidden));
await print(page, '02-editando');

// ---- desligar pelo interruptor e religar
await page.click('#fpAtivas .fp-btn[data-chave="transferencia"]');
await page.click('#fpAtivo');
check('20 desligar no interruptor manda para Desligadas', (await nomes('#fpDesligadas')).includes('Transf.'));
await page.click('#fpAtivo');
check('21 religar volta para as ligadas', (await nomes('#fpAtivas')).includes('Transf.'));

// ---- salvar
const esperado = await nomes('#fpAtivas');
await nav(page, () => page.click('#fpSalvar'));
check('22 salvar mostra a mensagem de sucesso', (await page.$eval('body', b => b.textContent)).includes('Formas de pagamento salvas'));
check('23 depois de salvar a ordem é a mesma', JSON.stringify(await nomes('#fpAtivas')) === JSON.stringify(esperado), [esperado, await nomes('#fpAtivas')]);
check('24 Boleto continua em Desligadas', JSON.stringify(await nomes('#fpDesligadas')) === '["Boleto"]', await nomes('#fpDesligadas'));
check('25 banco gravou 9 formas', sql('SELECT COUNT(*) FROM formas_pagamento WHERE empresa_id=1') === '9');
check('26 sem aviso de "não salvas" depois de salvar', await page.$eval('#fpSujo', e => e.hidden));
await print(page, '03-salvo');

// ---- PDV (abre o caixa nesta sessão do navegador; o .py deixou um caixa aberto do dono, que é reaproveitado)
await page.goto(BASE + '/app/caixa/abrir', { waitUntil: 'networkidle2' });
await page.evaluate(async () => {
    const t = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name=_token]')?.value;
    await fetch('/app/caixa/abrir', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: '_token=' + encodeURIComponent(t) + '&valor_abertura=100' });
});
await page.setViewport({ width: 1366, height: 900 });
await page.goto(BASE + '/app/pdv', { waitUntil: 'networkidle2' });
const pdvNomes = await page.$$eval('.payment-grid .btn-pay', bs => bs.map(b => b.dataset.nome));
check('27 PDV mostra os botões na ordem salva, sem Boleto', JSON.stringify(pdvNomes) === JSON.stringify(esperado), pdvNomes);
check('28 "Cartão" ocupa a linha inteira no PDV', await page.$eval('.btn-pay[data-chave="cartao_credito"]', b => getComputedStyle(b).gridColumnStart === 'span 2'));
await page.type('#searchInput', 'PROD002');
await page.keyboard.press('Enter');
await sleep(1500);
const temItem = await page.evaluate(() => (typeof PDV !== 'undefined' ? PDV.itens.length : 0));
if (!temItem) {
    // busca por texto abre lista: clica o primeiro resultado
    await page.$eval('#searchInput', e => { e.value = ''; });
    await page.type('#searchInput', 'Mouse');
    await sleep(1500);
    const r = await page.$('.search-result-item, .search-results [data-id], #searchResults > *');
    if (r) { await r.click(); await sleep(800); }
}
check('29 produto entra no carrinho do PDV', (await page.evaluate(() => (typeof PDV !== 'undefined' ? PDV.itens.length : 0))) > 0);
const chElo = sql("SELECT chave FROM formas_pagamento WHERE nome='Cartão Elo'");
await page.click(`.btn-pay[data-chave="${chElo}"]`);
await sleep(700);
check('30 clicar "Cartão Elo" abre o pagamento com o nome dele', (await page.$eval('#modalPagamentoTitle', e => e.textContent)) === 'Pagamento - Cartão Elo', await page.$eval('#modalPagamentoTitle', e => e.textContent));
check('31 "Cartão Elo" (crédito) mostra as parcelas', await page.evaluate(() => { const s = document.getElementById('parcelasSelect'); return !!s && s.offsetParent !== null; }));
await print(page, '04-pdv-modal');
const antes = sql('SELECT COUNT(*) FROM vendas');
const resp = page.waitForResponse(r => r.url().endsWith('/app/pdv/venda'), { timeout: 15000 }).catch(() => null);
await page.click('#btnConfirmarPagamento');
await sleep(500);
// se perguntar o documento, escolhe recibo
const doc = await page.$('.btn-doc-escolha');
if (doc) { await page.evaluate(() => { const b = [...document.querySelectorAll('.btn-doc-escolha')].find(x => /recibo/i.test(x.textContent)) || document.querySelector('.btn-doc-escolha'); b.click(); }); }
const r = await resp;
await sleep(1500);
check('32 venda finalizada pelo navegador', r && r.status() === 200 && sql('SELECT COUNT(*) FROM vendas') !== antes, r && r.status());
const det = sql('SELECT pagamento_detalhes FROM vendas ORDER BY id DESC LIMIT 1');
check('33 venda gravada com forma_nome "Cartão Elo" e forma cartao_credito', det.includes('Cartão Elo') && det.includes('"cartao_credito"'), det);

// ---- celular
await page.setViewport({ width: 390, height: 844 });
await page.goto(BASE + ROTA, { waitUntil: 'networkidle2' });
check('34 390 px: sem rolagem lateral', await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), await page.evaluate(() => document.documentElement.scrollWidth));
await print(page, '05-celular');

check('35 nenhum erro de JavaScript', erros.length === 0, erros.join(' | '));
await browser.close();
console.log(`\n${ok} ✅ / ${fail} ❌`);
process.exit(fail ? 1 : 0);
