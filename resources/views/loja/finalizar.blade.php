@extends('loja.layout')

@section('pagina', 'compra')

@section('conteudo')
@php($entregaPadrao = old('entrega', $loja->opcao('retirada') && !$loja->opcao('entrega') ? 'retirada' : ($loja->opcao('entrega') && !$loja->opcao('retirada') ? 'entrega' : '')))
@php($pagPadrao = old('pagamento', $meios['pix'] ? 'pix' : ($meios['cartao'] ? 'cartao' : 'combinar')))
<div class="lj-caixa">
    <div class="lj-pagina__topo"><h1>Finalizar compra</h1></div>
    @if(session('loja_erro'))<div class="lj-aviso lj-aviso--erro" role="alert"><i class="bi bi-exclamation-circle"></i><span>{{ session('loja_erro') }}</span></div>@endif
    @if($errors->any())<div class="lj-aviso lj-aviso--erro" role="alert"><i class="bi bi-exclamation-circle"></i><span>Confira os campos marcados abaixo.</span></div>@endif

    <form class="lj-duas" method="post" action="{{ $ctx->url('finalizar') }}" data-lj-fechar novalidate>
        @csrf
        <div>
            <fieldset class="lj-passo">
                <legend><span>1</span> Seus dados</legend>
                <div class="lj-form">
                    <div>
                        <label for="f-nome">Nome completo</label>
                        <input class="lj-campo" id="f-nome" name="nome" value="{{ old('nome') }}" autocomplete="name" required @error('nome') aria-invalid="true" @enderror>
                        @error('nome')<p class="lj-erro">{{ $message }}</p>@enderror
                    </div>
                    <div class="c3">
                        <label for="f-tel">WhatsApp ou telefone <small>com DDD</small></label>
                        <input class="lj-campo" id="f-tel" name="telefone" value="{{ old('telefone') }}" inputmode="tel" autocomplete="tel" placeholder="(00) 00000-0000" required @error('telefone') aria-invalid="true" @enderror>
                        @error('telefone')<p class="lj-erro">{{ $message }}</p>@enderror
                    </div>
                    <div class="c3">
                        <label for="f-doc">CPF ou CNPJ @unless($exigeDocumento)<small>(opcional)</small>@endunless</label>
                        <input class="lj-campo" id="f-doc" name="cpf_cnpj" value="{{ old('cpf_cnpj') }}" inputmode="numeric" @if($exigeDocumento) required @endif @error('cpf_cnpj') aria-invalid="true" @enderror>
                        @error('cpf_cnpj')<p class="lj-erro">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="f-email">E-mail <small>(opcional)</small></label>
                        <input class="lj-campo" id="f-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" @error('email') aria-invalid="true" @enderror>
                        @error('email')<p class="lj-erro">{{ $message }}</p>@enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="lj-passo">
                <legend><span>2</span> Como você quer receber</legend>
                <div class="lj-opcoes lj-opcoes--lado">
                    @if($loja->opcao('retirada'))
                    <label class="lj-opcao">
                        <input type="radio" name="entrega" value="retirada" @checked($entregaPadrao === 'retirada')>
                        <span><strong>Retirar na loja</strong>
                        <small>{{ $unidade ? collect([trim(($unidade->logradouro ?? '') . ' ' . ($unidade->numero ?? '')), $unidade->bairro, $unidade->cidade])->filter()->implode(', ') ?: $unidade->nome : 'Sem frete' }}</small></span>
                        <em>Grátis</em>
                    </label>
                    @endif
                    @if($loja->opcao('entrega'))
                    <label class="lj-opcao">
                        <input type="radio" name="entrega" value="entrega" @checked($entregaPadrao === 'entrega')>
                        <span><strong>Receber no endereço</strong><small>Frete calculado pelo CEP</small></span>
                    </label>
                    @endif
                </div>
                @error('entrega')<p class="lj-erro">{{ $message }}</p>@enderror

                <div data-lj-endereco @if($entregaPadrao !== 'entrega') hidden @endif style="margin-top:1.2rem">
                    <div class="lj-form">
                        <div class="c2">
                            <label for="f-cep">CEP</label>
                            <input class="lj-campo" id="f-cep" name="cep" value="{{ old('cep') }}" inputmode="numeric" maxlength="9" placeholder="00000-000" autocomplete="postal-code" @error('cep') aria-invalid="true" @enderror>
                            @error('cep')<p class="lj-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="c4">
                            <label for="f-rua">Rua</label>
                            <input class="lj-campo" id="f-rua" name="logradouro" value="{{ old('logradouro') }}" autocomplete="address-line1" @error('logradouro') aria-invalid="true" @enderror>
                            @error('logradouro')<p class="lj-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="c2">
                            <label for="f-num">Número</label>
                            <input class="lj-campo" id="f-num" name="numero" value="{{ old('numero') }}" @error('numero') aria-invalid="true" @enderror>
                            @error('numero')<p class="lj-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="c4">
                            <label for="f-comp">Complemento <small>(opcional)</small></label>
                            <input class="lj-campo" id="f-comp" name="complemento" value="{{ old('complemento') }}" placeholder="Apto, bloco, referência">
                        </div>
                        <div class="c3">
                            <label for="f-bairro">Bairro</label>
                            <input class="lj-campo" id="f-bairro" name="bairro" value="{{ old('bairro') }}" @error('bairro') aria-invalid="true" @enderror>
                            @error('bairro')<p class="lj-erro">{{ $message }}</p>@enderror
                        </div>
                        <div class="c3">
                            <label for="f-cidade">Cidade / UF</label>
                            <input class="lj-campo" id="f-cidade" value="{{ old('cidade') ? old('cidade') . '/' . old('uf') : '' }}" readonly tabindex="-1" placeholder="Preenchido pelo CEP" data-lj-cidade>
                            <input type="hidden" name="cidade" value="{{ old('cidade') }}"><input type="hidden" name="uf" value="{{ old('uf') }}">
                        </div>
                    </div>
                    <div style="margin-top:1rem" data-lj-fretes aria-live="polite"></div>
                </div>
            </fieldset>

            <fieldset class="lj-passo">
                <legend><span>3</span> Pagamento</legend>
                <div class="lj-opcoes">
                    @if($meios['pix'])
                    <label class="lj-opcao">
                        <input type="radio" name="pagamento" value="pix" @checked($pagPadrao === 'pix')>
                        <span><strong><i class="bi bi-qr-code"></i> PIX</strong>
                        <small>{{ $meios['pix'] === 'infinitepay' ? 'Você paga em uma página segura do nosso parceiro.' : 'O QR Code aparece na tela e a confirmação é automática.' }}</small></span>
                    </label>
                    @endif
                    @if($meios['cartao'])
                    <label class="lj-opcao">
                        <input type="radio" name="pagamento" value="cartao" @checked($pagPadrao === 'cartao')>
                        <span><strong><i class="bi bi-credit-card"></i> Cartão de crédito</strong><small>Você paga em uma página segura do nosso parceiro de pagamento.</small></span>
                    </label>
                    @endif
                    @if($meios['combinar'])
                    <label class="lj-opcao">
                        <input type="radio" name="pagamento" value="combinar" @checked($pagPadrao === 'combinar')>
                        <span><strong><i class="bi bi-chat-dots"></i> Combinar com a loja</strong><small>Feche o pedido agora; a loja fala com você para acertar o pagamento.</small></span>
                    </label>
                    @endif
                </div>
                @error('pagamento')<p class="lj-erro">{{ $message }}</p>@enderror

                <div style="margin-top:1.2rem">
                    <label for="f-obs" style="display:block;font-size:.88rem;font-weight:600;margin-bottom:.3rem">Observações <small style="font-weight:400;color:var(--suave)">(opcional)</small></label>
                    <textarea class="lj-campo" id="f-obs" name="observacoes" rows="2" maxlength="1000" placeholder="Algum recado para a loja?">{{ old('observacoes') }}</textarea>
                </div>
            </fieldset>
        </div>

        <aside class="lj-resumo" aria-label="Resumo do pedido">
            <h2>Seu pedido</h2>
            @foreach($itens as $i)
                <div class="lj-item lj-item--mini">
                    <div class="lj-item__foto">@if($i['foto'])<img src="{{ $i['foto'] }}" alt="">@else<span class="lj-semfoto">{{ mb_substr($i['nome'], 0, 1) }}</span>@endif</div>
                    <div>{{ $i['nome'] }}<small>{{ $i['quantidade'] }} × R$ {{ number_format($i['preco'], 2, ',', '.') }}</small></div>
                    <div class="lj-item__total">R$ {{ number_format($i['total'], 2, ',', '.') }}</div>
                </div>
            @endforeach
            <dl style="margin-top:1rem">
                <dt>Produtos</dt><dd>R$ {{ number_format($subtotal, 2, ',', '.') }}</dd>
                @if($desconto > 0)<dt class="lj-desconto">Cupom {{ $cupom }}</dt><dd class="lj-desconto">− R$ {{ number_format($desconto, 2, ',', '.') }}</dd>@endif
                <dt>Entrega</dt><dd data-lj-frete-valor>—</dd>
                <dt class="lj-total">Total</dt><dd class="lj-total" data-lj-total data-subtotal="{{ $subtotal - $desconto }}">R$ {{ number_format($subtotal - $desconto, 2, ',', '.') }}</dd>
            </dl>
            @if($cupom && $cupomOk && $cupomDeFrete)<p style="font-size:.88rem;color:var(--ok);margin-top:.6rem"><i class="bi bi-ticket-perforated"></i> Cupom {{ $cupom }}: frete grátis.</p>@endif
            @unless($cupom && $cupomOk)<p style="font-size:.85rem;color:var(--suave);margin-top:.6rem">Tem cupom? <a href="{{ $ctx->url('carrinho') }}">Aplique no carrinho</a>.</p>@endunless
            <label class="lj-aceite"><input type="checkbox" name="novidades" value="1" @checked(old('novidades'))> <span>Quero receber novidades e ofertas de {{ $loja->nome }}.</span></label>
            <button class="lj-btn lj-btn--cheio" type="submit" style="margin-top:1.2rem" data-lj-enviar>Fechar pedido</button>
            <p style="font-size:.82rem;color:var(--suave);margin-top:.8rem;text-align:center">Ao fechar, você concorda com os <a href="{{ $ctx->url('politica/termos') }}" target="_blank" rel="noopener">termos de uso</a> e a <a href="{{ $ctx->url('politica/privacidade') }}" target="_blank" rel="noopener">política de privacidade</a>.</p>
            <p style="font-size:.82rem;color:var(--suave);margin-top:.5rem;text-align:center"><a href="{{ $ctx->url('carrinho') }}">Voltar ao carrinho</a></p>
        </aside>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var f = document.querySelector('[data-lj-fechar]');
    var bloco = f.querySelector('[data-lj-endereco]');
    var fretes = f.querySelector('[data-lj-fretes]');
    var valorEl = f.querySelector('[data-lj-frete-valor]');
    var totalEl = f.querySelector('[data-lj-total]');
    var subtotal = Number(totalEl.dataset.subtotal);
    var servicoAntigo = @json(old('servico_id'));
    var ultimoCep = '';

    function modo() { var m = f.querySelector('input[name=entrega]:checked'); return m ? m.value : ''; }
    function pintar(frete, texto) {
        valorEl.textContent = texto;
        totalEl.textContent = LJ.moeda(subtotal + (frete || 0));
    }
    function aoEscolherFrete() {
        var r = fretes.querySelector('input[name=servico_id]:checked');
        if (r) pintar(Number(r.dataset.valor), Number(r.dataset.valor) === 0 ? 'Grátis' : LJ.moeda(Number(r.dataset.valor)));
    }
    function aoTrocarModo() {
        var m = modo();
        bloco.hidden = m !== 'entrega';
        if (m === 'retirada') pintar(0, 'Grátis (retirada)');
        else if (m === 'entrega') { if (fretes.querySelector('input:checked')) aoEscolherFrete(); else pintar(0, 'informe o CEP'); }
        else pintar(0, '—');
    }
    function cotar() {
        var cep = f.cep.value.replace(/\D/g, '');
        if (cep.length !== 8 || cep === ultimoCep) return;
        ultimoCep = cep;
        fretes.textContent = 'Calculando o frete…';
        LJ.post(@json($ctx->url('frete')), {cep: cep}).then(function (j) {
            fretes.textContent = '';
            if (j.endereco) {
                if (!f.logradouro.value && j.endereco.logradouro) f.logradouro.value = j.endereco.logradouro;
                if (!f.bairro.value && j.endereco.bairro) f.bairro.value = j.endereco.bairro;
                f.cidade.value = j.endereco.cidade || ''; f.uf.value = j.endereco.uf || '';
                f.querySelector('[data-lj-cidade]').value = (j.endereco.cidade || '') + (j.endereco.uf ? '/' + j.endereco.uf : '');
            }
            if (!j.disponivel) {
                var a = document.createElement('div'); a.className = 'lj-aviso lj-aviso--nota';
                a.textContent = j.mensagem || 'Não foi possível calcular o frete.'; fretes.appendChild(a);
                pintar(0, 'a combinar'); return;
            }
            var lista = document.createElement('div'); lista.className = 'lj-opcoes';
            j.opcoes.forEach(function (o, n) {
                var l = document.createElement('label'); l.className = 'lj-opcao';
                var i = document.createElement('input'); i.type = 'radio'; i.name = 'servico_id'; i.value = o.id; i.dataset.valor = o.valor;
                i.checked = servicoAntigo ? servicoAntigo === o.id : n === 0;
                var s = document.createElement('span'), t = document.createElement('strong'), p = document.createElement('small'), e = document.createElement('em');
                t.textContent = o.nome; p.textContent = o.prazo; e.textContent = o.gratis ? 'Grátis' : LJ.moeda(o.valor);
                s.appendChild(t); s.appendChild(p); l.appendChild(i); l.appendChild(s); l.appendChild(e); lista.appendChild(l);
            });
            if (!lista.querySelector('input:checked')) lista.querySelector('input').checked = true;
            fretes.appendChild(lista); aoEscolherFrete();
        }).catch(function () { fretes.textContent = 'Não foi possível calcular o frete agora.'; ultimoCep = ''; });
    }
    f.addEventListener('change', function (ev) {
        if (ev.target.name === 'entrega') aoTrocarModo();
        if (ev.target.name === 'servico_id') aoEscolherFrete();
    });
    f.cep.addEventListener('input', function () {
        var d = f.cep.value.replace(/\D/g, '').slice(0, 8);
        f.cep.value = d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d;
        if (d.length === 8) cotar();
    });
    f.cep.addEventListener('blur', cotar);
    f.addEventListener('submit', function () {
        var b = f.querySelector('[data-lj-enviar]');
        b.disabled = true; b.textContent = 'Fechando o pedido…';
    });
    aoTrocarModo();
    if (modo() === 'entrega') cotar();
    LJ.rastro.evento('fechamento', {valor: subtotal, itens: {!! json_encode($itens->map(fn ($i) => ['id' => $i['id'], 'nome' => $i['nome'], 'preco' => $i['preco'], 'quantidade' => $i['quantidade']])->values(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) !!}});
})();
</script>
@endpush
