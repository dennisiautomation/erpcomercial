#!/usr/bin/env node
// Smoke de TODAS as telas × perfis do ERP Comercial (28/10/2026) — parte do tools/validar/validar.sh.
// Lê as rotas GET do app DESCARTÁVEL (rotas.json = `artisan route:list --json`), resolve os {params} com o banco
// descartável (min(id) da tabela), faz login pelo formulário em cada perfil (admin IA365, dono, gerente, vendedor, caixa,
// financeiro, consulta) e abre cada tela com puppeteer, nas duas versões (?visual=novo|classico) quando a tela tem as duas.
// Classifica: ok (200 na própria tela) · negada (403, ou mandou para o login) · redir (200 noutra tela: vendedor→PDV,
// seleção de loja…) · erro (HTTP ≥ 400 exceto 403, exceção Laravel na página, tela vazia) · pulada (param sem dado).
// Print .png por tela (ok/redir/erro) em $OUT/telas/. Saída: smoke.tsv, smoke.json e, com SMOKE_ANTES, smoke-diff.txt.
// Uso: BASE=http://127.0.0.1:8097 DB_CT=erp-val-mysql ROTAS=rotas.json OUT=<pasta> [PERFIS=admin,dono] [ONLY=/app/pdv] [PARALELO=3] node smoke-telas.mjs
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const PUPPETEER = process.env.PUPPETEER || fs.readdirSync('/home/ubuntu/.npm/_npx').map((d) => `/home/ubuntu/.npm/_npx/${d}/node_modules/puppeteer`).find((p) => fs.existsSync(p));
const CHROME = process.env.CHROME || fs.readdirSync('/home/ubuntu/.cache/puppeteer/chrome-headless-shell').sort().map((d) => `/home/ubuntu/.cache/puppeteer/chrome-headless-shell/${d}/chrome-headless-shell-linux64/chrome-headless-shell`).reverse().find((p) => fs.existsSync(p));
const puppeteer = require(PUPPETEER);
const { BASE, DB_CT, ROTAS, OUT } = process.env;
if (!BASE || !DB_CT || !ROTAS || !OUT) { console.error('BASE, DB_CT, ROTAS e OUT são obrigatórios'); process.exit(2); }
const PARALELO = Number(process.env.PARALELO || 3);
const ONLY = process.env.ONLY ? process.env.ONLY.split(',') : null;
const PERFIS_ON = process.env.PERFIS ? process.env.PERFIS.split(',') : null;
fs.mkdirSync(path.join(OUT, 'telas'), { recursive: true });
const sql = (q) => { try { return execFileSync('docker', ['exec', DB_CT, 'mysql', '-uerp', '-perp', 'erp_teste', '-N', '-B', '-e', q], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim().split('\n')[0] || ''; } catch { return ''; } };

// ---- perfis (logins demo do CLAUDE.md + financeiro/consulta do seed-smoke.php)
const PERFIS = {
  admin: ['admin@ia365.com.br', 'admin123'], dono: ['dono@demo.com', 'dono123'], gerente: ['gerente@demo.com', 'gerente123'],
  vendedor: ['vendedor@demo.com', 'vendedor123'], caixa: ['caixa@demo.com', 'caixa123'],
  financeiro: ['financeiro@demo.com', 'financeiro123'], consulta: ['consulta@demo.com', 'consulta123'],
};
const perfis = Object.keys(PERFIS).filter((p) => !PERFIS_ON || PERFIS_ON.includes(p));

// ---- rotas GET (sem downloads/exports/webhooks/api)
const PULA = /(^|\/)(logout|sair|webhooks?|api|up|storage|_ignition|sanctum|livewire|telescope|horizon)(\/|$)|\b(export|exportar|download|baixar|xml|pdf|zip|csv|xlsx|danfe|template|qrcode|stream|sse|contar|search|autocomplete|buscar|calcular-st|tabela-st|sefaz-status|proxy|acesso-como|reset-password|verificar|esqueci|pwa)\b|pdv\/(produto|cliente)\/\{|reset-senha/i;
const todas = JSON.parse(fs.readFileSync(ROTAS, 'utf8')).filter((r) => r.method.includes('GET') && !PULA.test(r.uri));
// versões nova/antiga de uma mesma tela (rota → sufixos)
const VERSOES = { '': ['?visual=novo', '?visual=classico'] };

// ---- {params}: tabela pelo nome do param ou pelo segmento anterior da URL (min(id) no banco descartável)
const MAPA = { empresa: 'empresas', loja: 'unidades', unidade: 'unidades', notaFiscal: 'notas_fiscais', nfeRecebida: 'nfes_recebidas', nfseRecebida: 'nfses_recebidas',
  ordemServico: 'ordens_servico', contaReceber: 'contas_receber', contaPagar: 'contas_pagar', etiquetaFormato: 'etiqueta_formatos', etiquetaImagem: 'etiqueta_imagens',
  entrada: 'nfe_entradas', estoque: 'estoques', transferencia: 'estoque_transferencias', comodato: 'comodatos', adquirente: 'adquirentes', vale: 'vales',
  fatura: 'faturas', boleto: 'boletos', contrato: 'contratos', devolucao: 'devolucoes', treinamento: 'treinamentos', demonstracao: 'demonstracoes', conciliacao: 'conciliacoes',
  extrato: 'extratos_bancarios', notificacao: 'notificacoes', caixa: 'caixas', usuario: 'users', funcionario: 'users', movimentacao: 'estoque_movimentacoes',
  centros_custo: 'centros_custo', planoContas: 'plano_contas', servico: 'servicos', conciliacao: 'conciliacoes_bancarias', devolucao: 'devolucoes', venda: 'vendas', pedido: 'pedidos', orcamento: 'orcamentos', produto: 'produtos', cliente: 'clientes', fornecedor: 'fornecedores' };
const FIXOS = { uf: 'SP', tipo: 'clientes', mes: new Date().toISOString().slice(0, 7), chave: '2x5' };
const POR_URI = [[/pdv\/vale\/\{codigo\}/, () => sql('select codigo from vales order by id limit 1')],
  [/\{(usuario|funcionario)\}/, () => sql('select min(id) from users where empresa_id is not null')]];
const tabelas = new Set(sql('show tables').length ? execFileSync('docker', ['exec', DB_CT, 'mysql', '-uerp', '-perp', 'erp_teste', '-N', '-B', '-e', 'show tables'], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim().split('\n') : []);
const cache = {};
function resolver(uri) {
  for (const [rx, fn] of POR_URI) if (rx.test(uri)) { const v = fn(); if (!v) return null; uri = uri.replace(/\{\w+\}/, v); }
  const segs = uri.split('/');
  for (let i = 0; i < segs.length; i++) {
    const m = segs[i].match(/^\{(\w+)\??\}$/); if (!m) continue;
    const p = m[1];
    if (FIXOS[p]) { segs[i] = FIXOS[p]; continue; }
    let tab = MAPA[p] || (segs[i - 1] || '').replace(/-/g, '_');
    if (!tabelas.has(tab)) tab = MAPA[p] && tabelas.has(MAPA[p]) ? MAPA[p] : (tabelas.has(p + 's') ? p + 's' : null);
    if (!tab) return null;
    const v = (cache[tab] ??= sql(`select min(id) from ${tab}`));
    if (!v || v === 'NULL') return null;
    segs[i] = v;
  }
  return segs.join('/');
}
const jobs = [];
for (const r of todas) {
  const uri = '/' + r.uri.replace(/^\//, '');
  if (ONLY && !ONLY.some((o) => uri.startsWith(o))) continue;
  const url = resolver(r.uri.replace(/^\//, ''));
  const versoes = VERSOES[r.uri === '/' ? '' : r.uri] || [''];
  for (const perfil of perfis) for (const v of versoes) jobs.push({ rota: uri, nome: r.name || '', url: url === null ? null : '/' + url, versao: v ? v.replace('?visual=', '') : 'unica', query: v, perfil });
}
console.log(`smoke: ${todas.length} telas GET · perfis ${perfis.join(',')} · ${jobs.length} aberturas · paralelo ${PARALELO}`);

const browser = await puppeteer.launch({ headless: 'shell', args: ['--no-sandbox', '--disable-dev-shm-usage'], executablePath: CHROME });
const slug = (s) => s.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'raiz';
const resultados = [];
async function login(ctx, perfil) {
  const page = await ctx.newPage(); await page.setViewport({ width: 1366, height: 850 });
  await page.goto(BASE + '/login', { waitUntil: 'networkidle2', timeout: 60000 });
  await page.type('#email', PERFIS[perfil][0]); await page.type('#password', PERFIS[perfil][1]);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 60000 }), page.click('button[type=submit]')]);
  const ok = !page.url().includes('/login');
  await page.close(); return ok;
}
async function abrir(ctx, j) {
  if (!j.url) return { ...j, http: '', resultado: 'pulada', detalhe: 'param sem dado no banco descartável' };
  const page = await ctx.newPage(); await page.setViewport({ width: 1366, height: 850 });
  const erros = [];
  page.on('pageerror', (e) => erros.push(String(e.message || e).slice(0, 160)));
  let http = 0, detalhe = '', resultado = 'ok';
  try {
    const resp = await page.goto(BASE + j.url + j.query, { waitUntil: 'networkidle2', timeout: 45000 });
    http = resp ? resp.status() : 0;
    const ctype = resp ? (resp.headers()['content-type'] || '') : '';
    const fim = page.url().replace(BASE, '').split('?')[0];
    const texto = await page.evaluate(() => document.body?.innerText?.slice(0, 5000) || '');
    const quebrou = /Whoops|ErrorException|SQLSTATE\[|Stack trace:|Undefined (variable|array key|property)|Call to undefined|syntax error, unexpected|Illuminate\\|Internal Server Error|Server Error/i.test(texto);
    if (http < 400 && /json/.test(ctype)) { resultado = 'ok'; detalhe = 'json'; }
    else if (http === 403) { resultado = 'negada'; detalhe = (texto.match(/[^\n]*permiss[^\n]*|[^\n]*somente o PDV[^\n]*|[^\n]*plano[^\n]*/i) || ['403'])[0]; }
    else if (http >= 400) { resultado = 'erro'; detalhe = `HTTP ${http}`; }
    else if (quebrou) { resultado = 'erro'; detalhe = (texto.match(/(Whoops|ErrorException|SQLSTATE\[|Undefined [^\n]*|Call to undefined[^\n]*|syntax error[^\n]*|Internal Server Error|Server Error)[^\n]{0,120}/i) || [''])[0]; }
    else if (fim.startsWith('/login')) { resultado = 'negada'; detalhe = 'mandou para o login'; }
    else if (fim !== j.url.split('?')[0]) { resultado = 'redir'; detalhe = fim.slice(0, 80); }
    else if (texto.trim().length < 20) { resultado = 'erro'; detalhe = 'tela vazia'; }
    if (resultado !== 'negada') {
      const png = path.join(OUT, 'telas', `${slug(j.rota)}__${j.versao}__${j.perfil}.png`);
      await page.screenshot({ path: png, fullPage: false }); j.print = path.relative(OUT, png);
    }
  } catch (e) { resultado = 'erro'; detalhe = String(e.message || e).slice(0, 160); }
  await page.close().catch(() => {});
  return { ...j, http, resultado, detalhe: detalhe.replace(/\s+/g, ' ').trim().slice(0, 160) };
}
// um contexto (sessão) por perfil; PARALELO perfis ao mesmo tempo, telas em sequência dentro do perfil
let pi = 0;
await Promise.all(Array.from({ length: Math.min(PARALELO, perfis.length) }, async () => {
  while (pi < perfis.length) {
    const perfil = perfis[pi++];
    const ctx = await browser.createBrowserContext();
    let logado = false; try { logado = await login(ctx, perfil); } catch (e) { console.log(`  ❌ login ${perfil}: ${e.message}`); }
    for (const j of jobs.filter((x) => x.perfil === perfil)) {
      if (!logado) { resultados.push({ ...j, http: '', resultado: 'erro', detalhe: 'login do perfil falhou' }); continue; }
      const r = await abrir(ctx, j); resultados.push(r);
      if (r.resultado === 'erro') console.log(`  ❌ ${r.rota} [${r.versao}] ${r.perfil}: ${r.detalhe}`);
    }
    await ctx.close().catch(() => {});
  }
}));
await browser.close();

resultados.sort((a, b) => a.rota.localeCompare(b.rota) || a.versao.localeCompare(b.versao) || a.perfil.localeCompare(b.perfil));
fs.writeFileSync(path.join(OUT, 'smoke.tsv'), ['rota\tversao\tperfil\thttp\tresultado\tdetalhe\tnome\tprint', ...resultados.map((r) => [r.rota, r.versao, r.perfil, r.http, r.resultado, r.detalhe, r.nome, r.print || ''].join('\t'))].join('\n') + '\n');
const cont = (k) => resultados.filter((r) => r.resultado === k).length;
const resumo = { telas: todas.length, perfis, aberturas: resultados.length, ok: cont('ok'), negada: cont('negada'), redir: cont('redir'), erro: cont('erro'), pulada: cont('pulada'), erro_dono: resultados.filter((r) => r.resultado === 'erro' && r.perfil === 'dono').length, puladas_rotas: [...new Set(resultados.filter((r) => r.resultado === 'pulada').map((r) => r.rota))] };
if (process.env.SMOKE_ANTES && fs.existsSync(process.env.SMOKE_ANTES)) {
  const antes = Object.fromEntries(fs.readFileSync(process.env.SMOKE_ANTES, 'utf8').split('\n').slice(1).filter(Boolean).map((l) => { const c = l.split('\t'); return [`${c[0]}|${c[1]}|${c[2]}`, c[4]]; }));
  const novos = [], acesso = [], abre = ['ok', 'negada', 'redir'];
  for (const r of resultados) {
    const a = antes[`${r.rota}|${r.versao}|${r.perfil}`];
    if (a === undefined) { if (r.resultado === 'erro') novos.push(`ERRO NOVO ${r.rota} [${r.versao}] ${r.perfil}: ${r.detalhe} (tela nova)`); continue; }
    if (r.resultado === 'erro' && a !== 'erro') novos.push(`ERRO NOVO ${r.rota} [${r.versao}] ${r.perfil}: ${r.detalhe} (antes: ${a})`);
    else if (abre.includes(a) && abre.includes(r.resultado) && a !== r.resultado) acesso.push(`ACESSO ${r.rota} [${r.versao}] ${r.perfil}: ${a} → ${r.resultado}`);
  }
  fs.writeFileSync(path.join(OUT, 'smoke-diff.txt'), [...novos, ...acesso].join('\n') + '\n');
  resumo.erros_novos = novos.length; resumo.mudancas_acesso = acesso.length;
}
fs.writeFileSync(path.join(OUT, 'smoke.json'), JSON.stringify(resumo, null, 2));
console.log(`smoke: ${resumo.ok} ok · ${resumo.negada} negadas · ${resumo.redir} redirecionadas · ${resumo.erro} erros (${resumo.erro_dono} no dono) · ${resumo.pulada} puladas (${resumo.puladas_rotas.length} rotas)` + (resumo.erros_novos !== undefined ? ` · × antes: ${resumo.erros_novos} erros novos, ${resumo.mudancas_acesso} mudanças de acesso` : ''));
