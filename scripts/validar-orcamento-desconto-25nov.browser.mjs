// Validação ✅/❌ no NAVEGADOR — desconto geral do orçamento em UM campo (25/11/2026).
// Ambiente DESCARTÁVEL (banco novo com o seed demo). $ANTES = código de produção, mesmo banco.
//   BASE=http://127.0.0.1:8098 ANTES=http://127.0.0.1:8097 MYSQL=od-mysql CHROME=/caminho/chrome-headless-shell \
//   NODE_PATH=/home/ubuntu/caixa-v2/node_modules PRINTS=/pasta node scripts/validar-orcamento-desconto-25nov.browser.mjs
import { createRequire } from 'module';
import { execFileSync } from 'child_process';
const require = createRequire(process.env.NODE_PATH + '/');
const puppeteer = require('puppeteer-core');

const BASE = process.env.BASE || 'http://127.0.0.1:8098';
const ANTES = process.env.ANTES || '';
const MYSQL = process.env.MYSQL || 'od-mysql';
const PRINTS = process.env.PRINTS || '';
let ok = 0, fail = 0;
const check = (nome, cond, obtido = '') => { cond ? ok++ : fail++; console.log((cond ? '✅ ' : '❌ ') + nome + (cond ? '' : `  (obtido: ${String(obtido).slice(0, 300)})`)); };
const nav = (page, acao) => Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), acao()]);
const print = async (page, nome) => { if (PRINTS) await page.screenshot({ path: `${PRINTS}/${nome}.png`, fullPage: true }); };
const sql = q => execFileSync('docker', ['exec', '-i', MYSQL, 'mysql', '--default-character-set=utf8mb4', '-uerp', '-perp', 'erp_teste', '-N', '-B'], { input: q }).toString().trim();
const sleep = ms => new Promise(r => setTimeout(r, ms));
const validade = new Date(Date.now() + 20 * 864e5).toISOString().slice(0, 10);
const ultimo = () => sql('SELECT subtotal, desconto_percentual, desconto_valor, frete_valor, total FROM orcamentos ORDER BY id DESC LIMIT 1').split('\t').map(Number);

const cliente = sql('SELECT id FROM clientes ORDER BY id LIMIT 1');
const produto = sql('SELECT id FROM produtos ORDER BY id LIMIT 1');
sql('DELETE FROM orcamento_itens; DELETE FROM orcamentos;');

const browser = await puppeteer.launch({ executablePath: process.env.CHROME, args: ['--no-sandbox'] });
const erros = [];

// Cada servidor num contexto próprio: mesma máquina em portas diferentes divide os cookies
// e um login derrubaria o outro.
async function entrar(base) {
    const page = await (await browser.createBrowserContext()).newPage();
    await page.setViewport({ width: 1366, height: 900 });
    page.on('pageerror', e => erros.push(base + ' ' + e.message));
    page.on('console', m => { if (m.type() === 'error' && !/favicon|ERR_|Failed to load resource/.test(m.text())) erros.push(base + ' ' + m.text()); });
    page.on('dialog', d => d.dismiss());
    await page.goto(base + '/login', { waitUntil: 'networkidle2' });
    await page.type('input[name=email]', 'dono@demo.com');
    await page.type('input[name=password]', 'dono123');
    await nav(page, () => page.click('button[type=submit]'));
    if (page.url().includes('selecionar-unidade')) {
        await nav(page, () => page.evaluate(() => { const f = document.querySelector('form[action*="selecionar-unidade"]') || document.querySelector('main form, form'); (f.querySelector('button[type=submit], button')).click(); }));
    }
    return page;
}

