// Validação ✅/❌ no NAVEGADOR — Folha de Pagamento (10/11/2026), Fase 2.
// Botões e telas: preencher ficha (resumo ao vivo), cadastrar guia, ver período, lançar com confirmação,
// abrir as contas do mês no Contas a Pagar, excluir guia. Usa meses de 2028 para não bater no script .py.
//   BASE=http://172.17.0.x:8299 CHROME=/caminho/chrome NODE_PATH=/home/ubuntu/caixa-v2/node_modules \
//     node scripts/validar-folha-pagamento-10nov.browser.mjs
// Rodar DEPOIS do validar-folha-pagamento-10nov.py (usa o plano de contas e as fichas que ele deixa).
import { createRequire } from 'module';
const require = createRequire(process.env.NODE_PATH + '/');
const puppeteer = require('puppeteer-core');

const BASE = process.env.BASE || 'http://127.0.0.1:8299';
const F = '/app/financeiro/folha';
let ok = 0, fail = 0;
const check = (nome, cond, obtido = '') => { cond ? ok++ : fail++; console.log((cond ? '✅ ' : '❌ ') + nome + (cond ? '' : `  (obtido: ${String(obtido).slice(0, 300)})`)); };
const nav = (page, acao) => Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), acao()]);
const confirmarModal = async page => {
    await page.waitForSelector('.modal.show', { timeout: 5000 });
    const btn = await page.evaluateHandle(() => [...document.querySelectorAll('.modal.show .modal-footer button')].find(b => !/cancel/i.test(b.textContent)));
    await new Promise(r => setTimeout(r, 300));
    return nav(page, () => btn.asElement().click());
};

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

// ---- menu
await page.goto(BASE + '/app/financeiro/contas-pagar', { waitUntil: 'networkidle2' });
const link = await page.$('#menuFinanceiro a[href$="/financeiro/folha"]');
check('M1 menu Financeiro tem "Folha de Pagamento"', !!link);
await nav(page, () => page.evaluate(() => document.querySelector('#menuFinanceiro a[href$="/financeiro/folha"]').click()));
check('M2 o link abre a folha e fica marcado no menu', page.url().endsWith(F) && await page.$eval('#menuFinanceiro a[href$="/financeiro/folha"]', a => a.classList.contains('active')));

// ---- ficha da Maria (gerente): resumo ao vivo
const fichaUrl = await page.evaluate(() => [...document.querySelectorAll('#tabelaFichas tr')].find(tr => tr.textContent.includes('Maria Santos')).querySelector('a').href);
await page.goto(fichaUrl, { waitUntil: 'networkidle2' });
const set = (sel, v) => page.$eval(sel, (el, v) => { el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })); }, v);
await set('#salario', '3000');
await set('#vale_valor', '4000');
check('F1 vale maior que o salário avisa na hora', (await page.$eval('#resumoFicha', e => e.textContent)).includes('maior que o salário'));
await set('#vale_valor', '1200');
const resumo = await page.$eval('#resumoFicha', e => e.textContent);
check('F2 resumo ao vivo: vale 1.200,00 no dia 20 e salário 1.800,00 no dia 5 do mês seguinte', resumo.includes('1.200,00 no dia 20') && resumo.includes('1.800,00 no dia 5 do mês seguinte'), resumo);
await page.click('#paga_mes_seguinte');
check('F3 desligar "mês seguinte" muda o resumo', !(await page.$eval('#resumoFicha', e => e.textContent)).includes('mês seguinte'));
await page.click('#paga_mes_seguinte');
await page.type('#cargo', 'Gerente');
await nav(page, () => page.click('#formFicha button[type=submit]'));
let html = await page.content();
check('F4 salvar a ficha volta para a folha com o aviso e a linha preenchida', page.url().includes(F) && html.includes('Ficha de Maria Santos salva') && html.includes('3.000,00'));

// ---- nova guia
await nav(page, () => page.click('a[href$="/folha/itens/novo"]'));
await page.select('#tipo', 'encargo');
await page.type('#nome', 'Guia FGTS navegador');
await page.type('#favorecido_nome', 'Caixa (FGTS)');
await set('#valor', '240');
await set('#dia_vencimento', '7');
await page.select('#plano_conta_id', await page.$eval('#plano_conta_id', el => [...el.options].find(o => o.textContent.includes('3.2.02')).value));
await nav(page, () => page.click('#formItem button[type=submit]'));
html = await page.content();
check('G1 guia cadastrada aparece na lista com valor e plano', html.includes('Item da folha cadastrado') && html.includes('Guia FGTS navegador') && html.includes('3.2.02'));

