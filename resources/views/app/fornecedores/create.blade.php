@extends('layouts.app')

@section('title', 'Novo Fornecedor')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-truck me-2"></i>Novo Fornecedor</h4>
    <a href="{{ route('app.fornecedores.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<form method="POST" action="{{ route('app.fornecedores.store') }}">
    @csrf

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-person-badge me-2"></i>Identificação</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Tipo de Pessoa <span class="text-danger">*</span></label>
                    <div class="d-flex gap-3 mt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo_pessoa" id="tipoPF" value="PF" {{ old('tipo_pessoa', 'PJ') === 'PF' ? 'checked' : '' }}>
                            <label class="form-check-label" for="tipoPF">Pessoa Física</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo_pessoa" id="tipoPJ" value="PJ" {{ old('tipo_pessoa', 'PJ') === 'PJ' ? 'checked' : '' }}>
                            <label class="form-check-label" for="tipoPJ">Pessoa Jurídica</label>
                        </div>
                    </div>
                    @error('tipo_pessoa')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="cpf_cnpj" class="form-label fw-semibold" id="labelCpfCnpj">CNPJ <span class="text-danger">*</span></label>
                    <input type="text" name="cpf_cnpj" id="cpf_cnpj" class="form-control @error('cpf_cnpj') is-invalid @enderror" value="{{ old('cpf_cnpj') }}" maxlength="18" required>
                    @error('cpf_cnpj')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="nome_razao_social" class="form-label fw-semibold" id="labelNome">Razão Social <span class="text-danger">*</span></label>
                    <input type="text" name="nome_razao_social" id="nome_razao_social" class="form-control @error('nome_razao_social') is-invalid @enderror" value="{{ old('nome_razao_social') }}" required>
                    @error('nome_razao_social')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 campos-pj">
                    <label for="nome_fantasia" class="form-label">Nome Fantasia</label>
                    <input type="text" name="nome_fantasia" id="nome_fantasia" class="form-control @error('nome_fantasia') is-invalid @enderror" value="{{ old('nome_fantasia') }}">
                    @error('nome_fantasia')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3 campos-pj">
                    <label for="ie" class="form-label">Inscrição Estadual</label>
                    <input type="text" name="ie" id="ie" class="form-control @error('ie') is-invalid @enderror" value="{{ old('ie') }}">
                    @error('ie')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Endereço</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <label for="cep" class="form-label">CEP</label>
                    <input type="text" name="cep" id="cep" class="form-control @error('cep') is-invalid @enderror" value="{{ old('cep') }}" maxlength="9" placeholder="00000-000">
                    @error('cep') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-5">
                    <label for="logradouro" class="form-label">Logradouro</label>
                    <input type="text" name="logradouro" id="logradouro" class="form-control @error('logradouro') is-invalid @enderror" value="{{ old('logradouro') }}">
                    @error('logradouro') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <label for="numero" class="form-label">Número</label>
                    <input type="text" name="numero" id="numero" class="form-control @error('numero') is-invalid @enderror" value="{{ old('numero') }}">
                    @error('numero') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="complemento" class="form-label">Complemento</label>
                    <input type="text" name="complemento" id="complemento" class="form-control @error('complemento') is-invalid @enderror" value="{{ old('complemento') }}">
                    @error('complemento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label for="bairro" class="form-label">Bairro</label>
                    <input type="text" name="bairro" id="bairro" class="form-control @error('bairro') is-invalid @enderror" value="{{ old('bairro') }}">
                    @error('bairro') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-5">
                    <label for="cidade" class="form-label">Cidade</label>
                    <input type="text" name="cidade" id="cidade" class="form-control @error('cidade') is-invalid @enderror" value="{{ old('cidade') }}">
                    @error('cidade') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="uf" class="form-label">UF</label>
                    <select name="uf" id="uf" class="form-select @error('uf') is-invalid @enderror">
                        <option value="">Selecione</option>
                        @foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $sigla)
                            <option value="{{ $sigla }}" {{ old('uf') === $sigla ? 'selected' : '' }}>{{ $sigla }}</option>
                        @endforeach
                    </select>
                    @error('uf') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-telephone me-2"></i>Contato</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="telefone" class="form-label">Telefone</label>
                    <input type="text" name="telefone" id="telefone" class="form-control @error('telefone') is-invalid @enderror" value="{{ old('telefone') }}">
                    @error('telefone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="whatsapp" class="form-label">WhatsApp</label>
                    <input type="text" name="whatsapp" id="whatsapp" class="form-control @error('whatsapp') is-invalid @enderror" value="{{ old('whatsapp') }}">
                    @error('whatsapp') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-12">
                    <label for="observacoes" class="form-label">Observações</label>
                    <textarea name="observacoes" id="observacoes" class="form-control @error('observacoes') is-invalid @enderror" rows="2">{{ old('observacoes') }}</textarea>
                    @error('observacoes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('app.fornecedores.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> Salvar Fornecedor
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tipoPF = document.getElementById('tipoPF');
        const tipoPJ = document.getElementById('tipoPJ');
        const camposPJ = document.querySelectorAll('.campos-pj');
        const labelCpfCnpj = document.getElementById('labelCpfCnpj');
        const labelNome = document.getElementById('labelNome');
        const cpfCnpjInput = document.getElementById('cpf_cnpj');

        function togglePJ() {
            const isPJ = tipoPJ.checked;
            camposPJ.forEach(el => el.style.display = isPJ ? '' : 'none');
            labelCpfCnpj.innerHTML = isPJ ? 'CNPJ <span class="text-danger">*</span>' : 'CPF <span class="text-danger">*</span>';
            labelNome.innerHTML = isPJ ? 'Razão Social <span class="text-danger">*</span>' : 'Nome Completo <span class="text-danger">*</span>';
        }

        tipoPF.addEventListener('change', togglePJ);
        tipoPJ.addEventListener('change', togglePJ);
        togglePJ();

        cpfCnpjInput.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '');
            if (v.length <= 11) {
                v = v.replace(/(\d{3})(\d)/, '$1.$2');
                v = v.replace(/(\d{3})(\d)/, '$1.$2');
                v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            } else {
                v = v.replace(/^(\d{2})(\d)/, '$1.$2');
                v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
                v = v.replace(/(\d{4})(\d)/, '$1-$2');
            }
            this.value = v;
            const digits = this.value.replace(/\D/g, '');
            if (digits.length > 11) { tipoPJ.checked = true; } else if (digits.length > 0) { tipoPF.checked = true; }
            togglePJ();
        });

        const cepInput = document.getElementById('cep');
        cepInput.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '');
            if (v.length > 5) v = v.substring(0, 5) + '-' + v.substring(5, 8);
            this.value = v;
        });
        cepInput.addEventListener('blur', function () {
            const cep = this.value.replace(/\D/g, '');
            if (cep.length === 8) {
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.erro) {
                            document.getElementById('logradouro').value = data.logradouro || '';
                            document.getElementById('bairro').value = data.bairro || '';
                            document.getElementById('cidade').value = data.localidade || '';
                            document.getElementById('uf').value = data.uf || '';
                            document.getElementById('numero').focus();
                        }
                    }).catch(() => {});
            }
        });
    });
</script>
@endpush
