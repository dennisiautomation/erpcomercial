{{-- Folha de estilo da vitrine, 3ª parte (14/11/2026): redesenho no nível dos melhores temas prontos —
     cabeçalho de loja grande (barra de utilidades, busca com sugestões, menu de categorias, sacola lateral),
     carrossel e grade de banners, cartão de produto com 2ª foto/selos/parcelas, página do produto com zoom,
     abas e barra de compra, lista com filtros. Vem DEPOIS das outras duas folhas e as sobrepõe. --}}
<style>
body.lj-trava{overflow:hidden}
.lj-caixa{max-width:1320px}

/* ── barra de utilidades ── */
.lj-util{background:color-mix(in oklab,var(--sobre-topo) 8%,var(--topo));color:color-mix(in oklab,var(--sobre-topo) 86%,var(--topo));font-size:.8rem}
.lj-util__dentro{display:flex;justify-content:space-between;align-items:center;gap:1rem;min-height:34px}
.lj-util nav{display:flex;gap:1.4rem}
.lj-util a{text-decoration:none;color:inherit}.lj-util a:hover{color:var(--sobre-topo);text-decoration:underline;text-underline-offset:3px}
.lj-util :focus-visible{outline-color:var(--sobre-topo)}
@media (max-width:899px){.lj-util nav{display:none}.lj-util__dentro{justify-content:center;text-align:center}}
[data-cabecalho=compacto] .lj-util,[data-pagina=compra] .lj-util{display:none}

/* ── cabeçalho ── */
.lj-topo{border-bottom:0;box-shadow:0 1px 0 color-mix(in oklab,var(--sobre-topo) 12%,var(--topo));transition:box-shadow .2s}
.lj-topo.lj-topo--rolou{box-shadow:0 8px 24px -16px rgb(0 0 0/.35),0 1px 0 color-mix(in oklab,var(--sobre-topo) 12%,var(--topo))}
.lj-topo__linha,[data-cabecalho=centralizado] .lj-topo__linha{grid-template-columns:auto minmax(0,1fr) auto;gap:clamp(12px,3vw,40px);padding-block:1rem;align-items:center}
.lj-menu-botao{display:none;border:0;background:transparent;color:inherit;cursor:pointer;font-size:1.6rem}
.lj-marca{font-size:clamp(1.25rem,2.4vw,1.7rem)}
.lj-marca img{max-height:48px}
.lj-busca{position:relative;max-width:640px;height:48px;padding:0 .5rem 0 1.1rem;justify-self:center;background:color-mix(in oklab,var(--sobre-topo) 6%,var(--topo));border-width:1.5px}
.lj-busca input{font-size:.98rem}
.lj-busca button[type=submit]{width:38px;height:38px;border-radius:var(--raio-botao);background:var(--marca);color:var(--sobre-marca);place-items:center;font-size:1rem}
.lj-sugestoes{position:absolute;left:0;right:0;top:calc(100% + 8px);background:var(--fundo);color:var(--texto);border:1px solid var(--linha);border-radius:min(14px,var(--raio));
  box-shadow:0 24px 50px -24px rgb(0 0 0/.45);padding:.5rem;z-index:40;max-height:70vh;overflow:auto}
.lj-sugestoes a{display:grid;grid-template-columns:52px minmax(0,1fr) auto;gap:.8rem;align-items:center;padding:.45rem;border-radius:min(10px,var(--raio));text-decoration:none}
.lj-sugestoes a:hover,.lj-sugestoes a[aria-selected=true]{background:var(--quadro)}
.lj-sugestoes img,.lj-sugestoes .lj-semfoto{width:52px;height:52px;object-fit:cover;border-radius:8px;background:var(--quadro);font-size:1.2rem}
.lj-sugestoes b{font-family:var(--fonte-preco);font-variant-numeric:tabular-nums;white-space:nowrap}
.lj-sugestoes s{display:block;font-size:.78rem;color:var(--suave);text-align:right}
.lj-sugestoes p{padding:.8rem;color:var(--suave);font-size:.92rem}
.lj-sugestoes .lj-sugestoes__todos{display:block;text-align:center;font-weight:700;color:var(--marca-tinta);padding:.7rem;border-top:1px solid var(--linha);margin-top:.3rem;border-radius:0}
.lj-acoes{gap:.2rem}
.lj-acao{width:auto;height:48px;padding:0 .7rem;border-radius:var(--raio-botao);display:inline-flex;gap:.55rem;font-size:1.35rem}
.lj-acao > span:not(.lj-conta){font-size:.82rem;font-weight:600;line-height:1}
.lj-sacola .lj-conta{top:4px;right:auto;left:26px}
@media (max-width:1099px){.lj-acao > span:not(.lj-conta){display:none}.lj-acao{width:46px;padding:0;justify-content:center}.lj-sacola .lj-conta{left:auto;right:0}}
.lj-topo__nav{border-top:1px solid color-mix(in oklab,var(--sobre-topo) 10%,var(--topo))}
.lj-nav{position:relative;overflow:visible;padding:0;gap:0;align-items:stretch;min-height:46px;font-size:.92rem}
.lj-nav > a{display:flex;align-items:center;padding:0 .95rem;border-bottom:0;font-weight:500;position:relative}
.lj-nav > a::after{content:"";position:absolute;left:.95rem;right:.95rem;bottom:0;height:2px;background:currentColor;transform:scaleX(0);transform-origin:left;transition:transform .25s cubic-bezier(.16,1,.3,1)}
.lj-nav > a:hover::after,.lj-nav > a[aria-current]::after{transform:scaleX(1)}
.lj-nav a.lj-nav__oferta::before{flex:none}
.lj-mega{display:flex}
.lj-mega__botao{display:inline-flex;align-items:center;gap:.55rem;border:0;background:transparent;color:inherit;font:inherit;font-weight:700;cursor:pointer;padding:0 1rem 0 0;margin-right:.3rem;
  border-right:1px solid color-mix(in oklab,var(--sobre-topo) 14%,var(--topo))}
