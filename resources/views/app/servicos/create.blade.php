@extends('layouts.app')

@section('title', 'Novo Servico')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-tools me-2"></i>Novo Servico</h4>
    <a href="{{ route('app.servicos.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<form method="POST" action="{{ route('app.servicos.store') }}">
    @csrf

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
                                   value="{{ old('codigo') }}" placeholder="Ex: SRV001">
                            @error('codigo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-9">
                            <label for="descricao" class="form-label fw-semibold">Descricao <span class="text-danger">*</span></label>
                            <input type="text" name="descricao" id="descricao"
                                   class="form-control @error('descricao') is-invalid @enderror"
                                   value="{{ old('descricao') }}" required placeholder="Ex: Manutencao preventiva, Instalacao de software...">
                            @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="valor_padrao" class="form-label fw-semibold">Valor Padrao (R$) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" name="valor_padrao" id="valor_padrao"
                                       class="form-control @error('valor_padrao') is-invalid @enderror"
                                       value="{{ old('valor_padrao', '0.00') }}" step="0.01" min="0" required>
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
                               value="{{ old('codigo_servico_municipal') }}" placeholder="Ex: 14.01">
                        @error('codigo_servico_municipal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-3">
                        <label for="cnae" class="form-label fw-semibold">CNAE</label>
                        <input type="text" name="cnae" id="cnae"
                               class="form-control @error('cnae') is-invalid @enderror"
                               value="{{ old('cnae') }}" placeholder="Ex: 6201-5/01">
                        @error('cnae') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="iss_aliquota" class="form-label fw-semibold">ISS (%)</label>
                        <div class="input-group">
                            <input type="number" name="iss_aliquota" id="iss_aliquota"
                                   class="form-control @error('iss_aliquota') is-invalid @enderror"
                                   value="{{ old('iss_aliquota', '0.00') }}" step="0.01" min="0" max="100">
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
                    <i class="bi bi-check-lg me-1"></i> Salvar Servico
                </button>
            </div>
        </div>
    </div>
</form>
@endsection
