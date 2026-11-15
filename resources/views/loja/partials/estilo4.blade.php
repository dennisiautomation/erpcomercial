{{-- Loja online — 4ª rodada (15/11/2026): conta, favoritos, grade, avaliações, rastreio, boleto. Só usa as variáveis do tema. --}}
<style>
/* favoritos */
.lj-acao--fav .lj-conta{top:4px;right:auto;left:26px}
@media (max-width:1099px){.lj-acao--fav .lj-conta{left:auto;right:0}}
@media (max-width:859px){.lj-acao--fav,.lj-acao--conta{display:none}}
.lj-fav{position:absolute;top:8px;right:8px;z-index:3;width:38px;height:38px;border-radius:999px;border:0;background:color-mix(in oklab,var(--fundo) 88%,transparent);color:var(--texto);
  display:grid;place-items:center;font-size:1.05rem;cursor:pointer;transition:transform .18s ease,color .18s ease,background .18s ease}
.lj-fav:hover{background:var(--fundo);transform:scale(1.08)}
.lj-fav[aria-pressed=true]{color:var(--oferta)}
.lj-fav:focus-visible,.lj-fav-texto:focus-visible{outline:2px solid var(--marca);outline-offset:2px}
.lj-fav-texto[aria-pressed=true] i{color:var(--oferta)}
.lj-card__topo{position:relative}
.lj-card__topo .lj-selos{max-width:calc(100% - 54px)}

