@extends('layouts.app')

@section('title', 'Novo Contrato')

@section('content')
<x-erp.page-header title="Novo Contrato" icon="file-earmark-plus">
    <a href="{{ route('app.contratos.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

<form action="{{ route('app.contratos.store') }}" method="POST">
    @csrf

    <x-erp.form-section title="Dados do Contrato" icon="file-earmark-text">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="cliente_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                <select name="cliente_id" id="cliente_id" class="form-select @error('cliente_id') is-invalid @enderror" required>
                    <option value="">Selecione...</option>
                    @foreach($clientes as $cliente)
                        <option value="{{ $cliente->id }}" @selected(old('cliente_id') == $cliente->id)>{{ $cliente->nome_razao_social }}</option>
                    @endforeach
                </select>
                @error('cliente_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="descricao" class="form-label">Descricao <span class="text-danger">*</span></label>
                <input type="text" name="descricao" id="descricao" class="form-control @error('descricao') is-invalid @enderror" value="{{ old('descricao') }}" required>
                @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-3">
                <label for="valor" class="form-label">Valor <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">R$</span>
                    <input type="number" name="valor" id="valor" class="form-control @error('valor') is-invalid @enderror" step="0.01" min="0.01" value="{{ old('valor') }}" required>
                </div>
                @error('valor') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-3">
                <label for="periodicidade" class="form-label">Periodicidade <span class="text-danger">*</span></label>
                <select name="periodicidade" id="periodicidade" class="form-select @error('periodicidade') is-invalid @enderror" required>
                    <option value="mensal" @selected(old('periodicidade') == 'mensal')>Mensal</option>
                    <option value="trimestral" @selected(old('periodicidade') == 'trimestral')>Trimestral</option>
                    <option value="semestral" @selected(old('periodicidade') == 'semestral')>Semestral</option>
                    <option value="anual" @selected(old('periodicidade') == 'anual')>Anual</option>
                </select>
                @error('periodicidade') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-3">
                <label for="inicio" class="form-label">Data Inicio <span class="text-danger">*</span></label>
                <input type="date" name="inicio" id="inicio" class="form-control @error('inicio') is-invalid @enderror" value="{{ old('inicio') }}" required>
                @error('inicio') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-3">
                <label for="fim" class="form-label">Data Fim</label>
                <input type="date" name="fim" id="fim" class="form-control @error('fim') is-invalid @enderror" value="{{ old('fim') }}">
                @error('fim') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-12">
                <label for="observacoes" class="form-label">Observacoes</label>
                <textarea name="observacoes" id="observacoes" class="form-control @error('observacoes') is-invalid @enderror" rows="3">{{ old('observacoes') }}</textarea>
                @error('observacoes') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-erp-primary">
                <i class="bi bi-check-lg me-1"></i> Salvar Contrato
            </button>
        </div>
    </x-erp.form-section>
</form>
@endsection
