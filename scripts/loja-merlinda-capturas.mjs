// Capturas da loja da Miss Merlinda (17/11/2026) no ambiente descartável.
// BASE=http://10.213.80.13 CHROME=... NODE_PATH=... PRINTS=pasta node scripts/loja-merlinda-capturas.mjs
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const puppeteer = require('puppeteer-core');
const BASE = process.env.BASE || 'http://10.213.80.13';
const OUT = process.env.PRINTS || 'storage/app/previa-merlinda';
const b = await puppeteer.launch({ executablePath: process.env.CHROME, args: ['--no-sandbox'] });
const p = await b.newPage();
const erros = [];
p.on('pageerror', e => erros.push(e.message));
await p.setViewport({ width: 1366, height: 900 });
await p.goto(BASE + '/login', { waitUntil: 'networkidle0' });
await p.type('input[name=email]', process.env.EMAIL || 'ferrugemtintas@gmail.com');
await p.type('input[name=password]', 'teste123');
await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
const tirar = async (url, nome, largura, alto = 900, inteira = true) => {
  await p.setViewport({ width: largura, height: alto, deviceScaleFactor: largura < 500 ? 2 : 1 });
  await p.goto(BASE + url, { waitUntil: 'networkidle0' });
  // rola até o fim para as fotos com loading=lazy carregarem, e volta ao topo
  await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); } window.scrollTo(0, 0); });
  await p.evaluate(() => { const b = document.querySelector('[data-lj-cookies] button, .lj-cookies button'); if (b) b.click(); });
  await new Promise(r => setTimeout(r, 1200));
  const lateral = await p.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
  await p.screenshot({ path: `${OUT}/${nome}.jpg`, fullPage: inteira, quality: 72, type: 'jpeg' });
  console.log(nome, lateral ? 'ROLAGEM LATERAL' : 'ok');
};
await tirar('/loja/miss-merlinda', 'inicio-computador', 1366);
await tirar('/loja/miss-merlinda', 'inicio-topo-computador', 1366, 900, false);
await tirar('/loja/miss-merlinda', 'inicio-celular', 390, 844);
await tirar('/loja/miss-merlinda', 'inicio-topo-celular', 390, 844, false);
await tirar('/app/loja-online/editor', 'editor', 1366, 900, false);
console.log('erros JS:', erros.length ? erros : 'nenhum');
await b.close();
