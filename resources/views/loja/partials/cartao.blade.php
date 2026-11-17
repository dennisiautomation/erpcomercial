{{-- Cartão de produto da vitrine (3ª rodada). $p = item de CatalogoLoja::paraVitrine --}}
<article class="lj-card @if($p['foto2']) lj-card--duas @endif @unless($p['disponivel']) lj-card--fim @endunless" data-produto="{{ $p['id'] }}">
    <div class="lj-card__topo">
        <a class="lj-card__foto" href="{{ $ctx->url($p['caminho']) }}" tabindex="-1" aria-hidden="true">
            @if($p['foto'])
                <img src="{{ $p['foto'] }}" alt="" loading="lazy" decoding="async">
                @if($p['foto2'])<img class="lj-card__foto2" src="{{ $p['foto2'] }}" alt="" loading="lazy" decoding="async">@endif
            @else
                <span class="lj-semfoto">{{ mb_substr($p['nome'], 0, 1) }}</span>
            @endif
        </a>
        <div class="lj-selos">
            @if($p['exemplo'] ?? false)<span class="lj-selo lj-selo--exemplo">Exemplo</span>@endif
            @if($p['promocao'] && $p['normal'] > 0)<span class="lj-selo">-{{ max(1, (int) round((1 - $p['preco'] / $p['normal']) * 100)) }}%</span>@endif
            @if(! $p['disponivel'])<span class="lj-selo lj-selo--fim">Esgotado</span>
            @elseif($p['novo'] && ! $p['promocao'])<span class="lj-selo lj-selo--novo">Novo</span>@endif
            @if($p['frete_gratis'] && $p['disponivel'])<span class="lj-selo lj-selo--frete">Frete grátis</span>@endif
        </div>
        @unless($p['exemplo'] ?? false)
        <button type="button" class="lj-fav" data-lj-fav="{{ $p['id'] }}" aria-pressed="false" aria-label="Guardar {{ $p['nome'] }} nos favoritos"><i class="bi bi-heart" aria-hidden="true"></i></button>
        @endunless
        @if($p['grade'] ?? false)
            {{-- produto com grade: tamanho/cor se escolhe na página --}}
            <div class="lj-card__add"><a class="lj-mais" href="{{ $ctx->url($p['caminho']) }}" aria-label="Ver as opções de {{ $p['nome'] }}"><i class="bi bi-sliders" aria-hidden="true"></i><span>Ver opções</span></a></div>
        @else
        <form method="post" action="{{ $ctx->caminho('carrinho/adicionar') }}" data-lj-add class="lj-card__add">
            @csrf
            <input type="hidden" name="produto_id" value="{{ $p['id'] }}">
            <button class="lj-mais" type="submit" @disabled(!$p['disponivel']) aria-label="Adicionar {{ $p['nome'] }} ao carrinho"><i class="bi bi-bag-plus" aria-hidden="true"></i><span>Adicionar</span></button>
        </form>
        @endif
    </div>
    <div class="lj-card__corpo">
        @if($p['categoria'])<span class="lj-card__cat">{{ $p['categoria'] }}</span>@endif
        <a class="lj-card__nome" href="{{ $ctx->url($p['caminho']) }}">{{ $p['nome'] }}</a>
        @if(($p['avaliacoes'] ?? 0) > 0)<span class="lj-nota" aria-label="Nota {{ number_format((float) $p['nota'], 1, ',', '') }} de 5, {{ $p['avaliacoes'] }} {{ $p['avaliacoes'] === 1 ? 'avaliação' : 'avaliações' }}"><i class="bi bi-star-fill" aria-hidden="true"></i> {{ number_format((float) $p['nota'], 1, ',', '') }} <small>({{ $p['avaliacoes'] }})</small></span>@endif
        <span class="lj-card__ref">Cód. {{ $p['codigo'] }}@if($loja->opcao('mostrar_estoque') && $p['disponivel']) · {{ (int) $p['saldo'] }} em estoque @endif</span>
        <div class="lj-card__pe">
            <div class="lj-preco @if($p['promocao']) lj-preco--promo @endif">
                @if($p['promocao'])<s>R$ {{ number_format($p['normal'], 2, ',', '.') }}</s>@endif
                <b>{!! ($p['a_partir'] ?? false) ? '<small class="lj-apartir">a partir de</small>' : '' !!}R$ {{ number_format($p['preco'], 2, ',', '.') }}</b>
                @if($p['parcelas'])<small>ou {{ $p['parcelas']['vezes'] }}x de R$ {{ number_format($p['parcelas']['valor'], 2, ',', '.') }}{{ $p['parcelas']['sem_juros'] ? ' sem juros' : '' }}</small>@endif
            </div>
        </div>
    </div>
</article>
