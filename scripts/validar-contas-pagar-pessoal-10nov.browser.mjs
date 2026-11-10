// Validação ✅/❌ no NAVEGADOR — Contas a Pagar para despesa de pessoal (10/11/2026).
// Botões e janelas: trocar "Pagar para", salvar salário de funcionário, janela de baixa, editar, estornar.
//   BASE=http://172.17.0.x:8299 CHROME=/caminho/chrome NODE_PATH=/home/ubuntu/caixa-v2/node_modules \
//     node scripts/validar-contas-pagar-pessoal-10nov.browser.mjs
// Rodar DEPOIS do validar-contas-pagar-pessoal-10nov.py (usa o plano de contas que ele cria).
import { createRequire } from 'module';
const require = createRequire(process.env.NODE_PATH + '/');
const puppeteer = require('puppeteer-core');

const BASE = process.env.BASE || 'http://127.0.0.1:8299';
let ok = 0, fail = 0;
const check = (nome, cond, obtido = '') => { cond ? ok++ : fail++; console.log((cond ? '✅ ' : '❌ ') + nome + (cond ? '' : `  (obtido: ${String(obtido).slice(0, 300)})`)); };
const visivel = (page, sel) => page.$eval(sel, el => el.offsetParent !== null).catch(() => false);
const CP = '/app/financeiro/contas-pagar';

const browser = await puppeteer.launch({ executablePath: process.env.CHROME, args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 1366, height: 900 });
const erros = [];
page.on('pageerror', e => erros.push(e.message));
page.on('console', m => { if (m.type() === 'error' && !/favicon|ERR_|Failed to load resource/.test(m.text())) erros.push(m.text()); });

await page.goto(BASE + '/login', { waitUntil: 'networkidle2' });
await page.type('input[name=email]', 'dono@demo.com');
await page.type('input[name=password]', 'dono123');
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.click('button[type=submit]')]);
if (page.url().includes('selecionar-unidade')) {
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.evaluate(() => { const f = document.querySelector('form'); (f.querySelector('button[type=submit],button') || f).click(); })]);
}

// ---- Nova conta: trocar "Pagar para"
await page.goto(BASE + CP + '/create', { waitUntil: 'networkidle2' });
check('N1 abre em Fornecedor: só a busca de fornecedor aparece', await visivel(page, '#fornecedorBusca') && !(await visivel(page, '#funcionario_id')) && !(await visivel(page, '#favorecido_nome')));
await page.click('label[for=favTipoFuncionario]');
check('N2 Funcionário: aparece a lista de funcionários e some o fornecedor', await visivel(page, '#funcionario_id') && !(await visivel(page, '#fornecedorBusca')) && !(await visivel(page, '#favorecido_nome')));
await page.click('label[for=favTipoOutro]');
check('N3 Imposto / outro: aparece o campo de nome livre', await visivel(page, '#favorecido_nome') && !(await visivel(page, '#funcionario_id')));

// salvar sem preencher o nome: fica na tela
await page.type('#descricao', 'Guia navegador');
await page.type('#valor', '123.45');
await page.$eval('#vencimento', el => { el.value = '2027-06-10'; el.dispatchEvent(new Event('change')); });
await page.click('form[action$="contas-pagar"] button[type=submit]');
await new Promise(r => setTimeout(r, 600));
check('N4 salvar sem dizer para quem não sai da tela e marca o campo', page.url().endsWith('/create') && await page.$eval('#favorecido_nome', el => el.classList.contains('is-invalid')));
check('N4b o botão Salvar continua liberado depois do aviso (antes travava 10 s)', await page.$eval('form[action$="contas-pagar"] button[type=submit]', b => !b.disabled));

// funcionário + recorrente: prévia e salvar
await page.click('label[for=favTipoFuncionario]');
const funcId = await page.$eval('#funcionario_id', el => { const o = [...el.options].find(o => o.textContent.includes('Carlos')); el.value = o.value; return o.value; });
await page.$eval('#descricao', el => { el.value = 'Salário navegador'; });
await page.select('#plano_conta_id', await page.$eval('#plano_conta_id', el => [...el.options].find(o => o.textContent.includes('3.1.01')).value));
await page.click('#recorrente');
await new Promise(r => setTimeout(r, 300));
const previa = await page.$$eval('#parcelas-list tr', trs => trs.length);
check('N5 recorrente mostra a prévia dos 12 meses', previa === 12 && await visivel(page, '#recorrencia-options'), previa);
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.click('form[action$="contas-pagar"] button[type=submit]')]);
let html = await page.content();
check('N6 salvou e voltou para a lista com o aviso de sucesso', page.url().includes(CP) && !page.url().includes('create') && html.includes('cadastrada com sucesso'), page.url());

