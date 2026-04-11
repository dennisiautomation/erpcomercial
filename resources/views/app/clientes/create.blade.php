@extends('layouts.app')

@section('title', 'Novo Cliente')

@push('styles')
<style>
    .section-card {
        border: none;
        border-radius: 12px;
        overflow: hidden;
    }
    .section-card .card-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 14px 20px;
    }
    .section-card .card-header h6 {
        font-size: 0.9rem;
        font-weight: 600;
        color: #334155;
    }
    .section-card .card-body {
        padding: 20px;
    }
    .tipo-toggle {
        display: flex;
        gap: 0;
        border-radius: 10px;
        overflow: hidden;
        border: 2px solid #e2e8f0;
        background: #f8fafc;
    }
    .tipo-toggle .tipo-option {
        flex: 1;
        text-align: center;
        padding: 10px 16px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s;
        border: none;
        background: transparent;
        color: #64748b;
    }
    .tipo-toggle .tipo-option:first-child {
        border-right: 1px solid #e2e8f0;
    }
    .tipo-toggle .tipo-option.active {
        background: #2563eb;
        color: #fff;
    }
    .tipo-toggle .tipo-option:hover:not(.active) {
        background: #e2e8f0;
    }
    .form-label {
        font-size: 0.82rem;
        font-weight: 500;
        color: #475569;
        margin-bottom: 4px;
    }
    .form-control:focus, .form-select:focus {
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        border-color: #3b82f6;
    }
    .cep-spinner {
        display: none;
        width: 16px;
        height: 16px;
    }
    .required-dot::after {
        content: '*';
        color: #ef4444;
        margin-left: 2px;
    }
</style>
@endpush

