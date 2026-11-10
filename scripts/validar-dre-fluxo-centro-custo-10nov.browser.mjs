// Validação ✅/❌ no NAVEGADOR — DRE (pessoal + competência), fluxo programado e despesas por centro de custo
// (10/11/2026), Fase 3. Usa o cenário de março/2029 que o script .py deixa no banco: rodar DEPOIS dele.
//   BASE=http://172.17.0.x:8299 CHROME=/caminho/chrome NODE_PATH=/home/ubuntu/caixa-v2/node_modules \
//     node scripts/validar-dre-fluxo-centro-custo-10nov.browser.mjs
import { createRequire } from 'module';
const require = createRequire(process.env.NODE_PATH + '/');
const puppeteer = require('puppeteer-core');

const BASE = process.env.BASE || 'http://127.0.0.1:8299';
let ok = 0, fail = 0;
const check = (nome, cond, obtido = '') => { cond ? ok++ : fail++; console.log((cond ? '✅ ' : '❌ ') + nome + (cond ? '' : `  (obtido: ${String(obtido).slice(0, 300)})`)); };
const nav = (page, acao) => Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), acao()]);
const limpo = t => t.replace(/\s+/g, ' ').trim();
const linha = (page, tabela, chave) => page.$eval(`#${tabela} tr[data-chave="${chave}"]`, tr => [...tr.cells].map(td => td.textContent.replace(/\s+/g, ' ').trim())).catch(() => []);
const setData = (page, sel, v) => page.$eval(sel, (el, v) => { el.value = v; }, v);

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

// ---- DRE: filtro pelo formulário
await page.goto(BASE + '/app/dre', { waitUntil: 'networkidle2' });
await setData(page, '#data_inicio', '2029-03-01');
await setData(page, '#data_fim', '2029-03-31');
await nav(page, () => page.evaluate(() => document.querySelector('#data_inicio').form.submit()));
let pessoal = await linha(page, 'tabelaDre', 'pessoal');
check('D1 DRE de março/2029 por caixa: Despesas com Pessoal (R$ 1.510,00)', pessoal[0] === 'Despesas com Pessoal' && pessoal[1] === '(R$ 1.510,00)', pessoal.join(' | '));
const recuo = await page.evaluate(() => {
    const px = ch => parseFloat(getComputedStyle(document.querySelector(`#tabelaDre tr[data-chave="${ch}"] td`)).paddingLeft);
    const grupo = [...document.querySelectorAll('#tabelaDre tr[data-chave^="pessoal.g"]')][0].dataset.chave;
    const conta = [...document.querySelectorAll('#tabelaDre tr[data-chave^="pessoal.c"]')][0].dataset.chave;
    return [px('despesas'), px('pessoal'), px(grupo), px(conta)];
});
check('D2 a hierarquia aparece em degraus (total → pessoal → grupo → conta)', recuo[0] < recuo[1] && recuo[1] < recuo[2] && recuo[2] < recuo[3], recuo.join(' < '));
await page.select('#regime', 'competencia');
await nav(page, () => page.evaluate(() => document.querySelector('#regime').form.submit()));
pessoal = await linha(page, 'tabelaDre', 'pessoal');
const aviso = limpo(await page.$eval('#dreRegimeAviso', e => e.textContent));
check('D3 trocar para competência no seletor: pessoal (R$ 4.210,00) e o aviso muda', pessoal[1] === '(R$ 4.210,00)' && aviso.startsWith('Por competência') && page.url().includes('regime=competencia'), pessoal.join(' | '));
check('D4 o seletor mantém a escolha e as datas depois de filtrar', await page.$eval('#regime', e => e.value) === 'competencia' && await page.$eval('#data_inicio', e => e.value) === '2029-03-01');
const cards = limpo(await page.$eval('#dreCards', e => e.textContent));
check('D5 cartões: resultado 11.240,00, receita 24.000,00 e pessoal 4.210,00 (17,5% da receita)', cards.includes('11.240,00') && cards.includes('24.000,00') && cards.includes('4.210,00') && cards.includes('17,5% da receita'), cards);
check('D6 4 cartões cabem sem rolagem lateral', await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));
await nav(page, () => page.click('a[href*="/dre/por-unidade"]'));
const pu = await linha(page, 'tabelaDreUnidades', 'pessoal');
check('D7 "Por Unidade" abre já em competência com as duas lojas', page.url().includes('regime=competencia') && pu.length === 3 && pu[1] === '(R$ 4.210,00)' && pu[2] === 'R$ 0,00', pu.join(' | '));
await nav(page, () => page.click('a.btn-erp-outline[href*="/app/dre?"]'));
check('D8 "Voltar" devolve ao DRE no mesmo período e regime', page.url().includes('data_inicio=2029-03-01') && page.url().includes('regime=competencia') && (await linha(page, 'tabelaDre', 'pessoal'))[1] === '(R$ 4.210,00)', page.url());