// ---- ver período (2028) e lançar
await page.$eval('#inicio', el => { el.value = '2028-01'; });
await page.select('#meses', '3');
await nav(page, () => page.click('#formPeriodo button[type=submit]'));
const linhasResumo = await page.$$eval('#tabelaResumo tbody tr', trs => trs.map(tr => tr.textContent.replace(/\s+/g, ' ').trim()));
check('L1 "Ver período" mostra jan, fev e mar/2028 como Não lançado', linhasResumo.length === 3 && linhasResumo[0].startsWith('jan/2028') && linhasResumo.every(l => l.includes('Não lançado')), linhasResumo.join(' | '));
const previa = await page.$eval('#tabelaPrevia', t => t.textContent.replace(/\s+/g, ' '));
check('L2 prévia de jan/2028 traz o vale e o salário da Maria e a guia nova', previa.includes('Vale 01/2028 — Maria Santos') && previa.includes('Salário 01/2028 — Maria Santos') && previa.includes('Guia FGTS navegador 01/2028') && previa.includes('07/02/2028'), previa.slice(0, 300));
await page.click('#formLancar button[type=submit]');
await page.waitForSelector('.modal.show', { timeout: 5000 }).catch(() => {});
const msgModal = await page.$eval('.modal.show .modal-body', e => e.textContent).catch(() => '');
check('L3 lançar pede confirmação dizendo o período', msgModal.includes('jan/2028') && msgModal.includes('mar/2028') && page.url().includes(F), msgModal);
await confirmarModal(page);
html = await page.content();
const lanc = await page.$$eval('#tabelaResumo tbody tr', trs => trs.map(tr => tr.textContent.replace(/\s+/g, ' ').trim()));
check('L4 confirmou: contas lançadas e os 3 meses viram Lançado', /contas lançadas no Contas a Pagar/.test(html) && lanc.length === 3 && lanc.every(l => l.includes('Lançado') && !l.includes('Não lançado')), lanc.join(' | '));
check('L5 a tela avisa que o período já está todo lançado', html.includes('já está todo lançado'));

// ---- ver as contas do mês
await nav(page, () => page.click('#tabelaResumo tbody tr:first-child a'));
html = await page.content();
check('C1 o botão do mês abre o Contas a Pagar só com a folha de jan/2028', page.url().includes('competencia=2028-01') && html.includes('Vale 01/2028 — Maria Santos') && html.includes('Guia FGTS navegador 01/2028') && html.includes('Mostrando só as contas lançadas pela folha') && !html.includes('02/2028 —'));
await page.click('button[data-baixar][data-descricao*="Guia FGTS navegador"]');
await page.waitForSelector('#modalBaixa.show', { timeout: 5000 }).catch(() => {});
await page.$eval('#baixaValor', el => { el.value = '251.37'; el.dispatchEvent(new Event('input')); });
await nav(page, () => page.click('#formBaixa button[type=submit]'));
html = await page.content();
check('C2 guia do mês ajustada na baixa (251,37) e marcada como paga', html.includes('Conta marcada como paga') && html.includes('Pago: R$ 251,37'));

// ---- excluir a guia na folha
await page.goto(BASE + F + '?inicio=2028-01&meses=3', { waitUntil: 'networkidle2' });
const pago = await page.$eval('#tabelaResumo tbody tr:first-child', tr => tr.textContent.replace(/\s+/g, ' '));
check('R1 resumo de jan/2028 mostra o que já foi pago', pago.includes('251,37'), pago);
await page.evaluate(() => [...document.querySelectorAll('#tabelaItens tr')].find(tr => tr.textContent.includes('Guia FGTS navegador')).querySelector('form button').click());
await confirmarModal(page);
html = await page.content();
check('X1 excluir a guia pede confirmação e ela sai da lista', html.includes('Item excluído') && !(await page.$eval('#tabelaItens', t => t.textContent)).includes('Guia FGTS navegador'));

check('Z1 nenhum erro de JavaScript nas telas', erros.length === 0, erros.join(' | '));
console.log(`\n${ok} ✅ / ${fail} ❌`);
await browser.close();
process.exit(fail ? 1 : 0);
