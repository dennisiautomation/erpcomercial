// Ajuste = balanço (26/11/2026): confere a tela de Nova Movimentação e a lista no navegador.
// uso: node validar-ajuste-balanco-26nov.browser.mjs <BASE> <email> <senha> <codigo_do_produto> [pasta-prints]
// GRAVA movimentações de teste no produto informado — rodar só em homologação.
import { createRequire } from 'module';
import fs from 'fs';
const require = createRequire('/home/ubuntu/apps/caixa-vinculo-erp/');
const puppeteer = require('puppeteer-core');
const CHROME = fs.readdirSync('/home/ubuntu/.cache/puppeteer/chrome-headless-shell').sort().reverse()
  .map((d) => `/home/ubuntu/.cache/puppeteer/chrome-headless-shell/${d}/chrome-headless-shell-linux64/chrome-headless-shell`)
  .find((p) => fs.existsSync(p));
const [BASE, EMAIL, SENHA, CODIGO, PRINTS] = process.argv.slice(2);
let ok = 0, falha = 0;
const chk = (n, c, x = '') => { c ? ok++ : falha++; console.log(c ? '✅' : '❌', n, c ? '' : x); };
const browser = await puppeteer.launch({ headless: 'shell', executablePath: CHROME, args: ['--no-sandbox'] });
const p = await browser.newPage();
const erros = []; p.on('pageerror', (e) => erros.push(e.message));
await p.setViewport({ width: 1366, height: 900 });
await p.goto(BASE + '/login', { waitUntil: 'networkidle2' });
await p.type('input[name=email]', EMAIL); await p.type('input[name=password]', SENHA);
await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type=submit]')]);
// usuário com mais de uma loja cai na escolha da loja: entra na primeira
if (p.url().includes('selecionar-unidade')) {
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.evaluate(() => document.querySelector('form[action*="selecionar-unidade"]').submit())]);
}

const linha = () => p.evaluate(() => ({
  rotulo: document.querySelector('.rotulo-quantidade').textContent.trim(),
  min: document.querySelector('input[name="itens[0][quantidade]"]').min,
  info: document.querySelector('.saldo-linha').textContent.trim(),
  aviso: !document.getElementById('aviso-ajuste').classList.contains('d-none'),
}));
async function abrir() {
  await p.goto(BASE + '/app/estoque/movimentacoes/create', { waitUntil: 'networkidle2' });
}
async function escolherProduto() {
  await p.type('.produto-texto', CODIGO);
  await p.keyboard.press('Enter');
}
async function lancar(tipo, qtd) {
  await abrir();
  await p.click(`label[for=tipo-${tipo}]`);
  await escolherProduto();
  await p.type('input[name="itens[0][quantidade]"]', String(qtd));
  const antes = await linha();
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('#form-movimentacao button[type=submit]')]);
  const primeira = await p.evaluate(() => {
    const tr = document.querySelector('table tbody tr'); if (!tr) return null;
    const td = [...tr.querySelectorAll('td')].map((c) => c.textContent.replace(/\s+/g, ' ').trim());
    return { tipo: td[2], qtd: td[3], estoque: td[4], baixo: !!tr.querySelector('.bi-arrow-down-short'), cima: !!tr.querySelector('.bi-arrow-up-short') };
  });
  const aviso = await p.evaluate(() => [...document.querySelectorAll('.alert')].map((a) => a.textContent.replace(/\s+/g, ' ').trim()).join(' | '));
  return { antes, primeira, aviso };
}
const num = (t) => Number(t.replace(/\./g, '').replace(',', '.'));
const saldoDepois = (r) => num(r.primeira.estoque.split(' ').pop());

// 1) tela: Entrada × Ajuste
await abrir();
let l = await linha();
chk('Entrada: rótulo "Quantidade", mínimo 0,001, sem aviso', l.rotulo === 'Quantidade' && l.min === '0.001' && !l.aviso, JSON.stringify(l));
await p.click('label[for=tipo-ajuste]');
l = await linha();
chk('Ajuste: rótulo "Quantidade contada", aceita zero, aviso do balanço aparece', l.rotulo === 'Quantidade contada' && l.min === '0' && l.aviso, JSON.stringify(l));
await escolherProduto();
l = await linha();
chk('Produto escolhido mostra "No sistema: X"', /^No sistema: /.test(l.info), l.info);
const saldo0 = num(l.info.replace('No sistema: ', ''));
await p.type('input[name="itens[0][quantidade]"]', String(saldo0 + 3));
l = await linha();
chk('Contou 3 a mais → "entra 3"', /entra 3$/.test(l.info), l.info);
if (PRINTS) await p.screenshot({ path: PRINTS + '/ajuste-nova-1366.png' });
await p.click('label[for=tipo-perda]');
l = await linha();
chk('Voltar para Perda: rótulo "Quantidade" e sem a diferença', l.rotulo === 'Quantidade' && !/entra|sai/.test(l.info) && !l.aviso, JSON.stringify(l));

// 2) gravar: ajuste para menos, igual, repetido, para mais, zero
let r = await lancar('entrada', 10);
const base = saldoDepois(r);
chk('Entrada de 10 soma', base === saldo0 + 10, JSON.stringify(r.primeira));
r = await lancar('ajuste', 4);
chk('Ajuste contado 4 → estoque 4, seta para baixo, diferença na coluna', saldoDepois(r) === 4 && r.primeira.baixo && num(r.primeira.qtd) === base - 4 && /sai /.test(r.antes.info), JSON.stringify(r));
r = await lancar('ajuste', 4);
chk('Ajuste de 4 de novo NÃO soma e avisa que nada mudou', saldoDepois(r) === 4 && /já estava/.test(r.aviso), JSON.stringify(r));
r = await lancar('ajuste', 9);
chk('Ajuste contado 9 → estoque 9, seta para cima', saldoDepois(r) === 9 && r.primeira.cima && num(r.primeira.qtd) === 5, JSON.stringify(r));
r = await lancar('ajuste', 0);
chk('Ajuste contado 0 → estoque zerado', saldoDepois(r) === 0 && r.primeira.baixo, JSON.stringify(r));
if (PRINTS) await p.screenshot({ path: PRINTS + '/ajuste-lista-1366.png' });
// devolve o saldo do começo
r = await lancar('ajuste', saldo0);
chk('Saldo do começo devolvido', saldo0 === 0 ? /já estava/.test(r.aviso) : saldoDepois(r) === saldo0, JSON.stringify(r));

// 3) celular
await p.setViewport({ width: 390, height: 800 });
await abrir();
await p.click('label[for=tipo-ajuste]');
const sobra = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
chk('390 px sem rolagem lateral', sobra <= 1, 'sobra ' + sobra);
if (PRINTS) await p.screenshot({ path: PRINTS + '/ajuste-nova-390.png' });
chk('Nenhum erro de JavaScript', erros.length === 0, erros.join(' | '));
console.log(`\n${ok} ✅ / ${falha} ❌`);
await browser.close();
process.exit(falha ? 1 : 0);
