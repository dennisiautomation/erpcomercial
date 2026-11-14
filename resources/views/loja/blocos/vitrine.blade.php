@php($verTodos = match($bloco['origem'] ?? '') { 'promocoes' => 'produtos?promocao=1', 'categoria' => 'produtos?categoria=' . ($bloco['categoria_id'] ?? ''), default => 'produtos' })
<section class="lj-caixa lj-secao">
    <div class="lj-secao__topo">
        <h2>{{ $bloco['titulo'] ?: 'Produtos' }}</h2>
        @if($bloco['produtos']->isNotEmpty())<a class="lj-link" href="{{ $ctx->url($verTodos) }}">Ver todos</a>@endif
    </div>
    @if($bloco['produtos']->isEmpty())
        <div class="lj-vazio">Esta fileira está vazia: nenhum produto da loja se encaixa em “{{ ['destaques' => 'marcados como destaque', 'novidades' => 'os mais novos', 'promocoes' => 'em promoção', 'categoria' => 'da categoria escolhida', 'todos' => 'todos'][$bloco['origem'] ?? 'novidades'] ?? '' }}”. O público não vê fileira vazia.</div>
    @else
        {{-- sem órfão: sobra da última linha (no computador) não aparece --}}
        @php($col = max(1, (int) ($bloco['colunas'] ?? 4)))
        @php($n = $bloco['produtos']->count())
        @php($lista = ($tema['cartao'] ?? '') !== 'lista' && $n > $col && $n % $col ? $bloco['produtos']->take($n - $n % $col) : $bloco['produtos'])
        <div class="lj-grade @if(($bloco['origem'] ?? '') === 'categoria') lj-sem-cat @endif" style="--colunas:{{ $col }}">
            @foreach($lista as $p) @include('loja.partials.cartao', ['p' => $p]) @endforeach
        </div>
    @endif
</section>