// ---- Fluxo de caixa
await page.goto(BASE + '/app/financeiro/fluxo-caixa?data_inicio=2029-03-01&data_fim=2029-04-30', { waitUntil: 'networkidle2' });
let prog = await page.$$eval('#tabelaProgramado tbody tr', trs => trs.map(tr => tr.textContent.replace(/\s+/g, ' ').trim()));
check('F1 fluxo mostra o quadro Programado com a guia do INSS e o total', prog.length === 2 && prog[0].includes('20/04/2029') && prog[0].includes('INSS') && prog[1].includes('700,00'), prog.join(' | '));
check('F2 o gráfico do fluxo foi desenhado', await page.evaluate(() => { const c = document.querySelector('canvas'); return !!c && c.width > 0 && c.height > 0; }));
const admId = await page.$eval('#campo-centro_custo_id', el => [...el.options].find(o => o.textContent.trim() === 'Administrativo').value);
await page.select('#campo-centro_custo_id', admId);
await nav(page, () => page.evaluate(() => document.querySelector('#campo-centro_custo_id').form.submit()));
const corpo = limpo(await page.$eval('main, .content, body', e => e.textContent));
check('F3 filtrar pelo centro Administrativo: aviso de filtro, saídas 3.000,00 e nada programado', !!(await page.$('#fluxoAvisoFiltro')) && corpo.includes('3.000,00') && corpo.includes('Nada programado'), '');
check('F4 o filtro fica selecionado e o período é mantido', await page.$eval('#campo-centro_custo_id', e => e.value) === admId && await page.$eval('#campo-data_fim', e => e.value) === '2029-04-30');
await nav(page, () => page.evaluate(() => [...document.querySelectorAll('a')].find(a => a.textContent.trim() === 'Limpar').click()));
check('F5 "Limpar" tira o filtro', !(await page.$('#fluxoAvisoFiltro')) && await page.$eval('#campo-centro_custo_id', e => e.value) === '');
await page.goto(BASE + '/app/financeiro/fluxo-caixa?data_inicio=2029-03-01&data_fim=2029-04-30', { waitUntil: 'networkidle2' });
await nav(page, () => page.click('#tabelaProgramado tbody tr:first-child a'));
check('F6 clicar na conta programada abre a conta no Contas a Pagar', /contas-pagar\/\d+$/.test(page.url()) && (await page.content()).includes('F3 Guia INSS') && (await page.content()).includes('Dar baixa'), page.url());

// ---- Despesas por centro de custo
await page.goto(BASE + '/app/financeiro/contas-pagar', { waitUntil: 'networkidle2' });
await nav(page, () => page.evaluate(() => document.querySelector('#menuFinanceiro a[href$="/financeiro/despesas-centro-custo"]').click()));
check('C1 menu Financeiro → Despesas por Centro de Custo abre e marca o item', page.url().endsWith('/financeiro/despesas-centro-custo') && await page.$eval('#menuFinanceiro a[href$="/financeiro/despesas-centro-custo"]', a => a.classList.contains('active')));
await setData(page, '#data_inicio', '2029-03-01');
await setData(page, '#data_fim', '2029-03-31');
await page.select('#regime', 'competencia');
await nav(page, () => page.evaluate(() => document.querySelector('#regime').form.submit()));
const centro = nome => page.$eval(`#tabelaCentros tr[data-centro="${nome}"]`, tr => [...tr.cells].map(td => td.textContent.replace(/\s+/g, ' ').trim())).catch(() => []);
const adm = await centro('Administrativo');
const total = await page.$eval('#tabelaCentros tr.table-dark', tr => [...tr.cells].map(td => td.textContent.replace(/\s+/g, ' ').trim()));
check('C2 por competência: Administrativo com pessoal R$ 3.000,00 e total geral R$ 12.760,00', adm[1] === 'R$ 3.000,00' && total[4] === 'R$ 12.760,00', adm.join(' | ') + ' // ' + total.join(' | '));
const cardsC = limpo(await page.$eval('#cardsCentro', e => e.textContent));
check('C3 cartões do relatório: total 12.760,00, pessoal 4.210,00, já pago 12.060,00', cardsC.includes('12.760,00') && cardsC.includes('4.210,00') && cardsC.includes('12.060,00'), cardsC);
await page.select('#regime', 'pagamento');
await nav(page, () => page.evaluate(() => document.querySelector('#regime').form.submit()));
const total2 = await page.$eval('#tabelaCentros tr.table-dark', tr => [...tr.cells].map(td => td.textContent.replace(/\s+/g, ' ').trim()));
check('C4 voltar para "o que foi pago": total R$ 10.060,00', total2[4] === 'R$ 10.060,00', total2.join(' | '));
await nav(page, () => page.click('a[href*="/app/dre?"]'));
check('C5 o botão DRE leva o mesmo período', page.url().includes('data_inicio=2029-03-01') && (await linha(page, 'tabelaDre', 'pessoal'))[1] === '(R$ 1.510,00)', page.url());

// ---- celular
await page.setViewport({ width: 390, height: 844 });
for (const [nome, url] of [['DRE', '/app/dre?data_inicio=2029-03-01&data_fim=2029-03-31'], ['fluxo', '/app/financeiro/fluxo-caixa?data_inicio=2029-03-01&data_fim=2029-04-30'], ['centro de custo', '/app/financeiro/despesas-centro-custo?data_inicio=2029-03-01&data_fim=2029-03-31']]) {
    await page.goto(BASE + url, { waitUntil: 'networkidle2' });
    const sobra = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    check(`M celular (390 px): ${nome} sem rolagem lateral da página`, sobra <= 1, sobra + ' px');
}

check('Z1 nenhum erro de JavaScript nas telas', erros.length === 0, erros.join(' | '));
console.log(`\n${ok} ✅ / ${fail} ❌`);
await browser.close();
process.exit(fail ? 1 : 0);
