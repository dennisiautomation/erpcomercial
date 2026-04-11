@extends('layouts.app')

@section('title', 'Editar Fornecedor')

@push('styles')
<style>
    /* ─── Wizard Progress ─────────────────────────────────── */
    .wizard-progress {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0;
        margin-bottom: 2.5rem;
        padding: 0 1rem;
    }
    .wizard-progress .step-item {
        display: flex;
        align-items: center;
    }
    .wizard-progress .step-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
        border: 2px solid var(--border);
        color: var(--text-muted);
        background: var(--bg-card);
        transition: all 0.3s ease;
        position: relative;
    }
    .wizard-progress .step-circle.active {
        border-color: var(--primary);
        background: var(--primary);
        color: #fff;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15);
    }
    .wizard-progress .step-circle.done {
        border-color: var(--success);
        background: var(--success);
        color: #fff;
    }
    .wizard-progress .step-label {
        display: none;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-muted);
        position: absolute;
        top: 48px;
        white-space: nowrap;
    }
    .wizard-progress .step-circle.active .step-label,
    .wizard-progress .step-circle.done .step-label {
        color: var(--text-secondary);
    }
    @media (min-width: 576px) {
        .wizard-progress .step-label { display: block; }
    }
    .wizard-progress .step-line {
        width: 60px;
        height: 2px;
        background: var(--border);
        margin: 0 0.5rem;
        transition: background 0.3s ease;
    }
    .wizard-progress .step-line.done {
        background: var(--success);
    }

    /* ─── Wizard Steps ────────────────────────────────────── */
    .wizard-step { display: none; }
    .wizard-step.active {
        display: block;
        animation: wizardFadeIn 0.35s ease;
    }
    @keyframes wizardFadeIn {
        from { opacity: 0; transform: translateY(12px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ─── Wizard Card Container ───────────────────────────── */
    .wizard-card {
        background: var(--bg-card);
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow);
        padding: 2rem;
        max-width: 720px;
        margin: 0 auto;
    }
    .wizard-card .step-title { font-size: 1.35rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.35rem; }
    .wizard-card .step-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.75rem; }
    .wizard-card .form-label { font-size: 0.82rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 4px; }
    .wizard-card .form-control:focus, .wizard-card .form-select:focus { box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12); border-color: var(--primary); }
    .required-dot::after { content: ' *'; color: var(--danger); font-weight: 700; }

    .wizard-nav {
        display: flex; justify-content: space-between; align-items: center;
        margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border);
    }
    .wizard-nav .btn { min-width: 140px; font-weight: 600; }
    .field-error { color: var(--danger); font-size: 0.8rem; margin-top: 4px; display: none; }
    .field-error.show { display: block; animation: wizardFadeIn 0.2s ease; }
</style>
@endpush

