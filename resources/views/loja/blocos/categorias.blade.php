@php($estilo = $bloco['estilo'] ?? 'cartoes')
<section class="lj-caixa lj-secao">
    <div class="lj-secao__topo"><h2>{{ $bloco['titulo'] ?: 'Categorias' }}</h2>
        @if($estilo === 'circulos' && $bloco['categorias']->count() > 7)
            <span class="lj-setas" data-lj-setas>
                <button type="button" data-d="-1" aria-label="Categorias anteriores"><i class="bi bi-arrow-left" aria-hidden="true"></i></button>
                <button type="button" data-d="1" aria-label="Mais categorias"><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
            </span>
        @endif
    </div>
    @if($bloco['categorias']->isEmpty())
        <div class="lj-vazio">Os produtos da loja ainda não têm categoria. O público não vê este bloco vazio.</div>
    @elseif($estilo === 'circulos')
        <div class="lj-circulos" data-lj-trilho tabindex="0">
            @foreach($bloco['categorias'] as $c)
                <a href="{{ $ctx->url('produtos?categoria=' . $c['id']) }}">
                    <span class="lj-circulo">@if($c['foto'])<img src="{{ $c['foto'] }}" alt="" loading="lazy">@else<b>{{ mb_substr($c['nome'], 0, 1) }}</b>@endif</span>
                    <strong>{{ $c['nome'] }}</strong>
                </a>
            @endforeach
        </div>
    @elseif($estilo === 'pilulas')
        <div class="lj-pilulas">
            @foreach($bloco['categorias'] as $c)<a href="{{ $ctx->url('produtos?categoria=' . $c['id']) }}">{{ $c['nome'] }}</a>@endforeach
        </div>
    @elseif($estilo === 'lista')
        <div class="lj-indice">
            @foreach($bloco['categorias'] as $c)
                <a href="{{ $ctx->url('produtos?categoria=' . $c['id']) }}"><span>{{ $c['nome'] }}</span><span>{{ $c['total'] }} {{ $c['total'] === 1 ? 'item' : 'itens' }}</span></a>
            @endforeach
        </div>
    @else
        <div class="lj-cats">
            @foreach($bloco['categorias']->take(8) as $c)
                <a class="lj-cat @if($c['foto']) lj-cat--foto @endif" href="{{ $ctx->url('produtos?categoria=' . $c['id']) }}">
                    @if($c['foto'])<img src="{{ $c['foto'] }}" alt="" loading="lazy">@endif
                    <strong>{{ $c['nome'] }}<small>{{ $c['total'] }} {{ $c['total'] === 1 ? 'produto' : 'produtos' }}</small></strong>
                </a>
            @endforeach
        </div>
    @endif
</section>
