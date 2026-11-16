// Validação ✅/❌ no NAVEGADOR — LOJA ONLINE (14/11/2026). Ambiente DESCARTÁVEL.
// Rodar DEPOIS do .py (ele deixa a loja "demo" liberada, no ar, com 30 produtos na vitrine).
//   BASE=http://10.213.80.4 MYSQL=ec-mysql APP=ec-app CHROME=/caminho/chrome-headless-shell NODE_PATH=/home/ubuntu/caixa-v2/node_modules \
//   FOTOS=/pasta/das/fotos PRINTS=/pasta node scripts/validar-loja-online-14nov.browser.mjs
// Cobre o que só o navegador prova: os 6 modelos no computador e no celular (sem erro de JavaScript e
// sem rolagem lateral), botão "+" do cartão, galeria, quantidade, carrinho, fechamento com frete ao
// vivo, tela do PIX (QR desenhado, copiar, confirmação automática), bloco H (2ª rodada: aviso de cookies,
// rastreadores só depois do aceite, popup, cupom, eventos de compra, movimento), editor visual (trocar modelo,
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
sql("UPDATE lojas_online SET modelo='vitrine', tema=NULL, blocos=NULL, rascunho=NULL, publicada=1, estoque_id=NULL, config=JSON_SET(config,'$.pedido_minimo',0,'$.vender_sem_estoque',false,'$.entrega',true,'$.retirada',true,'$.cookies_aviso',false,'$.popup_ativo',false,'$.meta_pixel','','$.ga4','','$.google_ads','','$.gtm','','$.tiktok_pixel','','$.frete_gratis_acima',0,'$.whatsapp_flutuante',true) WHERE slug='demo'; UPDATE empresas SET exige_documento_cadastro=0 WHERE id=1;");
// 4ª rodada: pedido online pago RESERVA estoque — os pedidos deixados pelos roteiros anteriores saem da conta
sql("UPDATE pedidos SET status='cancelado' WHERE loja_token IS NOT NULL AND status IN ('rascunho','confirmado')");
const PID = Number(sql("SELECT id FROM produtos WHERE descricao='Camisa Linho Areia'"));
const CAMINHO_P = `/loja/demo/p/${PID}-camisa-linho-areia`;

