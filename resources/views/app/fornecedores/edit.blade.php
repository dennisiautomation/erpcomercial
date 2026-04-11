@extends('layouts.app')

@section('title', 'Editar Fornecedor')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Editar Fornecedor</h4>
        <small class="text-muted">{{ $fornecedore->razao_social }}</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('app.fornecedores.show', $fornecedore) }}" class="btn btn-outline-info">
            <i class="bi bi-eye me-1"></i> Visualizar
        </a>
        <a href="{{ route('app.fornecedores.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

<form method="POST" action="{{ route('app.fornecedores.update', $fornecedore) }}" id="formFornecedor" novalidate>
    @csrf
    @method('PUT')

    {{-- Identificacao --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-person-badge me-2"></i>Identificacao</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="cpf_cnpj" class="form-label fw-semibold">CPF/CNPJ <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                        <input type="text" name="cpf_cnpj" id="cpf_cnpj" class="form-control @error('cpf_cnpj') is-invalid @enderror" value="{{ old('cpf_cnpj', $fornecedore->cpf_cnpj) }}" maxlength="18" required>
                        @error('cpf_cnpj')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="col-md-8">
                    <label for="razao_social" class="form-label fw-semibold">Razao Social <span class="text-danger">*</span></label>
                    <input type="text" name="razao_social" id="razao_social" class="form-control @error('razao_social') is-invalid @enderror" value="{{ old('razao_social', $fornecedore->razao_social) }}" required>
                    @error('razao_social')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="nome_fantasia" class="form-label">Nome Fantasia</label>
                    <input type="text" name="nome_fantasia" id="nome_fantasia" class="form-control @error('nome_fantasia') is-invalid @enderror" value="{{ old('nome_fantasia', $fornecedore->nome_fantasia) }}">
                    @error('nome_fantasia')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="contato_representante" class="form-label">Contato / Representante</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="contato_representante" id="contato_representante" class="form-control @error('contato_representante') is-invalid @enderror" value="{{ old('contato_representante', $fornecedore->contato_representante) }}">
                        @error('contato_representante')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Endereco --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Endereco</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <label for="cep" class="form-label">CEP</label>
                    <div class="input-group">
                        <input type="text" name="cep" id="cep" class="form-control @error('cep') is-invalid @enderror" value="{{ old('cep', $fornecedore->cep) }}" maxlength="9" placeholder="00000-000">
                        <button type="button" class="btn btn-outline-secondary" id="btnBuscarCep" title="Buscar CEP">
                            <i class="bi bi-search"></i>
                        </button>
                        @error('cep') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-text" id="cepFeedback"></div>
                </div>
                <div class="col-md-5">
                    <label for="logradouro" class="form-label">Logradouro</label>
                    <input type="text" name="logradouro" id="logradouro" class="form-control @error('logradouro') is-invalid @enderror" value="{{ old('logradouro', $fornecedore->logradouro) }}">
                    @error('logradouro') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label for="numero" class="form-label">Numero</label>
                    <input type="text" name="numero" id="numero" class="form-control @error('numero') is-invalid @enderror" value="{{ old('numero', $fornecedore->numero) }}">
                    @error('numero') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="complemento" class="form-label">Complemento</label>
                    <input type="text" name="complemento" id="complemento" class="form-control @error('complemento') is-invalid @enderror" value="{{ old('complemento', $fornecedore->complemento) }}">
                    @error('complemento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label for="bairro" class="form-label">Bairro</label>
                    <input type="text" name="bairro" id="bairro" class="form-control @error('bairro') is-invalid @enderror" value="{{ old('bairro', $fornecedore->bairro) }}">
                    @error('bairro') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-5">
                    <label for="cidade" class="form-label">Cidade</label>
                    <input type="text" name="cidade" id="cidade" class="form-control @error('cidade') is-invalid @enderror" value="{{ old('cidade', $fornecedore->cidade) }}">
                    @error('cidade') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="uf" class="form-label">UF</label>
                    <select name="uf" id="uf" class="form-select @error('uf') is-invalid @enderror">
                        <option value="">Selecione</option>
                        @foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $sigla)
                            <option value="{{ $sigla }}" {{ old('uf', $fornecedore->uf) === $sigla ? 'selected' : '' }}>{{ $sigla }}</option>
                        @endforeach
                    </select>
                    @error('uf') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Contato e Condicoes --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-telephone me-2"></i>Contato e Condicoes Comerciais</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="telefone" class="form-label">Telefone</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                        <input type="text" name="telefone" id="telefone" class="form-control @error('telefone') is-invalid @enderror" value="{{ old('telefone', $fornecedore->telefone) }}" maxlength="15" placeholder="(00) 00000-0000">
                        @error('telefone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-8">
                    <label for="email" class="form-label">E-mail</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $fornecedore->email) }}" placeholder="email@exemplo.com">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-12">
                    <label for="condicoes_comerciais" class="form-label">Condicoes Comerciais</label>
                    <textarea name="condicoes_comerciais" id="condicoes_comerciais" class="form-control @error('condicoes_comerciais') is-invalid @enderror" rows="3" placeholder="Prazo de pagamento, descontos, frete, etc.">{{ old('condicoes_comerciais', $fornecedore->condicoes_comerciais) }}</textarea>
                    @error('condicoes_comerciais') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Botoes --}}
    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="{{ route('app.fornecedores.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg me-1"></i> Cancelar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> Atualizar Fornecedor
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- CPF/CNPJ Mask ---
    const cpfCnpjInput = document.getElementById('cpf_cnpj');
    cpfCnpjInput.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '');
        if (v.length <= 11) {
            v = v.replace(/(\d{3})(\d)/, '$1.$2');
            v = v.replace(/(\d{3})(\d)/, '$1.$2');
            v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        } else {
            v = v.substring(0, 14);
            v = v.replace(/^(\d{2})(\d)/, '$1.$2');
            v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
            v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
            v = v.replace(/(\d{4})(\d)/, '$1-$2');
        }
        this.value = v;
    });

    // --- Telefone Mask ---
    const telefoneInput = document.getElementById('telefone');
    telefoneInput.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '');
        if (v.length <= 10) {
            v = v.replace(/^(\d{2})(\d)/, '($1) $2');
            v = v.replace(/(\d{4})(\d)/, '$1-$2');
        } else {
            v = v.substring(0, 11);
            v = v.replace(/^(\d{2})(\d)/, '($1) $2');
            v = v.replace(/(\d{5})(\d)/, '$1-$2');
        }
        this.value = v;
    });

    // --- CEP Mask ---
    const cepInput = document.getElementById('cep');
    cepInput.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').substring(0, 8);
        if (v.length > 5) v = v.substring(0, 5) + '-' + v.substring(5);
        this.value = v;
    });

    // --- ViaCEP ---
    function buscarCep() {
        const cep = cepInput.value.replace(/\D/g, '');
        const feedback = document.getElementById('cepFeedback');
        if (cep.length !== 8) {
            feedback.textContent = 'CEP deve ter 8 digitos';
            feedback.className = 'form-text text-danger';
            return;
        }
        feedback.textContent = 'Buscando...';
        feedback.className = 'form-text text-info';

        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(r => r.json())
            .then(data => {
                if (data.erro) {
                    feedback.textContent = 'CEP nao encontrado';
                    feedback.className = 'form-text text-danger';
                    return;
                }
                document.getElementById('logradouro').value = data.logradouro || '';
                document.getElementById('bairro').value = data.bairro || '';
                document.getElementById('cidade').value = data.localidade || '';
                document.getElementById('uf').value = data.uf || '';
                document.getElementById('numero').focus();
                feedback.textContent = 'Endereco preenchido';
                feedback.className = 'form-text text-success';
                setTimeout(() => { feedback.textContent = ''; }, 3000);
            })
            .catch(() => {
                feedback.textContent = 'Erro ao buscar CEP';
                feedback.className = 'form-text text-danger';
            });
    }

    cepInput.addEventListener('blur', function () {
        if (this.value.replace(/\D/g, '').length === 8) buscarCep();
    });
    document.getElementById('btnBuscarCep').addEventListener('click', buscarCep);
});
</script>
@endpush
