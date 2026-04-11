@extends('layouts.app')

@section('title', 'Nova Transferencia de Estoque')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-truck me-2"></i>Nova Transferencia de Estoque</h4>
    <a href="{{ route('app.transferencias.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('app.transferencias.store') }}" id="form-transferencia">
            @csrf

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="unidade_destino_id" class="form-label">Unidade Destino <span class="text-danger">*</span></label>
                    <select name="unidade_destino_id" id="unidade_destino_id"
                        class="form-select @error('unidade_destino_id') is-invalid @enderror" required>
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

            <hr>

            <h5 class="mb-3">Itens da Transferencia</h5>

            <div id="itens-container">
                <div class="row g-3 mb-2 item-row" data-index="0">
                    <div class="col-md-6">
                        <label class="form-label">Produto <span class="text-danger">*</span></label>
                        <select name="itens[0][produto_id]" class="form-select" required>
                            <option value="">Selecione...</option>
                            @foreach($produtos as $produto)
                                <option value="{{ $produto->id }}">{{ $produto->descricao }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Quantidade <span class="text-danger">*</span></label>
                        <input type="number" name="itens[0][quantidade]" class="form-control" step="0.001" min="0.001" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-danger btn-remove-item" disabled>
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-outline-success mt-2" id="btn-add-item">
                <i class="bi bi-plus-lg me-1"></i> Adicionar Item
            </button>

            <hr>

            <div class="mb-3">
                <label for="observacoes" class="form-label">Observacoes</label>
                <textarea name="observacoes" id="observacoes" rows="3"
                    class="form-control @error('observacoes') is-invalid @enderror">{{ old('observacoes') }}</textarea>
                @error('observacoes')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Solicitar Transferencia
                </button>
                <a href="{{ route('app.transferencias.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
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
        row.className = 'row g-3 mb-2 item-row';
        row.dataset.index = itemIndex;
        row.innerHTML = `
            <div class="col-md-6">
                <select name="itens[${itemIndex}][produto_id]" class="form-select" required>
                    ${options}
                </select>
            </div>
            <div class="col-md-3">
                <input type="number" name="itens[${itemIndex}][quantidade]" class="form-control" step="0.001" min="0.001" required>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger btn-remove-item">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        container.appendChild(row);
        itemIndex++;
        updateRemoveButtons();
    });

    container.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-item')) {
            e.target.closest('.item-row').remove();
            updateRemoveButtons();
        }
    });

    function updateRemoveButtons() {
        const rows = container.querySelectorAll('.item-row');
        rows.forEach(row => {
            row.querySelector('.btn-remove-item').disabled = rows.length <= 1;
        });
    }
</script>
@endpush