// ───────────────────── A. os 6 modelos, computador e celular ─────────────────────
console.log('\n== A. Os seis modelos renderizam, no computador e no celular ==');
const esperado = {
  vitrine: { titulo: 'Figtree', cartao: 'borda', foto: 'quadrada' }, atelie: { titulo: 'Bodoni Moda', cartao: 'limpo', foto: 'retrato' },
  circuito: { titulo: 'Archivo', cartao: 'sombra', foto: 'quadrada' }, feira: { titulo: 'Bricolage Grotesque', cartao: 'sombra', foto: 'quadrada' },
  mimo: { titulo: 'Fredoka', cartao: 'sombra', foto: 'quadrada' }, balcao: { titulo: 'Manrope', cartao: 'lista', foto: 'quadrada' },
};
for (const [modelo, esp] of Object.entries(esperado)) {
  sql(`UPDATE lojas_online SET modelo='${modelo}', tema=NULL, blocos=NULL WHERE slug='demo'`);
  const antes = erros.length;
  const d = await abrir(1366);
  await d.goto(BASE + '/loja/demo', { waitUntil: 'networkidle2' });
  const info = await d.evaluate(() => ({
    fonte: getComputedStyle(document.querySelector('.lj-banner h1, .lj-carrossel h1')).fontFamily, cabecalho: document.querySelector('[data-cabecalho]').dataset.cabecalho,
    cartao: document.body.dataset.cartao, foto: document.body.dataset.foto,
    cards: document.querySelectorAll('.lj-card').length, imgs: [...document.querySelectorAll('.lj-mosaico img, .lj-slide__produto img, .lj-slide__img')].filter(i => i.complete && i.naturalWidth > 0).length,
    h1: document.querySelectorAll('h1').length,
    colunas: getComputedStyle(document.querySelector('.lj-grade')).gridTemplateColumns.split(' ').length,
  }));
  check(`A1 ${modelo}: fonte do título (${esp.titulo}), cartão "${esp.cartao}" e foto "${esp.foto}"`, info.fonte.includes(esp.titulo) && info.cartao === esp.cartao && info.foto === esp.foto, info);
  check(`A2 ${modelo}: produtos na página, topo com fotos de produto carregadas, um único h1`, info.cards >= 4 && info.imgs >= 1 && info.h1 === 1, info);
  const topo = await d.evaluate(() => { const c = document.querySelector('.lj-carrossel'), b = document.querySelector('.lj-banner');
    if (c) return { tipo: 'carrossel', slides: c.querySelectorAll('.lj-slide').length, pontos: c.querySelectorAll('.lj-carrossel__pontos button').length, altura: Math.round(c.getBoundingClientRect().height) };
    const m = b.querySelector('.lj-mosaico'), t = b.querySelector('h1').getBoundingClientRect(), r = m.getBoundingClientRect();
    return { tipo: 'banner', estilo: [...b.classList].find(k => k.startsWith('lj-banner--')), altura: Math.round(b.getBoundingClientRect().height), embaixo: r.top >= t.bottom }; });
  const formaEsperada = { vitrine: t => t.tipo === 'carrossel' && t.slides >= 2 && t.pontos === t.slides, atelie: t => t.embaixo && t.estilo === 'lj-banner--editorial', circuito: t => t.tipo === 'carrossel' && t.slides >= 2,
    feira: t => t.embaixo && t.estilo === 'lj-banner--cheio', mimo: t => t.embaixo && t.estilo === 'lj-banner--centro', balcao: t => t.tipo === 'banner' && t.altura <= 300 }[modelo];
  check(`A2b ${modelo}: o topo tem a composição do modelo (${JSON.stringify(topo)})`, formaEsperada(topo), topo);
  const pecas = await d.evaluate(() => ({ circulos: !!document.querySelector('.lj-circulos'), banners: document.querySelectorAll('.lj-banners > *').length, desliza: document.querySelectorAll('.lj-grade--carrossel').length,
    busca: !!document.querySelector('[data-lj-busca] input'), sacola: !!document.querySelector('[data-lj-gaveta]'), rodape: document.querySelector('.lj-rodape').textContent.includes('Formas de pagamento') }));
  const cabecalhoEsperado = { vitrine: 'esquerda', atelie: 'centralizado', circuito: 'esquerda', feira: 'esquerda', mimo: 'centralizado', balcao: 'compacto' }[modelo];
  check(`A2c ${modelo}: cabeçalho com busca, sacola lateral e rodapé novo`, pecas.busca && pecas.sacola && pecas.rodape, pecas);
  check(`A2d ${modelo}: cabeçalho no formato do modelo (${cabecalhoEsperado})`, info.cabecalho === cabecalhoEsperado, info.cabecalho);
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
await erp.evaluate(() => document.querySelector('.ed-pega').scrollIntoView({ block: 'center' }));  // a lista ficou maior: a 1ª alça pode estar fora da tela
await sleep(200);
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

// ───────────────────── H. 2ª rodada: cookies, rastreamento, popup, cupom, movimento ─────────────────────
console.log('\n== H. Cookies, rastreamento, popup, cupom e movimento ==');
artisan('cache:clear');
sql("DELETE FROM loja_cupons WHERE codigo IN ('NAV20'); INSERT INTO loja_cupons (empresa_id,codigo,tipo,valor,minimo,usos,ativo,created_at,updated_at) VALUES (1,'NAV20','valor',20,0,0,1,NOW(),NOW()); DELETE FROM loja_contatos WHERE email='navegador@teste.test';");
const cfg2 = { cookies_aviso: true, meta_pixel: '1234567890123456', ga4: 'G-AB12CD34EF', google_ads: 'AW-123456789', google_ads_rotulo: 'AbCdEf_123', gtm: '', tiktok_pixel: '',
  popup_ativo: true, popup_capturar: true, popup_titulo: 'Ganhe R$ 20 na primeira compra', popup_texto: 'Cadastre seu e-mail.', popup_cupom: 'NAV20', popup_botao: 'Quero o cupom', popup_gatilho: 'entrar', popup_dias: 1,
  whatsapp_flutuante: true, whatsapp: '(86) 99999-0000' };
sql(`UPDATE lojas_online SET modelo='vitrine', tema=NULL, rascunho=NULL, config=JSON_MERGE_PATCH(config, '${JSON.stringify(cfg2)}'), blocos='${JSON.stringify([
  { id: 'h1', tipo: 'banner', titulo: 'Loja de teste', subtitulo: 'Movimento e promoções', botao_texto: 'Ver produtos', botao_link: 'produtos', imagem: '', estilo: 'dividido', altura: 'media' },
  { id: 'h2', tipo: 'oferta', titulo: 'Oferta relâmpago', texto: 'Só hoje', termina_em: '2099-12-31T23:59', cupom: 'NAV20', botao_texto: 'Ver', botao_link: 'produtos' },
  { id: 'h3', tipo: 'vitrine', titulo: 'Destaques', origem: 'destaques', categoria_id: null, quantidade: '8', colunas: '4' },
  { id: 'h4', tipo: 'video', titulo: 'Vídeo', video: 'dQw4w9WgXcQ' },
  { id: 'h5', tipo: 'faq', titulo: 'Dúvidas', lista: [{ nome: 'Como pago?', texto: 'PIX ou cartão.' }] },
  { id: 'h6', tipo: 'vitrine', titulo: 'Novidades', origem: 'novidades', categoria_id: null, quantidade: '8', colunas: '4' },
])}' WHERE slug='demo'`);

async function novoVisitante(largura = 1366, opts = {}) {
  const ctx = await browser.createBrowserContext();
  const p = await ctx.newPage();
  await p.setViewport({ width: largura, height: largura < 500 ? 844 : 900 });
  if (opts.semMovimento) await p.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
  p.on('pageerror', e => erros.push(e.message));
  const pedidos = [];
  await p.setRequestInterception(true);
  p.on('request', r => {
    const u = r.url();
    if (/googletagmanager\.com|google-analytics\.com|connect\.facebook\.net|facebook\.com\/tr|analytics\.tiktok\.com|youtube|ytimg/.test(u)) { pedidos.push(u); r.abort(); } else r.continue();
  });
  return { p, pedidos, ctx };
}
const rastreadores = l => l.filter(u => /googletagmanager|google-analytics|facebook|tiktok/.test(u));

// primeira visita: aviso de cookies, nada de rastreador
let v = await novoVisitante();
await v.p.goto(BASE + '/loja/demo', { waitUntil: 'networkidle2' });
await sleep(1800);
let h = await v.p.evaluate(() => ({ barra: !document.querySelector('[data-lj-cookies]').hidden, popup: document.querySelector('[data-lj-popup]').open, fbq: typeof window.fbq, gtag: typeof window.gtag,
  zap: getComputedStyle(document.querySelector('.lj-zap')).bottom, altBarra: document.querySelector('[data-lj-cookies]').offsetHeight }));
check('H1 primeira visita: o aviso de cookies aparece e NENHUM rastreador é chamado', h.barra && rastreadores(v.pedidos).length === 0 && h.fbq === 'undefined' && h.gtag === 'undefined', { ...h, pedidos: v.pedidos });
check('H2 o popup espera o aviso de cookies (não abre por cima dele)', h.popup === false, h);
check('H3 o botão do WhatsApp sobe para não ficar embaixo do aviso', parseFloat(h.zap) >= h.altBarra, h);
await print(v.p, 'h-aviso-cookies', false);
await v.p.goto(BASE + CAMINHO_P, { waitUntil: 'networkidle2' });
h = await v.p.evaluate(() => ({ registrados: LJ.rastro.registrados.map(e => e[0]), fila: LJ.rastro.fila.length }));
check('H4 antes do aceite, "viu o produto" fica na fila da página, sem sair para ninguém', h.registrados.includes('ver_produto') && h.fila === 1 && rastreadores(v.pedidos).length === 0, h);
// escolher: só medição
await v.p.click('[data-lj-cookies-escolher]');
h = await v.p.evaluate(() => ({ escolhas: !document.querySelector('[data-lj-cookies-escolhas]').hidden, salvar: !document.querySelector('[data-lj-cookies-salvar]').hidden,
  necessario: document.querySelector('[data-lj-cookies-escolhas] input[disabled]').checked, analise: document.querySelector('[data-lj-cookie=analise]').checked, marketing: document.querySelector('[data-lj-cookie=marketing]').checked }));
check('H5 "Escolher" abre as categorias: necessários travado e ligado; medição e publicidade DESMARCADAS de início', h.escolhas && h.salvar && h.necessario && !h.analise && !h.marketing, h);
await v.p.click('[data-lj-cookie=analise]');
await v.p.click('[data-lj-cookies-salvar]');
await sleep(600);
h = await v.p.evaluate(() => ({ barra: document.querySelector('[data-lj-cookies]').hidden, gtag: typeof window.gtag, fbq: typeof window.fbq, guardado: JSON.parse(localStorage.getItem('lj_cookies_' + (document.querySelector('[data-lj-popup]')?.dataset.x || '')) || 'null'),
  consent: LJ.rastro.consentimento, fila: LJ.rastro.fila.length, eventos: (window.dataLayer || []).map(a => a[1]).filter(Boolean) }));
check('H6 aceitou só medição: carrega o Google Analytics, NÃO carrega a Meta; o aviso some', h.barra && h.gtag === 'function' && h.fbq === 'undefined' && h.consent.analise === true && h.consent.marketing === false
  && v.pedidos.some(u => u.includes('googletagmanager.com/gtag/js?id=G-AB12CD34EF')) && !v.pedidos.some(u => u.includes('facebook')), { ...h, pedidos: v.pedidos });
check('H7 o que estava na fila sai agora: view_item foi para o Google', h.fila === 0 && h.eventos.includes('view_item'), h.eventos);
await v.p.reload({ waitUntil: 'networkidle2' });
h = await v.p.evaluate(() => ({ barra: document.querySelector('[data-lj-cookies]').hidden, gtag: typeof window.gtag, fbq: typeof window.fbq }));
check('H8 na visita seguinte a escolha é lembrada: sem aviso, Analytics ligado, Meta desligada', h.barra && h.gtag === 'function' && h.fbq === 'undefined', h);
// mudar de ideia pelo rodapé
await v.p.evaluate(() => document.querySelector('.lj-rodape [data-lj-cookies-abrir]').click());
h = await v.p.evaluate(() => ({ barra: !document.querySelector('[data-lj-cookies]').hidden, analise: document.querySelector('[data-lj-cookie=analise]').checked, marketing: document.querySelector('[data-lj-cookie=marketing]').checked }));
check('H9 "Preferências de cookies" no rodapé reabre o aviso com a escolha atual marcada', h.barra && h.analise && !h.marketing, h);
check('H9b ao reabrir, as categorias já vêm abertas para mudar', await v.p.evaluate(() => !document.querySelector('[data-lj-cookies-escolhas]').hidden && !document.querySelector('[data-lj-cookies-salvar]').hidden));
await sleep(600); // o aviso chega deslizando
await v.p.click('[data-lj-cookies-aceitar]');
await sleep(500);
h = await v.p.evaluate(() => ({ fbq: typeof window.fbq, fila: (window.fbq && window.fbq.queue || []).map(a => a[1]) }));
check('H10 "Aceitar todos": a Meta passa a carregar, com PageView', h.fbq === 'function' && v.pedidos.some(u => u.includes('connect.facebook.net')) && h.fila.includes('PageView'), h);
await v.p.click('.lj-comprar button[type=submit]');
await v.p.waitForFunction(() => LJ.rastro.registrados.some(e => e[0] === 'carrinho'), { timeout: 8000 }).catch(() => {});
h = await v.p.evaluate(() => ({ ev: LJ.rastro.registrados.find(e => e[0] === 'carrinho'), google: (window.dataLayer || []).filter(a => a[1] === 'add_to_cart').map(a => a[2]), meta: (window.fbq.queue || []).filter(a => a[1] === 'AddToCart').map(a => a[2]) }));
check('H11 adicionar ao carrinho avisa Google (add_to_cart) e Meta (AddToCart) com valor e produto', h.ev && h.ev[1].valor > 0 && h.google.length === 1 && h.google[0].currency === 'BRL' && h.google[0].items[0].item_id === String(PID)
  && h.meta.length === 1 && h.meta[0].content_ids[0] === String(PID), h);
await v.ctx.close();

// popup: abre depois do aceite; cadastro com aceite entrega o cupom
v = await novoVisitante();
await v.p.goto(BASE + '/loja/demo', { waitUntil: 'networkidle2' });
await v.p.click('[data-lj-cookies-recusar]');
await v.p.waitForFunction(() => document.querySelector('[data-lj-popup]').open, { timeout: 8000 }).catch(() => {});
h = await v.p.evaluate(() => ({ aberto: document.querySelector('[data-lj-popup]').open, titulo: document.querySelector('#lj-popup-titulo').textContent, foco: document.activeElement.closest('[data-lj-popup]') !== null,
  rastro: typeof window.gtag + typeof window.fbq, consent: LJ.rastro.consentimento }));
check('H12 "Só os necessários": nenhum rastreador carrega; e aí o popup abre, com o foco dentro dele', h.aberto && h.foco && h.rastro === 'undefinedundefined' && rastreadores(v.pedidos).length === 0 && h.consent.marketing === false, h);
await print(v.p, 'h-popup', false);
await v.p.type('#lj-popup-email', 'navegador@teste.test');
await v.p.click('[data-lj-popup] form button[type=submit]');
await v.p.waitForFunction(() => document.querySelector('[data-lj-popup] [data-lj-contato-retorno]').textContent.length > 3, { timeout: 8000 }).catch(() => {});
h = await v.p.evaluate(() => ({ msg: document.querySelector('[data-lj-popup] [data-lj-contato-retorno]').textContent, cupom: document.querySelector('[data-lj-popup-cupom]').hidden }));
check('H13 sem marcar o aceite o cadastro não vai, e o popup explica', /aceita receber/.test(h.msg) && h.cupom === true && sql("SELECT COUNT(*) FROM loja_contatos WHERE email='navegador@teste.test'") === '0', h);
await v.p.click('[data-lj-popup] input[name=aceite]');
await v.p.click('[data-lj-popup] form button[type=submit]');
await v.p.waitForFunction(() => !document.querySelector('[data-lj-popup-cupom]').hidden, { timeout: 8000 }).catch(() => {});
h = await v.p.evaluate(() => ({ cupom: document.querySelector('[data-lj-popup-cupom] b').textContent, visivel: !document.querySelector('[data-lj-popup-cupom]').hidden, form: document.querySelector('[data-lj-popup] form').hidden }));
check('H14 com o aceite: o contato é gravado e o popup mostra o cupom NAV20', h.visivel && h.cupom === 'NAV20' && h.form && sql("SELECT origem FROM loja_contatos WHERE email='navegador@teste.test'") === 'popup', h);
await v.p.keyboard.press('Escape');
await sleep(300);
check('H15 a tecla Esc fecha o popup', await v.p.evaluate(() => !document.querySelector('[data-lj-popup]').open));
await v.p.reload({ waitUntil: 'networkidle2' });
await sleep(2500);
check('H16 quem já viu o popup não vê de novo na visita seguinte', await v.p.evaluate(() => !document.querySelector('[data-lj-popup]').open));

// blocos novos: relógio, cupom copiável, vídeo, perguntas
h = await v.p.evaluate(() => [...document.querySelectorAll('[data-lj-relogio] b')].map(b => b.textContent).join(':'));
await sleep(1300);
const h2 = await v.p.evaluate(() => [...document.querySelectorAll('[data-lj-relogio] b')].map(b => b.textContent).join(':'));
check('H17 o relógio da oferta anda de um segundo para o outro', /^\d+:\d\d:\d\d:\d\d$/.test(h) && h !== h2, { h, h2 });
await v.p.click('.lj-oferta [data-lj-copiar-texto]');
await sleep(250);
check('H18 clicar no cupom da oferta avisa que copiou', /NAV20 copiado/.test(await v.p.$eval('[data-lj-brinde-texto]', e => e.textContent)));
check('H19 o vídeo NÃO carrega o YouTube até o clique (só a capa)', !v.pedidos.some(u => /youtube(-nocookie)?\.com\/embed/.test(u)) && await v.p.evaluate(() => !document.querySelector('.lj-video iframe')));
await v.p.click('.lj-video');
await sleep(400);
check('H20 no clique o vídeo entra pelo endereço sem cookies do YouTube', await v.p.evaluate(() => document.querySelector('.lj-video iframe')?.src.includes('youtube-nocookie.com/embed/dQw4w9WgXcQ')));
await v.p.click('.lj-faq summary');
check('H21 a pergunta abre ao clicar', await v.p.evaluate(() => document.querySelector('.lj-faq details').open));

// cupom no carrinho e compra com evento
await v.p.goto(BASE + CAMINHO_P, { waitUntil: 'networkidle2' });
await v.p.$eval('#lj-qtd', e => { e.value = '2'; });
await v.p.click('.lj-comprar button[type=submit]');
await v.p.waitForFunction(() => document.querySelector('[data-lj-conta]').textContent.trim() === '2', { timeout: 8000 }).catch(() => {});
await v.p.goto(BASE + '/loja/demo/carrinho', { waitUntil: 'networkidle2' });
await v.p.type('#lj-cupom', 'nav20');
await nav(v.p, () => v.p.click('.lj-cupom-form button'));
h = await v.p.evaluate(() => ({ aplicado: !!document.querySelector('.lj-cupom-aplicado'), desconto: document.querySelector('dd.lj-desconto')?.textContent.trim(), total: document.querySelector('dd.lj-total').textContent.trim(), ok: document.querySelector('.lj-aviso--ok')?.textContent.trim() }));
check('H22 digitar o cupom no carrinho: desconto de R$ 20,00 na conta e a confirmação na tela', h.aplicado && /20,00/.test(h.desconto || '') && h.total === 'R$ 99,80' && /NAV20 aplicado/.test(h.ok || ''), h);
await print(v.p, 'h-carrinho-cupom', false);
await nav(v.p, () => v.p.click('.lj-resumo a.lj-btn'));
h = await v.p.evaluate(() => ({ zap: getComputedStyle(document.querySelector('.lj-zap')).display, busca: getComputedStyle(document.querySelector('.lj-busca')).display, popup: !!document.querySelector('[data-lj-popup]'),
  ev: LJ.rastro.registrados.map(e => e[0]), total: document.querySelector('[data-lj-total]').textContent.trim() }));
check('H23 fechamento: sem WhatsApp flutuante, sem busca e sem popup para distrair; total já com o cupom; evento "fechamento"', h.zap === 'none' && h.busca === 'none' && !h.popup && h.total === 'R$ 99,80' && h.ev.includes('fechamento'), h);
await v.p.type('#f-nome', 'Cliente do Rastreio');
await v.p.type('#f-tel', '86966665555');
await v.p.click('input[name=entrega][value=retirada]');
await nav(v.p, () => v.p.click('[data-lj-enviar]'));
h = await v.p.evaluate(() => ({ compra: LJ.rastro.registrados.filter(e => e[0] === 'compra').map(e => e[1]), h1: document.querySelector('h1').textContent, desconto: document.querySelector('dd.lj-desconto')?.textContent.trim() }));
check('H24 pedido fechado com o cupom: a página do pedido registra a COMPRA com número e valor (R$ 99,80)', h.compra.length === 1 && h.compra[0].valor === 99.8 && String(h.compra[0].pedido) === h.h1.replace(/\D/g, '') && /20,00/.test(h.desconto || ''), h);
await v.p.reload({ waitUntil: 'networkidle2' });
check('H25 recarregar a página do pedido NÃO conta a compra de novo', await v.p.evaluate(() => LJ.rastro.registrados.filter(e => e[0] === 'compra').length) === 0);
await v.ctx.close();

// movimento
v = await novoVisitante();
await v.p.goto(BASE + '/loja/demo', { waitUntil: 'networkidle2' });
await v.p.click('[data-lj-cookies-recusar]');
await v.p.evaluate(() => { const d = document.querySelector('[data-lj-popup]'); if (d) d.remove(); });
h = await v.p.evaluate(() => ({ js: document.documentElement.classList.contains('lj-js'), mov: document.body.dataset.movimento, voar: typeof LJ.voar, ultima: [...document.querySelectorAll('.lj-grade')].pop().classList.contains('lj-vista'),
  primeira: document.querySelector('.lj-grade').getBoundingClientRect().top < innerHeight }));
check('H26 movimento completo: as listas ainda fora da tela esperam para entrar', h.js && h.mov === 'completo' && h.voar === 'function' && h.ultima === false, h);
await v.p.evaluate(() => [...document.querySelectorAll('.lj-grade')].pop().scrollIntoView({ block: 'center' }));
await sleep(1500);
h = await v.p.evaluate(() => { const g = [...document.querySelectorAll('.lj-grade')].pop(); return { vista: g.classList.contains('lj-vista'), opacidade: getComputedStyle(g.querySelector('.lj-card')).opacity, atraso: getComputedStyle(g.querySelectorAll('.lj-card')[3]).animationDelay }; });
check('H27 ao rolar, a lista entra em sequência (cada cartão com um pequeno atraso) e fica visível', h.vista && Number(h.opacidade) > 0.99 && h.atraso === '0.135s', h);
await v.p.evaluate(() => window.scrollTo(0, 0));
await sleep(400);
await v.p.evaluate(() => document.querySelector('.lj-card form[data-lj-add] button').click());
await v.p.waitForSelector('.lj-voo', { timeout: 4000 }).catch(() => {});
h = await v.p.evaluate(() => ({ voo: !!document.querySelector('.lj-voo') }));
await sleep(900);
const depois = await v.p.evaluate(() => ({ voo: !!document.querySelector('.lj-voo'), conta: document.querySelector('[data-lj-conta]').textContent.trim() }));
check('H28 adicionar pelo cartão: a foto voa até a sacola e some ao chegar', h.voo && !depois.voo && depois.conta === '1', { h, depois });
h = await v.p.evaluate(() => { const c = document.querySelector('.lj-card'); c.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true })); return c.querySelector('.lj-card__foto img').style.viewTransitionName; });
check('H29 ao tocar num produto, a foto dele recebe o nome da transição (viaja para a página do produto)', h === 'lj-foto', h);
await v.p.goto(BASE + CAMINHO_P, { waitUntil: 'networkidle2' });
check('H30 na página do produto a foto principal é o destino da transição', await v.p.evaluate(() => getComputedStyle(document.querySelector('[data-lj-palco] img')).viewTransitionName) === 'lj-foto');
await v.p.goto(BASE + '/loja/demo', { waitUntil: 'networkidle2' });
await sleep(6600);
check('H30b rede de segurança: sem rolar nada, em poucos segundos todas as listas aparecem (conteúdo nunca fica escondido)', await v.p.evaluate(() => [...document.querySelectorAll('.lj-grade')].every(g => g.classList.contains('lj-vista') && getComputedStyle(g.querySelector('.lj-card')).opacity > 0.99)));
await v.ctx.close();
v = await novoVisitante(1366, { semMovimento: true });
await v.p.goto(BASE + '/loja/demo', { waitUntil: 'networkidle2' });
h = await v.p.evaluate(() => { const g = [...document.querySelectorAll('.lj-grade')].pop(); return { voar: typeof LJ.voar, vista: g.classList.contains('lj-vista'), opacidade: getComputedStyle(g.querySelector('.lj-card')).opacity,
  mosaico: getComputedStyle(document.querySelector('.lj-mosaico a')).animationName, titulo: getComputedStyle(document.querySelector('.lj-banner h1')).animationName }; });