.lj-mega__botao .bi-chevron-down{font-size:.7rem;transition:rotate .2s}
.lj-mega__botao[aria-expanded=true] .bi-chevron-down{rotate:180deg}
.lj-mega__painel{position:absolute;left:0;top:100%;width:min(900px,92vw);background:var(--fundo);color:var(--texto);border:1px solid var(--linha);border-top:0;
  border-radius:0 0 min(16px,var(--raio)) min(16px,var(--raio));box-shadow:0 30px 60px -30px rgb(0 0 0/.5);padding:1rem;display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.3rem;z-index:35}
.lj-mega__painel a{display:flex;align-items:center;gap:.8rem;padding:.5rem;border-radius:min(10px,var(--raio));text-decoration:none}
.lj-mega__painel a:hover{background:var(--quadro)}
.lj-mega__foto{flex:none;width:46px;height:46px;border-radius:999px;overflow:hidden;background:var(--quadro);display:block}
.lj-mega__foto img{width:100%;height:100%;object-fit:cover}
.lj-mega__painel strong{display:block;font-weight:600;line-height:1.2}.lj-mega__painel small{color:var(--suave);font-size:.78rem}
[data-titulos=maiusculas] .lj-mega__painel{text-transform:none;letter-spacing:0;font-size:.92rem}
[data-cabecalho=centralizado] .lj-topo__linha{grid-template-columns:minmax(0,1fr) auto minmax(0,1fr)}
[data-cabecalho=centralizado] .lj-topo__linha > .lj-marca{grid-column:2;justify-self:center;font-size:clamp(1.4rem,2.8vw,2rem)}
[data-cabecalho=centralizado] .lj-busca{grid-column:1;grid-row:1;max-width:320px;justify-self:start}
[data-cabecalho=centralizado] .lj-acoes{grid-column:3;justify-self:end}
[data-cabecalho=centralizado] .lj-nav{justify-content:center}
[data-cabecalho=centralizado] .lj-mega__botao{border-right:0}
[data-cabecalho=compacto] .lj-topo__linha{padding-block:.7rem}
[data-cabecalho=compacto] .lj-busca{max-width:420px;height:42px;justify-self:end}
[data-cabecalho=compacto] .lj-busca button[type=submit]{background:transparent;color:inherit}
[data-cabecalho=compacto] .lj-topo__nav{border-top:0}
[data-cabecalho=compacto] .lj-nav{min-height:40px;font-size:.88rem}
@media (max-width:899px){
  .lj-menu-botao{display:grid}
  .lj-topo__linha,[data-cabecalho=centralizado] .lj-topo__linha,[data-cabecalho=compacto] .lj-topo__linha{grid-template-columns:auto minmax(0,1fr) auto;row-gap:.6rem;padding-block:.6rem;gap:.4rem}
  .lj-topo__linha > .lj-marca,[data-cabecalho=centralizado] .lj-topo__linha > .lj-marca{grid-column:2;grid-row:1;justify-self:start;font-size:1.2rem}
  .lj-acoes,[data-cabecalho=centralizado] .lj-acoes{grid-column:3;grid-row:1}
  .lj-busca,[data-cabecalho=centralizado] .lj-busca,[data-cabecalho=compacto] .lj-busca{grid-column:1/-1;grid-row:2;max-width:none;justify-self:stretch;height:44px}
  .lj-topo__nav{display:none}
  .lj-topo{position:sticky}
  .lj-topo.lj-topo--rolou .lj-busca{display:none}
}
[data-pagina=compra] .lj-topo__nav,[data-pagina=compra] .lj-menu-botao{display:none}
[data-pagina=compra] .lj-topo__linha{grid-template-columns:minmax(0,1fr) auto}
[data-pagina=compra] .lj-topo__linha > .lj-marca{grid-column:1;justify-self:start}

/* ── gavetas (menu e sacola) ── */
.lj-gaveta{position:fixed;inset:0 0 0 auto;margin:0;height:100dvh;max-height:none;width:min(430px,94vw);max-width:none;border:0;padding:0;background:var(--fundo);color:var(--texto);
  display:none;flex-direction:column;box-shadow:0 0 60px -10px rgb(0 0 0/.5)}
