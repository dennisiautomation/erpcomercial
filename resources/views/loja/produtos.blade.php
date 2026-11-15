@extends('loja.layout')

@section('conteudo')
@php
    $tituloLista = filled($filtros['q']) ? 'Busca por “' . $filtros['q'] . '”' : ($categoriaAtual->nome ?? ($filtros['promocao'] ? 'Ofertas' : ($filtros['destaque'] ? 'Destaques' : 'Todos os produtos')));
    $com = fn (array $troca) => $ctx->caminho('produtos') . '?' . http_build_query(array_filter(array_merge(
        ['q' => $filtros['q'], 'categoria' => $filtros['categoria'], 'ordem' => $filtros['ordem'], 'promocao' => $filtros['promocao'] ? 1 : null,
         'destaque' => $filtros['destaque'] ? 1 : null, 'preco_min' => $filtros['preco_min'], 'preco_max' => $filtros['preco_max'], 'rascunho' => $ctx->rascunho ? 1 : null], $troca), fn ($v) => $v !== null && $v !== '' && $v !== false));
    $ativos = array_filter([
        'q' => filled($filtros['q']) ? '“' . $filtros['q'] . '”' : null,
        'categoria' => $categoriaAtual->nome ?? null,
        'promocao' => $filtros['promocao'] ? 'Em oferta' : null,
        'destaque' => $filtros['destaque'] ? 'Destaques' : null,
        'preco_min' => is_numeric($filtros['preco_min']) && $filtros['preco_min'] > 0 ? 'A partir de R$ ' . number_format((float) $filtros['preco_min'], 0, ',', '.') : null,
        'preco_max' => is_numeric($filtros['preco_max']) && $filtros['preco_max'] > 0 ? 'Até R$ ' . number_format((float) $filtros['preco_max'], 0, ',', '.') : null,
    ]);
