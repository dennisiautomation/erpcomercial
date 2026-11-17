<!doctype html>
<html lang="pt-BR" data-movimento="{{ $tema['movimento'] ?? 'completo' }}">
<head>
<!--
  LOJA ONLINE — contrato de direção (14/11/2026)
  THESIS: a vitrine é do LOJISTA, não da plataforma: uma estrutura, seis mundos (Vitrine, Ateliê, Circuito, Feira, Mimo, Balcão).
          Recusa o "tema recolorido": cada modelo muda forma, tipo, foto, preço e composição do topo, não só a cor.
  OWN-WORLD: variáveis de :root (marca, oferta, fundo, quadro, topo, rodapé, duas fontes, raio) + data-cartao/botao/titulos/preco/foto.
  STORY: o visitante entende o que a loja vende, confia (produto real, preço, estoque do ERP) e compra em três passos.
  FIRST VIEWPORT: nome da loja, busca e carrinho; banner com promessa + FOTOS DE PRODUTOS REAIS (mosaico) e um botão.
  FORM: loja de varejo jogada direto — produto na frente; um único momento de movimento (o mosaico se revela).
-->
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ request()->routeIs('loja.home') && filled($loja->opcao('seo_titulo')) ? $loja->opcao('seo_titulo') : ($titulo ?? $loja->nome) }}</title>
<script>document.documentElement.classList.add('lj-js')</script>
<meta name="description" content="{{ ($descricaoPagina ?? '') !== '' ? $descricaoPagina : ($loja->opcao('descricao_busca') ?: $loja->nome . ' — compre online.') }}">
@if($ctx->previa || $ctx->rascunho || ($semIndice ?? false))<meta name="robots" content="noindex,nofollow">@endif
<meta name="theme-color" content="{{ $tema['cor_cabecalho'] }}">
@php $imagemCompartilhar = $imagemPagina ?? \App\Services\LojaOnline\LojaContexto::arquivo($loja->opcao('imagem_compartilhar')); @endphp
<meta property="og:site_name" content="{{ $loja->nome }}">
<meta property="og:title" content="{{ $titulo ?? $loja->nome }}">
<meta property="og:type" content="{{ isset($imagemPagina) ? 'product' : 'website' }}">
@if($imagemCompartilhar)<meta property="og:image" content="{{ url($imagemCompartilhar) }}">@endif
@if(filled($loja->opcao('favicon')))<link rel="icon" href="{{ \App\Services\LojaOnline\LojaContexto::arquivo($loja->opcao('favicon')) }}">@endif
@if(preg_match('/^[A-Za-z0-9_-]{10,100}$/', (string) $loja->opcao('verificacao_google')))<meta name="google-site-verification" content="{{ $loja->opcao('verificacao_google') }}">@endif
@if(preg_match('/^[A-Za-z0-9]{10,100}$/', (string) $loja->opcao('verificacao_meta')))<meta name="facebook-domain-verification" content="{{ $loja->opcao('verificacao_meta') }}">@endif
@if($temaFontes)
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="{{ $temaFontes }}">
@endif
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>{!! $temaEstilo !!}</style>
@include('loja.partials.estilo')
@include('loja.partials.estilo2')
@include('loja.partials.estilo3')
@include('loja.partials.estilo4')
@if(($modeloLoja ?? null) && view()->exists('loja.modelos.' . $modeloLoja))@include('loja.modelos.' . $modeloLoja)@endif
@stack('estilo')
</head>
<body {!! $temaAtributos !!} @if($modeloLoja ?? null) data-modelo="{{ $modeloLoja }}" @endif data-pagina="@yield('pagina', 'loja')">
<a class="lj-pular" href="#conteudo">Pular para o conteúdo</a>
@if($ctx->rascunho)
    <div class="lj-previa">Prévia do editor — o público ainda não vê estas mudanças.</div>
@elseif($ctx->previa)
    <div class="lj-previa">Esta loja ainda não está no ar: só você, logado no ERP, consegue ver.</div>
@endif
@if(filled($loja->opcao('aviso_topo')))
    <div class="lj-faixa"><span class="lj-faixa__trilho"><span>{{ $loja->opcao('aviso_topo') }}</span><span aria-hidden="true">{{ $loja->opcao('aviso_topo') }}</span><span aria-hidden="true">{{ $loja->opcao('aviso_topo') }}</span><span aria-hidden="true">{{ $loja->opcao('aviso_topo') }}</span></span></div>