.lj-gaveta[open]{display:flex}
.lj-gaveta--esq{inset:0 auto 0 0;width:min(360px,88vw)}
.lj-gaveta::backdrop{background:rgb(0 0 0/.5)}
.lj-gaveta__topo{display:flex;justify-content:space-between;align-items:center;padding:.9rem 1rem .9rem 1.25rem;border-bottom:1px solid var(--linha);font-size:1.1rem;font-family:var(--fonte-titulo)}
.lj-gaveta__topo .lj-icone{border:0;background:transparent;color:inherit;cursor:pointer}
.lj-gaveta__topo .lj-icone:hover{background:var(--quadro)}
.lj-gaveta__corpo{flex:1;overflow:auto;padding:.5rem 1.25rem}
.lj-gaveta__meta{padding:.8rem 1.25rem;background:var(--quadro);font-size:.9rem}
/* atacado: mínimo de peças (27/11/2026) */
.lj-pecas p{margin:0;font-size:.9rem}
.lj-pecas[data-ok=nao] .lj-meta__barra i{background:var(--oferta)}
aside .lj-pecas{margin-top:1rem;padding:.8rem .9rem;background:var(--quadro);border-radius:min(var(--raio),12px)}
.lj-gaveta__pe{padding:1rem 1.25rem 1.25rem;border-top:1px solid var(--linha);display:grid;gap:.6rem}
.lj-gaveta__total{display:flex;justify-content:space-between;align-items:baseline;font-size:1.05rem}
.lj-gaveta__total strong{font-family:var(--fonte-preco);font-size:1.4rem;font-variant-numeric:tabular-nums}
.lj-gaveta__vazia{text-align:center;padding:3rem 1rem;color:var(--suave)}
.lj-gaveta__vazia i{font-size:2.6rem;display:block;margin-bottom:.6rem}
.lj-gaveta .lj-item{grid-template-columns:68px minmax(0,1fr) auto;padding:.9rem 0;align-items:start}
.lj-gaveta .lj-item__foto{width:68px}
.lj-gaveta .lj-qtd{height:36px}.lj-gaveta .lj-qtd button{width:34px}.lj-gaveta .lj-qtd span{min-width:28px;text-align:center;font-weight:600}
.lj-menu-lateral{display:grid;align-content:start;padding:.5rem .75rem}
.lj-menu-lateral a{display:flex;align-items:center;gap:.8rem;padding:.7rem .5rem;text-decoration:none;border-radius:10px;font-weight:500;min-height:48px}
.lj-menu-lateral a:hover{background:var(--quadro)}
.lj-menu-lateral a small{margin-left:auto;color:var(--suave)}
.lj-menu-lateral .lj-mega__foto{width:36px;height:36px}
.lj-menu-lateral hr{border:0;border-top:1px solid var(--linha);margin:.5rem 0;width:100%}
@media (prefers-reduced-motion:no-preference){
  .lj-gaveta--dir[open]{animation:lj-gaveta-d .32s cubic-bezier(.16,1,.3,1)}
  .lj-gaveta--esq[open]{animation:lj-gaveta-e .32s cubic-bezier(.16,1,.3,1)}
  .lj-gaveta[open]::backdrop{animation:lj-vem .25s ease-out}
  @keyframes lj-gaveta-d{from{transform:translateX(100%)}}@keyframes lj-gaveta-e{from{transform:translateX(-100%)}}
}

/* ── seções e setas ── */
.lj-secao__topo{align-items:center;margin-bottom:1.4rem}
.lj-secao__topo h2{font-size:clamp(1.45rem,2.6vw,2rem)}
.lj-secao__acoes{display:flex;align-items:center;gap:1rem}
.lj-setas{display:flex;gap:.4rem}
.lj-setas button,.lj-carrossel__seta,.lj-galeria__seta{width:42px;height:42px;border-radius:999px;border:1.5px solid var(--linha-forte);background:var(--fundo);color:var(--texto);cursor:pointer;display:grid;place-items:center;font-size:1rem;
  transition:background-color .15s,color .15s,border-color .15s,opacity .15s}
.lj-setas button:hover,.lj-carrossel__seta:hover,.lj-galeria__seta:hover{background:var(--texto);color:var(--fundo);border-color:var(--texto)}
.lj-setas button[disabled]{opacity:.35;cursor:default;background:var(--fundo);color:var(--texto);border-color:var(--linha-forte)}
[data-botao=reto] .lj-setas button{border-radius:0}
@media (max-width:699px){.lj-setas{display:none}}

