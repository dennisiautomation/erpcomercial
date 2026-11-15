@php($verTodos = match($bloco['origem'] ?? '') { 'promocoes' => 'produtos?promocao=1', 'categoria' => 'produtos?categoria=' . ($bloco['categoria_id'] ?? ''), 'destaques' => 'produtos?destaque=1', 'novidades' => 'produtos?ordem=novos', default => 'produtos' })
@php($carrossel = ($bloco['formato'] ?? 'grade') === 'carrossel' && ($tema['cartao'] ?? '') !== 'lista')
<section class="lj-caixa lj-secao">
    <div class="lj-secao__topo">
        <h2>{{ $bloco['titulo'] ?: 'Produtos' }}</h2>
        @if($bloco['produtos']->isNotEmpty())
            <div class="lj-secao__acoes">
                <a class="lj-link" href="{{ $ctx->url($verTodos) }}">Ver todos</a>
                @if($carrossel)
                    <span class="lj-setas" data-lj-setas>
                        <button type="button" data-d="-1" aria-label="Produtos anteriores"><i class="bi bi-arrow-left" aria-hidden="true"></i></button>
                        <button type="button" data-d="1" aria-label="Próximos produtos"><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
                    </span>
                @endif
            </div>
        @endif
    </div>
    @if($bloco['produtos']->isEmpty())
        <div class="lj-vazio">Esta fileira está vazia: nenhum produto da loja se encaixa em “{{ ['destaques' => 'marcados como destaque', 'novidades' => 'os mais novos', 'promocoes' => 'em promoção', 'categoria' => 'da categoria escolhida', 'todos' => 'todos'][$bloco['origem'] ?? 'novidades'] ?? '' }}”. O público não vê fileira vazia.</div>
    @else
        @php($col = max(1, (int) ($bloco['colunas'] ?? 4)))
        @php($n = $bloco['produtos']->count())
        @php($lista = ! $carrossel && ($tema['cartao'] ?? '') !== 'lista' && $n > $col && $n % $col ? $bloco['produtos']->take($n - $n % $col) : $bloco['produtos'])
        <div class="lj-grade @if($carrossel) lj-grade--carrossel @endif @if(($bloco['origem'] ?? '') === 'categoria') lj-sem-cat @endif" style="--colunas:{{ $col }}" @if($carrossel) data-lj-trilho tabindex="0" @endif>
            @foreach($lista as $p) @include('loja.partials.cartao', ['p' => $p]) @endforeach
        </div>
    @endif
</section>
