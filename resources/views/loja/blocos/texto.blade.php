<section class="lj-caixa lj-secao">
    <div class="lj-texto" data-al="{{ $bloco['alinhamento'] ?? 'centro' }}">
        @if(filled($bloco['titulo'] ?? ''))<h2>{{ $bloco['titulo'] }}</h2>@endif
        @if(filled($bloco['texto'] ?? ''))<p>{{ $bloco['texto'] }}</p>@endif
    </div>
</section>
