{{-- Folha de estilo da vitrine — UMA para todos os modelos (14/11/2026).
     Inline de propósito: o deploy por tar não leva public/ (armadilha 46).
     O modelo entra pelas variáveis de :root (TemaCss) e pelos data-* do <body>. --}}
<style>
*,*::before,*::after{box-sizing:border-box}
html{-webkit-text-size-adjust:100%;scroll-behavior:smooth}
body{margin:0;background:var(--fundo);color:var(--texto);font-family:var(--fonte-texto);font-size:16px;line-height:1.55;
  --suave:color-mix(in oklab,var(--texto) 66%,var(--fundo));--linha:color-mix(in oklab,var(--texto) 13%,var(--fundo));
  --linha-forte:color-mix(in oklab,var(--texto) 28%,var(--fundo));--linha-campo:color-mix(in oklab,var(--texto) 50%,var(--fundo));--ok:#0b7a43;--erro:#b3261e;
  --sombra:0 10px 24px -14px rgb(0 0 0/.28),0 2px 6px -2px rgb(0 0 0/.08);-webkit-font-smoothing:antialiased}
[hidden]{display:none!important}
img{max-width:100%;display:block}
a{color:inherit}
h1,h2,h3{font-family:var(--fonte-titulo);line-height:1.12;letter-spacing:-.02em;margin:0;text-wrap:balance;font-weight:700}
p{margin:0}
button,input,select,textarea{font:inherit;color:inherit}
:focus-visible{outline:2px solid var(--marca-tinta);outline-offset:2px;border-radius:2px}
.lj-topo :focus-visible{outline-color:var(--sobre-topo)}.lj-rodape :focus-visible{outline-color:var(--sobre-rodape)}
.lj-banner :focus-visible,.lj-faixa :focus-visible{outline-color:var(--sobre-marca)}
.lj-banner--centro :focus-visible,.lj-banner--editorial :focus-visible{outline-color:var(--texto)}
.lj-banner--foto :focus-visible{outline-color:#fff}
.lj-so-leitor{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
.lj-caixa{width:100%;max-width:1240px;margin-inline:auto;padding-inline:clamp(16px,4vw,40px)}
.lj-pular{position:absolute;left:8px;top:-60px;background:var(--marca);color:var(--sobre-marca);padding:.6rem 1rem;z-index:99;border-radius:6px}
.lj-pular:focus{top:8px}

/* ── botões ── */
.lj-btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;border:1.5px solid var(--marca);background:var(--marca);
  color:var(--sobre-marca);border-radius:var(--raio-botao);padding:.8rem 1.4rem;font-weight:600;text-decoration:none;cursor:pointer;
  line-height:1.2;transition:transform .18s cubic-bezier(.2,.8,.2,1),box-shadow .18s,background-color .18s;min-height:46px}
.lj-btn:hover{transform:translateY(-1px);box-shadow:0 8px 16px -10px rgb(0 0 0/.5)}
.lj-btn:active{transform:translateY(0)}
.lj-btn[disabled],.lj-btn[aria-disabled=true]{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none}
.lj-btn--linha{background:transparent;color:var(--texto);border-color:var(--linha-forte)}
.lj-btn--linha:hover{border-color:var(--texto)}
.lj-btn--inverso{background:var(--sobre-marca);color:var(--marca);border-color:var(--sobre-marca)}
.lj-btn--cheio{width:100%}
.lj-btn--p{padding:.5rem .9rem;min-height:38px;font-size:.9rem}
[data-titulos=maiusculas] .lj-btn{text-transform:uppercase;letter-spacing:.12em;font-size:.82rem;font-weight:500}
.lj-link{color:var(--marca-tinta);font-weight:600;text-underline-offset:3px}

/* ── cabeçalho ── */
.lj-previa{background:#111;color:#fff;font-size:.85rem;padding:.5rem 0;text-align:center}
.lj-previa a{color:#ffd166}
.lj-faixa{background:var(--oferta);color:var(--sobre-oferta);font-weight:600;text-align:center;font-size:.82rem;letter-spacing:.03em;padding:.55rem 1rem}
.lj-topo{background:var(--topo);color:var(--sobre-topo);position:sticky;top:0;z-index:30;
  border-bottom:1px solid color-mix(in oklab,var(--sobre-topo) 13%,var(--topo))}
.lj-topo__linha{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:clamp(12px,3vw,32px);align-items:center;padding-block:.85rem}
.lj-marca{display:inline-flex;align-items:center;gap:.6rem;text-decoration:none;font-family:var(--fonte-titulo);font-weight:700;
  font-size:clamp(1.15rem,2.4vw,1.5rem);letter-spacing:-.02em;line-height:1.1}
.lj-marca img{max-height:44px;width:auto}
[data-titulos=maiusculas] .lj-marca{text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:clamp(1rem,2.2vw,1.35rem)}
.lj-busca{display:flex;align-items:center;gap:.5rem;background:color-mix(in oklab,var(--sobre-topo) 8%,var(--topo));
  border:1px solid color-mix(in oklab,var(--sobre-topo) 16%,var(--topo));border-radius:var(--raio-botao);padding:0 .9rem;max-width:520px;width:100%}
.lj-busca:focus-within{border-color:var(--sobre-topo)}
.lj-busca input{flex:1;min-width:0;border:0;background:transparent;padding:.65rem 0;outline:0}
.lj-busca input::placeholder{color:color-mix(in oklab,var(--sobre-topo) 90%,var(--topo))}
.lj-busca button{border:0;background:transparent;cursor:pointer;padding:.3rem;display:grid}
.lj-acoes{display:flex;align-items:center;gap:.4rem}
.lj-icone{position:relative;display:grid;place-items:center;width:44px;height:44px;border-radius:999px;text-decoration:none;font-size:1.3rem}
.lj-icone:hover{background:color-mix(in oklab,var(--sobre-topo) 10%,var(--topo))}
.lj-conta{position:absolute;top:2px;right:0;min-width:20px;height:20px;padding:0 5px;border-radius:999px;background:var(--oferta);
  color:var(--sobre-oferta);font-size:.72rem;font-weight:700;display:grid;place-items:center;line-height:1}
.lj-conta[hidden]{display:none}
.lj-conta.pulsa{animation:lj-pulsa .5s cubic-bezier(.2,.8,.2,1)}
@keyframes lj-pulsa{40%{transform:scale(1.45)}}
.lj-nav{display:flex;gap:1.4rem;overflow-x:auto;scrollbar-width:none;padding-bottom:.7rem;font-size:.92rem}
.lj-nav::-webkit-scrollbar{display:none}
.lj-nav a{text-decoration:none;white-space:nowrap;color:color-mix(in oklab,var(--sobre-topo) 82%,var(--topo));padding-block:.15rem;border-bottom:2px solid transparent}
.lj-nav a:hover,.lj-nav a[aria-current]{color:var(--sobre-topo);border-color:currentColor}
[data-titulos=maiusculas] .lj-nav{text-transform:uppercase;letter-spacing:.14em;font-size:.76rem}
[data-titulos=maiusculas] .lj-faixa{background:var(--texto);color:var(--fundo);text-transform:uppercase;letter-spacing:.14em;font-weight:500;font-size:.72rem}
/* telas de compra (fechamento e pedido): sem busca nem categorias para distrair */
[data-pagina=compra] .lj-busca,[data-pagina=compra] .lj-nav{display:none}
[data-pagina=compra] .lj-topo{position:static}
[data-pagina=compra] .lj-topo__linha{grid-template-columns:minmax(0,1fr) auto}
[data-cabecalho=centralizado] .lj-topo__linha{grid-template-columns:minmax(0,1fr) auto minmax(0,1fr)}
[data-cabecalho=centralizado] .lj-topo__linha > .lj-marca{grid-column:2;grid-row:1;justify-self:center}
[data-cabecalho=centralizado] .lj-busca{grid-column:1;grid-row:1;max-width:300px}
[data-cabecalho=centralizado] .lj-acoes{grid-column:3;justify-self:end}
[data-cabecalho=centralizado] .lj-nav{justify-content:center}
@media (max-width:719px){
  .lj-topo__linha,[data-cabecalho=centralizado] .lj-topo__linha{grid-template-columns:minmax(0,1fr) auto;row-gap:.6rem}
  .lj-topo__linha > .lj-marca,[data-cabecalho=centralizado] .lj-topo__linha > .lj-marca{grid-column:1;grid-row:1;justify-self:start}
  .lj-topo{position:static}
  .lj-acoes,[data-cabecalho=centralizado] .lj-acoes{grid-column:2;grid-row:1}
  .lj-busca,[data-cabecalho=centralizado] .lj-busca{grid-column:1/-1;grid-row:2;max-width:none}
  [data-cabecalho=centralizado] .lj-nav{justify-content:flex-start}
}

/* ── seções ── */
.lj-secao{padding-block:clamp(2.2rem,5vw,4rem) 0}
.lj-secao__topo{display:flex;align-items:baseline;justify-content:space-between;gap:1rem;margin-bottom:1.25rem}
.lj-secao__topo h2{font-size:clamp(1.4rem,3vw,2rem)}
[data-titulos=maiusculas] .lj-secao__topo h2{text-transform:uppercase;letter-spacing:.16em;font-size:clamp(1rem,2vw,1.2rem);font-weight:500}
.lj-vazio{border:1.5px dashed var(--linha-forte);border-radius:var(--raio);padding:1.5rem;color:var(--suave);text-align:center}

/* ── banner ── */
.lj-banner{position:relative;overflow:hidden;background:var(--marca);color:var(--sobre-marca)}
.lj-banner__dentro{display:grid;gap:clamp(1.5rem,4vw,3rem);align-items:center;min-height:var(--alt,420px);padding-block:clamp(2rem,6vw,4rem)}
.lj-banner[data-altura=baixa]{--alt:240px}.lj-banner[data-altura=alta]{--alt:560px}
.lj-banner h1{font-size:clamp(2.1rem,6vw,4.2rem);font-weight:800;letter-spacing:-.035em}
.lj-banner__sub{font-size:clamp(1rem,1.6vw,1.2rem);max-width:46ch;margin-top:1rem;opacity:.92}
.lj-banner .lj-btn{margin-top:1.6rem}
.lj-banner__img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.lj-banner--foto::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgb(0 0 0/.62),rgb(0 0 0/.18) 70%,rgb(0 0 0/0))}
.lj-banner--foto{color:#fff;background:#111}
.lj-banner--foto .lj-banner__dentro{position:relative;z-index:1}
.lj-banner--foto .lj-btn--inverso{background:#fff;color:#111;border-color:#fff}
@media (min-width:860px){.lj-banner--dividido .lj-banner__dentro{grid-template-columns:1.05fr .95fr}}
/* cheio ("banca"): texto em cima, os produtos saem da faixa de cor por baixo */
.lj-banner--cheio:not(.lj-banner--foto){overflow:visible;margin-bottom:clamp(2rem,5vw,3.5rem)}
.lj-banner--cheio:not(.lj-banner--foto) .lj-banner__dentro{grid-template-columns:1fr;padding-bottom:0;min-height:0}
.lj-banner--cheio .lj-banner__sub{max-width:56ch}
.lj-banner--cheio .lj-mosaico{grid-template-columns:repeat(3,1fr);grid-template-rows:auto;aspect-ratio:auto;max-width:none;justify-self:stretch;margin-bottom:calc(-1*clamp(2rem,5vw,3.5rem))}
.lj-banner--cheio .lj-mosaico a{grid-row:auto!important;aspect-ratio:4/3;box-shadow:var(--sombra)}
@media (max-width:599px){.lj-banner--cheio .lj-mosaico{gap:8px}.lj-banner--cheio .lj-mosaico span{left:6px;bottom:6px;font-size:.74rem!important;padding:.18rem .4rem .18rem .8rem!important}}
/* baixa: compacto de verdade (catálogo técnico) */
.lj-banner[data-altura=baixa] .lj-banner__dentro{padding-block:clamp(1.25rem,3vw,2rem);min-height:0}
.lj-banner[data-altura=baixa] h1,.lj-banner[data-altura=baixa] h2{font-size:clamp(1.7rem,3.4vw,2.6rem)}
.lj-banner[data-altura=baixa] .lj-btn{margin-top:1rem}
.lj-banner--cheio[data-altura=baixa]:not(.lj-banner--foto){margin-bottom:0}
.lj-banner--cheio[data-altura=baixa]:not(.lj-banner--foto) .lj-banner__dentro{padding-bottom:clamp(1.25rem,3vw,2rem)}
@media (min-width:860px){.lj-banner--cheio[data-altura=baixa]:not(.lj-banner--foto) .lj-banner__dentro{grid-template-columns:1fr auto}}
.lj-banner[data-altura=baixa] .lj-mosaico{grid-template-columns:repeat(3,1fr);grid-template-rows:auto;aspect-ratio:auto;max-width:360px;width:360px;gap:8px;margin-bottom:0;justify-self:end}
.lj-banner[data-altura=baixa] .lj-mosaico a{grid-row:auto!important;aspect-ratio:1;box-shadow:none}
.lj-banner[data-altura=baixa] .lj-mosaico span{display:none}
@media (max-width:859px){.lj-banner[data-altura=baixa] .lj-mosaico{width:100%;max-width:none}}
/* mosaico de produtos reais (banner sem imagem) */
.lj-mosaico{display:grid;grid-template-columns:1.25fr 1fr;grid-template-rows:1fr 1fr;gap:12px;aspect-ratio:1.18/1;max-width:560px;justify-self:end;width:100%}
.lj-mosaico a{position:relative;display:block;overflow:hidden;border-radius:var(--raio);background:var(--quadro);color:var(--texto);text-decoration:none}
.lj-mosaico a:first-child{grid-row:1/3}
.lj-mosaico a:only-child{grid-column:1/-1}
.lj-mosaico img{width:100%;height:100%;object-fit:cover;transition:transform .5s cubic-bezier(.2,.8,.2,1)}
.lj-mosaico a:hover img{transform:scale(1.04)}
.lj-mosaico span{position:absolute;left:10px;bottom:10px;background:var(--fundo);color:var(--texto);border-radius:var(--raio-botao);
  padding:.3rem .7rem;font-size:.82rem;font-weight:700;box-shadow:0 4px 10px -6px rgb(0 0 0/.5);font-family:var(--fonte-preco);font-variant-numeric:lining-nums tabular-nums}
[data-preco=etiqueta] .lj-mosaico span{background:var(--oferta);color:var(--sobre-oferta);border-radius:4px;box-shadow:none;font-size:1rem;padding:.3rem .7rem .3rem 1.15rem;clip-path:polygon(12px 0,100% 0,100% 100%,12px 100%,0 50%)}
@media (prefers-reduced-motion:no-preference){
  .lj-mosaico a{animation:lj-revela .9s cubic-bezier(.16,1,.3,1) both}
  .lj-mosaico a:nth-child(2){animation-delay:.12s}.lj-mosaico a:nth-child(3){animation-delay:.24s}
  @keyframes lj-revela{from{clip-path:inset(100% 0 0 0);transform:translateY(18px)}to{clip-path:inset(0);transform:none}}
}
.lj-banner--centro{background:var(--quadro);color:var(--texto);text-align:center}
.lj-banner--centro .lj-banner__sub{margin-inline:auto}
.lj-banner--centro .lj-mosaico{grid-template-columns:repeat(3,1fr);grid-template-rows:auto;aspect-ratio:auto;max-width:760px;justify-self:center}
.lj-banner--centro .lj-mosaico a{grid-row:auto!important;aspect-ratio:1}
.lj-banner--centro.lj-banner--foto{color:#fff}
.lj-banner--centro.lj-banner--foto::after{background:rgb(0 0 0/.45)}
/* editorial: título gigante, fios, fotos em pé */
.lj-banner--editorial{background:var(--fundo);color:var(--texto)}
.lj-banner--editorial .lj-banner__dentro{display:block;min-height:0;padding-block:clamp(2rem,5vw,3.5rem) clamp(1.5rem,4vw,3rem)}
.lj-banner--editorial h1{font-size:clamp(3rem,12.5vw,6rem);font-weight:500;letter-spacing:-.03em;line-height:.95;text-transform:uppercase;
  border-top:1px solid var(--texto);padding-top:clamp(1rem,3vw,2rem)}
.lj-banner--editorial .lj-banner__pe{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:end;gap:1rem 2rem;
  border-bottom:1px solid var(--texto);padding-block:1.2rem}
.lj-banner--editorial .lj-banner__sub{margin:0}
.lj-banner--editorial .lj-btn{margin:0}
.lj-banner--editorial .lj-mosaico{grid-template-columns:repeat(3,1fr);grid-template-rows:auto;aspect-ratio:auto;max-width:none;margin-top:clamp(1rem,3vw,2rem)}
.lj-banner--editorial .lj-mosaico a{grid-row:auto!important;aspect-ratio:3/4}
.lj-banner--editorial.lj-banner--foto{color:var(--texto);background:var(--fundo)}
.lj-banner--editorial.lj-banner--foto::after{display:none}
.lj-banner--editorial .lj-banner__img{position:static;aspect-ratio:21/9;margin-top:clamp(1rem,3vw,2rem);height:auto}
@media (max-width:859px){.lj-mosaico{justify-self:stretch;max-width:none}}

/* ── vantagens (faixa, não cartões) ── */
.lj-vantagens{display:grid;border-block:1px solid var(--linha);margin-top:clamp(2.2rem,5vw,4rem)}
.lj-vantagens > div{display:flex;gap:.9rem;align-items:flex-start;padding:1.15rem 0}
.lj-vantagens i{font-size:1.5rem;color:var(--marca-tinta);line-height:1.2}
.lj-vantagens strong{display:block;font-family:var(--fonte-titulo)}
.lj-vantagens span{color:var(--suave);font-size:.92rem}
@media (min-width:760px){.lj-vantagens{grid-template-columns:repeat(var(--n,3),1fr)}
  .lj-vantagens > div{padding:1.35rem 1.5rem}.lj-vantagens > div + div{border-left:1px solid var(--linha)}
  .lj-vantagens > div:first-child{padding-left:0}}
@media (max-width:759px){.lj-vantagens > div + div{border-top:1px solid var(--linha)}}

/* ── grade e cartão de produto ── */
.lj-grade{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:clamp(12px,2vw,24px) clamp(10px,2vw,22px)}
@media (min-width:700px){.lj-grade{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media (min-width:1000px){.lj-grade{grid-template-columns:repeat(var(--colunas,4),minmax(0,1fr))}}
.lj-card{position:relative;display:flex;flex-direction:column;min-width:0}
.lj-card__foto{position:relative;display:block;aspect-ratio:1;overflow:hidden;background:var(--quadro);border-radius:var(--raio)}
[data-foto=retrato] .lj-card__foto{aspect-ratio:3/4}[data-foto=paisagem] .lj-card__foto{aspect-ratio:4/3}
.lj-card__foto img{width:100%;height:100%;object-fit:cover;transition:transform .5s cubic-bezier(.2,.8,.2,1)}
.lj-card:hover .lj-card__foto img{transform:scale(1.045)}
.lj-semfoto{display:grid;place-items:center;width:100%;height:100%;font-family:var(--fonte-titulo);font-weight:700;
  font-size:clamp(2.5rem,8vw,4.5rem);color:color-mix(in oklab,var(--texto) 22%,var(--quadro));text-transform:uppercase;user-select:none}
.lj-selo{position:absolute;top:10px;left:10px;background:var(--oferta);color:var(--sobre-oferta);font-size:.75rem;font-weight:700;
  padding:.22rem .55rem;border-radius:var(--raio-botao);letter-spacing:.02em;z-index:1}
.lj-selo--fim{background:var(--texto);color:var(--fundo)}
.lj-card__corpo{display:flex;flex-direction:column;gap:.2rem;padding-top:.75rem;flex:1}
.lj-card__cat{display:none;font-size:.74rem;color:var(--suave);text-transform:uppercase;letter-spacing:.08em}
[data-cartao=borda] .lj-card__cat{display:block}
.lj-sem-cat .lj-card__cat{display:none!important}
.lj-card__nome{font-weight:500;text-decoration:none;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.3}
.lj-card__nome::after{content:"";position:absolute;inset:0}
.lj-card__pe{display:flex;align-items:end;justify-content:space-between;gap:.5rem;margin-top:auto;padding-top:.45rem}
.lj-preco{display:flex;flex-direction:column;line-height:1.15}
.lj-preco s{color:var(--suave);font-size:.82rem;text-decoration-thickness:1px}
.lj-preco b{font-family:var(--fonte-preco);font-size:1.12rem;font-weight:700;letter-spacing:-.01em;font-variant-numeric:lining-nums tabular-nums}
.lj-preco--promo b{color:var(--oferta-tinta)}
[data-preco=etiqueta] .lj-preco b{background:var(--oferta);color:var(--sobre-oferta);padding:.2rem .6rem .2rem 1.05rem;border-radius:4px;
  clip-path:polygon(12px 0,100% 0,100% 100%,12px 100%,0 50%);font-size:1.22rem;width:max-content}
[data-preco=etiqueta] .lj-preco--promo b{color:var(--sobre-oferta)}
.lj-mais{position:relative;z-index:2;flex:none;width:40px;height:40px;border-radius:999px;border:1.5px solid var(--linha-forte);background:var(--fundo);
  color:var(--texto);display:grid;place-items:center;cursor:pointer;font-size:1.15rem;transition:background-color .15s,color .15s,border-color .15s}
.lj-mais:hover{background:var(--marca);color:var(--sobre-marca);border-color:var(--marca)}
.lj-mais[disabled]{opacity:.35;cursor:not-allowed}
[data-botao=reto] .lj-mais{border-radius:0}
[data-cartao=borda] .lj-card{border:1px solid var(--linha);border-radius:var(--raio);padding:10px;background:var(--fundo)}
[data-cartao=borda] .lj-card__foto{border-radius:max(0px,calc(var(--raio) - 6px))}
[data-cartao=sombra] .lj-card{background:var(--quadro);border-radius:var(--raio);box-shadow:var(--sombra);overflow:hidden;transition:transform .25s cubic-bezier(.2,.8,.2,1)}
[data-cartao=sombra] .lj-card:hover{transform:translateY(-3px)}
[data-cartao=sombra] .lj-card__foto{border-radius:0;background:color-mix(in oklab,var(--texto) 5%,var(--quadro))}
[data-cartao=sombra] .lj-card__corpo{padding:.8rem .9rem .9rem}
[data-cartao=limpo] .lj-card__foto{border-radius:var(--raio)}
/* lista: foto ao lado, código e saldo à vista */
[data-cartao=lista] .lj-grade{grid-template-columns:1fr;gap:0}
@media (min-width:760px){[data-cartao=lista] .lj-grade{grid-template-columns:repeat(2,minmax(0,1fr));gap:0 clamp(1.5rem,4vw,3.5rem)}}
[data-cartao=lista] .lj-card{flex-direction:row;gap:1rem;align-items:center;padding:.85rem 0;border-bottom:1px solid var(--linha)}
[data-cartao=lista] .lj-card__foto{width:76px;flex:none;border-radius:min(8px,var(--raio))}
[data-cartao=lista] .lj-card__corpo{padding:0;display:grid;grid-template-columns:minmax(0,1fr) auto;column-gap:1rem;align-items:center}
[data-cartao=lista] .lj-card__corpo > :not(.lj-card__pe){grid-column:1}
[data-cartao=lista] .lj-card__pe{grid-column:2;grid-row:1/span 3;padding:0;margin:0;gap:.9rem;align-items:center}
[data-cartao=lista] .lj-preco{align-items:flex-end}
@media (max-width:519px){[data-cartao=lista] .lj-card__corpo{grid-template-columns:minmax(0,1fr)}
  [data-cartao=lista] .lj-card__pe{grid-column:1;grid-row:auto;justify-content:space-between;padding-top:.35rem}
  [data-cartao=lista] .lj-preco{align-items:flex-start}}
.lj-card__ref{display:none;font-size:.78rem;color:var(--suave);font-variant-numeric:tabular-nums}
[data-cartao=lista] .lj-card__ref{display:block}

/* ── categorias ── */
.lj-cats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
@media (min-width:760px){.lj-cats{grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}}
.lj-cat{position:relative;display:flex;align-items:flex-end;aspect-ratio:4/3;overflow:hidden;border-radius:var(--raio);background:var(--marca);
  color:#fff;text-decoration:none;padding:1rem}
.lj-cat img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:transform .5s cubic-bezier(.2,.8,.2,1)}
.lj-cat:hover img{transform:scale(1.05)}
.lj-cat--foto::after{content:"";position:absolute;inset:0;background:linear-gradient(0deg,rgb(0 0 0/.68),rgb(0 0 0/0) 62%)}
.lj-cat:not(.lj-cat--foto){color:var(--sobre-marca)}
.lj-cat strong{position:relative;z-index:1;font-family:var(--fonte-titulo);font-size:1.08rem;line-height:1.2}
.lj-cat small{position:relative;z-index:1;display:block;font-weight:400;opacity:.85;font-size:.8rem;font-family:var(--fonte-texto)}
.lj-pilulas{display:flex;flex-wrap:wrap;gap:.6rem}
.lj-pilulas a{text-decoration:none;border:1.5px solid var(--linha-forte);border-radius:999px;padding:.6rem 1.15rem;font-weight:600;background:var(--quadro);
  transition:background-color .15s,color .15s,border-color .15s}
.lj-pilulas a:hover{background:var(--marca);color:var(--sobre-marca);border-color:var(--marca)}
.lj-indice{columns:1;column-gap:3rem}
@media (min-width:760px){.lj-indice{columns:2}}
.lj-indice a{display:flex;justify-content:space-between;align-items:baseline;gap:1rem;text-decoration:none;padding:.8rem 0;border-bottom:1px solid var(--linha);break-inside:avoid}
.lj-indice a span:first-child{font-family:var(--fonte-titulo);font-size:clamp(1.15rem,2.4vw,1.5rem)}
.lj-indice a span:last-child{color:var(--suave);font-size:.85rem;font-variant-numeric:tabular-nums}
.lj-indice a:hover span:first-child{text-decoration:underline;text-underline-offset:5px}

/* ── imagem com texto / texto ── */
.lj-par{display:grid;gap:clamp(1.25rem,4vw,3.5rem);align-items:center}
@media (min-width:820px){.lj-par{grid-template-columns:1fr 1fr}.lj-par[data-lado=direita] .lj-par__img{order:2}}
.lj-par__img{aspect-ratio:5/4;overflow:hidden;border-radius:var(--raio);background:var(--marca);color:var(--sobre-marca);display:grid;place-items:center}
.lj-par__img img{width:100%;height:100%;object-fit:cover}
.lj-par__img b{font-family:var(--fonte-titulo);font-size:clamp(3rem,11vw,7rem);line-height:.9;letter-spacing:-.04em;text-align:center;padding:1rem;overflow-wrap:anywhere}
.lj-par--sem-img{grid-template-columns:1fr!important;max-width:70ch}
.lj-par h2{font-size:clamp(1.6rem,3.6vw,2.6rem)}
.lj-par p{margin-top:1rem;color:var(--suave);max-width:60ch;white-space:pre-line}
.lj-par .lj-btn{margin-top:1.5rem}
.lj-texto{max-width:70ch}.lj-texto[data-al=centro]{margin-inline:auto;text-align:center}
.lj-texto h2{font-size:clamp(1.5rem,3.2vw,2.2rem)}.lj-texto p{margin-top:.9rem;color:var(--suave);white-space:pre-line}

/* ── lista de produtos ── */
.lj-trilha{font-size:.85rem;color:var(--suave);padding-top:1.4rem;display:flex;flex-wrap:wrap;gap:.4rem}
.lj-trilha a{text-underline-offset:3px}
.lj-pagina__topo{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:end;gap:1rem;padding-block:1rem 1.5rem}
.lj-pagina__topo h1{font-size:clamp(1.7rem,4vw,2.6rem)}
.lj-filtros{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center}
.lj-campo{border:1.5px solid var(--linha-campo);background:var(--fundo);border-radius:min(10px,var(--raio));padding:.7rem .85rem;width:100%;min-height:46px}
.lj-campo:focus{border-color:var(--marca-tinta);outline:0;box-shadow:0 0 0 3px color-mix(in oklab,var(--marca-tinta) 22%,transparent)}
.lj-campo[aria-invalid=true]{border-color:var(--erro)}
select.lj-campo{width:auto;padding-right:2rem}
.lj-paginas{display:flex;justify-content:center;gap:.4rem;flex-wrap:wrap;padding-block:2.5rem 0}
.lj-paginas a,.lj-paginas span{min-width:44px;height:44px;display:grid;place-items:center;border:1.5px solid var(--linha);border-radius:var(--raio-botao);text-decoration:none;padding-inline:.7rem}
.lj-paginas [aria-current]{background:var(--texto);color:var(--fundo);border-color:var(--texto)}
.lj-paginas a:hover{border-color:var(--texto)}

/* ── produto ── */
.lj-produto{display:grid;gap:clamp(1.5rem,4vw,4rem);padding-block:1rem clamp(2rem,5vw,3.5rem)}
@media (min-width:880px){.lj-produto{grid-template-columns:1.12fr .88fr;align-items:start}.lj-produto__info{position:sticky;top:120px}}
.lj-galeria{display:grid;gap:12px}
@media (min-width:880px){.lj-galeria--varias{grid-template-columns:76px 1fr}}
.lj-galeria__palco{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;border-radius:var(--raio);background:var(--quadro);aspect-ratio:1}
[data-foto=retrato] .lj-galeria__palco{aspect-ratio:3/4}
.lj-galeria__palco::-webkit-scrollbar{display:none}
.lj-galeria__palco > *{flex:0 0 100%;scroll-snap-align:center;width:100%;height:100%}
.lj-galeria__palco img{width:100%;height:100%;object-fit:cover}
.lj-miniaturas{display:flex;gap:8px;overflow-x:auto;scrollbar-width:none}
@media (min-width:880px){.lj-miniaturas{flex-direction:column;order:-1;max-height:560px}}
.lj-miniaturas button{flex:none;width:64px;aspect-ratio:1;padding:0;border:1.5px solid transparent;border-radius:min(8px,var(--raio));overflow:hidden;cursor:pointer;background:var(--quadro)}
@media (min-width:880px){.lj-miniaturas button{width:100%}}
.lj-miniaturas button[aria-current=true]{border-color:var(--texto)}
.lj-miniaturas img{width:100%;height:100%;object-fit:cover}
.lj-produto h1{font-size:clamp(1.6rem,3.6vw,2.4rem)}
.lj-produto__ref{color:var(--suave);font-size:.85rem;margin-top:.5rem;font-variant-numeric:tabular-nums}
.lj-produto__preco{margin-top:1.3rem;display:flex;align-items:baseline;flex-wrap:wrap;gap:.3rem .8rem}
.lj-produto__preco b{font-family:var(--fonte-preco);font-variant-numeric:lining-nums tabular-nums;font-size:clamp(1.9rem,4.4vw,2.6rem);letter-spacing:-.02em;line-height:1}
.lj-produto__preco s{color:var(--suave)}
.lj-produto__nota{color:var(--suave);font-size:.92rem;margin-top:.5rem}
.lj-estoque{display:inline-flex;align-items:center;gap:.4rem;font-size:.9rem;font-weight:600;margin-top:1rem}
.lj-estoque--ok{color:var(--ok)}.lj-estoque--fim{color:var(--erro)}
.lj-comprar{display:flex;gap:.7rem;flex-wrap:wrap;margin-top:1.4rem}
.lj-comprar .lj-btn{flex:1;min-width:180px}
.lj-qtd{display:inline-flex;align-items:center;border:1.5px solid var(--linha-campo);border-radius:var(--raio-botao);overflow:hidden;height:46px;background:var(--fundo)}
.lj-qtd button{width:42px;height:100%;border:0;background:transparent;cursor:pointer;font-size:1.1rem}
.lj-qtd button:hover{background:var(--quadro)}
.lj-qtd input{width:46px;text-align:center;border:0;background:transparent;-moz-appearance:textfield;height:100%;outline:0;font-weight:600}
.lj-qtd input::-webkit-outer-spin-button,.lj-qtd input::-webkit-inner-spin-button{-webkit-appearance:none}
.lj-bloco{border-top:1px solid var(--linha);margin-top:1.6rem;padding-top:1.3rem}
.lj-bloco h2{font-size:1rem;letter-spacing:0;margin-bottom:.7rem}
.lj-frete{display:flex;gap:.6rem;max-width:340px}
.lj-frete__saida{margin-top:.8rem;font-size:.92rem;display:grid;gap:.45rem}
.lj-frete__saida div{display:flex;justify-content:space-between;gap:1rem}
.lj-frete__saida small{color:var(--suave);display:block}
.lj-descricao{white-space:pre-line;color:color-mix(in oklab,var(--texto) 88%,var(--fundo));max-width:68ch}
.lj-ficha{display:grid;grid-template-columns:auto 1fr;gap:.35rem 1.5rem;font-size:.92rem}
.lj-ficha dt{color:var(--suave)}.lj-ficha dd{margin:0}

/* ── carrinho / fechamento / pedido ── */
.lj-duas{display:grid;gap:clamp(1.5rem,4vw,3rem);padding-block:.5rem clamp(2.5rem,6vw,4rem);align-items:start}
@media (min-width:920px){.lj-duas{grid-template-columns:minmax(0,1fr) 380px}.lj-resumo{position:sticky;top:120px}}
.lj-resumo{background:var(--quadro);border-radius:var(--raio);padding:1.4rem;border:1px solid var(--linha)}
.lj-resumo h2{font-size:1.1rem;margin-bottom:1rem;letter-spacing:0}
.lj-resumo dl{display:grid;grid-template-columns:1fr auto;gap:.5rem 0;margin:0}
.lj-resumo dt{padding-right:1rem}
.lj-resumo dd{margin:0;text-align:right;font-variant-numeric:tabular-nums}
.lj-resumo dt{color:var(--suave)}
.lj-resumo .lj-total{font-weight:700;font-size:1.25rem;color:var(--texto);border-top:1px solid var(--linha-forte);padding-top:.8rem;margin-top:.3rem;font-family:var(--fonte-preco);font-variant-numeric:lining-nums tabular-nums}
.lj-item{display:grid;grid-template-columns:76px minmax(0,1fr) auto;gap:1rem;padding:1.1rem 0;border-bottom:1px solid var(--linha);align-items:center}
.lj-item__foto{width:76px;aspect-ratio:1;border-radius:min(10px,var(--raio));overflow:hidden;background:var(--quadro)}
.lj-item__foto img{width:100%;height:100%;object-fit:cover}
.lj-item__foto .lj-semfoto{font-size:1.8rem}
.lj-item__nome{font-weight:600;text-decoration:none;line-height:1.3}
.lj-item small{color:var(--suave);display:block}
.lj-item__acoes{display:flex;align-items:center;gap:.8rem;margin-top:.5rem;flex-wrap:wrap}
.lj-item__total{font-weight:700;font-variant-numeric:lining-nums tabular-nums;white-space:nowrap;font-family:var(--fonte-preco)}
.lj-tirar{border:0;background:none;color:var(--suave);cursor:pointer;text-decoration:underline;text-underline-offset:3px;font-size:.88rem;padding:.4rem 0}
.lj-tirar:hover{color:var(--erro)}
.lj-item--mini{grid-template-columns:52px minmax(0,1fr) auto;padding:.7rem 0;font-size:.92rem}
.lj-item--mini .lj-item__foto{width:52px}
.lj-aviso{border-radius:min(10px,var(--raio));padding:.85rem 1rem;margin-bottom:1rem;display:flex;gap:.6rem;align-items:flex-start;font-size:.95rem}
.lj-aviso--erro{background:color-mix(in oklab,var(--erro) 10%,var(--fundo));color:color-mix(in oklab,var(--erro) 80%,var(--texto))}
.lj-aviso--ok{background:color-mix(in oklab,var(--ok) 11%,var(--fundo));color:color-mix(in oklab,var(--ok) 78%,var(--texto))}
.lj-aviso--nota{background:var(--quadro);border:1px solid var(--linha)}
.lj-passo{border:0;padding:0;margin:0 0 2rem;min-width:0}
.lj-passo legend{font-family:var(--fonte-titulo);font-size:1.25rem;font-weight:700;padding:0;margin-bottom:1rem;display:flex;gap:.7rem;align-items:center}
.lj-passo legend span{display:grid;place-items:center;width:30px;height:30px;border-radius:999px;background:var(--texto);color:var(--fundo);font-size:.9rem;font-family:var(--fonte-texto)}
.lj-form{display:grid;gap:.9rem 1rem;grid-template-columns:repeat(6,minmax(0,1fr))}
.lj-form > div{grid-column:span 6}
@media (min-width:600px){.lj-form .c2{grid-column:span 2}.lj-form .c3{grid-column:span 3}.lj-form .c4{grid-column:span 4}}
.lj-form label{display:block;font-size:.88rem;font-weight:600;margin-bottom:.3rem}
.lj-form label small{font-weight:400;color:var(--suave);font-size:.8rem}
.lj-erro{color:var(--erro);font-size:.85rem;margin-top:.3rem}
.lj-opcoes{display:grid;gap:.6rem}
@media (min-width:600px){.lj-opcoes--lado{grid-template-columns:1fr 1fr}}
.lj-opcao{position:relative;display:flex;gap:.8rem;align-items:flex-start;border:1.5px solid var(--linha-campo);border-radius:min(12px,var(--raio));padding:.95rem 1rem;cursor:pointer;background:var(--fundo)}
.lj-opcao:has(input:checked){border-color:var(--texto);box-shadow:inset 0 0 0 1px var(--texto);background:var(--quadro)}
.lj-opcao:has(input:focus-visible){outline:2px solid var(--marca-tinta);outline-offset:2px}
.lj-opcao input{margin-top:.2rem;accent-color:var(--texto);width:18px;height:18px;flex:none}
.lj-opcao strong{display:block;line-height:1.25}
.lj-opcao small{color:var(--suave)}
.lj-opcao em{margin-left:auto;font-style:normal;font-weight:700;white-space:nowrap;font-variant-numeric:tabular-nums}
.lj-vazio-grande{text-align:center;padding-block:clamp(3rem,10vw,6rem)}
.lj-vazio-grande i{font-size:3rem;color:var(--suave)}
.lj-vazio-grande h1{font-size:clamp(1.5rem,4vw,2.2rem);margin-top:1rem}
.lj-vazio-grande p{color:var(--suave);margin:.6rem auto 1.6rem;max-width:44ch}
/* pedido */
.lj-etapas{display:grid;grid-template-columns:repeat(4,1fr);gap:0;margin:1.5rem 0 2rem;list-style:none;padding:0;counter-reset:e}
.lj-etapas li{position:relative;text-align:center;font-size:.82rem;color:var(--suave);padding-top:40px}
.lj-etapas li::before{content:"";position:absolute;top:13px;left:-50%;width:100%;height:3px;background:var(--linha);z-index:0}
.lj-etapas li:first-child::before{display:none}
.lj-etapas li::after{content:"";position:absolute;top:4px;left:50%;translate:-50% 0;width:20px;height:20px;border-radius:999px;background:var(--fundo);border:3px solid var(--linha-forte);z-index:1}
.lj-etapas li[data-feito]{color:var(--texto);font-weight:600}
.lj-etapas li[data-feito]::before{background:var(--ok)}
.lj-etapas li[data-feito]::after{background:var(--ok);border-color:var(--ok)}
.lj-etapas li[data-agora]::after{border-color:var(--marca-tinta);box-shadow:0 0 0 5px color-mix(in oklab,var(--marca-tinta) 20%,transparent)}
.lj-etapas li[data-agora]{color:var(--texto);font-weight:600}
.lj-pagar{border:1.5px solid var(--linha-forte);border-radius:var(--raio);padding:clamp(1.1rem,3vw,1.75rem);background:var(--fundo)}
.lj-pagar h2{font-size:1.25rem;letter-spacing:0}
.lj-pagar__valor{font-family:var(--fonte-preco);font-size:clamp(1.9rem,5vw,2.4rem);font-weight:700;line-height:1;margin-top:.6rem;font-variant-numeric:lining-nums tabular-nums}
.lj-pagar--ok{border-color:var(--ok);background:color-mix(in oklab,var(--ok) 7%,var(--fundo))}
.lj-pagar--ok > i{font-size:2rem;color:var(--ok);line-height:1}
.lj-pagar--ok h2{margin-top:.5rem}
@media (max-width:639px){.lj-pix > div:last-child{order:-1}.lj-pix__codigo{flex-direction:column}.lj-pix__codigo .lj-btn{width:100%;min-height:48px}.lj-pix__qr{width:168px}}
.lj-pix{display:grid;gap:1.5rem;margin-top:1.2rem;align-items:center}
@media (min-width:640px){.lj-pix{grid-template-columns:220px 1fr}}
.lj-pix__qr{width:220px;max-width:100%;aspect-ratio:1;background:#fff;border-radius:12px;padding:10px;border:1px solid var(--linha);justify-self:center;display:grid;place-items:center}
.lj-pix__qr img,.lj-pix__qr svg{width:100%;height:100%}
.lj-pix ol{margin:0 0 1rem;padding-left:1.2rem;color:var(--suave);font-size:.93rem}
.lj-pix__codigo{display:flex;gap:.5rem}
.lj-pix__codigo input{flex:1;min-width:0;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.8rem}
.lj-espera{display:flex;align-items:center;gap:.6rem;color:var(--suave);font-size:.92rem;margin-top:1rem}
.lj-espera::before{content:"";width:10px;height:10px;border-radius:999px;background:var(--marca-tinta);animation:lj-pisca 1.4s ease-in-out infinite}
@keyframes lj-pisca{50%{opacity:.25}}
.lj-brinde{position:fixed;left:50%;bottom:20px;translate:-50% 0;background:var(--texto);color:var(--fundo);padding:.8rem 1.1rem;border-radius:var(--raio-botao);
  box-shadow:0 12px 30px -12px rgb(0 0 0/.6);z-index:60;display:flex;gap:.9rem;align-items:center;max-width:calc(100vw - 32px);font-size:.95rem}
.lj-brinde a{color:inherit;font-weight:700;white-space:nowrap}
.lj-brinde[hidden]{display:none}
@media (prefers-reduced-motion:no-preference){.lj-brinde{animation:lj-sobe .35s cubic-bezier(.16,1,.3,1)}@keyframes lj-sobe{from{translate:-50% 24px;opacity:0}}}

/* ── rodapé ── */
.lj-rodape{background:var(--rodape);color:var(--sobre-rodape);margin-top:clamp(2rem,6vw,4rem);font-size:.93rem}
.lj-rodape__grade{display:grid;gap:2rem;padding-block:clamp(2rem,5vw,3.5rem)}
@media (min-width:760px){.lj-rodape__grade{grid-template-columns:1.4fr 1fr 1fr 1fr}}
.lj-rodape h2{font-size:.8rem;text-transform:uppercase;letter-spacing:.12em;margin-bottom:.8rem;font-family:var(--fonte-texto);font-weight:700;
  color:color-mix(in oklab,var(--sobre-rodape) 100%,transparent)}
.lj-rodape strong.lj-marca{color:inherit;display:block;margin-bottom:.6rem}
.lj-rodape ul{list-style:none;margin:0;padding:0;display:grid;gap:.45rem}
.lj-rodape a{text-decoration:none}.lj-rodape a:hover{text-decoration:underline;text-underline-offset:3px}
.lj-rodape__fim{border-top:1px solid color-mix(in oklab,var(--sobre-rodape) 22%,var(--rodape));padding-block:1rem;font-size:.8rem;display:flex;flex-wrap:wrap;justify-content:space-between;gap:.5rem}
.lj-zap{position:fixed;right:18px;bottom:18px;width:54px;height:54px;border-radius:999px;background:#128c4b;color:#fff;display:grid;place-items:center;
  font-size:1.6rem;text-decoration:none;box-shadow:0 10px 22px -10px rgb(0 0 0/.6);z-index:40}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important;scroll-behavior:auto!important}}
</style>