/* nota (estrelas) */
.lj-nota{display:inline-flex;align-items:center;gap:.25rem;font-size:.82rem;font-weight:700;color:var(--texto);text-decoration:none}
.lj-nota i{color:#e0a100;font-size:.8rem}
.lj-nota small{font-weight:500;color:var(--suave)}
.lj-apartir{font-size:.68rem;font-weight:600;color:var(--suave);letter-spacing:.02em;text-transform:uppercase;display:block;line-height:1}
.lj-estrelas{color:#e0a100;letter-spacing:.08em;font-size:.9rem}
.lj-estrelas .bi-star{color:var(--linha-forte)}

/* grade: tamanho, cor */
.lj-variacao{margin:1rem 0 0}
.lj-variacao__nome{display:block;font-size:.88rem;color:var(--suave);margin-bottom:.45rem}
.lj-variacao__nome strong{color:var(--texto)}
.lj-variacao__opcoes{display:flex;flex-wrap:wrap;gap:.45rem}
.lj-variacao__opcao{min-width:46px;min-height:44px;padding:0 .9rem;display:inline-grid;place-items:center;border:1.5px solid var(--linha-forte);border-radius:var(--raio-botao);
  background:var(--fundo);color:var(--texto);font-weight:600;font-size:.92rem;text-decoration:none;transition:border-color .15s ease,background .15s ease}
.lj-variacao__opcao:hover{border-color:var(--texto)}
.lj-variacao__opcao[aria-current=true]{border-color:var(--marca);background:var(--marca);color:var(--sobre-marca)}
.lj-variacao__opcao--fim{color:var(--suave);border-style:dashed;text-decoration:line-through}
.lj-variacao__opcao--fim[aria-current=true]{background:var(--quadro);color:var(--suave);border-color:var(--linha-forte)}
.lj-variacao + .lj-estoque{margin-top:1rem}

/* avise-me */
.lj-avise{margin-top:.9rem;padding:1rem;border:1px solid var(--linha);border-radius:var(--raio);background:var(--quadro)}
.lj-avise__linha{display:flex;gap:.5rem;margin:.5rem 0}
.lj-avise__linha .lj-campo{flex:1;min-width:0}
.lj-avise__saida{font-size:.9rem;font-weight:600;margin-top:.4rem;min-height:1.2em}
.lj-avise__saida[data-erro]{color:var(--oferta)}

/* avaliações na página do produto */
.lj-avaliacoes__resumo{display:flex;align-items:center;gap:.7rem;margin-bottom:1.2rem}
.lj-avaliacoes__resumo b{font-size:2.2rem;line-height:1}
.lj-avaliacoes__resumo small{color:var(--suave)}
.lj-avaliacoes__lista{list-style:none;padding:0;margin:0;display:grid;gap:1rem;grid-template-columns:repeat(auto-fill,minmax(min(100%,320px),1fr))}
.lj-avaliacoes__lista li{padding:1rem 1.1rem;border:1px solid var(--linha);border-radius:var(--raio);background:var(--fundo)}
.lj-avaliacoes__lista small{color:var(--suave)}
.lj-avaliacoes__lista p{margin:.45rem 0 0;line-height:1.55}
.lj-avaliacoes__resposta{padding:.6rem .8rem;border-radius:calc(var(--raio) * .6);background:var(--quadro);font-size:.92rem}

/* avaliar (página do pedido) */
.lj-avaliar{margin-top:2.2rem;padding-top:1.6rem;border-top:1px solid var(--linha)}
.lj-avaliar h2{font-size:1.15rem;letter-spacing:0}
.lj-avaliar__item{display:grid;gap:.5rem;padding:1rem 0;border-bottom:1px solid var(--linha)}
.lj-avaliar__item:last-child{border-bottom:0}
.lj-avaliar__item .lj-btn{justify-self:start}
.lj-estrelas-campo{border:0;padding:0;margin:0;display:inline-flex;flex-direction:row-reverse;justify-content:flex-end;gap:.1rem}
.lj-estrelas-campo input{position:absolute;opacity:0;width:1px;height:1px}
.lj-estrelas-campo label{cursor:pointer;font-size:1.7rem;line-height:1;color:var(--linha-forte);padding:.15rem;transition:color .12s ease}
.lj-estrelas-campo label:hover,.lj-estrelas-campo label:hover ~ label,.lj-estrelas-campo input:checked ~ label{color:#e0a100}
.lj-estrelas-campo input:focus-visible + label{outline:2px solid var(--marca);outline-offset:2px;border-radius:4px}

/* rastreio */
.lj-rastreio{display:flex;align-items:center;gap:.9rem;margin-top:1.2rem;padding:1rem 1.1rem;border:1px solid var(--linha);border-radius:var(--raio);background:var(--quadro)}
.lj-rastreio > i{font-size:1.6rem;color:var(--marca)}
.lj-rastreio > div{flex:1;min-width:0;display:grid;gap:.1rem}
.lj-rastreio__codigo{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.95rem;overflow-wrap:anywhere;color:var(--suave)}

/* conta do comprador */
.lj-conta-dica{margin:0 0 1rem;padding:.75rem 1rem;border-radius:var(--raio);background:var(--quadro);font-size:.92rem}
.lj-conta-dica a{font-weight:700}
.lj-entrar{display:grid;gap:clamp(1.5rem,4vw,3rem);grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));align-items:start;padding-bottom:3rem}
.lj-entrar__quadro{padding:clamp(1.2rem,3vw,2rem);border:1px solid var(--linha);border-radius:var(--raio);background:var(--fundo)}
.lj-entrar__quadro h2{font-size:1.25rem;letter-spacing:0;margin-bottom:.3rem}
.lj-entrar__quadro > p{color:var(--suave);font-size:.93rem;margin-bottom:1rem}
.lj-entrar__quadro .lj-form,.lj-resumo .lj-form{display:grid;grid-template-columns:minmax(0,1fr);gap:.85rem}
.lj-entrar__quadro .lj-form > *,.lj-resumo .lj-form > *{grid-column:1}
.lj-entrar__quadro .lj-aceite{display:flex;gap:.5rem;align-items:flex-start;font-size:.9rem}
.lj-entrar__quadro label{display:block;font-size:.88rem;font-weight:600;margin-bottom:.3rem}
.lj-entrar__quadro .lj-aceite{font-weight:400}
.lj-entrar details{margin-top:1rem;font-size:.92rem}
.lj-entrar summary{cursor:pointer;color:var(--marca);font-weight:600}
.lj-pedidos-lista{display:grid;gap:.7rem}
.lj-pedido-linha{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.8rem;padding:1rem 1.1rem;border:1px solid var(--linha);border-radius:var(--raio);
  text-decoration:none;color:inherit;transition:border-color .15s ease}
.lj-pedido-linha:hover{border-color:var(--texto)}
.lj-pedido-linha small{display:block;color:var(--suave)}
.lj-etiqueta{display:inline-block;padding:.2rem .6rem;border-radius:999px;font-size:.78rem;font-weight:700;background:var(--quadro);color:var(--texto)}
.lj-etiqueta--ok{background:color-mix(in oklab,#1c8a4f 16%,var(--fundo));color:color-mix(in oklab,#1c8a4f 80%,var(--texto))}
.lj-etiqueta--fim{background:color-mix(in oklab,var(--oferta) 14%,var(--fundo));color:var(--oferta)}
.lj-vazio-quadro{padding:2.5rem 1rem;text-align:center;color:var(--suave);border:1px dashed var(--linha-forte);border-radius:var(--raio)}
.lj-vazio-quadro i{display:block;font-size:2rem;margin-bottom:.6rem}

/* ── página do produto: sobre a foto (favoritar, enviar, pontos) ── */
.lj-galeria__acoes{position:absolute;top:12px;right:12px;z-index:3;display:grid;gap:.5rem}
.lj-galeria__acao{width:42px;height:42px;border-radius:999px;border:0;background:rgb(255 255 255/.94);color:#111;display:grid;place-items:center;font-size:1.05rem;cursor:pointer;
  box-shadow:0 6px 16px -8px rgb(0 0 0/.45);transition:transform .18s ease}
.lj-galeria__acao:hover{transform:scale(1.08)}
.lj-galeria__acao[aria-pressed=true]{color:var(--oferta)}
.lj-galeria__acao:focus-visible{outline:2px solid var(--marca);outline-offset:2px}
.lj-galeria__pontos{display:none}
.lj-preco-nota{font-size:.86rem;color:var(--suave);font-weight:600}
.lj-garantias small{display:block;font-size:.86rem;color:var(--suave);font-weight:400}
.lj-garantias small a{color:inherit}
.lj-avaliacoes__resumo{flex-wrap:wrap}
.lj-avaliacoes__barras{display:grid;grid-template-columns:auto minmax(90px,180px) auto;gap:.2rem .6rem;align-items:center;font-size:.78rem;color:var(--suave);margin-left:auto}
.lj-avaliacoes__barras i{display:block;height:6px;border-radius:99px;background:var(--linha);overflow:hidden}
.lj-avaliacoes__barras b{display:block;height:100%;border-radius:99px;background:#e0a100}
.lj-sanfona__topo{display:none}

/* ── página do produto no CELULAR (15/11/2026): foto de ponta a ponta, compra sem moldura, sanfona ── */
@media (max-width:699px){
  .lj-trilha > span:last-child,.lj-trilha > span:nth-last-child(2){display:none}
  .lj-trilha{padding-block:.7rem;font-size:.82rem}
  .lj-produto{gap:0;grid-template-columns:minmax(0,1fr)}
  .lj-produto > *{min-width:0}
  .lj-galeria{margin-inline:calc(-1 * clamp(16px,4vw,40px));display:block}
  .lj-galeria__moldura{border-radius:0}
  .lj-galeria__palco{border-radius:0;aspect-ratio:1/1.08}
  .lj-galeria__palco .lj-zoom,.lj-galeria__palco img{border-radius:0}
  .lj-galeria__palco img{width:100%;height:100%;object-fit:contain}
  .lj-galeria__seta,.lj-galeria__conta,.lj-miniaturas{display:none}
  .lj-galeria__pontos{display:flex;gap:6px;position:absolute;left:50%;bottom:14px;translate:-50% 0;z-index:2;padding:6px 9px;border-radius:99px;background:rgb(17 17 17/.42);backdrop-filter:blur(6px)}
  .lj-galeria__pontos i{width:6px;height:6px;border-radius:99px;background:rgb(255 255 255/.55);transition:width .25s cubic-bezier(.16,1,.3,1),background .25s ease}
  .lj-galeria__pontos i[data-agora]{width:18px;background:#fff}
  .lj-galeria .lj-selos{top:12px;left:12px}
  .lj-produto__info{padding-top:1.1rem}
  .lj-produto__info h1{font-size:1.5rem;line-height:1.2;margin-top:.25rem;text-wrap:balance}
  .lj-produto__ref{margin-top:.35rem}
  .lj-compra{border:0;padding:0;border-radius:0;margin-top:1rem;background:transparent}
  .lj-produto__preco{display:flex;flex-wrap:wrap;align-items:baseline;gap:.15rem .6rem}
  .lj-produto__preco b{font-size:2.15rem;line-height:1.05}
  .lj-produto__preco s{flex-basis:100%;font-size:.95rem}
  .lj-economia{flex-basis:auto;padding:.2rem .55rem;border-radius:99px;background:color-mix(in oklab,var(--oferta) 13%,var(--fundo));color:var(--oferta-tinta,var(--oferta));font-size:.8rem;font-weight:700}
  .lj-formas{margin-top:.8rem;padding:.8rem .9rem;border-radius:var(--raio);background:var(--quadro);font-size:.9rem}
  .lj-variacao{margin-top:1.2rem}
  .lj-variacao__opcoes{gap:.5rem}
  .lj-variacao__opcao{min-width:52px;min-height:48px;font-size:.95rem}
  .lj-estoque{margin-top:1.1rem}
  .lj-comprar{display:grid;grid-template-columns:auto minmax(0,1fr);gap:.6rem;align-items:stretch}
  .lj-comprar .lj-qtd{height:56px}
  .lj-comprar .lj-qtd button{width:40px}
  .lj-comprar .lj-qtd input{width:38px}
  .lj-comprar .lj-btn--grande{min-width:0;min-height:56px;padding-inline:.6rem;font-size:clamp(.88rem,4vw,1rem);letter-spacing:0;line-height:1.15;text-wrap:balance;gap:.45rem}
  .lj-comprar .lj-btn--grande i{display:none}
  .lj-compra > .lj-btn--cheio{min-height:52px}
  .lj-produto__info > .lj-bloco{margin-top:1.4rem;padding:1rem;border:1px solid var(--linha);border-radius:var(--raio)}
  .lj-garantias{grid-template-columns:repeat(auto-fit,minmax(0,1fr));grid-auto-flow:column;gap:.5rem;border-top:0;padding-top:0;margin-top:1.1rem}
  .lj-garantias li{flex-direction:column;align-items:center;text-align:center;gap:.4rem;padding:.9rem .4rem;border-radius:var(--raio);background:var(--quadro);font-size:.8rem;line-height:1.3}
  .lj-garantias i{font-size:1.35rem}
  .lj-garantias small{font-size:.74rem;margin-top:.1rem}
  .lj-partilha{margin-top:1rem}
  .lj-partilha .lj-fav-texto,.lj-partilha a[href^="https://wa.me/?"]{display:none}
  .lj-partilha .lj-btn{flex:1;justify-content:center;min-height:46px}
  body:has([data-lj-produto]) .lj-zap{display:none}
  /* abas viram sanfona */
  .lj-abas{margin-top:1.6rem;border-top:1px solid var(--linha-forte)}
  .lj-abas--sanfona .lj-abas__lista{display:none}
  .lj-abas--sanfona .lj-sanfona__topo{display:flex;width:100%;align-items:center;justify-content:space-between;gap:1rem;padding:1.05rem 0;border:0;border-bottom:1px solid var(--linha);
    background:transparent;color:var(--texto);font:inherit;font-weight:700;font-size:1.02rem;text-align:left;cursor:pointer}
  .lj-abas--sanfona .lj-sanfona__topo i{transition:rotate .25s cubic-bezier(.16,1,.3,1);color:var(--suave)}
  .lj-abas--sanfona .lj-sanfona__topo[aria-expanded=true]{border-bottom-color:transparent}
  .lj-abas--sanfona .lj-sanfona__topo[aria-expanded=true] i{rotate:180deg}
  .lj-abas--sanfona .lj-abas__painel{padding:0 0 1.2rem;border-bottom:1px solid var(--linha)}
  /* avaliações deslizam */
  .lj-avaliacoes__resumo{gap:.5rem .7rem}
  .lj-avaliacoes__barras{flex-basis:100%;margin:.4rem 0 0;grid-template-columns:auto 1fr auto}
  .lj-avaliacoes__lista{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;gap:.7rem;margin-inline:calc(-1 * clamp(16px,4vw,40px));padding-inline:clamp(16px,4vw,40px);scrollbar-width:none}
  .lj-avaliacoes__lista::-webkit-scrollbar{display:none}
  .lj-avaliacoes__lista{align-items:flex-start}
  .lj-avaliacoes__lista li{flex:0 0 84%;scroll-snap-align:center}
  .lj-barra-compra{padding:.7rem 1rem calc(.7rem + env(safe-area-inset-bottom))}
}
@media (prefers-reduced-motion:reduce){.lj-galeria__pontos i,.lj-sanfona__topo i,.lj-galeria__acao{transition:none}}
</style>
