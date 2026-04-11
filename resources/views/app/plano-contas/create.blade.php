@extends('layouts.app')

@section('title', 'Nova Conta')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Nova Conta</h4>
    <a href="{{ route('app.plano-contas.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('app.plano-contas.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="parent_id" class="form-label">Conta Pai</label>
                        <select name="parent_id" id="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
                            <option value="">Nenhuma (Conta Raiz)</option>
                            @foreach($contasPai as $contaPai)
                                <option value="{{ $contaPai->id }}" {{ old('parent_id', $parentId) == $contaPai->id ? 'selected' : '' }}>
                                    {{ $contaPai->codigo }} - {{ $contaPai->nome }}
                                </option>
                            @endforeach
                        </select>
                        @error('parent_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="codigo" class="form-label">Codigo <span class="text-danger">*</span></label>
                            <input type="text" name="codigo" id="codigo" class="form-control @error('codigo') is-invalid @enderror"
                                   value="{{ old('codigo') }}" placeholder="Ex: 1.1.1" required>
                            @error('codigo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-8 mb-3">
                            <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" name="nome" id="nome" class="form-control @error('nome') is-invalid @enderror"
                                   value="{{ old('nome') }}" required>
                            @error('nome')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo" id="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
                                <option value="">Selecione...</option>
                                <option value="receita" {{ old('tipo') === 'receita' ? 'selected' : '' }}>Receita</option>
                                <option value="despesa" {{ old('tipo') === 'despesa' ? 'selected' : '' }}>Despesa</option>
                                <option value="custo" {{ old('tipo') === 'custo' ? 'selected' : '' }}>Custo</option>
                            </select>
                            @error('tipo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="natureza" class="form-label">Natureza <span class="text-danger">*</span></label>
                            <select name="natureza" id="natureza" class="form-select @error('natureza') is-invalid @enderror" required>
                                <option value="">Selecione...</option>
                                <option value="sintetica" {{ old('natureza') === 'sintetica' ? 'selected' : '' }}>Sintetica (Grupo)</option>
                                <option value="analitica" {{ old('natureza') === 'analitica' ? 'selected' : '' }}>Analitica (Lancavel)</option>
                            </select>
                            @error('natureza')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Salvar
                        </button>
                        <a href="{{ route('app.plano-contas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle me-1"></i> Dicas</h6>
            </div>
            <div class="card-body small text-muted">
                <p><strong>Sintetica:</strong> Conta de grupo que agrupa subcontas. Nao recebe lancamentos diretamente.</p>
                <p><strong>Analitica:</strong> Conta que recebe lancamentos financeiros.</p>
                <p class="mb-0"><strong>Codigo:</strong> Use hierarquia com pontos (ex: 1, 1.1, 1.1.1).</p>
            </div>
        </div>
    </div>
</div>
@endsection
