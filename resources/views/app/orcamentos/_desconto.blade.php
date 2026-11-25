{{-- Desconto geral do orçamento (25/11/2026): UM campo, com a escolha "em % / em R$".
     Antes eram dois campos lado a lado para o mesmo desconto, e parecia que eram dois.
     Incluído em create e edit. Espera: $descPercAtual, $descValorAtual (0 em orçamento novo).
     O formulário continua mandando desconto_percentual e desconto_valor (campos ocultos);
     quem decide o valor gravado é OrcamentoController::descontoGeral(). --}}
@php
    $descPercIni  = (float) old('desconto_percentual', $descPercAtual ?? 0);
    $descValorIni = (float) old('desconto_valor', $descValorAtual ?? 0);
    $descTipoIni  = old('desconto_tipo', $descPercIni > 0 ? 'percentual' : ($descValorIni > 0 ? 'valor' : 'percentual'));
    $descTipoIni  = $descTipoIni === 'valor' ? 'valor' : 'percentual';
    $descDigitado = $descTipoIni === 'percentual' ? $descPercIni : $descValorIni;
@endphp
<div class="mb-3">
    <label for="descontoGeral" class="form-label small fw-semibold">Desconto no orçamento</label>
    <div class="input-group input-group-sm">
        <select name="desconto_tipo" id="descontoTipo" class="form-select" style="max-width: 7.5rem" aria-label="Desconto em porcentagem ou em reais">
            <option value="percentual" @selected($descTipoIni === 'percentual')>Em %</option>
            <option value="valor" @selected($descTipoIni === 'valor')>Em R$</option>
        </select>
        <input type="number" id="descontoGeral" class="form-control" step="0.01" min="0"
               value="{{ rtrim(rtrim(number_format($descDigitado, 2, '.', ''), '0'), '.') ?: 0 }}">
    </div>
    <div class="form-text" id="descontoAjuda">Vale para o orçamento inteiro e diminui o total.</div>
    <input type="hidden" name="desconto_percentual" id="descontoPerc" value="{{ $descPercIni }}">
    <input type="hidden" name="desconto_valor" id="descontoValor" value="{{ $descValorIni }}">
</div>

<script>
    // calcularTotais() chama aplicar(subtotal) e recebe o desconto em R$ já limitado ao subtotal.
    window.DescontoOrc = (function () {
        const tipo = document.getElementById('descontoTipo');
        const campo = document.getElementById('descontoGeral');
        const hPerc = document.getElementById('descontoPerc');
        const hValor = document.getElementById('descontoValor');
        const ajuda = document.getElementById('descontoAjuda');
        const padrao = ajuda.textContent;
        const r2 = v => Math.round(v * 100) / 100;
        const brl = v => 'R$ ' + v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        const num = v => String(r2(v)).replace('.', ',');

        function aplicar(subtotal) {
            subtotal = Math.max(0, r2(subtotal || 0));
            const digitado = Math.max(0, parseFloat(campo.value) || 0);
            let perc = 0, valor = 0, msg = padrao, aviso = false;
            if (tipo.value === 'percentual') {
                perc = Math.min(100, digitado);
                valor = r2(subtotal * perc / 100);
                if (digitado > 100) { msg = 'O desconto vai até 100%. Está valendo 100%.'; aviso = true; }
                else if (perc > 0) msg = 'Diminui ' + brl(valor) + ' do total.';
            } else {
                valor = r2(digitado);
                if (valor > subtotal) {
                    valor = subtotal;
                    msg = 'O desconto não passa do subtotal dos itens. Está valendo ' + brl(valor) + '.';
                    aviso = true;
                } else if (valor > 0) {
                    msg = 'Equivale a ' + num(valor / subtotal * 100) + '% do subtotal.';
                }
            }
            hPerc.value = perc;
            hValor.value = valor.toFixed(2);
            ajuda.textContent = msg;
            ajuda.classList.toggle('text-danger', aviso);
            return valor;
        }

        const recalcular = () => { if (window.calcularTotaisOrc) window.calcularTotaisOrc(); };
        campo.addEventListener('input', recalcular);
        tipo.addEventListener('change', recalcular);
        return { aplicar };
    })();
</script>