// Preenche cliente e UM item (qtd × preço) direto nos campos da linha, como a busca faria.
async function montar(page, qtd, preco) {
    await page.evaluate((cliente, produto, qtd, preco, validade) => {
        document.getElementById('clienteId').value = cliente;
        document.querySelector('[name=validade_ate]').value = validade;
        const row = document.querySelector('#itensBody tr');
        row.querySelector('.produto-id').value = produto;
        row.querySelector('.item-descricao').value = 'Item de teste';
        row.querySelector('.produto-busca').value = 'Item de teste';
        row.querySelector('.item-qtd').value = qtd;
        row.querySelector('.item-preco').value = preco;
        window.calcularTotaisOrc();
    }, cliente, produto, qtd, preco, validade);
}
async function desconto(page, tipo, valor) {
    await page.select('#descontoTipo', tipo);
    await page.evaluate(v => { const c = document.getElementById('descontoGeral'); c.value = v; c.dispatchEvent(new Event('input', { bubbles: true })); }, String(valor));
}
const tela = page => page.evaluate(() => ({
    total: document.getElementById('totalDisplay').textContent.trim(),
    ajuda: document.getElementById('descontoAjuda').textContent.trim(),
    perc: document.getElementById('descontoPerc').value,
    valor: document.getElementById('descontoValor').value,
    previa: document.getElementById('quadroPagamentoPrevia').innerText,
}));
const salvar = page => nav(page, () => page.evaluate(() => document.querySelector('form button[type=submit].btn-primary').click()));
// POST direto, sem a tela (o que um formulário antigo ou adulterado mandaria)
const postDireto = (page, base, campos) => page.evaluate(async (base, campos, cliente, produto, validade) => {
    const fd = new URLSearchParams({
        _token: document.querySelector('meta[name=csrf-token]')?.content || document.querySelector('input[name=_token]').value,
        cliente_id: cliente, validade_ate: validade,
        'itens[0][produto_id]': produto, 'itens[0][quantidade]': '2', 'itens[0][preco_unitario]': '100', ...campos,
    });
    const r = await fetch(base + '/app/orcamentos', { method: 'POST', body: fd, redirect: 'manual', credentials: 'same-origin' });
    return r.status || r.type;
}, base, campos, cliente, produto, validade);

const page = await entrar(BASE);

console.log('== 1. A tela nova: um campo só');
await page.goto(BASE + '/app/orcamentos/create', { waitUntil: 'networkidle2' });
const campos = await page.evaluate(() => ({
    visiveis: [...document.querySelectorAll('label')].filter(l => /^Desconto/.test(l.textContent.trim())).map(l => l.textContent.trim()),
    tipos: [...document.querySelectorAll('#descontoTipo option')].map(o => o.textContent.trim()),
    ocultos: ['descontoPerc', 'descontoValor'].map(i => document.getElementById(i)?.type),
    texto: document.body.innerText,
}));
check('1.1 um campo "Desconto no orçamento" (os dois antigos saíram)', campos.visiveis.filter(t => t !== 'Desconto Dinheiro/PIX').join('|') === 'Desconto no orçamento', campos.visiveis);
check('1.2 escolha "Em %" / "Em R$"', campos.tipos.join('|') === 'Em %|Em R$', campos.tipos);
check('1.3 percentual e valor seguem no formulário como campos ocultos', campos.ocultos.join('|') === 'hidden|hidden', campos.ocultos);
check('1.4 o desconto de Dinheiro/PIX avisa que não muda o TOTAL', campos.texto.includes('não muda o TOTAL'));

console.log('== 2. Desconto em %');
await montar(page, 2, 100);
await desconto(page, 'percentual', 10);
let t = await tela(page);
check('2.1 10% de 200 → TOTAL R$ 180,00', t.total === 'R$ 180,00', t.total);
check('2.2 a ajuda mostra quanto sai em reais', t.ajuda.includes('R$ 20,00'), t.ajuda);
await print(page, '01-desconto-percentual');
await desconto(page, 'percentual', '');
t = await tela(page);
check('2.3 apagou o % → desconto some (antes o R$ ficava preso)', t.total === 'R$ 200,00' && Number(t.valor) === 0, JSON.stringify(t));
await desconto(page, 'percentual', 150);
t = await tela(page);
check('2.4 150% vale 100% e avisa', t.total === 'R$ 0,00' && t.ajuda.includes('100%'), JSON.stringify(t));
await desconto(page, 'percentual', 10);
await salvar(page);
let o = ultimo();
check('2.5 salvo: subtotal 200, 10%, R$ 20, total 180', o.join('|') === '200|10|20|0|180', o);