@section('content')
{{-- Header --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-person-plus me-2"></i>Novo Cliente</h4>
        <p class="text-muted mb-0 small">Preencha os dados para cadastrar um novo cliente</p>
    </div>
    <a href="{{ route('app.clientes.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<form method="POST" action="{{ route('app.clientes.store') }}" id="formCliente" novalidate>
    @csrf

    {{-- Identificacao --}}
    <div class="card section-card shadow-sm mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-person-badge me-2 text-primary"></i>Identificacao</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                {{-- Tipo Pessoa Toggle --}}
                <div class="col-md-3">
                    <label class="form-label required-dot">Tipo de Pessoa</label>
                    <div class="tipo-toggle">
                        <div class="tipo-option {{ old('tipo_pessoa', 'pf') === 'pf' ? 'active' : '' }}" data-value="pf" id="btnPF">
                            <i class="bi bi-person me-1"></i> Pessoa Fisica
                        </div>
                        <div class="tipo-option {{ old('tipo_pessoa') === 'pj' ? 'active' : '' }}" data-value="pj" id="btnPJ">
                            <i class="bi bi-building me-1"></i> Pessoa Juridica
                        </div>
                    </div>
                    <input type="hidden" name="tipo_pessoa" id="tipo_pessoa" value="{{ old('tipo_pessoa', 'pf') }}">
                    @error('tipo_pessoa')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label for="cpf_cnpj" class="form-label required-dot" id="labelCpfCnpj">CPF</label>
                    <input type="text" name="cpf_cnpj" id="cpf_cnpj" class="form-control @error('cpf_cnpj') is-invalid @enderror" value="{{ old('cpf_cnpj') }}" placeholder="000.000.000-00" required>
                    @error('cpf_cnpj')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="nome_razao_social" class="form-label required-dot" id="labelNome">Nome Completo</label>
                    <input type="text" name="nome_razao_social" id="nome_razao_social" class="form-control @error('nome_razao_social') is-invalid @enderror" value="{{ old('nome_razao_social') }}" required>
                    @error('nome_razao_social')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6 campos-pj" style="display: none;">
                    <label for="nome_fantasia" class="form-label">Nome Fantasia</label>
                    <input type="text" name="nome_fantasia" id="nome_fantasia" class="form-control @error('nome_fantasia') is-invalid @enderror" value="{{ old('nome_fantasia') }}">
                    @error('nome_fantasia')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3 campos-pj" style="display: none;">
                    <label for="ie" class="form-label">Inscricao Estadual</label>
                    <input type="text" name="ie" id="ie" class="form-control @error('ie') is-invalid @enderror" value="{{ old('ie') }}" placeholder="Isento ou numero">
                    @error('ie')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Endereco --}}
    <div class="card section-card shadow-sm mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-geo-alt me-2 text-danger"></i>Endereco</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <label for="cep" class="form-label">CEP</label>
                    <div class="input-group">
                        <input type="text" name="cep" id="cep" class="form-control @error('cep') is-invalid @enderror" value="{{ old('cep') }}" maxlength="9" placeholder="00000-000">
                        <span class="input-group-text bg-white" id="cepLoading" style="display:none;">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Buscando...</span>
                            </div>
                        </span>
                    </div>
                    @error('cep')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-5">
                    <label for="logradouro" class="form-label">Logradouro</label>
                    <input type="text" name="logradouro" id="logradouro" class="form-control @error('logradouro') is-invalid @enderror" value="{{ old('logradouro') }}">
                    @error('logradouro')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label for="numero" class="form-label">Numero</label>
                    <input type="text" name="numero" id="numero" class="form-control @error('numero') is-invalid @enderror" value="{{ old('numero') }}">
                    @error('numero')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="complemento" class="form-label">Complemento</label>
                    <input type="text" name="complemento" id="complemento" class="form-control @error('complemento') is-invalid @enderror" value="{{ old('complemento') }}" placeholder="Apto, Sala, etc.">
                    @error('complemento')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="bairro" class="form-label">Bairro</label>
                    <input type="text" name="bairro" id="bairro" class="form-control @error('bairro') is-invalid @enderror" value="{{ old('bairro') }}">
                    @error('bairro')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-5">
                    <label for="cidade" class="form-label">Cidade</label>
                    <input type="text" name="cidade" id="cidade" class="form-control @error('cidade') is-invalid @enderror" value="{{ old('cidade') }}">
                    @error('cidade')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="uf" class="form-label">UF</label>
                    <select name="uf" id="uf" class="form-select @error('uf') is-invalid @enderror">
                        <option value="">Selecione</option>
                        @foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $sigla)
                            <option value="{{ $sigla }}" {{ old('uf') === $sigla ? 'selected' : '' }}>{{ $sigla }}</option>
                        @endforeach
                    </select>
                    @error('uf')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Contato --}}
    <div class="card section-card shadow-sm mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-telephone me-2 text-success"></i>Contato</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="telefone" class="form-label">Telefone</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-telephone text-muted"></i></span>
                        <input type="text" name="telefone" id="telefone" class="form-control @error('telefone') is-invalid @enderror" value="{{ old('telefone') }}" placeholder="(00) 0000-0000">
                    </div>
                    @error('telefone')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="whatsapp" class="form-label">WhatsApp</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-whatsapp text-success"></i></span>
                        <input type="text" name="whatsapp" id="whatsapp" class="form-control @error('whatsapp') is-invalid @enderror" value="{{ old('whatsapp') }}" placeholder="(00) 00000-0000">
                    </div>
                    @error('whatsapp')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">E-mail</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-envelope text-muted"></i></span>
                        <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="exemplo@email.com">
                    </div>
                    @error('email')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Financeiro / Obs --}}
    <div class="card section-card shadow-sm mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-wallet2 me-2 text-warning"></i>Financeiro</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="limite_credito" class="form-label">Limite de Credito</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white fw-semibold">R$</span>
                        <input type="number" name="limite_credito" id="limite_credito" class="form-control @error('limite_credito') is-invalid @enderror" value="{{ old('limite_credito', '0.00') }}" step="0.01" min="0">
                    </div>
                    @error('limite_credito')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-9">
                    <label for="observacoes" class="form-label">Observacoes</label>
                    <textarea name="observacoes" id="observacoes" class="form-control @error('observacoes') is-invalid @enderror" rows="2" placeholder="Informacoes adicionais sobre o cliente...">{{ old('observacoes') }}</textarea>
                    @error('observacoes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="{{ route('app.clientes.index') }}" class="btn btn-outline-secondary rounded-pill px-4">Cancelar</a>
        <button type="submit" class="btn btn-primary rounded-pill px-4">
            <i class="bi bi-check-lg me-1"></i> Salvar Cliente
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btnPF = document.getElementById('btnPF');
    const btnPJ = document.getElementById('btnPJ');
    const tipoPessoaInput = document.getElementById('tipo_pessoa');
    const camposPJ = document.querySelectorAll('.campos-pj');
    const labelCpfCnpj = document.getElementById('labelCpfCnpj');
    const labelNome = document.getElementById('labelNome');
    const cpfCnpjInput = document.getElementById('cpf_cnpj');

    function setTipoPessoa(tipo) {
        tipoPessoaInput.value = tipo;
        const isPJ = tipo === 'pj';

        btnPF.classList.toggle('active', !isPJ);
        btnPJ.classList.toggle('active', isPJ);

        camposPJ.forEach(el => {
            el.style.display = isPJ ? '' : 'none';
            // Clear PJ fields when switching to PF
            if (!isPJ) {
                el.querySelectorAll('input').forEach(inp => { if (!inp.value) inp.value = ''; });
            }
        });

        labelCpfCnpj.innerHTML = isPJ ? 'CNPJ<span class="text-danger ms-1">*</span>' : 'CPF<span class="text-danger ms-1">*</span>';
        labelNome.innerHTML = isPJ ? 'Razao Social<span class="text-danger ms-1">*</span>' : 'Nome Completo<span class="text-danger ms-1">*</span>';
        cpfCnpjInput.placeholder = isPJ ? '00.000.000/0000-00' : '000.000.000-00';
        cpfCnpjInput.maxLength = isPJ ? 18 : 14;
    }

    btnPF.addEventListener('click', () => setTipoPessoa('pf'));
    btnPJ.addEventListener('click', () => setTipoPessoa('pj'));
    setTipoPessoa(tipoPessoaInput.value);

    // CPF/CNPJ mask with auto-detect
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

        // Auto-detect PF/PJ
        const digits = this.value.replace(/\D/g, '');
        if (digits.length > 11) {
            setTipoPessoa('pj');
        } else if (digits.length > 0 && digits.length <= 11) {
            setTipoPessoa('pf');
        }
    });

    // CEP mask + ViaCEP
    const cepInput = document.getElementById('cep');
    const cepLoading = document.getElementById('cepLoading');

    cepInput.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '');
        if (v.length > 5) v = v.substring(0, 5) + '-' + v.substring(5, 8);
        this.value = v;
    });

    cepInput.addEventListener('blur', function () {
        const cep = this.value.replace(/\D/g, '');
        if (cep.length !== 8) return;

        cepLoading.style.display = '';
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
            })
            .catch(() => {})
            .finally(() => { cepLoading.style.display = 'none'; });
    });

    // Phone masks
    function phoneMask(input, maxDigits) {
        if (!input) return;
        input.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '');
            if (v.length > maxDigits) v = v.substring(0, maxDigits);
            if (v.length > 10) {
                v = v.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
            } else if (v.length > 6) {
                v = v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
            } else if (v.length > 2) {
                v = v.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
            }
            this.value = v;
        });
    }
    phoneMask(document.getElementById('telefone'), 11);
    phoneMask(document.getElementById('whatsapp'), 11);
});
</script>
@endpush
