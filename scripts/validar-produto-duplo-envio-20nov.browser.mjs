// Validação ✅/❌ no NAVEGADOR — duplo envio do cadastro de produto (20/11/2026, chamado da Stilo Vinte).
// Dois cliques no "Salvar Produto" mandavam o formulário duas vezes e o segundo ia com o preço 100×
// (o campo já estava convertido para "165.00" e era lido de novo como "16500").
//
// Não precisa de login nem grava nada: serve o HTML da tela já renderizado + o public/ da cópia a testar
// e conta os envios num servidor local que demora para responder (como o de verdade).
//
//   HTML=/caminho/create.html [HTML_EDIT=/caminho/edit.html] PUB=/caminho/da/copia/public \
//   CHROME=/caminho/chrome-headless-shell NODE_PATH=/home/ubuntu/caixa-v2/node_modules \
//     node scripts/validar-produto-duplo-envio-20nov.browser.mjs
//
// O HTML sai de um ambiente com banco (homologação), pelo tinker:
//   Auth::login(User::withoutGlobalScopes()->find(ID)); sessão com unidade_id;
//   app(Kernel::class)->handle(Request::create('/app/produtos/create')) → getContent()
// Rodar com PUB da main = "antes" (❌ nos envios duplos); com PUB da branch = "depois".
import { createRequire } from 'module';
import http from 'http'; import fs from 'fs'; import path from 'path';
const require = createRequire(process.env.NODE_PATH + '/');
const puppeteer = require('puppeteer-core');

const PUB = process.env.PUB;
const PORTA = Number(process.env.PORTA || 8377);
const BASE = `http://127.0.0.1:${PORTA}`;
const ler = f => fs.readFileSync(f, 'utf8').replaceAll('http://localhost', '');
const paginas = { '/app/produtos/create': ler(process.env.HTML) };
if (process.env.HTML_EDIT) paginas['/app/produtos/editar'] = ler(process.env.HTML_EDIT);

let posts = [];
const srv = http.createServer((q, r) => {
    const u = q.url.split('?')[0];
    if (q.method === 'POST') {
        let b = ''; q.on('data', d => b += d.toString('latin1'));
        q.on('end', () => {
            const g = n => (b.match(new RegExp('name="' + n + '"\\r\\n\\r\\n([^\\r]*)')) || [])[1];
            posts.push({ venda: g('preco_venda'), custo: g('preco_custo') });
            setTimeout(() => { r.writeHead(302, { location: '/salvo' }); r.end(); }, 1500);
        });
        return;
    }
    if (paginas[u]) { r.writeHead(200, { 'content-type': 'text/html; charset=utf-8' }); return r.end(paginas[u]); }
    const f = path.join(PUB, u);
    if (f.startsWith(PUB) && fs.existsSync(f) && fs.statSync(f).isFile()) {
        r.writeHead(200, { 'content-type': u.endsWith('.js') ? 'text/javascript' : u.endsWith('.css') ? 'text/css' : 'application/octet-stream' });
        return r.end(fs.readFileSync(f));
    }
    r.writeHead(200, { 'content-type': 'text/html' }); r.end('<h1>ok</h1>');
}).listen(PORTA, '127.0.0.1');

let ok = 0, fail = 0;
const check = (nome, cond, obtido = '') => { cond ? ok++ : fail++; console.log((cond ? '✅ ' : '❌ ') + nome + (cond ? '' : `  (obtido: ${String(obtido).slice(0, 300)})`)); };
const sleep = ms => new Promise(r => setTimeout(r, ms));
const resumo = () => `${posts.length} envio(s): ` + posts.map(p => `venda=${p.venda} custo=${p.custo}`).join(' || ');

const browser = await puppeteer.launch({ executablePath: process.env.CHROME, args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 1366, height: 900 });
const erros = [];
page.on('pageerror', e => erros.push(e.message));

