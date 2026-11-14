@extends('loja.layout')

@section('conteudo')
<div class="lj-caixa">
    <nav class="lj-trilha" aria-label="Você está em">
        <a href="{{ $ctx->url() }}">Início</a> <span>/</span>
        @if($categoriaAtual)<a href="{{ $ctx->url('produtos') }}">Produtos</a> <span>/</span> <span>{{ $categoriaAtual->nome }}</span>@else<span>Produtos</span>@endif
    </nav>
    <div class="lj-pagina__topo">
        <div>
            <h1>@if(filled($filtros['q']))Busca por “{{ $filtros['q'] }}”@elseif($categoriaAtual){{ $categoriaAtual->nome }}@elseif($filtros['promocao'])Promoções@else Todos os produtos @endif</h1>
            <p style="color:var(--suave);margin-top:.3rem">{{ $produtos->total() }} {{ $produtos->total() === 1 ? 'produto' : 'produtos' }}</p>
        </div>
        <form class="lj-filtros" method="get" action="{{ $ctx->url('produtos') }}">
            @foreach(['q', 'categoria'] as $k) @if(filled($filtros[$k]))<input type="hidden" name="{{ $k }}" value="{{ $filtros[$k] }}">@endif @endforeach
            @if($filtros['promocao'])<input type="hidden" name="promocao" value="1">@endif
            @if($ctx->rascunho)<input type="hidden" name="rascunho" value="1">@endif
            <label class="lj-so-leitor" for="lj-ordem">Ordenar por</label>
            <select class="lj-campo" id="lj-ordem" name="ordem" onchange="this.form.submit()">
                <option value="" @selected(blank($filtros['ordem']))>Destaques primeiro</option>
                <option value="menor" @selected($filtros['ordem'] === 'menor')>Menor preço</option>
                <option value="maior" @selected($filtros['ordem'] === 'maior')>Maior preço</option>
                <option value="nome" @selected($filtros['ordem'] === 'nome')>Nome (A–Z)</option>
            </select>
            <noscript><button class="lj-btn lj-btn--p" type="submit">Ordenar</button></noscript>
        </form>
    </div>

    @if($produtos->isEmpty())
        <div class="lj-vazio-grande">
            <i class="bi bi-search"></i>
            <h2>Não encontramos nada aqui</h2>
            <p>@if(filled($filtros['q']))Tente outra palavra, ou veja tudo o que a loja tem.@else Esta parte da loja ainda está sem produtos.@endif</p>
            <a class="lj-btn" href="{{ $ctx->url('produtos') }}">Ver todos os produtos</a>
        </div>
    @else
        <div class="lj-grade" style="--colunas:4">
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
<div style="height:clamp(2rem,5vw,3.5rem)"></div>
@endsection
