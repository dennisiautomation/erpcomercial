{{-- Taxa de entrega + quadro "Formas de pagamento" do orçamento (25/10/2026, pedido da DK).
     Incluído em create e edit, logo antes do TOTAL. Espera: $configLoja, $freteAtual,
     $parcelasPadrao (null = quadro desligado), $descontoAvistaPadrao.
     A conta da tela espelha OrcamentoPagamentoService — quem grava e imprime é o PHP. --}}
@php
    $maxParcelasLoja = max(1, (int) ($configLoja->max_parcelas ?? 6), (int) ($parcelasPadrao ?? 0));
    $parcelasSel = old('parcelas', $parcelasPadrao);
    $lojaPrecoCartao = $configLoja->precoBaseCartao();
@endphp
<div class="mb-3">
    <label class="form-label small fw-semibold" for="freteValor">Taxa de entrega (R$)</label>
    <div class="input-group input-group-sm">
        <span class="input-group-text">R$</span>
        <input type="number" name="frete_valor" id="freteValor" class="form-control" step="0.01" min="0"
               value="{{ old('frete_valor', $freteAtual ?? 0) }}">
    </div>
    <div class="form-text">Soma no total. Desconto não incide sobre a entrega.</div>
</div>

<div class="border rounded-3 p-3 mb-3">
    <div class="fw-semibold small mb-2"><i class="bi bi-credit-card me-1"></i>Formas de pagamento no orçamento impresso</div>
    <div class="row g-2">
        <div class="col-6">
            <label class="form-label small" for="parcelasOrc">Cartão em até</label>
            <select name="parcelas" id="parcelasOrc" class="form-select form-select-sm">
                <option value="">Não mostrar</option>
                @for($n = 1; $n <= $maxParcelasLoja; $n++)
                    <option value="{{ $n }}" @selected((string) $parcelasSel === (string) $n)>{{ $n }}x</option>
                @endfor
            </select>
        </div>
        <div class="col-6">
            <label class="form-label small" for="descontoAvistaOrc">Desconto Dinheiro/PIX</label>
            <div class="input-group input-group-sm">
                <input type="number" name="desconto_avista_percentual" id="descontoAvistaOrc" class="form-control"
                       step="0.01" min="0" max="100" value="{{ old('desconto_avista_percentual', $descontoAvistaPadrao ?? 0) }}">
                <span class="input-group-text">%</span>
            </div>
        </div>
    </div>
    <div id="quadroPagamentoPrevia" class="small mt-2"></div>
</div>

<script>
    // Prévia do quadro de pagamento. Chamada por calcularTotais() com o total dos
    // produtos já descontado (sem frete).
    window.QuadroPagamentoOrc = (function () {
        const cfg = {!! json_encode([
            'preco_cartao'       => $lojaPrecoCartao,
            'percentual_credito' => (float) ($configLoja->percentual_credito ?? 0),
            'juros'              => (object) ($configLoja->juros_por_parcela ?? []),
        ]) !!};
        const brl = v => 'R$ ' + v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        const r2 = v => Math.round(v * 100) / 100;
        let ultimoProdutos = 0;
        function render(produtos) {
            if (produtos !== undefined) ultimoProdutos = produtos;
            const alvo = document.getElementById('quadroPagamentoPrevia');
            const n = parseInt(document.getElementById('parcelasOrc').value) || 0;
            if (!n) { alvo.innerHTML = '<span class="text-muted">O orçamento sai sem o quadro de pagamento.</span>'; return; }
            const frete = Math.max(0, parseFloat(document.getElementById('freteValor').value) || 0);
            const d = Math.min(100, Math.max(0, parseFloat(document.getElementById('descontoAvistaOrc').value) || 0));
            const p = Math.max(0, r2(ultimoProdutos));
            const avista = r2(p - r2(p * d / 100) + frete);
            const base = cfg.preco_cartao ? p : r2(p * (1 + cfg.percentual_credito / 100));
            const juros = Math.max(0, parseFloat(cfg.juros[String(n)]) || 0);
            const cartao = juros > 0 ? r2((base + frete) * (1 + juros / 100)) : r2(base + frete);
            const pct = String(d).replace('.', ',');
            alvo.innerHTML =
                '<div><i class="bi bi-cash-coin text-success me-1"></i>Dinheiro/PIX' + (d > 0 ? ' (' + pct + '% de desconto)' : '') + ': <strong>' + brl(avista) + '</strong></div>' +
                '<div><i class="bi bi-credit-card text-primary me-1"></i>Cartão: <strong>' + n + 'x de ' + brl(r2(cartao / n)) + '</strong> ' +
                (juros > 0 ? '(com juros, total ' + brl(cartao) + ')' : 'sem juros' + (n > 1 ? ' (total ' + brl(cartao) + ')' : '')) + '</div>';
        }
        ['parcelasOrc', 'descontoAvistaOrc', 'freteValor'].forEach(id =>
            document.getElementById(id).addEventListener('input', () => window.calcularTotaisOrc ? window.calcularTotaisOrc() : render()));
        document.getElementById('parcelasOrc').addEventListener('change', () => render());
        return { render };
    })();
</script>
