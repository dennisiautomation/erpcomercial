@php($estilo = $bloco['estilo'] ?? 'dividido')
@php($img = \App\Services\LojaOnline\LojaContexto::arquivo($bloco['imagem'] ?? null))
@php($tag = !empty($h1) ? 'h1' : 'h2')
@php($link = $vinculo($bloco['botao_link'] ?? ''))
<section class="lj-banner lj-banner--{{ $estilo }} @if($img) lj-banner--foto @endif" data-altura="{{ $bloco['altura'] ?? 'media' }}">
    @if($img && $estilo !== 'editorial')<img class="lj-banner__img" src="{{ $img }}" alt="">@endif
    <div class="lj-caixa">
        <div class="lj-banner__dentro">
            @if($estilo === 'editorial')
                <{{ $tag }}>{{ $bloco['titulo'] ?? '' }}</{{ $tag }}>
                <div class="lj-banner__pe">
                    @if(filled($bloco['subtitulo'] ?? ''))<p class="lj-banner__sub">{{ $bloco['subtitulo'] }}</p>@endif
                    @if($link && filled($bloco['botao_texto'] ?? ''))<a class="lj-btn" href="{{ $link }}">{{ $bloco['botao_texto'] }} <i class="bi bi-arrow-right"></i></a>@endif
                </div>
                @if($img)<img class="lj-banner__img" src="{{ $img }}" alt="">@else @include('loja.partials.mosaico') @endif
            @else
                <div>
                    <{{ $tag }}>{{ $bloco['titulo'] ?? '' }}</{{ $tag }}>
                    @if(filled($bloco['subtitulo'] ?? ''))<p class="lj-banner__sub">{{ $bloco['subtitulo'] }}</p>@endif
                    @if($link && filled($bloco['botao_texto'] ?? ''))
                        <a class="lj-btn {{ $estilo === 'centro' && !$img ? '' : 'lj-btn--inverso' }}" href="{{ $link }}">{{ $bloco['botao_texto'] }}</a>
                    @endif
                </div>
                @unless($img) @include('loja.partials.mosaico') @endunless
            @endif
        </div>
    </div>
</section>
