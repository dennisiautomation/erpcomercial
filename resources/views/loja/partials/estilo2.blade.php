{{-- Folha de estilo da vitrine, 2ª parte (14/11/2026): promoções, popup, cookies, redes, blocos novos e MOVIMENTO.
     Tese do movimento: (1) momento autoral = o mosaico do topo se revela e a foto do produto "viaja" — do cartão para a
     página do produto (view transition) e da página para a sacola (ao adicionar); (2) continuidade = troca de página
     com esmaecer curto; (3) resposta = botões, contador, cupom copiado, relógio; (4) listas entram como listas, uma vez.
     data-movimento=suave tira (1) e (4); =nenhum tira tudo; prefers-reduced-motion também. O conteúdo é visível sem JS. --}}
<style>
/* ── navegação: ofertas ── */
.lj-nav a.lj-nav__oferta{color:var(--sobre-topo);font-weight:700}
.lj-nav a.lj-nav__oferta::before{content:"";display:inline-block;width:.5em;height:.5em;border-radius:99px;background:var(--oferta);margin-right:.45em;vertical-align:.08em}

/* ── faixa correndo (letreiro) ── */
.lj-faixa{overflow:hidden}
.lj-faixa__trilho{display:inline-flex;gap:3rem;white-space:nowrap}
.lj-faixa__trilho > span + span{display:none}
@media (prefers-reduced-motion:no-preference){
  [data-faixa=correndo]:not([data-movimento=nenhum]) .lj-faixa{text-align:left;padding-inline:0}
  [data-faixa=correndo]:not([data-movimento=nenhum]) .lj-faixa__trilho{animation:lj-corre 28s linear infinite;padding-left:3rem}
  [data-faixa=correndo]:not([data-movimento=nenhum]) .lj-faixa__trilho > span + span{display:inline}
  [data-faixa=correndo] .lj-faixa:hover .lj-faixa__trilho{animation-play-state:paused}
  @keyframes lj-corre{to{transform:translateX(-50%)}}
}

/* ── redes sociais ── */
.lj-redes{list-style:none;display:flex;flex-wrap:wrap;gap:.5rem;margin:1rem 0 0;padding:0}
.lj-redes a{display:inline-flex;align-items:center;gap:.5rem;min-width:44px;height:44px;justify-content:center;border-radius:var(--raio-botao);
  border:1.5px solid color-mix(in oklab,currentColor 35%,transparent);text-decoration:none!important;font-size:1.15rem;padding-inline:.7rem;
  transition:background-color .15s,color .15s,border-color .15s}
.lj-redes a:hover{background:currentColor}
.lj-redes a:hover i,.lj-redes a:hover span{color:var(--rodape)}
.lj-redes--grandes{justify-content:center}
.lj-redes--grandes a{font-size:1rem;font-weight:600;padding-inline:1.1rem;border-color:var(--linha-campo)}
.lj-redes--grandes a:hover{background:var(--texto);border-color:var(--texto)}
.lj-redes--grandes a:hover i,.lj-redes--grandes a:hover span{color:var(--fundo)}
.lj-rodape__politicas{display:flex;flex-wrap:wrap;gap:.4rem 1.2rem}
.lj-rodape__politicas a,.lj-rodape__politicas button{color:inherit;background:none;border:0;padding:0;font:inherit;cursor:pointer;text-decoration:underline;text-underline-offset:3px}

/* ── WhatsApp flutuante ── */
.lj-zap{transition:transform .2s cubic-bezier(.16,1,.3,1),box-shadow .2s}
.lj-zap:hover{transform:translateY(-2px) scale(1.04)}
.lj-zap span{position:absolute;right:calc(100% + 10px);top:50%;translate:0 -50%;background:var(--texto);color:var(--fundo);font-size:.82rem;font-weight:600;
  white-space:nowrap;padding:.4rem .7rem;border-radius:var(--raio-botao);opacity:0;pointer-events:none;transition:opacity .15s}
.lj-zap:hover span,.lj-zap:focus-visible span{opacity:1}
[data-pagina=compra] .lj-zap{display:none}
body:has(.lj-cookies:not([hidden])) .lj-zap{bottom:calc(18px + var(--alt-cookies,0px))}

/* ── cupom / frete grátis no carrinho ── */
.lj-cupom{display:inline-flex;align-items:center;gap:.4rem;border:1.5px dashed currentColor;background:transparent;color:inherit;font:inherit;font-weight:700;
  letter-spacing:.06em;padding:.25rem .6rem;border-radius:6px;cursor:pointer;font-variant-numeric:tabular-nums}
