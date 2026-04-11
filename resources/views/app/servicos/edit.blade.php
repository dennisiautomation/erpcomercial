@extends('layouts.app')

@section('title', 'Editar Servico')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Editar Servico</h4>
    <a href="{{ route('app.servicos.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<form method="POST" action="{{ route('app.servicos.update', $servico) }}">
    @csrf
    @method('PUT')

    <div class="row g-4">
        {{-- Dados do Servico --}}
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-info-circle me-1"></i> Dados do Servico
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="codigo" class="form-label fw-semibold">Codigo</label>
                            <input type="text" name="codigo" id="codigo"
                                   class="form-control @error('codigo') is-invalid @enderror"
                                   value="{{ old('codigo', $servico->codigo) }}">
                            @error('codigo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="descricao" class="form-label fw-semibold">Descricao <span class="text-danger">*</span></label>
                            <input type="text" name="descricao" id="descricao"
                                   class="form-control @error('descricao') is-invalid @enderror"
                                   value="{{ old('descricao', $servico->descricao) }}" required>
                            @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="ativo" {{ old('status', $servico->status) === 'ativo' ? 'selected' : '' }}>Ativo</option>
                                <option value="inativo" {{ old('status', $servico->status) === 'inativo' ? 'selected' : '' }}>Inativo</option>
                            </select>
                            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="valor_padrao" class="form-label fw-semibold">Valor Padrao (R$) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" name="valor_padrao" id="valor_padrao"
                                       class="form-control @error('valor_padrao') is-invalid @enderror"
                                       value="{{ old('valor_padrao', $servico->valor_padrao) }}" step="0.01" min="0" required>
                            </div>
                            @error('valor_padrao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Dados Fiscais --}}
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-file-earmark-text me-1"></i> Dados Fiscais
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="codigo_servico_municipal" class="form-label fw-semibold">Cod. Servico Municipal</label>
                        <input type="text" name="codigo_servico_municipal" id="codigo_servico_municipal"
                               class="form-control @error('codigo_servico_municipal') is-invalid @enderror"
                               value="{{ old('codigo_servico_municipal', $servico->codigo_servico_municipal) }}">
                        @error('codigo_servico_municipal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label for="cnae" class="form-label fw-semibold">CNAE</label>
                        <input type="text" name="cnae" id="cnae"
                               class="form-control @error('cnae') is-invalid @enderror"
                               value="{{ old('cnae', $servico->cnae) }}">
                        @error('cnae') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="iss_aliquota" class="form-label fw-semibold">ISS (%)</label>
                        <div class="input-group">
                            <input type="number" name="iss_aliquota" id="iss_aliquota"
                                   class="form-control @error('iss_aliquota') is-invalid @enderror"
                                   value="{{ old('iss_aliquota', $servico->iss_aliquota) }}" step="0.01" min="0" max="100">
                            <span class="input-group-text">%</span>
                        </div>
                        @error('iss_aliquota') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="col-12">
            <hr>
            <div class="d-flex justify-content-between">
                <a href="{{ route('app.servicos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg me-1"></i> Atualizar Servico
                </button>
            </div>
        </div>
    </div>
</form>
@endsection