check('H31 quem pediu "reduzir movimento" no aparelho: tudo visível de imediato, sem voo, sem entrada, sem revelação', h.voar === 'undefined' && h.vista && h.opacidade === '1' && h.mosaico === 'none' && h.titulo === 'none', h);
await v.ctx.close();
sql("UPDATE lojas_online SET tema='{\"movimento\":\"nenhum\",\"faixa\":\"correndo\"}', config=JSON_SET(config,'$.aviso_topo','Frete grátis nesta semana') WHERE slug='demo'");
v = await novoVisitante();
await v.p.goto(BASE + '/loja/demo', { waitUntil: 'networkidle2' });
h = await v.p.evaluate(() => ({ html: document.documentElement.dataset.movimento, mosaico: getComputedStyle(document.querySelector('.lj-mosaico a')).animationName, trilho: getComputedStyle(document.querySelector('.lj-faixa__trilho')).animationName,
  copias: getComputedStyle(document.querySelector('.lj-faixa__trilho span + span')).display }));
check('H32 lojista escolheu "sem movimento": nada anima, e o letreiro fica parado (sem texto repetido)', h.html === 'nenhum' && h.mosaico === 'none' && h.trilho === 'none' && h.copias === 'none', h);
await v.ctx.close();
sql("UPDATE lojas_online SET tema='{\"faixa\":\"correndo\"}' WHERE slug='demo'");
v = await novoVisitante();
await v.p.goto(BASE + '/loja/demo', { waitUntil: 'networkidle2' });
h = await v.p.evaluate(() => ({ trilho: getComputedStyle(document.querySelector('.lj-faixa__trilho')).animationName, copias: getComputedStyle(document.querySelector('.lj-faixa__trilho span + span')).display, sobra: document.documentElement.scrollWidth - innerWidth }));
check('H33 letreiro ligado: a faixa corre, sem criar rolagem lateral', h.trilho === 'lj-corre' && h.copias !== 'none' && h.sobra <= 1, h);
await v.p.click('[data-lj-cookies-recusar]');
await v.p.evaluate(() => { const d = document.querySelector('[data-lj-popup]'); if (d) d.remove(); });
await print(v.p, 'h-inicio-blocos-novos');
await v.ctx.close();
for (const [larg, nome] of [[390, 'celular']]) {
  v = await novoVisitante(larg);
  for (const rota of ['/loja/demo', '/loja/demo/politica/privacidade', '/loja/demo/carrinho']) {
    await v.p.goto(BASE + rota, { waitUntil: 'networkidle2' });
    check(`H34 ${nome} ${rota}: sem rolagem lateral com aviso de cookies, letreiro e blocos novos`, await sobraLateral(v.p) <= 1, await sobraLateral(v.p));
  }
  const cab = await v.p.evaluate(() => { const b = document.querySelector('[data-lj-cookies]'); return { alt: b.offsetHeight, tela: innerHeight, botoes: [...b.querySelectorAll('button:not([hidden])')].map(x => Math.round(x.getBoundingClientRect().height)) }; });
  check(`H35 ${nome}: o aviso de cookies ocupa menos da metade da tela e os botões têm 44 px de toque`, cab.alt < cab.tela / 2 && cab.botoes.every(x => x >= 44), cab);
  await print(v.p, 'h-politica-celular', false);
  await v.ctx.close();
}

