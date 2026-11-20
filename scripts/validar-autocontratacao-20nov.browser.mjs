// Validação ✅/❌ no NAVEGADOR — autocontratação pelo site (20/11/2026).
// Precisa de um ERP de TESTE (nunca produção) com MAIL_MAILER=log e os planos cadastrados.
//   BASE=http://10.213.80.9 APP=ac-app CHROME=/caminho/chrome-headless-shell NODE_PATH=/home/ubuntu/caixa-v2/node_modules PRINTS=/pasta \
//     node scripts/validar-autocontratacao-20nov.browser.mjs
import { createRequire } from 'module';
import { execFileSync } from 'child_process';
const require = createRequire(process.env.NODE_PATH + '/');
const puppeteer = require('puppeteer-core');

const BASE = process.env.BASE, APP = process.env.APP || 'ac-app', PRINTS = process.env.PRINTS || '';
let ok = 0, fail = 0;
const check = (nome, cond, obtido = '') => { cond ? ok++ : fail++; console.log((cond ? '✅ ' : '❌ ') + nome + (cond ? '' : `  (obtido: ${String(obtido).slice(0, 300)})`)); };
const tinker = code => execFileSync('docker', ['exec', APP, 'php', 'artisan', 'tinker', '--execute', code]).toString().trim();
const chave = v => tinker(`\\App\\Services\\Plataforma\\ContratacaoSite::definir(${v ? 'true' : 'false'}); echo 'ok';`);
const print = async (page, nome, full = true) => { if (PRINTS) await page.screenshot({ path: `${PRINTS}/${nome}.png`, fullPage: full }); };
const sobra = page => page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
const texto = page => page.evaluate(() => document.body.innerText);
const caminho = page => new URL(page.url()).pathname;
const sufixo = Date.now().toString().slice(-6);
const email = `teste${sufixo}@lojateste.com`;
// CNPJ válido gerado na hora (não repete entre rodadas)
const cnpj = (() => {
  const b = ('77' + sufixo + '0001').split('').map(Number);
  const dv = n => {
    const p = n.length === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    const r = n.reduce((s, d, i) => s + d * p[i], 0) % 11;
    return r < 2 ? 0 : 11 - r;
  };
  const d1 = dv(b), d2 = dv([...b, d1]);
  const s = [...b, d1, d2].join('');
  return `${s.slice(0, 2)}.${s.slice(2, 5)}.${s.slice(5, 8)}/${s.slice(8, 12)}-${s.slice(12)}`;
})();
const empresaSql = `\\App\\Models\\Empresa::withoutGlobalScopes()->where('cnpj','${cnpj}')`;

const browser = await puppeteer.launch({ executablePath: process.env.CHROME, args: ['--no-sandbox'] });
const page = await browser.newPage();
const erros = [];
page.on('pageerror', e => erros.push(e.message));
const larguras = [[1366, 820, 'pc'], [390, 800, 'cel']];

// ---------- chave DESLIGADA ----------
chave(false);
for (const [w, h, n] of larguras) {
  await page.setViewport({ width: w, height: h });
  await page.goto(BASE + '/', { waitUntil: 'networkidle2' });
  const t = await texto(page);
  check(`[${n}] desligada: planos "Sob consulta"`, (t.match(/Sob consulta/g) || []).length >= 3);
  check(`[${n}] desligada: nenhum preço dos planos na página`, !/R\$\s?(590|920|1\.200)/.test(t), t.match(/R\$\s?(590|920|1\.200)[^\n]*/));
  check(`[${n}] desligada: sem botão de teste grátis`, !(await page.$('a[href*="teste-gratis"]')));
  check(`[${n}] desligada: sem sobra lateral`, (await sobra(page)) <= 0, await sobra(page));
  await page.evaluate(() => document.querySelector('#planos').scrollIntoView());
  await print(page, `site-desligada-planos-${n}`, false);
}
await page.goto(BASE + '/teste-gratis', { waitUntil: 'networkidle2' });
check('desligada: /teste-gratis volta para a página inicial', caminho(page) === '/');

// ---------- chave LIGADA ----------
chave(true);
for (const [w, h, n] of larguras) {
  await page.setViewport({ width: w, height: h });
  await page.goto(BASE + '/', { waitUntil: 'networkidle2' });
  const t = await texto(page);
  check(`[${n}] ligada: preços aparecem`, /R\$ 590/.test(t) && /R\$ 920/.test(t));
  check(`[${n}] ligada: botão "Testar grátis" em cada plano`, (await page.$$('#planos a[href*="teste-gratis?plano="]')).length >= 3);
  check(`[${n}] ligada: sem sobra lateral`, (await sobra(page)) <= 0, await sobra(page));
  await page.evaluate(() => document.querySelector('#planos').scrollIntoView());
  await print(page, `site-ligada-planos-${n}`, false);
  await page.goto(BASE + '/teste-gratis?plano=profissional', { waitUntil: 'networkidle2' });
  check(`[${n}] cadastro abre com o plano escolhido marcado`, await page.$eval('input[name=plano]:checked', e => e.value) === 'profissional');
  check(`[${n}] cadastro sem sobra lateral`, (await sobra(page)) <= 0, await sobra(page));
  await print(page, `cadastro-${n}`);
}