@endphp
<div class="lj-caixa">
    <nav class="lj-trilha" aria-label="Você está em">
        <a href="{{ $ctx->url() }}">Início</a> <span>/</span>
        @if($categoriaAtual)<a href="{{ $ctx->url('produtos') }}">Produtos</a> <span>/</span> <span>{{ $categoriaAtual->nome }}</span>@else<span>Produtos</span>@endif
    </nav>
    <div class="lj-pagina__topo">
        <div>
            <h1>{{ $tituloLista }}</h1>
            <p style="color:var(--suave);margin-top:.3rem">{{ $produtos->total() }} {{ $produtos->total() === 1 ? 'produto' : 'produtos' }}</p>
        </div>
        <div class="lj-filtros">
            <button type="button" class="lj-btn lj-btn--linha lj-btn--p lj-filtros__abrir" data-lj-filtros-abrir><i class="bi bi-sliders" aria-hidden="true"></i> Filtrar @if(count($ativos))<span class="lj-conta" style="position:static">{{ count($ativos) }}</span>@endif</button>
            <form method="get" action="{{ $ctx->caminho('produtos') }}">
                @foreach(['q', 'categoria', 'preco_min', 'preco_max'] as $k) @if(filled($filtros[$k]))<input type="hidden" name="{{ $k }}" value="{{ $filtros[$k] }}">@endif @endforeach
                @if($filtros['promocao'])<input type="hidden" name="promocao" value="1">@endif
                @if($filtros['destaque'])<input type="hidden" name="destaque" value="1">@endif
                @if($ctx->rascunho)<input type="hidden" name="rascunho" value="1">@endif
                <label class="lj-so-leitor" for="lj-ordem">Ordenar por</label>
                <select class="lj-campo" id="lj-ordem" name="ordem" onchange="this.form.submit()">
                    <option value="" @selected(blank($filtros['ordem']))>Destaques primeiro</option>
                    <option value="novos" @selected($filtros['ordem'] === 'novos')>Mais novos</option>
                    <option value="menor" @selected($filtros['ordem'] === 'menor')>Menor preço</option>
                    <option value="maior" @selected($filtros['ordem'] === 'maior')>Maior preço</option>
                    <option value="nome" @selected($filtros['ordem'] === 'nome')>Nome (A–Z)</option>
                </select>
                <noscript><button class="lj-btn lj-btn--p" type="submit">Ordenar</button></noscript>
            </form>
        </div>
    </div>

    @if(count($ativos))
        <div class="lj-chips" aria-label="Filtros aplicados">
            @foreach($ativos as $chave => $rotulo)
                <a href="{{ $com([$chave => null]) }}" aria-label="Tirar o filtro {{ $rotulo }}">{{ $rotulo }} <i class="bi bi-x" aria-hidden="true"></i></a>
            @endforeach
            <a class="lj-chips__limpar" href="{{ $ctx->url('produtos') }}">Limpar tudo</a>
        </div>
    @endif

    <div class="lj-lista">
        <aside class="lj-lateral" data-lj-filtros aria-label="Filtros">
            <div class="lj-lateral__topo"><strong>Filtrar</strong><button type="button" class="lj-icone" data-lj-filtros-fechar aria-label="Fechar os filtros"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
            <div class="lj-lateral__grupo">
                <h2>Categorias</h2>
                <ul>
                    <li><a href="{{ $com(['categoria' => null]) }}" @if(! $categoriaAtual) aria-current="true" @endif>Todas <small>{{ $categoriasMenu->sum('total') }}</small></a></li>
                    @foreach($categoriasMenu as $c)
                        <li><a href="{{ $com(['categoria' => $c['id']]) }}" @if((int) $filtros['categoria'] === $c['id']) aria-current="true" @endif>{{ $c['nome'] }} <small>{{ $c['total'] }}</small></a></li>
                    @endforeach
                </ul>
            </div>
            <div class="lj-lateral__grupo">
                <h2>Preço</h2>
                <form method="get" action="{{ $ctx->caminho('produtos') }}" class="lj-faixa-preco">
                    @foreach(['q', 'categoria', 'ordem'] as $k) @if(filled($filtros[$k]))<input type="hidden" name="{{ $k }}" value="{{ $filtros[$k] }}">@endif @endforeach
                    @if($filtros['promocao'])<input type="hidden" name="promocao" value="1">@endif
                    @if($filtros['destaque'])<input type="hidden" name="destaque" value="1">@endif
                    @if($ctx->rascunho)<input type="hidden" name="rascunho" value="1">@endif
                    <label class="lj-so-leitor" for="lj-pmin">Preço mínimo</label>
                    <input class="lj-campo" id="lj-pmin" type="number" name="preco_min" min="0" step="1" inputmode="numeric" placeholder="De R$ {{ number_format($faixa['menor'], 0, ',', '.') }}" value="{{ $filtros['preco_min'] }}">
                    <label class="lj-so-leitor" for="lj-pmax">Preço máximo</label>
                    <input class="lj-campo" id="lj-pmax" type="number" name="preco_max" min="0" step="1" inputmode="numeric" placeholder="Até R$ {{ number_format($faixa['maior'], 0, ',', '.') }}" value="{{ $filtros['preco_max'] }}">
                    <button class="lj-btn lj-btn--linha lj-btn--p" type="submit">Aplicar</button>
                </form>
            </div>
            <div class="lj-lateral__grupo">
                <h2>Mostrar só</h2>
                <ul>
                    @if($temOfertas ?? false)<li><a href="{{ $com(['promocao' => $filtros['promocao'] ? null : 1]) }}" @if($filtros['promocao']) aria-current="true" @endif><span class="lj-caixinha"></span> Em oferta</a></li>@endif
                    <li><a href="{{ $com(['destaque' => $filtros['destaque'] ? null : 1]) }}" @if($filtros['destaque']) aria-current="true" @endif><span class="lj-caixinha"></span> Destaques da loja</a></li>
                </ul>
            </div>
        </aside>

        <div>
            @if($produtos->isEmpty())
                <div class="lj-vazio-grande">
                    <i class="bi bi-search"></i>
                    <h2>Não encontramos nada aqui</h2>
                    <p>@if(count($ativos))Tire algum filtro, tente outra palavra ou veja tudo o que a loja tem.@else Esta parte da loja ainda está sem produtos.@endif</p>
                    <a class="lj-btn" href="{{ $ctx->url('produtos') }}">Ver todos os produtos</a>
                </div>
            @else
                <div class="lj-grade @if($categoriaAtual) lj-sem-cat @endif" style="--colunas:{{ ($tema['cartao'] ?? '') === 'lista' ? 2 : 3 }}">
                    @foreach($produtos as $p) @include('loja.partials.cartao', ['p' => $p]) @endforeach
                </div>
                @if($produtos->hasPages())
                    <nav class="lj-paginas" aria-label="Páginas">
                        @if($produtos->onFirstPage())<span aria-disabled="true" style="opacity:.4">‹</span>@else<a href="{{ $produtos->previousPageUrl() }}" rel="prev" aria-label="Página anterior">‹</a>@endif
                        @foreach(range(max(1, $produtos->currentPage() - 2), min($produtos->lastPage(), $produtos->currentPage() + 2)) as $n)
                            @if($n === $produtos->currentPage())<span aria-current="page">{{ $n }}</span>@else<a href="{{ $produtos->url($n) }}">{{ $n }}</a>@endif
                        @endforeach
                        @if($produtos->hasMorePages())<a href="{{ $produtos->nextPageUrl() }}" rel="next" aria-label="Próxima página">›</a>@else<span aria-disabled="true" style="opacity:.4">›</span>@endif
                    </nav>
                @endif
            @endif
        </div>
    </div>
</div>
<div style="height:clamp(2rem,5vw,3.5rem)"></div>
@endsection

@push('scripts')
<script>
@if(filled($filtros['q']))
LJ.rastro.evento('busca', {termo: {!! json_encode(mb_substr((string) $filtros['q'], 0, 80), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}});
@endif
(function () {
    var lateral = document.querySelector('[data-lj-filtros]');
    document.addEventListener('click', function (ev) {
        if (ev.target.closest('[data-lj-filtros-abrir]')) { lateral.classList.add('aberta'); document.body.classList.add('lj-trava'); }
        if (ev.target.closest('[data-lj-filtros-fechar]')) { lateral.classList.remove('aberta'); document.body.classList.remove('lj-trava'); }
    });
})();
</script>
@endpush
