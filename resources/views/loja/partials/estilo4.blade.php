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
</style>
