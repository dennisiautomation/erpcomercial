{{--
  MODELO EXCLUSIVO — CELL MASTER, vendas no atacado (27/11/2026). Só carrega quando a loja usa o modelo "cellmaster"
  (layout.blade.php → $modeloLoja). Tudo aqui é preso a [data-modelo=cellmaster]: não vaza para os 6 modelos públicos.

  THESIS: o balcão do atacadista no preto e branco do logo — quem compra é revendedor, quer ver preço, peça e quanto
          falta para fechar o pedido. Recusa o "atacadão amarelo de encarte" e a loja de varejo com banner de estilo de vida.
  OWN-WORLD: preto #0a0a0a em CAMPOS inteiros (topo, capa, regra do atacado, rodapé), página branca para o produto aparecer,
          UM sinal — lima #c8f31d — só onde a regra do atacado fala (mínimo de peças, contador, selo). Jost 800/900 itálico
          em caixa alta, como o "CELL MASTER" do logo. O gesto é a INCLINAÇÃO do M: marcador de título, selo e número inclinam.
  STORY: o revendedor reconhece a marca, lê a regra (6 peças, pode misturar), enche a sacola vendo o contador e fecha.
  FIRST VIEWPORT: topo preto com o logo; capa preta com o título gigante em itálico e o M do logo entrando pela direita.
  FORM: cartaz tipográfico de balcão; movimento único = o M sobe inclinado ao abrir a capa.
--}}
@php
    $cmMarca = \App\Services\LojaOnline\LojaContexto::arquivo(\App\Services\LojaOnline\Exclusivos\CellMaster::caminho($loja, 'marca.png'));
    $cmLogo = \App\Services\LojaOnline\LojaContexto::arquivo(($tema['logo'] ?? '') ?: \App\Services\LojaOnline\Exclusivos\CellMaster::caminho($loja, 'logo.png'));
