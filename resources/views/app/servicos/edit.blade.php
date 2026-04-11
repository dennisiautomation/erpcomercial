@extends('layouts.app')

@section('title', 'Editar Serviço')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Editar Serviço</h4>
    <a href="{{ route('app.servicos.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('app.servicos.update', $servico) }}">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-3">
                    <label for="codigo" class="form-label">Código</label>
                    <input type="text" name="codigo" id="codigo" class="form-control @error('codigo') is-invalid @enderror" value="{{ old('codigo', $servico->codigo) }}">
                    @error('codigo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label for="descricao" class="form-label fw-semibold">Descrição <span class="text-danger">*</span></label>
                    <input type="text" name="descricao" id="descricao" class="form-control @error('descricao') is-invalid @enderror" value="{{ old('descricao', $servico->descricao) }}" required>
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
                <div class="col-md-3">
                    <label for="valor_padrao" class="form-label fw-semibold">Valor Padrão (R$) <span class="text-danger">*</span></label>
                    <input type="number" name="valor_padrao" id="valor_padrao" class="form-control @error('valor_padrao') is-invalid @enderror" value="{{ old('valor_padrao', $servico->valor_padrao) }}" step="0.01" min="0" required>
                    @error('valor_padrao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="codigo_servico_municipal" class="form-label">Cód. Serviço Municipal</label>
                    <input type="text" name="codigo_servico_municipal" id="codigo_servico_municipal" class="form-control @error('codigo_servico_municipal') is-invalid @enderror" value="{{ old('codigo_servico_municipal', $servico->codigo_servico_municipal) }}">
                    @error('codigo_servico_municipal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="cnae" class="form-label">CNAE</label>
                    <input type="text" name="cnae" id="cnae" class="form-control @error('cnae') is-invalid @enderror" value="{{ old('cnae', $servico->cnae) }}">
                    @error('cnae') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="iss_aliquota" class="form-label">ISS (%)</label>
                    <input type="number" name="iss_aliquota" id="iss_aliquota" class="form-control @error('iss_aliquota') is-invalid @enderror" value="{{ old('iss_aliquota', $servico->iss_aliquota) }}" step="0.01" min="0" max="100">
                    @error('iss_aliquota') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('app.servicos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Atualizar Serviço
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
