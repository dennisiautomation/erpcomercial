@php($img = \App\Services\LojaOnline\LojaContexto::arquivo($bloco['imagem'] ?? null))
@php($link = $vinculo($bloco['botao_link'] ?? ''))
<section class="lj-caixa lj-secao">
    <div class="lj-par @unless($img) lj-par--sem-img @endunless" data-lado="{{ $bloco['lado'] ?? 'esquerda' }}">
        @if($img)<div class="lj-par__img"><img src="{{ $img }}" alt="" loading="lazy"></div>@endif
        <div>
            <h2>{{ $bloco['titulo'] ?? '' }}</h2>
            @if(filled($bloco['texto'] ?? ''))<p>{{ $bloco['texto'] }}</p>@endif
            @if($link && filled($bloco['botao_texto'] ?? ''))<a class="lj-btn" href="{{ $link }}">{{ $bloco['botao_texto'] }}</a>@endif
        </div>
    </div>
</section>
