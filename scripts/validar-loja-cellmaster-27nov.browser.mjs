// Validação no navegador — loja de atacado da Cell Master (27/11/2026). Loja NO AR, com produtos marcados.
//   BASE=http://10.213.91.4 SLUG=n-s-borba-servicos P1=p/6018-... PRECO1=42,00 P2=p/....
//   CHROME=/caminho/chrome-headless-shell NODE_PATH=/home/ubuntu/caixa-v2/node_modules PRINTS=/pasta \
//   [EMAIL=dono-cm@teste.local SENHA=teste123] node scripts/validar-loja-cellmaster-27nov.browser.mjs
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const puppeteer = require('puppeteer-core');
const BASE = process.env.BASE || 'http://10.213.91.4', SLUG = process.env.SLUG || 'n-s-borba-servicos';
const L = BASE + '/loja/' + SLUG, OUT = process.env.PRINTS || '.';
const P1 = process.env.P1, P2 = process.env.P2, PRECO1 = process.env.PRECO1, MIN = Number(process.env.MINIMO || 6);
let ok = 0, ruim = 0;
const t = (id, texto, passou, extra = '') => { passou ? ok++ : ruim++; console.log(`${passou ? '✅' : '❌'} ${id} ${texto}${extra ? ' — ' + extra : ''}`); };
const espera = ms => new Promise(r => setTimeout(r, ms));
const b = await puppeteer.launch({ executablePath: process.env.CHROME, args: ['--no-sandbox'] });
const p = await b.newPage();
const erros = [];
p.on('pageerror', e => erros.push(e.message));
const ir = async (url, largura = 1366, alto = 900) => {
  await p.setViewport({ width: largura, height: alto, deviceScaleFactor: largura < 500 ? 2 : 1 });
  const r = await p.goto(url, { waitUntil: 'networkidle0' });
  await p.evaluate(() => { const c = document.querySelector('[data-lj-cookies] button, .lj-cookies button'); if (c) c.click(); });
  return r;
};
const foto = async (nome, inteira = false) => {
  await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 250) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 140)); } window.scrollTo(0, 0); });
  await espera(900);
  await p.screenshot({ path: `${OUT}/${nome}.jpg`, fullPage: inteira, quality: 74, type: 'jpeg' });
};
const lateral = () => p.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
const texto = sel => p.evaluate(s => (document.querySelector(s)?.textContent || '').replace(/\s+/g, ' ').trim(), sel);
const adicionar = async (caminho, qtd) => {
  await ir(L + '/' + caminho);
  await p.evaluate(q => { const c = document.querySelector('#lj-qtd'); c.value = q; }, qtd);
  await p.click('[data-lj-comprar-principal]');
  await p.waitForFunction(() => !document.querySelector('[data-lj-brinde]').hidden, { timeout: 8000 });
  await espera(900);
  return texto('[data-lj-brinde-texto]');
};

// ── A. página inicial ──
let r = await ir(L);
t('A01', 'página inicial abre no ar', r.status() === 200, 'HTTP ' + r.status());
t('A02', 'o modelo é o exclusivo cellmaster', await p.evaluate(() => document.body.dataset.modelo === 'cellmaster'));
t('A03', 'logo da Cell Master carregado no topo', await p.evaluate(() => { const i = document.querySelector('.lj-topo .lj-marca img'); return !!i && i.naturalWidth > 100; }));
await p.evaluate(() => document.fonts.ready);
t('A04', 'fonte do logo (Jost 900 itálico) carregada', await p.evaluate(() => document.fonts.check('italic 900 40px Jost')));
t('A05', `bloco da regra mostra ${MIN} peças`, new RegExp('^' + MIN + '\\s*peças').test(await texto('.lj-atacado__numero')), await texto('.lj-atacado__numero'));
t('A06', 'faixa fala do pedido mínimo', (await texto('.lj-faixa')).includes(`Pedido mínimo de ${MIN} peças`));
t('A07', 'título da capa é o h1 e cabe na tela', await p.evaluate(() => { const h = document.querySelector('.lj-slide h1'); const c = h.getBoundingClientRect(); return c.right <= innerWidth && c.left >= 0 && parseFloat(getComputedStyle(h).fontSize) <= 96; }));
t('A08', 'nenhum selo "Exemplo" para o público', !(await p.evaluate(() => !!document.querySelector('.lj-selo--exemplo, .lj-aviso-exemplo'))));
t('A09', 'vitrines trazem produtos de verdade', (await p.$$('.lj-card')).length >= 8, (await p.$$('.lj-card')).length + ' cartões');
t('A10', 'computador 1366 sem rolagem lateral', !(await lateral()));
await foto('publico-inicio-computador', true);
await ir(L, 390, 844);
t('A11', 'celular 390 sem rolagem lateral', !(await lateral()));
t('A12', 'celular: título da capa não estoura', await p.evaluate(() => { const c = document.querySelector('.lj-slide h1').getBoundingClientRect(); return c.right <= innerWidth + 1; }));
await foto('publico-inicio-celular', true);

