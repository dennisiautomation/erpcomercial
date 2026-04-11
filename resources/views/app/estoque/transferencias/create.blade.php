@extends('layouts.app')

@section('title', 'Nova Transferencia de Estoque')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-truck me-2"></i>Nova Transferencia de Estoque</h4>
        <p class="text-muted mb-0 small">Solicite a transferencia de produtos para outra unidade</p>
    </div>
    <a href="{{ route('app.transferencias.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<div class="row">
    <div class="col-lg-9">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('app.transferencias.store') }}" id="form-transferencia">
                    @csrf

                    {{-- Destination --}}
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="unidade_destino_id" class="form-label fw-semibold">
                                <i class="bi bi-building me-1"></i> Unidade Destino <span class="text-danger">*</span>
                            </label>
                            <select name="unidade_destino_id" id="unidade_destino_id"
                                class="form-select form-select-lg @error('unidade_destino_id') is-invalid @enderror" required>
                                <option value="">Selecione a unidade destino...</option>
                                @foreach($unidades as $unidade)
                                    <option value="{{ $unidade->id }}" {{ old('unidade_destino_id') == $unidade->id ? 'selected' : '' }}>
                                        {{ $unidade->nome }}
                                    </option>
                                @endforeach
                            </select>
                            @error('unidade_destino_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <hr class="my-4">

                    {{-- Items --}}
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="bi bi-box-seam me-1"></i> Itens da Transferencia
                        </h5>
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
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold text-muted">Quantidade</label>
                                        <input type="number" name="itens[0][quantidade]" class="form-control" step="0.001" min="0.001" placeholder="0,000" required>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-remove-item" disabled>
                                            <i class="bi bi-trash me-1"></i> Remover
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    {{-- Notes --}}
                    <div class="mb-4">
                        <label for="observacoes" class="form-label fw-semibold">Observacoes</label>
                        <textarea name="observacoes" id="observacoes" rows="3"
                            class="form-control @error('observacoes') is-invalid @enderror"
                            placeholder="Motivo da transferencia, instrucoes, etc.">{{ old('observacoes') }}</textarea>
                        @error('observacoes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-lg px-4">
                            <i class="bi bi-send me-1"></i> Solicitar Transferencia
                        </button>
                        <a href="{{ route('app.transferencias.index') }}" class="btn btn-outline-secondary btn-lg">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Side info --}}
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm bg-light">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-1"></i> Como funciona</h6>
                <ol class="small text-muted ps-3 mb-0">
                    <li class="mb-2">Selecione a unidade de destino</li>
                    <li class="mb-2">Adicione os produtos e quantidades</li>
                    <li class="mb-2">Envie a solicitacao</li>
                    <li class="mb-2">Aguarde a aprovacao do responsavel</li>
                    <li class="mb-0">O estoque sera movimentado automaticamente</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let itemIndex = 1;
    const container = document.getElementById('itens-container');
    const produtos = @json($produtos);

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
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Quantidade</label>
                        <input type="number" name="itens[${itemIndex}][quantidade]" class="form-control" step="0.001" min="0.001" placeholder="0,000" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
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
