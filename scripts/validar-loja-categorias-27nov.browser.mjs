// Validação no navegador — categorias da vitrine montadas pelo lojista (27/11/2026).
// Precisa de uma loja NO AR com produtos marcados em pelo menos 3 categorias do cadastro e NENHUMA categoria da loja criada.
//   BASE=http://10.213.91.4 SLUG=n-s-borba-servicos EMAIL=dono-cm@teste.local SENHA=teste123
//   A=miniatura B=chaveiro   (pedaços de nome de categorias do cadastro que têm produto na loja)
//   CHROME=/caminho/chrome-headless-shell NODE_PATH=/home/ubuntu/caixa-v2/node_modules PRINTS=/pasta \
//   node scripts/validar-loja-categorias-27nov.browser.mjs
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const puppeteer = require('puppeteer-core');
const BASE = process.env.BASE || 'http://10.213.91.4', SLUG = process.env.SLUG || 'n-s-borba-servicos';
const L = BASE + '/loja/' + SLUG, PAINEL = BASE + '/app/loja-online/categorias', OUT = process.env.PRINTS || '.';
const A = (process.env.A || 'miniatura').toLowerCase(), B = (process.env.B || 'chaveiro').toLowerCase();
let ok = 0, ruim = 0;
const t = (id, texto, passou, extra = '') => { passou ? ok++ : ruim++; console.log(`${passou ? '✅' : '❌'} ${id} ${texto}${extra ? ' — ' + extra : ''}`); };
const espera = ms => new Promise(r => setTimeout(r, ms));
const b = await puppeteer.launch({ executablePath: process.env.CHROME, args: ['--no-sandbox'] });
// dois contextos: o lojista logado no painel e o público na loja (cookies separados)
const painel = await (await b.createBrowserContext()).newPage();
const loja = await (await b.createBrowserContext()).newPage();
const erros = [];
painel.on('pageerror', e => erros.push('painel: ' + e.message));
loja.on('pageerror', e => erros.push('loja: ' + e.message));
await painel.setViewport({ width: 1366, height: 900 });
await loja.setViewport({ width: 1366, height: 900 });
const lateral = p => p.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
const menuLoja = async () => { await loja.goto(L, { waitUntil: 'networkidle0' }); return loja.evaluate(() => [...document.querySelectorAll('.lj-mega__painel a strong')].map(e => e.textContent.trim())); };
const blocoLoja = () => loja.evaluate(() => [...document.querySelectorAll('.lj-cat strong, .lj-circulos strong')].map(e => e.childNodes[0].textContent.trim()));
const linhas = () => painel.evaluate(() => [...document.querySelectorAll('table tbody tr')].map(tr => ({ nome: tr.children[2].textContent.trim(), total: parseInt(tr.children[4].textContent, 10), situacao: tr.children[5].textContent.trim() })));
const criar = async (nome, pedaco, produto = null) => {
  await painel.goto(PAINEL, { waitUntil: 'networkidle0' });
  await painel.type('#nome', nome);
  await painel.type('#filtroCat', pedaco);
  const visiveis = await painel.evaluate(() => [...document.querySelectorAll('#filtroCat ~ .lj-escolha .lj-escolha__item')].filter(i => !i.hidden).length);
  await painel.evaluate(() => [...document.querySelectorAll('#filtroCat ~ .lj-escolha .lj-escolha__item')].filter(i => !i.hidden).forEach(i => i.querySelector('input').click()));
  if (produto) {
    await painel.type('#filtroProd', produto);
    await painel.evaluate(() => { const i = [...document.querySelectorAll('#filtroProd ~ .lj-escolha .lj-escolha__item')].find(x => !x.hidden); if (i) i.querySelector('input').click(); });
  }
  await Promise.all([painel.waitForNavigation({ waitUntil: 'networkidle0' }), painel.click('form[enctype] button[type=submit]')]);
  return visiveis;
};
const acao = async (seletor) => { await Promise.all([painel.waitForNavigation({ waitUntil: 'networkidle0' }), painel.evaluate(s => document.querySelector(s).click(), seletor)]); };

// ── login ──
await painel.goto(BASE + '/login', { waitUntil: 'networkidle0' });
await painel.type('input[name=email]', process.env.EMAIL || 'dono-cm@teste.local'); await painel.type('input[name=password]', process.env.SENHA || 'teste123');
await Promise.all([painel.waitForNavigation(), painel.click('button[type=submit]')]);
if (painel.url().includes('selecionar-unidade')) await Promise.all([painel.waitForNavigation(), painel.click('form button[type=submit], form button')]);

