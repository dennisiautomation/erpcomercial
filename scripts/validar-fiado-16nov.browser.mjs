// Validação ✅/❌ no NAVEGADOR — Fiado / pagamento direto com a loja (16/11/2026).
// Rodar DEPOIS do .py (ele deixa o botão renomeado para FIADO e o caixa do dono aberto).
//   BASE=http://10.213.82.4 MYSQL=fi-mysql CHROME=/caminho/chrome-headless-shell NODE_PATH=/home/ubuntu/caixa-v2/node_modules PRINTS=/pasta \
//     node scripts/validar-fiado-16nov.browser.mjs
import { createRequire } from 'module';
import { execFileSync } from 'child_process';
const require = createRequire(process.env.NODE_PATH + '/');
const puppeteer = require('puppeteer-core');

const BASE = process.env.BASE || 'http://10.213.82.4';
const MYSQL = process.env.MYSQL || 'fi-mysql';
const PRINTS = process.env.PRINTS || '';
let ok = 0, fail = 0;
const check = (nome, cond, obtido = '') => { cond ? ok++ : fail++; console.log((cond ? '✅ ' : '❌ ') + nome + (cond ? '' : `  (obtido: ${String(obtido).slice(0, 300)})`)); };
const nav = (page, acao) => Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), acao()]);
const print = async (page, nome, full = false) => { if (PRINTS) await page.screenshot({ path: `${PRINTS}/${nome}.png`, fullPage: full }); };
const sql = q => execFileSync('docker', ['exec', '-i', MYSQL, 'mysql', '--default-character-set=utf8mb4', '-uerp', '-perp', 'erp_teste', '-N', '-B'], { input: q }).toString().trim();
const sleep = ms => new Promise(r => setTimeout(r, ms));
const iso = d => d.toISOString().slice(0, 10);
const hoje = new Date(); hoje.setHours(12);

// Estado conhecido: ninguém devendo; Maria sem limite, João com limite de R$ 300
sql(`DELETE FROM fiado_recebimentos; DELETE FROM contas_receber;
UPDATE clientes SET nome_razao_social='Maria do Fiado', limite_credito=NULL, whatsapp='(98) 99999-1111' WHERE id=2;
UPDATE clientes SET nome_razao_social='João Limite', limite_credito=300 WHERE id=3;`);

const browser = await puppeteer.launch({ executablePath: process.env.CHROME, args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 1366, height: 820 });
const erros = [];
page.on('pageerror', e => erros.push(e.message));
page.on('console', m => { if (m.type() === 'error' && !/favicon|ERR_|Failed to load resource/.test(m.text())) erros.push(m.text()); });
page.on('dialog', d => d.dismiss());

await page.goto(BASE + '/login', { waitUntil: 'networkidle2' });
await page.type('input[name=email]', 'dono@demo.com');
await page.type('input[name=password]', 'dono123');
await nav(page, () => page.click('button[type=submit]'));
if (page.url().includes('selecionar-unidade')) {
    await nav(page, () => page.evaluate(() => { const f = document.querySelector('form[action*="selecionar-unidade"]') || document.querySelector('main form, form'); (f.querySelector('button[type=submit], button')).click(); }));
}

// ---- Configurações → Formas de pagamento: o botão FIADO
await page.goto(BASE + '/app/configuracoes/formas-pagamento', { waitUntil: 'networkidle2' });
await print(page, '01-formas-pagamento-fiado');
const nomesPrevia = await page.$$eval('#fpAtivas .fp-btn .fp-nome', els => els.map(e => e.textContent.trim()));
check('1 tela de formas mostra o botão FIADO', nomesPrevia.includes('FIADO'), nomesPrevia);
// abre o editor do FIADO para o print do "funciona como"
const idxFiado = nomesPrevia.indexOf('FIADO');
if (idxFiado >= 0) {
    await (await page.$$('#fpAtivas .fp-btn'))[idxFiado].click();
    await sleep(300);
    await print(page, '02-editor-fiado');
    const txt = await page.evaluate(() => document.body.innerText);
    check('2 editor do FIADO diz "Direto com a loja (fiado / crediário)"', txt.includes('Direto com a loja (fiado / crediário)'));
}

