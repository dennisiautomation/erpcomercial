<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Sistema para rede de lojas · ERP Comercial IA365</title>
<meta name="description" content="Sistema para quem tem de 2 a 10 lojas: estoque, caixa, vendedores e nota fiscal de todas as lojas no mesmo painel, com venda na maquininha Stone, loja online e atendente de IA no WhatsApp.">
<meta property="og:title" content="Sistema para rede de lojas · ERP Comercial IA365">
<meta property="og:description" content="Todas as suas lojas no mesmo painel. Estoque, caixa, vendedores e nota fiscal, com implantação feita pela nossa equipe.">
<meta property="og:type" content="website">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%231d1d1f'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='16' font-weight='bold' fill='white' text-anchor='middle'%3EI%3C/text%3E%3C/svg%3E">
<script>document.documentElement.className+=" js";</script>
<style>
/* ============================================================
   ERP Comercial IA365 — página "rede de lojas" (/redes)
   Mesmo sistema visual da landing V2: superfícies #fff / #f5f5f7,
   tinta #1d1d1f, azul de ação #0071e3, semânticas iOS só para DADO.
   O herói é o painel da rede: uma linha por loja.
   ============================================================ */
:root{
  --bg:#ffffff; --bg2:#f5f5f7;
  --ink:#1d1d1f; --ink2:#6e6e73; --ink3:#86868b;
  --sep:rgba(0,0,0,.09);
  --blue:#0071e3; --blue-h:#0077ed; --link:#06c;
  --ok:#34c759; --okD:#248a3d;
  --warn:#ff9500; --warnD:#c93400;
  --bad:#ff3b30; --badD:#d70015;
  --r:18px;
  --shadow:0 1px 2px rgba(0,0,0,.04),0 8px 32px rgba(0,0,0,.07);
  --ease:cubic-bezier(.16,1,.3,1);
}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{
  font-family:-apple-system,BlinkMacSystemFont,'SF Pro Display','SF Pro Text','Segoe UI',Roboto,Helvetica,Arial,sans-serif;
  background:var(--bg);color:var(--ink);font-size:17px;line-height:1.55;overflow-x:hidden;
  -webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;
}
::selection{background:rgba(0,113,227,.18)}
.wrap{max-width:1040px;margin-inline:auto;padding-inline:22px}
a{color:var(--link);text-decoration:none}
a:hover{text-decoration:underline}
.skip{position:absolute;left:-9999px;top:0;background:var(--ink);color:#fff;padding:.6rem 1rem;border-radius:0 0 10px 0;z-index:100}
.skip:focus{left:0}
:focus-visible{outline:2px solid var(--blue);outline-offset:3px;border-radius:4px}
h1,h2,h3{letter-spacing:-.015em;text-wrap:balance}
.d2{font-size:clamp(1.8rem,3.8vw,2.7rem);font-weight:700;line-height:1.12}
.lede{font-size:clamp(1.05rem,1.7vw,1.25rem);color:var(--ink2);line-height:1.5;text-wrap:pretty;max-width:60ch}
.kicker{display:block;font-size:1rem;font-weight:600;color:var(--okD);margin-bottom:.8rem}
.num{font-variant-numeric:tabular-nums}

.pill{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;background:var(--blue);color:#fff;border:none;cursor:pointer;
  font:inherit;font-size:1.02rem;font-weight:500;padding:.72rem 1.35rem;border-radius:980px;min-height:44px;
  transition:background .18s var(--ease),transform .18s var(--ease)}
.pill:hover{background:var(--blue-h);text-decoration:none;color:#fff}
.pill:active{transform:scale(.985)}
.pill--ghost{background:transparent;color:var(--blue);box-shadow:inset 0 0 0 1px var(--blue)}
.pill--ghost:hover{background:var(--blue);color:#fff}
.pill{white-space:nowrap}
.pill--block{width:100%;white-space:normal}
.pill--wa{background:#1f8f4e}
.pill--wa:hover{background:#1a7d44}

/* nav */
.nav{position:sticky;top:0;z-index:60;background:rgba(255,255,255,.86);
  -webkit-backdrop-filter:blur(20px) saturate(1.8);backdrop-filter:blur(20px) saturate(1.8);border-bottom:1px solid var(--sep)}
.nav__in{display:flex;align-items:center;gap:1rem;height:52px}
.brand{display:inline-flex;align-items:center;gap:.55rem;font-weight:600;color:var(--ink);font-size:1rem;white-space:nowrap}
.brand:hover{text-decoration:none}
.brand__mark{width:26px;height:26px;border-radius:7px;background:var(--ink);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700}
.brand small{color:var(--ink3);font-weight:500}
.nav__cta{margin-left:auto;display:flex;align-items:center;gap:1rem}
.nav__cta .entrar{font-size:.88rem;color:var(--ink);opacity:.85}
.nav__cta .pill{font-size:.82rem;padding:.36rem .95rem;min-height:32px}
@media(max-width:480px){.brand small{display:none}}
@media(max-width:430px){.nav__in{gap:.6rem}.nav__cta{gap:.7rem}.nav__cta .pill{font-size:.78rem;padding:.34rem .75rem}.brand{font-size:.92rem;gap:.4rem}}
@media(max-width:360px){.nav__cta .entrar{display:none}}

/* hero */
.hero{padding:clamp(2.2rem,5vw,3.6rem) 0 0;text-align:center}
.hero h1{font-size:clamp(2.1rem,5.2vw,3.6rem);font-weight:700;line-height:1.07}
.hero .lede{margin:.9rem auto 0}
.hero__actions{display:flex;gap:.9rem;justify-content:center;align-items:center;margin-top:1.4rem;flex-wrap:wrap}
.hero__fine{margin-top:.8rem;font-size:.88rem;color:var(--ink3)}

/* painel da rede (mock) */
.stage{max-width:980px;margin:clamp(1.8rem,3.5vw,2.6rem) auto 0;padding-inline:22px}
.board{background:var(--bg2);border:1px solid rgba(0,0,0,.07);border-radius:22px;text-align:left;
  box-shadow:0 2px 6px rgba(0,0,0,.06),0 24px 70px rgba(0,0,0,.13);overflow:hidden}
.board__top{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.2rem;flex-wrap:wrap}
.board__top b{font-size:1.05rem}
.board__top span{font-size:.82rem;color:var(--ink3)}
.board__kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:.7rem;padding:0 1.2rem 1rem}
.kp{background:#fff;border-radius:14px;padding:.8rem .9rem;min-width:0}
.kp span{display:block;font-size:.78rem;color:var(--ink2)}
.kp b{font-size:clamp(1.05rem,2.6vw,1.5rem);font-weight:700}
.board__scroll{overflow-x:auto;background:#fff;border-top:1px solid var(--sep)}
.lojas{width:100%;border-collapse:collapse;font-size:.92rem;min-width:560px}
.lojas th{font-size:.74rem;font-weight:600;color:var(--ink3);text-align:left;padding:.6rem 1.2rem;border-bottom:1px solid var(--sep);white-space:nowrap}
.lojas td{padding:.72rem 1.2rem;border-bottom:1px solid var(--sep);white-space:nowrap}
.lojas tr:last-child td{border-bottom:0}
.lojas .r{text-align:right}
.chip{display:inline-flex;align-items:center;gap:.35rem;font-size:.78rem;font-weight:600;padding:.16rem .6rem;border-radius:980px}
.chip::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}
.chip--ok{background:rgba(52,199,89,.14);color:var(--okD)}
.chip--warn{background:rgba(255,149,0,.15);color:var(--warnD)}
.chip--bad{background:rgba(255,59,48,.12);color:var(--badD)}
.board__note{padding:.6rem 1.2rem;font-size:.76rem;color:var(--ink3);background:#fff;border-top:1px solid var(--sep)}

/* seções */
.sec{padding:clamp(3.2rem,7vw,5.5rem) 0}
.sec--gray{background:var(--bg2)}
.sec--dark{background:#1d1d1f;color:#f5f5f7}
.sec--dark .lede{color:#a1a1a6}
.sec--dark .kicker{color:#63e6a2}
.sec__head{margin-bottom:2.2rem}
.sec__head .lede{margin-top:.8rem}

/* prova em números */
.proof{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:var(--sep);border-block:1px solid var(--sep);margin-top:clamp(2.4rem,5vw,3.6rem)}
.proof div{background:#fff;padding:1.4rem 1rem;text-align:center}
.proof b{display:block;font-size:clamp(1.5rem,3.6vw,2.3rem);font-weight:700;line-height:1.1}
.proof span{font-size:.9rem;color:var(--ink2)}
@media(max-width:640px){.proof{grid-template-columns:1fr}}

/* o que muda: linhas, não cards */
.rows{border-top:1px solid var(--sep)}
.row{display:grid;grid-template-columns:minmax(0,5fr) minmax(0,7fr);gap:.4rem 2rem;padding:1.5rem 0;border-bottom:1px solid var(--sep)}
.row h3{font-size:1.2rem;font-weight:600}
.row p{color:var(--ink2)}
.row small{display:block;color:var(--ink3);font-size:.86rem;margin-top:.3rem}
@media(max-width:720px){.row{grid-template-columns:1fr}}

/* IA no WhatsApp */
.ia{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:2.5rem;align-items:center}
@media(max-width:820px){.ia{grid-template-columns:1fr}}
.ia ul{list-style:none;margin-top:1.4rem;display:grid;gap:.7rem}
.ia li{display:flex;gap:.6rem;color:#d2d2d7}
.ia li svg{flex:none;width:1.1em;height:1.1em;margin-top:.25em;color:#63e6a2}
.chat{background:#0b141a;border-radius:22px;padding:1rem;display:flex;flex-direction:column;gap:.55rem;max-width:400px;width:100%;margin-inline:auto;border:1px solid rgba(255,255,255,.08)}
.chat__top{display:flex;align-items:center;gap:.6rem;padding-bottom:.7rem;border-bottom:1px solid rgba(255,255,255,.08);font-size:.9rem;color:#e9edef}
.chat__top i{width:30px;height:30px;border-radius:50%;background:#1f8f4e;display:inline-flex;align-items:center;justify-content:center;font-style:normal;font-weight:700;font-size:.8rem;color:#fff}
.chat__top small{display:block;color:#8696a0;font-size:.74rem}
.msg{max-width:86%;padding:.5rem .7rem;border-radius:12px;font-size:.9rem;line-height:1.4;color:#e9edef}
.msg--in{background:#202c33;align-self:flex-start;border-top-left-radius:3px}
.msg--out{background:#005c4b;align-self:flex-end;border-top-right-radius:3px}
.chat__note{font-size:.72rem;color:#8696a0;text-align:center;margin-top:.2rem}

/* comparação */
.cmp__scroll{overflow-x:auto;border-radius:var(--r);background:#fff;box-shadow:var(--shadow)}
.cmp{width:100%;border-collapse:collapse;min-width:600px;font-size:.96rem}
.cmp th,.cmp td{padding:.95rem 1.1rem;text-align:left;border-bottom:1px solid var(--sep);vertical-align:top}
.cmp tr:last-child td{border-bottom:0}
.cmp th{font-size:.82rem;color:var(--ink3);font-weight:600}
.cmp th.me,.cmp td.me{background:rgba(0,113,227,.05)}
.cmp th.me{color:var(--blue)}
.cmp td:first-child{font-weight:600;width:28%}
.cmp td{color:var(--ink2)}
.cmp td.me{color:var(--ink)}

/* preço + troca */
.price{display:grid;grid-template-columns:minmax(0,5fr) minmax(0,7fr);gap:2rem;align-items:start}
@media(max-width:820px){.price{grid-template-columns:1fr}}
.price__box{background:#fff;border-radius:var(--r);box-shadow:var(--shadow);padding:1.6rem}
.price__box .from{font-size:.9rem;color:var(--ink2)}
.price__box .val{font-size:clamp(2.2rem,5vw,3rem);font-weight:700;line-height:1.1}
.price__box .val small{font-size:1rem;font-weight:500;color:var(--ink2)}
.price__box ul{list-style:none;margin:1rem 0 1.3rem;display:grid;gap:.5rem;font-size:.96rem}
.price__box li{display:flex;gap:.55rem}
.price__box li svg{flex:none;width:1.05em;height:1.05em;margin-top:.28em;color:var(--okD)}
.steps{list-style:none;counter-reset:s;display:grid;gap:1.1rem}
.steps li{counter-increment:s;display:grid;grid-template-columns:auto 1fr;gap:.2rem .9rem}
.steps li::before{content:counter(s);grid-row:span 2;width:34px;height:34px;border-radius:50%;background:var(--ink);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.95rem}
.steps b{font-size:1.08rem}
.steps span{color:var(--ink2);font-size:.97rem}

/* formulário */
.cta__grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:2.5rem;align-items:start}
@media(max-width:820px){.cta__grid{grid-template-columns:1fr}}
.cta__list{list-style:none;margin-top:1.4rem;display:grid;gap:.6rem}
.cta__list li{display:flex;gap:.55rem}
.cta__list svg{flex:none;width:1.05em;height:1.05em;margin-top:.28em;color:var(--okD)}
.form{background:#fff;border-radius:22px;box-shadow:var(--shadow);padding:1.6rem;position:relative}
.form h3{font-size:1.3rem;font-weight:600}
.form>p{color:var(--ink2);font-size:.95rem;margin:.2rem 0 1rem}
.field{margin-bottom:.85rem}
.field--row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
@media(max-width:480px){.field--row{grid-template-columns:1fr}}
.field label{display:block;font-size:.84rem;font-weight:600;margin-bottom:.3rem}
.field input,.field select{width:100%;font:inherit;font-size:1rem;padding:.7rem .8rem;border:1px solid #d2d2d7;border-radius:12px;background:#fff;color:var(--ink);min-height:46px}
.field input:focus,.field select:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,113,227,.18)}
.form__fine{font-size:.8rem;color:var(--ink3);margin-top:.8rem}
.form__feedback{margin-top:.8rem;padding:.7rem .9rem;border-radius:12px;font-size:.92rem;font-weight:500}
.form__feedback.is-ok{background:rgba(52,199,89,.14);color:var(--okD)}
.form__feedback.is-err{background:rgba(255,59,48,.1);color:var(--badD)}
.form__alt{margin-top:.9rem;font-size:.92rem;text-align:center}


/* ---------- movimento ao rolar ---------- */
.prog{position:fixed;left:0;top:0;height:3px;width:100%;transform-origin:0 50%;transform:scaleX(0);background:var(--blue);z-index:70}
html.js .rv{opacity:0;transform:translateY(26px);transition:opacity .7s var(--ease),transform .7s var(--ease);transition-delay:var(--d,0s)}
html.js .rv.in{opacity:1;transform:none}
.lojas tr{transition:background .6s var(--ease)}
.lojas tr.pulse{background:rgba(52,199,89,.14)}
.board{transform:perspective(1400px) rotateX(var(--tilt,0deg));transform-origin:50% 0;transition:transform .15s linear}

/* ---------- um estoque só ---------- */
.uno{background:var(--bg2);border-radius:26px;padding:clamp(1rem,2.5vw,1.8rem)}
.uno__stock{position:sticky;top:60px;z-index:5;display:flex;align-items:center;gap:.9rem;flex-wrap:wrap;background:#1d1d1f;color:#fff;border-radius:16px;padding:.8rem 1.1rem;box-shadow:0 8px 24px rgba(0,0,0,.18)}
.uno__label{font-size:.9rem;color:#d2d2d7}
.uno__n{font-size:1.9rem;font-weight:700;line-height:1;min-width:1.4ch;text-align:center;background:#34c759;color:#06310f;border-radius:10px;padding:.15rem .55rem;transition:background .4s var(--ease),transform .4s var(--ease)}
.uno__n.bump{transform:scale(1.25)}
.uno[data-n="1"] .uno__n{background:#ff9500;color:#3a2000}
.uno[data-n="0"] .uno__n{background:#ff453a;color:#fff}
.uno__msg{font-size:.9rem;color:#f5f5f7;margin-left:auto}
.uno__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1.2rem;margin-top:1.6rem;align-items:start}
@media(max-width:860px){.uno__grid{grid-template-columns:1fr;gap:2.2rem}.uno__stock{gap:.25rem .7rem;padding:.6rem .85rem}.uno__label{flex:1;font-size:.8rem}.uno__n{font-size:1.4rem}.uno__msg{margin-left:0;flex-basis:100%;font-size:.8rem}}
.dev{display:flex;flex-direction:column;align-items:center;gap:1rem;text-align:center}
.dev figcaption{font-size:.95rem;color:var(--ink2);max-width:34ch}
.dev figcaption b{display:block;color:var(--ink);font-size:1.08rem;margin-bottom:.2rem}
.uno__note{font-size:.78rem;color:var(--ink3);text-align:center;margin-top:1.4rem}

/* maquininha */
.pos{position:relative;width:200px;padding:46px 14px 16px;background:linear-gradient(160deg,#17a065,#0c7d4c);border-radius:26px;box-shadow:0 18px 40px rgba(12,125,76,.32),inset 0 1px 0 rgba(255,255,255,.25)}
.pos__paper{position:absolute;left:34px;right:34px;top:6px;height:0;overflow:hidden;background:#fff;border-radius:4px 4px 0 0;box-shadow:0 -2px 8px rgba(0,0,0,.15);display:flex;flex-direction:column;gap:5px;padding:0 10px;transition:height .7s var(--ease),top .7s var(--ease),padding .7s var(--ease)}
.pos__paper i{display:block;height:4px;border-radius:2px;background:#d2d2d7}
.pos__paper i:nth-child(2){width:70%}
.pos__paper i:nth-child(3){width:45%}
.pos__paper b{font-size:.62rem;color:#248a3d;letter-spacing:.02em}
.dev.is-on .pos__paper{height:64px;top:-30px;padding:9px 10px}
.pos__screen{background:#0f1512;border-radius:14px;padding:10px;color:#f5f5f7;text-align:left;display:flex;flex-direction:column;gap:7px;min-height:226px}
.pos__bar{font-size:.7rem;color:#9fb3a8;border-bottom:1px solid rgba(255,255,255,.1);padding-bottom:6px}
.pos__item{display:flex;justify-content:space-between;gap:.5rem;font-size:.76rem}
.pos__item--soft{color:#9fb3a8}
.pos__total{display:flex;justify-content:space-between;align-items:baseline;margin-top:auto;font-size:.76rem;color:#9fb3a8}
.pos__total b{font-size:1.25rem;color:#fff}
.pos__btn{border-radius:10px;padding:.55rem .4rem;text-align:center;font-size:.78rem;font-weight:600}
.pos__btn--pay{background:#fff;color:#0f1512}
.pos__btn--ok{background:#34c759;color:#06310f;display:none}
.dev.is-on .pos__btn--pay{display:none}
.dev.is-on .pos__btn--ok{display:block}
.pos__keys{display:flex;justify-content:center;gap:16px;margin-top:14px}
.pos__keys i{width:26px;height:8px;border-radius:4px;background:rgba(255,255,255,.35)}
.pos__keys i:nth-child(1){background:#ff6961}.pos__keys i:nth-child(2){background:#ffd60a}.pos__keys i:nth-child(3){background:#d8ffe7}

/* celular com a loja online */
.phone{position:relative;width:210px;aspect-ratio:9/18.5;background:#1d1d1f;border-radius:34px;padding:9px;box-shadow:0 18px 44px rgba(0,0,0,.22)}
.phone__in{width:100%;height:100%;border-radius:26px;overflow:hidden;background:#fff}
.phone__in img{display:block;width:100%;height:auto;transform:translateY(calc(var(--p,0) * -58%));transition:transform .2s linear;will-change:transform}
.phone__toast{position:absolute;left:16px;right:16px;bottom:18px;background:rgba(255,255,255,.96);border-radius:14px;padding:.55rem .7rem;text-align:left;box-shadow:0 8px 24px rgba(0,0,0,.25);font-size:.72rem;color:var(--ink2);opacity:0;transform:translateY(20px);transition:opacity .5s var(--ease),transform .5s var(--ease)}
.phone__toast b{display:block;color:var(--okD);font-size:.76rem}
.dev.is-on .phone__toast{opacity:1;transform:none}

/* conversa pequena */
.chat--mini{max-width:230px;min-height:300px;border-radius:20px;padding:.8rem;text-align:left}
.chat--mini .msg{font-size:.78rem}
.wa__r1,.wa__r2,.wa__r3{opacity:0;transform:translateY(8px);transition:opacity .45s var(--ease),transform .45s var(--ease)}
.dev.is-on .wa__r1,.dev.is-on .wa__r2,.dev.is-on .wa__r3{opacity:1;transform:none}
.dev.is-on .wa__r2{transition-delay:.7s}
.dev.is-on .wa__r3{transition-delay:1.4s}

/* modelos de loja */
.modelos{display:grid;grid-template-columns:minmax(0,4fr) minmax(0,8fr);gap:1.6rem;align-items:center;margin-top:clamp(2.2rem,5vw,3.4rem)}
@media(max-width:860px){.modelos{grid-template-columns:1fr}}
.modelos__txt h3{font-size:1.3rem;font-weight:600;margin-bottom:.4rem}
.modelos__txt p{color:var(--ink2)}
.modelos__row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem}
.brow{border-radius:12px;overflow:hidden;border:1px solid var(--sep);box-shadow:var(--shadow);background:#fff;transition:transform .5s var(--ease)}
.brow:hover{transform:translateY(-6px)}
.brow:nth-child(2){transform:translateY(calc(var(--m,0) * -18px))}
.brow__bar{display:flex;gap:4px;padding:6px 8px;background:var(--bg2);border-bottom:1px solid var(--sep)}
.brow__bar i{width:6px;height:6px;border-radius:50%;background:#c7c7cc}
.brow img{display:block;width:100%;height:auto}

@media(prefers-reduced-motion:reduce){
  html.js .rv{opacity:1;transform:none;transition:none}
  .board{transform:none}
  .phone__in img{transform:none;transition:none}
  .pos__paper,.phone__toast,.wa__r1,.wa__r2,.wa__r3,.uno__n{transition:none}
  .prog{display:none}
}

.foot{padding:1.6rem 0 2.2rem;border-top:1px solid var(--sep);font-size:.82rem;color:var(--ink3)}
.foot .wrap{display:flex;gap:.6rem 1.4rem;flex-wrap:wrap;justify-content:space-between}
[hidden]{display:none!important}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}.pill{transition:none}}
</style>
</head>
<body>
@php
  $wa = 'https://wa.me/5511917120940?text=' . rawurlencode('Tenho uma rede de lojas e quero conhecer o ERP Comercial');
  $check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
@endphp
<a class="skip" href="#main">Ir para o conteúdo</a>
<div class="prog" id="prog" aria-hidden="true"></div>

<header class="nav">
  <div class="wrap nav__in">
    <a class="brand" href="{{ route('site.home') }}"><span class="brand__mark">IA</span> ERP Comercial <small>para redes de lojas</small></a>
    <div class="nav__cta">
      <a class="entrar" href="{{ route('login') }}">Entrar</a>
      <a class="pill" href="#demo">Ver demonstração</a>
    </div>
  </div>
</header>

<main id="main">

<section class="hero">
  <div class="wrap">
    <h1>O sistema para quem tem mais de uma loja.</h1>
    <p class="lede">Estoque, caixa, vendedores e nota fiscal de todas as lojas no mesmo painel, com venda na maquininha Stone, loja online e atendente de IA no WhatsApp.</p>
    <div class="hero__actions">
      <a class="pill" href="#demo">Quero ver com as minhas lojas</a>
      <a class="pill pill--ghost" href="{{ $wa }}" target="_blank" rel="noopener">Chamar no WhatsApp</a>
    </div>
    <p class="hero__fine">Feito para redes de 2 a 10 lojas. A implantação é feita pela nossa equipe.</p>
  </div>

  <div class="stage">
    <div class="board" role="img" aria-label="Exemplo do painel da rede, com uma linha para cada loja">
      <div class="board__top"><b>Minha rede · hoje</b><span>atualizado agora há pouco</span></div>
      <div class="board__kpis">
        <div class="kp"><span>Vendido hoje</span><b class="num">R$ <span id="kTot" data-count="31480">31.480</span></b></div>
        <div class="kp"><span>Vendas</span><b class="num"><span id="kVen" data-count="262">262</span></b></div>
        <div class="kp"><span>Notas emitidas</span><b class="num"><span id="kNf1">262</span> de <span id="kNf2">262</span></b></div>
      </div>
      <div class="board__scroll">
        <table class="lojas">
          <thead><tr><th>Loja</th><th class="r">Vendido hoje</th><th class="r">Vendas</th><th>Caixa</th><th>Estoque</th></tr></thead>
          <tbody>
            <tr><td>Centro</td><td class="r num">R$ 9.870</td><td class="r num">81</td><td><span class="chip chip--ok">Aberto</span></td><td><span class="chip chip--ok">Em dia</span></td></tr>
            <tr><td>Shopping</td><td class="r num">R$ 8.240</td><td class="r num">64</td><td><span class="chip chip--ok">Aberto</span></td><td><span class="chip chip--warn">3 itens acabando</span></td></tr>
            <tr><td>Zona Leste</td><td class="r num">R$ 6.910</td><td class="r num">58</td><td><span class="chip chip--ok">Aberto</span></td><td><span class="chip chip--ok">Em dia</span></td></tr>
            <tr><td>Zona Sul</td><td class="r num">R$ 4.130</td><td class="r num">37</td><td><span class="chip chip--bad">Fechou com diferença</span></td><td><span class="chip chip--ok">Em dia</span></td></tr>
            <tr><td>Timon</td><td class="r num">R$ 2.330</td><td class="r num">22</td><td><span class="chip chip--ok">Aberto</span></td><td><span class="chip chip--warn">Transferência a caminho</span></td></tr>
          </tbody>
        </table>
      </div>
      <div class="board__note">Tela de exemplo, com dados de demonstração.</div>
    </div>
  </div>

  {{-- Números reais de uso (banco de produção, 30 dias até 14/11/2026). Atualizar ou retirar antes de publicar. --}}
  <div class="proof">
    <div><b class="num"><span data-count="14">14</span> lojas</b><span>de duas redes de Teresina já operam no sistema</span></div>
    <div><b class="num"><span data-count="7900">7.900</span>+</b><span>vendas passaram por elas nos últimos 30 dias</span></div>
    <div><b class="num"><span data-count="54">54</span> pessoas</b><span>usam todo dia: dono, gerente, caixa e vendedor</span></div>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div class="sec__head">
      <span class="kicker">O que muda quando a loja vira rede</span>
      <h2 class="d2">Uma loja você controla de olho. Cinco, não.</h2>
    </div>
    <div class="rows">
      <div class="row">
        <h3>Estoque de cada loja, e de todas</h3>
        <p>Você vê o que tem em cada loja e transfere de uma para outra com pedido e aprovação. O vendedor consulta a peça nas outras lojas antes de dizer que não tem.<small>Transferência entre lojas, estoque por loja e relatório de várias lojas juntas.</small></p>
      </div>
      <div class="row">
        <h3>Caixa fechado no mesmo dia</h3>
        <p>Cada loja abre e fecha o seu caixa. Se fechou com diferença, aparece para você na hora, com quem abriu, quem fechou e quanto faltou.<small>Caixa por loja e por operador, com sangria, suprimento e conferência.</small></p>
      </div>
      <div class="row">
        <h3>Vendedor, meta e comissão por loja</h3>
        <p>Cada venda tem dono. A comissão sai calculada pelo percentual de cada vendedor, e o gerente só enxerga a loja dele.<small>Sete perfis de acesso: dono, gerente, financeiro, vendedor, caixa e consulta.</small></p>
      </div>
      <div class="row">
        <h3>Nota fiscal sai sozinha em todas</h3>
        <p>A venda fecha no caixa e a nota é emitida com o CNPJ da loja certa. Você não precisa entender de imposto, e o contador recebe tudo organizado.<small>NFC-e, NF-e e NFS-e, com um certificado por loja.</small></p>
      </div>
      <div class="row">
        <h3>O resultado da rede e de cada loja</h3>
        <p>Contas a pagar, contas a receber, folha e o resultado do mês, por loja ou somando tudo. Dá para ver qual loja paga a conta e qual está pesando.<small>Financeiro completo, com resultado por loja e consolidado.</small></p>
      </div>
    </div>
  </div>
</section>

<section class="sec sec--dark">
  <div class="wrap ia">
    <div>
      <span class="kicker">Atendente de IA no WhatsApp</span>
      <h2 class="d2">Sua loja responde o cliente às 11 da noite.</h2>
      <p class="lede" style="margin-top:.8rem">O atendente de IA conversa no WhatsApp da loja, consulta o estoque de verdade e monta o pedido. Quando precisa de gente, passa para o seu vendedor.</p>
      <ul>
        <li>{!! $check !!} Responde preço e disponibilidade olhando o estoque do sistema</li>
        <li>{!! $check !!} Monta o pedido e manda o link de pagamento</li>
        <li>{!! $check !!} Transfere para uma pessoa da loja quando o cliente pede</li>
      </ul>
    </div>
    <div class="chat" role="img" aria-label="Exemplo de conversa do atendente de IA com uma cliente">
      <div class="chat__top"><i>L</i><span>Loja Centro<small>atendente de IA</small></span></div>
      <div class="msg msg--in">Oi, tem a sandália preta no 37?</div>
      <div class="msg msg--out">Oi! Tenho sim. No Centro tem 2 pares e no Shopping tem 1. Sai por R$ 149,90.</div>
      <div class="msg msg--in">Quero uma, retiro amanhã no Centro</div>
      <div class="msg msg--out">Separado no Centro no seu nome. Segue o link para pagar no PIX ou cartão.</div>
      <div class="chat__note">Conversa de exemplo.</div>
    </div>
  </div>
</section>

<section class="sec sec--gray">
  <div class="wrap">
    <div class="sec__head">
      <span class="kicker">Antes de comparar preço</span>
      <h2 class="d2">O sistema de R$ 99 foi feito para uma loja.</h2>
      <p class="lede">Ele resolve bem o começo. O problema aparece quando você abre a segunda loja e precisa de mais gente usando.</p>
    </div>
    <div class="cmp__scroll">
      <table class="cmp">
        <thead><tr><th></th><th>Plano de entrada de sistema popular</th><th class="me">ERP Comercial para redes</th></tr></thead>
        <tbody>
          <tr><td>Lojas</td><td>Uma empresa. Outra loja costuma ser outro plano ou outra conta.</td><td class="me">Todas as lojas no mesmo painel, cada uma com seu estoque, caixa e CNPJ.</td></tr>
          <tr><td>Pessoas usando</td><td>Poucos usuários. Passou disso, sobe de plano.</td><td class="me">Dono, gerentes, caixas e vendedores, cada um vendo só o que deve.</td></tr>
          <tr><td>Colocar para rodar</td><td>Você mesmo configura, com vídeo e central de ajuda.</td><td class="me">Nossa equipe cadastra as lojas, importa os produtos e treina o pessoal.</td></tr>
          <tr><td>Quando dá problema</td><td>Fila de atendimento.</td><td class="me">Você fala com quem implantou a sua rede.</td></tr>
          <tr><td>Maquininha e internet</td><td>Maquininha separada do sistema. Loja online costuma ser outro serviço.</td><td class="me">Venda na maquininha Stone e loja online ligadas ao mesmo estoque.</td></tr>
          <tr><td>WhatsApp</td><td>Em geral não tem atendente que vende.</td><td class="me">Atendente de IA ligado ao estoque das lojas.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section class="sec" id="canais">
  <div class="wrap">
    <div class="sec__head">
      <span class="kicker">Venda onde o cliente estiver</span>
      <h2 class="d2">Na maquininha, na internet e no WhatsApp. O estoque é um só.</h2>
      <p class="lede">Cada venda, venha de onde vier, baixa o mesmo estoque na mesma hora. Ninguém vende o que já saiu.</p>
    </div>

    <div class="uno" id="uno">
      <div class="uno__stock">
        <span class="uno__label">Sandália preta 37 · estoque da rede</span>
        <b class="uno__n num" id="unoN">0</b>
        <span class="uno__msg" id="unoMsg">Acabou. Reposição pedida ao fornecedor.</span>
      </div>

      <div class="uno__grid">
        <figure class="dev is-on" data-dev="pos">
          <div class="pos" aria-hidden="true">
            <div class="pos__paper"><i></i><i></i><i></i><b>NFC-e emitida</b></div>
            <div class="pos__screen">
              <div class="pos__bar">Venda · Loja Centro</div>
              <div class="pos__item"><span>Sandália preta 37</span><b class="num">R$ 149,90</b></div>
              <div class="pos__item pos__item--soft"><span>Vendedora</span><b>Ana</b></div>
              <div class="pos__total"><span>Total</span><b class="num">R$ 149,90</b></div>
              <div class="pos__btn pos__btn--pay">Cobrar no cartão</div>
              <div class="pos__btn pos__btn--ok">Aprovado · nota emitida</div>
            </div>
            <div class="pos__keys"><i></i><i></i><i></i></div>
          </div>
          <figcaption><b>Maquininha Stone</b>O vendedor fecha a venda na própria maquininha, no corredor ou na fila. Estoque, caixa e nota saem dali.</figcaption>
        </figure>

        <figure class="dev is-on" data-dev="loja">
          <div class="phone" aria-hidden="true">
            <div class="phone__in"><img id="lojaImg" src="{{ asset('site/redes/loja-celular.jpg') }}" alt="" width="390" height="1440" loading="lazy"></div>
            <div class="phone__toast"><b>Pedido novo da loja online</b><span>Sandália preta 37 · PIX pago</span></div>
          </div>
          <figcaption><b>Loja online</b>A vitrine na internet usa os produtos e o estoque do sistema. O cliente paga por PIX ou cartão e o pedido chega junto com os das lojas.</figcaption>
        </figure>

        <figure class="dev is-on" data-dev="wa">
          <div class="chat chat--mini" aria-hidden="true">
            <div class="chat__top"><i>L</i><span>Loja Centro<small>atendente de IA</small></span></div>
            <div class="msg msg--in">Ainda tem a sandália preta 37?</div>
            <div class="msg msg--out wa__r1">Tem o último par, no Shopping. Separo para você?</div>
            <div class="msg msg--in wa__r2">Separa, pago agora</div>
            <div class="msg msg--out wa__r3">Separado. Segue o link do PIX.</div>
          </div>
          <figcaption><b>WhatsApp com atendente de IA</b>Responde olhando o estoque de todas as lojas e fecha o pedido, a qualquer hora.</figcaption>
        </figure>
      </div>
      <p class="uno__note">Telas de exemplo. A loja online mostrada é uma loja de demonstração do sistema.</p>
    </div>

    <div class="modelos">
      <div class="modelos__txt">
        <h3>Seis modelos de loja online prontos</h3>
        <p>Você escolhe o modelo, troca cores e fotos num editor visual e publica. Pode usar o seu próprio endereço na internet.</p>
      </div>
      <div class="modelos__row">
        <div class="brow"><div class="brow__bar"><i></i><i></i><i></i></div><img src="{{ asset('site/redes/modelo-vitrine.jpg') }}" alt="Modelo de loja online com vitrine azul" width="600" height="520" loading="lazy"></div>
        <div class="brow"><div class="brow__bar"><i></i><i></i><i></i></div><img src="{{ asset('site/redes/modelo-circuito.jpg') }}" alt="Modelo de loja online de ofertas, em amarelo" width="600" height="520" loading="lazy"></div>
        <div class="brow"><div class="brow__bar"><i></i><i></i><i></i></div><img src="{{ asset('site/redes/modelo-mimo.jpg') }}" alt="Modelo de loja online para presentes, em lilás" width="600" height="520" loading="lazy"></div>
      </div>
    </div>
  </div>
</section>

<section class="sec sec--gray" id="preco">
  <div class="wrap price">
    <div class="price__box">
      <span class="from">A partir de</span>
      <div class="val num">R$ 590 <small>por mês</small></div>
      <ul>
        <li>{!! $check !!} Valor fechado pelo número de lojas</li>
        <li>{!! $check !!} Venda, estoque, caixa, financeiro e nota fiscal</li>
        <li>{!! $check !!} Implantação e treinamento pela nossa equipe</li>
        <li>{!! $check !!} Venda na maquininha Stone e loja online</li>
        <li>{!! $check !!} Atendente de IA no WhatsApp como opcional</li>
      </ul>
      <a class="pill pill--block" href="#demo">Pedir proposta para a minha rede</a>
    </div>
    <div>
      <span class="kicker">Trocar de sistema sem parar a loja</span>
      <h2 class="d2" style="margin-bottom:1.4rem">A troca é nossa, não sua.</h2>
      <ol class="steps">
        <li><b>Mostramos com as suas lojas</b><span>Em 30 minutos você vê o sistema no cenário da sua rede e recebe a proposta.</span></li>
        <li><b>Trazemos os produtos e clientes</b><span>Importamos o cadastro do sistema atual e deixamos lojas, caixas e nota fiscal configurados.</span></li>
        <li><b>Treinamos a equipe de cada loja</b><span>Caixa e vendedor aprendem no próprio balcão. A virada é feita com a gente junto.</span></li>
      </ol>
      <p style="margin-top:1.4rem;color:var(--ink2)">Janeiro é o melhor mês para trocar, depois do movimento de dezembro. Fazemos poucas implantações por mês para acompanhar cada rede de perto.</p>
    </div>
  </div>
</section>

<section class="sec" id="demo">
  <div class="wrap cta__grid">
    <div>
      <span class="kicker">Demonstração gratuita</span>
      <h2 class="d2">Veja o sistema com o número de lojas que você tem.</h2>
      <p class="lede" style="margin-top:.8rem">Sem compromisso e sem instalar nada. Você sai da conversa sabendo quanto custa e em quanto tempo a rede estaria rodando.</p>
      <ul class="cta__list">
        <li>{!! $check !!} 30 minutos, por vídeo ou na sua loja em Teresina</li>
        <li>{!! $check !!} Proposta com valor fechado</li>
        <li>{!! $check !!} Vaga de implantação reservada para janeiro</li>
      </ul>
    </div>

    <form class="form" id="demoForm" action="{{ route('site.demo.store') }}" method="POST" novalidate>
      @csrf
      <input type="hidden" name="origem" value="redes">
      <input type="text" name="site" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
      <h3>Agendar demonstração</h3>
      <p>Preencha e a gente chama você para marcar o horário.</p>
      <div class="field">
        <label for="nome">Seu nome</label>
        <input id="nome" name="nome" type="text" autocomplete="name" placeholder="Como podemos te chamar" required>
      </div>
      <div class="field">
        <label for="empresa">Nome da rede</label>
        <input id="empresa" name="empresa" type="text" autocomplete="organization" placeholder="Nome das suas lojas" required>
      </div>
      <div class="field field--row">
        <div>
          <label for="email">E-mail</label>
          <input id="email" name="email" type="email" autocomplete="email" placeholder="voce@empresa.com" required>
        </div>
        <div>
          <label for="whats">WhatsApp</label>
          <input id="whats" name="whatsapp" type="tel" inputmode="tel" autocomplete="tel" placeholder="(86) 90000-0000" required>
        </div>
      </div>
      <div class="field">
        <label for="lojas">Quantas lojas?</label>
        <select id="lojas" name="lojas">
          <option value="2 a 3 lojas">2 a 3 lojas</option>
          <option value="4 a 10 lojas">4 a 10 lojas</option>
          <option value="mais de 10 lojas">Mais de 10 lojas</option>
          <option value="1 loja">1 loja, abrindo a segunda</option>
        </select>
      </div>
      <button class="pill pill--block" type="submit">Quero agendar a demonstração</button>
      <p class="form__fine">Seus dados são usados só para este contato.</p>
      <p id="formMsg" class="form__feedback" hidden role="status"></p>
      <p class="form__alt">Prefere agora? <a href="{{ $wa }}" target="_blank" rel="noopener">Chamar no WhatsApp</a></p>
    </form>
  </div>
</section>

</main>

<footer class="foot">
  <div class="wrap">
    <span>© {{ date('Y') }} IA365 · ERP Comercial</span>
    <span><a href="{{ route('site.home') }}">Conhecer o sistema completo</a> · contato@ia365.com.br</span>
  </div>
</footer>

<script>
(function () {
  var reduz = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var fmt = function (n) { return Math.round(n).toLocaleString('pt-BR'); };

  /* 1. entrada das partes ao rolar */
  var alvos = document.querySelectorAll('.sec__head, .row, .dev, .uno__stock, .modelos, .cmp__scroll, .price__box, .steps li, .form, .cta__grid > div, .ia > div, .chat:not(.chat--mini), .proof > div, .board');
  alvos.forEach(function (el, i) {
    el.classList.add('rv');
    var irmaos = el.parentNode ? Array.prototype.indexOf.call(el.parentNode.children, el) : 0;
    el.style.setProperty('--d', Math.min(irmaos, 4) * 0.08 + 's');
  });
  function mostrar(el) { el.classList.add('in'); }
  if (reduz || !('IntersectionObserver' in window)) {
    alvos.forEach(mostrar);
  } else {
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { mostrar(e.target); io.unobserve(e.target); } });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    alvos.forEach(function (el) { io.observe(el); });
    setTimeout(function () { alvos.forEach(function (el) { if (el.getBoundingClientRect().top < window.innerHeight) mostrar(el); }); }, 1200);
  }

  /* 2. números que contam */
  function contar(el) {
    var fim = parseFloat(el.getAttribute('data-count')); if (reduz || !fim) return;
    var t0 = null;
    function passo(t) { if (!t0) t0 = t; var k = Math.min((t - t0) / 1100, 1); k = 1 - Math.pow(1 - k, 3); el.textContent = fmt(fim * k); if (k < 1) requestAnimationFrame(passo); }
    el.textContent = '0'; requestAnimationFrame(passo);
  }
  if ('IntersectionObserver' in window) {
    var ioN = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { contar(e.target); ioN.unobserve(e.target); } }); }, { threshold: 0.6 });
    document.querySelectorAll('[data-count]').forEach(function (el) { ioN.observe(el); });
  }

  /* 3. painel do topo: uma venda nova de vez em quando */
  var linhas = document.querySelectorAll('.lojas tbody tr');
  var kTot = document.getElementById('kTot'), kVen = document.getElementById('kVen'), kNf1 = document.getElementById('kNf1'), kNf2 = document.getElementById('kNf2');
  var total = 31480, vendas = 262;
  var num = function (txt) { return parseInt(String(txt).replace(/\D/g, ''), 10) || 0; };
  if (!reduz && linhas.length) {
    setInterval(function () {
      if (document.hidden) return;
      var tr = linhas[Math.floor(Math.random() * linhas.length)];
      var valor = [59, 89, 119, 149, 179, 229][Math.floor(Math.random() * 6)];
      var cVal = tr.children[1], cQtd = tr.children[2];
      cVal.textContent = 'R$ ' + fmt(num(cVal.textContent) + valor);
      cQtd.textContent = num(cQtd.textContent) + 1;
      total += valor; vendas += 1;
      if (kTot) kTot.textContent = fmt(total);
      if (kVen) kVen.textContent = vendas;
      if (kNf1) kNf1.textContent = vendas;
      if (kNf2) kNf2.textContent = vendas;
      tr.classList.add('pulse'); setTimeout(function () { tr.classList.remove('pulse'); }, 900);
    }, 2600);
  }

  /* 4. um estoque só: maquininha, loja online e WhatsApp baixando o mesmo número */
  var uno = document.getElementById('uno');
  if (uno) {
    var n = document.getElementById('unoN'), msg = document.getElementById('unoMsg');
    var devs = { pos: uno.querySelector('[data-dev="pos"]'), loja: uno.querySelector('[data-dev="loja"]'), wa: uno.querySelector('[data-dev="wa"]') };
    var timers = [], rodando = false;
    function estado(q, texto) {
      uno.setAttribute('data-n', q); n.textContent = q; msg.textContent = texto;
      n.classList.add('bump'); setTimeout(function () { n.classList.remove('bump'); }, 350);
    }
    function ciclo() {
      Object.keys(devs).forEach(function (k) { devs[k].classList.remove('is-on'); });
      estado(3, '2 pares no Centro, 1 no Shopping');
      timers.push(setTimeout(function () { devs.pos.classList.add('is-on'); estado(2, 'Vendeu 1 na maquininha, no Centro'); }, 1500));
      timers.push(setTimeout(function () { devs.loja.classList.add('is-on'); estado(1, 'Vendeu 1 na loja online'); }, 4000));
      timers.push(setTimeout(function () { devs.wa.classList.add('is-on'); }, 6200));
      timers.push(setTimeout(function () { estado(0, 'Vendeu o último no WhatsApp. Reposição pedida.'); }, 8200));
      timers.push(setTimeout(ciclo, 12500));
    }
    function parar() { timers.forEach(clearTimeout); timers = []; rodando = false; }
    if (reduz || !('IntersectionObserver' in window)) {
      uno.setAttribute('data-n', 0);
    } else {
      new IntersectionObserver(function (es) {
        es.forEach(function (e) {
          if (e.isIntersecting && !rodando) { rodando = true; ciclo(); }
          else if (!e.isIntersecting && rodando) { parar(); }
        });
      }, { threshold: 0.15 }).observe(uno);
    }
  }

  /* 5. coisas presas à rolagem: barra do topo, painel que deita, loja que rola dentro do celular */
  var prog = document.getElementById('prog'), board = document.querySelector('.board');
  var phone = document.querySelector('.phone'), img = document.getElementById('lojaImg'), modelos = document.querySelector('.modelos__row');
  var pedido = false;
  function aoRolar() {
    pedido = false;
    var h = document.documentElement, y = window.pageYOffset, vh = window.innerHeight;
    if (prog) prog.style.transform = 'scaleX(' + (y / Math.max(h.scrollHeight - vh, 1)) + ')';
    if (reduz) return;
    if (board) { var r = board.getBoundingClientRect(); var k = Math.max(0, Math.min(1, (r.top - vh * 0.25) / (vh * 0.6))); board.style.setProperty('--tilt', (k * 9).toFixed(2) + 'deg'); }
    if (phone && img) { var p = phone.getBoundingClientRect(); var q = Math.max(0, Math.min(1, (vh - p.top) / (vh + p.height))); img.style.setProperty('--p', q.toFixed(3)); }
    if (modelos) { var m = modelos.getBoundingClientRect(); var z = Math.max(0, Math.min(1, (vh - m.top) / (vh + m.height))); modelos.style.setProperty('--m', z.toFixed(3)); }
  }
  window.addEventListener('scroll', function () { if (!pedido) { pedido = true; requestAnimationFrame(aoRolar); } }, { passive: true });
  window.addEventListener('resize', aoRolar);
  aoRolar();
})();
</script>
<script>
(function () {
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
        if (btn) btn.textContent = 'Pedido enviado';
      })
      .catch(function () {
        showMsg('Não foi possível enviar agora. Chame no WhatsApp, no link abaixo.', false);
        if (btn) { btn.disabled = false; btn.textContent = btnText; }
      });
  });
})();
</script>
</body>
</html>
