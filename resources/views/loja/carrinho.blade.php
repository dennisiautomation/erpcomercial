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
        @if(session('loja_erro'))<div class="lj-aviso lj-aviso--erro" role="alert"><i class="bi bi-exclamation-circle"></i><span>{{ session('loja_erro') }}</span></div>@endif
        @if(session('loja_ok'))<div class="lj-aviso lj-aviso--ok" role="status"><i class="bi bi-check-circle"></i><span>{{ session('loja_ok') }}</span></div>@endif
        @if($cupom && ! $cupomOk)<div class="lj-aviso lj-aviso--erro"><i class="bi bi-exclamation-circle"></i><span>O cupom {{ $cupom }} não vale mais para este carrinho: {{ $cupomMensagem }}</span></div>@endif
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
                    @if($desconto > 0)<dt class="lj-desconto">Cupom {{ $cupom }}</dt><dd class="lj-desconto">− R$ {{ number_format($desconto, 2, ',', '.') }}</dd>@endif
                    <dt>Entrega</dt><dd>{{ $freteGratis && $loja->opcao('entrega') ? 'frete grátis' : 'no próximo passo' }}</dd>
                    <dt class="lj-total">Subtotal</dt><dd class="lj-total">R$ {{ number_format($subtotal - $desconto, 2, ',', '.') }}</dd>
                </dl>
                @if($freteGratisAcima > 0 && $loja->opcao('entrega'))
                    <div class="lj-meta">
                        @if($faltaFreteGratis > 0)Faltam <strong>R$ {{ number_format($faltaFreteGratis, 2, ',', '.') }}</strong> para o frete grátis.
                        @else<strong style="color:var(--ok)"><i class="bi bi-truck"></i> Você ganhou frete grátis.</strong>@endif
                        <div class="lj-meta__barra" aria-hidden="true"><i style="--p:{{ min(100, (int) round(($subtotal - $desconto) / $freteGratisAcima * 100)) }}%"></i></div>
                    </div>
                @endif
                @if($cupom && $cupomOk)
                    <form class="lj-cupom-aplicado" method="post" action="{{ $ctx->caminho('carrinho/cupom') }}">
                        @csrf <input type="hidden" name="remover" value="1">
                        <span><i class="bi bi-ticket-perforated"></i> <strong>{{ $cupom }}</strong>@if($cupomDeFrete) · frete grátis @endif</span>
                        <button type="submit">Tirar</button>
                    </form>
                @else
                    <form class="lj-cupom-form" method="post" action="{{ $ctx->caminho('carrinho/cupom') }}">
                        @csrf
                        <label class="lj-so-leitor" for="lj-cupom">Cupom de desconto</label>
                        <input class="lj-campo" id="lj-cupom" name="codigo" value="{{ old('codigo') }}" placeholder="Cupom de desconto" maxlength="30" autocomplete="off">
                        <button class="lj-btn lj-btn--linha lj-btn--p" type="submit">Aplicar</button>
                    </form>
                @endif
                @if($pecasMinimo > 0)
                    <div class="lj-pecas" data-lj-pecas data-ok="{{ $faltamPecas > 0 ? 'nao' : 'sim' }}">
                        <p><strong>{{ $pecas }} de {{ $pecasMinimo }} peças</strong> @if($faltamPecas > 0) — {{ $faltamPecas === 1 ? 'falta 1 peça' : "faltam {$faltamPecas} peças" }} para fechar o pedido. Pode misturar produtos. @else — mínimo do atacado atingido. @endif </p>
                        <div class="lj-meta__barra" aria-hidden="true"><i style="--p:{{ min(100, (int) round($pecas / $pecasMinimo * 100)) }}%"></i></div>
                    </div>
                @endif
                @if($subtotal < $pedidoMinimo)
                    <div class="lj-aviso lj-aviso--nota" style="margin:1rem 0 0"><i class="bi bi-info-circle"></i><span>O pedido mínimo desta loja é de R$ {{ number_format($pedidoMinimo, 2, ',', '.') }}. Faltam R$ {{ number_format($pedidoMinimo - $subtotal, 2, ',', '.') }}.</span></div>
                    <span class="lj-btn lj-btn--cheio" aria-disabled="true" style="margin-top:1rem">Finalizar compra</span>
                @elseif($faltamPecas > 0)
                    <span class="lj-btn lj-btn--cheio" aria-disabled="true" style="margin-top:1rem">Finalizar compra</span>
                    <a class="lj-btn lj-btn--linha lj-btn--cheio" style="margin-top:.6rem" href="{{ $ctx->url('produtos') }}">Escolher mais peças</a>
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
