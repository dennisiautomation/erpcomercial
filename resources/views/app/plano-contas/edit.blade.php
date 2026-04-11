@extends('layouts.app')

@section('title', 'Editar Conta')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-pencil me-2"></i>Editar Conta</h4>
    <a href="{{ route('app.plano-contas.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('app.plano-contas.update', $planoContas) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="parent_id" class="form-label">Conta Pai</label>
                        <select name="parent_id" id="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
                            <option value="">Nenhuma (Conta Raiz)</option>
                            @foreach($contasPai as $contaPai)
                                <option value="{{ $contaPai->id }}" {{ old('parent_id', $planoContas->parent_id) == $contaPai->id ? 'selected' : '' }}>
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
                                   value="{{ old('codigo', $planoContas->codigo) }}" required>
                            @error('codigo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-8 mb-3">
                            <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" name="nome" id="nome" class="form-control @error('nome') is-invalid @enderror"
                                   value="{{ old('nome', $planoContas->nome) }}" required>
                            @error('nome')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo" id="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
                                <option value="receita" {{ old('tipo', $planoContas->tipo) === 'receita' ? 'selected' : '' }}>Receita</option>
                                <option value="despesa" {{ old('tipo', $planoContas->tipo) === 'despesa' ? 'selected' : '' }}>Despesa</option>
                                <option value="custo" {{ old('tipo', $planoContas->tipo) === 'custo' ? 'selected' : '' }}>Custo</option>
                            </select>
                            @error('tipo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="natureza" class="form-label">Natureza <span class="text-danger">*</span></label>
                            <select name="natureza" id="natureza" class="form-select @error('natureza') is-invalid @enderror" required>
                                <option value="sintetica" {{ old('natureza', $planoContas->natureza) === 'sintetica' ? 'selected' : '' }}>Sintetica (Grupo)</option>
                                <option value="analitica" {{ old('natureza', $planoContas->natureza) === 'analitica' ? 'selected' : '' }}>Analitica (Lancavel)</option>
                            </select>
                            @error('natureza')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="hidden" name="ativo" value="0">
                            <input class="form-check-input" type="checkbox" name="ativo" id="ativo" value="1"
                                   {{ old('ativo', $planoContas->ativo) ? 'checked' : '' }}>
                            <label class="form-check-label" for="ativo">Conta Ativa</label>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Atualizar
                        </button>
                        <a href="{{ route('app.plano-contas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