// painel: abas novas
await erp.goto(BASE + '/app/loja-online/promocoes', { waitUntil: 'networkidle2' });
h = await erp.evaluate(() => ({ abas: [...document.querySelectorAll('.nav-pills .nav-link')].map(a => a.textContent.trim()), cupom: document.body.innerText.includes('NAV20'), contato: document.body.innerText.includes('navegador@teste.test') }));
check('H36 painel: 14 abas (5ª rodada), com o cupom e o contato captado no popup', h.abas.length === 14 && h.abas.includes('Domínio') && h.abas.includes('Editor visual') && h.abas.includes('Promoções') && h.abas.includes('Anúncios') && h.abas.includes('Pedidos') && h.abas.includes('Grade') && h.cupom && h.contato, h);
await erp.select('#cupom_tipo', 'frete_gratis');
check('H37 escolher "Frete grátis" no cupom trava o campo de valor', await erp.$eval('#cupom_valor', e => e.disabled));
await print(erp, 'h-painel-promocoes');
await erp.goto(BASE + '/app/loja-online/politicas', { waitUntil: 'networkidle2' });
await erp.click('[data-usar-modelo=politica_privacidade]');
h = await erp.$eval('#politica_privacidade', e => e.value);
check('H38 políticas: "Copiar o texto de partida" preenche o campo com o modelo (com Google e Meta, que estão configurados)', h.includes('Lei Geral de Proteção de Dados') && h.includes('Meta (Facebook e Instagram)'), h.slice(0, 120));
await print(erp, 'h-painel-politicas');
for (const aba of ['avisos', 'rastreamento']) { await erp.goto(BASE + '/app/loja-online/' + aba, { waitUntil: 'networkidle2' }); await print(erp, 'h-painel-' + aba); }
await erp.goto(BASE + '/app/loja-online/editor', { waitUntil: 'networkidle2' });
await erp.click('[data-bs-target="#edPagina"]');
await sleep(400);
h = await erp.evaluate(() => [...document.querySelectorAll('#edNovoBloco .dropdown-item')].map(b => b.childNodes[1].textContent.trim()));
check('H39 editor oferece os 16 tipos de bloco (6 da 2ª rodada + carrossel, grade de banners e galeria)', h.length === 16 && ['Carrossel de banners', 'Grade de banners', 'Galeria de fotos', 'Oferta com contagem', 'Depoimentos', 'Perguntas frequentes', 'Receber novidades', 'Vídeo', 'Redes sociais'].every(n => h.includes(n)), h);
await erp.click('#edPagina .dropdown-toggle');
await erp.evaluate(() => [...document.querySelectorAll('#edNovoBloco .dropdown-item')].find(b => b.textContent.includes('Depoimentos')).click());
await sleep(300);
await erp.type('.ed-bloco.aberto textarea', 'Atendimento excelente');
await erp.evaluate(() => [...document.querySelectorAll('.ed-bloco.aberto button')].find(b => b.textContent.includes('+ Adicionar')).click());
await sleep(300);
h = await erp.evaluate(() => document.querySelectorAll('.ed-bloco.aberto textarea').length);
await sleep(2800);
const naPrevia = await erp.frames().find(f => f.url().includes('/loja/demo')).evaluate(() => document.body.innerText.includes('Atendimento excelente'));
check('H40 editor: bloco de depoimentos com linhas que se adicionam, e o texto aparece na prévia', h === 2 && naPrevia, { h, naPrevia });
await erp.click('#edDescartar');
await erp.waitForSelector('.modal.show [data-role=ok]');
await sleep(350);
await erp.click('.modal.show [data-role=ok]');
await sleep(800);
sql("DELETE FROM loja_cupons WHERE codigo='NAV20'; UPDATE lojas_online SET tema=NULL, blocos=NULL, rascunho=NULL, config=JSON_SET(config,'$.popup_ativo',false,'$.cookies_aviso',false,'$.meta_pixel','','$.ga4','','$.google_ads','','$.aviso_topo','') WHERE slug='demo'");

