// Capturas da loja de atacado da Cell Master (27/11/2026) no ambiente descartável.
// BASE=http://10.213.91.4 CHROME=... NODE_PATH=... PRINTS=pasta [LOGIN=1 EMAIL=.. SENHA=..] [SUFIXO=-previa] node scripts/loja-cellmaster-capturas.mjs
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const puppeteer = require('puppeteer-core');
const BASE = process.env.BASE || 'http://10.213.91.4';
const OUT = process.env.PRINTS || 'storage/app/previa-cellmaster';
const SLUG = process.env.SLUG || 'n-s-borba-servicos';
const SUF = process.env.SUFIXO || '';
const b = await puppeteer.launch({ executablePath: process.env.CHROME, args: ['--no-sandbox'] });
const p = await b.newPage();
const erros = [];
p.on('pageerror', e => erros.push(e.message));
await p.setViewport({ width: 1366, height: 900 });
if (process.env.LOGIN) {
  await p.goto(BASE + '/login', { waitUntil: 'networkidle0' });
  await p.type('input[name=email]', process.env.EMAIL || 'dono-cm@teste.local');
  await p.type('input[name=password]', process.env.SENHA || 'teste123');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
}
const tirar = async (url, nome, largura, alto = 900, inteira = true, antes = null) => {
  await p.setViewport({ width: largura, height: alto, deviceScaleFactor: largura < 500 ? 2 : 1 });
  await p.goto(BASE + url, { waitUntil: 'networkidle0' });
  await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); } window.scrollTo(0, 0); });
  await p.evaluate(() => { const b = document.querySelector('[data-lj-cookies] button, .lj-cookies button'); if (b) b.click(); });
  if (antes) await antes();
  await new Promise(r => setTimeout(r, 1300));
  const lateral = await p.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
  await p.screenshot({ path: `${OUT}/${nome}${SUF}.jpg`, fullPage: inteira, quality: 74, type: 'jpeg' });
  console.log(nome + SUF, lateral ? 'ROLAGEM LATERAL' : 'ok');
};
const L = '/loja/' + SLUG;
for (const alvo of (process.env.SO || 'inicio,topo,produtos,produto,carrinho').split(',')) {
  if (alvo === 'inicio') { await tirar(L, 'inicio-computador', 1366); await tirar(L, 'inicio-celular', 390, 844); }
  if (alvo === 'topo') { await tirar(L, 'topo-computador', 1366, 900, false); await tirar(L, 'topo-celular', 390, 844, false); }
  if (alvo === 'produtos') { await tirar(L + '/produtos', 'produtos-computador', 1366, 900, false); await tirar(L + '/produtos', 'produtos-celular', 390, 844, false); }
  if (alvo === 'produto' && process.env.PRODUTO) { await tirar(L + '/' + process.env.PRODUTO, 'produto-computador', 1366, 900, false); await tirar(L + '/' + process.env.PRODUTO, 'produto-celular', 390, 844, false); }
  if (alvo === 'carrinho') { await tirar(L + '/carrinho', 'carrinho-computador', 1366, 900, false); await tirar(L + '/carrinho', 'carrinho-celular', 390, 844, false); }
}
console.log('erros JS:', erros.length ? erros : 'nenhum');
await b.close();