// envio vazio: volta com os erros em português
await page.setViewport({ width: 1366, height: 820 });
await page.goto(BASE + '/teste-gratis', { waitUntil: 'networkidle2' });
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.$eval('#tgForm', f => f.submit())]);
const errosTela = await page.$$eval('.erro', els => els.map(e => e.innerText));
check('envio vazio: mostra os erros nos campos', errosTela.length >= 8, errosTela.length);
check('envio vazio: mensagens em português, sem nome técnico de campo', !errosTela.some(e => /required|field|_/.test(e)), errosTela.join(' | '));
await print(page, 'cadastro-erros-pc');

// cadastro completo
await page.goto(BASE + '/teste-gratis?plano=basico', { waitUntil: 'networkidle2' });
const digita = async (sel, v) => { await page.click(sel, { clickCount: 3 }); await page.type(sel, v); };
await digita('#nome', 'Maria Roteiro');
await digita('#email', email);
await digita('#whatsapp', '86999990000');
await digita('#senha', 'segredo-123');
await digita('#senha_confirmation', 'segredo-123');
await digita('#cnpj', cnpj.replace(/\D/g, ''));
await digita('#razao_social', 'Loja Roteiro LTDA');
await digita('#nome_fantasia', 'Loja Roteiro');
check('máscara do CNPJ', await page.$eval('#cnpj', e => e.value) === cnpj, await page.$eval('#cnpj', e => e.value));
check('máscara do WhatsApp', await page.$eval('#whatsapp', e => e.value) === '(86) 99999-0000', await page.$eval('#whatsapp', e => e.value));
await page.$eval('#cep', e => { e.value = '64000-000'; });
await digita('#logradouro', 'Rua do Roteiro');
await digita('#numero', '10');
await digita('#bairro', 'Centro');
await digita('#cidade', 'Teresina');
await digita('#uf', 'pi');
await page.click('input[name=aceite]');
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.click('#tgEnviar')]);
check('depois de enviar: tela "falta confirmar o e-mail"', /confirmar o seu e-mail/.test(await texto(page)), page.url());
await print(page, 'cadastro-enviado-pc');
check('empresa ainda NÃO existe antes da confirmação', tinker(`echo ${empresaSql}->count();`).endsWith('0'));

// o link saiu no e-mail (MAIL_MAILER=log)
const log = execFileSync('docker', ['exec', APP, 'sh', '-c', 'cat /var/www/storage/logs/*.log | tail -c 400000']).toString().replace(/=\r?\n/g, '').replace(/=3D/g, '=');
const links = log.match(/https?:\/\/[^\s"<]+\/teste-gratis\/confirmar\/[A-Za-z0-9]{64}/g) || [];
check('e-mail de confirmação saiu com o link', links.length > 0);
const link = BASE + new URL(links[links.length - 1]).pathname;
await page.goto(link, { waitUntil: 'networkidle2' });
check('confirmou: entrou direto no sistema (painel)', caminho(page) === '/app/dashboard', page.url());
const painel = await texto(page);
check('painel avisa o teste grátis e os dias', /teste grátis começou/i.test(painel) && /14 dias/.test(painel), painel.slice(0, 200));
await print(page, 'painel-teste-pc', false);
const emp = tinker(`$e=${empresaSql}->first(); echo $e->origem_cadastro,'|',(int)$e->em_trial,'|',now()->diffInDays($e->trial_fim, false) >= 13 ? 'dias-ok' : 'dias-errado','|',$e->plano,'|',$e->unidades()->count();`);
check('empresa: origem site, em teste, 14 dias, plano básico, 1 loja', emp.endsWith('site|1|dias-ok|basico|1'), emp);
await page.goto(BASE + '/app/produtos', { waitUntil: 'networkidle2' });
check('dentro do teste: Produtos abre', caminho(page) === '/app/produtos', page.url());
await page.goto(link, { waitUntil: 'networkidle2' });
check('link usado de novo: avisa que já foi confirmado', /já foi confirmado/.test(await texto(page)));

// teste vencido: sistema inteiro parado na tela de plano
tinker(`${empresaSql}->update(['trial_fim'=>now()->subDay()]); echo 'ok';`);
for (const rota of ['/app/produtos', '/app/dashboard', '/app/pdv']) {
  await page.goto(BASE + rota, { waitUntil: 'networkidle2' });
  check(`teste vencido: ${rota} cai na tela de plano`, caminho(page) === '/app/plano/expirado', page.url());
}
check('tela de plano: botão leva ao WhatsApp', !!(await page.$('a[href*="wa.me"]')));
await print(page, 'plano-expirado-pc');
tinker(`${empresaSql}->first()->estenderTrial(30); echo 'ok';`);
await page.goto(BASE + '/app/produtos', { waitUntil: 'networkidle2' });
check('admin estendeu o teste: volta a entrar', caminho(page) === '/app/produtos', page.url());

check('nenhum erro de JavaScript nas telas', erros.length === 0, erros.join(' | '));
chave(false);
console.log(`\n${ok} ✅  ${fail} ❌   (chave deixada DESLIGADA; cadastro de teste: ${email} / ${cnpj})`);
await browser.close();
process.exit(fail ? 1 : 0);
