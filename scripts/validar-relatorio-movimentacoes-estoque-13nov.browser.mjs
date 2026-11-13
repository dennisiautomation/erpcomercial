// Validação ✅/❌ no NAVEGADOR — Relatórios → Entradas e saídas do estoque (13/11/2026).
// Usa o cenário de março/2029 que o script .py deixa no banco: rodar DEPOIS dele.
//   BASE=http://10.213.77.4 CHROME=/caminho/chrome NODE_PATH=/home/ubuntu/caixa-v2/node_modules PRINTS=/pasta \
//     node scripts/validar-relatorio-movimentacoes-estoque-13nov.browser.mjs
import { createRequire } from 'module';
const require = createRequire(process.env.NODE_PATH + '/');
const puppeteer = require('puppeteer-core');
const fs = require('fs');

const BASE = process.env.BASE || 'http://10.213.77.4';
const PRINTS = process.env.PRINTS || '';
const ROTA = '/app/relatorios/movimentacoes-estoque';
let ok = 0, fail = 0;
const check = (nome, cond, obtido = '') => { cond ? ok++ : fail++; console.log((cond ? '✅ ' : '❌ ') + nome + (cond ? '' : `  (obtido: ${String(obtido).slice(0, 300)})`)); };
const nav = (page, acao) => Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), acao()]);
const txt = (page, sel) => page.$eval(sel, el => el.textContent.replace(/\s+/g, ' ').trim()).catch(() => null);
const print = async (page, nome) => { if (PRINTS) await page.screenshot({ path: `${PRINTS}/${nome}.png`, fullPage: true }); };

const browser = await puppeteer.launch({ executablePath: process.env.CHROME, args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 1366, height: 900 });
const erros = [];
page.on('pageerror', e => erros.push(e.message));
page.on('console', m => { if (m.type() === 'error' && !/favicon|ERR_|Failed to load resource/.test(m.text())) erros.push(m.text()); });

await page.goto(BASE + '/login', { waitUntil: 'networkidle2' });
await page.type('input[name=email]', 'dono@demo.com');
await page.type('input[name=password]', 'dono123');
await nav(page, () => page.click('button[type=submit]'));
if (page.url().includes('selecionar-unidade')) {
    await nav(page, () => page.evaluate(() => { const f = document.querySelector('form[action*="selecionar-unidade"]') || document.querySelector('main form, form'); const b = f.querySelector('button[type=submit], button'); b.click(); }));
}

// ---- pelo menu
await page.goto(BASE + '/app/dashboard', { waitUntil: 'networkidle2' });
const link = await page.$('a[href$="/app/relatorios/movimentacoes-estoque"]');
check('1 link "Entradas e saídas" existe no menu Relatórios', !!link);
await page.goto(BASE + ROTA, { waitUntil: 'networkidle2' });
check('2 a tela abre com o título', (await txt(page, 'h4')) === 'Entradas e saídas do estoque', await txt(page, 'h4'));
check('3 item do menu fica marcado como ativo', await page.$eval('a[href$="/app/relatorios/movimentacoes-estoque"]', a => a.classList.contains('active')));

// ---- filtro pelo formulário: março/2029, as duas lojas
const setData = (sel, v) => page.$eval(sel, (el, v) => { el.value = v; }, v);
await setData('#campo-data_inicio', '2029-03-01');
await setData('#campo-data_fim', '2029-03-31');
await page.click('#filtro-lojas .js-todas');
check('4 "Marcar todas" marca as 2 lojas e o contador avisa', (await txt(page, '#filtro-lojas .js-contador')) === '(todas marcadas)', await txt(page, '#filtro-lojas .js-contador'));
await nav(page, () => page.click('#btn-filtrar'));
check('5 Filtrar: entrou 40 · saiu 8,500 · 10 movimentações', (await txt(page, '#kpi-entrou')) === '40' && (await txt(page, '#kpi-saiu')) === '8,500' && (await txt(page, '#kpi-movimentos')) === '10',
    [await txt(page, '#kpi-entrou'), await txt(page, '#kpi-saiu'), await txt(page, '#kpi-movimentos')]);
check('6 as datas ficam no formulário depois de filtrar', (await page.$eval('#campo-data_inicio', e => e.value)) === '2029-03-01' && (await page.$eval('#campo-data_fim', e => e.value)) === '2029-03-31');
await print(page, '1-resumo');

// ---- só entradas / só saídas
await page.click('label[for="sentido-entrada"]');
await nav(page, () => page.click('#btn-filtrar'));
check('7 "Só entradas": saiu 0, 5 movimentações, botão segue marcado', (await txt(page, '#kpi-saiu')) === '0' && (await txt(page, '#kpi-movimentos')) === '5' && await page.$eval('#sentido-entrada', e => e.checked));
await page.click('label[for="sentido-saida"]');
await nav(page, () => page.click('#btn-filtrar'));
check('8 "Só saídas": entrou 0, saiu 8,500', (await txt(page, '#kpi-entrou')) === '0' && (await txt(page, '#kpi-saiu')) === '8,500');
await page.click('label[for="sentido-tudo"]');

// ---- tipos em pílulas
await page.click('label[for="tipo-ajuste"]');
check('9 marcar um tipo atualiza o contador (1 de 7)', (await txt(page, '#filtro-tipos .js-contador')) === '(1 de 7)', await txt(page, '#filtro-tipos .js-contador'));
await nav(page, () => page.click('#btn-filtrar'));
check('10 tipo Ajuste: entrou 5, saiu 2, pílula segue marcada', (await txt(page, '#kpi-entrou')) === '5' && (await txt(page, '#kpi-saiu')) === '2' && await page.$eval('#tipo-ajuste', e => e.checked));
await page.click('#filtro-tipos .js-limpar');
check('11 "Limpar" dos tipos desmarca e avisa que vale todos', (await txt(page, '#filtro-tipos .js-contador')).startsWith('Nenhum marcado'));
await nav(page, () => page.click('#btn-filtrar'));

