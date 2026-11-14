@if(filled($bloco['video'] ?? ''))
<section class="lj-caixa lj-secao">
    @if(filled($bloco['titulo'] ?? ''))<div class="lj-secao__topo"><h2>{{ $bloco['titulo'] }}</h2></div>@endif
    {{-- O vídeo só é carregado (e o YouTube só recebe cookies) quando a pessoa clica --}}
    <button type="button" class="lj-video" data-lj-video="{{ $bloco['video'] }}" aria-label="Assistir ao vídeo{{ filled($bloco['titulo'] ?? '') ? ': ' . $bloco['titulo'] : '' }}">
        <img src="https://i.ytimg.com/vi/{{ $bloco['video'] }}/hqdefault.jpg" alt="" loading="lazy">
        <span><i class="bi bi-play-fill" aria-hidden="true"></i></span>
    </button>
</section>
@elseif($ctx->rascunho)
<section class="lj-caixa lj-secao"><div class="lj-vazio">Vídeo: cole o endereço de um vídeo do YouTube.</div></section>
@endif
