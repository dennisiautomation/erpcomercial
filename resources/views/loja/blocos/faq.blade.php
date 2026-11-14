@php($lista = collect($bloco['lista'] ?? [])->filter(fn ($d) => filled($d['nome'] ?? '') && filled($d['texto'] ?? '')))
@if($lista->isNotEmpty())
<section class="lj-caixa lj-secao">
    <div class="lj-faq">
        @if(filled($bloco['titulo'] ?? ''))<h2>{{ $bloco['titulo'] }}</h2>@endif
        <div>
            @foreach($lista as $d)
                <details><summary>{{ $d['nome'] }}</summary><p>{{ $d['texto'] }}</p></details>
            @endforeach
        </div>
    </div>
</section>
@elseif($ctx->rascunho)
<section class="lj-caixa lj-secao"><div class="lj-vazio">Perguntas frequentes: escreva pelo menos uma pergunta com resposta.</div></section>
@endif