@endif

@php
    $zapNumero = filled($loja->opcao('whatsapp')) ? preg_replace('/\D/', '', $loja->opcao('whatsapp')) : null;
    $catsMenu = ($categoriasMenu ?? collect());
    $freteAcima = (float) $loja->opcao('frete_gratis_acima');
@endphp
<div class="lj-util">
    <div class="lj-caixa lj-util__dentro">
        <span>
            @if($freteAcima > 0 && $loja->opcao('entrega'))<i class="bi bi-truck" aria-hidden="true"></i> Frete grátis a partir de R$ {{ number_format($freteAcima, 2, ',', '.') }}
            @elseif(filled($loja->opcao('horario_atendimento')))<i class="bi bi-clock" aria-hidden="true"></i> {{ $loja->opcao('horario_atendimento') }}
            @elseif($loja->opcao('retirada'))<i class="bi bi-bag-check" aria-hidden="true"></i> Compre pelo site e retire na loja
            @endif
        </span>
        <nav aria-label="Ajuda">
            <a href="{{ $ctx->url('politica/entrega') }}">Entrega</a>
            <a href="{{ $ctx->url('politica/trocas') }}">Trocas</a>
            @if($zapNumero)<a href="https://wa.me/55{{ $zapNumero }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i> {{ $loja->opcao('whatsapp') }}</a>@endif
        </nav>
    </div>
</div>

<header class="lj-topo" data-lj-topo>
    <div class="lj-caixa">
        <div class="lj-topo__linha">
            <button type="button" class="lj-icone lj-menu-botao" data-lj-menu-abrir aria-label="Abrir o menu" aria-haspopup="dialog"><i class="bi bi-list" aria-hidden="true"></i></button>
            <a class="lj-marca" href="{{ $ctx->url() }}" aria-label="{{ $loja->nome }} — página inicial">
                @if(!empty($tema['logo']))
                    <img src="{{ \App\Services\LojaOnline\LojaContexto::arquivo($tema['logo']) }}" alt="{{ $loja->nome }}">
                @else
                    {{ $loja->nome }}
                @endif
            </a>
            <form class="lj-busca" action="{{ $ctx->caminho('produtos') }}" method="get" role="search" data-lj-busca data-sugestoes="{{ $ctx->caminho('buscar') }}">
                @if($ctx->rascunho)<input type="hidden" name="rascunho" value="1">@endif
                <label class="lj-so-leitor" for="lj-q">Buscar produtos</label>
                <input id="lj-q" type="search" name="q" value="{{ request('q') }}" placeholder="O que você procura?" autocomplete="off" aria-controls="lj-sugestoes" aria-expanded="false">
                <button type="submit" aria-label="Buscar"><i class="bi bi-search" aria-hidden="true"></i></button>
                <div class="lj-sugestoes" id="lj-sugestoes" data-lj-sugestoes hidden></div>
            </form>
            <div class="lj-acoes">
                @if($zapNumero)
                    <a class="lj-icone lj-acao" href="https://wa.me/55{{ $zapNumero }}" target="_blank" rel="noopener" aria-label="Falar no WhatsApp"><i class="bi bi-whatsapp" aria-hidden="true"></i><span>Atendimento</span></a>
                @endif
                @if($loja->opcao('conta_cliente'))
                    <a class="lj-icone lj-acao lj-acao--conta" href="{{ $ctx->url(($contaLoja ?? null) ? 'conta' : 'entrar') }}" aria-label="{{ ($contaLoja ?? null) ? 'Minha conta' : 'Entrar' }}"><i class="bi bi-person{{ ($contaLoja ?? null) ? '-check' : '' }}" aria-hidden="true"></i><span>{{ ($contaLoja ?? null) ? \Illuminate\Support\Str::limit(\Illuminate\Support\Str::before($contaLoja->nome, ' '), 12, '') : 'Entrar' }}</span></a>
                @endif
                <a class="lj-icone lj-acao lj-acao--fav" href="{{ $ctx->url('favoritos') }}" aria-label="Favoritos"><i class="bi bi-heart" aria-hidden="true"></i><span>Favoritos</span><span class="lj-conta" data-lj-fav-conta hidden>0</span></a>
                <a class="lj-icone lj-acao lj-sacola" href="{{ $ctx->url('carrinho') }}" aria-label="Carrinho" data-lj-sacola>
                    <i class="bi bi-bag" aria-hidden="true"></i><span>Sacola</span>
                    <span class="lj-conta" data-lj-conta @if(($carrinhoTotal ?? 0) < 1) hidden @endif>{{ $carrinhoTotal ?? 0 }}</span>
                </a>
            </div>
        </div>
    </div>
    <div class="lj-topo__nav">
        <div class="lj-caixa">
            <nav class="lj-nav" aria-label="Categorias">
                @if($catsMenu->count() > 1)
                <div class="lj-mega" data-lj-mega>
                    <button type="button" class="lj-mega__botao" aria-expanded="false" aria-controls="lj-mega-painel"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i> Todas as categorias <i class="bi bi-chevron-down" aria-hidden="true"></i></button>
                    <div class="lj-mega__painel" id="lj-mega-painel" hidden>
                        @foreach($catsMenu as $c)
                            <a href="{{ $ctx->url('produtos?categoria=' . $c['id']) }}">
                                <span class="lj-mega__foto">@if($c['foto'])<img src="{{ $c['foto'] }}" alt="" loading="lazy">@endif</span>
                                <span><strong>{{ $c['nome'] }}</strong><small>{{ $c['total'] }} {{ $c['total'] === 1 ? 'produto' : 'produtos' }}</small></span>
                            </a>
                        @endforeach
                    </div>
                </div>
                @endif
                <a href="{{ $ctx->url('produtos') }}" @if(request()->routeIs('loja.produtos') && !request('categoria') && !request('promocao')) aria-current="page" @endif>Todos os produtos</a>
                @if($temOfertas ?? false)<a class="lj-nav__oferta" href="{{ $ctx->url('produtos?promocao=1') }}" @if(request('promocao')) aria-current="page" @endif>Ofertas</a>@endif
                @foreach($catsMenu->take(7) as $c)
                    <a href="{{ $ctx->url('produtos?categoria=' . $c['id']) }}" @if((int) request('categoria') === $c['id']) aria-current="page" @endif>{{ $c['nome'] }}</a>
                @endforeach
            </nav>
        </div>
    </div>
