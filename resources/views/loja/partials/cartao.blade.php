{{-- Cartão de produto da vitrine. $p = item de CatalogoLoja::paraVitrine --}}
<article class="lj-card">
    <a class="lj-card__foto" href="{{ $ctx->url($p['caminho']) }}" tabindex="-1" aria-hidden="true">
        @if($p['promocao'] && $p['normal'] > 0)
            <span class="lj-selo">-{{ max(1, (int) round((1 - $p['preco'] / $p['normal']) * 100)) }}%</span>
        @endif
        @unless($p['disponivel'])<span class="lj-selo lj-selo--fim">Esgotado</span>@endunless
        @if($p['foto'])
            <img src="{{ $p['foto'] }}" alt="" loading="lazy" decoding="async">
        @else
            <span class="lj-semfoto">{{ mb_substr($p['nome'], 0, 1) }}</span>
        @endif
    </a>
    <div class="lj-card__corpo">
        @if($p['categoria'])<span class="lj-card__cat">{{ $p['categoria'] }}</span>@endif
        <a class="lj-card__nome" href="{{ $ctx->url($p['caminho']) }}">{{ $p['nome'] }}</a>
        <span class="lj-card__ref">Cód. {{ $p['codigo'] }}@if($loja->opcao('mostrar_estoque') && $p['disponivel']) · {{ (int) $p['saldo'] }} em estoque @endif</span>
        <div class="lj-card__pe">
            <div class="lj-preco @if($p['promocao']) lj-preco--promo @endif">
                @if($p['promocao'])<s>R$ {{ number_format($p['normal'], 2, ',', '.') }}</s>@endif
                <b>R$ {{ number_format($p['preco'], 2, ',', '.') }}</b>
            </div>
            <form method="post" action="{{ $ctx->url('carrinho/adicionar') }}" data-lj-add>
                @csrf
                <input type="hidden" name="produto_id" value="{{ $p['id'] }}">
                <button class="lj-mais" type="submit" @disabled(!$p['disponivel']) aria-label="Adicionar {{ $p['nome'] }} ao carrinho"><i class="bi bi-plus-lg"></i></button>
            </form>
        </div>
    </div>
</article>
