@extends('layouts.app')

@section('title', 'Fechar Caixa')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-secondary text-white text-center">
                <h5 class="mb-0"><i class="bi bi-lock me-2"></i>Fechamento de Caixa #{{ $caixa->numero_caixa }}</h5>
            </div>
            <div class="card-body p-4">
                {{-- Resumo --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="text-muted mb-1"><i class="bi bi-clock me-1"></i>Abertura</h6>
                                <p class="mb-0">{{ $caixa->aberto_em?->format('d/m/Y H:i') }}</p>
                                <p class="mb-0 small text-muted">Operador: {{ $caixa->operador->name ?? '-' }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="text-muted mb-1"><i class="bi bi-clock-history me-1"></i>Fechamento</h6>
                                <p class="mb-0 fw-bold">{{ now()->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Movimentacoes --}}
                <div class="card mb-4">
                    <div class="card-header"><i class="bi bi-list-check me-1"></i> Resumo de Movimentacoes</div>
                    <div class="card-body p-0">
                        <table class="table table-bordered mb-0">
                            <tbody>
                                <tr>
                                    <td><i class="bi bi-unlock text-success me-1"></i> Valor de Abertura</td>
                                    <td class="text-end fw-semibold">R$ {{ number_format($resumo['abertura'], 2, ',', '.') }}</td>
                                </tr>
                                <tr class="table-success">
                                    <td><i class="bi bi-bag-check text-success me-1"></i> Total em Vendas</td>
                                    <td class="text-end fw-semibold text-success">+ R$ {{ number_format($resumo['vendas'], 2, ',', '.') }}</td>
                                </tr>
                                <tr class="table-info">
                                    <td><i class="bi bi-arrow-up-circle text-info me-1"></i> Suprimentos</td>
                                    <td class="text-end fw-semibold text-info">+ R$ {{ number_format($resumo['suprimentos'], 2, ',', '.') }}</td>
                                </tr>
                                <tr class="table-danger">
                                    <td><i class="bi bi-arrow-down-circle text-danger me-1"></i> Sangrias</td>
                                    <td class="text-end fw-semibold text-danger">- R$ {{ number_format($resumo['sangrias'], 2, ',', '.') }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="table-dark">
                                    <td><strong>Valor Esperado no Caixa</strong></td>
                                    <td class="text-end"><strong class="fs-5">R$ {{ number_format($valorEsperado, 2, ',', '.') }}</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- Formulario de Fechamento --}}
                <form method="POST" action="{{ route('app.caixa.fechar') }}">
                    @csrf

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Valor Contado (em caixa)</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">R$</span>
                                <input type="number" name="valor_contado" id="valorContado" class="form-control text-center"
                                    step="0.01" min="0" required autofocus
                                    value="{{ old('valor_contado') }}">
                            </div>
                            @error('valor_contado')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Diferenca</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">R$</span>
                                <input type="text" id="diferenca" class="form-control text-center fw-bold" readonly value="0,00">
                            </div>
                            <small id="diferencaLabel" class="text-muted">Informe o valor contado</small>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Observacoes</label>
                        <textarea name="observacoes" class="form-control" rows="2" placeholder="Observacoes do fechamento...">{{ old('observacoes') }}</textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-secondary btn-lg flex-grow-1" onclick="return confirm('Confirmar fechamento do caixa?')">
                            <i class="bi bi-lock me-2"></i>Fechar Caixa
                        </button>
                        <a href="{{ route('app.pdv.index') }}" class="btn btn-outline-secondary btn-lg">
                            <i class="bi bi-arrow-left me-1"></i>Voltar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const valorEsperado = {{ $valorEsperado }};
    const contadoInput = document.getElementById('valorContado');
    const diferencaInput = document.getElementById('diferenca');
    const diferencaLabel = document.getElementById('diferencaLabel');

    contadoInput.addEventListener('input', function() {
        const contado = parseFloat(this.value) || 0;
        const diff = contado - valorEsperado;

        diferencaInput.value = diff.toFixed(2).replace('.', ',');

        if (diff > 0.01) {
            diferencaInput.style.color = '#198754';
            diferencaLabel.textContent = 'Sobra de R$ ' + Math.abs(diff).toFixed(2).replace('.', ',');
            diferencaLabel.className = 'text-success small';
        } else if (diff < -0.01) {
            diferencaInput.style.color = '#dc3545';
            diferencaLabel.textContent = 'Falta de R$ ' + Math.abs(diff).toFixed(2).replace('.', ',');
            diferencaLabel.className = 'text-danger small';
        } else {
            diferencaInput.style.color = '#198754';
            diferencaLabel.textContent = 'Caixa confere!';
            diferencaLabel.className = 'text-success small fw-bold';
        }
    });
});
</script>
@endpush