/* ── carrossel de banners ── */
.lj-carrossel{position:relative;--alt:480px}
.lj-carrossel[data-altura=alta]{--alt:600px}.lj-carrossel[data-altura=baixa]{--alt:340px}
.lj-carrossel__trilho{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;overscroll-behavior-x:contain}
.lj-carrossel__trilho::-webkit-scrollbar{display:none}
.lj-slide{position:relative;flex:0 0 100%;scroll-snap-align:start;min-height:var(--alt);display:flex;align-items:center;overflow:hidden;background:var(--marca);color:var(--sobre-marca)}
.lj-slide[data-tom=cor][data-n="1"]{background:var(--texto);color:var(--fundo)}
.lj-slide[data-tom=cor][data-n="2"]{background:var(--oferta);color:var(--sobre-oferta)}
.lj-slide__img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.lj-slide--foto[data-tom=claro]{color:#fff;background:#111}
.lj-slide--foto[data-tom=claro]::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgb(0 0 0/.74),rgb(0 0 0/.5) 40%,rgb(0 0 0/.08) 75%)}
.lj-slide--foto[data-tom=claro][data-lado=direita]::after{background:linear-gradient(270deg,rgb(0 0 0/.74),rgb(0 0 0/.5) 40%,rgb(0 0 0/.08) 75%)}
.lj-slide--foto[data-tom=claro][data-lado=centro]::after{background:rgb(0 0 0/.46)}
.lj-slide--foto[data-tom=escuro]{color:#111;background:#f2f2f2}
.lj-slide--foto[data-tom=escuro]::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgb(255 255 255/.94),rgb(255 255 255/.8) 38%,rgb(255 255 255/.2) 68%,rgb(255 255 255/0))}
.lj-slide--foto[data-tom=escuro][data-lado=direita]::after{background:linear-gradient(270deg,rgb(255 255 255/.94),rgb(255 255 255/.8) 38%,rgb(255 255 255/.2) 68%,rgb(255 255 255/0))}
.lj-slide--foto[data-tom=escuro][data-lado=centro]::after{background:rgb(255 255 255/.6)}
.lj-slide__dentro{position:relative;z-index:1;display:grid;gap:2rem;align-items:center;padding-block:clamp(2rem,5vw,3.5rem)}
@media (min-width:860px){.lj-slide:not(.lj-slide--foto) .lj-slide__dentro{grid-template-columns:1.1fr .9fr}}
.lj-slide__texto{max-width:36rem}
.lj-slide[data-lado=centro] .lj-slide__texto{margin-inline:auto;text-align:center}
.lj-slide[data-lado=direita].lj-slide--foto .lj-slide__texto{margin-left:auto}
.lj-slide h1,.lj-slide h2{font-size:clamp(2.1rem,5.4vw,4rem);font-weight:800;letter-spacing:-.035em;line-height:1.02}
[data-titulos=maiusculas] .lj-slide h1,[data-titulos=maiusculas] .lj-slide h2{font-weight:500;text-transform:uppercase;letter-spacing:-.01em}
.lj-slide p{font-size:clamp(1rem,1.6vw,1.2rem);margin-top:1rem;max-width:44ch;opacity:.94}
.lj-slide[data-lado=centro] p{margin-inline:auto}
.lj-slide .lj-btn{margin-top:1.6rem;background:currentColor;border-color:currentColor;min-height:52px;padding-inline:1.7rem}
.lj-slide .lj-btn > *,.lj-slide .lj-btn{-webkit-text-fill-color:initial}
.lj-slide[data-tom=cor][data-n="0"] .lj-btn{color:var(--sobre-marca);background:var(--sobre-marca)}
.lj-slide .lj-btn{color:inherit}
.lj-slide[data-tom=cor][data-n="0"] .lj-btn{background:var(--sobre-marca);border-color:var(--sobre-marca);color:var(--marca)}
.lj-slide[data-tom=cor][data-n="1"] .lj-btn{background:var(--fundo);border-color:var(--fundo);color:var(--texto)}
.lj-slide[data-tom=cor][data-n="2"] .lj-btn{background:var(--sobre-oferta);border-color:var(--sobre-oferta);color:var(--oferta)}
.lj-slide--foto[data-tom=claro] .lj-btn{background:#fff;border-color:#fff;color:#111}
.lj-slide--foto[data-tom=escuro] .lj-btn{background:#111;border-color:#111;color:#fff}
.lj-slide__produto{position:relative;justify-self:end;width:min(100%,430px);aspect-ratio:1;border-radius:calc(var(--raio) + 6px);overflow:hidden;background:#fff;text-decoration:none;color:#111;box-shadow:0 30px 60px -30px rgb(0 0 0/.55)}
.lj-slide__produto img{width:100%;height:100%;object-fit:cover;transition:transform .6s cubic-bezier(.16,1,.3,1)}
.lj-slide__produto:hover img{transform:scale(1.04)}
.lj-slide__produto span{position:absolute;left:14px;right:14px;bottom:14px;background:rgb(255 255 255/.94);border-radius:var(--raio-botao);padding:.55rem .9rem;display:flex;justify-content:space-between;gap:.8rem;align-items:center;font-size:.9rem}
.lj-slide__produto small{overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
.lj-slide__produto b{font-family:var(--fonte-preco);font-variant-numeric:tabular-nums;white-space:nowrap}
@media (max-width:859px){.lj-carrossel{--alt:420px}.lj-carrossel[data-altura=alta]{--alt:520px}.lj-carrossel[data-altura=baixa]{--alt:300px}.lj-slide__produto{justify-self:start;width:min(70%,260px)}}
.lj-carrossel__seta{position:absolute;top:50%;translate:0 -50%;left:max(12px,calc((100vw - 1320px)/2 - 60px));z-index:3;width:46px;height:46px;border:0;background:rgb(255 255 255/.92);color:#111;box-shadow:0 6px 18px -8px rgb(0 0 0/.5)}
.lj-carrossel__seta[data-d="1"]{left:auto;right:max(12px,calc((100vw - 1320px)/2 - 60px))}
@media (max-width:859px){.lj-carrossel__seta{display:none}}
.lj-carrossel__pontos{position:absolute;left:0;right:0;bottom:16px;display:flex;justify-content:center;gap:8px;z-index:3}
.lj-carrossel__pontos button{width:28px;height:28px;border:0;background:transparent;cursor:pointer;padding:0;display:grid;place-items:center}
.lj-carrossel__pontos button::before{content:"";width:9px;height:9px;border-radius:99px;background:currentColor;opacity:.4;transition:width .3s cubic-bezier(.16,1,.3,1),opacity .2s}
.lj-carrossel__pontos button[aria-current=true]::before{width:26px;opacity:1}
.lj-carrossel__pontos{color:#fff;mix-blend-mode:difference}

/* ── grade de banners ── */
.lj-banners{display:grid;gap:clamp(10px,1.6vw,20px);grid-template-columns:1fr}
@media (min-width:700px){.lj-banners[data-arranjo=iguais]{grid-template-columns:repeat(var(--cols,2),1fr)}.lj-banners[data-n="3"]{--cols:3}.lj-banners[data-n="4"]{--cols:4}
  .lj-banners[data-arranjo=destaque]{grid-template-columns:1.5fr 1fr;grid-auto-rows:1fr}.lj-banners[data-arranjo=destaque] > :first-child{grid-row:span 2}}
.lj-bannerzinho{position:relative;display:flex;align-items:flex-end;min-height:clamp(180px,22vw,280px);border-radius:var(--raio);overflow:hidden;text-decoration:none;padding:clamp(1rem,2vw,1.6rem);background:var(--quadro);color:var(--texto);isolation:isolate}
.lj-bannerzinho[data-n="1"]:not(.lj-bannerzinho--foto){background:color-mix(in oklab,var(--marca) 14%,var(--fundo))}
.lj-bannerzinho[data-n="2"]:not(.lj-bannerzinho--foto){background:color-mix(in oklab,var(--oferta) 16%,var(--fundo))}
.lj-banners[data-arranjo=destaque] > :first-child{min-height:clamp(280px,36vw,520px)}
.lj-bannerzinho > img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-2;transition:transform .7s cubic-bezier(.16,1,.3,1)}
.lj-bannerzinho > img.lj-bannerzinho__produto{inset:auto 0 0 auto;width:62%;height:100%;object-fit:cover;mix-blend-mode:multiply;mask-image:linear-gradient(90deg,transparent,#000 35%);-webkit-mask-image:linear-gradient(90deg,transparent,#000 35%)}
.lj-bannerzinho:hover > img{transform:scale(1.045)}
.lj-bannerzinho--foto{color:#fff}
.lj-bannerzinho--foto::after{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(0deg,rgb(0 0 0/.72),rgb(0 0 0/.12) 60%,rgb(0 0 0/0))}
.lj-bannerzinho__texto{display:grid;gap:.15rem;max-width:70%}
.lj-bannerzinho--foto .lj-bannerzinho__texto{max-width:none}
.lj-bannerzinho strong{font-family:var(--fonte-titulo);font-size:clamp(1.3rem,2.6vw,2rem);line-height:1.05;letter-spacing:-.02em}
[data-titulos=maiusculas] .lj-bannerzinho strong{text-transform:uppercase;font-weight:500;letter-spacing:.02em}
.lj-bannerzinho small{font-size:.95rem;opacity:.9}
.lj-bannerzinho em{font-style:normal;font-weight:700;font-size:.88rem;margin-top:.5rem;display:inline-flex;gap:.4rem;align-items:center}
.lj-bannerzinho em i{transition:translate .25s cubic-bezier(.16,1,.3,1)}.lj-bannerzinho:hover em i{translate:4px 0}

/* ── categorias em círculos ── */
.lj-circulos{display:flex;gap:clamp(14px,2.4vw,34px);overflow-x:auto;scrollbar-width:none;padding:4px 2px 8px;scroll-snap-type:x proximity}
.lj-circulos::-webkit-scrollbar{display:none}
.lj-circulos a{flex:none;display:grid;justify-items:center;gap:.7rem;text-decoration:none;width:clamp(86px,11vw,132px);text-align:center;scroll-snap-align:start}
.lj-circulo{display:block;width:100%;aspect-ratio:1;border-radius:999px;overflow:hidden;background:var(--quadro);outline:2px solid transparent;outline-offset:3px;transition:outline-color .2s}
.lj-circulo img{width:100%;height:100%;object-fit:cover;transition:transform .5s cubic-bezier(.16,1,.3,1)}
.lj-circulo b{display:grid;place-items:center;height:100%;font-family:var(--fonte-titulo);font-size:2rem;color:var(--suave)}
.lj-circulos a:hover .lj-circulo{outline-color:var(--marca-tinta)}.lj-circulos a:hover img{transform:scale(1.06)}
.lj-circulos strong{font-size:.92rem;font-weight:600;line-height:1.2}

/* ── galeria de fotos ── */
.lj-galeria-fotos{display:grid;grid-template-columns:repeat(2,1fr);gap:clamp(6px,1vw,12px)}
@media (min-width:700px){.lj-galeria-fotos{grid-template-columns:repeat(4,1fr)}}
.lj-galeria-fotos > *{display:block;aspect-ratio:1;overflow:hidden;border-radius:min(12px,var(--raio));background:var(--quadro)}
.lj-galeria-fotos img{width:100%;height:100%;object-fit:cover;transition:transform .6s cubic-bezier(.16,1,.3,1)}
.lj-galeria-fotos a:hover img{transform:scale(1.05)}

/* ── cartão de produto ── */
.lj-card__topo{position:relative}
.lj-card__foto{background:#f6f6f4}
.lj-card__foto2{position:absolute;inset:0;opacity:0;transition:opacity .35s ease}
@media (hover:hover){.lj-card--duas:hover .lj-card__foto2{opacity:1}.lj-card--duas:hover .lj-card__foto img:first-child{transform:none}}
.lj-card--fim .lj-card__foto img{opacity:.55;filter:grayscale(.3)}
.lj-selos{position:absolute;top:10px;left:10px;display:flex;flex-direction:column;align-items:flex-start;gap:5px;z-index:2;pointer-events:none}
.lj-selos .lj-selo{position:static;font-size:.72rem;padding:.26rem .55rem;text-transform:uppercase;letter-spacing:.04em}
.lj-selo--novo{background:var(--texto);color:var(--fundo)}
.lj-selo--frete{background:var(--fundo);color:var(--ok);box-shadow:inset 0 0 0 1.5px var(--ok)}
.lj-card__add{position:absolute;left:10px;right:10px;bottom:10px;z-index:2}
.lj-mais{width:100%;height:44px;border-radius:var(--raio-botao);border:0;background:var(--fundo);color:var(--texto);font-weight:700;font-size:.9rem;display:flex;align-items:center;justify-content:center;gap:.5rem;
  box-shadow:0 8px 20px -10px rgb(0 0 0/.45);transition:background-color .15s,color .15s,opacity .2s,transform .25s cubic-bezier(.16,1,.3,1)}
.lj-mais:hover{background:var(--marca);color:var(--sobre-marca);border-color:var(--marca)}
@media (hover:hover) and (min-width:700px){.lj-card .lj-mais{opacity:0;transform:translateY(8px)}.lj-card:hover .lj-mais,.lj-card:focus-within .lj-mais{opacity:1;transform:none}}
@media (hover:none),(max-width:699px){.lj-card__add{left:auto;right:8px;bottom:8px}.lj-mais{width:42px;height:42px;border-radius:999px}.lj-mais span{display:none}}
.lj-card__corpo{padding-top:.85rem;gap:.25rem}
.lj-card__nome{font-weight:500;font-size:.98rem}
.lj-card__pe{padding-top:.35rem}
.lj-preco b{font-size:1.18rem}
.lj-preco small{color:var(--suave);font-size:.8rem;margin-top:.15rem}
[data-cartao=borda] .lj-card{padding:0;overflow:hidden;transition:border-color .2s,box-shadow .25s}
[data-cartao=borda] .lj-card:hover{border-color:var(--linha-forte);box-shadow:var(--sombra)}
[data-cartao=borda] .lj-card__foto{border-radius:0}
[data-cartao=borda] .lj-card__corpo{padding:.9rem 1rem 1rem}
[data-cartao=lista] .lj-card__topo{position:static;flex:none;width:76px}
[data-cartao=lista] .lj-selos{display:none}
[data-cartao=lista] .lj-card__add{position:absolute;left:auto;right:0;top:50%;bottom:auto;translate:0 -50%}
[data-cartao=lista] .lj-mais{width:40px;height:40px;border-radius:999px;opacity:1!important;transform:none!important;box-shadow:none;border:1.5px solid var(--linha-forte)}
[data-cartao=lista] .lj-mais span{display:none}
[data-cartao=lista] .lj-card__pe{padding-right:52px}
/* grade em carrossel */
.lj-grade--carrossel{display:grid;grid-auto-flow:column;grid-template-columns:none!important;grid-auto-columns:calc((100% - (var(--colunas,4) - 1)*clamp(10px,2vw,22px))/var(--colunas,4));overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;padding-bottom:6px;overscroll-behavior-x:contain}
.lj-grade--carrossel::-webkit-scrollbar{display:none}
.lj-grade--carrossel > *{scroll-snap-align:start}
@media (max-width:999px){.lj-grade--carrossel{grid-auto-columns:30%}}
@media (max-width:699px){.lj-grade--carrossel{grid-auto-columns:46%}}
@media (min-width:700px) and (max-width:999px){.lj-grade:not(.lj-grade--carrossel){grid-template-columns:repeat(3,minmax(0,1fr))}}

/* ── vantagens: ícone maior ── */
.lj-vantagens i{font-size:1.7rem}
.lj-vantagens > div{align-items:center}
@media (max-width:859px){.lj-slide--foto{align-items:flex-end}
  .lj-slide--foto[data-tom=claro]::after,.lj-slide--foto[data-tom=claro][data-lado=direita]::after{background:linear-gradient(0deg,rgb(0 0 0/.84),rgb(0 0 0/.55) 45%,rgb(0 0 0/.05) 80%)}
  .lj-slide--foto[data-tom=escuro]::after,.lj-slide--foto[data-tom=escuro][data-lado=direita]::after{background:linear-gradient(0deg,rgb(255 255 255/.97),rgb(255 255 255/.92) 58%,rgb(255 255 255/.15) 88%)}
  .lj-slide--foto .lj-slide__dentro{padding-bottom:3.2rem}}
@media (max-width:759px){.lj-vantagens{grid-template-columns:1fr 1fr;column-gap:1rem}
  .lj-vantagens > div{flex-direction:column;align-items:flex-start;gap:.45rem;padding:1rem 0}
  .lj-vantagens > div + div{border-top:0}.lj-vantagens > div:nth-child(n+3){border-top:1px solid var(--linha)}
  .lj-vantagens span{font-size:.85rem}}

/* ── página do produto ── */
.lj-produto{gap:clamp(1.5rem,4vw,4.5rem)}
@media (min-width:880px){.lj-produto{grid-template-columns:1.15fr .85fr}}
.lj-galeria__moldura{position:relative;min-width:0}
.lj-galeria__palco{background:#f6f6f4}
.lj-zoom{flex:0 0 100%;border:0;padding:0;background:transparent;cursor:zoom-in;overflow:hidden;display:block;width:100%;height:100%}
.lj-zoom img{transition:transform .25s ease-out}
.lj-zoom--ativo img{transform:scale(2)}
.lj-galeria__seta{position:absolute;top:50%;translate:0 -50%;left:12px;z-index:2;border:0;background:rgb(255 255 255/.92);color:#111;box-shadow:0 6px 16px -8px rgb(0 0 0/.5)}
.lj-galeria__seta[data-lj-galeria="1"],.lj-galeria__seta[data-lj-luz-ir="1"]{left:auto;right:12px}
.lj-galeria__conta{position:absolute;right:12px;bottom:12px;background:rgb(17 17 17/.72);color:#fff;font-size:.78rem;padding:.2rem .55rem;border-radius:99px;font-variant-numeric:tabular-nums;z-index:2}
.lj-miniaturas button{background:#f6f6f4;width:72px}
@media (min-width:880px){.lj-galeria--varias{grid-template-columns:84px 1fr}.lj-miniaturas button{width:100%}}
.lj-produto__cat{font-size:.78rem;text-transform:uppercase;letter-spacing:.1em;color:var(--suave);text-decoration:none;font-weight:600}
.lj-produto__cat:hover{color:var(--texto)}
.lj-produto h1{font-size:clamp(1.7rem,3.4vw,2.5rem);margin-top:.4rem}
.lj-compra{margin-top:1.3rem;border:1px solid var(--linha);border-radius:var(--raio);padding:clamp(1rem,2.4vw,1.5rem);background:var(--fundo)}
.lj-produto__preco{margin-top:0;align-items:baseline}
.lj-produto__preco b{font-size:clamp(2.1rem,4.6vw,2.9rem)}
.lj-preco--promo b,.lj-produto__preco s + b{color:var(--oferta-tinta)}
.lj-economia{font-size:.82rem;font-weight:700;color:var(--ok);background:color-mix(in oklab,var(--ok) 11%,var(--fundo));padding:.25rem .6rem;border-radius:99px}
.lj-formas{list-style:none;margin:.9rem 0 0;padding:0;display:grid;gap:.4rem;font-size:.94rem}
.lj-formas li{display:flex;gap:.6rem;align-items:flex-start}.lj-formas i{color:var(--marca-tinta);margin-top:.1rem}
.lj-estoque{margin-top:1.1rem}
.lj-estoque--pouco{color:var(--oferta-tinta)}
.lj-comprar{margin-top:1rem;flex-wrap:nowrap}
.lj-btn--grande{min-height:54px;font-size:1.05rem;flex:1}
.lj-qtd{height:54px}
.lj-compra > .lj-btn--cheio{margin-top:.6rem;min-height:50px}
.lj-bloco h2{display:flex;gap:.5rem;align-items:center}
.lj-garantias{list-style:none;margin:1.5rem 0 0;padding:1.1rem 0 0;border-top:1px solid var(--linha);display:grid;gap:.75rem;font-size:.93rem}
.lj-garantias li{display:flex;gap:.75rem;align-items:flex-start}
.lj-garantias i{font-size:1.25rem;color:var(--marca-tinta);line-height:1.2}
.lj-garantias a{color:inherit}
.lj-partilha{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1.2rem}
.lj-abas{margin-top:clamp(2rem,5vw,3.5rem)}
.lj-abas__lista{display:flex;gap:clamp(1rem,3vw,2.5rem);border-bottom:1px solid var(--linha-forte);overflow-x:auto;scrollbar-width:none}
.lj-abas__lista button{border:0;background:transparent;color:var(--suave);font:inherit;font-weight:600;font-size:1.02rem;padding:.9rem 0;cursor:pointer;border-bottom:2.5px solid transparent;margin-bottom:-1px;white-space:nowrap}
.lj-abas__lista button[aria-selected=true]{color:var(--texto);border-color:var(--texto)}
.lj-abas__painel{padding-top:1.5rem;max-width:78ch}
.lj-ficha{gap:.7rem 2.5rem}
.lj-barra-compra{position:fixed;left:0;right:0;bottom:0;z-index:45;background:var(--fundo);border-top:1px solid var(--linha-forte);box-shadow:0 -10px 26px -16px rgb(0 0 0/.4);padding:.6rem 1rem;display:flex;gap:.8rem;align-items:center;justify-content:space-between}
.lj-barra-compra small{display:block;color:var(--suave);font-size:.78rem}
.lj-barra-compra b{font-family:var(--fonte-preco);font-size:1.15rem;font-variant-numeric:tabular-nums}
@media (min-width:880px){.lj-barra-compra{display:none}}
body:has(.lj-barra-compra:not([hidden])) .lj-zap{bottom:84px}
.lj-luz{border:0;padding:0;width:100vw;height:100dvh;max-width:none;max-height:none;background:rgb(12 12 12/.96);display:none;place-items:center}
.lj-luz[open]{display:grid}
.lj-luz img{max-width:min(94vw,1300px);max-height:92dvh;object-fit:contain;border-radius:6px;background:#f6f6f4}
.lj-luz__fechar{position:absolute;top:14px;right:14px;border:0;background:rgb(255 255 255/.14);color:#fff;cursor:pointer;z-index:3}

/* ── lista com filtros ── */
.lj-filtros{display:flex;gap:.6rem;align-items:center}
.lj-filtros__abrir{display:none}
.lj-chips{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin-bottom:1.2rem}
.lj-chips a{display:inline-flex;align-items:center;gap:.3rem;text-decoration:none;background:var(--quadro);border:1px solid var(--linha);border-radius:99px;padding:.35rem .5rem .35rem .85rem;font-size:.88rem;font-weight:600;min-height:36px}
.lj-chips a:hover{border-color:var(--texto)}
.lj-chips .lj-chips__limpar{background:none;border:0;text-decoration:underline;text-underline-offset:3px;font-weight:500;color:var(--suave);padding-inline:.4rem}
.lj-lista{display:grid;gap:clamp(1.5rem,3vw,3rem);align-items:start}
@media (min-width:960px){.lj-lista{grid-template-columns:250px minmax(0,1fr)}.lj-lateral{position:sticky;top:150px}}
.lj-lateral__topo{display:none}
.lj-lateral__grupo{padding-bottom:1.3rem;margin-bottom:1.3rem;border-bottom:1px solid var(--linha)}
.lj-lateral__grupo:last-child{border-bottom:0}
.lj-lateral h2{font-size:.82rem;text-transform:uppercase;letter-spacing:.1em;font-family:var(--fonte-texto);font-weight:700;margin-bottom:.7rem}
.lj-lateral ul{list-style:none;margin:0;padding:0;display:grid;gap:.1rem}
.lj-lateral ul a{display:flex;align-items:center;gap:.55rem;text-decoration:none;padding:.42rem 0;color:color-mix(in oklab,var(--texto) 82%,var(--fundo));min-height:36px}
.lj-lateral ul a:hover{color:var(--texto)}
.lj-lateral ul a small{margin-left:auto;color:var(--suave);font-variant-numeric:tabular-nums}
.lj-lateral ul a[aria-current]{color:var(--texto);font-weight:700}
.lj-caixinha{width:18px;height:18px;border:1.5px solid var(--linha-campo);border-radius:4px;flex:none;display:grid;place-items:center}
a[aria-current] > .lj-caixinha{background:var(--texto);border-color:var(--texto)}
a[aria-current] > .lj-caixinha::after{content:"";width:9px;height:5px;border:2px solid var(--fundo);border-top:0;border-right:0;rotate:-45deg;translate:0 -1px}
.lj-faixa-preco{display:grid;grid-template-columns:1fr 1fr;gap:.5rem}
.lj-faixa-preco .lj-campo{min-height:42px;padding:.5rem .65rem;font-size:.9rem}
.lj-faixa-preco .lj-btn{grid-column:1/-1}
@media (max-width:959px){
  .lj-filtros__abrir{display:inline-flex}
  .lj-lateral{position:fixed;inset:0 auto 0 0;width:min(340px,88vw);background:var(--fundo);z-index:80;padding:0 1.25rem 1.5rem;overflow:auto;transform:translateX(-102%);transition:transform .32s cubic-bezier(.16,1,.3,1);box-shadow:0 0 60px -10px rgb(0 0 0/.5)}
  .lj-lateral.aberta{transform:none}
  .lj-lateral__topo{display:flex;justify-content:space-between;align-items:center;padding:.8rem 0;margin-bottom:1rem;border-bottom:1px solid var(--linha);font-family:var(--fonte-titulo);font-size:1.1rem;position:sticky;top:0;background:var(--fundo)}
  .lj-lateral__topo .lj-icone{border:0;background:transparent;color:inherit;cursor:pointer}
}

/* ── rodapé ── */
.lj-rodape__grade{gap:2.2rem 2rem}
@media (min-width:760px){.lj-rodape__grade{grid-template-columns:1.5fr 1fr 1fr 1.2fr}}
.lj-rodape__marca p{max-width:42ch;margin-bottom:.4rem}
.lj-rodape__marca .lj-marca img{max-height:44px}
.lj-rodape li{display:flex;gap:.55rem;align-items:flex-start}
.lj-rodape li > i,.lj-rodape li a > i{margin-top:.15rem}
.lj-rodape__selos{display:flex;flex-wrap:wrap;gap:1.5rem 4rem;padding-block:1.4rem;border-top:1px solid color-mix(in oklab,var(--sobre-rodape) 22%,var(--rodape))}
.lj-pagamentos{display:flex!important;flex-wrap:wrap;gap:.5rem!important}
.lj-pagamentos li{border:1px solid color-mix(in oklab,var(--sobre-rodape) 32%,var(--rodape));border-radius:8px;padding:.4rem .75rem;font-size:.85rem;align-items:center}
</style>
