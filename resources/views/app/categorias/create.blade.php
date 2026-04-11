@extends('layouts.app')

@section('title', 'Nova Categoria')

@section('content')
<x-erp.page-header title="Nova Categoria" icon="tag">
    <a href="{{ route('app.categorias.index') }}" class="btn btn-erp-outline"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
</x-erp.page-header>

<form method="POST" action="{{ route('app.categorias.store') }}" class="erp-form">
    @csrf

    <x-erp.form-section title="Dados da Categoria" icon="tag">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="nome" class="form-label fw-semibold">Nome <span class="text-danger">*</span></label>
                <input type="text" name="nome" id="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}" required>
                @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label for="parent_id" class="form-label">Categoria Pai</label>
                <select name="parent_id" id="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
                    <option value="">Nenhuma (categoria raiz)</option>
                    @foreach($pais as $pai)
                        <option value="{{ $pai->id }}" {{ old('parent_id') == $pai->id ? 'selected' : '' }}>{{ $pai->nome }}</option>
                    @endforeach
                </select>
                @error('parent_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-12">
                <label for="descricao" class="form-label">Descricao</label>
                <textarea name="descricao" id="descricao" class="form-control @error('descricao') is-invalid @enderror" rows="3">{{ old('descricao') }}</textarea>
                @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </x-erp.form-section>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-erp-primary"><i class="bi bi-check-lg me-1"></i>Salvar Categoria</button>
        <a href="{{ route('app.categorias.index') }}" class="btn btn-erp-outline">Cancelar</a>
    </div>
</form>
@endsection