@endphp
<style>
[data-modelo=cellmaster]{--preto:#0a0a0a;--lima:#c8f31d;--papel:#f3f3f0;--giz:#b9b9b2;--inclina:-14deg;--sai:cubic-bezier(.16,1,.3,1)}
[data-modelo=cellmaster] h1,[data-modelo=cellmaster] h2,[data-modelo=cellmaster] h3{font-style:italic;font-weight:800;text-transform:uppercase;letter-spacing:-.02em;text-wrap:balance}

/* ── topo: campo preto, logo branco, navegação em caixa alta ── */
[data-modelo=cellmaster] .lj-util{background:var(--preto);color:var(--giz);border-bottom:1px solid rgb(255 255 255/.14)}
[data-modelo=cellmaster] .lj-util a:hover{color:#fff}
[data-modelo=cellmaster] .lj-topo{background:var(--preto);color:#fff;box-shadow:none}
[data-modelo=cellmaster] .lj-marca img{max-height:58px}
[data-modelo=cellmaster] .lj-topo__nav{border-top:1px solid rgb(255 255 255/.14)}
[data-modelo=cellmaster] .lj-nav > a,[data-modelo=cellmaster] .lj-mega__botao{text-transform:uppercase;font-style:italic;font-weight:700;letter-spacing:.02em;font-size:.86rem}
[data-modelo=cellmaster] .lj-nav > a[aria-current=page]{color:var(--lima)}
/* nomes de categoria longos: só os 4 primeiros atalhos ficam na barra (o resto está em "Todas as categorias") e encolhem com reticências */
[data-modelo=cellmaster] .lj-nav{min-width:0}
[data-modelo=cellmaster] .lj-nav > a{display:block;flex:0 1 auto;min-width:0;line-height:46px;overflow:hidden;text-overflow:ellipsis}
[data-modelo=cellmaster] .lj-nav > a:first-of-type,[data-modelo=cellmaster] .lj-nav > a.lj-nav__oferta,[data-modelo=cellmaster] .lj-nav > .lj-mega{flex:none}
[data-modelo=cellmaster] .lj-nav > a:not(.lj-nav__oferta):nth-of-type(n+6){display:none}
[data-modelo=cellmaster] .lj-sacola .lj-conta{background:var(--lima);color:var(--preto);font-weight:800}
@media (max-width:899px){[data-modelo=cellmaster] .lj-marca img{max-height:40px}}

/* ── faixa: a regra do atacado em lima, correndo ── */
[data-modelo=cellmaster] .lj-faixa{background:var(--lima);color:var(--preto);text-transform:uppercase;font-style:italic;font-weight:800;letter-spacing:.04em;font-size:.82rem;padding-block:.6rem}

/* ── títulos de seção: marcador inclinado como o M ── */
[data-modelo=cellmaster] .lj-secao__topo h2,[data-modelo=cellmaster] .lj-faq h2{font-size:clamp(1.6rem,3.2vw,2.5rem);line-height:1;display:flex;align-items:center;gap:.5em}
[data-modelo=cellmaster] .lj-secao__topo h2::before,[data-modelo=cellmaster] .lj-faq h2::before{content:"";flex:none;width:.42em;height:.86em;background:var(--preto);transform:skewX(var(--inclina))}
[data-modelo=cellmaster] .lj-link{text-transform:uppercase;font-style:italic;font-weight:700;font-size:.84rem}

/* ── capa: cartaz preto, título gigante, o M do logo à direita ── */
[data-modelo=cellmaster] .lj-carrossel{--alt:clamp(460px,46vw,620px)}
[data-modelo=cellmaster] .lj-slide[data-tom=cor]{background:var(--preto);color:#fff}
[data-modelo=cellmaster] .lj-slide[data-tom=cor][data-n="1"]{background:var(--lima);color:var(--preto)}
[data-modelo=cellmaster] .lj-slide[data-tom=cor]::before{content:"";position:absolute;right:max(-40px,calc((100vw - 1320px)/2 - 10px));bottom:-9%;width:min(52vw,640px);aspect-ratio:649/636;
  background:url("{{ $cmMarca }}") center/contain no-repeat;opacity:.16;pointer-events:none}
[data-modelo=cellmaster] .lj-slide[data-tom=cor][data-n="1"]::before{filter:invert(1);opacity:.14}
[data-modelo=cellmaster] .lj-slide[data-tom=cor]:not(:has(.lj-slide__produto))::before{opacity:1;width:min(40vw,500px);bottom:auto;top:50%;translate:0 -50%;right:max(24px,calc((100vw - 1320px)/2 + 40px))}
[data-modelo=cellmaster] .lj-slide[data-tom=cor][data-n="1"]:not(:has(.lj-slide__produto))::before{opacity:1}
[data-modelo=cellmaster] .lj-slide__dentro{grid-template-columns:minmax(0,1.25fr) minmax(0,.75fr)}
[data-modelo=cellmaster] .lj-slide__texto{max-width:44rem}
[data-modelo=cellmaster] .lj-slide h1,[data-modelo=cellmaster] .lj-slide h2{font-weight:900;font-size:clamp(2.5rem,6.4vw,5.6rem);line-height:1.02;letter-spacing:-.035em}
[data-modelo=cellmaster] .lj-slide p{font-size:clamp(1.02rem,1.5vw,1.22rem);max-width:38ch;opacity:1;color:var(--giz)}
[data-modelo=cellmaster] .lj-slide[data-n="1"] p{color:color-mix(in oklab,var(--preto) 82%,var(--lima))}
[data-modelo=cellmaster] .lj-slide[data-tom=cor][data-n="0"] .lj-btn{background:var(--lima);border-color:var(--lima);color:var(--preto)}
[data-modelo=cellmaster] .lj-slide[data-tom=cor][data-n="1"] .lj-btn{background:var(--preto);border-color:var(--preto);color:#fff}
[data-modelo=cellmaster] .lj-slide__produto{border-radius:0;rotate:-4deg;width:min(100%,360px);box-shadow:14px 18px 0 0 rgb(255 255 255/.14)}
[data-modelo=cellmaster] .lj-slide[data-n="1"] .lj-slide__produto{box-shadow:14px 18px 0 0 rgb(10 10 10/.16)}
[data-modelo=cellmaster] .lj-slide__produto span{border-radius:0;background:var(--preto);color:#fff;left:0;right:0;bottom:0;padding:.7rem .9rem}
[data-modelo=cellmaster] .lj-slide__produto b{color:var(--lima);font-style:italic;font-weight:800}
[data-modelo=cellmaster] .lj-carrossel__seta{border-radius:0;background:#fff}
[data-modelo=cellmaster] .lj-carrossel__pontos{justify-content:flex-start;left:max(16px,calc((100vw - 1320px)/2 + 30px));mix-blend-mode:difference}
[data-modelo=cellmaster] .lj-carrossel__pontos button::before{border-radius:0;height:5px;transform:skewX(var(--inclina))}
@media (max-width:859px){
  [data-modelo=cellmaster] .lj-carrossel,[data-modelo=cellmaster] .lj-carrossel[data-altura=alta]{--alt:auto}
  [data-modelo=cellmaster] .lj-slide__dentro{grid-template-columns:1fr;padding-block:2.4rem 3.8rem}
  [data-modelo=cellmaster] .lj-slide h1,[data-modelo=cellmaster] .lj-slide h2{font-size:clamp(2.3rem,11.5vw,3.4rem)}
  [data-modelo=cellmaster] .lj-slide[data-tom=cor]::before,[data-modelo=cellmaster] .lj-slide[data-tom=cor]:not(:has(.lj-slide__produto))::before{opacity:.14;width:78vw;top:auto;bottom:-8%;right:-14vw;translate:none}
  [data-modelo=cellmaster] .lj-slide[data-tom=cor][data-n="1"]:not(:has(.lj-slide__produto))::before{opacity:.12}
  [data-modelo=cellmaster] .lj-slide__produto{display:none}
  [data-modelo=cellmaster] .lj-carrossel__pontos{left:10px}
}

/* ── a regra do atacado: campo preto de ponta a ponta, número em lima ── */
[data-modelo=cellmaster] .lj-atacado{background:var(--preto);color:#fff;margin-top:0;border-top:1px solid rgb(255 255 255/.16);padding-block:clamp(2.6rem,6vw,4.6rem);overflow:hidden}
[data-modelo=cellmaster] .lj-atacado__dentro{display:grid;gap:clamp(2rem,5vw,4.5rem);align-items:center}
[data-modelo=cellmaster] .lj-atacado__numero{display:flex;align-items:center;gap:1.1rem;margin:0 0 1.6rem .6rem}
[data-modelo=cellmaster] .lj-atacado__numero b{font-style:italic;font-weight:900;font-size:clamp(5.4rem,13vw,6rem);line-height:.8;color:var(--preto);background:var(--lima);padding:.14em .3em .1em .22em;transform:skewX(var(--inclina));font-variant-numeric:lining-nums}
[data-modelo=cellmaster] .lj-atacado__numero span{font-style:italic;font-weight:800;text-transform:uppercase;font-size:clamp(1.05rem,2vw,1.4rem);line-height:1.08;color:var(--lima)}
[data-modelo=cellmaster] .lj-atacado h2{font-size:clamp(1.8rem,3.6vw,2.9rem);line-height:.98;color:#fff}
[data-modelo=cellmaster] .lj-atacado__apoio{margin-top:.8rem;color:var(--giz);max-width:34ch;font-size:1.05rem}
[data-modelo=cellmaster] .lj-atacado__passos{list-style:none;margin:0;padding:0;counter-reset:passo;display:grid}
[data-modelo=cellmaster] .lj-atacado__passos li{counter-increment:passo;display:grid;grid-template-columns:3.2rem minmax(0,1fr);column-gap:1rem;padding:1.15rem 0;border-top:1px solid rgb(255 255 255/.2)}
[data-modelo=cellmaster] .lj-atacado__passos li:last-child{border-bottom:1px solid rgb(255 255 255/.2)}
[data-modelo=cellmaster] .lj-atacado__passos li::before{content:counter(passo);grid-row:1/span 2;font-style:italic;font-weight:900;font-size:2.4rem;line-height:1;color:var(--lima)}
[data-modelo=cellmaster] .lj-atacado__passos strong{font-style:italic;font-weight:800;text-transform:uppercase;font-size:1.12rem;letter-spacing:-.005em}
[data-modelo=cellmaster] .lj-atacado__passos span{grid-column:2;color:var(--giz);max-width:52ch;margin-top:.2rem}
@media (min-width:860px){[data-modelo=cellmaster] .lj-atacado__dentro{grid-template-columns:minmax(0,.85fr) minmax(0,1.15fr)}}

[data-modelo=cellmaster] .lj-aviso-exemplo{display:block}
[data-modelo=cellmaster] .lj-aviso-exemplo i{margin-right:.35rem}

/* ── cartão de produto: foto no branco, linha fina, preço que manda ── */
[data-modelo=cellmaster] .lj-card{border-color:color-mix(in oklab,var(--preto) 16%,#fff);transition:border-color .2s,box-shadow .25s var(--sai)}
[data-modelo=cellmaster] .lj-card:hover{border-color:var(--preto);box-shadow:6px 6px 0 0 var(--preto)}
[data-modelo=cellmaster] .lj-card__foto{background:#fff}
[data-modelo=cellmaster] .lj-card__foto img{object-fit:contain;padding:8%}
[data-modelo=cellmaster] .lj-card__corpo{border-top:1px solid color-mix(in oklab,var(--preto) 16%,#fff)}
[data-modelo=cellmaster] .lj-card__cat{font-weight:600;letter-spacing:.06em}
[data-modelo=cellmaster] .lj-card__nome{font-weight:600}
[data-modelo=cellmaster] .lj-preco b{font-style:italic;font-weight:800;font-size:1.28rem;letter-spacing:-.01em}
[data-modelo=cellmaster] .lj-mais{background:var(--preto);color:#fff;border-radius:0}
[data-modelo=cellmaster] .lj-mais:hover{background:var(--lima);color:var(--preto)}
[data-modelo=cellmaster] .lj-selo{border-radius:0;font-style:italic;font-weight:800;text-transform:uppercase;transform:skewX(var(--inclina));transform-origin:left bottom}
[data-modelo=cellmaster] .lj-selo--novo{background:var(--preto);color:#fff}
[data-modelo=cellmaster] .lj-fav{border-radius:0}

/* ── categorias em cartão: etiqueta preta no pé ── */
[data-modelo=cellmaster] .lj-cat{background:var(--papel);color:var(--preto);border:1px solid color-mix(in oklab,var(--preto) 16%,#fff)}
[data-modelo=cellmaster] .lj-cat--foto::after{display:none}
[data-modelo=cellmaster] .lj-cat img{object-fit:contain;padding:10% 10% 26%;background:#fff}
[data-modelo=cellmaster] .lj-cat strong{background:var(--preto);color:#fff;font-style:italic;font-weight:800;text-transform:uppercase;padding:.55rem .8rem;margin:0;width:100%;font-size:1rem}
[data-modelo=cellmaster] .lj-cat:not(.lj-cat--foto) strong{color:#fff}
[data-modelo=cellmaster] .lj-cat small{font-style:normal;text-transform:none;font-weight:500;color:var(--giz);opacity:1}
[data-modelo=cellmaster] .lj-cat:hover strong{background:var(--lima);color:var(--preto)}
[data-modelo=cellmaster] .lj-cat:hover small{color:color-mix(in oklab,var(--preto) 78%,var(--lima))}
[data-modelo=cellmaster] .lj-cat{padding:0}
[data-modelo=cellmaster] .lj-cat strong{overflow-wrap:anywhere;line-height:1.1}
@media (max-width:699px){[data-modelo=cellmaster] .lj-cat strong{font-size:.84rem;padding:.45rem .6rem}[data-modelo=cellmaster] .lj-cat img{padding:8% 8% 34%}}

/* ── página do produto: a foto inteira, sem cortar a embalagem ── */
[data-modelo=cellmaster] .lj-galeria__palco img{object-fit:contain;background:#fff}

/* ── grade de banners (quando a loja usar): cartaz preto ── */
[data-modelo=cellmaster] .lj-bannerzinho:not(.lj-bannerzinho--foto){background:var(--papel)}
[data-modelo=cellmaster] .lj-bannerzinho strong{font-style:italic;font-weight:900;text-transform:uppercase;letter-spacing:-.02em}
[data-modelo=cellmaster] .lj-bannerzinho em{text-transform:uppercase;font-style:italic;font-weight:800}

/* ── dúvidas ── */
[data-modelo=cellmaster] .lj-faq summary{font-weight:700;font-size:1.06rem}
[data-modelo=cellmaster] .lj-faq details[open] summary{font-style:italic}

/* ── botões ── */
[data-modelo=cellmaster] .lj-btn{text-transform:uppercase;font-style:italic;font-weight:800;letter-spacing:.01em}
[data-modelo=cellmaster] .lj-btn:not(.lj-btn--linha):hover{box-shadow:5px 5px 0 0 var(--lima)}
[data-modelo=cellmaster] .lj-slide .lj-btn:hover{box-shadow:5px 5px 0 0 rgb(255 255 255/.3)}

/* ── contador de peças (sacola e carrinho): trilho preto, sinal em lima ── */
[data-modelo=cellmaster] .lj-pecas{background:var(--preto);color:#fff}
[data-modelo=cellmaster] .lj-pecas p{font-weight:500}
[data-modelo=cellmaster] .lj-pecas strong{font-style:italic;font-weight:800;text-transform:uppercase;color:var(--lima)}
[data-modelo=cellmaster] .lj-pecas .lj-meta__barra{background:rgb(255 255 255/.22);border-radius:0;height:8px}
[data-modelo=cellmaster] .lj-pecas .lj-meta__barra i,[data-modelo=cellmaster] .lj-pecas[data-ok=nao] .lj-meta__barra i{background:var(--lima);border-radius:0}

/* ── rodapé: preto, com o logo no lugar do nome ── */
[data-modelo=cellmaster] .lj-rodape .lj-marca{display:block;width:min(100%,250px);aspect-ratio:676/220;background:url("{{ $cmLogo }}") left center/contain no-repeat;font-size:0;color:transparent}
[data-modelo=cellmaster] .lj-rodape h2{font-size:.82rem;letter-spacing:.06em;color:#fff}

/* ── movimento: o M sobe inclinado ao abrir a capa (único momento) ── */
@media (prefers-reduced-motion:no-preference){
  [data-modelo=cellmaster]:not([data-movimento=nenhum]) .lj-slide[data-tom=cor][data-n="0"]::before{animation:cm-marca 1s var(--sai) both}
  @keyframes cm-marca{from{clip-path:inset(100% 0 0 0);transform:translate(-26px,60px)}to{clip-path:inset(0 0 0 0);transform:translate(0,0)}}
}
</style>