// ── A. antes: a loja usa as categorias do cadastro ──
const r = await painel.goto(PAINEL, { waitUntil: 'networkidle0' });
t('A01', 'tela Categorias abre no painel da loja', r.status() === 200 && (await painel.$('#abasLojaOnline a.active'))  !== null && (await painel.evaluate(() => document.querySelector('#abasLojaOnline a.active').textContent.trim())) === 'Categorias');
t('A02', 'sem categoria montada, a tela diz que a loja usa o cadastro', (await painel.evaluate(() => document.body.innerText)).includes('Nenhuma categoria montada'));
const antes = await menuLoja();
t('A03', 'a loja mostra as categorias do cadastro', antes.length >= 3 && antes.some(n => n.toLowerCase().includes(A)), antes.length + ' categorias');
await painel.screenshot({ path: `${OUT}/categorias-painel-vazio.jpg`, type: 'jpeg', quality: 74, fullPage: true });

// ── B. cria duas categorias da loja ──
const achadas = await criar('Perfumes e miniaturas', A, 'a');
t('B01', `o filtro "${A}" acha categorias do cadastro`, achadas >= 1, achadas + ' achadas');
let ls = await linhas();
t('B02', 'categoria criada aparece na lista, ligada, com produtos', ls.length === 1 && ls[0].nome === 'Perfumes e miniaturas' && ls[0].total > 0 && ls[0].situacao === 'Ligada', JSON.stringify(ls));
t('B03', 'aviso de sucesso explica que a loja passa a mostrar só as desta tela', (await painel.evaluate(() => document.body.innerText)).includes('só as categorias desta tela'));
await criar('Brindes e chaveiros', B);
ls = await linhas();
t('B04', 'segunda categoria criada, depois da primeira', ls.length === 2 && ls[1].nome === 'Brindes e chaveiros' && ls[1].total > 0, JSON.stringify(ls));
t('B05', 'a tela avisa quantos produtos ficaram fora de categoria', /não est(á|ão) em nenhuma categoria/.test(await painel.evaluate(() => document.body.innerText)));
t('B06', 'painel 1366 sem rolagem lateral', !(await lateral(painel)));
await painel.screenshot({ path: `${OUT}/categorias-painel.jpg`, type: 'jpeg', quality: 74, fullPage: true });

// ── C. a loja mostra só as duas, na ordem ──
let menu = await menuLoja();
t('C01', 'menu da loja mostra só as duas categorias, na ordem', JSON.stringify(menu) === JSON.stringify(['Perfumes e miniaturas', 'Brindes e chaveiros']), JSON.stringify(menu));
t('C02', 'bloco "Compre por categoria" mostra as mesmas duas', JSON.stringify(await blocoLoja()) === JSON.stringify(menu), JSON.stringify(await blocoLoja()));
await loja.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 250) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 140)); } window.scrollTo(0, 0); });
await espera(700);
await loja.screenshot({ path: `${OUT}/categorias-loja-inicio.jpg`, type: 'jpeg', quality: 74, fullPage: true });
await Promise.all([loja.waitForNavigation({ waitUntil: 'networkidle0' }), loja.evaluate(() => document.querySelector('.lj-cat, .lj-circulos a').click())]);
const pagina = await loja.evaluate(() => ({ url: location.search, h1: document.querySelector('h1').textContent.trim(), cartoes: document.querySelectorAll('.lj-card').length, etiquetas: [...new Set([...document.querySelectorAll('.lj-card__cat')].map(e => e.textContent.trim()))] }));
t('C03', 'abrir a categoria lista os produtos dela, com o nome da loja no título', /categoria=-\d+/.test(pagina.url) && pagina.h1.toLowerCase() === 'perfumes e miniaturas' && pagina.cartoes > 0, JSON.stringify(pagina));
t('C04', 'a etiqueta do cartão usa o nome da categoria da loja', pagina.etiquetas.length === 1 && pagina.etiquetas[0].toLowerCase() === 'perfumes e miniaturas', JSON.stringify(pagina.etiquetas));
t('C05', 'lista da categoria 1366 sem rolagem lateral', !(await lateral(loja)));
await loja.screenshot({ path: `${OUT}/categorias-loja-lista.jpg`, type: 'jpeg', quality: 74 });
await loja.goto(L + '/produtos', { waitUntil: 'networkidle0' });
const todos = await loja.evaluate(() => parseInt((document.body.innerText.match(/(\d+) produtos/) || [])[1] || '0', 10));
t('C06', '"Todos os produtos" continua com a loja inteira', todos > pagina.cartoes, todos + ' produtos');