</header>

{{-- menu lateral (celular) --}}
<dialog class="lj-gaveta lj-gaveta--esq" data-lj-menu aria-label="Menu da loja">
    <div class="lj-gaveta__topo"><strong>{{ $loja->nome }}</strong><button type="button" class="lj-icone" data-lj-x aria-label="Fechar o menu"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
    <nav class="lj-gaveta__corpo lj-menu-lateral" aria-label="Categorias">
        <a href="{{ $ctx->url('produtos') }}"><i class="bi bi-grid" aria-hidden="true"></i> Todos os produtos</a>
        @if($temOfertas ?? false)<a href="{{ $ctx->url('produtos?promocao=1') }}"><i class="bi bi-percent" aria-hidden="true"></i> Ofertas</a>@endif
        @foreach($catsMenu as $c)
            <a href="{{ $ctx->url('produtos?categoria=' . $c['id']) }}"><span class="lj-mega__foto">@if($c['foto'])<img src="{{ $c['foto'] }}" alt="" loading="lazy">@endif</span> {{ $c['nome'] }} <small>{{ $c['total'] }}</small></a>
        @endforeach
        <hr>
        @if($loja->opcao('conta_cliente'))<a href="{{ $ctx->url(($contaLoja ?? null) ? 'conta' : 'entrar') }}"><i class="bi bi-person" aria-hidden="true"></i> {{ ($contaLoja ?? null) ? 'Minha conta' : 'Entrar ou criar conta' }}</a>@endif
        <a href="{{ $ctx->url('favoritos') }}"><i class="bi bi-heart" aria-hidden="true"></i> Favoritos</a>
        <a href="{{ $ctx->url('politica/entrega') }}">Entrega e retirada</a>
        <a href="{{ $ctx->url('politica/trocas') }}">Trocas e devoluções</a>
        @if($zapNumero)<a href="https://wa.me/55{{ $zapNumero }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i> Falar com a loja</a>@endif
    </nav>
</dialog>

