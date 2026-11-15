// Capturas da DEMONSTRAÇÃO da loja online com fotos reais (14/11/2026) — ambiente descartável `ed-app`.
//   docker exec -i ed-app php < scripts/loja-online-demo.php     (monta o catálogo)
//   BASE=http://10.213.80.6 MYSQL=ec-mysql BANCO=erp_demo CHROME=… NODE_PATH=… OUT=/pasta [MODELOS=vitrine,atelie] node scripts/loja-online-demo.capturas.mjs
// Para cada modelo liga o ramo de produtos que combina com ele, aplica banners de exemplo e fotografa
// início, lista e produto (computador e celular). Não valida nada: é só para OLHAR o resultado.
import { createRequire } from 'module';
import { execFileSync } from 'child_process';
const require = createRequire(process.env.NODE_PATH + '/');
const puppeteer = require('puppeteer-core');
const BASE = process.env.BASE || 'http://10.213.80.6', OUT = process.env.OUT, BANCO = process.env.BANCO || 'erp_demo';
const sql = q => execFileSync('docker', ['exec', '-i', process.env.MYSQL || 'ec-mysql', 'mysql', '--default-character-set=utf8mb4', '-uerp', '-perp', BANCO, '-N', '-B'], { input: q, stdio: ['pipe', 'pipe', 'ignore'] }).toString().trim();
const img = id => `loja-online/1/${id}demo000000000000000000000.jpg`;
const vit = (titulo, origem, quantidade, colunas, formato = 'grade') => ({ tipo: 'vitrine', titulo, origem, categoria_id: null, quantidade: String(quantidade), colunas: String(colunas), formato });
const slide = (imagem, titulo, texto, botao_texto, botao_link, tom, lado = 'esquerda') => ({ imagem: img(imagem), titulo, texto, botao_texto, botao_link, tom, lado });
const ban = (imagem, titulo, texto, botao_link) => ({ imagem: imagem ? img(imagem) : '', titulo, texto, botao_link });
const confianca = { tipo: 'beneficios', itens: [{ icone: 'truck', titulo: 'Entrega para sua casa', texto: 'Frete calculado pelo CEP' }, { icone: 'bag-check', titulo: 'Retire na loja', texto: 'Sem pagar frete' },
  { icone: 'qr-code', titulo: 'PIX ou cartão', texto: 'Em até 6x no cartão' }, { icone: 'chat-dots', titulo: 'Atendimento', texto: 'Fale com a loja' }] };