@section('content')
{{-- Header --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-pencil-square me-2"></i>Editar Fornecedor</h4>
        <p class="text-muted mb-0 small">{{ $fornecedore->razao_social }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('app.fornecedores.show', $fornecedore) }}" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="bi bi-eye me-1"></i> Visualizar
        </a>
        <a href="{{ route('app.fornecedores.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

{{-- Progress Indicator --}}
<div class="wizard-progress" id="wizardProgress">
    <div class="step-item">
        <div class="step-circle active" data-step="1">1<span class="step-label">Identificacao</span></div>
    </div>
    <div class="step-item"><div class="step-line" data-line="1"></div></div>
    <div class="step-item">
        <div class="step-circle" data-step="2">2<span class="step-label">Endereco</span></div>
    </div>
    <div class="step-item"><div class="step-line" data-line="2"></div></div>
    <div class="step-item">
        <div class="step-circle" data-step="3">3<span class="step-label">Contato</span></div>
    </div>
</div>

<form method="POST" action="{{ route('app.fornecedores.update', $fornecedore) }}" id="formFornecedor" novalidate>
    @csrf
    @method('PUT')

    <div class="wizard-card">

        {{-- STEP 1 — Identificacao --}}
        <div class="wizard-step active" data-step="1">
            <h5 class="step-title">Identificacao</h5>
            <p class="step-subtitle">Dados de identificacao do fornecedor</p>

            <div class="row g-3">
                <div class="col-md-5">
                    <label for="cpf_cnpj" class="form-label required-dot">CNPJ</label>
                    <div class="input-group">
                        <input type="text" name="cpf_cnpj" id="cpf_cnpj"
                               class="form-control @error('cpf_cnpj') is-invalid @enderror"
                               value="{{ old('cpf_cnpj', $fornecedore->cpf_cnpj) }}"
                               maxlength="18" data-mask="cnpj" data-cnpj-lookup required>
                        <span class="input-group-text bg-white" id="cnpjLoading" style="display:none;">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        </span>
                    </div>
                    @error('cpf_cnpj') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    <div class="field-error" id="errorCpfCnpj"></div>
                </div>

                <div class="col-md-7">
                    <label for="razao_social" class="form-label required-dot">Razao Social</label>
                    <input type="text" name="razao_social" id="razao_social"
                           class="form-control @error('razao_social') is-invalid @enderror"
                           value="{{ old('razao_social', $fornecedore->razao_social) }}" required>
                    @error('razao_social') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    <div class="field-error" id="errorRazaoSocial"></div>
                </div>

                <div class="col-md-12">
                    <label for="nome_fantasia" class="form-label">Nome Fantasia</label>
                    <input type="text" name="nome_fantasia" id="nome_fantasia"
                           class="form-control @error('nome_fantasia') is-invalid @enderror"
                           value="{{ old('nome_fantasia', $fornecedore->nome_fantasia) }}">
                    @error('nome_fantasia') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        {{-- STEP 2 — Endereco --}}
        <div class="wizard-step" data-step="2">
            <h5 class="step-title">Endereco</h5>
            <p class="step-subtitle">Digite o CEP para preencher automaticamente</p>

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="cep" class="form-label">CEP</label>
                    <div class="input-group">
                        <input type="text" name="cep" id="cep"
                               class="form-control @error('cep') is-invalid @enderror"
                               value="{{ old('cep', $fornecedore->cep) }}" maxlength="9" placeholder="00000-000" data-cep>
                        <span class="input-group-text bg-white" id="cepLoading" style="display:none;">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        </span>
                    </div>
                    @error('cep') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-8">
                    <label for="logradouro" class="form-label">Logradouro</label>
                    <input type="text" name="logradouro" id="logradouro" class="form-control @error('logradouro') is-invalid @enderror" value="{{ old('logradouro', $fornecedore->logradouro) }}">
                    @error('logradouro') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="numero" class="form-label">Numero</label>
                    <input type="text" name="numero" id="numero" class="form-control @error('numero') is-invalid @enderror" value="{{ old('numero', $fornecedore->numero) }}" placeholder="123">
                    @error('numero') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label for="complemento" class="form-label">Complemento</label>
                    <input type="text" name="complemento" id="complemento" class="form-control @error('complemento') is-invalid @enderror" value="{{ old('complemento', $fornecedore->complemento) }}" placeholder="Sala, Bloco, etc.">
                    @error('complemento') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-5">
                    <label for="bairro" class="form-label">Bairro</label>
                    <input type="text" name="bairro" id="bairro" class="form-control @error('bairro') is-invalid @enderror" value="{{ old('bairro', $fornecedore->bairro) }}">
                    @error('bairro') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-7">
                    <label for="cidade" class="form-label">Cidade</label>
                    <input type="text" name="cidade" id="cidade" class="form-control @error('cidade') is-invalid @enderror" value="{{ old('cidade', $fornecedore->cidade) }}">
                    @error('cidade') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-5">
                    <label for="uf" class="form-label">UF</label>
                    <select name="uf" id="uf" class="form-select @error('uf') is-invalid @enderror">
                        <option value="">Selecione</option>
                        @foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $sigla)
                            <option value="{{ $sigla }}" {{ old('uf', $fornecedore->uf) === $sigla ? 'selected' : '' }}>{{ $sigla }}</option>
                        @endforeach
                    </select>
                    @error('uf') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        {{-- STEP 3 — Contato e Condicoes --}}
        <div class="wizard-step" data-step="3">
            <h5 class="step-title">Contato e Condicoes</h5>
            <p class="step-subtitle">Informacoes de contato e condicoes comerciais</p>

            <div class="row g-3">
                <div class="col-md-12">
                    <label for="contato_representante" class="form-label">Contato / Representante</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-person text-muted"></i></span>
                        <input type="text" name="contato_representante" id="contato_representante"
                               class="form-control @error('contato_representante') is-invalid @enderror"
                               value="{{ old('contato_representante', $fornecedore->contato_representante) }}">
                    </div>
                    @error('contato_representante') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-5">
                    <label for="telefone" class="form-label">Telefone</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-telephone text-muted"></i></span>
                        <input type="text" name="telefone" id="telefone"
                               class="form-control @error('telefone') is-invalid @enderror"
                               value="{{ old('telefone', $fornecedore->telefone) }}" maxlength="15" placeholder="(00) 00000-0000" data-mask="telefone">
                    </div>
                    @error('telefone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-7">
                    <label for="email" class="form-label">E-mail</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-envelope text-muted"></i></span>
                        <input type="email" name="email" id="email"
                               class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email', $fornecedore->email) }}" placeholder="email@exemplo.com">
                    </div>
                    @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label for="condicoes_comerciais" class="form-label">Condicoes Comerciais</label>
                    <textarea name="condicoes_comerciais" id="condicoes_comerciais"
                              class="form-control @error('condicoes_comerciais') is-invalid @enderror"
                              rows="3" placeholder="30/60/90 dias boleto">{{ old('condicoes_comerciais', $fornecedore->condicoes_comerciais) }}</textarea>
                    @error('condicoes_comerciais') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        {{-- Navigation Bar --}}
        <div class="wizard-nav">
            <button type="button" class="btn btn-outline-secondary rounded-pill" id="btnVoltar" onclick="goToStep(currentStep - 1)" style="display:none;">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </button>
            <div id="navSpacer"></div>
            <button type="button" class="btn btn-primary rounded-pill" id="btnAvancar" onclick="goToStep(currentStep + 1)">
                Avancar <i class="bi bi-arrow-right ms-1"></i>
            </button>
            <button type="submit" class="btn btn-success rounded-pill" id="btnSalvar" style="display:none;">
                <i class="bi bi-check-lg me-1"></i> Atualizar Fornecedor
            </button>
        </div>

    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    let currentStep = 1;
    const totalSteps = 3;

    @if($errors->any())
        @if($errors->has('cpf_cnpj') || $errors->has('razao_social') || $errors->has('nome_fantasia'))
            goToStep(1, true);
        @elseif($errors->has('cep') || $errors->has('logradouro') || $errors->has('numero') || $errors->has('bairro') || $errors->has('cidade') || $errors->has('uf'))
            goToStep(2, true);
        @else
            goToStep(3, true);
        @endif
    @endif

    window.currentStep = currentStep;

    window.goToStep = function(n, skipValidation) {
        if (n < 1 || n > totalSteps) return;
        if (!skipValidation && n > currentStep && !validateStep(currentStep)) return;

        document.querySelectorAll('.wizard-step').forEach(el => el.classList.remove('active'));
        document.querySelector('.wizard-step[data-step="' + n + '"]').classList.add('active');

        document.querySelectorAll('.wizard-progress .step-circle').forEach(el => {
            const s = parseInt(el.dataset.step);
            el.classList.remove('active', 'done');
            if (s === n) el.classList.add('active');
            else if (s < n) el.classList.add('done');
        });

        document.querySelectorAll('.wizard-progress .step-line').forEach(el => {
            const l = parseInt(el.dataset.line);
            el.classList.toggle('done', l < n);
        });

        document.getElementById('btnVoltar').style.display = (n === 1) ? 'none' : '';
        document.getElementById('navSpacer').style.display = (n === 1) ? '' : 'none';
        document.getElementById('btnAvancar').style.display = (n === totalSteps) ? 'none' : '';
        document.getElementById('btnSalvar').style.display = (n === totalSteps) ? '' : 'none';

        currentStep = n;
        window.currentStep = n;

        const firstInput = document.querySelector('.wizard-step[data-step="' + n + '"] input:not([type="hidden"])');
        if (firstInput) setTimeout(() => firstInput.focus(), 100);
    };

    function validateStep(step) {
        clearErrors();
        if (step === 1) {
            let valid = true;
            const cpfCnpj = document.getElementById('cpf_cnpj').value.replace(/\D/g, '');
            const razao = document.getElementById('razao_social').value.trim();
            if (cpfCnpj.length < 11) { showError('errorCpfCnpj', 'Informe um CPF ou CNPJ valido'); valid = false; }
            if (!razao) { showError('errorRazaoSocial', 'Informe a Razao Social'); valid = false; }
            return valid;
        }
        return true;
    }

    function showError(id, msg) { const el = document.getElementById(id); if (el) { el.textContent = msg; el.classList.add('show'); } }
    function clearErrors() { document.querySelectorAll('.field-error').forEach(el => el.classList.remove('show')); }

    // ─── CPF/CNPJ Mask ──────────────────────────────────────
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

    // ─── CNPJ Auto-Lookup ───────────────────────────────────
    cpfCnpjInput.addEventListener('blur', function () {
        const cnpj = this.value.replace(/\D/g, '');
        if (cnpj.length !== 14) return;
        const loading = document.getElementById('cnpjLoading');
        loading.style.display = '';
        fetch('https://receitaws.com.br/v1/cnpj/' + cnpj, { mode: 'cors' })
            .then(r => r.json())
            .then(data => {
                if (data.status !== 'ERROR') {
                    if (data.nome) document.getElementById('razao_social').value = data.nome;
                    if (data.fantasia) document.getElementById('nome_fantasia').value = data.fantasia;
                    if (data.cep) {
                        document.getElementById('cep').value = data.cep.replace(/\D/g, '').replace(/(\d{5})(\d{3})/, '$1-$2');
                        document.getElementById('logradouro').value = data.logradouro || '';
                        document.getElementById('numero').value = data.numero || '';
                        document.getElementById('complemento').value = data.complemento || '';
                        document.getElementById('bairro').value = data.bairro || '';
                        document.getElementById('cidade').value = data.municipio || '';
                        document.getElementById('uf').value = data.uf || '';
                    }
                }
            })
            .catch(() => {})
            .finally(() => { loading.style.display = 'none'; });
    });

    // ─── CEP Mask + ViaCEP ───────────────────────────────────
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
        fetch('https://viacep.com.br/ws/' + cep + '/json/')
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

    // ─── Telefone Mask ───────────────────────────────────────
    const telefoneInput = document.getElementById('telefone');
    telefoneInput.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '');
        if (v.length > 11) v = v.substring(0, 11);
        if (v.length > 10) {
            v = v.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
        } else if (v.length > 6) {
            v = v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
        } else if (v.length > 2) {
            v = v.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
        }
        this.value = v;
    });
});
</script>
@endpush
