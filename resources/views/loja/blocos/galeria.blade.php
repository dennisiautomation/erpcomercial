@php($lista = collect($bloco['lista'] ?? [])->filter(fn ($g) => filled($g['imagem'] ?? ''))->values())
@if($lista->isNotEmpty())
<section class="lj-caixa lj-secao">
    @if(filled($bloco['titulo'] ?? ''))<div class="lj-secao__topo"><h2>{{ $bloco['titulo'] }}</h2></div>@endif
    <div class="lj-galeria-fotos" data-n="{{ $lista->count() }}">
        @foreach($lista as $g)
            @php($link = $vinculo($g['botao_link'] ?? ''))
            @if($link)<a href="{{ $link }}"><img src="{{ \App\Services\LojaOnline\LojaContexto::arquivo($g['imagem']) }}" alt="" loading="lazy"></a>
            @else<span><img src="{{ \App\Services\LojaOnline\LojaContexto::arquivo($g['imagem']) }}" alt="" loading="lazy"></span>@endif
        @endforeach
    </div>
</section>
@elseif($ctx->rascunho)
<section class="lj-caixa lj-secao"><div class="lj-vazio">Galeria: envie pelo menos uma foto.</div></section>
@endif