const news = { tipo: 'newsletter', titulo: 'Receba as novidades primeiro', texto: 'Lançamentos e ofertas no seu e-mail.', botao_texto: 'Quero receber' };
const DEMOS = {
  vitrine: { nome: 'Casa Aurora', grupos: ['moda', 'presentes', 'eletronicos'], blocos: [
    { tipo: 'carrossel', altura: 'media', lista: [slide('1483985988355', 'Tudo o que você procura, num só lugar', 'Moda, beleza e tecnologia com entrega para sua casa.', 'Ver produtos', 'produtos', 'claro'),
      slide('1607082348824', 'Semana de ofertas', 'Até 25% de desconto em produtos selecionados.', 'Aproveitar', 'produtos?promocao=1', 'claro')] },
    confianca, { tipo: 'categorias', titulo: 'Compre por categoria', estilo: 'circulos' }, vit('Em oferta', 'promocoes', 8, 4, 'carrossel'),
    { tipo: 'banners', titulo: '', arranjo: 'iguais', lista: [ban('1490481651871', 'Moda', 'A estação chegou', 'produtos?ordem=novos'), ban('1596462502278', 'Beleza', 'Cuide de você', 'produtos?destaque=1'), ban('1498049794561', 'Tecnologia', 'Para o dia a dia', 'produtos')] },
    vit('Destaques', 'destaques', 8, 4), vit('Novidades', 'novidades', 8, 4, 'carrossel'), news] },
  atelie: { nome: 'Maison Lume', grupos: ['moda'], blocos: [
    { tipo: 'faixa', texto: 'Frete grátis a partir de R$ 299  ·  Troca fácil na loja' },
    { tipo: 'banner', titulo: 'Nova coleção', subtitulo: 'Peças escolhidas uma a uma para a estação.', botao_texto: 'Ver a coleção', botao_link: 'produtos?ordem=novos', imagem: img('1445205170230'), estilo: 'editorial', altura: 'alta' },
    { tipo: 'banners', titulo: '', arranjo: 'destaque', lista: [ban('1441984904996', 'A estação', 'O que acabou de entrar', 'produtos?ordem=novos'), ban('1558769132', 'Essenciais', 'Para usar sempre', 'produtos'), ban('1490481651871', 'Em oferta', 'Últimas peças', 'produtos?promocao=1')] },
    vit('Acabou de chegar', 'novidades', 8, 4), { tipo: 'categorias', titulo: 'Por categoria', estilo: 'lista' }, vit('As preferidas', 'destaques', 8, 4, 'carrossel'), news] },
  circuito: { nome: 'Ponto Tech', grupos: ['eletronicos'], blocos: [
    { tipo: 'carrossel', altura: 'media', lista: [slide('1468495244123', 'Ofertas que valem a troca', 'Smartphones, notebooks e áudio com o preço da loja.', 'Ver ofertas', 'produtos?promocao=1', 'claro'),
      slide('1498049794561', 'Som de outro nível', 'Fones e caixas de som para ouvir do seu jeito.', 'Ver áudio', 'produtos', 'escuro')] },
    { tipo: 'categorias', titulo: 'Categorias', estilo: 'cartoes' }, vit('Em promoção', 'promocoes', 10, 5, 'carrossel'),
    { tipo: 'banners', titulo: '', arranjo: 'iguais', lista: [ban('1511707171634', 'Smartphones', 'Os mais procurados', 'produtos?destaque=1'), ban('1468495244123', 'Para trabalhar', 'Notebooks e tablets', 'produtos?ordem=novos')] },
    vit('Mais procurados', 'destaques', 10, 5), confianca] },
  feira: { nome: 'Empório da Vila', grupos: ['mercado'], blocos: [
    { tipo: 'carrossel', altura: 'media', lista: [slide('1488459716781', 'Fresquinho, direto pra sua casa', 'Monte seu pedido e receba hoje, ou retire na loja.', 'Fazer pedido', 'produtos', 'claro'),
      slide('1542838132', 'Hortifrúti da semana', 'Colhido e separado para você.', 'Ver ofertas', 'produtos?promocao=1', 'claro')] },
    { tipo: 'categorias', titulo: 'O que você procura?', estilo: 'circulos' }, vit('Ofertas do dia', 'promocoes', 10, 5, 'carrossel'), vit('Os mais pedidos', 'destaques', 10, 5),
    { tipo: 'banners', titulo: '', arranjo: 'iguais', lista: [ban('1556909114', 'Para cozinhar hoje', 'Ingredientes fresquinhos', 'produtos?ordem=novos'), ban('1542838132', 'Hortifrúti', 'Direto da banca', 'produtos')] },
    vit('Para completar a lista', 'todos', 10, 5), confianca] },
  mimo: { nome: 'Doce Mimo', grupos: ['presentes'], blocos: [
    { tipo: 'faixa', texto: 'Embalagem para presente em todos os pedidos' },
    { tipo: 'carrossel', altura: 'media', lista: [slide('1596462502278', 'Um mimo para quem você gosta', 'Perfumes, maquiagem e decoração para presentear.', 'Escolher presente', 'produtos', 'escuro', 'centro')] },
    { tipo: 'categorias', titulo: 'Para cada ocasião', estilo: 'circulos' }, vit('Queridinhos', 'destaques', 8, 4),
    { tipo: 'banners', titulo: '', arranjo: 'iguais', lista: [ban('1556228453', 'Casa', 'Para deixar tudo mais bonito', 'produtos?ordem=novos'), ban('1596462502278', 'Beleza', 'Cuidado todo dia', 'produtos?promocao=1')] },
    vit('Acabou de chegar', 'novidades', 8, 4, 'carrossel'), news] },
  balcao: { nome: 'Central Distribuidora', grupos: ['tecnico'], blocos: null },
};
const grupos = JSON.parse(execFileSync('docker', ['exec', 'ed-app', 'cat', '/tmp/demo/grupos.json']).toString());
const browser = await puppeteer.launch({ executablePath: process.env.CHROME, args: ['--no-sandbox'] });
const erros = [];
async function foto(url, nome, largura, full = true) {
  const p = await browser.newPage();
  p.on('pageerror', e => erros.push(nome + ': ' + e.message));
  await p.setViewport({ width: largura, height: largura < 500 ? 844 : 900 });
  await p.goto(BASE + url, { waitUntil: 'networkidle2', timeout: 60000 });
  await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 90)); } window.scrollTo(0, 0);
    document.querySelectorAll('.lj-grade,.lj-cats').forEach(g => g.classList.add('lj-vista'));
    await Promise.all([...document.images].map(i => { i.loading = 'eager'; i.decoding = 'sync'; return i.complete ? i.decode().catch(() => 0) : new Promise(r => { i.onload = i.onerror = r; setTimeout(r, 8000); }); })); });
  await new Promise(r => setTimeout(r, 900));
  const sobra = await p.evaluate(() => document.documentElement.scrollWidth - innerWidth);
  if (sobra > 1) erros.push(`${nome}: rolagem lateral de ${sobra}px`);
  await p.screenshot({ path: `${OUT}/${nome}.png`, fullPage: full });
  await p.close();
}
for (const modelo of (process.env.MODELOS || Object.keys(DEMOS).join(',')).split(',')) {
  const d = DEMOS[modelo];
  const ids = d.grupos.flatMap(g => grupos[g]);
  sql(`UPDATE produtos SET loja_online=0; UPDATE produtos SET loja_online=1 WHERE id IN (${ids.join(',')});
       UPDATE lojas_online SET nome='${d.nome}', modelo='${modelo}', tema=NULL, rascunho=NULL, publicada=1, blocos=${d.blocos ? `'${JSON.stringify(d.blocos.map((b, i) => ({ id: 'd' + i, ...b }))).replace(/'/g, "''")}'` : 'NULL'},
       config=JSON_MERGE_PATCH(IFNULL(config,'{}'), '{"cookies_aviso":false,"popup_ativo":false,"parcelas_max":6,"parcela_minima":20,"frete_gratis_acima":299,"whatsapp":"(86) 99999-0000","whatsapp_flutuante":true,"instagram":"@lojademo","facebook":"lojademo","horario_atendimento":"Seg a sáb, 9h às 18h","email":"contato@lojademo.test","texto_rodape":"Loja de demonstração. Produtos e preços fictícios.","entrega":true,"retirada":true,"cartao":true,"pix":true}') WHERE slug='demo'`);
  let pid = ids[0];
  for (const c of ids.slice(0, 12)) { const h = await (await fetch(`${BASE}/loja/demo/p/${c}-x`)).text(); if (!h.includes('Esgotado no momento') && h.includes('lj-miniaturas')) { pid = c; break; } }
  const nomeP = sql(`SELECT descricao FROM produtos WHERE id=${pid}`);
  const slug = nomeP.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  await foto('/loja/demo', `${modelo}-inicio`, 1440);
  await foto('/loja/demo', `${modelo}-inicio-cel`, 390);
  if (!process.env.SO_INICIO) {
    await foto(`/loja/demo/p/${pid}-${slug}`, `${modelo}-produto`, 1440);
    await foto(`/loja/demo/p/${pid}-${slug}`, `${modelo}-produto-cel`, 390);
    await foto('/loja/demo/produtos', `${modelo}-lista`, 1440);
  }
}
console.log(erros.length ? 'AVISOS:\n' + erros.join('\n') : 'sem erros de página nem rolagem lateral');
await browser.close();
