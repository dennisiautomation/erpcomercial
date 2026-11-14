// Validação ✅/❌ no NAVEGADOR — LOJA ONLINE (14/11/2026). Ambiente DESCARTÁVEL.
// Rodar DEPOIS do .py (ele deixa a loja "demo" liberada, no ar, com 30 produtos na vitrine).
//   BASE=http://10.213.80.4 MYSQL=ec-mysql APP=ec-app CHROME=/caminho/chrome-headless-shell NODE_PATH=/home/ubuntu/caixa-v2/node_modules \
//   FOTOS=/pasta/das/fotos PRINTS=/pasta node scripts/validar-loja-online-14nov.browser.mjs
// Cobre o que só o navegador prova: os 6 modelos no computador e no celular (sem erro de JavaScript e
// sem rolagem lateral), botão "+" do cartão, galeria, quantidade, carrinho, fechamento com frete ao
// vivo, tela do PIX (QR desenhado, copiar, confirmação automática), editor visual (trocar modelo,
// cor, bloco, ordem, imagem, publicar) e o cadastro do produto (mais fotos + interruptor).
import { createRequire } from 'module';
import { execFileSync } from 'child_process';
import { readFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';
const require = createRequire(process.env.NODE_PATH + '/');
const puppeteer = require('puppeteer-core');

const BASE = process.env.BASE || 'http://10.213.80.4';
const MYSQL = process.env.MYSQL || 'ec-mysql';
const APP = process.env.APP || 'ec-app';
const PRINTS = process.env.PRINTS || '';
const FOTOS = process.env.FOTOS || '';
const AQUI = dirname(fileURLToPath(import.meta.url));
let ok = 0, fail = 0;
const check = (nome, cond, obtido = '') => { cond ? ok++ : fail++; console.log((cond ? '✅ ' : '❌ ') + nome + (cond ? '' : `  (obtido: ${String(typeof obtido === 'string' ? obtido : JSON.stringify(obtido)).slice(0, 300)})`)); };
const sql = q => execFileSync('docker', ['exec', '-i', MYSQL, 'mysql', '--default-character-set=utf8mb4', '-uerp', '-perp', 'erp_teste', '-N', '-B'], { input: q, stdio: ['pipe', 'pipe', 'ignore'] }).toString().trim();
const php = (arquivo, env = []) => execFileSync('docker', ['exec', '-i', ...env.flatMap(e => ['-e', e]), APP, 'php'], { input: readFileSync(join(AQUI, arquivo)), stdio: ['pipe', 'pipe', 'ignore'] }).toString().trim().split('\n').pop();
const artisan = (...a) => execFileSync('docker', ['exec', APP, 'php', 'artisan', ...a], { stdio: ['ignore', 'pipe', 'ignore'] }).toString();
const sleep = ms => new Promise(r => setTimeout(r, ms));
const nav = (page, acao) => Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), acao()]);

const browser = await puppeteer.launch({ executablePath: process.env.CHROME, args: ['--no-sandbox'] });
const erros = [];
async function abrir(largura = 1366, altura = 900) {
  const page = await browser.newPage();
  await page.setViewport({ width: largura, height: altura });
  page.on('pageerror', e => erros.push(e.message));
  page.on('console', m => { if (m.type() === 'error' && !/favicon|ERR_|Failed to load resource/.test(m.text())) erros.push(m.text()); });
  page.on('dialog', d => d.accept());
  return page;
}
const print = async (page, nome, full = true) => {
  if (!PRINTS) return;
  await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 50)); } window.scrollTo(0, 0); });
  await sleep(300);
  await page.screenshot({ path: `${PRINTS}/${nome}.png`, fullPage: full });
};
const sobraLateral = page => page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);

artisan('cache:clear');
php('validar-loja-online-14nov.pix.php', ['ACAO=desligar']);
sql("UPDATE lojas_online SET modelo='vitrine', tema=NULL, blocos=NULL, rascunho=NULL, publicada=1, estoque_id=NULL, config=JSON_SET(config,'$.pedido_minimo',0,'$.vender_sem_estoque',false,'$.entrega',true,'$.retirada',true) WHERE slug='demo'; UPDATE empresas SET exige_documento_cadastro=0 WHERE id=1;");
const PID = Number(sql("SELECT id FROM produtos WHERE descricao='Camisa Linho Areia'"));
const CAMINHO_P = `/loja/demo/p/${PID}-camisa-linho-areia`;

