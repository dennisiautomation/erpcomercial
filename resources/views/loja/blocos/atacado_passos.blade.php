{{-- Como comprar no atacado (bloco do modelo exclusivo Cell Master, 27/11/2026).
     O número grande é a regra da loja (lojas_online.config.pecas_minimo): muda em Configurações, muda aqui. --}}
@php
    $minimoPecas = (int) $loja->opcao('pecas_minimo');
    $passos = collect($bloco['lista'] ?? [])->filter(fn ($p) => filled($p['nome'] ?? ''))->values();
@endphp
@if($minimoPecas > 0 || $passos->isNotEmpty())
<section class="lj-atacado" aria-labelledby="lj-atacado-{{ $bloco['id'] ?? 'x' }}">
    <div class="lj-caixa lj-atacado__dentro">
        <div class="lj-atacado__regra">
            @if($minimoPecas > 0)
                <p class="lj-atacado__numero"><b>{{ $minimoPecas }}</b><span>{{ $minimoPecas === 1 ? 'peça' : 'peças' }}<br>é o pedido mínimo</span></p>
            @endif
            <h2 id="lj-atacado-{{ $bloco['id'] ?? 'x' }}">{{ $bloco['titulo'] ?: 'Como comprar no atacado' }}</h2>
            @if(filled($bloco['texto'] ?? ''))<p class="lj-atacado__apoio">{{ $bloco['texto'] }}</p>@endif
        </div>
        @if($passos->isNotEmpty())
            <ol class="lj-atacado__passos">
                @foreach($passos as $p)
                    <li><strong>{{ $p['nome'] }}</strong>@if(filled($p['texto'] ?? ''))<span>{{ $p['texto'] }}</span>@endif</li>
                @endforeach
            </ol>
        @endif
    </div>
</section>
@elseif($ctx->rascunho)
<section class="lj-caixa lj-secao"><div class="lj-vazio">Como comprar no atacado: escreva pelo menos um passo, ou defina o mínimo de peças em Loja online → Configurações.</div></section>
@endif