.lj-cupom-form{display:flex;gap:.5rem;margin-top:1rem}
.lj-cupom-form .lj-campo{text-transform:uppercase;min-height:42px;padding-block:.5rem}
.lj-cupom-aplicado{display:flex;justify-content:space-between;align-items:center;gap:.75rem;margin-top:1rem;padding:.6rem .75rem;border-radius:min(10px,var(--raio));
  background:color-mix(in oklab,var(--ok) 10%,var(--fundo));font-size:.92rem}
.lj-cupom-aplicado button{border:0;background:none;color:inherit;text-decoration:underline;cursor:pointer;font:inherit;font-size:.85rem;padding:.3rem 0}
.lj-meta{margin-top:1rem;font-size:.9rem}
.lj-meta__barra{height:6px;border-radius:99px;background:var(--linha);overflow:hidden;margin-top:.45rem}
.lj-meta__barra i{display:block;height:100%;width:var(--p,0%);background:var(--ok);border-radius:inherit;transition:width .6s cubic-bezier(.16,1,.3,1)}
.lj-desconto{color:var(--ok)}
.lj-aceite{display:flex;gap:.55rem;align-items:flex-start;font-size:.88rem;color:var(--suave);margin-top:.8rem;cursor:pointer}
.lj-aceite input{margin-top:.15rem;width:18px;height:18px;accent-color:var(--texto);flex:none}
.lj-aceite a{color:inherit}

/* ── bloco: oferta com contagem ── */
.lj-oferta{display:grid;gap:1.5rem 3rem;align-items:center;background:var(--oferta);color:var(--sobre-oferta);border-radius:var(--raio);padding:clamp(1.4rem,4vw,2.6rem)}
@media (min-width:820px){.lj-oferta{grid-template-columns:1fr auto}}
.lj-oferta h2{font-size:clamp(1.6rem,4vw,2.6rem)}
.lj-oferta p{margin-top:.5rem;max-width:52ch}
.lj-oferta__cupom{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;font-weight:600}
.lj-oferta__lado{display:flex;flex-direction:column;gap:1rem;align-items:flex-start}
@media (min-width:820px){.lj-oferta__lado{align-items:flex-end}}
.lj-relogio{display:flex;gap:.5rem}
.lj-relogio span{display:grid;place-items:center;min-width:62px;padding:.55rem .4rem;border-radius:min(10px,var(--raio));background:var(--sobre-oferta);color:var(--oferta)}
.lj-relogio b{font-family:var(--fonte-preco);font-size:1.7rem;line-height:1;font-variant-numeric:tabular-nums;font-weight:700}
.lj-relogio small{font-size:.68rem;text-transform:uppercase;letter-spacing:.08em;margin-top:.25rem}
.lj-btn--inverso-oferta{background:var(--sobre-oferta);color:var(--oferta);border-color:var(--sobre-oferta)}
.lj-oferta :focus-visible{outline-color:var(--sobre-oferta)}

/* ── bloco: depoimentos (falas, não cartões) ── */
.lj-falas{display:grid;gap:2rem clamp(1.5rem,4vw,3.5rem)}
@media (min-width:760px){.lj-falas{grid-template-columns:repeat(var(--n,3),1fr)}}
.lj-falas figure{margin:0;border-top:2px solid var(--texto);padding-top:1.1rem}
.lj-falas blockquote{margin:0;font-family:var(--fonte-titulo);font-size:clamp(1.05rem,1.8vw,1.3rem);line-height:1.4;text-wrap:pretty}
.lj-falas blockquote::before{content:"“"}.lj-falas blockquote::after{content:"”"}
.lj-falas figcaption{margin-top:.9rem;font-size:.9rem}
.lj-falas figcaption span{color:var(--suave)}.lj-falas figcaption span::before{content:" · "}

/* ── bloco: perguntas ── */
.lj-faq{display:grid;gap:1rem clamp(2rem,5vw,4rem)}
@media (min-width:860px){.lj-faq{grid-template-columns:.8fr 1.2fr}}
.lj-faq h2{font-size:clamp(1.5rem,3.2vw,2.2rem)}
.lj-faq details{border-bottom:1px solid var(--linha-forte)}
.lj-faq details:first-child{border-top:1px solid var(--linha-forte)}
.lj-faq summary{cursor:pointer;list-style:none;display:flex;justify-content:space-between;gap:1rem;align-items:center;padding:1rem 0;font-weight:600}
.lj-faq summary::-webkit-details-marker{display:none}
.lj-faq summary::after{content:"+";font-size:1.4rem;line-height:1;font-weight:400;transition:rotate .25s cubic-bezier(.16,1,.3,1)}
.lj-faq details[open] summary::after{rotate:45deg}
.lj-faq details p{padding:0 0 1.1rem;color:var(--suave);max-width:65ch;white-space:pre-line}

