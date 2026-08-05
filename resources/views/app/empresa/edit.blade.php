@extends('layouts.app')

@section('title', 'Minha Empresa')

@section('content')
<x-erp.page-header title="Minha Empresa" subtitle="Dados cadastrais, contato e logo" icon="building">
</x-erp.page-header>

<form action="{{ route('app.empresa.update') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <x-erp.form-section title="Identificação" icon="building">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">CNPJ</label>
                <input type="text" class="form-control" value="{{ $empresa->cnpj }}" disabled>
                <div class="form-text">Alteração de CNPJ ou razão social: fale com o suporte da IA365.</div>
            </div>
            <div class="col-md-8">
                <label class="form-label">Razão Social</label>
                <input type="text" class="form-control" value="{{ $empresa->razao_social }}" disabled>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nome Fantasia</label>
                <input type="text" class="form-control @error('nome_fantasia') is-invalid @enderror"
                       name="nome_fantasia" value="{{ old('nome_fantasia', $empresa->nome_fantasia) }}">
                @error('nome_fantasia')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Logo <span class="text-muted small">(aparece nas etiquetas e documentos)</span></label>
                <input type="file" class="form-control @error('logo') is-invalid @enderror" name="logo" accept="image/*">
                @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if($empresa->logo)
                    <div class="mt-2 d-flex align-items-center gap-2">
                        <img src="{{ asset('storage/' . $empresa->logo) }}" alt="Logo" style="max-height:48px; max-width:160px;">
                        <span class="text-muted small">logo atual — enviar um novo substitui</span>
                    </div>
                @endif
            </div>
        </div>
    </x-erp.form-section>

    <x-erp.form-section title="Contato e Endereço" icon="geo-alt">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Telefone</label>
                <input type="text" class="form-control @error('telefone') is-invalid @enderror"
                       name="telefone" value="{{ old('telefone', $empresa->telefone) }}" data-mask="telefone">
                @error('telefone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-5">
                <label class="form-label">E-mail</label>
                <input type="email" class="form-control @error('email') is-invalid @enderror"
                       name="email" value="{{ old('email', $empresa->email) }}">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Código do Município (IBGE) <span class="text-muted small">— 7 dígitos, usado nas notas fiscais</span></label>
                <input type="text" class="form-control @error('codigo_municipio') is-invalid @enderror"
                       name="codigo_municipio" value="{{ old('codigo_municipio', $empresa->codigo_municipio) }}" maxlength="7">
                @error('codigo_municipio')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">CEP</label>
                <input type="text" class="form-control @error('cep') is-invalid @enderror"
                       name="cep" value="{{ old('cep', $empresa->cep) }}" data-mask="cep" data-cep>
                @error('cep')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Logradouro</label>
                <input type="text" class="form-control" name="logradouro" value="{{ old('logradouro', $empresa->logradouro) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Número</label>
                <input type="text" class="form-control" name="numero" value="{{ old('numero', $empresa->numero) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Complemento</label>
                <input type="text" class="form-control" name="complemento" value="{{ old('complemento', $empresa->complemento) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Bairro</label>
                <input type="text" class="form-control" name="bairro" value="{{ old('bairro', $empresa->bairro) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Cidade</label>
                <input type="text" class="form-control" name="cidade" value="{{ old('cidade', $empresa->cidade) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">UF</label>
                <input type="text" class="form-control" name="uf" value="{{ old('uf', $empresa->uf) }}" maxlength="2" style="text-transform:uppercase">
            </div>
        </div>
    </x-erp.form-section>

    <div class="d-flex justify-content-end gap-2 mt-3">
        <button type="submit" class="btn btn-erp-primary"><i class="bi bi-check-lg me-1"></i> Salvar</button>
    </div>
</form>
@endsection
