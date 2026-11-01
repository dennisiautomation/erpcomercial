// Validação ✅/❌ no NAVEGADOR — troca no PDV com "preço cadastrado = cartão" (01/11/2026).
// O defeito da STILO VINTE era a TELA fechar a conta no preço do dinheiro e o SERVIDOR cobrar o
// do cartão ("Pagamento insuficiente… Faltam R$ 2,50"). Aqui roda o JavaScript real do PDV.
// Ambiente DESCARTÁVEL. Uso:
//   BASE=http://127.0.0.1:8197 DB_CT=pct-mysql PUPPETEER=<.../node_modules/puppeteer> node scripts/validar-pdv-troca-01nov.mjs
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const puppeteer = require(process.env.PUPPETEER || 'puppeteer');
const { BASE } = process.env;
const DB_CT = process.env.DB_CT || 'pct-mysql';
const CHROME = process.env.CHROME || fs.readdirSync('/home/ubuntu/.cache/puppeteer/chrome-headless-shell').sort().map((d) => `/home/ubuntu/.cache/puppeteer/chrome-headless-shell/${d}/chrome-headless-shell-linux64/chrome-headless-shell`).reverse().find((p) => fs.existsSync(p));
const sql = (q) => execFileSync('docker', ['exec', DB_CT, 'mysql', '-uerp', '-perp', 'erp_teste', '-N', '-B', '-e', q], { stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim();
let ok = 0, fail = 0;
const check = (d, c, extra = '') => { c ? ok++ : fail++; console.log(`${c ? '✅' : '❌'} ${d}${c ? '' : `  (obtido: ${extra})`}`); };

// Loja em "preço do cartão"; produto 3 no formato antigo: PIX 25 (cadastrado) e cartão fixo 27,50
sql("delete from produto_precos; delete from vale_usos; delete from vales; delete from devolucao_itens; delete from devolucoes;");
sql("update produtos set preco_venda=25, preco_custo=0, markup=0, status='ativo' where id=3");
sql("insert into produto_precos (empresa_id, produto_id, modalidade, valor, created_at, updated_at) values (1,3,'debito',27.50,now(),now()),(1,3,'credito',27.50,now(),now())");
sql("update configuracoes_loja set preco_base='cartao', desconto_avista_percentual=0, regra_preco_split='cartao_maior', percentual_debito=0, percentual_credito=0, troca_prazo_dias=30, troca_sobra='vale'");

const browser = await puppeteer.launch({ headless: 'shell', args: ['--no-sandbox', '--disable-dev-shm-usage'], executablePath: CHROME });
const page = await browser.newPage();
await page.setViewport({ width: 1400, height: 900 });
await page.goto(`${BASE}/login`);
await page.type('input[name=email]', 'caixa@demo.com');
await page.type('input[name=password]', 'caixa123');
await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
// Abre o caixa (ou retoma o já aberto) para esta sessão
await page.goto(`${BASE}/app/caixa/abrir`);
await page.evaluate(async () => {
  const t = document.querySelector('input[name=_token]')?.value || document.querySelector('meta[name="csrf-token"]')?.content;
  await fetch('/app/caixa/abrir', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `_token=${encodeURIComponent(t)}&valor_abertura=0` });
});

async function cenario(nome, formaOriginal, valorOriginal, esperado) {
  await page.goto(`${BASE}/app/pdv`, { waitUntil: 'networkidle0' });
  const r = await page.evaluate(async (formaOriginal, valorOriginal) => {
    const alertas = [];
    const showAlert = PDV.showAlert.bind(PDV);
    PDV.showAlert = (m, t) => { alertas.push(`${t}: ${String(m).replace(/<[^>]+>/g, '')}`); showAlert(m, t); };
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const post = (url, body) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, body: JSON.stringify(body) }).then((x) => x.json());
    // 1. Venda original da peça (a que o cliente vai devolver)
    const v = await post('/app/pdv/venda', { itens: [{ produto_id: 3, quantidade: 1, preco_unitario: valorOriginal, desconto_valor: 0 }], pagamentos: [{ forma: formaOriginal, valor: valorOriginal, parcelas: 1 }], tabela_precos: true, documento: 'recibo' });
    PDV.documentoEscolhido = 'recibo';
    // 2. F6: a troca pelo mesmo endpoint que o PDV usa; o crédito entra como o PDV faz
    const vendaId = v.venda?.id ?? v.venda_id ?? v.id;
    if (!vendaId) return { erroVenda: JSON.stringify(v).slice(0, 300) };
    const tv = await fetch(`/app/pdv/troca/venda/${vendaId}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then((x) => x.json());
    const item = (tv.itens || tv.situacao?.itens || [])[0];
    const data = await post('/app/pdv/troca', { venda_id: vendaId, tipo: 'troca', motivo: 'Tamanho / numeração', itens: [{ venda_item_id: item.venda_item_id, quantidade: 1, retorna_estoque: true }] });
    PDV.creditoTroca = { codigo: data.vale.codigo, saldo: data.vale.saldo, devolucaoId: data.devolucao.id, validade: data.vale.validade, modalidade: data.vale.modalidade || null };
    PDV.repriceItens?.(); PDV.updateSummary();
    // 3. Bipa a MESMA peça
    const prod = await fetch('/app/pdv/produto/PROD003', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then((x) => x.json());
    await PDV.addProduto(Array.isArray(prod) ? prod[0] : (prod.produto || prod));
    const totalNaTela = PDV.getTotal(); const credito = PDV.creditoAplicado();
    const faltaNaTela = Math.round((totalNaTela - credito) * 100) / 100;
    // 4. Caixa escolhe a forma da venda original e finaliza
    const formaTela = formaOriginal === 'dinheiro' ? 'dinheiro' : 'cartao_credito';
    if (faltaNaTela > 0.009) {
      PDV.openPagamento(formaTela);
      await new Promise((ok) => setTimeout(ok, 300));
      document.getElementById('valorRecebido').value = String(faltaNaTela.toFixed(2)).replace('.', ',');
      try { PDV.confirmarPagamento(); } catch (e) { alertas.push('erro: ' + e.message); }
    } else {
      PDV.openPagamento(formaTela); // como o caixa faria; o PDV avisa "Pagamento ja completo"
      await PDV.finalizarVenda();
    }
    await new Promise((ok) => setTimeout(ok, 1500));
    return { totalNaTela, credito, faltaNaTela, alertas, vendaNovaGravada: null };
  }, formaOriginal, valorOriginal);
  if (r.erroVenda) { check(`${nome}: venda original`, false, r.erroVenda); return; }
  const gravada = sql(`select count(*) from vendas v join devolucoes d on d.id=(select max(id) from devolucoes) where v.id > (select venda_id from devolucoes where id=(select max(id) from devolucoes)) and v.total>0`);
  const insuf = r.alertas.find((a) => /insuficiente|Faltam/i.test(a));
  check(`${nome}: tela mostra total ${esperado.toFixed(2)} e crédito ${esperado.toFixed(2)} (falta 0)`, Math.abs(r.totalNaTela - esperado) < 0.01 && Math.abs(r.faltaNaTela) < 0.01, JSON.stringify({ total: r.totalNaTela, credito: r.credito }));
  check(`${nome}: a venda da troca é aceita pelo servidor (sem "Pagamento insuficiente")`, !insuf && Number(gravada) >= 1, insuf || r.alertas.join(' | '));
}

await cenario('peça paga no DINHEIRO (25) trocada pela mesma', 'dinheiro', 25, 25);
await cenario('peça paga no CARTÃO (27,50) trocada pela mesma', 'cartao_credito', 27.5, 27.5);

await browser.close();
console.log(`\nTOTAL: ${ok} ✅ / ${fail} ❌`);
process.exit(fail ? 1 : 0);