/* ── bloco: novidades ── */
.lj-novidades{display:grid;gap:1.25rem 3rem;align-items:center;background:var(--quadro);border:1px solid var(--linha);border-radius:var(--raio);padding:clamp(1.4rem,4vw,2.4rem)}
@media (min-width:860px){.lj-novidades{grid-template-columns:1fr 1.1fr}}
.lj-novidades h2{font-size:clamp(1.4rem,3vw,2rem)}
.lj-novidades p{color:var(--suave);margin-top:.4rem}
.lj-novidades__linha{display:flex;gap:.5rem;flex-wrap:wrap}
.lj-novidades__linha .lj-campo{flex:1;min-width:200px}
.lj-novidades__retorno{font-size:.92rem;font-weight:600;margin-top:.6rem;min-height:1.2em}
.lj-novidades__retorno[data-ok]{color:var(--ok)}.lj-novidades__retorno[data-erro]{color:var(--erro)}

/* ── bloco: vídeo ── */
.lj-video{position:relative;display:block;width:100%;aspect-ratio:16/9;border:0;padding:0;border-radius:var(--raio);overflow:hidden;cursor:pointer;background:#000}
.lj-video img{width:100%;height:100%;object-fit:cover;opacity:.85;transition:opacity .2s,transform .5s cubic-bezier(.2,.8,.2,1)}
.lj-video:hover img{opacity:1;transform:scale(1.02)}
.lj-video span{position:absolute;inset:0;margin:auto;width:76px;height:76px;border-radius:99px;background:var(--marca);color:var(--sobre-marca);display:grid;place-items:center;font-size:2.2rem;padding-left:4px}
.lj-video iframe{position:absolute;inset:0;width:100%;height:100%;border:0}

/* ── políticas ── */
.lj-politica{display:grid;gap:2rem clamp(2rem,5vw,4rem);padding-bottom:clamp(2rem,6vw,4rem)}
@media (min-width:900px){.lj-politica{grid-template-columns:minmax(0,1fr) 240px;align-items:start}.lj-politica__menu{position:sticky;top:130px}}
.lj-politica__texto{max-width:70ch}
.lj-politica__texto h2{font-size:1.15rem;letter-spacing:0;margin:1.8rem 0 .5rem}
.lj-politica__texto p{color:color-mix(in oklab,var(--texto) 88%,var(--fundo));margin-top:.7rem;white-space:pre-line}
.lj-politica__menu{display:grid;gap:.1rem;border-left:1px solid var(--linha-forte);padding-left:1rem;font-size:.95rem}
.lj-politica__menu a,.lj-politica__menu button{padding:.45rem 0;text-decoration:none;color:var(--suave);background:none;border:0;text-align:left;font:inherit;cursor:pointer}
.lj-politica__menu a:hover,.lj-politica__menu button:hover{color:var(--texto)}
.lj-politica__menu [aria-current]{color:var(--texto);font-weight:700}

/* ── aviso de cookies ── */
.lj-cookies{position:fixed;left:0;right:0;bottom:0;z-index:70;background:var(--fundo);color:var(--texto);border-top:1px solid var(--linha-forte);
  box-shadow:0 -12px 30px -18px rgb(0 0 0/.45);padding:1rem 0}
.lj-cookies__dentro{display:grid;gap:.9rem 2rem;align-items:center}
@media (min-width:860px){.lj-cookies__dentro{grid-template-columns:1fr auto}}
.lj-cookies p{font-size:.92rem;max-width:75ch}
.lj-cookies p a{color:inherit}
.lj-cookies__botoes{display:flex;gap:.5rem;flex-wrap:wrap}
.lj-cookies__botoes .lj-btn{min-height:44px;padding:.6rem 1rem;font-size:.92rem}
.lj-cookies__escolhas{display:grid;gap:.6rem;margin-top:.4rem}
.lj-cookies__escolhas label{display:flex;gap:.6rem;align-items:flex-start;font-size:.9rem;cursor:pointer}
.lj-cookies__escolhas input{width:18px;height:18px;margin-top:.15rem;accent-color:var(--texto);flex:none}
.lj-cookies__escolhas small{display:block;color:var(--suave)}

/* ── popup ── */
.lj-popup{border:0;padding:0;border-radius:var(--raio);background:var(--fundo);color:var(--texto);width:min(92vw,760px);max-height:90vh;overflow:auto;
  box-shadow:0 30px 80px -30px rgb(0 0 0/.6)}
.lj-popup::backdrop{background:rgb(0 0 0/.55)}
.lj-popup__dentro{display:grid}
@media (min-width:700px){.lj-popup--foto .lj-popup__dentro{grid-template-columns:1fr 1.1fr}}
.lj-popup__foto{min-height:180px;background:var(--marca)}
.lj-popup__foto img{width:100%;height:100%;object-fit:cover}
.lj-popup__corpo{padding:clamp(1.4rem,4vw,2.4rem)}
.lj-popup h2{font-size:clamp(1.5rem,4vw,2.1rem)}
.lj-popup p{margin-top:.6rem;color:var(--suave)}
.lj-popup__fechar{position:absolute;top:8px;right:8px;width:44px;height:44px;border-radius:99px;border:0;background:var(--fundo);color:var(--texto);cursor:pointer;font-size:1.3rem;display:grid;place-items:center;z-index:1}
.lj-popup form{margin-top:1.1rem;display:grid;gap:.6rem}
.lj-popup__cupom{margin-top:1.1rem;display:flex;align-items:center;gap:.7rem;flex-wrap:wrap}
.lj-popup__cupom .lj-cupom{font-size:1.2rem;padding:.5rem .9rem;color:var(--texto)}
.lj-popup .lj-btn{margin-top:1.1rem}
.lj-popup form .lj-btn{margin-top:0}

/* ── movimento ── */
@media (prefers-reduced-motion:no-preference){
  /* continuidade: troca de página esmaece; a foto do produto viaja do cartão para a página */
  @view-transition{navigation:auto}
  ::view-transition-old(root){animation:lj-sai .16s ease-out both}
  ::view-transition-new(root){animation:lj-vem .28s cubic-bezier(.16,1,.3,1) both}
  @keyframes lj-sai{to{opacity:0}}@keyframes lj-vem{from{opacity:0}}
  ::view-transition-group(lj-foto){animation-duration:.42s;animation-timing-function:cubic-bezier(.16,1,.3,1)}
  ::view-transition-old(lj-foto),::view-transition-new(lj-foto){height:100%;object-fit:cover}
  /* listas entram como listas, uma vez (só com JS e só no movimento completo) */
  .lj-js [data-movimento=completo] .lj-grade:not(.lj-vista) > .lj-card,
  .lj-js [data-movimento=completo] .lj-cats:not(.lj-vista) > *{opacity:0;transform:translateY(16px)}
  .lj-js [data-movimento=completo] .lj-grade.lj-vista > .lj-card,
  .lj-js [data-movimento=completo] .lj-cats.lj-vista > *{animation:lj-chega .55s cubic-bezier(.16,1,.3,1) both;animation-delay:calc(min(var(--i,0),7)*45ms)}
  @keyframes lj-chega{from{opacity:0;transform:translateY(16px)}}
  /* título do topo: sobe uma vez */
  [data-movimento=completo] .lj-banner h1{animation:lj-titulo .7s cubic-bezier(.16,1,.3,1) both}
  [data-movimento=completo] .lj-banner .lj-banner__sub,[data-movimento=completo] .lj-banner .lj-btn{animation:lj-titulo .7s .12s cubic-bezier(.16,1,.3,1) both}
  @keyframes lj-titulo{from{opacity:0;transform:translateY(14px)}}
  /* popup e aviso de cookies chegam; relógio pulsa a cada segundo */
  .lj-popup[open]{animation:lj-popup .32s cubic-bezier(.16,1,.3,1)}
  .lj-popup[open]::backdrop{animation:lj-vem .25s ease-out}
  @keyframes lj-popup{from{opacity:0;transform:translateY(18px) scale(.97)}}
  .lj-cookies:not([hidden]){animation:lj-barra .4s cubic-bezier(.16,1,.3,1)}
  @keyframes lj-barra{from{transform:translateY(100%)}}
  .lj-relogio b.lj-tique{animation:lj-tique .3s cubic-bezier(.16,1,.3,1)}
  @keyframes lj-tique{from{opacity:.35;transform:translateY(-4px)}}
  /* a foto voando para a sacola */
  .lj-voo{position:fixed;z-index:90;border-radius:12px;object-fit:cover;pointer-events:none;box-shadow:0 14px 30px -12px rgb(0 0 0/.5)}
  .lj-icone.lj-recebe{animation:lj-recebe .45s cubic-bezier(.16,1,.3,1)}
  @keyframes lj-recebe{35%{transform:scale(1.22) rotate(-6deg)}}
}
[data-movimento=nenhum] *,[data-movimento=nenhum] *::before,[data-movimento=nenhum] *::after{animation:none!important;transition:none!important}
[data-movimento=suave] .lj-mosaico a{animation:none}
html[data-movimento=nenhum]::view-transition-group(*),html[data-movimento=nenhum]::view-transition-old(*),html[data-movimento=nenhum]::view-transition-new(*){animation:none!important}
</style>
