@extends('layouts.app')

@section('title', 'Novo Serviço')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-tools me-2"></i>Novo Serviço</h4>
    <a href="{{ route('app.servicos.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('app.servicos.store') }}">
            @csrf

            <div class="row g-3">
                <div class="col-md-3">
                    <label for="codigo" class="form-label">Código</label>
                    <input type="text" name="codigo" id="codigo" class="form-control @error('codigo') is-invalid @enderror" value="{{ old('codigo') }}">
                    @error('codigo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-9">
                    <label for="descricao" class="form-label fw-semibold">Descrição <span class="text-danger">*</span></label>
                    <input type="text" name="descricao" id="descricao" class="form-control @error('descricao') is-invalid @enderror" value="{{ old('descricao') }}" required>
                    @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="valor_padrao" class="form-label fw-semibold">Valor Padrão (R$) <span class="text-danger">*</span></label>
                    <input type="number" name="valor_padrao" id="valor_padrao" class="form-control @error('valor_padrao') is-invalid @enderror" value="{{ old('valor_padrao', '0.00') }}" step="0.01" min="0" required>
                    @error('valor_padrao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="codigo_servico_municipal" class="form-label">Cód. Serviço Municipal</label>
                    <input type="text" name="codigo_servico_municipal" id="codigo_servico_municipal" class="form-control @error('codigo_servico_municipal') is-invalid @enderror" value="{{ old('codigo_servico_municipal') }}">
                    @error('codigo_servico_municipal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="cnae" class="form-label">CNAE</label>
                    <input type="text" name="cnae" id="cnae" class="form-control @error('cnae') is-invalid @enderror" value="{{ old('cnae') }}">
                    @error('cnae') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="iss_aliquota" class="form-label">ISS (%)</label>
                    <input type="number" name="iss_aliquota" id="iss_aliquota" class="form-control @error('iss_aliquota') is-invalid @enderror" value="{{ old('iss_aliquota', '0.00') }}" step="0.01" min="0" max="100">
                    @error('iss_aliquota') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('app.servicos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Salvar Serviço
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