// ───────────────────── A. os 6 modelos, computador e celular ─────────────────────
console.log('\n== A. Os seis modelos renderizam, no computador e no celular ==');
const esperado = {
  vitrine: { titulo: 'Figtree', cartao: 'borda', foto: 'quadrada' }, atelie: { titulo: 'Bodoni Moda', cartao: 'limpo', foto: 'retrato' },
  circuito: { titulo: 'Archivo', cartao: 'sombra', foto: 'quadrada' }, feira: { titulo: 'Bricolage Grotesque', cartao: 'limpo', foto: 'quadrada' },
  mimo: { titulo: 'Fredoka', cartao: 'sombra', foto: 'quadrada' }, balcao: { titulo: 'Manrope', cartao: 'lista', foto: 'quadrada' },
};
for (const [modelo, esp] of Object.entries(esperado)) {
  sql(`UPDATE lojas_online SET modelo='${modelo}', tema=NULL, blocos=NULL WHERE slug='demo'`);
  const antes = erros.length;
  const d = await abrir(1366);
  await d.goto(BASE + '/loja/demo', { waitUntil: 'networkidle2' });
  const info = await d.evaluate(() => ({
    fonte: getComputedStyle(document.querySelector('.lj-banner h1, .lj-banner h2')).fontFamily,
    cartao: document.body.dataset.cartao, foto: document.body.dataset.foto,
    cards: document.querySelectorAll('.lj-card').length, imgs: [...document.querySelectorAll('.lj-mosaico img')].filter(i => i.complete && i.naturalWidth > 0).length,
    h1: document.querySelectorAll('h1').length,
    colunas: getComputedStyle(document.querySelector('.lj-grade')).gridTemplateColumns.split(' ').length,
  }));
  check(`A1 ${modelo}: fonte do título (${esp.titulo}), cartão "${esp.cartao}" e foto "${esp.foto}"`, info.fonte.includes(esp.titulo) && info.cartao === esp.cartao && info.foto === esp.foto, info);
  check(`A2 ${modelo}: produtos na página, mosaico com fotos carregadas, um único h1`, info.cards >= 4 && info.imgs >= 1 && info.h1 === 1, info);
  const topo = await d.evaluate(() => { const b = document.querySelector('.lj-banner'), m = b.querySelector('.lj-mosaico'), t = b.querySelector('h1').getBoundingClientRect(), r = m.getBoundingClientRect();
    return { estilo: [...b.classList].find(c => c.startsWith('lj-banner--')), altura: Math.round(b.getBoundingClientRect().height), aoLado: r.left > t.right - 40 && r.top < t.bottom, embaixo: r.top >= t.bottom, foco: getComputedStyle(document.body).getPropertyValue('--fonte-preco').length > 3 }; });
  const formaEsperada = { vitrine: t => t.aoLado, atelie: t => t.embaixo && t.estilo === 'lj-banner--editorial', circuito: t => t.aoLado, feira: t => t.embaixo && t.estilo === 'lj-banner--cheio', mimo: t => t.embaixo && t.estilo === 'lj-banner--centro', balcao: t => t.altura <= 300 }[modelo];
  check(`A2b ${modelo}: o topo tem a composição do modelo (${JSON.stringify(topo)})`, formaEsperada(topo), topo);
  check(`A3 ${modelo} no computador: sem rolagem lateral`, await sobraLateral(d) <= 1, await sobraLateral(d));
  await print(d, `${modelo}-inicio-computador`);
  await d.goto(BASE + CAMINHO_P, { waitUntil: 'networkidle2' });
  await print(d, `${modelo}-produto-computador`);
  await d.close();
  const m = await abrir(390, 844);
  for (const [rota, nome] of [['/loja/demo', 'início'], ['/loja/demo/produtos', 'lista'], [CAMINHO_P, 'produto']]) {
    await m.goto(BASE + rota, { waitUntil: 'networkidle2' });
    check(`A4 ${modelo} no celular (${nome}): sem rolagem lateral`, await sobraLateral(m) <= 1, await sobraLateral(m));
    if (nome === 'início') await print(m, `${modelo}-inicio-celular`);
  }
  const toque = await m.evaluate(() => [...document.querySelectorAll('.lj-btn, .lj-mais, .lj-icone')].filter(e => e.offsetParent).map(e => e.getBoundingClientRect()).filter(r => r.height < 38).length);
  check(`A5 ${modelo} no celular: botões com área de toque de pelo menos 38 px`, toque === 0, toque);
  await m.close();
  check(`A6 ${modelo}: nenhum erro de JavaScript`, erros.length === antes, erros.slice(antes));
}
sql("UPDATE lojas_online SET modelo='vitrine', tema=NULL, blocos=NULL WHERE slug='demo'");

// ───────────────────── B. comprar: cartão, produto, carrinho ─────────────────────
console.log('\n== B. Do cartão de produto ao carrinho ==');
const page = await abrir(1366);
await page.goto(BASE + '/loja/demo', { waitUntil: 'networkidle2' });
await page.click('.lj-card form[data-lj-add] button');
await page.waitForFunction(() => document.querySelector('[data-lj-conta]').textContent.trim() === '1', { timeout: 8000 }).catch(() => {});
const b1 = await page.evaluate(() => ({ conta: document.querySelector('[data-lj-conta]').textContent.trim(), escondido: document.querySelector('[data-lj-conta]').hidden,
  aviso: document.querySelector('[data-lj-brinde]').hidden ? '' : document.querySelector('[data-lj-brinde-texto]').textContent, url: location.pathname }));