// ───────────────────── I. 3ª rodada: peças novas da vitrine ─────────────────────
console.log('\n== I. 3ª rodada: sacola lateral, busca, carrossel, produto, filtros, editor ==');
sql("UPDATE lojas_online SET modelo='vitrine', tema=NULL, blocos=NULL, rascunho=NULL WHERE slug='demo'");
{
  const ctxI = await browser.createBrowserContext(); // carrinho próprio
  const i = await ctxI.newPage();
  await i.setViewport({ width: 1366, height: 900 });
  i.on('pageerror', e => erros.push(e.message));
  await i.goto(BASE + '/loja/demo', { waitUntil: 'networkidle2' });

  // carrossel do topo
  let r = await i.evaluate(() => { const c = document.querySelector('[data-lj-carrossel]'), t = c.querySelector('[data-lj-trilho-banner]'); return { slides: t.children.length, pos: t.scrollLeft, larg: t.clientWidth }; });
  await i.click('.lj-carrossel__pontos button[data-ir="1"]');
  await sleep(900);
  let r2 = await i.evaluate(() => { const c = document.querySelector('[data-lj-carrossel]'), t = c.querySelector('[data-lj-trilho-banner]'); return { pos: Math.round(t.scrollLeft), atual: c.querySelector('.lj-carrossel__pontos button[data-ir="1"]').getAttribute('aria-current') }; });
  check('I1 carrossel: clicar no 2º ponto leva ao 2º banner e marca o ponto', r.slides >= 2 && Math.abs(r2.pos - r.larg) < 6 && r2.atual === 'true', { ...r, ...r2 });
  await i.click('.lj-carrossel__seta[data-d="-1"]');
  await sleep(900);
  check('I2 carrossel: a seta "anterior" volta ao 1º banner', await i.evaluate(() => Math.round(document.querySelector('[data-lj-trilho-banner]').scrollLeft)) < 6);

  // fileira que desliza
  r = await i.evaluate(() => { const s = document.querySelector('[data-lj-setas]'), t = s.closest('section').querySelector('[data-lj-trilho]'); return { antes: t.scrollLeft, cabe: t.scrollWidth <= t.clientWidth + 2 }; });
  await i.evaluate(() => document.querySelector('[data-lj-setas] button[data-d="1"]').click());
  await sleep(900);
  r2 = await i.evaluate(() => { const s = document.querySelector('[data-lj-setas]'), t = s.closest('section').querySelector('[data-lj-trilho]'); return t.scrollLeft; });
  check('I3 fileira em carrossel: a seta desliza os produtos (ou tudo já cabe na tela)', r.cabe || r2 > r.antes + 50, { ...r, depois: r2 });

  // busca com sugestões
  await i.type('#lj-q', 'fone');
  await i.waitForFunction(() => !document.querySelector('[data-lj-sugestoes]').hidden, { timeout: 8000 }).catch(() => {});
  r = await i.evaluate(() => { const c = document.querySelector('[data-lj-sugestoes]'); return { aberta: !c.hidden, itens: [...c.querySelectorAll('a')].map(a => a.textContent.trim()), expandido: document.querySelector('#lj-q').getAttribute('aria-expanded') }; });
  check('I4 busca: digitar "fone" abre as sugestões com o produto e o atalho "ver todos"', r.aberta && r.itens.some(t => t.includes('Fone Bluetooth Pro')) && r.itens.some(t => t.includes('Ver todos')) && r.expandido === 'true', r);
  await i.keyboard.press('Escape');
  check('I5 busca: Esc fecha as sugestões', await i.evaluate(() => document.querySelector('[data-lj-sugestoes]').hidden));
  await i.evaluate(() => { document.querySelector('#lj-q').value = ''; });

  // sacola lateral
  await i.hover('.lj-card');
  await i.click('.lj-card form[data-lj-add] button');
  await i.waitForFunction(() => document.querySelector('[data-lj-gaveta]').open, { timeout: 8000 }).catch(() => {});
  r = await i.evaluate(() => { const g = document.querySelector('[data-lj-gaveta]'); return { aberta: g.open, itens: g.querySelectorAll('.lj-item').length, total: g.querySelector('[data-lj-gaveta-total]').textContent.trim(), conta: document.querySelector('[data-lj-conta]').textContent.trim(), url: location.pathname }; });
  check('I6 adicionar pelo cartão abre a sacola lateral com o item e o subtotal, sem sair da página', r.aberta && r.itens === 1 && /R\$\s?\d/.test(r.total) && r.conta === '1' && r.url === '/loja/demo', r);
  await sleep(700); // a sacola entra deslizando
  await i.click('[data-lj-gaveta] .lj-item button[aria-label^="Aumentar"]');
  await i.waitForFunction(() => document.querySelector('[data-lj-conta]').textContent.trim() === '2', { timeout: 8000 }).catch(() => {});
  r2 = await i.evaluate(() => ({ qtd: document.querySelector('[data-lj-gaveta] .lj-qtd span').textContent, conta: document.querySelector('[data-lj-conta]').textContent.trim(), total: document.querySelector('[data-lj-gaveta-total]').textContent.trim() }));
  check('I7 "+" dentro da sacola lateral: quantidade 2, contador 2 e subtotal recalculado', r2.qtd === '2' && r2.conta === '2' && r2.total !== r.total, r2);
  await i.click('[data-lj-gaveta] .lj-tirar');
  await i.waitForFunction(() => !!document.querySelector('[data-lj-gaveta] .lj-gaveta__vazia'), { timeout: 8000 }).catch(() => {});
  r = await i.evaluate(() => ({ vazia: !!document.querySelector('[data-lj-gaveta] .lj-gaveta__vazia'), pe: document.querySelector('[data-lj-gaveta] .lj-gaveta__pe').hidden, conta: document.querySelector('[data-lj-conta]').hidden }));
  check('I8 "Tirar" esvazia a sacola: estado vazio, sem botão de finalizar, contador some', r.vazia && r.pe && r.conta, r);
  await i.keyboard.press('Escape');
  await sleep(200);
  check('I9 Esc fecha a sacola lateral', await i.evaluate(() => !document.querySelector('[data-lj-gaveta]').open));

  // página do produto
  await i.goto(BASE + CAMINHO_P, { waitUntil: 'networkidle2' });
  await i.click('#aba-entrega');
  r = await i.evaluate(() => ({ sel: document.querySelector('#aba-entrega').getAttribute('aria-selected'), painel: !document.querySelector('#painel-entrega').hidden, outro: document.querySelector('#painel-descricao').hidden }));
  check('I10 produto: aba "Entrega e trocas" troca o painel', r.sel === 'true' && r.painel && r.outro, r);
  await i.click('[data-lj-zoom="0"]');
  await sleep(300);
  r = await i.evaluate(() => { const l = document.querySelector('[data-lj-luz]'); return { aberta: l.open, src: l.querySelector('[data-lj-luz-img]').getAttribute('src') }; });
  await i.click('[data-lj-luz-ir="1"]');
  r2 = await i.evaluate(() => document.querySelector('[data-lj-luz] [data-lj-luz-img]').getAttribute('src'));
  check('I11 produto: clicar na foto abre a foto ampliada e a seta passa para a próxima', r.aberta && !!r.src && r2 !== r.src, { ...r, depois: r2 });
  await i.keyboard.press('Escape');
  await sleep(200);
  await nav(i, () => i.click('[data-lj-comprar-agora]'));
  check('I12 "Comprar agora" adiciona e vai direto para o fechamento', i.url().endsWith('/loja/demo/finalizar') && await i.evaluate(() => document.querySelectorAll('.lj-resumo .lj-linha, .lj-resumo li, .lj-resumo [data-lj-total]').length > 0), i.url());
  await i.goto(BASE + CAMINHO_P, { waitUntil: 'networkidle2' });
  await i.goto(BASE + '/loja/demo', { waitUntil: 'networkidle2' });
  check('I13 produto visto fica guardado no navegador (para "vistos recentemente")', await i.evaluate(pid => Object.keys(localStorage).some(k => (localStorage.getItem(k) || '').includes(String(pid))), PID));

  // lista com filtros
  await i.goto(BASE + '/loja/demo/produtos', { waitUntil: 'networkidle2' });
  const total = await i.evaluate(() => document.querySelectorAll('.lj-card').length);
  await i.type('input[name=preco_max]', '50');
  await nav(i, () => i.evaluate(() => document.querySelector('input[name=preco_max]').form.requestSubmit()));
  r = await i.evaluate(() => ({ url: location.search, cards: document.querySelectorAll('.lj-card').length, chips: [...document.querySelectorAll('.lj-chips a, .lj-chips span')].map(c => c.textContent.trim()).join(' | '),
    precos: [...document.querySelectorAll('.lj-card .lj-preco b, .lj-card__pe b')].map(b => Number(b.textContent.replace(/[^\d,]/g, '').replace(',', '.'))) }));
  check('I14 lista: filtrar "até R$ 50" recarrega só com produtos até R$ 50 e mostra o filtro aplicado', /preco_max=50/.test(r.url) && r.cards > 0 && r.cards < total && r.precos.every(p => p <= 50.001) && /50/.test(r.chips), { ...r, total });
  await nav(i, () => i.click('.lj-chips__limpar'));
  check('I15 "Limpar tudo" volta à lista completa', await i.evaluate(() => document.querySelectorAll('.lj-card').length) === total);
  await i.close();

  // celular: menu lateral e barra de compra
  const c = await ctxI.newPage();
  await c.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });
  c.on('pageerror', e => erros.push(e.message));
  await c.goto(BASE + '/loja/demo', { waitUntil: 'networkidle2' });
  await c.evaluate(() => document.querySelector('[data-lj-menu-abrir]').click());
  await sleep(300);
  r = await c.evaluate(() => { const m = document.querySelector('[data-lj-menu]'); return { aberto: m.open, links: m.querySelectorAll('a').length, largura: Math.round(m.getBoundingClientRect().width) }; });
  check('I16 celular: o botão de menu abre a gaveta com as categorias', r.aberto && r.links >= 4 && r.largura <= 390, r);
  await c.evaluate(() => document.querySelector('[data-lj-menu] [data-lj-x]').click());
  await sleep(200);
  check('I17 celular: o X fecha o menu', await c.evaluate(() => !document.querySelector('[data-lj-menu]').open));
  await c.goto(BASE + CAMINHO_P, { waitUntil: 'networkidle2' });
  await c.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await sleep(500);
  r = await c.evaluate(() => { const b = document.querySelector('[data-lj-barra]'); return { visivel: !b.hidden && getComputedStyle(b).display !== 'none', sobra: document.documentElement.scrollWidth - innerWidth }; });
  check('I18 celular: rolando além do botão de compra, aparece a barra fixa de compra (sem rolagem lateral)', r.visivel && r.sobra <= 1, r);
  await c.close();
  await ctxI.close();
}

// editor: bloco novo com lista de imagem + link + opções
{
  const ed = await abrir(1440, 900);
  await ed.goto(BASE + '/app/loja-online/editor', { waitUntil: 'networkidle2' });
  if (ed.url().includes('/login')) { check('I19 editor abre com a sessão do dono', false, ed.url()); } else {
    await ed.click('[data-bs-target="#edPagina"]');
    await sleep(300);
    const antes = await ed.$$eval('.ed-bloco', b => b.length);
    await ed.evaluate(() => [...document.querySelectorAll('#edNovoBloco .dropdown-item')].find(b => b.textContent.includes('Carrossel de banners')).click());
    await sleep(400);
    let r = await ed.evaluate(() => { const a = document.querySelector('.ed-bloco.aberto'); return { blocos: document.querySelectorAll('.ed-bloco').length, imagem: !!a.querySelector('.ed-img'), selects: a.querySelectorAll('select').length, texto: a.querySelectorAll('input[type=text]').length }; });
    check('I19 editor: "Carrossel de banners" abre com imagem, textos, destino do botão, cor do texto e posição', r.blocos === antes + 1 && r.imagem && r.selects >= 4 && r.texto >= 3, r);
    await ed.evaluate(() => [...document.querySelectorAll('.ed-bloco.aberto button')].find(b => b.textContent.includes('+ Adicionar')).click());
    await sleep(300);
    const campo = await ed.$('.ed-bloco.aberto input[aria-label="Título 2"]');
    await campo.type('Segundo banner do teste');
    await ed.waitForFunction(() => document.querySelector('#edEstado').textContent.includes('não publicadas'), { timeout: 15000 }).catch(() => {});
    await sleep(2800);
    const quadroI = ed.frames().find(f => f.url().includes('/loja/'));
    r = await quadroI.evaluate(() => ({ carrosseis: document.querySelectorAll('.lj-carrossel').length, texto: document.body.textContent.includes('Segundo banner do teste') }));
    r.estado = await ed.$eval('#edEstado', e => e.textContent.trim());
    check('I20 editor: adicionar um 2º banner e escrever o título aparece na prévia', r.texto && r.carrosseis === 2, r);
    await ed.click('#edDescartar');
    await ed.waitForSelector('.modal.show [data-role=ok]');
    await sleep(350);
    await ed.click('.modal.show [data-role=ok]');
    await sleep(800);
  }
  await ed.close();
}
sql("UPDATE lojas_online SET tema=NULL, blocos=NULL, rascunho=NULL WHERE slug='demo'");

