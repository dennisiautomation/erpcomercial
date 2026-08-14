<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>ERP Comercial IA365 · Sua loja vende, o sistema emite a nota</title>
<meta name="description" content="ERP para micro, pequenas e médias empresas: emite NF-e, NFC-e e NFS-e automaticamente, controla PDV, estoque, financeiro e várias lojas em um só lugar. Integração oficial Focus NFe.">
<meta property="og:title" content="ERP Comercial IA365">
<meta property="og:description" content="Venda no PDV, controle estoque e financeiro, e deixe a emissão fiscal no automático. Feito para PMEs brasileiras.">
<meta property="og:type" content="website">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%231d1d1f'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='16' font-weight='bold' fill='white' text-anchor='middle'%3EI%3C/text%3E%3C/svg%3E">
<style>
/* ============================================================
   ERP Comercial IA365 — landing V2 "formato Apple"
   Sistema: superfícies claras alternadas (#fff / #f5f5f7), tinta
   #1d1d1f, hairlines, azul de ação #0071e3, semânticas iOS para
   DADO (verde ok / laranja atenção / vermelho alerta), SF stack,
   números tabulares, movimento contínuo (lerp), vidro só onde
   flutua sobre conteúdo real.
   ============================================================ */
:root{
  --bg:#ffffff; --bg2:#f5f5f7;
  --ink:#1d1d1f; --ink2:#6e6e73; --ink3:#86868b;
  --sep:rgba(0,0,0,.09);
  --blue:#0071e3; --blue-h:#0077ed; --link:#06c;
  --ok:#34c759; --okD:#248a3d;
  --warn:#ff9500; --warnD:#c93400;
  --bad:#ff3b30; --badD:#d70015;
  --cyan:#32ade6;
  --r:18px;
  --shadow:0 1px 2px rgba(0,0,0,.04),0 8px 32px rgba(0,0,0,.07);
  --ease:cubic-bezier(.16,1,.3,1);
}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{
  font-family:-apple-system,BlinkMacSystemFont,'SF Pro Display','SF Pro Text','Segoe UI',Roboto,Helvetica,Arial,sans-serif;
  background:var(--bg);color:var(--ink);
  font-size:17px;line-height:1.55;
  -webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;
}
::selection{background:rgba(0,113,227,.18)}
.wrap{max-width:1040px;margin-inline:auto;padding-inline:22px}
.wrap--wide{max-width:1200px}
a{color:var(--link);text-decoration:none}
a:hover{text-decoration:underline}
.skip{position:absolute;left:-9999px;top:0;background:var(--ink);color:#fff;padding:.6rem 1rem;border-radius:0 0 10px 0;z-index:100}
.skip:focus{left:0}
:focus-visible{outline:2px solid var(--blue);outline-offset:3px;border-radius:4px}

/* ---------- tipografia ---------- */
h1,h2,h3{letter-spacing:-.015em;text-wrap:balance}
.d1{font-size:clamp(2.6rem,6.4vw,4.4rem);font-weight:700;line-height:1.06}
.d2{font-size:clamp(1.9rem,4vw,3rem);font-weight:700;line-height:1.12}
.d3{font-size:1.35rem;font-weight:600;line-height:1.25}
.lede{font-size:clamp(1.1rem,1.9vw,1.35rem);color:var(--ink2);line-height:1.5;text-wrap:pretty}
.kicker{display:block;font-size:1.05rem;font-weight:600;color:var(--warnD);margin-bottom:.9rem}
.num,.mono{font-variant-numeric:tabular-nums}

/* ---------- botões / links Apple ---------- */
.pill{
  display:inline-flex;align-items:center;gap:.45rem;
  background:var(--blue);color:#fff;border:none;cursor:pointer;
  font:inherit;font-size:1.02rem;font-weight:500;
  padding:.72rem 1.35rem;border-radius:980px;min-height:44px;
  transition:background .18s var(--ease),transform .18s var(--ease);
}
.pill:hover{background:var(--blue-h);text-decoration:none;color:#fff}
.pill:active{transform:scale(.985)}
.pill--ghost{background:transparent;color:var(--blue);box-shadow:inset 0 0 0 1px var(--blue)}
.pill--ghost:hover{background:var(--blue);color:#fff}
.pill--light{background:#fff;color:var(--ink)}
.pill--light:hover{background:#f0f0f2;color:var(--ink)}
.pill--block{width:100%;justify-content:center}
.alink{display:inline-flex;align-items:center;gap:.3rem;font-size:1.02rem;font-weight:500;min-height:44px}
.alink svg{width:.85em;height:.85em;transition:transform .18s var(--ease)}
.alink:hover svg{transform:translateX(3px)}

/* ---------- nav (vidro sobre conteúdo real) ---------- */
.nav{
  position:sticky;top:0;z-index:60;
  background:rgba(255,255,255,.86);
  -webkit-backdrop-filter:blur(20px) saturate(1.8);backdrop-filter:blur(20px) saturate(1.8);
  border-bottom:1px solid transparent;
  transition:border-color .25s var(--ease);
}
.nav.is-scrolled{border-bottom-color:var(--sep)}
.nav__in{display:flex;align-items:center;gap:1.4rem;height:52px}
.brand{display:inline-flex;align-items:center;gap:.55rem;font-weight:600;color:var(--ink);font-size:1rem;white-space:nowrap}
.brand:hover{text-decoration:none;color:var(--ink)}
.brand__mark{width:26px;height:26px;border-radius:7px;background:var(--ink);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;letter-spacing:0}
.brand small{color:var(--ink3);font-weight:500}
.nav__links{display:flex;gap:1.5rem;margin-inline:auto}
.nav__links a{color:var(--ink);font-size:.88rem;opacity:.85}
.nav__links a:hover{opacity:1;text-decoration:none}
.nav__cta{display:flex;align-items:center;gap:1rem}
.nav__cta .entrar{font-size:.88rem;color:var(--ink);opacity:.85}
.nav__cta .pill{font-size:.82rem;padding:.36rem .95rem;min-height:32px}
@media(max-width:760px){.nav__links,.nav__cta .entrar{display:none}}

/* ---------- hero compacto: o painel é o herói ---------- */
.hero{padding:clamp(1.7rem,3.5vw,2.7rem) 0 0;text-align:center}
.hero h1{font-size:clamp(1.9rem,3.4vw,2.7rem);font-weight:700;line-height:1.12}
.hero h1 .fisco{color:var(--okD)}
.hero .lede{font-size:clamp(1rem,1.5vw,1.15rem);max-width:58ch;margin:.7rem auto 0}
.hero__actions{display:flex;gap:1.3rem;justify-content:center;align-items:center;margin-top:1.2rem;flex-wrap:wrap}
.hero__actions .pill{font-size:.95rem;padding:.6rem 1.2rem}

/* ---------- mock do painel (o "dash formato Apple") ---------- */
.stage{position:relative;max-width:1200px;margin:clamp(1.8rem,3.5vw,2.6rem) auto 0;padding-inline:22px}
.mac{
  background:var(--bg2);border-radius:22px;overflow:hidden;text-align:left;
  box-shadow:0 2px 6px rgba(0,0,0,.06),0 24px 70px rgba(0,0,0,.13);
  border:1px solid rgba(0,0,0,.07);
}
.mac__body{display:grid;grid-template-columns:210px 1fr;min-height:520px}
.side{
  background:rgba(255,255,255,.6);
  -webkit-backdrop-filter:blur(24px) saturate(1.6);backdrop-filter:blur(24px) saturate(1.6);
  border-right:1px solid var(--sep);padding:1rem .8rem;display:flex;flex-direction:column;gap:.15rem;
}
.side__brand{display:flex;align-items:center;gap:.5rem;font-size:.8rem;font-weight:700;padding:.35rem .55rem .9rem}
.side__brand .brand__mark{width:22px;height:22px;border-radius:6px;font-size:.6rem}
.side a{display:flex;align-items:center;gap:.55rem;font-size:.8rem;font-weight:500;color:var(--ink);padding:.42rem .55rem;border-radius:9px;pointer-events:none}
.side a svg{width:15px;height:15px;color:var(--ink3)}
.side a.on{background:rgba(0,113,227,.12);color:var(--blue);font-weight:600}
.side a.on svg{color:var(--blue)}
.dash{padding:1.4rem 1.6rem 1.6rem;display:flex;flex-direction:column;gap:1.05rem;min-width:0}
.dash__h{display:flex;align-items:baseline;justify-content:space-between;gap:1rem}
.dash__h b{font-size:.98rem;font-weight:700;letter-spacing:-.01em}
.dash__h time{font-size:.72rem;color:var(--ink3);font-weight:500}
.dgrid{display:grid;grid-template-columns:240px 1fr;gap:1.05rem;align-items:stretch}
.dcard{background:#fff;border-radius:16px;box-shadow:var(--shadow);padding:.95rem 1.05rem;min-width:0}
/* anel Apple Watch — arco 240°, texto no centro real do círculo */
.ringcard{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.1rem}
.ring{position:relative;width:180px}
.ring svg{display:block;width:100%;height:auto}
.ring__txt{position:absolute;left:0;right:0;top:59.6%;transform:translateY(-50%);text-align:center;line-height:1.15}
.ring__k{display:block;font-size:.56rem;font-weight:700;letter-spacing:.06em;color:var(--ink3)}
.ring__n{display:block;font-size:1.5rem;font-weight:700;letter-spacing:-.02em}
.ring__u{display:block;font-size:.62rem;font-weight:600;color:var(--okD)}
.ring__cap{font-size:.7rem;color:var(--ink2);font-weight:500;text-align:center}
.dright{display:flex;flex-direction:column;gap:.9rem;min-width:0}
.kpis{display:grid;grid-template-columns:1fr 1fr;gap:.9rem}
.kpi small{display:block;font-size:.68rem;font-weight:600;color:var(--ink3);margin-bottom:.15rem}
.kpi b{display:block;font-size:1.55rem;font-weight:700;letter-spacing:-.02em}
.kpi .delta{font-size:.7rem;font-weight:600;color:var(--okD)}
.chartcard{flex:1;display:flex;flex-direction:column;gap:.3rem}
.chartcard small{font-size:.68rem;font-weight:600;color:var(--ink3)}
.chartcard svg{display:block;width:100%;height:auto}
/* toast de vidro flutuando sobre o mock */
.toast{
  position:absolute;right:0;bottom:26px;max-width:290px;
  display:flex;gap:.7rem;align-items:flex-start;
  background:rgba(255,255,255,.78);
  -webkit-backdrop-filter:blur(24px) saturate(1.6);backdrop-filter:blur(24px) saturate(1.6);
  border-radius:16px;padding:.8rem .95rem;
  box-shadow:0 8px 30px rgba(0,0,0,.14),0 2px 8px rgba(0,0,0,.06);
  border:1px solid rgba(255,255,255,.6);
  opacity:0;transform:translateY(14px) scale(.96);
}
.toast.is-in{animation:toastIn .55s var(--ease) forwards}
@keyframes toastIn{to{opacity:1;transform:none}}
.toast .tic{width:30px;height:30px;border-radius:50%;background:var(--ok);color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.toast .tic svg{width:15px;height:15px}
.toast b{display:block;font-size:.85rem;font-weight:700}
.toast small{display:block;font-size:.75rem;color:var(--ink2)}
.toast .chave{margin-top:.3rem;font-family:'SF Mono',ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.62rem;color:var(--ink3);letter-spacing:.04em}
@media(max-width:820px){
  .mac__body{grid-template-columns:1fr}
  .side{flex-direction:row;overflow-x:auto;border-right:none;border-bottom:1px solid var(--sep);padding:.6rem}
  .side__brand{display:none}
  .dgrid{grid-template-columns:1fr}
  .ringcard{padding-block:1.1rem}
  .toast{position:static;margin:.9rem auto 0;transform:none}
}

/* ---------- faixa de confiança ---------- */
.trust{border-top:1px solid var(--sep);border-bottom:1px solid var(--sep);margin-top:clamp(3rem,7vw,5rem);background:var(--bg)}
.trust__in{display:flex;align-items:center;gap:1.6rem;padding-block:1.05rem;flex-wrap:wrap;justify-content:center}
.trust__label{font-size:.85rem;color:var(--ink3);font-weight:500}
.trust__items{display:flex;gap:1.5rem;flex-wrap:wrap;justify-content:center}
.titem{display:inline-flex;align-items:center;gap:.4rem;font-size:.88rem;font-weight:600;color:var(--ink)}
.titem svg{width:15px;height:15px;color:var(--okD)}

/* ---------- seções ---------- */
.sec{padding-block:clamp(3.6rem,8vw,6.2rem)}
.sec--gray{background:var(--bg2)}
.sec--dark{background:#000;color:#f5f5f7}
.sechead{max-width:640px;margin-bottom:clamp(2.2rem,5vw,3.6rem)}
.sechead--center{margin-inline:auto;text-align:center}
.sechead .lede{margin-top:.9rem}
.sec--dark .lede{color:#a1a1a6}

/* ---------- features (cenas de UI) ---------- */
.feat{display:grid;grid-template-columns:1fr 1fr;gap:clamp(2rem,5vw,4rem);align-items:center;padding-block:clamp(1.8rem,4vw,3rem)}
.feat+.feat{border-top:1px solid var(--sep)}
.feat__copy h3{font-size:clamp(1.5rem,2.6vw,2rem);font-weight:700;margin-bottom:.7rem}
.feat__copy>p{color:var(--ink2);margin-bottom:1.1rem;text-wrap:pretty}
.checks{list-style:none;display:flex;flex-direction:column;gap:.65rem}
.checks li{display:flex;gap:.6rem;align-items:flex-start;font-size:.98rem}
.checks svg{width:17px;height:17px;color:var(--okD);flex-shrink:0;margin-top:.18rem}
.checks b{font-weight:600}
@media(max-width:820px){.feat{grid-template-columns:1fr}.feat--flip .feat__media{order:-1}}
@media(min-width:821px){.feat--flip .feat__media{order:-1}}

/* cartões de cena */
.scene{background:#fff;border-radius:var(--r);box-shadow:var(--shadow);border:1px solid rgba(0,0,0,.05);padding:1.15rem 1.25rem;font-size:.9rem}
.scene__h{display:flex;justify-content:space-between;align-items:center;gap:.8rem;margin-bottom:.9rem}
.scene__h b{font-size:.95rem;font-weight:700}
.scene__tag{font-size:.68rem;font-weight:600;color:var(--ink2);background:var(--bg2);border-radius:980px;padding:.22rem .6rem;white-space:nowrap}
.rows{display:flex;flex-direction:column}
.row{display:grid;grid-template-columns:1fr auto;gap:.8rem;align-items:center;padding-block:.62rem}
.row+.row{border-top:1px solid var(--sep)}
.row__l small{display:block;font-size:.7rem;color:var(--ink3);font-weight:500}
.row__l span{font-weight:600;font-size:.88rem}
.row__v{font-weight:700;font-variant-numeric:tabular-nums}
.bar{height:5px;border-radius:3px;background:rgba(118,118,128,.14);overflow:hidden;grid-column:1/-1}
.bar i{display:block;height:100%;border-radius:3px;background:var(--ink3);transform:scaleX(0);transform-origin:left;transition:transform 1.1s var(--ease)}
.bar i.is-on{transform:scaleX(1)}
.bar i.ok{background:var(--ok)}
.bar i.low{background:var(--bad)}
.chip{font-size:.68rem;font-weight:700;border-radius:980px;padding:.2rem .6rem;white-space:nowrap}
.chip--ok{background:rgba(52,199,89,.14);color:var(--okD)}
.chip--low{background:rgba(255,59,48,.12);color:var(--badD)}
/* PDV escuro */
.pdv{background:#1c1c1e;color:#f5f5f7;border-radius:var(--r);box-shadow:var(--shadow);padding:1.1rem 1.2rem;font-size:.9rem}
.pdv__h{display:flex;justify-content:space-between;align-items:center;font-size:.78rem;color:#98989d;margin-bottom:.85rem}
.pdv__h .fkey{background:rgba(255,255,255,.1);border-radius:7px;padding:.18rem .55rem;font-size:.66rem;font-weight:700;color:#f5f5f7}
.pdv__row{display:flex;justify-content:space-between;gap:1rem;padding-block:.55rem;border-bottom:1px solid rgba(255,255,255,.09)}
.pdv__row small{display:block;color:#98989d;font-size:.72rem}
.pdv__row b{font-variant-numeric:tabular-nums;font-weight:700}
.pdv__total{display:flex;justify-content:space-between;align-items:baseline;padding-block:.8rem .95rem}
.pdv__total span{color:#98989d;font-size:.8rem}
.pdv__total b{font-size:1.45rem;font-weight:700;font-variant-numeric:tabular-nums;letter-spacing:-.02em}
.pdv__pay{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem}
.pdv__key{background:rgba(255,255,255,.1);border-radius:10px;padding:.55rem;text-align:center;font-size:.76rem;font-weight:600}
.pdv__key--go{background:var(--ok);color:#003910}
/* mini-anéis (estoque por loja) */
.mring{position:relative;width:40px;height:40px;flex-shrink:0}
.mring svg{transform:rotate(-90deg)}
.mring circle{fill:none;stroke-width:4;stroke-linecap:round}
.mring .trk{stroke:rgba(118,118,128,.16)}
.mring .val{stroke:var(--ok);stroke-dasharray:0 100;transition:stroke-dasharray 1.2s var(--ease)}
.mring .val.low{stroke:var(--bad)}
.mring b{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:.66rem;font-weight:700;font-variant-numeric:tabular-nums}
.rowloja{display:flex;align-items:center;gap:.8rem;padding-block:.6rem}
.rowloja+.rowloja{border-top:1px solid var(--sep)}
.rowloja .row__l{flex:1}

/* ---------- fiscal (banda preta, bento) ---------- */
.bento{display:grid;grid-template-columns:repeat(6,1fr);gap:14px}
.bcard{
  background:#1d1d1f;border-radius:var(--r);padding:1.5rem 1.5rem 1.4rem;
  grid-column:span 2;display:flex;flex-direction:column;gap:.55rem;
  border:1px solid rgba(255,255,255,.08);
}
.bcard--wide{grid-column:span 3}
.bcard h3{font-size:1.15rem;font-weight:700;letter-spacing:-.01em}
.bcard p{font-size:.92rem;color:#a1a1a6;flex:1;text-wrap:pretty}
.bcard .bic{width:34px;height:34px;border-radius:9px;background:rgba(52,199,89,.16);color:var(--ok);display:flex;align-items:center;justify-content:center;margin-bottom:.3rem}
.bcard .bic svg{width:18px;height:18px}
.btags{display:flex;gap:.45rem;flex-wrap:wrap;margin-top:.35rem}
.btag{font-size:.68rem;font-weight:700;color:#f5f5f7;background:rgba(255,255,255,.1);border-radius:980px;padding:.24rem .65rem}
.fiscal__foot{display:flex;flex-direction:column;align-items:center;gap:1.1rem;margin-top:clamp(2rem,5vw,3rem);text-align:center}
.fiscal__foot p{color:#a1a1a6;max-width:52ch;text-wrap:pretty}
@media(max-width:900px){.bento{grid-template-columns:1fr 1fr}.bcard,.bcard--wide{grid-column:span 1}}
@media(max-width:560px){.bento{grid-template-columns:1fr}}

/* ---------- módulos ---------- */
.mods{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.mod{background:#fff;border-radius:var(--r);padding:1.4rem 1.45rem;box-shadow:var(--shadow);border:1px solid rgba(0,0,0,.04)}
.mod__h{display:flex;align-items:center;gap:.7rem;margin-bottom:.55rem}
.mod__h h3{font-size:1.05rem;font-weight:700}
.mod__ic{width:32px;height:32px;border-radius:9px;background:var(--bg2);color:var(--ink);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.mod__ic svg{width:17px;height:17px}
.mod>p{font-size:.9rem;color:var(--ink2);margin-bottom:.7rem;text-wrap:pretty}
.mod ul{list-style:none;display:flex;flex-wrap:wrap;gap:.35rem .8rem;font-size:.78rem;font-weight:600;color:var(--ink3)}
.mod li{display:inline-flex;align-items:center;gap:.3rem}
.mod li::before{content:'';width:4px;height:4px;border-radius:50%;background:var(--okD)}
@media(max-width:900px){.mods{grid-template-columns:1fr 1fr}}
@media(max-width:560px){.mods{grid-template-columns:1fr}}

/* ---------- multi-empresa ---------- */
.split{display:grid;grid-template-columns:1fr 1fr;gap:clamp(2rem,5vw,4rem);align-items:center}
@media(max-width:820px){.split{grid-template-columns:1fr}}

/* ---------- planos ---------- */
.plans{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;align-items:stretch}
.plan{background:#fff;border-radius:var(--r);padding:1.7rem 1.6rem;box-shadow:var(--shadow);border:1px solid rgba(0,0,0,.04);display:flex;flex-direction:column;gap:.9rem}
.plan--feat{border:2px solid var(--blue);position:relative}
.plan--feat::before{content:'Mais escolhido';position:absolute;top:-11px;left:50%;transform:translateX(-50%);background:var(--blue);color:#fff;font-size:.66rem;font-weight:700;border-radius:980px;padding:.2rem .7rem;letter-spacing:.02em}
.plan h3{font-size:1.25rem;font-weight:700}
.plan__desc{font-size:.9rem;color:var(--ink2)}
.plan__price b{font-size:1.3rem;font-weight:700}
.plan ul{list-style:none;display:flex;flex-direction:column;gap:.55rem;flex:1}
.plan li{display:flex;gap:.55rem;align-items:flex-start;font-size:.9rem}
.plan li svg{width:15px;height:15px;color:var(--okD);flex-shrink:0;margin-top:.2rem}
@media(max-width:820px){.plans{grid-template-columns:1fr;max-width:420px;margin-inline:auto}}

/* ---------- segurança ---------- */
.segur{display:grid;grid-template-columns:repeat(4,1fr);gap:clamp(1.4rem,3vw,2.4rem)}
.sitem .sic{width:36px;height:36px;border-radius:10px;background:var(--bg2);display:flex;align-items:center;justify-content:center;margin-bottom:.7rem}
.sitem .sic svg{width:18px;height:18px;color:var(--ink)}
.sitem b{display:block;font-size:.98rem;font-weight:700;margin-bottom:.3rem}
.sitem p{font-size:.88rem;color:var(--ink2);text-wrap:pretty}
@media(max-width:900px){.segur{grid-template-columns:1fr 1fr}}
@media(max-width:560px){.segur{grid-template-columns:1fr}}

/* ---------- demo / form ---------- */
.cta__grid{display:grid;grid-template-columns:1.05fr .95fr;gap:clamp(2rem,5vw,4rem);align-items:start}
@media(max-width:880px){.cta__grid{grid-template-columns:1fr}}
.cta__list{list-style:none;display:flex;flex-direction:column;gap:.65rem;margin-block:1.3rem}
.cta__list li{display:flex;gap:.6rem;align-items:flex-start;font-size:.98rem}
.cta__list svg{width:17px;height:17px;color:var(--okD);flex-shrink:0;margin-top:.18rem}
.map{border-radius:var(--r);overflow:hidden;box-shadow:var(--shadow);border:1px solid rgba(0,0,0,.05);background:#fff}
.map iframe{display:block;width:100%;height:230px;border:0}
.map__bar{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:.8rem 1.1rem;font-size:.82rem;flex-wrap:wrap}
.map__bar span{display:inline-flex;gap:.45rem;align-items:flex-start;color:var(--ink2)}
.map__bar svg{width:15px;height:15px;flex-shrink:0;margin-top:.15rem;color:var(--ink)}
.form{background:#fff;border-radius:22px;box-shadow:var(--shadow);border:1px solid rgba(0,0,0,.05);padding:1.9rem 1.8rem}
.form__badge{display:inline-flex;align-items:center;gap:.4rem;font-size:.74rem;font-weight:700;color:var(--okD);background:rgba(52,199,89,.12);border-radius:980px;padding:.3rem .8rem;margin-bottom:1rem}
.form__badge svg{width:13px;height:13px}
.form h3{font-size:1.5rem;font-weight:700;margin-bottom:.3rem}
.form>p{font-size:.92rem;color:var(--ink2);margin-bottom:1.4rem}
.field{margin-bottom:1rem}
.field label{display:block;font-size:.8rem;font-weight:600;margin-bottom:.35rem}
.field input,.field select{
  width:100%;font:inherit;font-size:.95rem;color:var(--ink);
  background:var(--bg2);border:1px solid transparent;border-radius:12px;
  padding:.72rem .9rem;min-height:44px;
  transition:border-color .15s,background .15s,box-shadow .15s;
  appearance:none;-webkit-appearance:none;
}
.field select{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236e6e73' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .85rem center;background-size:14px}
.field input::placeholder{color:var(--ink3)}
.field input:focus,.field select:focus{outline:none;background:#fff;border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,113,227,.18)}
.field--row{display:grid;grid-template-columns:1fr 1fr;gap:.9rem}
@media(max-width:480px){.field--row{grid-template-columns:1fr}}
.form__fine{display:flex;gap:.45rem;align-items:center;font-size:.76rem;color:var(--ink3);margin-top:.9rem}
.form__fine svg{width:13px;height:13px;flex-shrink:0}
.form__feedback{margin-top:.9rem;font-size:.9rem;font-weight:600;border-radius:12px;padding:.7rem .9rem}
.form__feedback.is-ok{background:rgba(52,199,89,.12);color:var(--okD)}
.form__feedback.is-err{background:rgba(255,59,48,.1);color:var(--badD)}
.form__alt{margin-top:1rem;font-size:.86rem;color:var(--ink2);text-align:center}

/* ---------- footer ---------- */
.foot{background:var(--bg2);border-top:1px solid var(--sep);padding-block:2.6rem 2rem;font-size:.82rem;color:var(--ink2)}
.foot__grid{display:grid;grid-template-columns:1.3fr 1fr 1fr 1.3fr;gap:2rem;margin-bottom:2rem}
.foot__brand p{margin-top:.7rem;max-width:30ch;text-wrap:pretty}
.foot h4{font-size:.78rem;font-weight:700;color:var(--ink);margin-bottom:.7rem}
.foot ul{list-style:none;display:flex;flex-direction:column;gap:.45rem}
.foot a{color:var(--ink2)}
.foot a:hover{color:var(--ink);text-decoration:none}
.foot__bar{border-top:1px solid var(--sep);padding-top:1.3rem;display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;font-size:.76rem;color:var(--ink3)}
@media(max-width:820px){.foot__grid{grid-template-columns:1fr 1fr}}

/* ---------- reveals ---------- */
.rv{opacity:0;transform:translateY(22px);transition:opacity .7s var(--ease),transform .7s var(--ease)}
.rv.is-in{opacity:1;transform:none}
.rv.dl1{transition-delay:.08s}
.rv.dl2{transition-delay:.16s}
@media(prefers-reduced-motion:reduce){
  html{scroll-behavior:auto}
  .rv,.toast{opacity:1!important;transform:none!important;transition:none!important;animation:none!important}
  .bar i,.mring .val{transition:none!important}
  *{animation-duration:.01ms!important;transition-duration:.01ms!important}
}
</style>
</head>
<body>
<a class="skip" href="#main">Pular para o conteúdo</a>

<header class="nav" id="nav">
  <div class="wrap wrap--wide nav__in">
    <a class="brand" href="#" aria-label="ERP Comercial IA365, ir para o início">
      <span class="brand__mark">IA</span>
      ERP Comercial <small>· IA365</small>
    </a>
    <nav class="nav__links" aria-label="Seções">
      <a href="#recursos">Recursos</a>
      <a href="#fiscal">Nota fiscal</a>
      <a href="#modulos">Módulos</a>
      <a href="#planos">Planos</a>
    </nav>
    <div class="nav__cta">
      <a class="entrar" href="{{ route('login') }}">Entrar</a>
      <a class="pill" href="#demo">Agendar demonstração</a>
    </div>
  </div>
</header>

<main id="main">

<!-- HERO -->
<section class="hero">
  <div class="wrap">
    <h1 class="rv">Sua loja vende. O sistema <span class="fisco">emite a nota</span> sozinho.</h1>
    <p class="lede rv dl1">Nota fiscal, estoque, caixa e financeiro de todas as suas lojas, em um lugar só.</p>
    <div class="hero__actions rv dl1">
      <a class="pill" href="#demo">Agendar demonstração</a>
      <a class="alink" href="#recursos">Ver o que faz
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
      </a>
    </div>
  </div>

  <!-- O painel no "formato Apple": vidro, anel, movimento contínuo -->
  <div class="stage rv dl2">
    <div class="mac" role="img" aria-label="Painel do ERP no estilo iOS: anel verde com 47 de 47 notas autorizadas, faturamento do dia de R$ 8.430 subindo 12%, 47 vendas e gráfico da semana. Uma notificação mostra a NFC-e da venda 2041 recém autorizada.">
      <div class="mac__body">
        <aside class="side" aria-hidden="true">
          <span class="side__brand"><span class="brand__mark">IA</span> ERP</span>
          <a class="on" href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg> Painel</a>
          <a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M3 9h18M8 22h8"/></svg> PDV</a>
          <a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/></svg> Vendas</a>
          <a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg> Estoque</a>
          <a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg> Financeiro</a>
          <a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg> Fiscal</a>
        </aside>
        <div class="dash" aria-hidden="true">
          <div class="dash__h">
            <b>Loja Centro · Hoje</b>
            <time class="num">quarta, 13 de agosto</time>
          </div>
          <div class="dgrid">
            <div class="dcard ringcard">
              <div class="ring">
                <svg viewBox="0 0 220 176">
                  <path d="M 35.5 148 A 86 86 0 1 1 184.5 148" fill="none" stroke="rgba(118,118,128,.16)" stroke-width="16" stroke-linecap="round"/>
                  <path id="ringArc" d="M 35.5 148 A 86 86 0 1 1 184.5 148" pathLength="100" fill="none" stroke="var(--ok)" stroke-width="16" stroke-linecap="round" stroke-dasharray="0 100"/>
                </svg>
                <span class="ring__txt">
                  <span class="ring__k">NOTAS</span>
                  <span class="ring__n num"><span id="ringNum">0</span>/47</span>
                  <span class="ring__u">autorizadas</span>
                </span>
              </div>
              <span class="ring__cap">Emissão fiscal do dia</span>
            </div>
            <div class="dright">
              <div class="kpis">
                <div class="dcard kpi">
                  <small>FATURAMENTO</small>
                  <b class="num">R$ <span id="kpiFat">0</span></b>
                  <span class="delta num">▲ 12% vs. ontem</span>
                </div>
                <div class="dcard kpi">
                  <small>VENDAS</small>
                  <b class="num" id="kpiVendas">0</b>
                  <span class="delta num">▲ 6 vs. ontem</span>
                </div>
              </div>
              <div class="dcard chartcard">
                <small>FATURAMENTO · ÚLTIMOS 9 DIAS</small>
                <svg id="chartSvg" viewBox="0 0 560 150" preserveAspectRatio="none" aria-hidden="true">
                  <defs>
                    <linearGradient id="chFill" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0" stop-color="rgba(0,113,227,.22)"/>
                      <stop offset="1" stop-color="rgba(0,113,227,0)"/>
                    </linearGradient>
                  </defs>
                  <path id="chArea" fill="url(#chFill)" d=""/>
                  <path id="chLine" fill="none" stroke="var(--blue)" stroke-width="3" stroke-linecap="round" d=""/>
                  <circle id="chDot" r="5" fill="var(--blue)" stroke="#fff" stroke-width="2.5" opacity="0"/>
                </svg>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="toast" id="toastFiscal" role="status">
      <span class="tic">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
      </span>
      <div>
        <b>NFC-e autorizada</b>
        <small class="num">Venda #2041 · R$ 189,90</small>
        <div class="chave">3526 0412 3456 7890 ··· 5501</div>
      </div>
    </div>
  </div>

  <!-- confiança -->
  <div class="trust">
    <div class="wrap wrap--wide trust__in">
      <span class="trust__label">Tudo que o fisco pede, resolvido:</span>
      <div class="trust__items">
        <span class="titem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> NF-e</span>
        <span class="titem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> NFC-e (cupom)</span>
        <span class="titem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> NFS-e (serviços)</span>
        <span class="titem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Certificado A1</span>
        <span class="titem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Backup de XML</span>
      </div>
    </div>
  </div>
</section>

<!-- RECURSOS -->
<section class="sec" id="recursos">
  <div class="wrap">
    <div class="sechead">
      <h2 class="d2">O dia da loja, do balcão ao fechamento do caixa.</h2>
      <p class="lede">Cada parte conversa com a outra. Vendeu no PDV, o estoque baixa, a nota sai e o financeiro registra. Sem digitar a mesma coisa três vezes.</p>
    </div>

    <!-- PDV -->
    <div class="feat rv">
      <div class="feat__copy">
        <h3>PDV de tela cheia, com atalhos de teclado</h3>
        <p>O caixa trabalha rápido: leitura de código de barras, busca por produto, desconto, pagamento dividido e troco. Funciona do jeito que o operador espera.</p>
        <ul class="checks">
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Atalhos <b>F1 a F12</b> e pagamento dividido em várias formas.</span></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Confere o estoque na hora e avisa se o produto está zerado.</span></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Fechou a venda, o cupom imprime e a <b>NFC-e</b> sai junto.</span></li>
        </ul>
      </div>
      <div class="feat__media">
        <div class="pdv" role="img" aria-label="Tela do PDV com dois itens, total de R$ 189,90 e teclas de pagamento.">
          <div class="pdv__h"><span>PDV · Loja Centro</span><span class="fkey">F8 finalizar</span></div>
          <div class="pdv__row"><span>Camiseta básica <small>2 un · R$ 49,90</small></span><b>99,80</b></div>
          <div class="pdv__row"><span>Tênis runner <small>1 un · R$ 90,10</small></span><b>90,10</b></div>
          <div class="pdv__total"><span>Total a pagar</span><b>R$ 189,90</b></div>
          <div class="pdv__pay">
            <span class="pdv__key">Dinheiro</span>
            <span class="pdv__key">Pix</span>
            <span class="pdv__key">Cartão</span>
            <span class="pdv__key">Desconto</span>
            <span class="pdv__key">Cliente</span>
            <span class="pdv__key pdv__key--go">Finalizar</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Financeiro -->
    <div class="feat feat--flip rv">
      <div class="feat__copy">
        <h3>Financeiro que mostra para onde o dinheiro vai</h3>
        <p>Contas a pagar e a receber com parcelas, fluxo de caixa em gráfico, conciliação do extrato do banco e DRE por loja ou consolidado. O que você precisa para decidir, na tela.</p>
        <ul class="checks">
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Contas a pagar e receber, com parcelas e baixa.</span></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Conciliação bancária por arquivo <b>OFX</b> e contratos recorrentes.</span></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>DRE e fluxo de caixa por unidade ou da empresa toda.</span></li>
        </ul>
      </div>
      <div class="feat__media">
        <div class="scene" role="img" aria-label="Painel financeiro com fluxo de caixa por categoria.">
          <div class="scene__h"><b>Fluxo de caixa · Junho</b><span class="scene__tag">consolidado</span></div>
          <div class="rows">
            <div class="row"><span class="row__l"><span>Vendas no balcão</span><small>entradas</small></span><span class="row__v num">R$ 62.140</span><span class="bar"><i class="ok" data-w="86"></i></span></div>
            <div class="row"><span class="row__l"><span>Serviços</span><small>entradas</small></span><span class="row__v num">R$ 18.900</span><span class="bar"><i class="ok" data-w="48"></i></span></div>
            <div class="row"><span class="row__l"><span>Fornecedores</span><small>saídas</small></span><span class="row__v num">R$ 27.430</span><span class="bar"><i data-w="60"></i></span></div>
            <div class="row"><span class="row__l"><span>Folha e despesas</span><small>saídas</small></span><span class="row__v num">R$ 14.220</span><span class="bar"><i data-w="33"></i></span></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Multi-loja -->
    <div class="feat rv">
      <div class="feat__copy">
        <h3>Várias lojas, um estoque sob controle</h3>
        <p>Cada unidade tem caixa, estoque e fiscal próprios. Quando o produto acaba em uma loja, o PDV pode vender do estoque da outra e o sistema registra a transferência. Você escolhe a regra.</p>
        <ul class="checks">
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Veja o saldo de cada loja e transfira com aprovação.</span></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Movimentações de entrada, saída, ajuste e transferência.</span></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Etiquetas com código de barras, prontas para imprimir.</span></li>
        </ul>
      </div>
      <div class="feat__media">
        <div class="scene" role="img" aria-label="Estoque do mesmo produto em três lojas, uma delas com saldo baixo.">
          <div class="scene__h"><b>Tênis runner 42</b><span class="scene__tag">estoque por loja</span></div>
          <div class="rowloja">
            <span class="mring"><svg viewBox="0 0 40 40"><circle class="trk" cx="20" cy="20" r="16" pathLength="100"/><circle class="val" cx="20" cy="20" r="16" pathLength="100" data-f="80"/></svg><b class="num">24</b></span>
            <span class="row__l"><span>Loja Centro</span><small>unidade atual</small></span>
            <span class="chip chip--ok">24 un</span>
          </div>
          <div class="rowloja">
            <span class="mring"><svg viewBox="0 0 40 40"><circle class="trk" cx="20" cy="20" r="16" pathLength="100"/><circle class="val" cx="20" cy="20" r="16" pathLength="100" data-f="50"/></svg><b class="num">12</b></span>
            <span class="row__l"><span>Loja Shopping</span><small>&nbsp;</small></span>
            <span class="chip chip--ok">12 un</span>
          </div>
          <div class="rowloja">
            <span class="mring"><svg viewBox="0 0 40 40"><circle class="trk" cx="20" cy="20" r="16" pathLength="100"/><circle class="val low" cx="20" cy="20" r="16" pathLength="100" data-f="10"/></svg><b class="num">2</b></span>
            <span class="row__l"><span>Loja Bairro</span><small>reposição sugerida</small></span>
            <span class="chip chip--low">2 un · baixo</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FISCAL -->
<section class="sec sec--dark" id="fiscal">
  <div class="wrap">
    <div class="sechead sechead--center">
      <span class="kicker" style="color:var(--ok)">A parte que tira o sono, resolvida</span>
      <h2 class="d2">A nota fiscal sai certa,<br>sem você ser contador.</h2>
      <p class="lede">Integração completa com a Focus NFe. O sistema preenche imposto, calcula o que precisa e fala direto com a SEFAZ. Você só vende.</p>
    </div>

    <div class="bento">
      <article class="bcard bcard--wide rv">
        <span class="bic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
        <h3>Emite tudo, no automático</h3>
        <p>Cupom no balcão, nota de produto para entrega e nota de serviço. O imposto é calculado por trás, conforme o seu regime tributário.</p>
        <div class="btags"><span class="btag">NF-e</span><span class="btag">NFC-e</span><span class="btag">NFS-e</span></div>
      </article>
      <article class="bcard bcard--wide rv dl1">
        <span class="bic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 4-6"/></svg></span>
        <h3>Já pronto para a Reforma Tributária</h3>
        <p>Cálculo de IBS, CBS e IS por item, além de substituição tributária interestadual com as alíquotas reais. Quando virar obrigatório, você já está em dia.</p>
        <div class="btags"><span class="btag">IBS / CBS / IS</span><span class="btag">ST interestadual</span></div>
      </article>
      <article class="bcard rv">
        <span class="bic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></span>
        <h3>Errou uma palavra? Corrige</h3>
        <p>Carta de correção com histórico, cancelamento e inutilização. Erro da SEFAZ traduzido em português claro.</p>
        <div class="btags"><span class="btag">CC-e</span><span class="btag">Cancelamento</span></div>
      </article>
      <article class="bcard rv dl1">
        <span class="bic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-6l-2-2H5a2 2 0 0 0-2 2z"/></svg></span>
        <h3>Notas que chegam para você</h3>
        <p>Dê ciência, confirme ou recuse as notas emitidas contra o seu CNPJ. Sincroniza sozinho.</p>
        <div class="btags"><span class="btag">Ciência</span><span class="btag">Confirmação</span><span class="btag">Recusa</span></div>
      </article>
      <article class="bcard rv dl2">
        <span class="bic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg></span>
        <h3>Certificado e CSC sem mistério</h3>
        <p>Suba o certificado A1 e o sistema avisa quando estiver vencendo. O arquivo não fica no servidor: vai direto para a emissora.</p>
        <div class="btags"><span class="btag">Certificado A1</span><span class="btag">Aviso de validade</span></div>
      </article>
      <article class="bcard bcard--wide rv" style="grid-column:span 6">
        <span class="bic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.5 0 4.8 1 6.4 2.6L21 8"/><path d="M21 3v5h-5"/></svg></span>
        <h3>Guarda os XML por 5 anos</h3>
        <p>Backup mensal dos arquivos fiscais feito todo dia, sem você lembrar. Status da SEFAZ por estado e painel com erros e emissões dos últimos dias.</p>
        <div class="btags"><span class="btag">Backup automático</span><span class="btag">Painel fiscal</span></div>
      </article>
    </div>

    <div class="fiscal__foot rv">
      <p>Sem colar token, sem decorar tabela de CFOP. Ao cadastrar a empresa, o sistema já provisiona a emissão fiscal e configura os avisos automáticos.</p>
      <a class="pill pill--light" href="#demo">Quero ver isso funcionando</a>
    </div>
  </div>
</section>

<!-- MÓDULOS -->
<section class="sec sec--gray" id="modulos">
  <div class="wrap">
    <div class="sechead sechead--center">
      <h2 class="d2">Tudo que a empresa precisa,<br>no mesmo sistema.</h2>
      <p class="lede">Do cadastro do cliente ao relatório do contador. Sem juntar três programas que não se falam.</p>
    </div>
    <div class="mods">
      <article class="mod rv">
        <div class="mod__h"><span class="mod__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><h3>Cadastros</h3></div>
        <p>Clientes, produtos, fornecedores, serviços e funcionários com passo a passo. CPF e CNPJ preenchem o resto sozinhos.</p>
        <ul><li>Busca de CNPJ</li><li>Importação CSV</li><li>Categorias</li><li>Etiquetas</li></ul>
      </article>
      <article class="mod rv dl1">
        <div class="mod__h"><span class="mod__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/></svg></span><h3>Vendas</h3></div>
        <p>Do orçamento ao pedido e à venda. PDV, venda balcão, devoluções e comissão por vendedor.</p>
        <ul><li>Orçamentos</li><li>PDV</li><li>Devoluções</li><li>Comissões</li></ul>
      </article>
      <article class="mod rv dl2">
        <div class="mod__h"><span class="mod__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/></svg></span><h3>Estoque</h3></div>
        <p>Saldo por loja, movimentações e transferências entre unidades com solicitação e aprovação.</p>
        <ul><li>Multi-loja</li><li>Transferências</li><li>Ajustes</li><li>Etiquetas</li></ul>
      </article>
      <article class="mod rv">
        <div class="mod__h"><span class="mod__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span><h3>Financeiro</h3></div>
        <p>Contas a pagar e receber, fluxo de caixa, conciliação bancária, DRE e plano de contas.</p>
        <ul><li>Fluxo de caixa</li><li>DRE</li><li>Conciliação OFX</li><li>Recorrências</li></ul>
      </article>
      <article class="mod rv dl1">
        <div class="mod__h"><span class="mod__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg></span><h3>Fiscal</h3></div>
        <p>Emissão de NF-e, NFC-e e NFS-e, carta de correção, manifestação e painel fiscal. Tudo via Focus NFe.</p>
        <ul><li>Emissão</li><li>CC-e</li><li>Backup XML</li><li>Reforma</li></ul>
      </article>
      <article class="mod rv dl2">
        <div class="mod__h"><span class="mod__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span><h3>Controle e auditoria</h3></div>
        <p>Sete perfis de acesso com permissão por módulo. Registro de quem mudou o quê, antes e depois.</p>
        <ul><li>Perfis e permissões</li><li>Histórico</li><li>Notificações</li><li>Busca global</li></ul>
      </article>
    </div>
  </div>
</section>

<!-- MULTI-EMPRESA -->
<section class="sec">
  <div class="wrap split">
    <div class="rv">
      <h2 class="d2" style="margin-bottom:1rem">Uma empresa,<br>ou uma rede inteira.</h2>
      <p class="lede" style="margin-bottom:1.5rem">Cada empresa tem suas lojas; cada loja tem CNPJ, estoque, caixa e fiscal independentes. O dono enxerga tudo, o vendedor vê só a loja dele. Os dados de uma empresa nunca se misturam com os de outra.</p>
      <ul class="checks">
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span><b>Multi-empresa e multi-loja</b> com isolamento total dos dados.</span></li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Cada usuário vê só o que o perfil dele permite.</span></li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Relatórios por loja ou da empresa toda, num clique.</span></li>
      </ul>
    </div>
    <div class="rv dl1">
      <div class="scene" role="img" aria-label="Resumo de três lojas de uma mesma empresa com faturamento e notas do dia.">
        <div class="scene__h"><b>Minha Empresa Ltda</b><span class="scene__tag">3 lojas ativas</span></div>
        <div class="rows">
          <div class="row"><span class="row__l"><span>Loja Centro</span><small>CNPJ ····/0001</small></span><span style="display:flex;gap:.7rem;align-items:center"><span class="row__v num">R$ 8.430</span><span class="chip chip--ok">47 notas</span></span></div>
          <div class="row"><span class="row__l"><span>Loja Shopping</span><small>CNPJ ····/0002</small></span><span style="display:flex;gap:.7rem;align-items:center"><span class="row__v num">R$ 12.190</span><span class="chip chip--ok">63 notas</span></span></div>
          <div class="row"><span class="row__l"><span>Loja Bairro</span><small>CNPJ ····/0003</small></span><span style="display:flex;gap:.7rem;align-items:center"><span class="row__v num">R$ 4.870</span><span class="chip chip--ok">29 notas</span></span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- PLANOS -->
<section class="sec sec--gray" id="planos">
  <div class="wrap">
    <div class="sechead sechead--center">
      <h2 class="d2">Planos que cabem no caixa da PME.</h2>
      <p class="lede">Comece testando. A demonstração mostra qual plano faz sentido para o número de lojas e usuários que você tem.</p>
    </div>
    <div class="plans">
      <article class="plan rv">
        <h3>Básico</h3>
        <p class="plan__desc">Para quem está começando com uma loja.</p>
        <div class="plan__price"><b>Sob consulta</b></div>
        <ul>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> PDV, vendas e estoque</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Emissão de NFC-e e NF-e</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Financeiro essencial</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> 1 loja, até 3 usuários</li>
        </ul>
        <a class="pill pill--ghost pill--block" href="#demo">Ver na demonstração</a>
      </article>
      <article class="plan plan--feat rv dl1">
        <h3>Profissional</h3>
        <p class="plan__desc">Para quem cresceu e tem mais de uma loja.</p>
        <div class="plan__price"><b>Sob consulta</b></div>
        <ul>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Tudo do Básico, com NFS-e</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Multi-loja com transferências</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> DRE, conciliação e recorrências</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Auditoria e comissões</li>
        </ul>
        <a class="pill pill--block" href="#demo">Agendar demonstração</a>
      </article>
      <article class="plan rv dl2">
        <h3>Enterprise</h3>
        <p class="plan__desc">Para redes com muitas lojas e operação fiscal pesada.</p>
        <div class="plan__price"><b>Sob consulta</b></div>
        <ul>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Tudo do Profissional</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Lojas e usuários sem limite prático</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Reforma Tributária e ST avançada</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Suporte prioritário</li>
        </ul>
        <a class="pill pill--ghost pill--block" href="#demo">Falar com vendas</a>
      </article>
    </div>
  </div>
</section>

<!-- SEGURANÇA -->
<section class="sec">
  <div class="wrap">
    <div class="sechead">
      <h2 class="d2">Seus dados, separados e auditados.</h2>
      <p class="lede">Cada empresa em seu próprio espaço, com registro de tudo que acontece dentro do sistema.</p>
    </div>
    <div class="segur">
      <div class="sitem rv">
        <span class="sic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
        <b>Isolamento por empresa</b>
        <p>Os dados de um cliente nunca aparecem para outro. Cada empresa vive no seu próprio espaço.</p>
      </div>
      <div class="sitem rv dl1">
        <span class="sic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg></span>
        <b>Perfis de acesso</b>
        <p>Dono, gerente, financeiro, vendedor, caixa e mais. Cada um enxerga só o que precisa.</p>
      </div>
      <div class="sitem rv dl2">
        <span class="sic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="9"/></svg></span>
        <b>Histórico de alterações</b>
        <p>Quem mudou, quando e o que mudou. O antes e o depois ficam registrados.</p>
      </div>
      <div class="sitem rv">
        <span class="sic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg></span>
        <b>Certificado fora do servidor</b>
        <p>O arquivo .pfx vai direto para a emissora. No banco ficam só os dados de validade.</p>
      </div>
    </div>
  </div>
</section>

<!-- DEMO -->
<section class="sec sec--gray" id="demo">
  <div class="wrap cta__grid">
    <div class="rv">
      <span class="kicker" style="color:var(--okD)">Demonstração gratuita</span>
      <h2 class="d2">Veja o ERP rodando na sua operação.</h2>
      <p class="lede" style="margin-top:.9rem">Em cerca de 30 minutos mostramos o sistema com o cenário da sua empresa — número de lojas, tipo de nota fiscal e o que mais pesa no seu dia a dia. Sem compromisso e sem instalar nada.</p>
      <ul class="cta__list">
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Demonstração guiada e no seu cenário real</li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Dúvidas de NF-e, NFC-e e NFS-e tiradas na hora</li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Nada para instalar — é só entrar na chamada</li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Proposta sob medida para o seu número de lojas</li>
      </ul>
      <div class="map">
        <iframe
          title="Localização IA365 — Alameda Santos, 200, Bela Vista, São Paulo/SP"
          src="https://www.google.com/maps?q=Alameda+Santos+200,+Bela+Vista,+S%C3%A3o+Paulo+-+SP,+01418-000&output=embed"
          loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        <div class="map__bar">
          <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg> <span><strong>IA365</strong> · Alameda Santos, 200 — 9º andar, Bela Vista, São Paulo/SP</span></span>
          <a href="https://www.google.com/maps/search/?api=1&query=Alameda+Santos+200+9%C2%BA+andar+Bela+Vista+S%C3%A3o+Paulo+SP+01418-000" target="_blank" rel="noopener">Como chegar →</a>
        </div>
      </div>
    </div>

    <form class="form rv dl1" id="demoForm" action="{{ route('site.demo.store') }}" method="POST" novalidate>
      @csrf
      <input type="text" name="site" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
      <span class="form__badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7v5l3 2"/><circle cx="12" cy="12" r="9"/></svg> Resposta em até 1 dia útil</span>
      <h3>Agendar demonstração</h3>
      <p>Preencha e a gente entra em contato para marcar o melhor horário.</p>
      <div class="field">
        <label for="nome">Seu nome</label>
        <input id="nome" name="nome" type="text" autocomplete="name" placeholder="Como podemos te chamar" required>
      </div>
      <div class="field">
        <label for="empresa">Empresa</label>
        <input id="empresa" name="empresa" type="text" autocomplete="organization" placeholder="Nome da sua empresa" required>
      </div>
      <div class="field field--row">
        <div>
          <label for="email">E-mail</label>
          <input id="email" name="email" type="email" autocomplete="email" placeholder="voce@empresa.com" required>
        </div>
        <div>
          <label for="whats">WhatsApp</label>
          <input id="whats" name="whatsapp" type="tel" inputmode="tel" autocomplete="tel" placeholder="(11) 90000-0000" required>
        </div>
      </div>
      <div class="field">
        <label for="lojas">Quantas lojas?</label>
        <select id="lojas" name="lojas">
          <option value="1 loja">1 loja</option>
          <option value="2 a 3 lojas">2 a 3 lojas</option>
          <option value="4 a 10 lojas">4 a 10 lojas</option>
          <option value="mais de 10 lojas">Mais de 10 lojas</option>
        </select>
      </div>
      <button class="pill pill--block" type="submit">Quero agendar a demonstração</button>
      <p class="form__fine"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg> Seus dados são usados só para este contato. Sem spam.</p>
      <p id="formMsg" class="form__feedback" hidden role="status"></p>
      <p class="form__alt">Prefere agora? <a href="https://wa.me/5511917120940?text=Quero%20uma%20demonstra%C3%A7%C3%A3o%20do%20ERP%20Comercial" target="_blank" rel="noopener">Chamar no WhatsApp</a></p>
    </form>
  </div>
</section>

</main>

<footer class="foot">
  <div class="wrap">
    <div class="foot__grid">
      <div class="foot__brand">
        <a class="brand" href="#"><span class="brand__mark">IA</span> ERP Comercial <small>· IA365</small></a>
        <p>O ERP que cuida da nota fiscal pela sua PME, do PDV ao relatório do contador.</p>
      </div>
      <div>
        <h4>Produto</h4>
        <ul>
          <li><a href="#recursos">Recursos</a></li>
          <li><a href="#fiscal">Nota fiscal</a></li>
          <li><a href="#modulos">Módulos</a></li>
          <li><a href="#planos">Planos</a></li>
        </ul>
      </div>
      <div>
        <h4>Fiscal</h4>
        <ul>
          <li><a href="#fiscal">NF-e, NFC-e, NFS-e</a></li>
          <li><a href="#fiscal">Carta de correção</a></li>
          <li><a href="#fiscal">Manifestação</a></li>
          <li><a href="#fiscal">Reforma Tributária</a></li>
        </ul>
      </div>
      <div>
        <h4>Contato</h4>
        <ul>
          <li><a href="#demo">Agendar demonstração</a></li>
          <li><a href="https://wa.me/5511917120940" target="_blank" rel="noopener">WhatsApp</a></li>
          <li><a href="mailto:contato@ia365.com.br">contato@ia365.com.br</a></li>
          <li><a href="https://www.google.com/maps/search/?api=1&query=Alameda+Santos+200+9%C2%BA+andar+Bela+Vista+S%C3%A3o+Paulo+SP+01418-000" target="_blank" rel="noopener">Alameda Santos, 200 — 9º andar<br>Bela Vista, São Paulo/SP · 01418-000</a></li>
        </ul>
      </div>
    </div>
    <div class="foot__bar">
      <span>© <span id="ano">2026</span> IA365 · ERP Comercial. Todos os direitos reservados.</span>
      <span>Emissão fiscal via Focus NFe · <a href="{{ route('site.home', ['visual' => 'classico']) }}">Visual clássico</a></span>
    </div>
  </div>
</footer>

<script>
(function () {
  'use strict';
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- nav: hairline ao rolar ---------- */
  var nav = document.getElementById('nav');
  var onScroll = function () { nav.classList.toggle('is-scrolled', window.scrollY > 8); };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---------- reveals + gatilhos de cena ---------- */
  var seen = new WeakSet();
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (!e.isIntersecting || seen.has(e.target)) return;
      seen.add(e.target);
      e.target.classList.add('is-in');
      // barras e mini-anéis dentro da cena revelada
      e.target.querySelectorAll('.bar i[data-w]').forEach(function (b) {
        b.style.width = b.dataset.w + '%';
        requestAnimationFrame(function () { b.classList.add('is-on'); });
      });
      e.target.querySelectorAll('.mring .val[data-f]').forEach(function (c) {
        requestAnimationFrame(function () { c.style.strokeDasharray = c.dataset.f + ' 100'; });
      });
      io.unobserve(e.target);
    });
  }, { threshold: 0.18 });
  document.querySelectorAll('.rv').forEach(function (el) { io.observe(el); });

  /* ---------- mock do painel: movimento contínuo (lerp, sem tranco) ---------- */
  var arc = document.getElementById('ringArc');
  var ringNum = document.getElementById('ringNum');
  var kpiFat = document.getElementById('kpiFat');
  var kpiVendas = document.getElementById('kpiVendas');
  var toast = document.getElementById('toastFiscal');
  var stage = document.querySelector('.stage');

  var DADOS = [38, 55, 42, 70, 88, 62, 78, 50, 66];      // % de altura, últimos 9 dias
  var chLine = document.getElementById('chLine');
  var chArea = document.getElementById('chArea');
  var chDot = document.getElementById('chDot');

  // path suave: bezier com controle no meio-x entre amostras (receita JL)
  function buildPath(vals) {
    var W = 560, H = 150, pad = 8;
    var n = vals.length, pts = vals.map(function (v, i) {
      return [pad + i * ((W - 2 * pad) / (n - 1)), H - pad - (v / 100) * (H - 2 * pad)];
    });
    var d = 'M ' + pts[0][0] + ' ' + pts[0][1];
    for (var i = 1; i < n; i++) {
      var mx = (pts[i - 1][0] + pts[i][0]) / 2;
      d += ' C ' + mx + ' ' + pts[i - 1][1] + ' ' + mx + ' ' + pts[i][1] + ' ' + pts[i][0] + ' ' + pts[i][1];
    }
    return { d: d, last: pts[n - 1] };
  }
  var built = buildPath(DADOS);
  chLine.setAttribute('d', built.d);
  chArea.setAttribute('d', built.d + ' L 552 142 L 8 142 Z');
  chDot.setAttribute('cx', built.last[0]);
  chDot.setAttribute('cy', built.last[1]);

  var fmtBR = function (n) { return Math.round(n).toLocaleString('pt-BR'); };

  function playMock() {
    if (reduced) {
      arc.setAttribute('stroke-dasharray', '100 100');
      ringNum.textContent = '47';
      kpiFat.textContent = fmtBR(8430);
      kpiVendas.textContent = '47';
      chDot.style.opacity = '1';
      toast.classList.add('is-in');
      return;
    }
    // draw-in do gráfico
    var len = chLine.getTotalLength();
    chLine.style.strokeDasharray = len + ' ' + len;
    chLine.style.strokeDashoffset = len;
    chArea.style.opacity = '0';
    requestAnimationFrame(function () {
      chLine.style.transition = 'stroke-dashoffset 1.6s cubic-bezier(.16,1,.3,1)';
      chArea.style.transition = 'opacity 1.2s ease .5s';
      chLine.style.strokeDashoffset = '0';
      chArea.style.opacity = '1';
      setTimeout(function () { chDot.style.transition = 'opacity .4s'; chDot.style.opacity = '1'; }, 1500);
    });
    // anel + count-ups perseguem o alvo com lerp por frame
    var cur = { ring: 0, fat: 0, ven: 0 };
    var alvo = { ring: 100, fat: 8430, ven: 47 };
    (function fluir() {
      var done = true;
      ['ring', 'fat', 'ven'].forEach(function (k) {
        cur[k] += (alvo[k] - cur[k]) * 0.045;
        if (alvo[k] - cur[k] > 0.5) done = false;
      });
      arc.setAttribute('stroke-dasharray', Math.min(100, cur.ring) + ' 100');
      ringNum.textContent = Math.round(47 * cur.ring / 100);
      kpiFat.textContent = fmtBR(cur.fat);
      kpiVendas.textContent = Math.round(cur.ven);
      if (!done) { requestAnimationFrame(fluir); }
      else {
        arc.setAttribute('stroke-dasharray', '100 100');
        ringNum.textContent = '47'; kpiFat.textContent = fmtBR(8430); kpiVendas.textContent = '47';
      }
    })();
    setTimeout(function () { toast.classList.add('is-in'); }, 1300);
  }

  var ioMock = new IntersectionObserver(function (entries) {
    if (entries[0].isIntersecting) { playMock(); ioMock.disconnect(); }
  }, { threshold: 0.35 });
  ioMock.observe(stage);

  /* ---------- ano do rodapé ---------- */
  var ano = document.getElementById('ano');
  if (ano) ano.textContent = new Date().getFullYear();

  /* ---------- formulário de demonstração (mesmo contrato da v1) ---------- */
  var form = document.getElementById('demoForm');
  if (!form) return;
  var endpoint = form.getAttribute('action');
  var tokenMeta = document.querySelector('meta[name="csrf-token"]');
  var token = tokenMeta ? tokenMeta.getAttribute('content') : '';
  var btn = form.querySelector('button[type="submit"]');
  var btnText = btn ? btn.textContent : '';
  var feedback = document.getElementById('formMsg');

  function showMsg(text, ok) {
    if (!feedback) return;
    feedback.textContent = text;
    feedback.className = 'form__feedback ' + (ok ? 'is-ok' : 'is-err');
    feedback.hidden = false;
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (!form.reportValidity()) return;
    if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }
    if (feedback) feedback.hidden = true;

    fetch(endpoint, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      body: new FormData(form),
    })
      .then(function (r) { if (!r.ok) throw new Error('status ' + r.status); return r.json(); })
      .then(function (data) {
        showMsg(data.message || 'Recebemos seu pedido! Em breve entramos em contato.', true);
        form.reset();
        if (btn) btn.textContent = 'Pedido enviado com sucesso';
      })
      .catch(function () {
        showMsg('Não foi possível enviar agora. Tente o WhatsApp abaixo.', false);
        if (btn) { btn.disabled = false; btn.textContent = btnText; }
      });
  });
})();
</script>
</body>
</html>