check('B1 "+" do cartão: contador vai a 1, aviso aparece e a página não muda', b1.conta === '1' && !b1.escondido && /Adicionado/.test(b1.aviso) && b1.url === '/loja/demo', b1);
await page.goto(BASE + CAMINHO_P, { waitUntil: 'networkidle2' });
const largPalco = await page.evaluate(() => document.querySelector('[data-lj-palco]').clientWidth);
await page.click('[data-lj-mini="2"]');
await sleep(900);
const b2 = await page.evaluate(() => ({ rolou: document.querySelector('[data-lj-palco]').scrollLeft, atual: document.querySelector('[data-lj-mini="2"]').getAttribute('aria-current') }));
check('B2 galeria: clicar na 3ª miniatura leva à 3ª foto e marca a miniatura', Math.abs(b2.rolou - largPalco * 2) < 6 && b2.atual === 'true', { ...b2, largPalco });
await page.click('[data-lj-qtd] button[data-d="1"]');
await page.click('[data-lj-qtd] button[data-d="1"]');
await page.click('[data-lj-qtd] button[data-d="-1"]');
check('B3 quantidade: + + − deixa 2', await page.$eval('#lj-qtd', e => e.value) === '2');
for (let i = 0; i < 15; i++) await page.click('[data-lj-qtd] button[data-d="1"]');
check('B4 quantidade não passa do saldo (10)', await page.$eval('#lj-qtd', e => e.value) === '10');
await page.$eval('#lj-qtd', e => { e.value = '2'; });
await page.click('.lj-comprar button[type=submit]');
await page.waitForFunction(() => document.querySelector('[data-lj-conta]').textContent.trim() === '3', { timeout: 8000 }).catch(() => {});
check('B5 "Adicionar ao carrinho" com 2: contador vai a 3', await page.$eval('[data-lj-conta]', e => e.textContent.trim()) === '3');
await page.type('#lj-cep', '01001000');
await page.click('[data-lj-frete] button');
await page.waitForFunction(() => { const t = document.querySelector('[data-lj-frete-saida]').textContent.trim(); return t.length > 12 && !t.includes('Calculando'); }, { timeout: 15000 }).catch(() => {});
const frete = await page.$eval('[data-lj-frete-saida]', e => e.textContent.trim());
check('B6 calcular frete na página do produto responde na tela (sem integração: explica)', /combina a entrega|Não/.test(frete), frete);
await page.goto(BASE + '/loja/demo/carrinho', { waitUntil: 'networkidle2' });
const b7 = await page.evaluate(() => ({ linhas: document.querySelectorAll('.lj-item').length, qtd: document.querySelector('.lj-item [data-lj-qtd-campo]').value }));
check('B7 carrinho junta o mesmo produto numa linha só, com 3 unidades', b7.linhas === 1 && b7.qtd === '3', b7);
await nav(page, () => page.click('.lj-item button[aria-label^="Aumentar"]'));
const sub1 = await page.$eval('.lj-resumo .lj-total + .lj-total, .lj-resumo dd.lj-total', e => e.textContent.trim());
check('B8 "+" no carrinho recalcula o subtotal', /\d/.test(sub1) && await page.$eval('[data-lj-conta]', e => e.textContent.trim()) === '4', sub1);
await page.$eval('.lj-item [data-lj-qtd-campo]', e => { e.value = '1'; e.dispatchEvent(new Event('change', { bubbles: true })); });
await page.waitForNavigation({ waitUntil: 'networkidle2' });
check('B9 digitar a quantidade (1) e sair do campo atualiza o carrinho', await page.$eval('.lj-item [data-lj-qtd-campo]', e => e.value) === '1');
await print(page, 'carrinho-computador');