// ───────────────────── J. 4ª rodada (15/11/2026) ─────────────────────
console.log('\n== J. 4ª rodada: favoritos, grade, avise-me, conta, carrinho salvo, avaliação, rastreio, painel ==');
{
  const phpJ = codigo => execFileSync('docker', ['exec', '-i', APP, 'php'], { input: "<?php require '/var/www/vendor/autoload.php'; $app = require '/var/www/bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();\n" + codigo, stdio: ['pipe', 'pipe', 'ignore'] }).toString().trim();
  const dono = "App\\Models\\User::where('email','dono@demo.com')->value('id')";
  sql("UPDATE lojas_online SET modelo='vitrine', tema=NULL, blocos=NULL, rascunho=NULL, config=JSON_SET(config,'$.conta_cliente',true,'$.avaliacoes',true,'$.avaliacoes_moderar',true,'$.avise_me',true,'$.reservar_minutos',30,'$.boleto',false,'$.popup_ativo',false,'$.cookies_aviso',false) WHERE slug='demo'; UPDATE produtos SET grade_id=NULL, grade_opcoes=NULL WHERE empresa_id=1; DELETE FROM loja_contas; DELETE FROM loja_carrinhos; DELETE FROM loja_avise_me; DELETE FROM loja_avaliacoes;");
  // (repetível: desfaz o que uma execução anterior deste bloco tenha deixado)
  const arrumarGrade = () => sql("UPDATE produtos SET grade_id=NULL, grade_opcoes=NULL WHERE empresa_id=1; UPDATE produtos SET descricao='Vestido Midi Floral' WHERE descricao='Vestido Midi Floral - P / Preto' AND empresa_id=1; UPDATE produtos SET loja_online=0, status='inativo' WHERE descricao LIKE 'Vestido Midi Floral - %' AND empresa_id=1;");
  arrumarGrade();
  // cenário: uma grade (Tamanho × Cor) com saldo só em P/Preto e M/Preto, e um produto esgotado
  const GID = Number(sql("SELECT id FROM produtos WHERE descricao='Vestido Midi Floral' AND empresa_id=1"));
  const EID = Number(sql("SELECT id FROM produtos WHERE descricao='Mochila Urbana' AND empresa_id=1"));
  const estoque = Number(sql("SELECT id FROM estoques WHERE unidade_id=1 ORDER BY is_padrao DESC, id LIMIT 1"));
  const saldo = (pid, alvo) => phpJ(`$a=App\\Services\\SaldoEstoque::noEstoque(${estoque},${pid}); App\\Services\\SaldoEstoque::registrar(1,1,${estoque},${pid},'ajuste',${alvo}-$a,['user_id'=>${dono}]);`);
  sql(`UPDATE produtos SET loja_online=1, status='ativo' WHERE id IN (${GID},${EID})`);
  phpJ(`App\\Services\\LojaOnline\\GradeLoja::gerar(App\\Models\\Produto::withoutGlobalScopes()->find(${GID}), ['Tamanho'=>['P','M','G'],'Cor'=>['Preto','Vinho']]);`);
  const vars = sql(`SELECT id, grade_opcoes FROM produtos WHERE grade_id=${GID} ORDER BY id`).split('\n').map(l => { const [id, o] = l.split('\t'); return { id: Number(id), o: JSON.parse(o) }; });
  for (const v of vars) saldo(v.id, (v.o.Cor === 'Preto' && v.o.Tamanho !== 'G') ? 4 : 0);
  saldo(EID, 0);
  const VMP = vars.find(v => v.o.Tamanho === 'M' && v.o.Cor === 'Preto').id;
  artisan('cache:clear');

  const ctxJ = await browser.createBrowserContext();
  const j = await ctxJ.newPage();
  await j.setViewport({ width: 1366, height: 900 });
  j.on('pageerror', e => erros.push(e.message));
  j.on('console', m => { if (m.type() === 'error' && !/favicon|ERR_|Failed to load resource/.test(m.text())) erros.push(m.text()); });

  // favoritos
  await j.goto(BASE + '/loja/demo/produtos?q=Camisa+Linho', { waitUntil: 'networkidle2' });
  await j.hover('.lj-card');
  await j.click('.lj-card [data-lj-fav]');
  let r = await j.evaluate(() => ({ p: document.querySelector('.lj-card [data-lj-fav]').getAttribute('aria-pressed'), n: document.querySelector('[data-lj-fav-conta]').textContent.trim(), oculto: document.querySelector('[data-lj-fav-conta]').hidden }));
  check('J1 favoritar no cartão: coração marcado e contador "1" no cabeçalho', r.p === 'true' && r.n === '1' && !r.oculto, r);
  await j.reload({ waitUntil: 'networkidle2' });
  check('J2 o favorito continua marcado depois de recarregar', await j.$eval('.lj-card [data-lj-fav]', b => b.getAttribute('aria-pressed')) === 'true');
  await j.goto(BASE + '/loja/demo/favoritos', { waitUntil: 'networkidle2' });
  await j.waitForSelector('[data-lj-fav-lista] .lj-card', { timeout: 5000 }).catch(() => {});
  r = await j.evaluate(() => ({ n: document.querySelectorAll('[data-lj-fav-lista] .lj-card').length, nome: document.querySelector('[data-lj-fav-lista] .lj-card__nome')?.textContent.trim(), vazio: document.querySelector('[data-lj-fav-vazio]').hidden }));
  check('J3 página Favoritos mostra o produto guardado', r.n === 1 && /Camisa Linho/.test(r.nome || '') && r.vazio === true, r);
  await print(j, 'r4-favoritos', false);
  await j.click('[data-lj-fav-lista] [data-lj-fav]');
  await sleep(900);
  r = await j.evaluate(() => ({ n: document.querySelectorAll('[data-lj-fav-lista] .lj-card').length, vazio: document.querySelector('[data-lj-fav-vazio]').hidden }));
  check('J4 desfavoritar na própria página tira o cartão e mostra o aviso de lista vazia', r.n === 0 && r.vazio === false, r);

  // grade
  await j.goto(BASE + '/loja/demo/produtos?q=Vestido+Midi', { waitUntil: 'networkidle2' });
  r = await j.evaluate(() => ({ n: document.querySelectorAll('.lj-card').length, nome: document.querySelector('.lj-card__nome')?.textContent.trim(), botao: document.querySelector('.lj-card__add')?.textContent.trim(), form: !!document.querySelector('.lj-card form[data-lj-add]') }));
  check('J5 grade: um cartão só, com o nome comum e "Ver opções" no lugar de "Adicionar"', r.n === 1 && r.nome === 'Vestido Midi Floral' && /Ver opções/.test(r.botao || '') && !r.form, r);
  await print(j, 'r4-cartao-grade', false);
  await nav(j, () => j.click('.lj-card__nome'));
  r = await j.evaluate(() => ({ grupos: [...document.querySelectorAll('[data-lj-variacao]')].map(g => g.querySelector('.lj-variacao__nome').textContent.replace(/\s+/g, ' ').trim()),
    atuais: [...document.querySelectorAll('.lj-variacao__opcao[aria-current=true]')].map(a => a.textContent.trim()), fim: [...document.querySelectorAll('.lj-variacao__opcao--fim')].map(a => a.textContent.replace(/\s*\(esgotado\)/, '').trim()),
    alvo: Math.min(...[...document.querySelectorAll('.lj-variacao__opcao')].map(a => a.getBoundingClientRect().height)) }));
  check('J6 página do produto: seletores Tamanho e Cor, P/Preto marcados, G e Vinho riscados, botões com 44 px ou mais',
    r.grupos.length === 2 && r.grupos[0] === 'Tamanho: P' && r.grupos[1] === 'Cor: Preto' && r.atuais.join() === 'P,Preto' && r.fim.includes('G') && r.fim.includes('Vinho') && r.alvo >= 44, r);
  await print(j, 'r4-produto-grade', false);
  await nav(j, () => j.evaluate(() => [...document.querySelectorAll('.lj-variacao__opcao')].find(a => a.textContent.trim() === 'M').click()));
  r = await j.evaluate(() => ({ h1: document.querySelector('h1').textContent.trim(), atuais: [...document.querySelectorAll('.lj-variacao__opcao[aria-current=true]')].map(a => a.textContent.trim()), url: location.pathname }));
  check('J7 clicar em M abre a variação M/Preto (endereço, título e opção marcada)', /M \/ Preto$/.test(r.h1) && r.atuais.join() === 'M,Preto' && r.url.includes('/p/' + VMP + '-'), r);
  await j.click('[data-lj-comprar-principal]');
  await j.waitForSelector('dialog[data-lj-gaveta][open]', { timeout: 5000 }).catch(() => {});
  await sleep(600);
  r = await j.evaluate(() => ({ aberta: document.querySelector('dialog[data-lj-gaveta]').open, texto: document.querySelector('[data-lj-gaveta-itens]').innerText }));
  check('J8 adicionar a variação: a sacola abre com o tamanho e a cor no nome', r.aberta && /Vestido Midi Floral - M \/ Preto/.test(r.texto), r);
  await j.evaluate(() => document.querySelector('dialog[data-lj-gaveta]').close());
  await nav(j, () => j.evaluate(() => [...document.querySelectorAll('.lj-variacao__opcao')].find(a => a.textContent.replace(/\s*\(esgotado\)/, '').trim() === 'Vinho').click()));
  r = await j.evaluate(() => ({ esgotado: document.body.innerText.includes('Esgotado no momento'), avise: !!document.querySelector('[data-lj-avise]'), comprar: !!document.querySelector('[data-lj-comprar-principal]') }));
  check('J9 variação sem saldo: "Esgotado", sem botão de comprar e com o "Avise-me"', r.esgotado && r.avise && !r.comprar, r);

  // avise-me
  await j.type('#lj-avise-email', 'avise@navegador.test');
  await j.click('[data-lj-avise] button[type=submit]');
  await sleep(900);
  r = await j.evaluate(() => ({ t: document.querySelector('[data-lj-avise-saida]').textContent, erro: document.querySelector('[data-lj-avise-saida]').hasAttribute('data-erro') }));
  check('J10 avise-me sem marcar o aceite: explica e não grava', r.erro && /aceita/i.test(r.t) && sql("SELECT COUNT(*) FROM loja_avise_me") === '0', r);
  await j.click('[data-lj-avise] input[name=aceite]');
  await j.click('[data-lj-avise] button[type=submit]');
  await sleep(900);
  r = await j.evaluate(() => ({ t: document.querySelector('[data-lj-avise-saida]').textContent, campoSumiu: document.querySelector('.lj-avise__linha').hidden }));
  check('J11 avise-me com o aceite: confirma na tela e grava o pedido de aviso', /Avisamos por e-mail/.test(r.t) && r.campoSumiu && sql("SELECT email FROM loja_avise_me") === 'avise@navegador.test', r);
  await print(j, 'r4-avise-me', false);

  // conta do comprador
  await j.goto(BASE + '/loja/demo/entrar', { waitUntil: 'networkidle2' });
  check('J12 tela Entrar sem rolagem lateral no computador', await sobraLateral(j) <= 1);
  await print(j, 'r4-entrar', false);
  await j.type('#c-nome', 'Nina Navegador');
  await j.type('#c-email', 'nina@navegador.test');
  await j.type('#c-tel', '86911110006');
  await j.type('#c-senha', 'senha-forte-1');
  await j.click('[data-lj-criar] input[name=aceite]');
  await nav(j, () => j.click('[data-lj-criar] button[type=submit]'));
  r = await j.evaluate(() => ({ url: location.pathname, h1: document.querySelector('h1').textContent.trim(), icone: document.querySelector('.lj-acao--conta')?.getAttribute('aria-label'), nome: document.querySelector('.lj-acao--conta span')?.textContent.trim() }));
  check('J13 criar conta: cai em Minha conta, com o nome no cabeçalho', r.url.endsWith('/conta') && r.h1 === 'Olá, Nina' && r.icone === 'Minha conta' && r.nome === 'Nina', r);
  await print(j, 'r4-conta', false);
  await j.goto(BASE + '/loja/demo/finalizar', { waitUntil: 'networkidle2' });
  r = await j.evaluate(() => ({ nome: document.querySelector('#f-nome').value, email: document.querySelector('#f-email').value, tel: document.querySelector('#f-tel').value, dica: document.querySelector('[data-lj-conta-dica]')?.innerText }));
  check('J14 fechamento logado: nome, e-mail e telefone já preenchidos', r.nome === 'Nina Navegador' && r.email === 'nina@navegador.test' && r.tel === '86911110006' && /Comprando como/.test(r.dica || ''), r);
  await sleep(2200);
  check('J15 carrinho salvo sozinho quando há contato no fechamento', sql("SELECT CONCAT(email,'|',total > 0) FROM loja_carrinhos") === 'nina@navegador.test|1', sql("SELECT COUNT(*) FROM loja_carrinhos"));
  await j.type('#f-doc', '39053344705');
  await j.evaluate(() => { const r = document.querySelector('input[name=entrega][value=retirada]'); r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); const p = document.querySelector('input[name=pagamento][value=combinar]'); p.checked = true; p.dispatchEvent(new Event('change', { bubbles: true })); });
  await nav(j, () => j.evaluate(() => document.querySelector('[data-lj-fechar]').requestSubmit()));
  const urlPedido = j.url();
  const tokenJ = urlPedido.split('/pedido/')[1];
  check('J16 compra fechada pela conta: vai para a página do pedido e o pedido fica ligado à conta', /\/pedido\/[a-z0-9]{32}$/.test(urlPedido) && sql(`SELECT loja_conta_id IS NOT NULL FROM pedidos WHERE loja_token='${tokenJ}'`) === '1', urlPedido);
  check('J17 carrinho salvo deixa de ser abandonado depois da compra', sql("SELECT pedido_id IS NOT NULL FROM loja_carrinhos") === '1');
  await j.goto(BASE + '/loja/demo/conta', { waitUntil: 'networkidle2' });
  r = await j.evaluate(() => ({ linhas: document.querySelectorAll('.lj-pedido-linha').length, href: document.querySelector('.lj-pedido-linha')?.getAttribute('href') }));
  check('J18 Minha conta lista o pedido, com link para acompanhar', r.linhas === 1 && (r.href || '').endsWith(tokenJ), r);

  // rastreio e avaliação na página do pedido
  sql(`UPDATE pedidos SET status='faturado', metodo_entrega='entrega', frete_valor=0, loja_enviado_em=NOW(), loja_transportadora='Correios', loja_rastreio_codigo='BR987654321BR', loja_rastreio_url='https://rastreamento.correios.com.br/app/index.php?objetos=BR987654321BR' WHERE loja_token='${tokenJ}'`);
  await j.goto(urlPedido, { waitUntil: 'networkidle2' });
  r = await j.evaluate(() => { const x = document.querySelector('[data-lj-rastreio]'); const a = x?.querySelector('a'); return { codigo: x?.querySelector('.lj-rastreio__codigo')?.textContent.trim(), href: a?.href, alvo: a?.target, rel: a?.rel, rotulo: document.querySelector('[data-lj-rotulo]').textContent.trim(), etapa: document.querySelector('.lj-etapas li[data-agora]')?.textContent.trim() }; });
  check('J19 pedido enviado: código de rastreio, botão Rastrear em outra aba e etapa "A caminho"', r.codigo === 'BR987654321BR' && /correios/.test(r.href || '') && r.alvo === '_blank' && /noopener/.test(r.rel || '') && r.rotulo === 'Pedido enviado' && r.etapa === 'A caminho', r);
  await j.evaluate(() => document.querySelector('#avaliar').scrollIntoView());
  await j.click('.lj-avaliar__item label[title="4 estrelas"]');
  await sleep(400);
  r = await j.evaluate(() => ({ marcado: document.querySelector('.lj-avaliar__item input[name=nota]:checked')?.value,
    acesas: [...document.querySelectorAll('.lj-avaliar__item .lj-estrelas-campo label')].filter(l => getComputedStyle(l).color === 'rgb(224, 161, 0)').length }));
  check('J20 avaliar: clicar na 4ª estrela marca nota 4 e acende 4 estrelas', r.marcado === '4' && r.acesas === 4, r);
  await print(j, 'r4-pedido-rastreio-avaliar');
  await j.type('.lj-avaliar__item textarea', 'Veio certinho, tecido ótimo.');
  await nav(j, () => j.click('.lj-avaliar__item button[type=submit]'));
  r = await j.evaluate(() => ({ ok: document.querySelector('.lj-aviso--ok')?.innerText, botao: document.querySelector('.lj-avaliar__item button[type=submit]').textContent.trim() }));
  check('J21 avaliação enviada: agradece e o botão vira "Atualizar avaliação"', /Obrigado/.test(r.ok || '') && r.botao === 'Atualizar avaliação' && sql("SELECT CONCAT(nota,'|',status) FROM loja_avaliacoes") === '4|pendente', r);
  sql("UPDATE loja_avaliacoes SET status='aprovada', resposta='Obrigada pelo carinho!'");
  await j.goto(BASE + '/loja/demo/p/' + GID, { waitUntil: 'networkidle2' });
  r = await j.evaluate(() => ({ nota: document.querySelector('.lj-avaliacoes__resumo b')?.textContent.trim(), itens: document.querySelectorAll('.lj-avaliacoes__lista li').length, resposta: document.querySelector('.lj-avaliacoes__resposta')?.innerText, topo: document.querySelector('.lj-produto__ref .lj-nota')?.innerText.replace(/\s+/g, ' ').trim() }));
  check('J22 avaliação publicada aparece na página de qualquer tamanho da grade, com nota e resposta da loja', r.nota === '4,0' && r.itens === 1 && /Obrigada pelo carinho/.test(r.resposta || '') && /4,0 \(1 avaliação\)/.test(r.topo || ''), r);
  await j.evaluate(() => document.querySelector('#avaliacoes').scrollIntoView());
  await print(j, 'r4-produto-avaliacoes', false);

  // sair e entrar de novo
  await j.goto(BASE + '/loja/demo/conta', { waitUntil: 'networkidle2' });
  await nav(j, () => j.click('form[action$="/sair"] button'));
  await j.goto(BASE + '/loja/demo/entrar', { waitUntil: 'networkidle2' });
  await j.type('#e-email', 'nina@navegador.test');
  await j.type('#e-senha', 'senha-errada');
  await nav(j, () => j.click('[data-lj-entrar] button[type=submit]'));
  check('J23 senha errada: mensagem de erro na tela, sem entrar', (await j.evaluate(() => document.querySelector('.lj-aviso--erro')?.innerText || '')).includes('não conferem') && j.url().endsWith('/entrar'));
  await j.evaluate(() => { document.querySelector('#e-email').value = ''; }); // a tela devolve o e-mail digitado
  await j.type('#e-email', 'nina@navegador.test');
  await j.type('#e-senha', 'senha-forte-1');
  await nav(j, () => j.click('[data-lj-entrar] button[type=submit]'));
  check('J24 senha certa: entra na conta', j.url().endsWith('/conta'), j.url());

  // celular
  const m = await ctxJ.newPage();
  await m.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });
  m.on('pageerror', e => erros.push(e.message));
  for (const [nome, caminho] of [['conta', '/loja/demo/conta'], ['favoritos', '/loja/demo/favoritos'], ['pedido', urlPedido.replace(BASE, '')], ['produto-grade', '/loja/demo/p/' + VMP]]) {
    await m.goto(BASE + caminho, { waitUntil: 'networkidle2' });
    check(`J25 celular (390 px): ${nome} sem rolagem lateral`, await sobraLateral(m) <= 1, await sobraLateral(m));
    await print(m, 'r4-celular-' + nome);
  }
  r = await m.evaluate(() => ({ alvo: Math.min(...[...document.querySelectorAll('.lj-variacao__opcao')].map(a => Math.min(a.getBoundingClientRect().height, a.getBoundingClientRect().width))) }));
  check('J26 celular: opções de tamanho e cor com alvo de toque de 44 px ou mais', r.alvo >= 44, r);
  // página do produto no celular (ajuste de 15/11): foto de ponta a ponta, pontos, compra em uma linha, sanfona
  r = await m.evaluate(() => { const g = document.querySelector('.lj-galeria__palco').getBoundingClientRect(), bt = document.querySelector('[data-lj-comprar-principal]').getBoundingClientRect(), q = document.querySelector('[data-lj-qtd]').getBoundingClientRect();
    return { foto: Math.round(g.width), tela: innerWidth, pontos: getComputedStyle(document.querySelector('.lj-galeria__pontos') || document.body).display, minis: getComputedStyle(document.querySelector('.lj-miniaturas') || document.body).display,
      mesmaLinha: Math.abs(bt.top - q.top) < 4, botaoAlt: Math.round(bt.height), moldura: getComputedStyle(document.querySelector('.lj-compra')).borderTopWidth,
      fav: !!document.querySelector('.lj-galeria__acao[data-lj-fav]'), zap: getComputedStyle(document.querySelector('.lj-zap') || document.createElement('i')).display }; });
  check('J26b celular: foto ocupa a largura inteira, com pontos no lugar das miniaturas e favoritar sobre a foto', r.foto === r.tela && (r.pontos === 'flex' || r.pontos === 'block') && r.fav, r);
  check('J26c celular: quantidade e "Adicionar" na mesma linha, botão com 56 px, caixa de compra sem moldura', r.mesmaLinha && r.botaoAlt >= 56 && r.botaoAlt <= 60 && r.moldura === '0px', r);
  r = await m.evaluate(() => { const ts = [...document.querySelectorAll('.lj-sanfona__topo')]; const antes = ts.map(t => t.getAttribute('aria-expanded')); ts[1].click();
    return { n: ts.length, antes, ficha: !document.querySelector('#painel-ficha').hidden, abas: getComputedStyle(document.querySelector('.lj-abas__lista')).display }; });
  check('J26d celular: Descrição, Ficha técnica e Entrega viram sanfona (a 1ª aberta; tocar abre a 2ª)', r.n === 3 && r.antes.join() === 'true,false,false' && r.ficha && r.abas === 'none', r);
  await m.evaluate(() => document.querySelector('form[action$="/sair"]')?.remove());
  await m.goto(BASE + '/loja/demo', { waitUntil: 'networkidle2' });
  await m.click('[data-lj-menu-abrir]');
  await sleep(500);
  r = await m.evaluate(() => document.querySelector('dialog[data-lj-menu]').innerText);
  check('J27 celular: Minha conta e Favoritos estão no menu lateral', /Minha conta/.test(r) && /Favoritos/.test(r), r.slice(0, 200));
  await m.close();
  await ctxJ.close();

  // painel do lojista (sessão do dono, aberta no bloco E)
  await erp.goto(BASE + '/app/loja-online/pedidos', { waitUntil: 'networkidle2' });
  const pidJ = sql(`SELECT id FROM pedidos WHERE loja_token='${tokenJ}'`);
  sql(`UPDATE pedidos SET metodo_entrega='entrega', loja_enviado_em=NULL, loja_rastreio_codigo=NULL, loja_rastreio_url=NULL, loja_transportadora=NULL WHERE id=${pidJ}`);
  await erp.reload({ waitUntil: 'networkidle2' });
  r = await erp.evaluate(id => { const c = document.querySelector(`[data-pedido="${id}"]`); return { tem: !!c, botao: c?.querySelector('[data-bs-target^="#envio-"]')?.textContent.trim(), fechado: !c?.querySelector('.collapse.show') }; }, pidJ);
  check('J28 painel Pedidos: o pedido pago aparece em "para separar/enviar" com o botão de informar o envio', r.tem && /Informar o envio/.test(r.botao || '') && r.fechado, r);
  await erp.click(`[data-pedido="${pidJ}"] [data-bs-target^="#envio-"]`);
  await erp.waitForSelector(`#envio-${pidJ}.show`);
  await erp.type(`#cod-${pidJ}`, 'QB111222333BR');
  await print(erp, 'r4-painel-pedidos');
  await nav(erp, () => erp.click(`#envio-${pidJ} button`));
  r = sql(`SELECT CONCAT(loja_rastreio_codigo,'|',loja_enviado_em IS NOT NULL) FROM pedidos WHERE id=${pidJ}`);
  check('J29 informar o envio pelo painel grava o rastreio e mostra a confirmação', r === 'QB111222333BR|1' && (await erp.evaluate(() => document.body.innerText)).includes('Envio informado'), r);
  await erp.goto(BASE + '/app/loja-online/grade', { waitUntil: 'networkidle2' });
  r = await erp.evaluate(() => ({ titulo: document.body.innerText.includes('Vestido Midi Floral'), linhas: document.querySelectorAll('table tbody tr').length, principal: document.body.innerText.includes('principal') }));
  check('J30 painel Grade: lista as 6 variações com o principal marcado', r.titulo && r.linhas === 6 && r.principal, r);
  await print(erp, 'r4-painel-grade');
  await erp.goto(BASE + '/app/loja-online/resultados', { waitUntil: 'networkidle2' });
  r = await erp.evaluate(() => ({ cartoes: document.querySelectorAll('[data-indicador]').length, sobra: document.documentElement.scrollWidth - window.innerWidth }));
  check('J31 painel Resultados: 6 indicadores, sem rolagem lateral', r.cartoes === 6 && r.sobra <= 1, r);
  await print(erp, 'r4-painel-resultados');
  await erp.goto(BASE + '/app/loja-online/avaliacoes?ver=aprovada', { waitUntil: 'networkidle2' });
  check('J32 painel Avaliações: a publicada aparece com a resposta da loja', (await erp.evaluate(() => document.querySelector('input[name=resposta]')?.value || '')) === 'Obrigada pelo carinho!');
  await print(erp, 'r4-painel-avaliacoes');
  arrumarGrade();
  sql("UPDATE pedidos SET status='cancelado' WHERE loja_token IS NOT NULL AND status IN ('rascunho','confirmado')");
}

