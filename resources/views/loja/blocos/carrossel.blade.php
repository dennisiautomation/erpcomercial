@php($slides = collect($bloco['lista'] ?? [])->filter(fn ($s) => filled($s['titulo'] ?? '') || filled($s['imagem'] ?? ''))->values())
@if($slides->isNotEmpty())
<section class="lj-carrossel" data-lj-carrossel data-altura="{{ $bloco['altura'] ?? 'media' }}" aria-roledescription="carrossel" aria-label="Destaques da loja">
    <div class="lj-carrossel__trilho" data-lj-trilho-banner>
        @foreach($slides as $i => $s)
            @php($img = \App\Services\LojaOnline\LojaContexto::arquivo($s['imagem'] ?? null))
            @php($link = $vinculo($s['botao_link'] ?? ''))
            @php($prod = $bloco['produtosSlide'][$i] ?? null)
            @php($tag = !empty($h1) && $i === 0 ? 'h1' : 'h2')
            <div class="lj-slide @if($img) lj-slide--foto @endif" data-tom="{{ $img ? ($s['tom'] ?? 'claro') : 'cor' }}" data-lado="{{ $s['lado'] ?? 'esquerda' }}" data-n="{{ $i % 3 }}"
                 role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} de {{ $slides->count() }}">
                @if($img)<img class="lj-slide__img" src="{{ $img }}" alt="" @if($i > 0) loading="lazy" @endif>@endif
                <div class="lj-caixa lj-slide__dentro">
                    <div class="lj-slide__texto">
                        @if(filled($s['titulo'] ?? ''))<{{ $tag }}>{{ $s['titulo'] }}</{{ $tag }}>@endif
                        @if(filled($s['texto'] ?? ''))<p>{{ $s['texto'] }}</p>@endif
                        @if($link && filled($s['botao_texto'] ?? ''))<a class="lj-btn" href="{{ $link }}">{{ $s['botao_texto'] }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>@endif
                    </div>
                    @if(! $img && $prod)
                        <a class="lj-slide__produto" href="{{ $ctx->url($prod['caminho']) }}" aria-label="{{ $prod['nome'] }}, R$ {{ number_format($prod['preco'], 2, ',', '.') }}">
                            <img src="{{ $prod['foto'] }}" alt="" @if($i > 0) loading="lazy" @endif>
                            <span><small>{{ $prod['nome'] }}</small><b>R$ {{ number_format($prod['preco'], 2, ',', '.') }}</b></span>
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    @if($slides->count() > 1)
        <button type="button" class="lj-carrossel__seta" data-d="-1" aria-label="Banner anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
        <button type="button" class="lj-carrossel__seta" data-d="1" aria-label="Próximo banner"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
        <div class="lj-carrossel__pontos">
            @foreach($slides as $i => $s)<button type="button" data-ir="{{ $i }}" aria-label="Ir para o banner {{ $i + 1 }}" @if($i === 0) aria-current="true" @endif></button>@endforeach
        </div>
    @endif
</section>
@elseif($ctx->rascunho)
<section class="lj-caixa lj-secao"><div class="lj-vazio">Carrossel: adicione pelo menos um banner com título ou imagem.</div></section>
@endif
