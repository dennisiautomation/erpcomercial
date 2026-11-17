{{--
  MODELO EXCLUSIVO — MISS MERLINDA (17/11/2026). Só carrega quando a loja usa o modelo "merlinda"
  (layout.blade.php → $modeloLoja). Tudo aqui é preso a body[data-modelo=merlinda]: não vaza para os 6 modelos públicos.

  THESIS: a boutique de bairro da Miss Merlinda, inteira no rosa da marca e no coração do logo; recusa o
          "moda preto-e-branco de revista" (Ateliê) e o e-commerce genérico de faixa escura sobre foto.
  OWN-WORLD: rosa-pétala #fedbe5 em CAMPOS inteiros (topo, capa, "Nossas lojas", rodapé), tinta grafite #2a282c,
          framboesa #a8174b só em oferta; Gilda Display (como o logo) + Jost em versalete espaçado (como o "MISS");
          fotos em ARCO de provador com filete grafite deslocado; o ♡ do logo como marcador.
  STORY: a cliente reconhece a loja que já conhece (logo, lojas, cidades), vê as peças em arco e compra ou chama no WhatsApp.
  FIRST VIEWPORT: topo rosa com o logo no centro; capa rosa-clara: título Gilda grande à esquerda + botão pílula grafite;
          à direita a foto em arco alto. Passa entre 3 capas.
  FORM: boutique em arco (provador/vitrine de loja de rua), 1º da lista; movimento único = o arco sobe ao entrar.
--}}
<style>
[data-modelo=merlinda]{--rosa:#fedbe5;--rosa-claro:#fdeef2;--tinta:#2a282c;--framboesa:#a8174b;--arco:999px 999px 18px 18px;--linha:color-mix(in oklab,var(--tinta) 14%,transparent)}
[data-modelo=merlinda] h1,[data-modelo=merlinda] h2,[data-modelo=merlinda] h3{font-weight:400;letter-spacing:-.01em}

/* ── topo: campo rosa, logo no centro, versalete espaçado ── */
[data-modelo=merlinda] .lj-util{background:var(--tinta);color:var(--rosa);letter-spacing:.06em}
[data-modelo=merlinda] .lj-util a:hover{color:#fff}
[data-modelo=merlinda] .lj-topo{background:var(--rosa);box-shadow:none;border-bottom:1px solid var(--linha)}
[data-modelo=merlinda] .lj-marca img{max-height:62px}
[data-modelo=merlinda] .lj-topo__nav{border-top:1px solid var(--linha)}
[data-modelo=merlinda] .lj-nav{justify-content:center}
[data-modelo=merlinda] .lj-nav > a,[data-modelo=merlinda] .lj-mega__botao{text-transform:uppercase;letter-spacing:.16em;font-size:.76rem;font-weight:500}
@media (max-width:899px){[data-modelo=merlinda] .lj-marca img{max-height:40px}}

/* ── faixa: grafite com versalete rosa ── */
[data-modelo=merlinda] .lj-faixa{background:var(--tinta);color:var(--rosa);text-transform:uppercase;letter-spacing:.2em;font-weight:500;font-size:.72rem;padding-block:.7rem}

/* ── títulos de seção: Gilda + coração ── */
[data-modelo=merlinda] .lj-secao__topo h2,[data-modelo=merlinda] .lj-lojas__cabeca h2,[data-modelo=merlinda] .lj-par h2{font-family:var(--fonte-titulo);font-size:clamp(1.8rem,3.4vw,2.7rem);line-height:1.05}
[data-modelo=merlinda] .lj-secao__topo h2::after,[data-modelo=merlinda] .lj-lojas__cabeca h2::after{content:"\2661";font-family:var(--fonte-texto);font-size:.5em;color:var(--framboesa);margin-left:.45em;vertical-align:.35em}

/* ── capa: campo rosa-claro, texto à esquerda, foto em ARCO à direita ── */
[data-modelo=merlinda] .lj-carrossel{--alt:620px}
[data-modelo=merlinda] .lj-slide--foto,[data-modelo=merlinda] .lj-slide--foto[data-tom]{background:var(--rosa-claro);color:var(--tinta)}
[data-modelo=merlinda] .lj-slide--foto::after{display:none}
[data-modelo=merlinda] .lj-slide__img{inset:auto;top:clamp(28px,4vw,48px);bottom:0;right:max(16px,calc((100vw - 1320px)/2 + 24px));left:auto;
  width:min(40vw,500px);height:auto;border-radius:var(--arco);object-fit:cover}
[data-modelo=merlinda] .lj-slide--foto::before{content:"";position:absolute;top:clamp(14px,2.6vw,30px);bottom:-12px;right:max(4px,calc((100vw - 1320px)/2 + 10px));
  width:min(40vw,500px);border:1.5px solid var(--tinta);border-radius:var(--arco);z-index:0;pointer-events:none}
[data-modelo=merlinda] .lj-slide__texto{max-width:min(34rem,52%)}
[data-modelo=merlinda] .lj-slide__texto::before{content:"Miss Merlinda \2661";display:block;font-size:.78rem;text-transform:uppercase;letter-spacing:.24em;margin-bottom:1.1rem}
[data-modelo=merlinda] .lj-slide h1,[data-modelo=merlinda] .lj-slide h2{font-family:var(--fonte-titulo);font-weight:400;font-size:clamp(2.6rem,6vw,5.2rem);letter-spacing:-.02em;line-height:.98}
[data-modelo=merlinda] .lj-slide p{color:color-mix(in oklab,var(--tinta) 82%,var(--rosa-claro))}
[data-modelo=merlinda] .lj-slide .lj-btn,[data-modelo=merlinda] .lj-slide--foto[data-tom] .lj-btn{background:var(--tinta);border-color:var(--tinta);color:#fff;text-transform:uppercase;letter-spacing:.14em;font-size:.8rem;font-weight:500}
[data-modelo=merlinda] .lj-carrossel__pontos{color:var(--tinta);mix-blend-mode:normal;justify-content:flex-start;left:max(16px,calc((100vw - 1320px)/2 + 16px));bottom:22px}
[data-modelo=merlinda] .lj-carrossel__seta{background:#fff}
@media (max-width:859px){
  [data-modelo=merlinda] .lj-carrossel,[data-modelo=merlinda] .lj-carrossel[data-altura=alta]{--alt:auto}
  [data-modelo=merlinda] .lj-slide--foto{flex-direction:column;align-items:stretch;padding-top:20px}
  [data-modelo=merlinda] .lj-slide__img{position:relative;inset:auto;width:min(calc(100% - 96px),300px);margin:0 auto;aspect-ratio:4/4.5;height:auto}
  [data-modelo=merlinda] .lj-slide--foto::before{top:10px;bottom:auto;right:auto;left:50%;translate:calc(-50% + 10px) 0;width:min(calc(100% - 96px),300px);aspect-ratio:4/4.5}
  [data-modelo=merlinda] .lj-slide h1,[data-modelo=merlinda] .lj-slide h2{font-size:clamp(2.3rem,11vw,3rem)}
  [data-modelo=merlinda] .lj-slide__texto::before{margin-bottom:.6rem}
  [data-modelo=merlinda] .lj-slide__texto{max-width:none}
  [data-modelo=merlinda] .lj-slide__dentro{padding-block:1.6rem 3.6rem}
  [data-modelo=merlinda] .lj-carrossel__pontos{left:16px}
}

/* ── cartão: foto em pé com topo arredondado, coração rosa ── */
[data-modelo=merlinda] .lj-card__foto{border-radius:120px 120px 14px 14px;background:var(--rosa-claro)}
[data-modelo=merlinda] .lj-card__nome{font-weight:400}
[data-modelo=merlinda] .lj-card__cat{text-transform:uppercase;letter-spacing:.14em;font-size:.68rem}
[data-modelo=merlinda] .lj-fav{background:var(--rosa);color:var(--tinta)}
[data-modelo=merlinda] .lj-selos{top:56px}
[data-modelo=merlinda] .lj-mais{background:var(--tinta);color:#fff}

/* ── grade de banners: etiqueta rosa em vez de sombra escura ── */
[data-modelo=merlinda] .lj-bannerzinho{border-radius:24px}
[data-modelo=merlinda] .lj-banners[data-arranjo=destaque] > :first-child{border-radius:var(--arco)}
[data-modelo=merlinda] .lj-bannerzinho--foto{color:var(--tinta)}
[data-modelo=merlinda] .lj-bannerzinho--foto::after{display:none}
[data-modelo=merlinda] .lj-bannerzinho__texto{background:rgb(254 219 229/.94);padding:.85rem 1.1rem;border-radius:16px;max-width:min(100%,22rem)!important}
[data-modelo=merlinda] .lj-bannerzinho strong{font-weight:400;letter-spacing:-.01em}
[data-modelo=merlinda] .lj-bannerzinho em{text-transform:uppercase;letter-spacing:.14em;font-size:.72rem;font-weight:500}

/* ── categorias em círculo: anel rosa ── */
[data-modelo=merlinda] .lj-circulo{background:var(--rosa-claro);outline-color:var(--rosa)}
[data-modelo=merlinda] .lj-circulos strong{font-weight:400}

/* ── Nossas lojas: campo rosa de ponta a ponta, cartões brancos numerados ── */
[data-modelo=merlinda] .lj-lojas{position:relative;isolation:isolate;padding-block:clamp(2.6rem,6vw,4.5rem)}
[data-modelo=merlinda] .lj-lojas::before{content:"";position:absolute;inset:0 calc(50% - 50vw);background:var(--rosa);z-index:-1}
[data-modelo=merlinda] .lj-lojas__cabeca{text-align:center;max-width:40rem;margin:0 auto 2rem}
[data-modelo=merlinda] .lj-lojas__lista{grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px}
[data-modelo=merlinda] .lj-loja{background:#fff;border-radius:22px;padding:1.4rem 1.4rem 1.2rem}
[data-modelo=merlinda] .lj-loja__n{font-family:var(--fonte-titulo);font-size:2.2rem;letter-spacing:0;opacity:1;color:var(--framboesa);line-height:1}
[data-modelo=merlinda] .lj-loja h3{font-family:var(--fonte-titulo);font-size:1.5rem;margin-top:.5rem}
[data-modelo=merlinda] .lj-loja__cidade{letter-spacing:.18em;font-weight:500}

/* ── imagem com texto: foto em arco ── */
[data-modelo=merlinda] .lj-par__img{background:var(--rosa-claro);aspect-ratio:4/4.4;border-radius:var(--arco);max-width:480px;justify-self:center;width:100%}

/* ── receber novidades: grafite com rosa ── */
[data-modelo=merlinda] .lj-novidades{background:var(--tinta);color:var(--rosa);border-radius:28px}
[data-modelo=merlinda] .lj-novidades h2{font-family:var(--fonte-titulo);color:#fff}
[data-modelo=merlinda] .lj-novidades .lj-btn{background:var(--rosa);border-color:var(--rosa);color:var(--tinta)}

/* ── botões ── */
[data-modelo=merlinda] .lj-btn{text-transform:uppercase;letter-spacing:.12em;font-size:.8rem;font-weight:500}

/* ── movimento: o arco sobe ao entrar (único momento) ── */
@media (prefers-reduced-motion:no-preference){
  html:not([data-movimento=nenhum]) [data-modelo=merlinda] .lj-slide__img{animation:mm-arco .9s cubic-bezier(.16,1,.3,1) both}
  @keyframes mm-arco{from{clip-path:inset(100% 0 0 0 round 999px 999px 18px 18px);translate:0 24px}to{clip-path:inset(0 0 0 0 round 999px 999px 18px 18px);translate:0 0}}
}
</style>