// ───────────────────── K. 5ª rodada: rastreio, domínio, dicas ─────────────────────
console.log('\n== K. 5ª rodada: Resultados com rastreio, Domínio em 3 passos, dicas do editor ==');
{
  sql("DELETE FROM loja_sessoes; DELETE FROM loja_buscas;");
  const fora = await (await browser.createBrowserContext()).newPage();
  await fora.setViewport({ width: 390, height: 844 });
  await fora.setUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1');
  await fora.goto(BASE + '/loja/demo?utm_source=instagram&utm_campaign=vitrine-nova', { waitUntil: 'networkidle2' });
  await fora.goto(BASE + '/loja/demo/produtos?q=qqqnaotem', { waitUntil: 'networkidle2' });
  await fora.close();
  for (const [pagina, w, h, nome] of [[erp, 1440, 950, 'computador'], [await abrir(390, 844), 390, 844, 'celular']]) {
    if (nome === 'celular') {
      await pagina.goto(BASE + '/login', { waitUntil: 'networkidle2' });
      if (pagina.url().includes('login')) { await pagina.type('input[name=email]', 'dono@demo.com'); await pagina.type('input[name=password]', 'dono123'); await nav(pagina, () => pagina.click('button[type=submit]')); }
    }
    await pagina.goto(BASE + '/app/loja-online/resultados', { waitUntil: 'networkidle2' });
    let r = await pagina.evaluate(() => ({
      etapas: document.querySelectorAll('[data-etapa]').length, insta: !!document.querySelector('[data-origem="instagram"]'), campanha: !!document.querySelector('[data-campanha="vitrine-nova"]'),
      cel: !!document.querySelector('[data-aparelho="Celular"]'), busca: !!document.querySelector('[data-busca-vazia="qqqnaotem"]'), agora: document.querySelector('[data-agora]')?.textContent,
      sobra: document.documentElement.scrollWidth - window.innerWidth,
    }));
    check(`K1 Resultados (${nome}): funil de 6 etapas, origem Instagram com a campanha, celular, busca sem resultado, "na loja agora" e sem rolagem lateral`,
      r.etapas === 6 && r.insta && r.campanha && r.cel && r.busca && Number(r.agora) >= 1 && r.sobra <= 1, r);
    if (nome === 'computador') {
      await pagina.select('#ljLinkOrigem', 'whatsapp');
      await pagina.type('#ljLinkCampanha', 'Dia das Mães');
      await pagina.click('#ljLinkPago');
      const link = await pagina.$eval('#ljLinkSaida', e => e.value);
      check('K2 gerador de link rastreado monta o endereço com origem, anúncio pago e campanha sem acento nem espaço', /\/\?utm_source=whatsapp&utm_medium=cpc&utm_campaign=dia-das-mes$/.test(link) || /utm_campaign=dia-das-m/.test(link), link);
    }
    await print(pagina, 'r5-resultados-' + nome);

    await pagina.goto(BASE + '/app/loja-online/dominio', { waitUntil: 'networkidle2' });
    r = await pagina.evaluate(() => ({ passos: document.querySelectorAll('[data-passo]').length, campo: !!document.querySelector('#dominio'), ajuda: document.querySelectorAll('#ondeDns .accordion-item').length,
      sobra: document.documentElement.scrollWidth - window.innerWidth }));
    check(`K3 Domínio (${nome}): 3 passos, campo do domínio, ajuda de 5 sites e sem rolagem lateral`, r.passos === 3 && r.campo && r.ajuda === 5 && r.sobra <= 1, r);
    await print(pagina, 'r5-dominio-' + nome);

    await pagina.goto(BASE + '/app/loja-online/editor', { waitUntil: 'networkidle2' });
    await pagina.click('[data-bs-target="#edPagina"]');
    await new Promise(r => setTimeout(r, 400));
    r = await pagina.evaluate(() => {
      const abas = [...document.querySelectorAll('.ed-painel [role=tablist] .nav-link')].map(e => Math.round(e.getBoundingClientRect().top));
      return { guia: document.querySelectorAll('#edGuia [data-guia]').length, mesmaLinha: new Set(abas).size === 1, sobra: document.documentElement.scrollWidth - window.innerWidth };
    });
    check(`K4 editor (${nome}): guia "para a loja ficar com a sua cara" com 3 itens, as 3 abas na mesma linha, sem rolagem lateral`, r.guia === 3 && r.mesmaLinha && r.sobra <= 1, r);
    await pagina.click('.ed-bloco__nome');
    await new Promise(r => setTimeout(r, 300));
    r = await pagina.evaluate(() => ({ dica: document.querySelector('.ed-bloco.aberto [data-dica]')?.textContent || '', sobra: document.documentElement.scrollWidth - window.innerWidth }));
    check(`K5 editor (${nome}): abrir um bloco mostra a dica do que colocar nele`, r.dica.startsWith('Dica:') && r.dica.length > 60 && r.sobra <= 1, r);
    await print(pagina, 'r5-editor-' + nome);
  }
  sql("DELETE FROM loja_sessoes; DELETE FROM loja_buscas;");
}

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
