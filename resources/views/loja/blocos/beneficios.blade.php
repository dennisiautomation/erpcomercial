@php($itens = collect($bloco['itens'] ?? [])->filter(fn ($i) => filled($i['titulo'] ?? '')))
@if($itens->isNotEmpty())
<section class="lj-caixa" aria-label="Vantagens de comprar aqui">
    <div class="lj-vantagens" style="--n:{{ $itens->count() }}">
        @foreach($itens as $i)
            <div><i class="bi bi-{{ $i['icone'] }}" aria-hidden="true"></i><p><strong>{{ $i['titulo'] }}</strong><span>{{ $i['texto'] ?? '' }}</span></p></div>
        @endforeach
    </div>
</section>
@endif
