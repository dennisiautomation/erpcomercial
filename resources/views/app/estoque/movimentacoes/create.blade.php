@extends('layouts.app')

@section('title', 'Nova Movimentacao de Estoque')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Nova Movimentacao de Estoque</h4>
    <a href="{{ route('app.movimentacoes.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('app.movimentacoes.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="produto_id" class="form-label">Produto <span class="text-danger">*</span></label>
                        <select name="produto_id" id="produto_id" class="form-select @error('produto_id') is-invalid @enderror" required>
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

                    <div id="produto-info" class="alert alert-info d-none mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Estoque minimo: <strong id="estoque-minimo-display">-</strong>
                    </div>

                    <div class="mb-3">
                        <label for="tipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select name="tipo" id="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
                            <option value="">Selecione...</option>
                            <option value="entrada" {{ old('tipo') == 'entrada' ? 'selected' : '' }}>Entrada Manual</option>
                            <option value="ajuste" {{ old('tipo') == 'ajuste' ? 'selected' : '' }}>Ajuste</option>
                            <option value="perda" {{ old('tipo') == 'perda' ? 'selected' : '' }}>Perda</option>
                            <option value="bonificacao" {{ old('tipo') == 'bonificacao' ? 'selected' : '' }}>Bonificacao</option>
                        </select>
                        @error('tipo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="quantidade" class="form-label">Quantidade <span class="text-danger">*</span></label>
                            <input type="number" name="quantidade" id="quantidade" step="0.001" min="0.001"
                                class="form-control @error('quantidade') is-invalid @enderror"
                                value="{{ old('quantidade') }}" required>
                            @error('quantidade')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="custo_unitario" class="form-label">Custo Unitario</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" name="custo_unitario" id="custo_unitario" step="0.01" min="0"
                                    class="form-control @error('custo_unitario') is-invalid @enderror"
                                    value="{{ old('custo_unitario') }}">
                            </div>
                            @error('custo_unitario')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

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
                            <i class="bi bi-check-lg me-1"></i> Registrar Movimentacao
                        </button>
                        <a href="{{ route('app.movimentacoes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
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
</script>
@endpush
