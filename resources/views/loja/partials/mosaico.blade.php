@if(($bloco['mosaico'] ?? collect())->isNotEmpty())
<div class="lj-mosaico">
    @foreach($bloco['mosaico'] as $m)
        <a href="{{ $ctx->url($m['caminho']) }}" aria-label="{{ $m['nome'] }}, R$ {{ number_format($m['preco'], 2, ',', '.') }}">
            <img src="{{ $m['foto'] }}" alt="" @if(!$loop->first) loading="lazy" @endif>
            <span>R$ {{ number_format($m['preco'], 2, ',', '.') }}</span>
        </a>
    @endforeach
</div>
@endif