// ───────────────────── C. fechamento ─────────────────────
console.log('\n== C. Fechamento: frete ao vivo e pedido ==');
await nav(page, () => page.click('.lj-resumo a.lj-btn'));
check('C1 "Finalizar compra" abre o fechamento', page.url().endsWith('/loja/demo/finalizar'));
const c2 = await page.evaluate(() => ({ escondido: document.querySelector('[data-lj-endereco]').hidden, frete: document.querySelector('[data-lj-frete-valor]').textContent.trim() }));
check('C2 de início o endereço fica escondido e a entrega "—"', c2.escondido && c2.frete === '—', c2);
await page.click('input[name=entrega][value=entrega]');
check('C3 "Receber no endereço" abre os campos e pede o CEP', await page.$eval('[data-lj-endereco]', e => !e.hidden) && /informe o CEP/.test(await page.$eval('[data-lj-frete-valor]', e => e.textContent)));
await page.type('#f-cep', '01001000');
await page.waitForFunction(() => { const t = document.querySelector('[data-lj-fretes]').textContent.trim(); return t.length > 12 && !t.includes('Calculando'); }, { timeout: 15000 }).catch(() => {});
const c4 = await page.evaluate(() => ({ cep: document.querySelector('#f-cep').value, aviso: document.querySelector('[data-lj-fretes]').textContent.trim(), frete: document.querySelector('[data-lj-frete-valor]').textContent.trim() }));
check('C4 CEP ganha a máscara e a cotação responde sozinha; sem integração, entrega "a combinar"', c4.cep === '01001-000' && c4.aviso.length > 12 && c4.frete === 'a combinar', c4);
await page.click('input[name=entrega][value=retirada]');
const c5 = await page.evaluate(() => ({ escondido: document.querySelector('[data-lj-endereco]').hidden, frete: document.querySelector('[data-lj-frete-valor]').textContent.trim() }));
check('C5 voltar para "Retirar na loja" esconde o endereço e mostra "Grátis (retirada)"', c5.escondido && /Grátis/.test(c5.frete), c5);
await nav(page, () => page.click('[data-lj-enviar]'));
const c6 = await page.evaluate(() => ({ url: location.pathname, erros: [...document.querySelectorAll('.lj-erro')].map(e => e.textContent.trim()), invalido: document.querySelector('#f-nome').getAttribute('aria-invalid') }));
check('C6 fechar sem nome/telefone: volta com os erros ao lado dos campos', c6.url.endsWith('/finalizar') && c6.erros.length >= 2 && c6.invalido === 'true', c6);
await print(page, 'fechamento-com-erros-computador');
await page.type('#f-nome', 'Cliente do Navegador');
await page.type('#f-tel', '86977776666');
await page.type('#f-obs', 'Pedido feito pelo teste de navegador');
await page.click('input[name=entrega][value=retirada]');
await nav(page, () => page.click('[data-lj-enviar]'));
const c7 = await page.evaluate(() => ({ url: location.pathname, h1: document.querySelector('h1').textContent, conta: document.querySelector('[data-lj-conta]').hidden, etapas: document.querySelectorAll('.lj-etapas li[data-feito]').length }));
check('C7 pedido fechado: página do pedido, carrinho zerado, 1ª etapa feita', /\/pedido\/[a-z0-9]{32}$/.test(c7.url) && /Pedido #\d+/.test(c7.h1) && c7.conta && c7.etapas === 1, c7);
const ult = sql("SELECT canal, status, total FROM pedidos WHERE loja_token IS NOT NULL ORDER BY id DESC LIMIT 1").split('\t');
check('C8 no banco: pedido online, rascunho', ult[0] === 'online' && ult[1] === 'rascunho', ult);
await print(page, 'pedido-combinar-computador');

// celular
const cel = await abrir(390, 844);
await cel.goto(BASE + '/loja/demo', { waitUntil: 'networkidle2' });
await cel.evaluate(pid => LJ.post('/loja/demo/carrinho/adicionar', { produto_id: pid, quantidade: 1 }), PID);
for (const [rota, nome] of [['/loja/demo/carrinho', 'carrinho'], ['/loja/demo/finalizar', 'fechamento']]) {
  await cel.goto(BASE + rota, { waitUntil: 'networkidle2' });
  check(`C9 ${nome} no celular: sem rolagem lateral`, await sobraLateral(cel) <= 1, await sobraLateral(cel));
  await print(cel, `${nome}-celular`);
}
await cel.close();

// ───────────────────── D. PIX na tela ─────────────────────
console.log('\n== D. PIX: QR Code na tela e confirmação automática ==');
const pix = JSON.parse(php('validar-loja-online-14nov.pix.php'));
artisan('cache:clear');
await page.goto(`${BASE}/loja/demo/pedido/${pix.token}`, { waitUntil: 'networkidle2' });
const d1 = await page.evaluate(() => ({ pagar: !!document.querySelector('[data-lj-meio=pix]'), pixEscondido: document.querySelector('[data-lj-pix]').hidden, espera: !!document.querySelector('.lj-espera') }));
check('D1 pedido aguardando com PIX ligado: botão "Pagar com PIX" e "Aguardando o pagamento…"', d1.pagar && d1.pixEscondido && d1.espera, d1);
await page.click('[data-lj-meio=pix]');
await page.waitForFunction(() => !document.querySelector('[data-lj-pix]').hidden && document.querySelector('[data-lj-qr] svg'), { timeout: 15000 }).catch(() => {});
const d2 = await page.evaluate(() => ({ visivel: !document.querySelector('[data-lj-pix]').hidden, svg: !!document.querySelector('[data-lj-qr] svg'), modulos: document.querySelectorAll('[data-lj-qr] svg path, [data-lj-qr] svg rect').length,
  copia: document.querySelector('#lj-copia').value, lado: Math.round(document.querySelector('[data-lj-qr]').getBoundingClientRect().width),
  erroVisivel: document.querySelector('[data-lj-pagar-erro]').offsetParent !== null, botao: document.querySelector('[data-lj-meio=pix]').textContent.trim() }));
check('D2 clicar gera o PIX: QR Code desenhado na tela e o copia-e-cola preenchido', d2.visivel && d2.svg && d2.modulos > 0 && d2.copia.startsWith('00020126') && d2.lado >= 180, d2);
check('D2b sem erro, a caixa de erro NÃO aparece; o botão vira "Gerar o PIX de novo"', !d2.erroVisivel && d2.botao === 'Gerar o PIX de novo', d2);
await page.click('[data-lj-pix] [data-lj-copiar]');
await sleep(300);
check('D3 botão "Copiar" responde "Copiado!"', await page.$eval('[data-lj-pix] [data-lj-copiar]', e => e.textContent.trim()) === 'Copiado!');
await print(page, 'pedido-pix-computador');
const celPix = await abrir(390, 844);
await celPix.goto(`${BASE}/loja/demo/pedido/${pix.token}`, { waitUntil: 'networkidle2' });
await celPix.click('[data-lj-meio=pix]');
await celPix.waitForFunction(() => document.querySelector('[data-lj-qr] svg'), { timeout: 15000 }).catch(() => {});
check('D4 PIX no celular: QR visível e sem rolagem lateral', await celPix.evaluate(() => !!document.querySelector('[data-lj-qr] svg')) && await sobraLateral(celPix) <= 1);
await print(celPix, 'pedido-pix-celular');
await celPix.close();
// o pagamento "cai" (o que o webhook faria): a tela, que pergunta de 6 em 6 s, recarrega sozinha
sql(`UPDATE pedido_cobrancas SET status='CONCLUIDA', pago_em=NOW() WHERE pedido_id=${pix.pedido}; UPDATE pedidos SET status='confirmado' WHERE id=${pix.pedido};`);
await page.waitForFunction(() => document.querySelector('[data-lj-pedido]')?.dataset.situacao === 'pago', { timeout: 25000 }).catch(() => {});
const d5 = await page.evaluate(() => ({ situacao: document.querySelector('[data-lj-pedido]').dataset.situacao, texto: document.querySelector('.lj-pagar--ok h2')?.textContent.trim(), pagar: !!document.querySelector('[data-lj-pagar]'), feitas: document.querySelectorAll('.lj-etapas li[data-feito]').length }));
check('D5 pagamento caiu: a página se atualiza sozinha para "Pagamento confirmado", sem o quadro de pagar', d5.situacao === 'pago' && /Pagamento confirmado/.test(d5.texto || '') && !d5.pagar && d5.feitas === 2, d5);
await print(page, 'pedido-pago-computador');
php('validar-loja-online-14nov.pix.php', ['ACAO=desligar']);

// ───────────────────── E. painel do lojista e editor visual ─────────────────────
console.log('\n== E. Painel do lojista e editor visual ==');
const erp = await abrir(1440, 950);
await erp.goto(BASE + '/login', { waitUntil: 'networkidle2' });
await erp.type('input[name=email]', 'dono@demo.com');
await erp.type('input[name=password]', 'dono123');
await nav(erp, () => erp.click('button[type=submit]'));
if (erp.url().includes('selecionar-unidade')) await nav(erp, () => erp.evaluate(() => { const f = document.querySelector('form'); const r = f.querySelector('input[name=unidade_id], button[name=unidade_id]'); if (r && r.type === 'radio') r.checked = true; (f.querySelector('button[type=submit], button[name=unidade_id]') || f).click(); }));
await erp.goto(BASE + '/app/loja-online', { waitUntil: 'networkidle2' });
const e1 = await erp.evaluate(() => ({ menu: !!document.querySelector('a[href$="/app/loja-online"].nav-link'), noAr: document.body.innerText.includes('No ar'), endereco: document.querySelector('#enderecoLoja')?.value }));
check('E1 painel: link no menu Gestão, situação "No ar" e o endereço da loja', e1.menu && e1.noAr && /lojas\.teste|\/loja\/demo|cliente\.com/.test(e1.endereco || ''), e1);
await print(erp, 'painel-visao-geral');
await erp.goto(BASE + '/app/loja-online/produtos', { waitUntil: 'networkidle2' });
await erp.click('#marcarTodos');
check('E2 produtos: "marcar todos" conta os da página', /50 marcados|\d+ marcados/.test(await erp.$eval('#contagemMarcados', e => e.textContent)));
await print(erp, 'painel-produtos');
await erp.goto(BASE + '/app/loja-online/configuracoes', { waitUntil: 'networkidle2' });
const e3 = await erp.evaluate(() => { const l = document.querySelector('#unidade_id'), s = document.querySelector('#estoque_id'); const antes = [...s.options].filter(o => !o.hidden).length;
  l.value = [...l.options].find(o => o.text.includes('Shopping')).value; l.dispatchEvent(new Event('change'));
  const visiveis = [...s.options].filter(o => !o.hidden); return { antes, depois: visiveis.length, alheios: visiveis.filter(o => o.dataset.unidade && o.dataset.unidade !== l.value).length, ecommerce: visiveis.some(o => o.text.includes('E-commerce')) }; });
check('E3 configurações: trocar a loja de origem deixa só os estoques dela (o "E-commerce" da outra some)', e3.antes === 3 && e3.alheios === 0 && !e3.ecommerce, e3);
await print(erp, 'painel-configuracoes');

await erp.goto(BASE + '/app/loja-online/editor', { waitUntil: 'networkidle2' });
const quadro = () => erp.frames().find(f => f.url().includes('/loja/demo'));
await sleep(800);
const e4 = await erp.evaluate(() => ({ modelos: document.querySelectorAll('.ed-modelo').length, emUso: document.querySelector('.ed-modelo[aria-pressed=true]')?.textContent.includes('Vitrine'), publicar: document.querySelector('#edPublicar').disabled }));
check('E4 editor: 6 modelos, "Vitrine" em uso, Publicar desligado sem mudança', e4.modelos === 6 && e4.emUso && e4.publicar, e4);
check('E5 a prévia carrega dentro do editor com o aviso de prévia', await quadro().evaluate(() => document.body.innerText.includes('Prévia do editor')));
await print(erp, 'editor-modelos', false);
// trocar de modelo → Mimo
await erp.evaluate(() => [...document.querySelectorAll('.ed-modelo')].find(b => b.textContent.includes('Mimo')).click());
await erp.waitForSelector('.modal.show [data-role=ok]');
await sleep(350);
await erp.click('.modal.show [data-role=ok]');
await erp.waitForFunction(() => document.querySelector('#edEstado').textContent.includes('não publicadas'), { timeout: 15000 }).catch(() => {});
await sleep(2500);
const e6 = await quadro().evaluate(() => ({ titulos: getComputedStyle(document.querySelector('.lj-banner h1')).fontFamily, h1: document.querySelector('.lj-banner h1').textContent }));
check('E6 trocar para "Mimo": a prévia muda de fonte e de banner, e o editor avisa "mudanças não publicadas"', e6.titulos.includes('Fredoka') && /mimo/i.test(e6.h1), e6);
check('E7 o público continua vendo o modelo antigo', sql("SELECT modelo FROM lojas_online WHERE slug='demo'") === 'vitrine');
// cor
await erp.click('[data-bs-target="#edTema"]');
await sleep(400);
await erp.$eval('#edCor_cor_primaria', e => { e.value = '#0a7d4f'; e.dispatchEvent(new Event('input', { bubbles: true })); });
await erp.waitForFunction(() => document.querySelector('#edEstado').textContent.includes('não publicadas'), { timeout: 15000 }).catch(() => {});
await sleep(2500);
check('E8 mudar a cor da marca: a prévia recarrega com a cor nova', (await quadro().evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--marca').trim())) === '#0a7d4f');
await erp.select('#ed_cartao', 'borda');
await sleep(3000);
check('E9 mudar o cartão do produto para "com borda": a prévia acompanha', (await quadro().evaluate(() => document.body.dataset.cartao)) === 'borda');
await print(erp, 'editor-cores', false);
// logo
if (FOTOS) {
  const up = await erp.$('#edLogo input[type=file]');
  await up.uploadFile(join(FOTOS, 'logo.png'));
  await erp.waitForFunction(() => document.querySelector('#edLogo img'), { timeout: 15000 }).catch(() => {});
  await sleep(3000);
  check('E10 enviar a logo: aparece no editor e no cabeçalho da prévia', await erp.evaluate(() => !!document.querySelector('#edLogo img')) && await quadro().evaluate(() => !!document.querySelector('.lj-topo .lj-marca img')));
}
// blocos
await erp.click('[data-bs-target="#edPagina"]');
await sleep(400);
const nAntes = await erp.$$eval('.ed-bloco', b => b.length);
await erp.click('#edPagina .dropdown-toggle');
await erp.evaluate(() => [...document.querySelectorAll('#edNovoBloco .dropdown-item')].find(b => b.textContent.includes('Texto')).click());
await sleep(300);
const campoTitulo = await erp.$('.ed-bloco.aberto input[type=text]');
await campoTitulo.click({ clickCount: 3 });
await campoTitulo.type('Bloco criado no teste');
await erp.waitForFunction(() => document.querySelector('#edEstado').textContent.includes('não publicadas'), { timeout: 15000 }).catch(() => {});
await sleep(2800);
const e11 = { n: await erp.$$eval('.ed-bloco', b => b.length), naPrevia: await quadro().evaluate(() => document.body.innerText.includes('Bloco criado no teste')) };
check('E11 adicionar um bloco "Texto" e escrever o título: aparece na prévia', e11.n === nAntes + 1 && e11.naPrevia, e11);
const ordemAntes = await erp.$$eval('.ed-bloco', b => b.map(x => x.dataset.id));
await erp.evaluate(() => { const l = [...document.querySelectorAll('.ed-bloco')]; l[l.length - 1].querySelector('button[title="Subir"]').click(); });
await sleep(2800);
const ordemDepois = await erp.$$eval('.ed-bloco', b => b.map(x => x.dataset.id));
check('E12 "Subir" troca o bloco de lugar', ordemDepois[ordemDepois.length - 2] === ordemAntes[ordemAntes.length - 1], { ordemAntes, ordemDepois });
// arrastar pela alça (o 1º para depois do 2º)
const alcas = await erp.$$('.ed-pega');
const a = await alcas[0].boundingBox(), b2box = await (await erp.$$('.ed-bloco'))[1].boundingBox();
await erp.mouse.move(a.x + a.width / 2, a.y + a.height / 2);
await erp.mouse.down();
await erp.mouse.move(a.x + a.width / 2, a.y + 20, { steps: 5 });
await erp.mouse.move(a.x + a.width / 2, b2box.y + b2box.height + 6, { steps: 14 });
await sleep(200);
await erp.mouse.up();
await sleep(2800);
const ordemArrasto = await erp.$$eval('.ed-bloco', b => b.map(x => x.dataset.id));
check('E13 arrastar pela alça muda a ordem', ordemArrasto[0] !== ordemDepois[0] && ordemArrasto.includes(ordemDepois[0]), { ordemDepois, ordemArrasto });
// clicar num trecho da prévia abre o bloco
await quadro().evaluate(() => document.querySelector('[data-lj-bloco] .lj-banner').click());
await sleep(500);
check('E14 clicar no banner da prévia abre o bloco "Banner" no editor', await erp.evaluate(() => document.querySelector('.ed-bloco.aberto')?.textContent.includes('Banner')));
if (FOTOS) {
  const upB = await erp.$('.ed-bloco.aberto input[type=file]');
  await upB.uploadFile(join(FOTOS, 'banner.jpg'));
  await erp.waitForFunction(() => document.querySelector('.ed-bloco.aberto .ed-img img'), { timeout: 15000 }).catch(() => {});
  await sleep(3000);
  check('E15 enviar imagem para o banner: a prévia troca o mosaico pela imagem', await quadro().evaluate(() => !!document.querySelector('.lj-banner--foto .lj-banner__img') && !document.querySelector('.lj-banner .lj-mosaico')));
}
await erp.click('button[data-tela=celular]');
await sleep(500);
check('E16 prévia "Celular": a moldura fica com 390 px', await erp.$eval('#edMoldura', e => Math.round(e.getBoundingClientRect().width)) === 390);
await print(erp, 'editor-pagina-celular', false);
await erp.click('button[data-tela=computador]');
// publicar
await erp.click('#edPublicar');
await erp.waitForSelector('.modal.show [data-role=ok]');
await sleep(350);
await erp.click('.modal.show [data-role=ok]');
await erp.waitForFunction(() => document.querySelector('#edEstado').textContent.includes('Publicado'), { timeout: 15000 }).catch(() => {});
const pubRow = sql("SELECT modelo, JSON_UNQUOTE(JSON_EXTRACT(tema,'$.cor_primaria')), IFNULL(rascunho,'NULO') FROM lojas_online WHERE slug='demo'").split('\t');
check('E17 Publicar: modelo Mimo, cor nova e nenhum rascunho pendente no banco', pubRow[0] === 'mimo' && pubRow[1] === '#0a7d4f' && pubRow[2] === 'NULO', pubRow);
const visitante = await abrir(1366);
await visitante.goto(BASE + '/loja/demo', { waitUntil: 'networkidle2' });
const e18 = await visitante.evaluate(() => ({ marca: getComputedStyle(document.documentElement).getPropertyValue('--marca').trim(), bloco: document.body.innerText.includes('Bloco criado no teste'), previa: document.body.innerText.includes('Prévia do editor') }));
check('E18 visitante vê o publicado: cor nova, bloco novo, sem aviso de prévia', e18.marca === '#0a7d4f' && e18.bloco && !e18.previa, e18);
await print(visitante, 'publicado-mimo-personalizado');
await visitante.close();

// ───────────────────── F. cadastro do produto ─────────────────────
console.log('\n== F. Cadastro do produto: mais fotos e interruptor ==');
const PID2 = Number(sql("SELECT id FROM produtos WHERE descricao='Vela Aromática Figo'"));
sql(`DELETE FROM produto_fotos WHERE produto_id=${PID2}; UPDATE produtos SET loja_online=1, loja_online_destaque=0 WHERE id=${PID2};`);
await erp.goto(`${BASE}/app/produtos/${PID2}/edit`, { waitUntil: 'networkidle2' });
await erp.evaluate(() => { document.querySelectorAll('.wizard-step').forEach(e => e.classList.add('active')); });
const f1 = await erp.evaluate(() => ({ naLoja: document.querySelector('#loja_online').checked, destaque: document.querySelector('#loja_online_destaque').disabled, campo: !!document.querySelector('#fotos_extras') }));
check('F1 edição do produto: interruptor ligado, destaque disponível e o campo "Mais fotos"', f1.naLoja && !f1.destaque && f1.campo, f1);
if (FOTOS) {
  const campo = await erp.$('#fotos_extras');
  await campo.uploadFile(join(FOTOS, 'p35.jpg'), join(FOTOS, 'p36.jpg'));
  await sleep(500);
  check('F2 escolher 2 fotos mostra as 2 prévias', await erp.$$eval('#fotosExtrasPreview img', i => i.length) === 2);
}
await erp.evaluate(() => { document.querySelector('#loja_online_destaque').click(); });
await print(erp, 'produto-edicao-loja-online');
await nav(erp, () => erp.evaluate(() => document.querySelector('#formProduto').requestSubmit()));
const f3 = sql(`SELECT (SELECT COUNT(*) FROM produto_fotos WHERE produto_id=${PID2}), loja_online, loja_online_destaque FROM produtos WHERE id=${PID2}`).split('\t');
check('F3 salvar: 2 fotos adicionais gravadas e o produto virou destaque', f3[0] === (FOTOS ? '2' : '0') && f3[1] === '1' && f3[2] === '1', f3);
await erp.goto(`${BASE}/app/produtos/${PID2}/edit`, { waitUntil: 'networkidle2' });
await erp.evaluate(() => { document.querySelectorAll('.wizard-step').forEach(e => e.classList.add('active')); document.querySelector('#loja_online').click(); });
check('F4 desligar "aparece na loja" desliga e trava o destaque', await erp.evaluate(() => { const d = document.querySelector('#loja_online_destaque'); return d.disabled && !d.checked; }));
await nav(erp, () => erp.evaluate(() => document.querySelector('#formProduto').requestSubmit()));
check('F5 salvar desligado: o produto sai da loja', sql(`SELECT CONCAT(loja_online, loja_online_destaque) FROM produtos WHERE id=${PID2}`) === '00');
const fora = await abrir(1366);
const r = await fora.goto(`${BASE}/loja/demo/p/${PID2}-vela-aromatica-figo`, { waitUntil: 'networkidle2' });
check('F6 a página pública do produto desligado some (404)', r.status() === 404, r.status());
await fora.close();
sql(`UPDATE produtos SET loja_online=1 WHERE id=${PID2}`);

// ───────────────────── G. admin ─────────────────────
console.log('\n== G. Admin: card da loja online ==');
const outroContexto = await browser.createBrowserContext(); // sessão separada da do dono
const adm = await outroContexto.newPage();
await adm.setViewport({ width: 1366, height: 900 });
adm.on('pageerror', e => erros.push(e.message));
await adm.goto(BASE + '/login', { waitUntil: 'networkidle2' });
await adm.type('input[name=email]', 'admin@ia365.com.br');
await adm.type('input[name=password]', 'admin123');
await nav(adm, () => adm.click('button[type=submit]'));
await adm.goto(BASE + '/admin/empresas/1', { waitUntil: 'networkidle2' });
await adm.evaluate(() => bootstrap.Tab.getOrCreateInstance(document.querySelector('#integracao-tab')).show());
await sleep(500);
const g1 = await adm.evaluate(() => { const c = document.querySelector('#cardLojaOnline'); return { texto: c.innerText, slug: c.querySelector('#lojaSlug')?.value, botao: c.querySelector('form button').textContent.trim() }; });
check('G1 card "Loja online" no admin: liberada, com o endereço e o botão Desligar', /Liberada/.test(g1.texto) && g1.slug === 'demo' && g1.botao === 'Desligar', g1);
if (PRINTS) await (await adm.$('#cardLojaOnline')).screenshot({ path: `${PRINTS}/admin-card-loja-online.png` });
await adm.close();

check('Z1 nenhum erro de JavaScript em todo o roteiro', erros.length === 0, erros.slice(0, 5));
sql("UPDATE lojas_online SET modelo='vitrine', tema=NULL, blocos=NULL, rascunho=NULL WHERE slug='demo'; UPDATE empresas SET exige_documento_cadastro=1 WHERE id=1;");
console.log(`\n${fail ? '❌' : '✅'} ${ok} ok, ${fail} falha(s)`);
await browser.close();
process.exit(fail ? 1 : 0);
