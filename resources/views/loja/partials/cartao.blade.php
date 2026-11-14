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
            @if($p['promocao'] && $p['normal'] > 0)<span class="lj-selo">-{{ max(1, (int) round((1 - $p['preco'] / $p['normal']) * 100)) }}%</span>@endif
            @if(! $p['disponivel'])<span class="lj-selo lj-selo--fim">Esgotado</span>
            @elseif($p['novo'] && ! $p['promocao'])<span class="lj-selo lj-selo--novo">Novo</span>@endif
            @if($p['frete_gratis'] && $p['disponivel'])<span class="lj-selo lj-selo--frete">Frete grátis</span>@endif
        </div>
        <form method="post" action="{{ $ctx->caminho('carrinho/adicionar') }}" data-lj-add class="lj-card__add">
            @csrf
            <input type="hidden" name="produto_id" value="{{ $p['id'] }}">
            <button class="lj-mais" type="submit" @disabled(!$p['disponivel']) aria-label="Adicionar {{ $p['nome'] }} ao carrinho"><i class="bi bi-bag-plus" aria-hidden="true"></i><span>Adicionar</span></button>
        </form>
    </div>
    <div class="lj-card__corpo">
        @if($p['categoria'])<span class="lj-card__cat">{{ $p['categoria'] }}</span>@endif
        <a class="lj-card__nome" href="{{ $ctx->url($p['caminho']) }}">{{ $p['nome'] }}</a>
        <span class="lj-card__ref">Cód. {{ $p['codigo'] }}@if($loja->opcao('mostrar_estoque') && $p['disponivel']) · {{ (int) $p['saldo'] }} em estoque @endif</span>
        <div class="lj-card__pe">
            <div class="lj-preco @if($p['promocao']) lj-preco--promo @endif">
                @if($p['promocao'])<s>R$ {{ number_format($p['normal'], 2, ',', '.') }}</s>@endif
                <b>R$ {{ number_format($p['preco'], 2, ',', '.') }}</b>
                @if($p['parcelas'])<small>ou {{ $p['parcelas']['vezes'] }}x de R$ {{ number_format($p['parcelas']['valor'], 2, ',', '.') }}{{ $p['parcelas']['sem_juros'] ? ' sem juros' : '' }}</small>@endif
            </div>
        </div>
    </div>
</article>