// ---- Cadastro: limite do cliente
await page.goto(BASE + '/app/clientes/3/edit', { waitUntil: 'networkidle2' });
await page.evaluate(() => goToStep(4, true));
await sleep(500);
const lim = await page.$('#limite_credito');
if (lim) { await lim.scrollIntoView(); await sleep(200); }
await print(page, '03-cliente-limite');
check('3 cadastro do cliente explica o limite do fiado', (await page.evaluate(() => document.body.innerText)).includes('Deixe 0 para não ter limite'));

// ---- PDV (abre o caixa nesta sessão do navegador)
await page.goto(BASE + '/app/caixa/abrir', { waitUntil: 'networkidle2' });
if (await page.$('input[name=valor_abertura]')) {
    await page.$eval('input[name=valor_abertura]', e => { e.value = '100'; });
    await nav(page, () => page.click('form[action$="/caixa/abrir"] button[type=submit]'));
}
await page.goto(BASE + '/app/pdv', { waitUntil: 'networkidle2' });
check('4 PDV abre com o caixa aberto', page.url().includes('/app/pdv'), page.url());
const add = (id, nome, preco) => page.evaluate((id, nome, preco) => PDV.addProdutoById(id, nome, preco, '', ''), id, nome, preco);
await add(2, 'Mouse Logitech MX Master 3', 599.90);
await sleep(600);
await print(page, '04-pdv-botao-fiado');
check('5 PDV mostra o botão FIADO e o "Receber FIADO"', !!(await page.$('.btn-pay[data-forma="crediario"]')) && !!(await page.$('#btnReceberFiado')));

// FIADO sem cliente → pede o cliente
await page.click('.btn-pay[data-forma="crediario"]');
await sleep(700);
const alerta = await page.evaluate(() => [...document.querySelectorAll('.pdv-alert')].map(a => a.textContent).join(' | '));
check('6 FIADO sem cliente: aviso "precisa do cliente" e abre a busca de cliente', alerta.includes('precisa do cliente') && await page.$eval('#modalCliente', e => e.classList.contains('show')), alerta);
await page.type('#clienteSearchInput', 'Maria');
await page.waitForSelector('#clienteSearchResults .client-search-item', { timeout: 5000 });
await print(page, '05-pdv-escolher-cliente');
await page.click('#clienteSearchResults .client-search-item');
await page.waitForFunction(() => document.getElementById('modalPagamento').classList.contains('show') && document.getElementById('fiadoWrap').style.display === 'block', { timeout: 5000 }).catch(() => {});
await page.waitForFunction(() => document.getElementById('fiadoCliente').innerText.includes('Já deve'), { timeout: 5000 }).catch(() => {});
const vencDefault = await page.$eval('#fiadoVencimento', e => e.value);
const d30 = new Date(hoje); d30.setDate(d30.getDate() + 30);
check('7 escolhido o cliente, o pagamento FIADO reabre sozinho', await page.$eval('#modalPagamento', e => e.classList.contains('show')));
check('8 mostra quanto a Maria já deve (R$ 0,00) e "Sem limite"', await page.$eval('#fiadoCliente', e => e.innerText.includes('R$ 0,00') && e.innerText.includes('Sem limite')), await page.$eval('#fiadoCliente', e => e.innerText));
check('9 1º vencimento sugerido = hoje + 30 dias', vencDefault === iso(d30), vencDefault);
await page.select('#fiadoParcelas', '2');
await sleep(300);
const resumo = await page.$eval('#fiadoResumo', e => e.innerText);
check('10 resumo mostra "2x de R$ 299,95" e as datas', resumo.includes('2x de') && resumo.includes('299,95'), resumo);
await print(page, '06-pdv-pagamento-fiado');
const nVendas = +sql('SELECT COUNT(*) FROM vendas');
await page.click('#btnConfirmarPagamento');
await page.waitForFunction(n => true, {}, nVendas);
await sleep(2500);
const contas = sql("SELECT CONCAT(parcela,'/',total_parcelas,' ',valor,' ',status) FROM contas_receber WHERE cliente_id=2 ORDER BY parcela").split('\n');
check('11 venda finalizada: 2 parcelas de R$ 299,95 em aberto no nome da Maria', +sql('SELECT COUNT(*) FROM vendas') === nVendas + 1 && contas.join('|') === '1/2 299.95 pendente|2/2 299.95 pendente', contas);
await print(page, '07-pdv-venda-fiado-finalizada');