{{-- sacola lateral (abre ao adicionar um produto) --}}
<dialog class="lj-gaveta lj-gaveta--dir" data-lj-gaveta aria-label="Sua sacola" data-resumo="{{ $ctx->caminho('carrinho/resumo') }}" data-atualizar="{{ $ctx->caminho('carrinho/atualizar') }}">
    <div class="lj-gaveta__topo"><strong>Sua sacola <span data-lj-gaveta-qtd></span></strong><button type="button" class="lj-icone" data-lj-x aria-label="Fechar a sacola"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
    <div class="lj-gaveta__meta" data-lj-gaveta-meta hidden><p></p><div class="lj-meta__barra" aria-hidden="true"><i></i></div></div>
    <div class="lj-gaveta__corpo" data-lj-gaveta-itens aria-live="polite"></div>
    <div class="lj-gaveta__pe">
        <div class="lj-gaveta__total"><span>Subtotal</span><strong data-lj-gaveta-total></strong></div>
        <a class="lj-btn lj-btn--cheio" href="{{ $ctx->url('finalizar') }}">Finalizar compra</a>
        <a class="lj-btn lj-btn--linha lj-btn--cheio" href="{{ $ctx->url('carrinho') }}">Ver a sacola</a>
    </div>
</dialog>

<main id="conteudo">
    @yield('conteudo')
</main>

@php $meiosRodape = \App\Services\LojaOnline\PagamentoLoja::meios($loja); @endphp
<footer class="lj-rodape">
    <div class="lj-caixa">
        <div class="lj-rodape__grade">
            <div class="lj-rodape__marca">
                <strong class="lj-marca">@if(!empty($tema['logo']) && \App\Services\LojaOnline\TemaCss::luminancia($tema['cor_rodape']) > 0.5)<img src="{{ \App\Services\LojaOnline\LojaContexto::arquivo($tema['logo']) }}" alt="{{ $loja->nome }}">@else{{ $loja->nome }}@endif</strong>
                @if(filled($loja->opcao('texto_rodape')))<p>{{ $loja->opcao('texto_rodape') }}</p>@endif
                @include('loja.partials.redes')
            </div>
            <div>
                <h2>Comprar</h2>
                <ul>
                    <li><a href="{{ $ctx->url('produtos') }}">Todos os produtos</a></li>
                    <li><a href="{{ $ctx->url('produtos?promocao=1') }}">Promoções</a></li>
                    <li><a href="{{ $ctx->url('produtos?ordem=novos') }}">Novidades</a></li>
                    <li><a href="{{ $ctx->url('carrinho') }}">Meu carrinho</a></li>
                    <li><a href="{{ $ctx->url('favoritos') }}">Favoritos</a></li>
                    @if($loja->opcao('conta_cliente'))<li><a href="{{ $ctx->url(($contaLoja ?? null) ? 'conta' : 'entrar') }}">{{ ($contaLoja ?? null) ? 'Minha conta' : 'Entrar' }}</a></li>@endif
                </ul>
            </div>
            <div>
                <h2>Ajuda</h2>
                <ul>
                    <li><a href="{{ $ctx->url('politica/entrega') }}">Entrega e retirada</a></li>
                    <li><a href="{{ $ctx->url('politica/trocas') }}">Trocas e devoluções</a></li>
                    <li><a href="{{ $ctx->url('politica/privacidade') }}">Privacidade</a></li>
                    <li><a href="{{ $ctx->url('politica/termos') }}">Termos de uso</a></li>
                </ul>
            </div>
            <div>
                @php $un = $loja->unidade; @endphp
                <h2>Atendimento</h2>
                <ul>
                    @if(filled($loja->opcao('whatsapp')))<li><a href="https://wa.me/55{{ preg_replace('/\D/', '', $loja->opcao('whatsapp')) }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i> WhatsApp {{ $loja->opcao('whatsapp') }}</a></li>@endif
                    @if(filled($loja->opcao('email')))<li><a href="mailto:{{ $loja->opcao('email') }}"><i class="bi bi-envelope" aria-hidden="true"></i> {{ $loja->opcao('email') }}</a></li>@endif
                    @if(filled($loja->opcao('horario_atendimento')))<li><i class="bi bi-clock" aria-hidden="true"></i> {{ $loja->opcao('horario_atendimento') }}</li>@endif
                    @if($un)<li><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ collect([trim(($un->logradouro ?? '') . ' ' . ($un->numero ?? '')), $un->bairro, trim(($un->cidade ?? '') . ($un->uf ? '/' . $un->uf : ''))])->filter()->implode(' · ') ?: $un->nome }}</li>@endif
                </ul>
            </div>
        </div>
        <div class="lj-rodape__selos">
            <div>
                <h2>Formas de pagamento</h2>
                <ul class="lj-pagamentos">
                    @if($meiosRodape['pix'])<li><i class="bi bi-qr-code" aria-hidden="true"></i> PIX</li>@endif
                    @if($meiosRodape['cartao'])<li><i class="bi bi-credit-card" aria-hidden="true"></i> Cartão de crédito</li>@endif
                    @if($meiosRodape['combinar'])<li><i class="bi bi-chat-dots" aria-hidden="true"></i> A combinar com a loja</li>@endif
                </ul>
            </div>
            <div>
                <h2>Como você recebe</h2>
                <ul class="lj-pagamentos">
                    @if($loja->opcao('entrega'))<li><i class="bi bi-truck" aria-hidden="true"></i> Entrega no endereço</li>@endif
                    @if($loja->opcao('retirada'))<li><i class="bi bi-bag-check" aria-hidden="true"></i> Retirada na loja</li>@endif
                </ul>
            </div>
        </div>
        <div class="lj-rodape__fim">
            <span>© {{ date('Y') }} {{ $loja->empresa?->razao_social ?? $loja->nome }}@if($loja->empresa?->cnpj) · CNPJ {{ \App\Models\ConfiguracaoLoja::formatarDocumento($loja->empresa->cnpj) }}@endif</span>
            <span class="lj-rodape__politicas">
                <button type="button" data-lj-cookies-abrir>Preferências de cookies</button>
                <span>Loja online IA365</span>
            </span>
        </div>
    </div>
