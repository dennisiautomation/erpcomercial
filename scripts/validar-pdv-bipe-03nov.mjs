// Validação ✅/❌ no NAVEGADOR — bipe no PDV (03/11/2026): lista de busca reaparecendo depois do bipe (+1 ao tocar),
// quantidade virando o código de barras, Enter do leitor apertando botão e releitura do leitor.
// Simula o leitor (digita rápido + Enter) no JavaScript real do PDV. Ambiente DESCARTÁVEL. Uso:
//   BASE=http://127.0.0.1:8297 DB_CT=cs-mysql PUPPETEER=<.../node_modules/puppeteer> node scripts/validar-pdv-bipe-03nov.mjs
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const puppeteer = require(process.env.PUPPETEER || 'puppeteer');
const BASE = process.env.BASE || 'http://127.0.0.1:8297';
const CHROME = fs.readdirSync('/home/ubuntu/.cache/puppeteer/chrome-headless-shell').sort().map(d => `/home/ubuntu/.cache/puppeteer/chrome-headless-shell/${d}/chrome-headless-shell-linux64/chrome-headless-shell`).reverse().find(p => fs.existsSync(p));
const CODE = '7891234567895';
const DB_CT = process.env.DB_CT || 'cs-mysql';
execFileSync('docker', ['exec', DB_CT, 'mysql', '-uerp', '-perp', 'erp_teste', '-e', "update produtos set codigo_barras='7891234567895', codigo_interno='PROD003', preco_venda=30, status='ativo' where id=3"], { stdio: 'ignore' });
const browser = await puppeteer.launch({ headless: 'shell', args: ['--no-sandbox', '--disable-dev-shm-usage'], executablePath: CHROME });
const page = await browser.newPage();
await page.setViewport({ width: 1400, height: 900 });
await page.goto(`${BASE}/login`);
await page.type('input[name=email]', 'caixa@demo.com');
await page.type('input[name=password]', 'caixa123');
await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
await page.goto(`${BASE}/app/caixa/abrir`);
await page.evaluate(async () => { const t = document.querySelector('input[name=_token]')?.value; await fetch('/app/caixa/abrir', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `_token=${encodeURIComponent(t)}&valor_abertura=0` }); });

const sleep = ms => new Promise(r => setTimeout(r, ms));
async function novo() { await page.goto(`${BASE}/app/pdv`, { waitUntil: 'networkidle0' }); }
// leitor: digita rápido (3 ms/tecla) e termina com Enter
async function bipar(enters = 1) { await page.keyboard.type(CODE, { delay: 3 }); for (let i = 0; i < enters; i++) await page.keyboard.press('Enter'); }
const qtds = () => page.evaluate(() => PDV.itens.map(i => i.quantidade));
const linhas = () => page.evaluate(() => document.querySelectorAll('#itemsBody tr').length);
async function relata(nome, esperado) { const q = await qtds(); const ok = JSON.stringify(q) === JSON.stringify(esperado); console.log(`${ok ? '✅' : '❌'} ${nome}: itens=${JSON.stringify(q)} (esperado ${JSON.stringify(esperado)}) linhas=${await linhas()}`); }


const dd = () => page.evaluate(() => document.getElementById('searchDropdown').classList.contains('show'));
await novo(); await page.focus('#searchInput'); for (let i = 0; i < 3; i++) { await bipar(); await sleep(700); }
await relata('1 foco na busca, 3 bipes espaçados', [3]);
await novo(); await page.click('.items-container'); for (let i = 0; i < 3; i++) { await bipar(); await sleep(700); }
await relata('2 foco fora de qualquer campo, 3 bipes', [3]);
await novo(); await page.focus('#searchInput'); for (let i = 0; i < 4; i++) { await bipar(); await sleep(150); }
await relata('3 leitor em modo contínuo: 4 leituras do mesmo código a cada ~190 ms contam como UM bipe', [1]);
await novo(); await page.focus('#searchInput'); for (let i = 0; i < 3; i++) { await bipar(); await sleep(450); }
await relata('3b 3 bipes com 450 ms entre eles (legítimos)', [3]);
await novo(); await page.focus('#searchInput'); await bipar(2); await sleep(700); await bipar(2); await sleep(700);
await relata('4 Enter duplo (CR+LF), 2 bipes espaçados', [2]);
await novo(); await page.focus('#searchInput'); await bipar(); await sleep(700);
await page.click('.qty-control input'); await bipar(); await sleep(900);
await relata('5 foco na caixinha de QUANTIDADE e bipa (antes virava o código)', [2]);
await novo(); await page.focus('#searchInput'); await bipar(); await sleep(700);
await page.click('.qty-control button:last-child'); await sleep(200); await bipar(); await sleep(700);
await relata('6 clica no "+" e bipa', [3]);
await novo(); await page.focus('#searchInput'); await bipar(); await sleep(700);
await page.evaluate(() => document.querySelector('.btn-pay')?.focus()); await bipar(); await sleep(900);
await relata('7 foco no botão de pagamento e bipa', [2]); console.log('   modal de pagamento abriu?', await page.evaluate(() => !!document.querySelector('.modal.show')), '(esperado false)');
await novo(); await page.focus('#searchInput'); await bipar(); await sleep(1000);
console.log(`${await dd() ? '❌' : '✅'} 8 lista de busca reaparece depois do bipe? ${await dd()} (esperado false) | qtd ${JSON.stringify(await qtds())}`);
await novo(); await page.focus('#searchInput'); await page.keyboard.type('Teclado', { delay: 120 }); await sleep(800);
console.log(`${await dd() ? '✅' : '❌'} 9 busca por NOME digitada continua mostrando a lista: ${await dd()}`);
await page.click('.search-result-item'); await sleep(700); await relata('9b clicar no resultado da busca por nome adiciona 1', [1]);
await novo(); await page.focus('#searchInput'); await page.keyboard.type('PROD003', { delay: 120 }); await page.keyboard.press('Enter'); await sleep(900);
await relata('10 código digitado À MÃO + Enter adiciona 1', [1]);
await novo(); await page.focus('#searchInput'); await bipar(); await sleep(700);
await page.click('.qty-control input'); await page.keyboard.down('Control'); await page.keyboard.press('a'); await page.keyboard.up('Control'); await page.keyboard.type('5', { delay: 200 }); await page.keyboard.press('Enter'); await sleep(500);
await relata('11 operador digita a quantidade à mão (5) na caixinha e confirma', [5]);
await browser.close();
