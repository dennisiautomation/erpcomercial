@php($link = $vinculo($bloco['botao_link'] ?? ''))
@php($fim = filled($bloco['termina_em'] ?? '') ? \Illuminate\Support\Carbon::parse($bloco['termina_em']) : null)
@if(! $fim || $fim->isFuture() || $ctx->rascunho)
<section class="lj-caixa lj-secao">
    <div class="lj-oferta">
        <div>
            <h2>{{ $bloco['titulo'] ?? '' }}</h2>
            @if(filled($bloco['texto'] ?? ''))<p>{{ $bloco['texto'] }}</p>@endif
            @if(filled($bloco['cupom'] ?? ''))
                <p class="lj-oferta__cupom">Use o cupom
                    <button type="button" class="lj-cupom" data-lj-copiar-texto="{{ mb_strtoupper($bloco['cupom']) }}" title="Copiar o cupom">{{ mb_strtoupper($bloco['cupom']) }} <i class="bi bi-copy" aria-hidden="true"></i></button>
                </p>
            @endif
        </div>
        <div class="lj-oferta__lado">
            @if($fim)
                <div class="lj-relogio" data-lj-relogio="{{ $fim->toIso8601String() }}" role="timer" aria-label="Tempo restante da oferta">
                    <span><b data-u="d">00</b><small>dias</small></span><span><b data-u="h">00</b><small>horas</small></span><span><b data-u="m">00</b><small>min</small></span><span><b data-u="s">00</b><small>seg</small></span>
                </div>
            @endif
            @if($link && filled($bloco['botao_texto'] ?? ''))<a class="lj-btn lj-btn--inverso-oferta" href="{{ $link }}">{{ $bloco['botao_texto'] }}</a>@endif
        </div>
    </div>
</section>
@endif
