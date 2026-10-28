// Prints + cliques da integracao-28out (puppeteer). Chamado por scripts/validar-integracao-28out.sh.
//   BASE=http://127.0.0.1:8097 OUT=<pasta> PED=<id do pedido faturado> PUPPETEER=<pasta do módulo> node validar-integracao-28out.mjs
// Escreve OUT/resultado.tsv (OK|FALHA <TAB> descrição) e os .png.
import fs from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const puppeteer = require(process.env.PUPPETEER || 'puppeteer');
const { BASE, OUT, PED } = process.env; const ANTES = process.env.ANTES || BASE;
const CHROME = process.env.CHROME || fs.readdirSync('/home/ubuntu/.cache/puppeteer/chrome-headless-shell').sort().map((d) => `/home/ubuntu/.cache/puppeteer/chrome-headless-shell/${d}/chrome-headless-shell-linux64/chrome-headless-shell`).reverse().find((p) => fs.existsSync(p));
const res = [];
const ok = (d, c, extra = '') => { res.push(`${c ? 'OK' : 'FALHA'}\t${d}${c ? '' : ` (${extra})`}`); console.log(`${c ? '✅' : '❌'} ${d} ${c ? '' : extra}`); };
const shot = (page, n) => page.screenshot({ path: `${OUT}/${n}.png`, fullPage: true });

const browser = await puppeteer.launch({ headless: 'shell', args: ['--no-sandbox', '--disable-dev-shm-usage'], executablePath: CHROME });
try {
  const page = await browser.newPage();
  await page.setViewport({ width: 1366, height: 900 });
  page.on('pageerror', (e) => console.log('  pageerror:', e.message));
  await page.goto(BASE + '/login', { waitUntil: 'networkidle2' });
  await shot(page, '01-login');
  await page.type('#email', 'dono@demo.com'); await page.type('#password', 'dono123');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.keyboard.press('Enter')]);
  ok('login do dono', !page.url().includes('/login'), page.url());

  // dashboard (gráficos) — Chart presente e sem erro de JS
  await page.goto(BASE + '/app/dashboard', { waitUntil: 'networkidle2' });
  const temChart = await page.evaluate(() => typeof window.Chart !== 'undefined');
  ok('dashboard: Chart.js carregado', temChart);
  await shot(page, '02-dashboard');

  // preço inline na lista
  await page.goto(BASE + '/app/produtos', { waitUntil: 'networkidle2' });
  await shot(page, '03-produtos-lista');
  const temLapis = await page.$('.preco-inline');
  ok('lista de produtos: lápis do preço visível para o dono', !!temLapis);
  if (temLapis) {
    await page.click('.preco-inline');
    await new Promise((r) => setTimeout(r, 300));
    const input = await page.$('.preco-inline input');
    ok('clique no preço abre o campo de edição', !!input);
    await shot(page, '04-produtos-preco-editando');
    if (input) { await page.keyboard.press('Escape'); await new Promise((r) => setTimeout(r, 300)); ok('Esc fecha sem salvar', !(await page.$('.preco-inline input'))); }
  }

  // Configurações da Loja em abas
  await page.goto(BASE + '/app/configuracoes', { waitUntil: 'networkidle2' });
  await shot(page, '05-configuracoes-aba-caixa');
  for (const aba of ['precos', 'cupom', 'trocas', 'estoque', 'impressos']) {
    await page.click(`#tab-${aba}`);
    await new Promise((r) => setTimeout(r, 350));
    const ativa = await page.evaluate((a) => document.querySelector(`#tab-${a}`)?.classList.contains('active') && !!document.querySelector('.tab-pane.active.show'), aba);
    ok(`configurações: aba ${aba} abre`, !!ativa);
    await shot(page, `06-configuracoes-aba-${aba}`);
  }
  const salvar = await page.evaluate(() => { const b = [...document.querySelectorAll('button[type=submit]')].find((x) => /salvar/i.test(x.textContent)); if (!b) return null; const r = b.getBoundingClientRect(); return { top: r.top, h: innerHeight }; });
  ok('configurações: botão Salvar visível na tela (barra fixa)', !!salvar && salvar.top >= 0 && salvar.top < salvar.h, JSON.stringify(salvar));

  // pedido + folha A4
  if (PED) {
    await page.goto(`${BASE}/app/pedidos/${PED}`, { waitUntil: 'networkidle2' });
    await shot(page, '07-pedido-show');
    await page.goto(`${BASE}/app/pedidos/${PED}/imprimir`, { waitUntil: 'networkidle2' });
    const txt = await page.evaluate(() => document.body.textContent); // textContent: sem o text-transform do CSS
    ok('folha A4 do pedido: "Entregar para" + "Loja:" + "Pedido de venda", sem título ORÇAMENTO', /Entregar para/.test(txt) && /Loja:/.test(txt) && /Pedido de venda/i.test(txt) && !/ORÇAMENTO/.test(txt), txt.slice(0, 200));
    await shot(page, '08-pedido-folha-a4');
  }

  // responsivo: PDV, vendas e dashboard em 375px — nunca pior que a produção (ANTES), e PDV/vendas sem rolagem
  const medir = async (pg, base, u) => { await pg.goto(base + u, { waitUntil: 'networkidle2' }); return pg.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth); };
  const ctxA = await browser.createBrowserContext(); const pa = await ctxA.newPage();
  await pa.goto(ANTES + '/login', { waitUntil: 'networkidle2' }); await pa.type('#email', 'dono@demo.com'); await pa.type('#password', 'dono123');
  await Promise.all([pa.waitForNavigation({ waitUntil: 'networkidle2' }), pa.keyboard.press('Enter')]);
  await pa.setViewport({ width: 375, height: 812, isMobile: true });
  await page.setViewport({ width: 375, height: 812, isMobile: true });
  for (const [u, n] of [['/app/pdv', '09-pdv-375'], ['/app/vendas', '10-vendas-375'], ['/app/dashboard', '11-dashboard-375']]) {
    const antes = await medir(pa, ANTES, u); const depois = await medir(page, BASE, u);
    ok(`${u} em 375px: rolagem horizontal depois ${depois}px ≤ antes ${antes}px` + (u === '/app/dashboard' ? ' (dashboard já estourava em produção)' : ''), depois <= Math.max(antes, 2), `${depois}px`);
    await shot(page, n);
  }
} finally {
  await browser.close();
  fs.writeFileSync(`${OUT}/resultado.tsv`, res.join('\n') + '\n');
}
