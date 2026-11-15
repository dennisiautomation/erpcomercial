@php($lista = collect($bloco['lista'] ?? [])->filter(fn ($d) => filled($d['texto'] ?? '')))
@if($lista->isNotEmpty())
<section class="lj-caixa lj-secao">
    @if(filled($bloco['titulo'] ?? ''))<div class="lj-secao__topo"><h2>{{ $bloco['titulo'] }}</h2></div>@endif
    <div class="lj-falas" style="--n:{{ min(3, $lista->count()) }}">
        @foreach($lista as $d)
            <figure>
                <blockquote>{{ $d['texto'] }}</blockquote>
                @if(filled($d['nome'] ?? ''))<figcaption><strong>{{ $d['nome'] }}</strong>@if(filled($d['detalhe'] ?? '')) <span>{{ $d['detalhe'] }}</span>@endif</figcaption>@endif
            </figure>
        @endforeach
    </div>
</section>
@elseif($ctx->rascunho)
<section class="lj-caixa lj-secao"><div class="lj-vazio">Depoimentos: escreva pelo menos um para o bloco aparecer.</div></section>
@endif