// ── D. ordem, desligar, editar ──
await painel.goto(PAINEL, { waitUntil: 'networkidle0' });
await acao('table tbody tr:nth-child(2) button[aria-label^="Subir"]');
menu = await menuLoja();
t('D01', 'subir a segunda troca a ordem na loja', menu[0] === 'Brindes e chaveiros' && menu[1] === 'Perfumes e miniaturas', JSON.stringify(menu));
await acao('table tbody tr:nth-child(1) form[action*="/categorias/"] button.btn-outline-success');
await menuLoja(); menu = await blocoLoja(); // com uma categoria só o menu "Todas as categorias" não aparece: confere pelo bloco
t('D02', 'desligar tira a categoria da loja', JSON.stringify(menu) === JSON.stringify(['Perfumes e miniaturas']), JSON.stringify(menu));
await acao('table tbody tr:nth-child(1) form[action*="/categorias/"] button.btn-outline-secondary:not([aria-label])');
await Promise.all([painel.waitForNavigation({ waitUntil: 'networkidle0' }), painel.evaluate(() => document.querySelector('table tbody tr:nth-child(2) a.btn-outline-primary').click())]);
t('D03', 'editar abre o formulário com o nome e o que estava marcado', (await painel.$eval('#nome', e => e.value)) === 'Perfumes e miniaturas' && (await painel.evaluate(() => document.querySelectorAll('.lj-escolha input:checked').length)) >= 2);
await painel.evaluate(() => { document.querySelector('#nome').value = ''; });
await painel.type('#nome', 'Perfumaria');
await Promise.all([painel.waitForNavigation({ waitUntil: 'networkidle0' }), painel.click('form[enctype] button[type=submit]')]);
menu = await menuLoja();
t('D04', 'o nome novo aparece na loja', menu.includes('Perfumaria') && !menu.includes('Perfumes e miniaturas'), JSON.stringify(menu));

// ── E. celular ──
await painel.setViewport({ width: 390, height: 844, deviceScaleFactor: 2 });
await painel.goto(PAINEL, { waitUntil: 'networkidle0' });
t('E01', 'painel 390 sem rolagem lateral da página', !(await lateral(painel)));
await painel.screenshot({ path: `${OUT}/categorias-painel-celular.jpg`, type: 'jpeg', quality: 74, fullPage: true });
await loja.setViewport({ width: 390, height: 844, deviceScaleFactor: 2 });
await loja.goto(L, { waitUntil: 'networkidle0' });
t('E02', 'loja 390 sem rolagem lateral com as categorias da loja', !(await lateral(loja)));
await painel.setViewport({ width: 1366, height: 900 });
await loja.setViewport({ width: 1366, height: 900 });

// ── F. excluir tudo: a loja volta ao cadastro ──
for (let i = 0; i < 2; i++) {
  await painel.goto(PAINEL, { waitUntil: 'networkidle0' });
  await painel.evaluate(() => document.querySelector('table tbody tr button.btn-outline-danger').click());
  await painel.waitForSelector('.modal.show', { timeout: 6000 });
  await espera(400);
  await Promise.all([painel.waitForNavigation({ waitUntil: 'networkidle0' }), painel.evaluate(() => { const m = document.querySelector('.modal.show'); (m.querySelector('.btn-danger, .btn-primary, [data-confirm-ok]') || [...m.querySelectorAll('button')].pop()).click(); })]);
}
t('F01', 'excluídas as duas, a lista do painel fica vazia', (await linhas()).length === 0);
menu = await menuLoja();
t('F02', 'a loja volta às categorias do cadastro, iguais às de antes', JSON.stringify(menu) === JSON.stringify(antes), menu.length + ' categorias');
t('Z01', 'nenhum erro de JavaScript', erros.length === 0, erros.join(' | '));
console.log(`\n${ok} ✅ / ${ruim} ❌`);
await b.close();
process.exit(ruim ? 1 : 0);