// ---- Lista: janela de baixa
await page.goto(BASE + CP + '?vencimento_inicio=2027-06-01&vencimento_fim=2027-06-30', { waitUntil: 'networkidle2' });
html = await page.content();
check('L1 a conta aparece com o funcionário como favorecido', html.includes('Salário navegador') && html.includes('Carlos Oliveira'));
await page.click('button[data-baixar][data-descricao*="Salário navegador"]'); // 1ª das 12 (jun/2027)
await page.waitForSelector('#modalBaixa.show', { timeout: 5000 }).catch(() => {});
const m = await page.evaluate(() => ({ aberto: document.getElementById('modalBaixa').classList.contains('show'), valor: document.getElementById('baixaValor').value, data: document.getElementById('baixaData').value, desc: document.getElementById('baixaDescricao').textContent, action: document.getElementById('formBaixa').action, aviso: getComputedStyle(document.getElementById('baixaAvisoValor')).display }));
const hoje = await page.evaluate(() => { const d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); });
check('L2 Dar baixa abre a janela com valor lançado, data de hoje e a conta certa', m.aberto && m.valor === '123.45' && m.data === hoje && m.desc.includes('Salário navegador') && /\/baixar$/.test(m.action) && m.aviso === 'none', JSON.stringify(m));
await page.$eval('#baixaValor', el => { el.value = '130.00'; el.dispatchEvent(new Event('input')); });
check('L3 valor diferente do lançado mostra o aviso', await page.$eval('#baixaAvisoValor', el => getComputedStyle(el).display) === 'block');
await page.select('#baixaForma', 'pix');
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.click('#formBaixa button[type=submit]')]);
html = await page.content();
check('L4 confirmar a baixa marca como paga com o valor ajustado', html.includes('Conta marcada como paga') && html.includes('Pago: R$ 130,00'));

// ---- Detalhe: estornar, editar
const showUrl = await page.$eval('button[data-baixar][data-descricao*="Salário navegador"]', b => b.dataset.baixar.replace(/\/baixar$/, '')).catch(() => null)
    || await page.evaluate(() => [...document.querySelectorAll('tr')].find(tr => tr.textContent.includes('Salário navegador')).querySelector('a[title="Detalhes"]').href);
await page.goto(showUrl, { waitUntil: 'networkidle2' });
check('D1 detalhe da paga mostra Estornar e não mostra Editar', (await page.content()).includes('Estornar baixa') && !(await page.$('a[href$="/edit"]')));
await page.click('form[action$="/estornar"] button[type=submit]');
await page.waitForSelector('.modal.show', { timeout: 5000 }).catch(() => {});
const confirmar = await page.evaluateHandle(() => [...document.querySelectorAll('.modal.show button')].find(b => /confirm|sim|ok/i.test(b.textContent) && !/cancel/i.test(b.textContent)));
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), confirmar.asElement().click()]);
html = await page.content();
check('D2 estornar pede confirmação e devolve para pendente', html.includes('Baixa estornada') && html.includes('Dar baixa'));
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.click('a[href$="/edit"]')]);
const e = await page.evaluate(() => ({ func: document.getElementById('funcionario_id').value, funcVis: document.getElementById('funcionario_id').offsetParent !== null, fornVis: document.getElementById('fornecedorBusca').offsetParent !== null, valor: document.getElementById('valor').value, proximas: !!document.getElementById('aplicar_proximas') }));
check('E1 Editar abre já em Funcionário, com o valor e a opção de aplicar nas próximas', e.func === funcId && e.funcVis && !e.fornVis && e.valor === '123.45' && e.proximas, JSON.stringify(e));
await page.$eval('#valor', el => { el.value = '150.00'; });
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.click('form[action*="contas-pagar/"] button.btn-erp-primary')]);
html = await page.content();
check('E2 salvar a edição volta ao detalhe com o valor novo', html.includes('Conta atualizada') && html.includes('150,00'));

// ---- Plano de contas: botão
await page.goto(BASE + '/app/plano-contas', { waitUntil: 'networkidle2' });
check('P1 plano de contas mostra o botão Criar contas padrão e as contas de pessoal', (await page.content()).includes('Criar contas padrão') && (await page.content()).includes('Vale-transporte'));

check('Z1 nenhum erro de JavaScript nas telas', erros.length === 0, erros.join(' | '));
console.log(`\n${ok} ✅ / ${fail} ❌`);
await browser.close();
process.exit(fail ? 1 : 0);
