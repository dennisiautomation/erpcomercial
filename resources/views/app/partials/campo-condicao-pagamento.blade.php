{{-- Condição de pagamento do pedido (14/11/2026): as formas da empresa
     (Configurações → Formas de pagamento) + os prazos. Valor antigo (a_vista,
     cartao) de pedido velho aparece em "Anterior" para não se perder. --}}
<select id="campo-condicao_pagamento" name="condicao_pagamento" class="form-select">
    <option value="">Selecione...</option>
    <optgroup label="Formas de pagamento">
        @foreach($opcoesCondicao['formas'] as $val => $label)
            <option value="{{ $val }}" @selected($selecionado === $val)>{{ $label }}</option>
        @endforeach
    </optgroup>
    <optgroup label="A prazo">
        @foreach($opcoesCondicao['prazos'] as $val => $label)
            <option value="{{ $val }}" @selected($selecionado === $val)>{{ $label }}</option>
        @endforeach
    </optgroup>
    @if(! empty($opcoesCondicao['legado']))
        <optgroup label="Anterior">
            @foreach($opcoesCondicao['legado'] as $val => $label)
                <option value="{{ $val }}" @selected($selecionado === $val)>{{ $label }}</option>
            @endforeach
        </optgroup>
    @endif
</select>
