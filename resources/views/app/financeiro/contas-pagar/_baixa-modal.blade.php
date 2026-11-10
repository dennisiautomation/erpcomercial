{{-- Baixa com data, valor e forma. Um modal só; os botões [data-baixar] preenchem. --}}
<div class="modal fade" id="modalBaixa" tabindex="-1" aria-labelledby="modalBaixaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content" id="formBaixa">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="modalBaixaTitulo"><i class="bi bi-check-circle text-success me-2"></i>Dar baixa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3"><span class="text-muted">Conta:</span> <strong id="baixaDescricao"></strong></p>
                <div class="row g-3">
                    <div class="col-6">
                        <label for="baixaValor" class="form-label fw-semibold">Valor pago</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" name="valor_pago" id="baixaValor" step="0.01" min="0.01" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <label for="baixaData" class="form-label fw-semibold">Pago em</label>
                        <input type="date" name="pago_em" id="baixaData" class="form-control" max="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div class="col-12">
                        <label for="baixaForma" class="form-label fw-semibold">Forma de pagamento</label>
                        <select name="forma_pagamento" id="baixaForma" class="form-select">
                            <option value="">Não informar</option>
                            <option value="boleto">Boleto</option>
                            <option value="pix">PIX</option>
                            <option value="transferencia">Transferência</option>
                            <option value="debito_automatico">Débito Automático</option>
                            <option value="dinheiro">Dinheiro</option>
                        </select>
                    </div>
                </div>
                <div class="form-text mt-3" id="baixaAvisoValor" style="display:none;">
                    O valor pago é diferente do valor lançado (<span id="baixaValorLancado"></span>). Fica registrado o que foi pago de fato.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-erp-outline" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Confirmar pagamento</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const modalEl = document.getElementById('modalBaixa');
    if (!modalEl) return;
    const form = document.getElementById('formBaixa');
    const valor = document.getElementById('baixaValor');
    const aviso = document.getElementById('baixaAvisoValor');
    let valorLancado = 0;

    function conferirValor() {
        const v = parseFloat(valor.value) || 0;
        aviso.style.display = (v > 0 && Math.abs(v - valorLancado) >= 0.005) ? 'block' : 'none';
    }
    valor.addEventListener('input', conferirValor);

    document.querySelectorAll('[data-baixar]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.action = btn.dataset.baixar;
            valorLancado = parseFloat(btn.dataset.valor) || 0;
            valor.value = valorLancado.toFixed(2);
            document.getElementById('baixaData').value = @json(now()->format('Y-m-d'));
            document.getElementById('baixaForma').value = btn.dataset.forma || '';
            document.getElementById('baixaDescricao').textContent = btn.dataset.descricao || '';
            document.getElementById('baixaValorLancado').textContent =
                'R$ ' + valorLancado.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            conferirValor();
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    });
})();
</script>
@endpush
