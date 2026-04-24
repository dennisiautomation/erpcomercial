@extends('layouts.app')

@section('title', 'Inutilizar Numeracao')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-slash-circle me-2"></i>Inutilizar Numeracao</h4>
    <a href="{{ route('app.notas-fiscais.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-slash-circle me-1"></i> Dados para Inutilizacao</div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    A inutilizacao de numeracao e irreversivel. Utilize somente para numeros que foram pulados na sequencia de emissao.
                </div>

                <form method="POST" action="{{ route('app.notas-fiscais.inutilizar.store') }}">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select name="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
                                <option value="">Selecione...</option>
                                <option value="nfe" {{ old('tipo') === 'nfe' ? 'selected' : '' }}>NF-e</option>
                                <option value="nfce" {{ old('tipo') === 'nfce' ? 'selected' : '' }}>NFC-e</option>
                            </select>
                            @error('tipo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Serie <span class="text-danger">*</span></label>
                            <input type="number" name="serie" class="form-control @error('serie') is-invalid @enderror"
                                   min="1" required value="{{ old('serie', 1) }}">
                            @error('serie')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Numero Inicial <span class="text-danger">*</span></label>
                            <input type="number" name="numero_inicial" class="form-control @error('numero_inicial') is-invalid @enderror"
                                   min="1" required value="{{ old('numero_inicial') }}">
                            @error('numero_inicial')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Numero Final <span class="text-danger">*</span></label>
                            <input type="number" name="numero_final" class="form-control @error('numero_final') is-invalid @enderror"
                                   min="1" required value="{{ old('numero_final') }}">
                            @error('numero_final')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label">Justificativa <span class="text-danger">*</span> <small class="text-muted">(minimo 15 caracteres)</small></label>
                            <textarea name="justificativa" class="form-control @error('justificativa') is-invalid @enderror"
                                      rows="3" minlength="15" maxlength="255" required placeholder="Informe o motivo da inutilizacao...">{{ old('justificativa') }}</textarea>
                            @error('justificativa')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <hr>
                            <button type="submit" class="btn btn-danger" data-confirm="Confirma a inutilizacao da numeracao? Esta acao e irreversivel.">
                                <i class="bi bi-slash-circle me-1"></i> Inutilizar Numeracao
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
