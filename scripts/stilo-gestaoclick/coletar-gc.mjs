// Coleta SÓ LEITURA do Gestão Click da STILO VINTE (03/11/2026): lojas + produtos por loja.
// Roda DENTRO do audit-app (INTEGRATION_SECRET no ambiente). A credencial cifrada chega pelo
// stdin, é decifrada aqui e nunca é impressa. Saída (stdout) = JSON só com dados da API.
import crypto from "crypto";
const BASE = "https://api.beteltecnologia.com";
let enc = ""; for await (const c of process.stdin) enc += c; enc = enc.trim();
const [iv, tag, data] = enc.split(".");
const d = crypto.createDecipheriv("aes-256-gcm", Buffer.from(process.env.INTEGRATION_SECRET, "hex"), Buffer.from(iv, "base64"));
d.setAuthTag(Buffer.from(tag, "base64"));
const creds = JSON.parse(Buffer.concat([d.update(Buffer.from(data, "base64")), d.final()]).toString("utf8"));
const H = { "access-token": creds.accessToken || "", "secret-access-token": creds.secretAccessToken || "" };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
async function get(path, t = 4) {
  await sleep(400); // limite 3 req/s
  const r = await fetch(BASE + path, { headers: H });
  if ((r.status === 429 || r.status >= 500) && t > 0) { await sleep(3000); return get(path, t - 1); }
  if (!r.ok) throw new Error(`HTTP ${r.status} em ${path}`);
  return r.json();
}
async function todas(path) {
  const out = []; let p = 1;
  for (;;) {
    const sep = path.includes("?") ? "&" : "?";
    const r = await get(`${path}${sep}pagina=${p}`);
    out.push(...(r.data || []));
    process.stderr.write(`${path} pág ${p}: ${out.length}/${r.meta?.total_registros ?? "?"}\n`);
    if (!r.meta?.proxima_pagina) break; p = r.meta.proxima_pagina;
  }
  return out;
}
const lojas = (await get("/lojas")).data || [];
const porLoja = {};
for (const l of lojas) porLoja[l.id] = { nome: l.nome, produtos: await todas(`/produtos?loja_id=${l.id}`) };
const valores = await get("/tipos_valores").catch(() => ({ data: null }));
process.stdout.write(JSON.stringify({ coletado_em: new Date().toISOString(), lojas, tipos_valores: valores.data, porLoja }));
