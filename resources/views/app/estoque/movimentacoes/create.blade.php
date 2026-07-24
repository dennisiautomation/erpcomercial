@extends('layouts.app')

@section('title', 'Nova Movimentacao de Estoque')

@section('content')
<x-erp.page-header title="Nova Movimentacao de Estoque" subtitle="Registre entradas, ajustes, perdas ou bonificacoes — varios itens de uma vez" icon="plus-circle">
    <a href="{{ route('app.movimentacoes.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

<div class="row">
    <div class="col-lg-9">
        <form method="POST" action="{{ route('app.movimentacoes.store') }}" id="form-movimentacao">
            @csrf

            <x-erp.form-section title="Tipo de Movimentacao" icon="arrow-left-right">
                <div class="row g-2" id="tipo-cards">
                    <div class="col-6 col-md-3">
                        <input type="radio" name="tipo" value="entrada" id="tipo-entrada" class="btn-check" {{ old('tipo', 'entrada') == 'entrada' ? 'checked' : '' }} required>
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
            </x-erp.form-section>

            <x-erp.form-section title="Itens da Movimentacao" icon="box-seam">
                <div class="d-flex justify-content-end mb-3">
                    <button type="button" class="btn btn-success btn-sm" id="btn-add-item">
                        <i class="bi bi-plus-lg me-1"></i> Adicionar Item
                    </button>
                </div>

                <div id="itens-container">
                    <div class="card bg-light border-0 mb-2 item-row" data-index="0">
                        <div class="card-body py-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-1 text-center">
                                    <span class="badge bg-primary rounded-pill item-number">1</span>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-semibold text-muted">Produto</label>
                                    <select name="itens[0][produto_id]" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        @foreach($produtos as $produto)
                                            <option value="{{ $produto->id }}">{{ $produto->descricao }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-semibold text-muted">Quantidade</label>
                                    <input type="number" name="itens[0][quantidade]" class="form-control" step="0.001" min="0.001" placeholder="0,000" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-semibold text-muted">Custo Unit. (R$)</label>
                                    <input type="number" name="itens[0][custo_unitario]" class="form-control" step="0.01" min="0" placeholder="0,00">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-danger btn-sm btn-remove-item" disabled>
                                        <i class="bi bi-trash me-1"></i> Remover
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </x-erp.form-section>

            <x-erp.form-section title="Observacoes" icon="chat-text">
                <div class="mb-4">
                    <label for="observacoes" class="form-label fw-semibold">Observacoes</label>
                    <textarea name="observacoes" id="observacoes" rows="3"
                        class="form-control @error('observacoes') is-invalid @enderror"
                        placeholder="Motivo da movimentacao, nota do fornecedor, referencia, etc.">{{ old('observacoes') }}</textarea>
                    @error('observacoes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Maximo 500 caracteres — vale para todos os itens</div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-erp-primary btn-lg px-4">
                        <i class="bi bi-check-lg me-1"></i> Registrar Movimentacao
                    </button>
                    <a href="{{ route('app.movimentacoes.index') }}" class="btn btn-erp-outline btn-lg">Cancelar</a>
                </div>
            </x-erp.form-section>
        </form>
    </div>

    {{-- Side help --}}
    <div class="col-lg-3">
        <x-erp.card title="Tipos de Movimentacao" icon="question-circle">
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
            <div class="mb-3">
                <span class="badge bg-info text-dark rounded-pill me-1">Bonificacao</span>
                <small class="text-muted">Saida para brindes ou amostras</small>
            </div>
            <hr>
            <small class="text-muted">Adicione quantos itens precisar — todos entram de uma vez, no mesmo tipo de movimentacao.</small>
        </x-erp.card>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let itemIndex = 1;
    const container = document.getElementById('itens-container');
    const produtos = @json($produtos->map->only(['id', 'descricao']));

    document.getElementById('btn-add-item').addEventListener('click', function() {
        let options = '<option value="">Selecione...</option>';
        produtos.forEach(p => {
            options += `<option value="${p.id}">${p.descricao}</option>`;
        });

        const row = document.createElement('div');
        row.className = 'card bg-light border-0 mb-2 item-row';
        row.dataset.index = itemIndex;
        row.innerHTML = `
            <div class="card-body py-3">
                <div class="row g-3 align-items-center">
                    <div class="col-md-1 text-center">
                        <span class="badge bg-primary rounded-pill item-number">${itemIndex + 1}</span>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold text-muted">Produto</label>
                        <select name="itens[${itemIndex}][produto_id]" class="form-select" required>
                            ${options}
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-muted">Quantidade</label>
                        <input type="number" name="itens[${itemIndex}][quantidade]" class="form-control" step="0.001" min="0.001" placeholder="0,000" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-muted">Custo Unit. (R$)</label>
                        <input type="number" name="itens[${itemIndex}][custo_unitario]" class="form-control" step="0.01" min="0" placeholder="0,00">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-danger btn-sm btn-remove-item">
                            <i class="bi bi-trash me-1"></i> Remover
                        </button>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(row);
        itemIndex++;
        updateRemoveButtons();
        renumberItems();
    });

    container.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-item')) {
            e.target.closest('.item-row').remove();
            updateRemoveButtons();
            renumberItems();
        }
    });

    function updateRemoveButtons() {
        const rows = container.querySelectorAll('.item-row');
        rows.forEach(row => {
            row.querySelector('.btn-remove-item').disabled = rows.length <= 1;
        });
    }

    function renumberItems() {
        const rows = container.querySelectorAll('.item-row');
        rows.forEach((row, i) => {
            row.querySelector('.item-number').textContent = i + 1;
        });
    }
</script>
@endpush