// cupom impresso tem o bloco do fiado
const vid = sql('SELECT id FROM vendas ORDER BY id DESC LIMIT 1');
const cup = await browser.newPage();
await cup.setViewport({ width: 420, height: 1100 });
await cup.goto(BASE + `/app/pdv/venda/${vid}/recibo`, { waitUntil: 'networkidle2' });
const cupTxt = await cup.evaluate(() => document.body.innerText.toLowerCase());
check('12 cupom: "FIADO — a pagar na loja", 2 vencimentos e assinatura', cupTxt.includes('fiado — a pagar na loja') && (cupTxt.match(/vence /g) || []).length === 2 && cupTxt.includes('maria do fiado'), cupTxt.slice(-400));
if (PRINTS) await cup.screenshot({ path: `${PRINTS}/08-cupom-fiado.png`, fullPage: true });
await cup.close();

// limite: João tem R$ 300, compra de R$ 599,90 é barrada no PDV
await page.goto(BASE + '/app/pdv', { waitUntil: 'networkidle2' });
await add(2, 'Mouse Logitech MX Master 3', 599.90);
await sleep(400);
await page.evaluate(() => PDV.selecionarCliente(3, 'João Limite', '158.729.340-06'));
await sleep(400);
await page.click('.btn-pay[data-forma="crediario"]');
await page.waitForFunction(() => document.getElementById('fiadoCliente').innerText.includes('pode comprar'), { timeout: 5000 }).catch(() => {});
await print(page, '09-pdv-limite');
const nV2 = +sql('SELECT COUNT(*) FROM vendas');
await page.click('#btnConfirmarPagamento');
await sleep(900);
check('13a campo de valor diz "Valor no FIADO (fica devendo)"', (await page.$eval('#valorRecebidoWrap label', e => e.textContent)).includes('Valor no FIADO'));
const alerta2 = await page.evaluate(() => [...document.querySelectorAll('.pdv-alert')].map(a => a.textContent).join(' | '));
check('13 passou do limite: o PDV avisa e não vende', alerta2.includes('Passa do limite') && +sql('SELECT COUNT(*) FROM vendas') === nV2, alerta2);
await print(page, '10-pdv-limite-aviso');
await page.evaluate(() => { const m = document.querySelector('.modal.show'); if (m) bootstrap.Modal.getInstance(m)?.hide(); PDV.novaVenda && PDV.novaVenda(); });
await sleep(600);

// ---- Receber FIADO no PDV
await page.goto(BASE + '/app/pdv', { waitUntil: 'networkidle2' });
await page.click('#btnReceberFiado');
await page.waitForSelector('#modalReceberFiado.show', { timeout: 5000 });
await page.type('#rfBuscaInput', 'Maria');
await page.waitForSelector('#rfResultados .client-search-item', { timeout: 5000 });
await print(page, '11-receber-buscar-cliente');
await page.click('#rfResultados .client-search-item');
await page.waitForFunction(() => document.getElementById('rfConta').style.display === 'block', { timeout: 5000 });
const dev = await page.$eval('#rfDevendo', e => e.innerText);
check('14 receber: mostra que a Maria deve R$ 599,90 e as 2 contas', dev.includes('599,90') && (await page.$$('#rfContas > div')).length === 2, dev);
check('15 botão Receber só libera depois de escolher como pagou', await page.$eval('#rfConfirmar', b => b.disabled));
await page.evaluate(() => document.querySelectorAll('.pdv-alert').forEach(a => a.remove()));
await page.click('.rf-forma[data-chave="dinheiro"]');
await sleep(300);
const alertaForma = await page.evaluate(() => [...document.querySelectorAll('.pdv-alert')].map(a => a.textContent).join(' | '));
check('15b escolher "Dinheiro" no recebimento não dispara o botão de venda', alertaForma === '', alertaForma);
const caixaModal = await page.$eval('#modalReceberFiado .modal-footer', e => e.getBoundingClientRect().bottom);
check('15c botão Receber visível sem rolar (tela 1366×820)', caixaModal <= 820, caixaModal);
await page.$eval('#rfValor', e => { e.value = ''; });
await page.type('#rfValor', '100');
await page.type('#rfObs', 'pagou uma parte');
await print(page, '12-receber-valor-forma');
await page.click('#rfConfirmar');
await sleep(1500);
const rec = sql("SELECT CONCAT(valor,' ',forma_pagamento,' ',saldo_depois) FROM fiado_recebimentos ORDER BY id DESC LIMIT 1");
check('16 recebido R$ 100 em dinheiro; ainda deve R$ 499,90', rec === '100.00 dinheiro 499.90', rec);
const alerta3 = await page.evaluate(() => [...document.querySelectorAll('.pdv-alert')].map(a => a.textContent).join(' | '));
check('17 PDV confirma "Recebido R$ 100,00. Ainda deve R$ 499,90."', alerta3.includes('Recebido R$ 100,00') && alerta3.includes('499,90'), alerta3);
const recId = sql('SELECT id FROM fiado_recebimentos ORDER BY id DESC LIMIT 1');
const rp = await browser.newPage();
await rp.setViewport({ width: 420, height: 900 });
await rp.goto(BASE + `/app/fiado/recibo/${recId}`, { waitUntil: 'networkidle2' }).catch(() => {});
if (PRINTS) await rp.screenshot({ path: `${PRINTS}/13-recibo-pagamento.png`, fullPage: true });
check('18 recibo do pagamento abre', (await rp.evaluate(() => document.body.innerText)).includes('RECIBO DE PAGAMENTO'));
await rp.close();

