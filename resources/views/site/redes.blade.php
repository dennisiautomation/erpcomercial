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
.hero{padding:clamp(3rem,6vw,4.6rem) 0 0;text-align:center}
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



/* ============ topo: palco 3D com a maquininha ============ */
.h3{background:#07130e;color:#f2f7f4;overflow:hidden;position:relative}
.h3__in{display:grid;grid-template-columns:minmax(0,10fr) minmax(0,11fr);gap:2rem;align-items:center;padding-block:clamp(2.4rem,5vw,4.2rem);max-width:1180px}
.h3__kicker{display:inline-block;font-size:.9rem;font-weight:600;color:#5fe3a4;margin-bottom:1rem}
.h3 h1{font-size:clamp(2.1rem,3.6vw,3rem);font-weight:700;line-height:1.06;letter-spacing:-.025em}
.h3__lede{font-size:clamp(1.02rem,1.5vw,1.2rem);color:#b4c6bc;line-height:1.5;margin-top:1.1rem;max-width:52ch}
.h3__actions{display:flex;gap:.8rem;flex-wrap:wrap;margin-top:1.6rem}
.pill--onDark{background:transparent;color:#f2f7f4;box-shadow:inset 0 0 0 1px rgba(242,247,244,.45)}
.pill--onDark:hover{background:#f2f7f4;color:#07130e}
.h3__canais{list-style:none;display:flex;gap:1.6rem;flex-wrap:wrap;margin-top:2rem;padding-top:1.4rem;border-top:1px solid rgba(242,247,244,.14);font-size:.86rem;color:#93a89d}
.h3__canais b{display:block;color:#f2f7f4;font-size:.98rem;font-weight:600}

.h3__stage{position:relative;height:540px;perspective:1300px;perspective-origin:50% 42%}
.h3__glow{position:absolute;left:66%;top:54%;width:560px;height:560px;transform:translate(-50%,-50%);background:radial-gradient(closest-side,rgba(0,168,104,.42),rgba(0,168,104,.12) 55%,transparent 72%);pointer-events:none}

/* a maquininha */
.m3d{position:absolute;left:68%;top:57%;width:196px;height:404px;margin:-212px 0 0 -98px;transform-style:preserve-3d;z-index:3}
.m3d__tilt,.m3d__spin,.m3d__body{position:absolute;inset:0;transform-style:preserve-3d}
.m3d__tilt{transform:rotateX(var(--rx,4deg)) rotateY(var(--ry,0deg));transition:transform .5s var(--ease)}
.m3d__spin{animation:m3dEntra 2.2s cubic-bezier(.2,.8,.2,1) both,m3dBalanca 9s ease-in-out 2.2s infinite alternate}
@keyframes m3dEntra{from{transform:rotateY(-400deg) translateY(30px)}to{transform:rotateY(-26deg)}}
@keyframes m3dBalanca{from{transform:rotateY(-26deg)}to{transform:rotateY(26deg)}}
.m3d__camada,.m3d__face{position:absolute;inset:0;border-radius:30px}
.m3d__camada{background:#078a55}
.m3d__camada:nth-child(odd){background:#06794a}
.m3d__front{transform:translateZ(17px);background:linear-gradient(155deg,#12c07c 0%,#00a868 38%,#048a56 100%);box-shadow:inset 0 1px 0 rgba(255,255,255,.45),inset 0 -2px 0 rgba(0,0,0,.2);backface-visibility:hidden;display:flex;flex-direction:column;align-items:center;padding:0 12px 12px}
.m3d__back{transform:rotateY(180deg) translateZ(17px);background:linear-gradient(200deg,#0fb574,#04804f);backface-visibility:hidden;display:flex;align-items:center;justify-content:center}
.m3d__cam{position:absolute;top:26px;left:50%;width:34px;height:34px;margin-left:-17px;border-radius:50%;background:radial-gradient(circle at 40% 35%,#3a4a43,#0a0f0d 60%);box-shadow:0 0 0 4px rgba(0,0,0,.25)}
.m3d__printer{position:relative;width:100%;height:58px;flex:none}
.m3d__printer::before{content:"";position:absolute;left:6px;right:6px;top:8px;height:40px;border-radius:14px 14px 8px 8px;background:linear-gradient(rgba(255,255,255,.16),rgba(255,255,255,0) 60%);box-shadow:inset 0 0 0 1px rgba(0,0,0,.12)}
.m3d__front::before{content:"";position:absolute;right:-3px;top:120px;width:4px;height:44px;border-radius:2px;background:#046a41;box-shadow:0 60px 0 #046a41}
.m3d__slot{position:absolute;left:14px;right:14px;top:30px;height:9px;border-radius:5px;background:linear-gradient(#03130c,#0a2a1c);box-shadow:inset 0 2px 3px rgba(0,0,0,.7),0 1px 0 rgba(255,255,255,.25)}
.m3d__paper{position:absolute;left:24px;right:24px;bottom:24px;height:0;overflow:hidden;background:linear-gradient(#fff,#f1f1ee);border-radius:3px 3px 0 0;display:flex;flex-direction:column;justify-content:flex-start;gap:4px;padding:0 9px;transition:height .9s cubic-bezier(.3,.7,.2,1),padding .9s cubic-bezier(.3,.7,.2,1);box-shadow:0 -6px 14px rgba(0,0,0,.18)}
.m3d__paper i{display:block;height:3px;border-radius:2px;background:#cfd3d0}
.m3d__paper i:nth-child(2){width:72%}.m3d__paper i:nth-child(3){width:46%}
.m3d__paper b{font-size:.56rem;color:#1c7a4d;font-weight:700}
.m3d__glass{width:100%;flex:1;border-radius:20px;background:linear-gradient(165deg,#1b211e,#070a09 40%);padding:9px 8px 9px;box-shadow:inset 0 0 0 1px rgba(255,255,255,.07),0 1px 0 rgba(255,255,255,.28);position:relative;overflow:hidden}
.m3d__glass::after{content:"";position:absolute;inset:0;background:linear-gradient(115deg,rgba(255,255,255,.14) 0%,rgba(255,255,255,0) 32%);pointer-events:none}
.m3d__screen{position:relative;height:100%;border-radius:13px;background:#f6f8f7;color:#10201a;overflow:hidden}
.m3d__marca{font-size:1.02rem;font-weight:800;letter-spacing:-.045em;color:#fff;margin-top:8px;line-height:1}
.m3d__marca--g{font-size:1.9rem;margin:0;opacity:.92}
.m3d__sombra{position:absolute;left:50%;bottom:-38px;width:230px;height:36px;margin-left:-115px;border-radius:50%;background:radial-gradient(closest-side,rgba(0,0,0,.55),transparent);transform:rotateX(78deg) translateZ(-40px);filter:blur(5px)}

/* telas da maquininha */
.ms{position:absolute;inset:0;padding:10px 10px 11px;display:flex;flex-direction:column;gap:6px;opacity:0;transform:translateY(8px);transition:opacity .45s var(--ease),transform .45s var(--ease);text-align:left}
.ms em{font-style:normal}
.ms__bar{font-size:.6rem;font-weight:600;color:#5b6d65;border-bottom:1px solid #dfe6e2;padding-bottom:6px}
.ms__item{display:flex;justify-content:space-between;gap:.4rem;font-size:.66rem}
.ms__item--soft{color:#74857d}
.ms__total{display:flex;justify-content:space-between;align-items:baseline;margin-top:auto;font-size:.64rem;color:#5b6d65}
.ms__total b{font-size:1.1rem;color:#10201a}
.ms__btn{background:#00a868;color:#fff;border-radius:9px;text-align:center;font-size:.72rem;font-weight:700;padding:.5rem}
.ms--aproxime,.ms--ok{align-items:center;justify-content:center;text-align:center;gap:8px;font-size:.66rem;color:#5b6d65}
.ms--aproxime b{font-size:1.25rem;color:#10201a}
.ms__waves{display:flex;gap:4px;align-items:center;height:44px}
.ms__waves i{display:block;width:10px;border:3px solid #00a868;border-color:transparent #00a868 transparent transparent;border-radius:50%;animation:msOnda 1.2s ease-in-out infinite}
.ms__waves i:nth-child(1){height:16px}.ms__waves i:nth-child(2){height:28px;animation-delay:.15s}.ms__waves i:nth-child(3){height:40px;animation-delay:.3s}
@keyframes msOnda{0%,100%{opacity:.25}50%{opacity:1}}
.ms--ok{background:#f6f8f7;color:#5b6d65}
.ms--ok b{font-size:1.15rem;color:#10201a}
.ms__check{width:50px;height:50px;border-radius:50%;background:#00a868;color:#fff;box-shadow:0 0 0 7px rgba(0,168,104,.16);display:flex;align-items:center;justify-content:center}
.ms__check svg{width:26px;height:26px}
[data-fase="0"] .ms--cobrar,[data-fase="1"] .ms--aproxime,[data-fase="2"] .ms--ok,[data-fase="3"] .ms--ok,[data-fase="4"] .ms--ok{opacity:1;transform:none}
[data-fase="2"] .m3d__paper,[data-fase="3"] .m3d__paper,[data-fase="4"] .m3d__paper{height:62px;padding:8px 9px}



/* mundo 3D: tudo dentro gira junto, cada peça numa profundidade */
.h3__world{position:absolute;inset:0;transform-style:preserve-3d;will-change:transform}
.h3__floor{position:absolute;left:68%;top:57%;width:560px;height:560px;margin:-280px 0 0 -280px;transform:translateY(232px) rotateX(84deg);border-radius:50%;background:radial-gradient(closest-side,rgba(0,168,104,.34),rgba(0,168,104,.08) 55%,transparent 72%);pointer-events:none}
.h3__floor i{position:absolute;inset:34%;border-radius:50%;border:3px solid rgba(95,227,164,.75);opacity:0}
.h3__stage.pulso .h3__floor i{animation:h3Anel 1.5s cubic-bezier(.2,.7,.2,1)}
.h3__stage.pulso .h3__floor i:nth-child(2){animation-delay:.22s}
@keyframes h3Anel{0%{opacity:.9;transform:scale(.5)}100%{opacity:0;transform:scale(2.5)}}
.h3__tok{position:absolute;left:0;top:0;width:14px;height:14px;margin:-7px;border-radius:50%;background:#7df3bd;box-shadow:0 0 0 5px rgba(95,227,164,.25),0 0 24px 8px rgba(0,168,104,.75);z-index:9;pointer-events:none}
.m3d__body{animation:m3dBoia 4.6s ease-in-out infinite alternate}
@keyframes m3dBoia{from{transform:translateY(-7px)}to{transform:translateY(7px)}}
.m3d__sombra{animation:m3dSombra 4.6s ease-in-out infinite alternate}
@keyframes m3dSombra{from{opacity:.75;width:210px;margin-left:-105px}to{opacity:1;width:240px;margin-left:-120px}}
.m3d__glass::after{background-size:260% 100%;animation:m3dBrilho 9s ease-in-out 2.2s infinite alternate}
@keyframes m3dBrilho{from{background-position:0% 0}to{background-position:100% 0}}
.m3d__paper.voa{animation:m3dVoa .75s cubic-bezier(.3,0,.6,1) forwards}
@keyframes m3dVoa{from{height:62px;padding:8px 9px;transform:none;opacity:1}to{height:62px;padding:8px 9px;transform:translate(46px,-96px) rotate(18deg);opacity:0}}
[data-fase="0"] .m3d__paper,[data-fase="1"] .m3d__paper{transition:none}
.m3d--parada .m3d__body,.m3d--parada .m3d__sombra,.m3d--parada .m3d__glass::after{animation:none}

/* janela do sistema, atrás da maquininha */
.sw{position:absolute;left:0;top:0;width:410px;height:286px;z-index:1;display:grid;grid-template-columns:104px 1fr;border-radius:16px;overflow:hidden;background:#f1f5f9;color:#0f172a;box-shadow:0 30px 70px rgba(0,0,0,.55),0 0 0 1px rgba(255,255,255,.08);transform:translate3d(0,0,-170px) rotateY(15deg) rotateX(3deg) scale(1.14);transform-origin:0 50%;text-align:left}
.sw__side{background:#1e293b;padding:11px 9px;display:flex;flex-direction:column;gap:3px}
.sw__logo{display:flex;align-items:center;gap:5px;font-size:.6rem;font-weight:700;color:#fff;margin-bottom:8px;white-space:nowrap}
.sw__logo i{font-style:normal;width:17px;height:17px;border-radius:5px;background:#4f46e5;display:inline-flex;align-items:center;justify-content:center;font-size:.5rem}
.sw__m{font-size:.62rem;color:#cbd5e1;padding:5px 7px;border-radius:6px}
.sw__m.is-on{background:#4f46e5;color:#fff;font-weight:600}
.sw__main{padding:11px 12px;display:flex;flex-direction:column;gap:9px;min-width:0}
.sw__top{display:flex;align-items:center;justify-content:space-between;gap:.5rem}
.sw__top b{font-size:.78rem}
.sw__chip{background:#059669;color:#fff;font-weight:700;font-size:.6rem;border-radius:980px;padding:.14rem .5rem;white-space:nowrap;opacity:0;transform:translateY(5px) scale(.94);transition:opacity .45s var(--ease),transform .45s var(--ease)}
.sw__kpis{display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:6px}
.sw__kpis > span{background:#fff;border:1px solid #e2e8f0;border-radius:9px;padding:6px 8px;display:flex;flex-direction:column;min-width:0}
.sw__kpis em{font-style:normal;font-size:.54rem;color:#64748b}
.sw__kpis b{font-size:.8rem;font-weight:800;white-space:nowrap}
.sw__kpis b span{white-space:nowrap}
.sw__ok{color:#047857}
.sw__rows{background:#fff;border:1px solid #e2e8f0;border-radius:9px;padding:3px 8px;display:flex;flex-direction:column}
.sw__r{display:grid;grid-template-columns:82px 1fr 44px;align-items:center;gap:8px;font-size:.62rem;padding:5px 2px;border-bottom:1px solid #f1f5f9;border-radius:5px;transition:background .5s var(--ease)}
.sw__r:last-child{border-bottom:0}
.sw__r em{font-style:normal;color:#475569;white-space:nowrap}
.sw__r i{display:block;height:6px;border-radius:3px;background:#eef2ff;position:relative;overflow:hidden}
.sw__r i::after{content:"";position:absolute;inset:0;width:var(--w);background:#4f46e5;border-radius:3px;transition:width .6s var(--ease)}
.sw__r b{text-align:right;font-weight:700}
.sw__r.pulse{background:#ecfdf5}
[data-fase="2"] .sw__chip,[data-fase="3"] .sw__chip,[data-fase="4"] .sw__chip{opacity:1;transform:none}

/* cartões que flutuam em volta */
.fc{position:absolute;z-index:2;background:#12201a;border:1px solid rgba(242,247,244,.14);border-radius:18px;padding:.85rem .95rem;width:212px;display:flex;flex-direction:column;gap:.3rem;font-size:.8rem;color:#b4c6bc;box-shadow:0 18px 40px rgba(0,0,0,.4);transition:border-color .5s var(--ease),box-shadow .5s var(--ease)}
.fc__t{font-size:.72rem;font-weight:600;color:#93a89d;display:flex;align-items:center;gap:.35rem}
.fc b{color:#f2f7f4;font-size:1rem}
.fc__big{font-size:1.55rem !important;line-height:1.1}
.fc__s{font-size:.76rem}
.fc--loja{right:-40px;bottom:44px;width:160px;transform:translateZ(90px) rotateY(-12deg)}
.fc--wa{left:10px;bottom:16px;width:204px;transform:translateZ(130px) rotateY(10deg)}
.fc--loja img{width:100%;height:104px;object-fit:cover;border-radius:11px;display:block;margin-bottom:.25rem;background:#e9e6e0}
.fc__bars{display:grid;gap:5px;margin-top:.35rem}
.fc__bars i{display:block;height:6px;border-radius:3px;background:rgba(242,247,244,.12);position:relative;overflow:hidden}
.fc__bars i::after{content:"";position:absolute;inset:0;width:var(--w);background:#00a868;border-radius:3px}
.fc__chip{align-self:flex-start;margin-top:.45rem;background:#00a868;color:#04140d;font-weight:700;font-size:.74rem;border-radius:980px;padding:.18rem .6rem;opacity:0;transform:translateY(6px) scale(.94);transition:opacity .45s var(--ease),transform .45s var(--ease)}
.fc__dot{width:8px;height:8px;border-radius:50%;background:#25d366;display:inline-block}
.fc__in,.fc__out{border-radius:11px;padding:.4rem .6rem;font-size:.78rem;line-height:1.35;max-width:92%}
.fc__in{background:rgba(242,247,244,.1);color:#e3ece7;align-self:flex-start;border-top-left-radius:3px}
.fc__out{background:#0a6b4c;color:#eafff5;align-self:flex-end;border-top-right-radius:3px;opacity:0;transform:translateY(8px);transition:opacity .45s var(--ease),transform .45s var(--ease)}
.fc--loja b,.fc--loja .fc__s{opacity:.35;transition:opacity .45s var(--ease)}
[data-fase="3"] .fc--loja,[data-fase="4"] .fc--wa{border-color:#00a868;box-shadow:0 18px 40px rgba(0,0,0,.4),0 0 0 4px rgba(0,168,104,.22)}
[data-fase="3"] .fc--loja b,[data-fase="3"] .fc--loja .fc__s,[data-fase="4"] .fc--loja b,[data-fase="4"] .fc--loja .fc__s{opacity:1}
[data-fase="4"] .fc__out{opacity:1;transform:none}

@media(max-width:940px){
  .h3__in{grid-template-columns:1fr;gap:1rem;padding-bottom:1.2rem}
  .h3__stage{height:500px;max-width:420px;width:100%;margin-inline:auto}
  .m3d{transform:scale(.8)}
  .fc{width:168px;padding:.65rem .7rem;font-size:.72rem}
  .fc--loja{width:140px;top:auto;bottom:0;right:0}
  .fc--loja img{height:70px}
  .sw{left:0;top:0;width:300px;height:206px;grid-template-columns:74px 1fr;transform:translate3d(0,0,-120px) rotateY(10deg) scale(1.1)}
  .sw__side{padding:8px 6px}.sw__m{font-size:.5rem;padding:3px 5px}.sw__logo{font-size:.48rem}.sw__logo i{width:13px;height:13px}
  .sw__main{padding:8px;gap:6px}.sw__top b{font-size:.62rem}.sw__chip{font-size:.5rem}
  .sw__kpis{gap:4px}.sw__kpis > span{padding:4px 5px}.sw__kpis em{font-size:.44rem}.sw__kpis b{font-size:.62rem}
  .sw__r{grid-template-columns:58px 1fr 32px;font-size:.5rem;padding:3px 1px;gap:5px}
  .m3d{left:64%;top:60%}
  .sw__kpis b{font-size:.58rem}
  .fc--wa{width:170px;left:0;bottom:0}
  .fc__big{font-size:1.2rem !important}
  .h3__canais{display:none}
}

/* a mesma maquininha, parada, na seção dos canais */
.m3d--parada{position:relative;left:auto;top:auto;margin:34px 0 0;perspective:1100px}
.m3d--parada .m3d__spin{animation:none;transform:rotateY(-14deg)}
.m3d--parada .m3d__tilt{transform:rotateX(5deg)}
.m3d--parada .m3d__sombra{bottom:-30px}
.dev .ms--cobrar{opacity:1;transform:none}
.dev.is-on .ms--cobrar{opacity:0}
.dev.is-on .ms--ok{opacity:1;transform:none}
.dev.is-on .m3d__paper{height:62px;padding:8px 9px}

.hero h2{margin-inline:auto;max-width:20ch}

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
  .m3d__spin{animation:none;transform:rotateY(-20deg)}
  .m3d__body,.m3d__sombra,.m3d__glass::after,.h3__stage.pulso .h3__floor i{animation:none}
  .m3d__tilt,.fc,.sw,.ms,.m3d__paper,.sw__chip,.fc__out{transition:none}
  .ms__waves i{animation:none}
}


/* ---------- vídeo ---------- */
.vid{padding-bottom:0}
.vid .sec__head{text-align:center;margin-bottom:1.6rem}
.vid__box{position:relative;max-width:960px;margin-inline:auto;border-radius:22px;overflow:hidden;background:#07130e;box-shadow:0 2px 6px rgba(0,0,0,.06),0 24px 70px rgba(0,0,0,.18);aspect-ratio:16/9}
.vid__box video{display:block;width:100%;height:100%;object-fit:cover;background:#07130e}
.vid__play{white-space:nowrap;position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);display:inline-flex;align-items:center;gap:.7rem;border:0;cursor:pointer;font:inherit;font-weight:600;font-size:1.05rem;color:#07130e;background:#fff;border-radius:980px;padding:.85rem 1.5rem .85rem 1.2rem;min-height:52px;box-shadow:0 12px 34px rgba(0,0,0,.4);transition:transform .2s var(--ease)}
.vid__play:hover{transform:translate(-50%,-50%) scale(1.05)}
.vid__tri{width:0;height:0;border-style:solid;border-width:9px 0 9px 15px;border-color:transparent transparent transparent #00a868}
.vid__nota{text-align:center;font-size:.88rem;color:var(--ink3);margin-top:.9rem}

/* ---------- conversão: preço no topo, prova, chips, dúvidas, barra fixa ---------- */
.h3__preco{margin-top:1rem;font-size:.95rem;color:#93a89d}
.h3__preco b{color:#f2f7f4}
.pill--wa{background:#1fa855}
.pill--wa:hover{background:#1c9a4e}
.prova .proof{margin-top:0;border-top:0}
.chips{border:0;display:flex;flex-wrap:wrap;gap:.45rem;margin-bottom:1rem}
.chips legend{font-size:.84rem;font-weight:600;margin-bottom:.4rem;padding:0}
.chips label{cursor:pointer}
.chips input{position:absolute;opacity:0;width:1px;height:1px}
.chips span{display:inline-flex;align-items:center;min-height:42px;padding:.4rem .95rem;border:1px solid #d2d2d7;border-radius:980px;font-size:.95rem;transition:background .18s var(--ease),border-color .18s var(--ease),color .18s var(--ease)}
.chips input:checked + span{background:var(--ink);border-color:var(--ink);color:#fff}
.chips input:focus-visible + span{outline:2px solid var(--blue);outline-offset:3px}
.faq{max-width:820px}
.faq__lista{border-top:1px solid var(--sep)}
.faq details{border-bottom:1px solid var(--sep)}
.faq summary{cursor:pointer;list-style:none;padding:1.1rem 2rem 1.1rem 0;font-weight:600;font-size:1.08rem;position:relative}
.faq summary::-webkit-details-marker{display:none}
.faq summary::after{content:"+";position:absolute;right:.2rem;top:50%;transform:translateY(-50%);font-size:1.5rem;font-weight:400;color:var(--ink2);transition:transform .25s var(--ease)}
.faq details[open] summary::after{transform:translateY(-50%) rotate(45deg)}
.faq details p{color:var(--ink2);padding:0 2rem 1.2rem 0;max-width:62ch}
.fixo{position:fixed;left:0;right:0;bottom:0;z-index:65;display:none;gap:.6rem;padding:.6rem .8rem calc(.6rem + env(safe-area-inset-bottom,0px));background:rgba(255,255,255,.94);border-top:1px solid var(--sep);box-shadow:0 -8px 24px rgba(0,0,0,.08)}
.fixo .pill{flex:1;font-size:.95rem;padding:.6rem .5rem}
@media(max-width:760px){.fixo:not([hidden]){display:flex}.foot{padding-bottom:5.2rem}}

.proof b span,.kp b span{display:inline;font-size:inherit;color:inherit}
.foot{padding:1.6rem 0 2.2rem;border-top:1px solid var(--sep);font-size:.82rem;color:var(--ink3)}
.foot .wrap{display:flex;gap:.6rem 1.4rem;flex-wrap:wrap;justify-content:space-between}
[hidden]{display:none!important}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}.pill{transition:none}}
</style>
</head>
<body>
@php
  $wa = 'https://wa.me/5511917120940?text=' . rawurlencode('Tenho uma rede de lojas e quero conhecer o ERP Comercial');
  $zap = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.5 8.5 0 0 1-12.6 7.4L3 20l1.2-5A8.5 8.5 0 1 1 21 11.5Z"/></svg>';
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

<section class="h3" id="topo">
  <div class="wrap h3__in">
    <div class="h3__txt">
      <span class="h3__kicker">ERP Comercial para redes de lojas</span>
      <h1>A maquininha, a loja online e o WhatsApp vendendo no mesmo sistema.</h1>
      <p class="h3__lede">Para quem tem de 2 a 10 lojas. Cada venda baixa o mesmo estoque, cai no caixa da loja certa e sai com nota fiscal. A troca de sistema é a nossa equipe que faz.</p>
      <div class="h3__actions">
        <a class="pill pill--wa" href="{{ $wa }}" target="_blank" rel="noopener">{!! $zap !!} Chamar no WhatsApp</a>
        <a class="pill pill--onDark" href="#demo">Agendar demonstração</a>
      </div>
      <p class="h3__preco">A partir de <b>R$ 590 por mês</b> para a rede toda. Resposta no mesmo dia.</p>
      <ul class="h3__canais">
        <li><b>Maquininha Stone</b>venda no corredor da loja</li>
        <li><b>Loja online</b>com o estoque das lojas</li>
        <li><b>Atendente de IA</b>no WhatsApp, 24 horas</li>
      </ul>
    </div>

    <div class="h3__stage" id="h3stage" data-fase="0" role="img" aria-label="Maquininha Stone girando à frente da tela do sistema, com a loja online e o atendente de IA recebendo a mesma venda">
      <div class="h3__glow"></div>
      <div class="h3__world" id="h3world">
      <div class="h3__floor"><i></i><i></i></div>

      <div class="sw" id="h3sw">
        <div class="sw__side">
          <span class="sw__logo"><i>IA</i>ERP Comercial</span>
          <span class="sw__m is-on">Painel</span><span class="sw__m">PDV</span><span class="sw__m">Estoque</span><span class="sw__m">Caixas</span><span class="sw__m">Notas fiscais</span><span class="sw__m">Financeiro</span>
        </div>
        <div class="sw__main">
          <div class="sw__top"><b>Minha rede · hoje</b><span class="sw__chip" id="h3chip">+ R$ 149,90 · Centro</span></div>
          <div class="sw__kpis">
            <span><em>Vendido hoje</em><b class="num">R$ <span id="h3tot">31.480</span></b></span>
            <span><em>Vendas</em><b class="num" id="h3ven">262</b></span>
            <span><em>Notas emitidas</em><b class="num sw__ok" id="h3nf">262</b></span>
          </div>
          <div class="sw__rows">
            <span class="sw__r" data-o="Centro"><em>Loja Centro</em><i style="--w:92%"></i><b class="num">9.870</b></span>
            <span class="sw__r"><em>Loja Shopping</em><i style="--w:78%"></i><b class="num">8.240</b></span>
            <span class="sw__r"><em>Loja Zona Leste</em><i style="--w:64%"></i><b class="num">6.910</b></span>
            <span class="sw__r" data-o="Loja online"><em>Loja online</em><i style="--w:40%"></i><b class="num">4.130</b></span>
            <span class="sw__r" data-o="WhatsApp"><em>WhatsApp</em><i style="--w:24%"></i><b class="num">2.330</b></span>
          </div>
        </div>
      </div>

      <div class="fc fc--loja">
        <img src="{{ asset('site/redes/sandalia.jpg') }}" alt="" width="480" height="480">
        <span class="fc__t">Loja online</span>
        <b>Pedido novo</b>
        <span class="fc__s">Sandália preta 37 · PIX pago</span>
      </div>

      <div class="fc fc--wa">
        <span class="fc__t"><i class="fc__dot"></i>WhatsApp · atendente de IA</span>
        <span class="fc__in">Tem a sandália preta 37?</span>
        <span class="fc__out">Tem 2 no Centro. Separo para você?</span>
      </div>

      <div class="m3d" id="m3d">
        <div class="m3d__tilt" id="m3dTilt">
          <div class="m3d__spin">
            <div class="m3d__body" id="m3dBody">
              <div class="m3d__face m3d__front">
                <div class="m3d__printer"><span class="m3d__slot"></span><span class="m3d__paper"><i></i><i></i><i></i><b>NFC-e emitida</b></span></div>
                <div class="m3d__glass">
                  <div class="m3d__screen">
                    <div class="ms ms--cobrar">
                      <span class="ms__bar">Venda · Loja Centro</span>
                      <span class="ms__item"><em>Sandália preta 37</em><b class="num">149,90</b></span>
                      <span class="ms__item ms__item--soft"><em>Vendedora</em><b>Ana</b></span>
                      <span class="ms__total"><em>Total</em><b class="num">R$ 149,90</b></span>
                      <span class="ms__btn">Cobrar</span>
                    </div>
                    <div class="ms ms--aproxime">
                      <span class="ms__waves"><i></i><i></i><i></i></span>
                      <b class="num">R$ 149,90</b>
                      <span>Aproxime ou insira o cartão</span>
                    </div>
                    <div class="ms ms--ok">
                      <span class="ms__check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                      <b>Aprovado</b>
                      <span>Estoque, caixa e nota já saíram</span>
                    </div>
                  </div>
                </div>
                <span class="m3d__marca">stone</span>
              </div>
              <div class="m3d__face m3d__back"><span class="m3d__marca m3d__marca--g">stone</span><i class="m3d__cam"></i></div>
            </div>
          </div>
        </div>
        <div class="m3d__sombra"></div>
      </div>
      </div>
    </div>
  </div>
</section>

<section class="prova">
  {{-- Números reais de uso (banco de produção, 30 dias até 14/11/2026). Atualizar ou retirar antes de publicar. --}}
  <div class="proof">
    <div><b class="num"><span data-count="14">14</span> lojas</b><span>de duas redes de Teresina já operam no sistema</span></div>
    <div><b class="num"><span data-count="7900">7.900</span>+</b><span>vendas passaram por elas nos últimos 30 dias</span></div>
    <div><b class="num"><span data-count="54">54</span> pessoas</b><span>usam todo dia: dono, gerente, caixa e vendedor</span></div>
  </div>
</section>

<section class="sec vid" id="video">
  <div class="wrap">
    <div class="sec__head">
      <span class="kicker">Em 1 minuto e meio</span>
      <h2 class="d2">Veja uma venda acontecer nas suas lojas.</h2>
    </div>
    <div class="vid__box">
      <video id="vd" preload="none" playsinline controls poster="{{ asset('site/redes/video-capa.jpg') }}" width="1280" height="720">
        <source src="{{ asset('site/redes/erp-redes.mp4') }}" type="video/mp4">
      </video>
      <button class="vid__play" id="vdPlay" type="button" aria-label="Assistir ao vídeo de 1 minuto e 29 segundos">
        <span class="vid__tri" aria-hidden="true"></span><span>Assistir · 1 min 29 s</span>
      </button>
    </div>
    <p class="vid__nota">Com narração e legenda. Dá para assistir sem som.</p>
  </div>
</section>

<section class="hero">
  <div class="wrap">
    <h2 class="d2">Você abre o celular e vê todas as lojas.</h2>
    <p class="lede">Quanto cada uma vendeu, qual caixa fechou com diferença e onde o estoque está acabando. Sem ligar para o gerente.</p>
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
      <p class="lede" style="margin-top:.8rem">O atendente de IA já vem pronto no sistema: você só configura como ele deve atender. Ele conversa no WhatsApp da loja, consulta o estoque de verdade e monta o pedido. Quando precisa de gente, passa para o seu vendedor.</p>
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
          <div class="m3d m3d--parada" aria-hidden="true">
            <div class="m3d__tilt"><div class="m3d__spin"><div class="m3d__body">
              <div class="m3d__face m3d__front">
                <div class="m3d__printer"><span class="m3d__slot"></span><span class="m3d__paper"><i></i><i></i><i></i><b>NFC-e emitida</b></span></div>
                <div class="m3d__glass">
                  <div class="m3d__screen">
                    <div class="ms ms--cobrar">
                      <span class="ms__bar">Venda · Loja Centro</span>
                      <span class="ms__item"><em>Sandália preta 37</em><b class="num">149,90</b></span>
                      <span class="ms__item ms__item--soft"><em>Vendedora</em><b>Ana</b></span>
                      <span class="ms__total"><em>Total</em><b class="num">R$ 149,90</b></span>
                      <span class="ms__btn">Cobrar</span>
                    </div>
                    <div class="ms ms--aproxime">
                      <span class="ms__waves"><i></i><i></i><i></i></span>
                      <b class="num">R$ 149,90</b>
                      <span>Aproxime ou insira o cartão</span>
                    </div>
                    <div class="ms ms--ok">
                      <span class="ms__check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                      <b>Aprovado</b>
                      <span>Estoque, caixa e nota já saíram</span>
                    </div>
                  </div>
                </div>
                <span class="m3d__marca">stone</span>
              </div>
            </div></div></div>
            <div class="m3d__sombra"></div>
          </div>
          <figcaption><b>Maquininha Stone</b>Você não troca de máquina: é só instalar o nosso aplicativo pela loja de aplicativos da Stone, direto na maquininha. O vendedor fecha a venda ali, e estoque, caixa e nota saem na hora.</figcaption>
        </figure>

        <figure class="dev is-on" data-dev="loja">
          <div class="phone" aria-hidden="true">
            <div class="phone__in"><img id="lojaImg" src="{{ asset('site/redes/loja-celular.jpg') }}" alt="" width="390" height="1440" loading="lazy"></div>
            <div class="phone__toast"><b>Pedido novo da loja online</b><span>Sandália preta 37 · PIX pago</span></div>
          </div>
          <figcaption><b>Loja online</b>Já vem pronta no sistema: você só escolhe um modelo e configura. Ela usa os seus produtos, os seus preços e o estoque das lojas. O cliente paga por PIX ou cartão.</figcaption>
        </figure>

        <figure class="dev is-on" data-dev="wa">
          <div class="chat chat--mini" aria-hidden="true">
            <div class="chat__top"><i>L</i><span>Loja Centro<small>atendente de IA</small></span></div>
            <div class="msg msg--in">Ainda tem a sandália preta 37?</div>
            <div class="msg msg--out wa__r1">Tem o último par, no Shopping. Separo para você?</div>
            <div class="msg msg--in wa__r2">Separa, pago agora</div>
            <div class="msg msg--out wa__r3">Separado. Segue o link do PIX.</div>
          </div>
          <figcaption><b>WhatsApp com atendente de IA</b>Também já vem pronto: você só configura como ele deve atender. Ele responde olhando o estoque de todas as lojas e fecha o pedido, a qualquer hora.</figcaption>
        </figure>
      </div>
      <p class="uno__note">Telas de exemplo. A loja online mostrada é uma loja de demonstração do sistema.</p>
    </div>

    <div class="modelos">
      <div class="modelos__txt">
        <h3>Seis modelos de loja online prontos</h3>
        <p>A loja já está no sistema. Você escolhe o modelo, troca cores e fotos num editor visual e publica. Pode usar o seu próprio endereço na internet.</p>
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

<section class="sec sec--gray" id="duvidas">
  <div class="wrap faq">
    <div class="sec__head">
      <span class="kicker">Antes de decidir</span>
      <h2 class="d2">O que todo dono de rede pergunta.</h2>
    </div>
    <div class="faq__lista">
      <details open><summary>Vou perder meus produtos e clientes do sistema atual?</summary><p>Não. Nossa equipe importa o cadastro de produtos e de clientes do sistema que você usa hoje e confere com você antes da virada.</p></details>
      <details><summary>Preciso parar a loja para trocar?</summary><p>Não. Deixamos tudo configurado antes, treinamos caixa e vendedor no próprio balcão e fazemos a virada junto com você, loja por loja.</p></details>
      <details><summary>Funciona com a minha maquininha?</summary><p>Se a sua maquininha é Stone, você não troca nada: é só instalar o nosso aplicativo pela loja de aplicativos da Stone, na própria máquina, e ela já fica ligada ao sistema. Com outras máquinas você continua vendendo normalmente pelo caixa do sistema e informa a forma de pagamento.</p></details>
      <details><summary>E a nota fiscal? Não entendo de imposto.</summary><p>A nota sai sozinha quando a venda fecha, no CNPJ da loja certa. Você não precisa preencher nada de imposto na hora da venda, e o seu contador recebe tudo organizado.</p></details>
      <details><summary>Quanto custa para a minha rede?</summary><p>A partir de R$ 590 por mês. O valor é fechado pelo número de lojas e você recebe a proposta no mesmo dia da demonstração.</p></details>
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
      <fieldset class="chips">
        <legend>Quantas lojas?</legend>
        <label><input type="radio" name="lojas" value="2 a 3 lojas" checked><span>2 a 3</span></label>
        <label><input type="radio" name="lojas" value="4 a 10 lojas"><span>4 a 10</span></label>
        <label><input type="radio" name="lojas" value="mais de 10 lojas"><span>Mais de 10</span></label>
        <label><input type="radio" name="lojas" value="1 loja"><span>1, abrindo a 2ª</span></label>
      </fieldset>
      <button class="pill pill--block" type="submit">Quero agendar a demonstração</button>
      <p class="form__fine">Seus dados são usados só para este contato.</p>
      <p id="formMsg" class="form__feedback" hidden role="status"></p>
      <p class="form__alt">Prefere agora? <a href="{{ $wa }}" target="_blank" rel="noopener">Chamar no WhatsApp</a></p>
    </form>
  </div>
</section>

</main>

<div class="fixo" id="fixo" hidden>
  <a class="pill pill--wa" href="{{ $wa }}" target="_blank" rel="noopener">{!! $zap !!} WhatsApp</a>
  <a class="pill" href="#demo">Ver demonstração</a>
</div>

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


  /* 0. topo: maquininha em 3D, fases da venda e cartões em volta */
  var stage = document.getElementById('h3stage');
  document.querySelectorAll('.m3d__body').forEach(function (corpo) {
    for (var z = -16; z <= 16; z += 2) { var c = document.createElement('div'); c.className = 'm3d__camada'; c.style.transform = 'translateZ(' + z + 'px)'; corpo.insertBefore(c, corpo.firstChild); }
  });
  if (stage) {
    var hTot = document.getElementById('h3tot'), hChip = document.getElementById('h3chip'), tilt = document.getElementById('m3dTilt');
    var soma = 31480;
    var origens = ['Centro', 'Loja online', 'WhatsApp'];
    function fase(n) { stage.setAttribute('data-fase', n); }
    var hVen = document.getElementById('h3ven'), hNf = document.getElementById('h3nf'), qtd = 262;
    function entrou(i) {
      soma += 150; qtd += 1;
      if (hTot) hTot.textContent = fmt(soma); if (hVen) hVen.textContent = qtd; if (hNf) hNf.textContent = qtd;
      if (hChip) hChip.textContent = '+ R$ 149,90 · ' + origens[i];
      var linha = stage.querySelector('.sw__r[data-o="' + origens[i] + '"]');
      if (linha) { var v = linha.querySelector('b'); v.textContent = fmt(parseInt(v.textContent.replace(/\D/g, ''), 10) + 150); linha.classList.add('pulse'); setTimeout(function () { linha.classList.remove('pulse'); }, 1400); }
    }
    var world = document.getElementById('h3world'), papel = stage.querySelector('.m3d .m3d__paper'), tela = stage.querySelector('.m3d .m3d__screen');
    function voar(de, para, depois) {
      if (!de || !para || !stage.animate) { depois(); return; }
      var s0 = stage.getBoundingClientRect(), a = de.getBoundingClientRect(), b = para.getBoundingClientRect();
      var x0 = a.left + a.width / 2 - s0.left, y0 = a.top + a.height / 2 - s0.top, x1 = b.left + b.width * 0.3 - s0.left, y1 = b.top + b.height / 2 - s0.top;
      var mx = (x0 + x1) / 2, my = Math.min(y0, y1) - 80, t = document.createElement('i');
      t.className = 'h3__tok'; stage.appendChild(t);
      t.animate([
        { transform: 'translate(' + x0 + 'px,' + y0 + 'px) scale(.3)', opacity: 0 },
        { transform: 'translate(' + x0 + 'px,' + y0 + 'px) scale(1)', opacity: 1, offset: 0.14 },
        { transform: 'translate(' + mx + 'px,' + my + 'px) scale(1.3)', opacity: 1, offset: 0.56 },
        { transform: 'translate(' + x1 + 'px,' + y1 + 'px) scale(.5)', opacity: 0.3 }
      ], { duration: 850, easing: 'cubic-bezier(.4,0,.2,1)' }).onfinish = function () { t.remove(); depois(); };
    }
    function linhaDe(i) { return stage.querySelector('.sw__r[data-o="' + origens[i] + '"]'); }
    function pulsar() { stage.classList.remove('pulso'); void stage.offsetWidth; stage.classList.add('pulso'); }
    function rodar() {
      if (papel && stage.getAttribute('data-fase') === '4') { papel.classList.add('voa'); setTimeout(function () { fase(0); papel.classList.remove('voa'); }, 760); } else { fase(0); }
      setTimeout(function () { fase(1); }, 2200);
      setTimeout(function () { fase(2); pulsar(); voar(tela, linhaDe(0), function () { entrou(0); }); }, 4200);
      setTimeout(function () { fase(3); voar(stage.querySelector('.fc--loja'), linhaDe(1), function () { entrou(1); }); }, 6600);
      setTimeout(function () { fase(4); voar(stage.querySelector('.fc--wa'), linhaDe(2), function () { entrou(2); }); }, 9000);
      setTimeout(rodar, 12600);
    }
    if (reduz) { fase(4); } else { setTimeout(rodar, 2300); }

    if (!reduz && tilt && world) {
      var alvoX = 0, alvoY = 0, cx = 0, cy = 0, visivel = true;
      stage.addEventListener('pointermove', function (e) { var r = stage.getBoundingClientRect(); alvoX = (e.clientX - r.left) / r.width - 0.5; alvoY = (e.clientY - r.top) / r.height - 0.5; });
      stage.addEventListener('pointerleave', function () { alvoX = 0; alvoY = 0; });
      if ('IntersectionObserver' in window) new IntersectionObserver(function (es) { visivel = es[0].isIntersecting; }).observe(stage);
      (function quadro(agora) {
        if (visivel) {
          var t = agora / 1000; cx += (alvoX - cx) * 0.07; cy += (alvoY - cy) * 0.07;
          world.style.transform = 'rotateX(' + (Math.cos(t * 0.45) * 2.2 - cy * 9).toFixed(2) + 'deg) rotateY(' + (Math.sin(t * 0.5) * 7 + cx * 18).toFixed(2) + 'deg)';
          tilt.style.setProperty('--rx', (4 - cy * 10).toFixed(1) + 'deg');
        }
        requestAnimationFrame(quadro);
      })(0);
    }
  }

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
    var st = document.getElementById('h3stage'), mt = document.getElementById('m3dTilt');
    if (st && mt) { var sr = st.getBoundingClientRect(); if (sr.bottom > 0) { var fora = (sr.top + sr.height / 2 - vh / 2) / vh; mt.style.setProperty('--ry', (Math.max(-1, Math.min(1, fora)) * -70).toFixed(1) + 'deg'); } }
    if (board) { var r = board.getBoundingClientRect(); var k = Math.max(0, Math.min(1, (r.top - vh * 0.25) / (vh * 0.6))); board.style.setProperty('--tilt', (k * 9).toFixed(2) + 'deg'); }
    if (phone && img) { var p = phone.getBoundingClientRect(); var q = Math.max(0, Math.min(1, (vh - p.top) / (vh + p.height))); img.style.setProperty('--p', q.toFixed(3)); }
    if (modelos) { var m = modelos.getBoundingClientRect(); var z = Math.max(0, Math.min(1, (vh - m.top) / (vh + m.height))); modelos.style.setProperty('--m', z.toFixed(3)); }
  }
  var vd = document.getElementById('vd'), vdPlay = document.getElementById('vdPlay');
  if (vd && vdPlay) {
    vd.controls = false;
    vdPlay.addEventListener('click', function () { vd.controls = true; vdPlay.hidden = true; var pr = vd.play(); if (pr && pr.catch) pr.catch(function () { vdPlay.hidden = false; }); });
    vd.addEventListener('play', function () { vdPlay.hidden = true; });
  }
  var fixo = document.getElementById('fixo'), topoEl = document.getElementById('topo'), demoEl = document.getElementById('demo');
  function barra() { if (!fixo || !topoEl || !demoEl) return; var passou = topoEl.getBoundingClientRect().bottom < 80, noForm = demoEl.getBoundingClientRect().top < window.innerHeight * 0.7; fixo.hidden = !(passou && !noForm); }
  window.addEventListener('scroll', barra, { passive: true }); barra();
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