async function abrir() {
    posts = [];
    await page.goto(BASE + '/app/produtos/create', { waitUntil: 'networkidle2' });
    await page.type('#descricao', 'TESTE DUPLO ENVIO');
    await page.click('.unidade-card');
    await page.click('#preco_venda', { clickCount: 3 });
    await page.type('#preco_venda', '165009');      // a sequência da Jamily: um dígito a mais...
    await page.keyboard.press('Backspace');         // ...apaga, fica 165,00
    await page.click('#preco_custo', { clickCount: 3 });
    await page.type('#preco_custo', '7500');
}
async function etapa3() {
    await page.click('#wizardStep1 .wizard-next'); await sleep(500);
    await page.click('#wizardStep2 .wizard-next'); await sleep(500);
}
const centro = async sel => { const b = (await page.$$(sel)).pop(); const bx = await b.boundingBox(); return [bx.x + bx.width / 2, bx.y + bx.height / 2]; };
const FINAL = '#wizardStep3 button[type=submit]:not([name])';

// ---- conversão do preço
await page.goto(BASE + '/app/produtos/create', { waitUntil: 'networkidle2' });
const conv = await page.evaluate(() => ['88,00', '88.00', '1.500,00', '1.500', '0.5', '16.500,00', '', '1234.56'].map(v => ERP.moneyToDecimal(v)));
check('C1 "88,00" → 88; "1.500,00" → 1500; "16.500,00" → 16500; vazio → 0', conv[0] === 88 && conv[2] === 1500 && conv[5] === 16500 && conv[6] === 0, conv);
check('C2 valor já convertido não multiplica: "88.00" → 88; "0.5" → 0.5; "1234.56" → 1234.56', conv[1] === 88 && conv[4] === 0.5 && conv[7] === 1234.56, conv);
check('C3 "1.500" (milhar sem centavos) continua 1500', conv[3] === 1500, conv[3]);

// ---- um clique
await abrir();
check('E1 dígito a mais + apagar deixa 165,00 na tela', await page.$eval('#preco_venda', e => e.value) === '165,00');
await etapa3(); { const [x, y] = await centro(FINAL); await page.mouse.click(x, y); } await sleep(2500);
check('E2 um clique no Salvar Produto = 1 envio com 165.00 / 75.00', posts.length === 1 && posts[0].venda === '165.00' && posts[0].custo === '75.00', resumo());

// ---- dois cliques no botão final (o caso do chamado)
await abrir(); await etapa3();
const [fx, fy] = await centro(FINAL);
// o estado dos botões é anotado pela própria página 40 ms depois do envio e lido na página seguinte
await page.evaluate(() => window.addEventListener('submit', () => setTimeout(() => {
    const bs = [...document.querySelectorAll('#formProduto button[type=submit]')];
    const fim = [...document.querySelectorAll('#wizardStep3 button[type=submit]:not([name])')].pop();
    sessionStorage.setItem('duplo', JSON.stringify({ travados: bs.every(b => b.disabled), girando: !!fim.querySelector('.spinner-border') }));
}, 40), { once: true }));
await page.mouse.click(fx, fy); await sleep(120);
await page.mouse.click(fx, fy).catch(() => {});
await sleep(2500);
const { travados, girando } = JSON.parse(await page.evaluate(() => sessionStorage.getItem('duplo')) || '{}');
check('D0 o botão tocado (Salvar Produto) mostra que está salvando', girando === true, girando);
check('D1 dois cliques seguidos no Salvar Produto = 1 envio só', posts.length === 1, resumo());
check('D2 nenhum envio saiu com o preço multiplicado', posts.every(p => p.venda === '165.00' && p.custo === '75.00'), resumo());
check('D3 depois do 1º clique todos os botões de salvar ficam desabilitados', travados === true, travados);

// ---- três cliques rápidos
await abrir(); await etapa3();
{ const [x, y] = await centro(FINAL); for (let i = 0; i < 3; i++) { await page.mouse.click(x, y).catch(() => {}); await sleep(90); } }
await sleep(2500);
check('D4 três cliques rápidos = 1 envio só', posts.length === 1 && posts[0].venda === '165.00', resumo());

