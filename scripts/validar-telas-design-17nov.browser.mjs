// Salva as telas mexidas pelo navegador e confere o resultado (ambiente de design, cópia da homologação).
import { createRequire } from 'module';
import fs from 'fs';
const require = createRequire('/home/ubuntu/apps/caixa-vinculo-erp/');
const puppeteer = require('puppeteer-core');
const CHROME = fs.readdirSync('/home/ubuntu/.cache/puppeteer/chrome-headless-shell').sort().reverse()
  .map((d) => `/home/ubuntu/.cache/puppeteer/chrome-headless-shell/${d}/chrome-headless-shell-linux64/chrome-headless-shell`)
  .find((p) => fs.existsSync(p));
const BASE = process.argv[2];
let ok = 0, falha = 0;
const chk = (n, c, x = '') => { c ? ok++ : falha++; console.log(c ? '✅' : '❌', n, c ? '' : x); };
const browser = await puppeteer.launch({ headless: 'shell', executablePath: CHROME, args: ['--no-sandbox'] });
async function entra(email) {
  const ctx = await browser.createBrowserContext(); const p = await ctx.newPage();
  const erros = []; p.on('pageerror', (e) => erros.push(e.message)); p.erros = erros;
  await p.setViewport({ width: 1366, height: 900 });
  await p.goto(BASE + '/login', { waitUntil: 'networkidle2' });
  await p.type('input[name=email]', email); await p.type('input[name=password]', 'Teste123!');
  await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type=submit]')]);
  return p;
}
// 1) admin: liga "Vendedor opera somente o PDV" e "Credishop", salva, confere, desliga de novo
const a = await entra('admin@ia365.com.br');
await a.goto(BASE + '/admin/empresas/2/edit', { waitUntil: 'networkidle2' });
const antes = await a.evaluate(() => ({ pdv: document.getElementById('vendedor_apenas_pdv').checked, cred: document.getElementById('pdv_credishop').checked, regime: document.querySelector('input[name=regime_cobranca]:checked')?.value }));
// a empresa de teste da homologação tem o CNPJ gravado sem máscara (14 dígitos): redigita para a máscara da tela formatar
const mascara = async () => a.evaluate(() => { const c = document.getElementById('cnpj') || document.querySelector('[name=cnpj]'); c.value = c.value.replace(/\D/g, ''); c.dispatchEvent(new Event('input')); });
await mascara();
await a.click('label[for=vendedor_apenas_pdv]'); await a.click('label[for=pdv_credishop]');
await Promise.all([a.waitForNavigation({ waitUntil: 'networkidle2' }), a.click('#form-empresa button[type=submit]')]);
chk('admin salvou a empresa (sem erro de validação)', !(await a.$('.is-invalid')) && !a.url().endsWith('/edit'), a.url());
await a.goto(BASE + '/admin/empresas/2/edit', { waitUntil: 'networkidle2' });
const depois = await a.evaluate(() => ({ pdv: document.getElementById('vendedor_apenas_pdv').checked, cred: document.getElementById('pdv_credishop').checked, regime: document.querySelector('input[name=regime_cobranca]:checked')?.value }));
chk('chave "Vendedor só PDV" gravou invertida', depois.pdv === !antes.pdv);
chk('chave "Credishop" gravou invertida', depois.cred === !antes.cred);
chk('regime de cobrança não mudou', depois.regime === antes.regime);
await a.click('label[for=vendedor_apenas_pdv]'); await a.click('label[for=pdv_credishop]');
// escolhe outro regime pelo cartão e volta (clique no cartão funciona)
await a.click('label[for=rc_padrao]');
await mascara();
chk('cartão do regime responde ao clique', await a.evaluate(() => document.getElementById('rc_padrao').checked));
await Promise.all([a.waitForNavigation({ waitUntil: 'networkidle2' }), a.click('#form-empresa button[type=submit]')]);
await a.goto(BASE + '/admin/empresas/2/edit', { waitUntil: 'networkidle2' });
const volta = await a.evaluate(() => ({ pdv: document.getElementById('vendedor_apenas_pdv').checked, cred: document.getElementById('pdv_credishop').checked }));
chk('voltou ao estado original', volta.pdv === antes.pdv && volta.cred === antes.cred);
// integrações: copiar e "avançado"
await a.goto(BASE + '/admin/integracoes', { waitUntil: 'networkidle2' });
chk('integrações: faixa da plataforma com 3 itens', (await a.$$('.plataforma-item')).length === 3);
await a.click('.avancado summary');
chk('integrações: avançado abre os escopos', await a.evaluate(() => document.getElementById('campo-scopes').offsetParent !== null));
chk('admin sem erro de JavaScript', a.erros.length === 0, a.erros.join(' | '));
// 2) lojista: salva Configurações da loja online (sem mudar nada) e abre cada aba
const d = await entra('dono@demo.com');
await d.goto(BASE + '/app/loja-online/configuracoes', { waitUntil: 'networkidle2' });
await Promise.all([d.waitForNavigation({ waitUntil: 'networkidle2' }), d.click('.lj-salvar button[type=submit]')]);
chk('loja: Salvar configurações volta sem erro', !(await d.$('.is-invalid')) && (await d.content()).includes('alert-success'), d.url());
const abas = await d.$$eval('.lj-aba', (as) => as.map((a) => a.href));
chk('loja: 14 abas', abas.length === 14, String(abas.length));
for (const href of abas) {
  const r = await d.goto(href, { waitUntil: 'networkidle2' });
  const ativa = await d.$eval('.lj-aba.active', (a) => a.href).catch(() => '');
  chk(`aba ${href.replace(/^.*loja-online/, '') || '/'} ${r.status()} e marcada`, r.status() === 200 && ativa === href);
}
chk('lojista sem erro de JavaScript', d.erros.length === 0, d.erros.join(' | '));
console.log(`\n${ok} ✅ / ${falha} ❌`);
await browser.close();
process.exit(falha ? 1 : 0);