// ---- aba movimento a movimento preserva os filtros
await nav(page, () => page.evaluate(() => [...document.querySelectorAll('.nav-tabs a')].find(a => a.textContent.includes('Movimento a movimento')).click()));
const linhas = await page.$$eval('#tabela-itens tbody tr', trs => trs.length);
check('12 aba "Movimento a movimento" com as 10 linhas e os filtros mantidos', linhas === 10 && page.url().includes('2029-03-01') && page.url().includes('ver=itens'), [linhas, page.url()]);
await print(page, '2-movimentos');
check('13 a tabela larga não empurra a página para o lado (1366 px)', await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));

// ---- produto do resumo leva às movimentações dele
await nav(page, () => page.evaluate(() => [...document.querySelectorAll('.nav-tabs a')].find(a => a.textContent.includes('Resumo por produto')).click()));
await nav(page, () => page.click('#tabela-resumo tbody tr:first-child a'));
check('14 clicar no produto do resumo abre só as movimentações dele (5)', (await page.$$eval('#tabela-itens tbody tr', t => t.length)) === 5 && (await page.$eval('#produtoFiltroBusca', e => e.value)).includes('Notebook'),
    await page.$eval('#produtoFiltroBusca', e => e.value));
// apagar o texto do produto limpa o filtro
await page.focus('#produtoFiltroBusca');
await page.keyboard.down('Control'); await page.keyboard.press('KeyA'); await page.keyboard.up('Control');
await page.keyboard.press('Backspace');
await new Promise(r => setTimeout(r, 300));
check('15 apagar o nome do produto limpa o filtro escondido', (await page.$eval('#produtoFiltroId', e => e.value)) === '', [await page.$eval('#produtoFiltroBusca', e => e.value), await page.$eval('#produtoFiltroId', e => e.value)]);
await nav(page, () => page.click('#btn-filtrar'));
check('16 sem o produto, voltam as 10', (await page.$$eval('#tabela-itens tbody tr', t => t.length)) === 10);

// ---- período rápido
await page.click('.js-periodo[data-periodo="mes-passado"]');
const [di, df] = [await page.$eval('#campo-data_inicio', e => e.value), await page.$eval('#campo-data_fim', e => e.value)];
const hoje = new Date(); const pIni = new Date(hoje.getFullYear(), hoje.getMonth() - 1, 1); const pFim = new Date(hoje.getFullYear(), hoje.getMonth(), 0);
const iso = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
check('17 "Mês passado" preenche do dia 1 ao último dia', di === iso(pIni) && df === iso(pFim), [di, df]);

// ---- PDF: a página de impressão, no papel
await page.evaluateOnNewDocument(() => { window.print = () => { window.__imprimiu = (window.__imprimiu || 0) + 1; }; });
await page.goto(BASE + ROTA + '?data_inicio=2029-05-01&data_fim=2029-05-31&ver=itens&imprimir=1', { waitUntil: 'networkidle2' });
await new Promise(r => setTimeout(r, 900));
check('18 a página do PDF chama a impressão sozinha, uma vez', (await page.evaluate(() => window.__imprimiu)) === 1, await page.evaluate(() => window.__imprimiu));
check('19 a página do PDF traz as 150 linhas', (await page.$$eval('#tabela-itens tbody tr', t => t.length)) === 150);
await page.emulateMediaType('print');
const papel = await page.evaluate(() => ({
    menu: getComputedStyle(document.querySelector('.sidebar')).display,
    filtros: getComputedStyle(document.querySelector('#form-mov-estoque').closest('.card')).display,
    cabecalho: getComputedStyle(document.querySelector('.rel-print-header')).display,
    botoes: getComputedStyle(document.querySelector('#btn-excel')).display === 'none' || getComputedStyle(document.querySelector('#btn-excel').parentElement.parentElement).display === 'none',
}));
check('20 no papel: sem menu, sem filtros, sem botões, com o cabeçalho da empresa', papel.menu === 'none' && papel.filtros === 'none' && papel.cabecalho === 'block' && papel.botoes, JSON.stringify(papel));
const pdf = await page.pdf({ preferCSSPageSize: true, printBackground: true });
if (PRINTS) fs.writeFileSync(`${PRINTS}/3-movimentos.pdf`, pdf);
const paginas = (Buffer.from(pdf).toString('latin1').match(/\/Type\s*\/Page[^s]/g) || []).length;
check('21 o PDF é gerado e tem mais de uma página (150 linhas)', pdf.length > 5000 && paginas >= 2, [pdf.length, paginas]);
await page.emulateMediaType('screen');

// ---- celular
await page.setViewport({ width: 390, height: 844 });
await page.goto(BASE + ROTA + '?data_inicio=2029-03-01&data_fim=2029-03-31&lojas[]=1&lojas[]=2', { waitUntil: 'networkidle2' });
check('22 em 390 px a página não rola para o lado', await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), await page.evaluate(() => document.documentElement.scrollWidth));
await print(page, '4-celular');

check('23 nenhum erro de JavaScript', erros.length === 0, erros.join(' | '));
console.log(`\n${ok} ✅ / ${fail} ❌`);
await browser.close();
process.exit(fail ? 1 : 0);
