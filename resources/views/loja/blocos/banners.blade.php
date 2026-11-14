@php($lista = collect($bloco['lista'] ?? [])->filter(fn ($b) => filled($b['titulo'] ?? '') || filled($b['imagem'] ?? ''))->values())
@if($lista->isNotEmpty())
<section class="lj-caixa lj-secao">
    @if(filled($bloco['titulo'] ?? ''))<div class="lj-secao__topo"><h2>{{ $bloco['titulo'] }}</h2></div>@endif
    <div class="lj-banners" data-arranjo="{{ $bloco['arranjo'] ?? 'iguais' }}" data-n="{{ $lista->count() }}">
        @foreach($lista as $i => $b)
            @php($img = \App\Services\LojaOnline\LojaContexto::arquivo($b['imagem'] ?? null))
            @php($prod = $bloco['produtosSlide'][$i] ?? null)
            @php($link = $vinculo($b['botao_link'] ?? '') ?: $ctx->url('produtos'))
            <a class="lj-bannerzinho @if($img) lj-bannerzinho--foto @endif" href="{{ $link }}" data-n="{{ $i % 3 }}">
                @if($img)<img src="{{ $img }}" alt="" loading="lazy">@elseif($prod)<img class="lj-bannerzinho__produto" src="{{ $prod['foto'] }}" alt="" loading="lazy">@endif
                <span class="lj-bannerzinho__texto">
                    <strong>{{ $b['titulo'] ?? '' }}</strong>
                    @if(filled($b['texto'] ?? ''))<small>{{ $b['texto'] }}</small>@endif
                    <em>Ver <i class="bi bi-arrow-right" aria-hidden="true"></i></em>
                </span>
            </a>
        @endforeach
    </div>
</section>
@elseif($ctx->rascunho)
<section class="lj-caixa lj-secao"><div class="lj-vazio">Grade de banners: adicione pelo menos um banner.</div></section>
@endif
