@extends('layouts.app')

@section('title', 'Nova Conta a Pagar')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Nova Conta a Pagar</h4>
    <a href="{{ route('app.contas-pagar.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('app.contas-pagar.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="fornecedor_id" class="form-label">Fornecedor <span class="text-danger">*</span></label>
                        <select name="fornecedor_id" id="fornecedor_id" class="form-select @error('fornecedor_id') is-invalid @enderror" required>
                            <option value="">Selecione o fornecedor...</option>
                            @foreach($fornecedores as $fornecedor)
                                <option value="{{ $fornecedor->id }}" {{ old('fornecedor_id') == $fornecedor->id ? 'selected' : '' }}>
                                    {{ $fornecedor->nome_razao_social }}
                                </option>
                            @endforeach
                        </select>
                        @error('fornecedor_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descricao <span class="text-danger">*</span></label>
                        <input type="text" name="descricao" id="descricao"
                            class="form-control @error('descricao') is-invalid @enderror"
                            value="{{ old('descricao') }}" required>
                        @error('descricao')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="valor" class="form-label">Valor <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" name="valor" id="valor" step="0.01" min="0.01"
                                    class="form-control @error('valor') is-invalid @enderror"
                                    value="{{ old('valor') }}" required>
                            </div>
                            @error('valor')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="vencimento" class="form-label">Vencimento <span class="text-danger">*</span></label>
                            <input type="date" name="vencimento" id="vencimento"
                                class="form-control @error('vencimento') is-invalid @enderror"
                                value="{{ old('vencimento') }}" required>
                            @error('vencimento')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="parcelas" class="form-label">Parcelas</label>
                            <input type="number" name="parcelas" id="parcelas" min="1" max="48"
                                class="form-control @error('parcelas') is-invalid @enderror"
                                value="{{ old('parcelas', 1) }}">
                            @error('parcelas')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="categoria" class="form-label">Categoria</label>
                            <input type="text" name="categoria" id="categoria"
                                class="form-control @error('categoria') is-invalid @enderror"
                                value="{{ old('categoria') }}" placeholder="Ex: Aluguel, Fornecedor, Imposto...">
                            @error('categoria')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="centro_custo" class="form-label">Centro de Custo</label>
                            <input type="text" name="centro_custo" id="centro_custo"
                                class="form-control @error('centro_custo') is-invalid @enderror"
                                value="{{ old('centro_custo') }}">
                            @error('centro_custo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="forma_pagamento" class="form-label">Forma de Pagamento</label>
                            <select name="forma_pagamento" id="forma_pagamento" class="form-select">
                                <option value="">Selecione...</option>
                                <option value="boleto" {{ old('forma_pagamento') == 'boleto' ? 'selected' : '' }}>Boleto</option>
                                <option value="pix" {{ old('forma_pagamento') == 'pix' ? 'selected' : '' }}>PIX</option>
                                <option value="transferencia" {{ old('forma_pagamento') == 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                                <option value="debito_automatico" {{ old('forma_pagamento') == 'debito_automatico' ? 'selected' : '' }}>Debito Automatico</option>
                                <option value="dinheiro" {{ old('forma_pagamento') == 'dinheiro' ? 'selected' : '' }}>Dinheiro</option>
                            </select>
                        </div>
                    </div>

                    {{-- Recorrente --}}
                    <div class="card bg-light mb-3">
                        <div class="card-body">
                            <div class="form-check form-switch mb-3">
                                <input type="hidden" name="recorrente" value="0">
                                <input class="form-check-input" type="checkbox" name="recorrente" value="1" id="recorrente"
                                    {{ old('recorrente') ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="recorrente">Conta Recorrente</label>
                            </div>
                            <div id="recorrencia-options" class="{{ old('recorrente') ? '' : 'd-none' }}">
                                <label for="recorrencia_tipo" class="form-label">Tipo de Recorrencia</label>
                                <select name="recorrencia_tipo" id="recorrencia_tipo" class="form-select">
                                    <option value="mensal" {{ old('recorrencia_tipo') == 'mensal' ? 'selected' : '' }}>Mensal</option>
                                    <option value="bimestral" {{ old('recorrencia_tipo') == 'bimestral' ? 'selected' : '' }}>Bimestral</option>
                                    <option value="trimestral" {{ old('recorrencia_tipo') == 'trimestral' ? 'selected' : '' }}>Trimestral</option>
                                    <option value="semestral" {{ old('recorrencia_tipo') == 'semestral' ? 'selected' : '' }}>Semestral</option>
                                    <option value="anual" {{ old('recorrencia_tipo') == 'anual' ? 'selected' : '' }}>Anual</option>
                                </select>
                                <small class="text-muted">Serao geradas 12 parcelas automaticamente.</small>
                            </div>
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
                            <i class="bi bi-check-lg me-1"></i> Salvar
                        </button>
                        <a href="{{ route('app.contas-pagar.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('recorrente').addEventListener('change', function() {
        document.getElementById('recorrencia-options').classList.toggle('d-none', !this.checked);
    });
</script>
@endpush
