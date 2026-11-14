@extends('loja.layout')

@section('conteudo')
<div class="lj-caixa">
    @if($itens->isEmpty())
        <div class="lj-vazio-grande">
            <i class="bi bi-bag"></i>
            <h1>Seu carrinho está vazio</h1>
            @foreach($avisos as $a)<div class="lj-aviso lj-aviso--erro" style="max-width:520px;margin:1rem auto;text-align:left"><i class="bi bi-exclamation-circle"></i><span>{{ $a }}</span></div>@endforeach
            <p>Escolha o que quiser na loja: o que você adicionar aparece aqui.</p>
            <a class="lj-btn" href="{{ $ctx->url('produtos') }}">Ver produtos</a>
        </div>
    @else
        <div class="lj-pagina__topo"><h1>Carrinho</h1></div>
        @if(session('loja_erro'))<div class="lj-aviso lj-aviso--erro"><i class="bi bi-exclamation-circle"></i><span>{{ session('loja_erro') }}</span></div>@endif
        @foreach($avisos as $a)<div class="lj-aviso lj-aviso--erro"><i class="bi bi-exclamation-circle"></i><span>{{ $a }}</span></div>@endforeach
        <div class="lj-duas">
            <div>
                @foreach($itens as $i)
                    <div class="lj-item">
                        <a class="lj-item__foto" href="{{ $ctx->url($i['caminho']) }}" tabindex="-1" aria-hidden="true">
                            @if($i['foto'])<img src="{{ $i['foto'] }}" alt="">@else<span class="lj-semfoto">{{ mb_substr($i['nome'], 0, 1) }}</span>@endif
                        </a>
                        <div>
                            <a class="lj-item__nome" href="{{ $ctx->url($i['caminho']) }}">{{ $i['nome'] }}</a>
                            <small>R$ {{ number_format($i['preco'], 2, ',', '.') }} cada</small>
                            <div class="lj-item__acoes">
                                <form method="post" action="{{ $ctx->url('carrinho/atualizar') }}" class="lj-qtd" data-lj-qtd-auto>
                                    @csrf
                                    <input type="hidden" name="produto_id" value="{{ $i['id'] }}">
                                    <button type="submit" name="quantidade" value="{{ $i['quantidade'] - 1 }}" aria-label="Diminuir quantidade de {{ $i['nome'] }}">−</button>
                                    <input type="number" value="{{ $i['quantidade'] }}" min="0" max="999" inputmode="numeric" aria-label="Quantidade de {{ $i['nome'] }}" data-lj-qtd-campo>
                                    <button type="submit" name="quantidade" value="{{ $i['quantidade'] + 1 }}" aria-label="Aumentar quantidade de {{ $i['nome'] }}">+</button>
                                </form>
                                <form method="post" action="{{ $ctx->url('carrinho/atualizar') }}">
                                    @csrf
                                    <input type="hidden" name="produto_id" value="{{ $i['id'] }}"><input type="hidden" name="quantidade" value="0">
                                    <button class="lj-tirar" type="submit">Tirar</button>
                                </form>
                            </div>
                        </div>
                        <div class="lj-item__total">R$ {{ number_format($i['total'], 2, ',', '.') }}</div>
                    </div>
                @endforeach
                <p style="margin-top:1.2rem"><a class="lj-link" href="{{ $ctx->url('produtos') }}">← Continuar comprando</a></p>
            </div>
            <aside class="lj-resumo" aria-label="Resumo da compra">
                <h2>Resumo</h2>
                <dl>
                    <dt>Produtos ({{ $itens->sum('quantidade') }})</dt><dd>R$ {{ number_format($subtotal, 2, ',', '.') }}</dd>
                    <dt>Entrega</dt><dd>no próximo passo</dd>
                    <dt class="lj-total">Subtotal</dt><dd class="lj-total">R$ {{ number_format($subtotal, 2, ',', '.') }}</dd>
                </dl>
                @if($subtotal < $pedidoMinimo)
                    <div class="lj-aviso lj-aviso--nota" style="margin:1rem 0 0"><i class="bi bi-info-circle"></i><span>O pedido mínimo desta loja é de R$ {{ number_format($pedidoMinimo, 2, ',', '.') }}. Faltam R$ {{ number_format($pedidoMinimo - $subtotal, 2, ',', '.') }}.</span></div>
                    <span class="lj-btn lj-btn--cheio" aria-disabled="true" style="margin-top:1rem">Finalizar compra</span>
                @else
                    <a class="lj-btn lj-btn--cheio" style="margin-top:1.2rem" href="{{ $ctx->url('finalizar') }}">Finalizar compra</a>
                @endif
            </aside>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-lj-qtd-auto]').forEach(function (f) {
    var campo = f.querySelector('[data-lj-qtd-campo]');
    campo.addEventListener('change', function () {
        var h = document.createElement('input'); h.type = 'hidden'; h.name = 'quantidade';
        h.value = Math.max(0, Math.min(999, parseInt(campo.value, 10) || 0));
        f.appendChild(h); f.submit();
    });
});
</script>
@endpush
