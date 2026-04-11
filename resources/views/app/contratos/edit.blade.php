@extends('layouts.app')

@section('title', 'Editar Contrato')

@section('content')
<x-erp.page-header title="Editar Contrato" icon="pencil-square">
    <a href="{{ route('app.contratos.show', $contrato) }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

<form action="{{ route('app.contratos.update', $contrato) }}" method="POST">
    @csrf
    @method('PUT')

    <x-erp.form-section title="Dados do Contrato" icon="file-earmark-text">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="cliente_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                <select name="cliente_id" id="cliente_id" class="form-select @error('cliente_id') is-invalid @enderror" required>
                    <option value="">Selecione...</option>
                    @foreach($clientes as $cliente)
                        <option value="{{ $cliente->id }}" @selected(old('cliente_id', $contrato->cliente_id) == $cliente->id)>{{ $cliente->nome_razao_social }}</option>
                    @endforeach
                </select>
                @error('cliente_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="descricao" class="form-label">Descricao <span class="text-danger">*</span></label>
                <input type="text" name="descricao" id="descricao" class="form-control @error('descricao') is-invalid @enderror" value="{{ old('descricao', $contrato->descricao) }}" required>
                @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-3">
                <label for="valor" class="form-label">Valor <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">R$</span>
                    <input type="number" name="valor" id="valor" class="form-control @error('valor') is-invalid @enderror" step="0.01" min="0.01" value="{{ old('valor', $contrato->valor) }}" required>
                </div>
                @error('valor') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-3">
                <label for="periodicidade" class="form-label">Periodicidade <span class="text-danger">*</span></label>
                <select name="periodicidade" id="periodicidade" class="form-select @error('periodicidade') is-invalid @enderror" required>
                    <option value="mensal" @selected(old('periodicidade', $contrato->periodicidade) == 'mensal')>Mensal</option>
                    <option value="trimestral" @selected(old('periodicidade', $contrato->periodicidade) == 'trimestral')>Trimestral</option>
                    <option value="semestral" @selected(old('periodicidade', $contrato->periodicidade) == 'semestral')>Semestral</option>
                    <option value="anual" @selected(old('periodicidade', $contrato->periodicidade) == 'anual')>Anual</option>
                </select>
                @error('periodicidade') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-3">
                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                    <option value="ativo" @selected(old('status', $contrato->status) == 'ativo')>Ativo</option>
                    <option value="vencido" @selected(old('status', $contrato->status) == 'vencido')>Vencido</option>
                    <option value="cancelado" @selected(old('status', $contrato->status) == 'cancelado')>Cancelado</option>
                    <option value="suspenso" @selected(old('status', $contrato->status) == 'suspenso')>Suspenso</option>
                </select>
                @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-3">
                <label for="inicio" class="form-label">Data Inicio <span class="text-danger">*</span></label>
                <input type="date" name="inicio" id="inicio" class="form-control @error('inicio') is-invalid @enderror" value="{{ old('inicio', $contrato->inicio?->format('Y-m-d')) }}" required>
                @error('inicio') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-3">
                <label for="fim" class="form-label">Data Fim</label>
                <input type="date" name="fim" id="fim" class="form-control @error('fim') is-invalid @enderror" value="{{ old('fim', $contrato->fim?->format('Y-m-d')) }}">
                @error('fim') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-12">
                <label for="observacoes" class="form-label">Observacoes</label>
                <textarea name="observacoes" id="observacoes" class="form-control @error('observacoes') is-invalid @enderror" rows="3">{{ old('observacoes', $contrato->observacoes) }}</textarea>
                @error('observacoes') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-erp-primary">
                <i class="bi bi-check-lg me-1"></i> Atualizar Contrato
            </button>
        </div>
    </x-erp.form-section>
</form>
@endsection
