@extends('layouts.app')

@section('title', 'Nova Movimentacao de Estoque')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-plus-circle me-2"></i>Nova Movimentacao de Estoque</h4>
        <p class="text-muted mb-0 small">Registre entradas, ajustes, perdas ou bonificacoes</p>
    </div>
    <a href="{{ route('app.movimentacoes.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('app.movimentacoes.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label for="produto_id" class="form-label fw-semibold">Produto <span class="text-danger">*</span></label>
                        <select name="produto_id" id="produto_id" class="form-select form-select-lg @error('produto_id') is-invalid @enderror" required>
                            <option value="">Selecione o produto...</option>
                            @foreach($produtos as $produto)
                                <option value="{{ $produto->id }}" {{ old('produto_id') == $produto->id ? 'selected' : '' }}
                                    data-estoque-minimo="{{ $produto->estoque_minimo }}">
                                    {{ $produto->descricao }}
                                </option>
                            @endforeach
                        </select>
                        @error('produto_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div id="produto-info" class="alert alert-info border-0 bg-info bg-opacity-10 d-none mb-4">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-info-circle fs-5 me-2 text-info"></i>
                            <span>Estoque minimo configurado: <strong id="estoque-minimo-display">-</strong> unidades</span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="tipo" class="form-label fw-semibold">Tipo de Movimentacao <span class="text-danger">*</span></label>
                        <div class="row g-2" id="tipo-cards">
                            <div class="col-6 col-md-3">
                                <input type="radio" name="tipo" value="entrada" id="tipo-entrada" class="btn-check" {{ old('tipo') == 'entrada' ? 'checked' : '' }} required>
                                <label class="btn btn-outline-success w-100 py-3 text-center" for="tipo-entrada">
                                    <i class="bi bi-box-arrow-in-down d-block fs-4 mb-1"></i>
                                    <span class="small fw-semibold">Entrada</span>
                                </label>
                            </div>
                            <div class="col-6 col-md-3">
                                <input type="radio" name="tipo" value="ajuste" id="tipo-ajuste" class="btn-check" {{ old('tipo') == 'ajuste' ? 'checked' : '' }}>
                                <label class="btn btn-outline-warning w-100 py-3 text-center" for="tipo-ajuste">
                                    <i class="bi bi-pencil-square d-block fs-4 mb-1"></i>
                                    <span class="small fw-semibold">Ajuste</span>
                                </label>
                            </div>
                            <div class="col-6 col-md-3">
                                <input type="radio" name="tipo" value="perda" id="tipo-perda" class="btn-check" {{ old('tipo') == 'perda' ? 'checked' : '' }}>
                                <label class="btn btn-outline-danger w-100 py-3 text-center" for="tipo-perda">
                                    <i class="bi bi-exclamation-triangle d-block fs-4 mb-1"></i>
                                    <span class="small fw-semibold">Perda</span>
                                </label>
                            </div>
                            <div class="col-6 col-md-3">
                                <input type="radio" name="tipo" value="bonificacao" id="tipo-bonificacao" class="btn-check" {{ old('tipo') == 'bonificacao' ? 'checked' : '' }}>
                                <label class="btn btn-outline-info w-100 py-3 text-center" for="tipo-bonificacao">
                                    <i class="bi bi-gift d-block fs-4 mb-1"></i>
                                    <span class="small fw-semibold">Bonificacao</span>
                                </label>
                            </div>
                        </div>
                        @error('tipo')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="quantidade" class="form-label fw-semibold">Quantidade <span class="text-danger">*</span></label>
                            <input type="number" name="quantidade" id="quantidade" step="0.001" min="0.001"
                                class="form-control form-control-lg @error('quantidade') is-invalid @enderror"
                                value="{{ old('quantidade') }}" placeholder="0,000" required>
                            @error('quantidade')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="custo_unitario" class="form-label fw-semibold">Custo Unitario</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">R$</span>
                                <input type="number" name="custo_unitario" id="custo_unitario" step="0.01" min="0"
                                    class="form-control @error('custo_unitario') is-invalid @enderror"
                                    value="{{ old('custo_unitario') }}" placeholder="0,00">
                            </div>
                            @error('custo_unitario')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="observacoes" class="form-label fw-semibold">Observacoes</label>
                        <textarea name="observacoes" id="observacoes" rows="3"
                            class="form-control @error('observacoes') is-invalid @enderror"
                            placeholder="Motivo da movimentacao, referencia, etc.">{{ old('observacoes') }}</textarea>
                        @error('observacoes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Maximo 500 caracteres</div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-lg px-4">
                            <i class="bi bi-check-lg me-1"></i> Registrar Movimentacao
                        </button>
                        <a href="{{ route('app.movimentacoes.index') }}" class="btn btn-outline-secondary btn-lg">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Side help --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm bg-light">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-question-circle me-1"></i> Tipos de Movimentacao</h6>
                <div class="mb-3">
                    <span class="badge bg-success rounded-pill me-1">Entrada</span>
                    <small class="text-muted">Adiciona produtos ao estoque (compras, recebimentos)</small>
                </div>
                <div class="mb-3">
                    <span class="badge bg-warning text-dark rounded-pill me-1">Ajuste</span>
                    <small class="text-muted">Correcao de inventario (adiciona ao estoque)</small>
                </div>
                <div class="mb-3">
                    <span class="badge bg-danger rounded-pill me-1">Perda</span>
                    <small class="text-muted">Produtos danificados, vencidos ou extraviados</small>
                </div>
                <div class="mb-0">
                    <span class="badge bg-info text-dark rounded-pill me-1">Bonificacao</span>
                    <small class="text-muted">Saida para brindes ou amostras</small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('produto_id').addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        const info = document.getElementById('produto-info');
        const display = document.getElementById('estoque-minimo-display');

        if (this.value) {
            info.classList.remove('d-none');
            display.textContent = selected.dataset.estoqueMinimo || '0';
        } else {
            info.classList.add('d-none');
        }
    });

    // Trigger on load if product already selected
    if (document.getElementById('produto_id').value) {
        document.getElementById('produto_id').dispatchEvent(new Event('change'));
    }
</script>
@endpush