console.log('== 3. Desconto em R$');
await page.goto(BASE + '/app/orcamentos/create', { waitUntil: 'networkidle2' });
await montar(page, 2, 100);
await desconto(page, 'valor', 30);
t = await tela(page);
check('3.1 R$ 30 de 200 → TOTAL R$ 170,00', t.total === 'R$ 170,00', t.total);
check('3.2 a ajuda mostra o equivalente em % (15%)', t.ajuda.includes('15%'), t.ajuda);
await print(page, '02-desconto-reais');
await desconto(page, 'valor', 500);
t = await tela(page);
check('3.3 R$ 500 em subtotal de 200 → vale R$ 200 e avisa', t.total === 'R$ 0,00' && Number(t.valor) === 200 && t.ajuda.includes('não passa do subtotal'), JSON.stringify(t));
await print(page, '03-desconto-maior-que-subtotal');
await page.evaluate(() => { document.getElementById('freteValor').value = 25; window.calcularTotaisOrc(); });
t = await tela(page);
check('3.4 a taxa de entrega não leva desconto: TOTAL R$ 25,00', t.total === 'R$ 25,00', t.total);
await desconto(page, 'valor', 30);
await salvar(page);
o = ultimo();
check('3.5 salvo: subtotal 200, 0%, R$ 30, entrega 25, total 195', o.join('|') === '200|0|30|25|195', o);

console.log('== 4. Desconto do orçamento + Dinheiro/PIX no quadro');
await page.goto(BASE + '/app/orcamentos/create', { waitUntil: 'networkidle2' });
await montar(page, 2, 100);
await desconto(page, 'percentual', 10);
await page.evaluate(() => {
    const p = document.getElementById('parcelasOrc'); p.value = '2'; p.dispatchEvent(new Event('change', { bubbles: true }));
    const d = document.getElementById('descontoAvistaOrc'); d.value = 5; d.dispatchEvent(new Event('input', { bubbles: true }));
});
t = await tela(page);
check('4.1 TOTAL segue R$ 180,00 (o de Dinheiro/PIX não mexe nele)', t.total === 'R$ 180,00', t.total);
check('4.2 quadro: Dinheiro/PIX R$ 171,00 (5% sobre os 180)', t.previa.includes('171,00'), t.previa);
await print(page, '04-desconto-e-pix');

console.log('== 5. Editar');
const idPerc = sql('SELECT id FROM orcamentos WHERE desconto_percentual = 10 ORDER BY id LIMIT 1');
await page.goto(`${BASE}/app/orcamentos/${idPerc}/edit`, { waitUntil: 'networkidle2' });
let e = await page.evaluate(() => ({ tipo: document.getElementById('descontoTipo').value, campo: document.getElementById('descontoGeral').value, total: document.getElementById('totalDisplay').textContent.trim() }));
check('5.1 orçamento com 10% abre em "Em %" com 10 e total 180', e.tipo === 'percentual' && Number(e.campo) === 10 && e.total === 'R$ 180,00', JSON.stringify(e));
const idValor = sql('SELECT id FROM orcamentos WHERE desconto_percentual = 0 AND desconto_valor = 30 ORDER BY id LIMIT 1');
await page.goto(`${BASE}/app/orcamentos/${idValor}/edit`, { waitUntil: 'networkidle2' });
e = await page.evaluate(() => ({ tipo: document.getElementById('descontoTipo').value, campo: document.getElementById('descontoGeral').value, total: document.getElementById('totalDisplay').textContent.trim() }));
check('5.2 orçamento com R$ 30 abre em "Em R$" com 30 e total 195', e.tipo === 'valor' && Number(e.campo) === 30 && e.total === 'R$ 195,00', JSON.stringify(e));
await print(page, '05-editar');
await desconto(page, 'percentual', 5);
await salvar(page);
o = sql(`SELECT subtotal, desconto_percentual, desconto_valor, frete_valor, total FROM orcamentos WHERE id = ${idValor}`).split('\t').map(Number);
check('5.3 trocou para 5% e salvou: R$ 10 de desconto, total 215', o.join('|') === '200|5|10|25|215', o);
await page.goto(`${BASE}/app/orcamentos/${idValor}/edit`, { waitUntil: 'networkidle2' });
await salvar(page);
const o2 = sql(`SELECT subtotal, desconto_percentual, desconto_valor, frete_valor, total FROM orcamentos WHERE id = ${idValor}`).split('\t').map(Number);
check('5.4 abrir e salvar sem mexer não muda nada', o2.join('|') === o.join('|'), o2);