// ---- salvar rápido da etapa 1, dois cliques
await abrir();
{ const b = await page.$('button[name=quick_save]'); await b.click(); await sleep(120); await b.click().catch(() => {}); }
await sleep(2500);
check('D5 dois cliques no salvar rápido (etapa 1) = 1 envio só', posts.length === 1 && posts[0].venda === '165.00', resumo());

// ---- Enter no campo e depois clique no botão final
await abrir(); await etapa3();
{ const [x, y] = await centro(FINAL); await page.evaluate(() => document.getElementById('preco_venda').focus()); await page.keyboard.press('Enter'); await sleep(150); await page.mouse.click(x, y).catch(() => {}); }
await sleep(2500);
check('D6 Enter no preço + clique no Salvar Produto = 1 envio só', posts.length === 1 && posts[0].venda === '165.00', resumo());

// ---- reenvio forçado por código enquanto o 1º está no ar
await abrir();
const reenvio = await page.evaluate(async () => {
    const f = document.getElementById('formProduto');
    f.requestSubmit();
    await new Promise(r => setTimeout(r, 50));
    f.requestSubmit();
    return document.getElementById('preco_venda').value;
});
await sleep(2500);
check('D7 reenvio por código (requestSubmit 2×) = 1 envio só e o campo segue 165.00', posts.length === 1 && reenvio === '165.00', resumo() + ' campo=' + reenvio);

// ---- envio cancelado pela própria tela não trava o formulário
await abrir();
await page.evaluate(() => { const f = document.getElementById('formProduto'); f.addEventListener('submit', e => e.preventDefault(), { once: true }); f.requestSubmit(); });
await sleep(300);
const livre = await page.evaluate(() => document.getElementById('formProduto').dataset.erpEnviando !== '1');
check('L1 envio cancelado (validação da tela) não deixa o formulário travado', posts.length === 0 && livre, resumo() + ' livre=' + livre);
await page.evaluate(() => document.getElementById('formProduto').requestSubmit()); await sleep(2500);
check('L2 e o envio seguinte sai normalmente, com o preço certo', posts.length === 1 && posts[0].venda === '165.00', resumo());

// ---- tela de editar (opcional): dois cliques não regravam o preço 100×
if (process.env.HTML_EDIT) {
    posts = [];
    await page.goto(BASE + '/app/produtos/editar', { waitUntil: 'networkidle2' });
    const antes = await page.$eval('[name=preco_venda]', e => e.value);
    const esperado = await page.evaluate(v => ERP.moneyToDecimal(v).toFixed(2), antes);
    const sel = 'form button[type=submit]:not([data-no-loading])';
    const alvo = await page.evaluateHandle(() => [...document.querySelectorAll('form')].find(f => f.querySelector('[name=preco_venda]')).querySelector('button[type=submit]'));
    const bx = await alvo.boundingBox();
    if (bx) { const x = bx.x + bx.width / 2, y = bx.y + bx.height / 2; await page.mouse.click(x, y); await sleep(120); await page.mouse.click(x, y).catch(() => {}); }
    else await page.evaluate(async () => { const f = [...document.querySelectorAll('form')].find(f => f.querySelector('[name=preco_venda]')); f.requestSubmit(); await new Promise(r => setTimeout(r, 120)); f.requestSubmit(); });
    await sleep(2500);
    check(`X1 editar produto: dois envios seguidos = 1 envio só, preço ${esperado} (tela mostrava ${antes})`, posts.length === 1 && posts[0].venda === esperado, resumo());
}

check('Z1 nenhum erro de JavaScript nas telas', erros.length === 0, erros.join(' | '));
console.log(`\n${ok} ✅ / ${fail} ❌`);
await browser.close(); srv.close();
process.exit(fail ? 1 : 0);
