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

/* chegando */
.soon{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
@media(max-width:720px){.soon{grid-template-columns:1fr}}
.soon__item{background:var(--bg2);border-radius:var(--r);padding:1.4rem}
.soon__item h3{font-size:1.15rem;font-weight:600;margin:0 0 .3rem}
.soon__item p{color:var(--ink2);font-size:.97rem}
.tag{display:inline-block;font-size:.74rem;font-weight:600;padding:.16rem .6rem;border-radius:980px;background:rgba(255,149,0,.15);color:var(--warnD)}

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
        <div class="kp"><span>Vendido hoje</span><b class="num">R$ 31.480</b></div>
        <div class="kp"><span>Vendas</span><b class="num">262</b></div>
        <div class="kp"><span>Notas emitidas</span><b class="num">262 de 262</b></div>
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
    <div><b class="num">14 lojas</b><span>de duas redes de Teresina já operam no sistema</span></div>
    <div><b class="num">7.900+</b><span>vendas passaram por elas nos últimos 30 dias</span></div>
    <div><b class="num">54 pessoas</b><span>usam todo dia: dono, gerente, caixa e vendedor</span></div>
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

<section class="sec">
  <div class="wrap">
    <div class="sec__head">
      <span class="kicker">Venda onde o cliente estiver</span>
      <h2 class="d2">No balcão, na maquininha e na internet. O estoque é um só.</h2>
    </div>
    <div class="soon">
      <div class="soon__item">
        <h3>Venda direto na maquininha Stone</h3>
        <p>O vendedor fecha a venda na própria maquininha, no corredor da loja ou na fila. A venda cai no sistema com estoque, caixa e nota, sem digitar de novo.</p>
      </div>
      <div class="soon__item">
        <h3>Loja online com o mesmo estoque</h3>
        <p>A vitrine na internet usa os produtos e o estoque que já estão no sistema. O cliente paga por PIX ou cartão e o pedido chega junto com os das lojas.</p>
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