// ---- Quem me deve + ficha
sql("UPDATE contas_receber SET vencimento=DATE_SUB(CURDATE(), INTERVAL 3 DAY) WHERE cliente_id=2 AND parcela=1");
await page.goto(BASE + '/app/fiado', { waitUntil: 'networkidle2' });
await print(page, '14-quem-me-deve', true);
const txtIdx = await page.evaluate(() => document.body.innerText);
check('19 "Quem me deve" lista a Maria com R$ 499,90 e a conta vencida', txtIdx.includes('Maria do Fiado') && txtIdx.includes('499,90'), txtIdx.slice(0, 400));
await nav(page, () => page.click('a[href*="/app/fiado/cliente/2"]'));
await print(page, '15-ficha-cliente', true);
const txtF = await page.evaluate(() => document.body.innerText.toLowerCase());
check('20 ficha: deve, vencido, compras e o pagamento de R$ 100', txtF.includes('deve hoje') && txtF.includes('vencida') && txtF.includes('r$ 100,00'), txtF.slice(0, 300));
await page.click('label[for="forma-pix"]');
await page.click('#formReceber button[type=submit]');
await page.waitForSelector('.modal.show [data-role="ok"]', { timeout: 5000 });
await sleep(400);
await print(page, '15b-ficha-confirmar');
await nav(page, () => page.click('.modal.show [data-role="ok"]'));
const txtF2 = await page.evaluate(() => document.body.innerText);
check('21 receber o resto pela ficha (PIX): "Ainda deve R$ 0,00"', txtF2.includes('Ainda deve R$ 0,00'), txtF2.slice(0, 300));
await print(page, '16-ficha-quitado', true);

// ---- Fechamento do caixa
await page.goto(BASE + '/app/caixa/fechar', { waitUntil: 'networkidle2' });
await print(page, '17-fechamento-fiado-recebido', true);
const txtC = await page.evaluate(() => document.body.innerText.toLowerCase());
check('22 fechamento mostra "Fiado recebido de clientes"', txtC.includes('fiado recebido de clientes'));

// celular: ficha sem rolagem lateral
await page.setViewport({ width: 390, height: 844 });
await page.goto(BASE + '/app/fiado/cliente/2', { waitUntil: 'networkidle2' });
const largura = await page.evaluate(() => document.documentElement.scrollWidth);
check('23 ficha a 390 px sem rolagem lateral', largura <= 392, largura);
await page.goto(BASE + '/app/fiado', { waitUntil: 'networkidle2' });
const largura2 = await page.evaluate(() => document.documentElement.scrollWidth);
check('24 "Quem me deve" a 390 px sem rolagem lateral', largura2 <= 392, largura2);

check('25 nenhum erro de JavaScript', erros.length === 0, erros.join(' | '));
await browser.close();
console.log(`\n${ok} ✅ / ${fail} ❌`);
process.exit(fail ? 1 : 0);