</footer>

<div class="lj-brinde" data-lj-brinde hidden role="status" aria-live="polite">
    <span data-lj-brinde-texto></span>
    <a href="{{ $ctx->url('carrinho') }}">Ver carrinho</a>
</div>

<script>
(function () {
    var csrf = document.querySelector('meta[name=csrf-token]').content;
    var conta = document.querySelector('[data-lj-conta]');
    var brinde = document.querySelector('[data-lj-brinde]');
    var relogio;
    window.LJ = {
        csrf: csrf,
        moeda: function (v) { return 'R$ ' + Number(v).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}); },
        post: function (url, dados) {
            return fetch(url, {method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify(dados || {})}).then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); });
        },
        avisar: function (texto) {
            brinde.querySelector('[data-lj-brinde-texto]').textContent = texto;
            brinde.hidden = false; clearTimeout(relogio);
            relogio = setTimeout(function () { brinde.hidden = true; }, 4500);
        },
        contar: function (n) {
            conta.textContent = n; conta.hidden = n < 1;
            conta.classList.remove('pulsa'); void conta.offsetWidth; conta.classList.add('pulsa');
        }
    };
    // "+" do cartão de produto: põe no carrinho sem sair da página
    document.addEventListener('submit', function (ev) {
        var f = ev.target.closest('form[data-lj-add]');
        if (!f) return;
        ev.preventDefault();
        var botao = f.querySelector('button[type=submit]');
        botao.disabled = true;
        LJ.post(f.action, {produto_id: Number(f.produto_id.value), quantidade: Number((f.quantidade && f.quantidade.value) || 1)})
            .then(function (j) {
                if (j.ok) {
                    LJ.contar(j.total); LJ.avisar(j.aviso || 'Adicionado ao carrinho.');
                    if (LJ.voar) LJ.voar((f.closest('.lj-card') || document).querySelector('.lj-card__foto img, [data-lj-palco] img'));
                    if (LJ.rastro && j.item) LJ.rastro.evento('carrinho', {valor: j.item.preco * j.item.quantidade, itens: [j.item]});
                    if (f.dataset.ir) { location.href = f.dataset.ir; return; }
                    if (LJ.abrirSacola && !f.hasAttribute('data-lj-sem-gaveta')) setTimeout(LJ.abrirSacola, LJ.voar ? 520 : 0);
                }
                else { LJ.avisar(j.erro || 'Não foi possível adicionar.'); }
            })
            .catch(function () { f.removeAttribute('data-lj-add'); f.submit(); })
            .finally(function () { botao.disabled = false; });
    });
})();
</script>
@include('loja.partials.extras')
@include('loja.partials.extras2')
@include('loja.partials.extras3')
@stack('scripts')
</body>
</html>