console.log('== 6. Servidor (envio direto, sem a tela)');
await page.goto(BASE + '/app/orcamentos/create', { waitUntil: 'networkidle2' });
await postDireto(page, BASE, { desconto_valor: '500' });
o = ultimo();
check('6.1 R$ 500 em subtotal de 200 → grava 200 e total 0 (nunca negativo)', o.join('|') === '200|0|200|0|0', o);
await postDireto(page, BASE, { desconto_percentual: '10', desconto_valor: '150' });
o = ultimo();
check('6.2 10% com valor adulterado (150) → servidor refaz: R$ 20, total 180', o.join('|') === '200|10|20|0|180', o);
await postDireto(page, BASE, { desconto_valor: '10' });
o = ultimo();
check('6.3 formulário antigo só com desconto_valor=10 segue valendo: total 190', o.join('|') === '200|0|10|0|190', o);
await postDireto(page, BASE, {});
o = ultimo();
check('6.4 sem desconto nenhum: total 200', o.join('|') === '200|0|0|0|200', o);
await postDireto(page, BASE, { desconto_valor: '500', frete_valor: '40' });
o = ultimo();
check('6.5 desconto maior que o subtotal + entrega 40 → total 40 (entrega inteira)', o.join('|') === '200|0|200|40|40', o);

if (ANTES) {
    console.log('== 7. Antes × depois (código de produção no mesmo banco)');
    const pa = await entrar(ANTES);
    await pa.goto(ANTES + '/app/orcamentos/create', { waitUntil: 'networkidle2' });
    await postDireto(pa, ANTES, { desconto_valor: '500' });
    o = ultimo();
    check('7.1 ANTES o mesmo envio gravava total negativo (-300): defeito confirmado', o[4] === -300, o);
    await postDireto(pa, ANTES, { desconto_valor: '10' });
    const a = ultimo();
    await postDireto(page, BASE, { desconto_valor: '10' });
    const d = ultimo();
    check('7.2 desconto normal em R$: mesmo resultado antes e depois', a.join('|') === d.join('|'), a + ' × ' + d);
    await postDireto(pa, ANTES, { desconto_percentual: '10', desconto_valor: '20' });
    const a2 = ultimo();
    await postDireto(page, BASE, { desconto_percentual: '10', desconto_valor: '20' });
    const d2 = ultimo();
    check('7.3 desconto normal em %: mesmo resultado antes e depois', a2.join('|') === d2.join('|'), a2 + ' × ' + d2);
    for (const u of ['/app/orcamentos', '/app/orcamentos/create', `/app/orcamentos/${idPerc}`, `/app/orcamentos/${idPerc}/imprimir`, `/app/orcamentos/${idPerc}/edit`]) {
        const [sa, sd] = [await pa.goto(ANTES + u), await page.goto(BASE + u)].map(r => r.status());
        const naTela = pa.url().endsWith(u) && page.url().endsWith(u);
        check(`7.4 ${u}: antes ${sa} = depois ${sd}`, sa === sd && sd === 200 && naTela, sa + '/' + sd + ' ' + pa.url() + ' ' + page.url());
    }
}

console.log('== 8. Celular (390 px)');
await page.setViewport({ width: 390, height: 844 });
const rc = await page.goto(BASE + '/app/orcamentos/create', { waitUntil: 'networkidle2' });
await sleep(300);
if (!(await page.$('#descontoGeral'))) console.log('   tela não abriu:', rc.status(), page.url(), (await page.evaluate(() => document.body.innerText)).slice(0, 400));
const larg = await page.evaluate(() => ({ doc: document.documentElement.scrollWidth, win: window.innerWidth, campo: document.getElementById('descontoGeral').getBoundingClientRect().width }));
check('8.1 sem rolagem lateral e campo de desconto utilizável', larg.doc <= larg.win + 1 && larg.campo > 120, JSON.stringify(larg));
await print(page, '06-celular');

check('9 nenhum erro de JavaScript no console', erros.length === 0, erros.join(' | '));
await browser.close();
console.log(`\n${ok} ✅ / ${fail} ❌`);
process.exit(fail ? 1 : 0);