// ── B. preço de atacado ──
await ir(L + '/' + P1);
const preco = await p.evaluate(() => document.querySelector('main').innerText);
t('B01', `página do produto mostra o preço de atacado (R$ ${PRECO1})`, preco.includes('R$ ' + PRECO1), P1);
t('B02', 'produto 1366 sem rolagem lateral', !(await lateral()));
await foto('publico-produto-computador');
await ir(L + '/' + P1, 390, 844);
t('B03', 'produto 390 sem rolagem lateral', !(await lateral()));
await foto('publico-produto-celular');
await ir(L + '/produtos');
t('B04', 'lista de produtos abre e não rola de lado', (await p.$$('.lj-card')).length > 0 && !(await lateral()));
await foto('publico-produtos-computador');
await ir(L + '/produtos', 390, 844);
t('B05', 'lista de produtos 390 sem rolagem lateral', !(await lateral()));
await foto('publico-produtos-celular');

// ── C. mínimo de peças ──
let aviso = await adicionar(P1, 2);
t('C01', 'ao adicionar 2 peças o aviso diz quantas faltam', aviso.includes(`Faltam ${MIN - 2} peças`), aviso);
await p.waitForFunction(() => document.querySelector('[data-lj-gaveta]').open, { timeout: 8000 }).catch(() => {});
await espera(700);
t('C02', 'sacola lateral mostra "2 de N peças"', (await texto('[data-lj-gaveta-pecas] p')).startsWith(`2 de ${MIN} peças`), await texto('[data-lj-gaveta-pecas] p'));
t('C03', 'sacola lateral esconde o "Finalizar compra"', await p.evaluate(() => document.querySelector('[data-lj-gaveta-finalizar]').hidden));
await espera(600);
await p.screenshot({ path: `${OUT}/publico-sacola-faltando.jpg`, quality: 74, type: 'jpeg' });
await ir(L + '/carrinho');
t('C04', 'carrinho mostra o contador e o que falta', (await texto('[data-lj-pecas] p')).includes(`faltam ${MIN - 2} peças`), await texto('[data-lj-pecas] p'));
t('C05', 'carrinho: "Finalizar compra" desligado', await p.evaluate(() => !!document.querySelector('aside [aria-disabled=true]') && !document.querySelector('aside a[href$="/finalizar"]')));
await foto('publico-carrinho-faltando');
await ir(L + '/finalizar');
t('C06', 'abrir o fechamento direto volta para o carrinho com o aviso', p.url().endsWith('/carrinho') && (await texto('.lj-aviso--erro')).includes('pedido mínimo'), await texto('.lj-aviso--erro'));
aviso = await adicionar(P2, MIN - 2);
t('C07', 'completando com OUTRO produto o aviso de falta some', !aviso.includes('Falta'), aviso);
await ir(L + '/carrinho');
t('C08', 'carrinho: mínimo atingido misturando produtos', (await texto('[data-lj-pecas] p')).includes(`${MIN} de ${MIN} peças`) && (await texto('[data-lj-pecas] p')).includes('atingido'), await texto('[data-lj-pecas] p'));
t('C09', 'carrinho: "Finalizar compra" liberado', await p.evaluate(() => !!document.querySelector('aside a[href$="/finalizar"]')));
await foto('publico-carrinho-ok');
await ir(L + '/carrinho', 390, 844);
t('C10', 'carrinho 390 sem rolagem lateral', !(await lateral()));
await foto('publico-carrinho-celular', true);
r = await ir(L + '/finalizar');
t('C11', 'fechamento abre com o mínimo atingido', p.url().endsWith('/finalizar') && !!(await p.$('form [name=nome]')));
await foto('publico-finalizar');
await ir(L + '/finalizar', 390, 844);
t('C12', 'fechamento 390 sem rolagem lateral', !(await lateral()));

// ── D. painel do lojista ──
if (process.env.EMAIL) {
  await ir(BASE + '/login');
  await p.type('input[name=email]', process.env.EMAIL); await p.type('input[name=password]', process.env.SENHA || 'teste123');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  if (p.url().includes('selecionar-unidade')) await Promise.all([p.waitForNavigation(), p.click('form button[type=submit], form button')]);
  await ir(BASE + '/app/loja-online/configuracoes');
  t('D01', 'Configurações mostra o mínimo de peças gravado', (await p.evaluate(() => document.querySelector('#pecas_minimo')?.value)) === String(MIN));
  t('D02', 'Configurações mostra o preço de atacado ligado', await p.evaluate(() => document.querySelector('#preco_atacado')?.checked === true));
  await foto('painel-configuracoes');
  await ir(BASE + '/app/loja-online/editor');
  const ed = await p.evaluate(() => document.body.textContent);
  t('D03', 'editor oferece o modelo Cell Master Atacado e o bloco dele', ed.includes('Cell Master Atacado') && ed.includes('Como comprar no atacado'));
  await foto('painel-editor');
}
t('Z01', 'nenhum erro de JavaScript', erros.length === 0, erros.join(' | '));
console.log(`\n${ok} ✅ / ${ruim} ❌`);
await b.close();
process.exit(ruim ? 1 : 0);
