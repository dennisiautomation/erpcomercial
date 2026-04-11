@extends('layouts.app')

@section('title', 'Editar Cliente')

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
        cursor: pointer;
    }
    .wizard-progress .step-circle:hover {
        border-color: var(--primary);
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
    .wizard-step {
        display: none;
    }
    .wizard-step.active {
        display: block;
        animation: wizardFadeIn 0.35s ease;
    }
    @keyframes wizardFadeIn {
        from { opacity: 0; transform: translateY(12px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ─── Type Selection Cards ────────────────────────────── */
    .wizard-option {
        border: 2px solid var(--border);
        border-radius: var(--radius-xl);
        cursor: pointer;
        transition: all 0.25s ease;
        background: var(--bg-card);
        position: relative;
        overflow: hidden;
    }
    .wizard-option:hover {
        border-color: var(--primary);
        transform: translateY(-4px);
        box-shadow: var(--shadow-md);
    }
    .wizard-option.selected {
        border-color: var(--primary);
        background: var(--primary-light);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
    }
    .wizard-option.selected::after {
        content: '\F26A';
        font-family: 'bootstrap-icons';
        position: absolute;
        top: 12px;
        right: 14px;
        font-size: 1.25rem;
        color: var(--primary);
    }
    .wizard-option h4 {
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }
    .wizard-option p {
        font-size: 0.9rem;
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
    .wizard-card .step-title {
        font-size: 1.35rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.35rem;
    }
    .wizard-card .step-subtitle {
        font-size: 0.9rem;
        color: var(--text-muted);
        margin-bottom: 1.75rem;
    }

    /* ─── Form Styles ─────────────────────────────────────── */
    .wizard-card .form-label {
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--text-secondary);
        margin-bottom: 4px;
    }
    .wizard-card .form-control:focus,
    .wizard-card .form-select:focus {
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
        border-color: var(--primary);
    }
    .required-dot::after {
        content: ' *';
        color: var(--danger);
        font-weight: 700;
    }

    /* ─── Bottom Navigation ───────────────────────────────── */
    .wizard-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border);
    }
    .wizard-nav .btn {
        min-width: 140px;
        font-weight: 600;
    }

    /* ─── Status Select ───────────────────────────────────── */
    .status-select-ativo { border-left: 4px solid var(--success); }
    .status-select-inativo { border-left: 4px solid var(--text-muted); }
    .status-select-bloqueado { border-left: 4px solid var(--danger); }

    /* ─── Validation feedback ─────────────────────────────── */
    .field-error {
        color: var(--danger);
        font-size: 0.8rem;
        margin-top: 4px;
        display: none;
    }
    .field-error.show {
        display: block;
        animation: wizardFadeIn 0.2s ease;
    }
</style>
@endpush

@section('content')
{{-- Header --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-pencil-square me-2"></i>Editar Cliente</h4>
        <p class="text-muted mb-0 small">{{ $cliente->nome_razao_social }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('app.clientes.show', $cliente) }}" class="btn btn-outline-info rounded-pill px-3">
            <i class="bi bi-eye me-1"></i> Visualizar
        </a>
        <a href="{{ route('app.clientes.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

{{-- Progress Indicator --}}
<div class="wizard-progress" id="wizardProgress">
    <div class="step-item">
        <div class="step-circle active" data-step="1" onclick="goToStep(1, true)">
            1
            <span class="step-label">Tipo</span>
        </div>
    </div>
    <div class="step-item"><div class="step-line done" data-line="1"></div></div>
    <div class="step-item">
        <div class="step-circle" data-step="2" onclick="goToStep(2, true)">
            2
            <span class="step-label">Dados</span>
        </div>
    </div>
    <div class="step-item"><div class="step-line" data-line="2"></div></div>
    <div class="step-item">
        <div class="step-circle" data-step="3" onclick="goToStep(3, true)">
            3
            <span class="step-label">Endereco</span>
        </div>
    </div>
    <div class="step-item"><div class="step-line" data-line="3"></div></div>
    <div class="step-item">
        <div class="step-circle" data-step="4" onclick="goToStep(4, true)">
            4
            <span class="step-label">Contato</span>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('app.clientes.update', $cliente) }}" id="formCliente" novalidate>
    @csrf
    @method('PUT')
    <input type="hidden" name="tipo_pessoa" id="tipo_pessoa" value="{{ old('tipo_pessoa', $cliente->tipo_pessoa) }}">

    <div class="wizard-card">

        {{-- ═══════════════════════════════════════════════════
             STEP 1 — Tipo de cliente
             ═══════════════════════════════════════════════════ --}}
        <div class="wizard-step active" data-step="1">
            <h5 class="step-title">Tipo de cliente</h5>
            <p class="step-subtitle">Altere o tipo de pessoa se necessario</p>

            <div class="row g-4 justify-content-center">
                <div class="col-md-6">
                    <div class="wizard-option {{ old('tipo_pessoa', $cliente->tipo_pessoa) === 'pf' ? 'selected' : '' }}" data-type="pf" onclick="selectType('pf')">
                        <div class="text-center p-4 p-md-5">
                            <i class="bi bi-person" style="font-size:3rem;color:var(--primary)"></i>
                            <h4 class="mt-3">Pessoa Fisica</h4>
                            <p class="text-muted mb-0">Cliente individual com CPF</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="wizard-option {{ old('tipo_pessoa', $cliente->tipo_pessoa) === 'pj' ? 'selected' : '' }}" data-type="pj" onclick="selectType('pj')">
                        <div class="text-center p-4 p-md-5">
                            <i class="bi bi-building" style="font-size:3rem;color:var(--primary)"></i>
                            <h4 class="mt-3">Pessoa Juridica</h4>
                            <p class="text-muted mb-0">Empresa com CNPJ</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="field-error text-center mt-3" id="errorStep1">Selecione o tipo de pessoa para continuar</div>

            @error('tipo_pessoa')
                <div class="text-danger small text-center mt-2">{{ $message }}</div>
            @enderror
        </div>

        {{-- ═══════════════════════════════════════════════════
             STEP 2 — Dados do cliente
             ═══════════════════════════════════════════════════ --}}
        <div class="wizard-step" data-step="2">
            <h5 class="step-title">Dados do cliente</h5>
            <p class="step-subtitle" id="step2Subtitle">Informe os dados de identificacao</p>

            <div class="row g-3">
                <div class="col-md-5">
                    <label for="cpf_cnpj" class="form-label required-dot" id="labelCpfCnpj">CPF</label>
                    <div class="input-group">
                        <input type="text" name="cpf_cnpj" id="cpf_cnpj"
                               class="form-control @error('cpf_cnpj') is-invalid @enderror"
                               value="{{ old('cpf_cnpj', $cliente->cpf_cnpj) }}"
                               maxlength="18" required>
                        <span class="input-group-text bg-white" id="cnpjLoading" style="display:none;">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        </span>
                    </div>
                    @error('cpf_cnpj')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                    <div class="field-error" id="errorCpfCnpj"></div>
                </div>

                <div class="col-md-7">
                    <label for="nome_razao_social" class="form-label required-dot" id="labelNome">Nome Completo</label>
                    <input type="text" name="nome_razao_social" id="nome_razao_social"
                           class="form-control @error('nome_razao_social') is-invalid @enderror"
                           value="{{ old('nome_razao_social', $cliente->nome_razao_social) }}" required>
                    @error('nome_razao_social')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                    <div class="field-error" id="errorNome"></div>
                </div>

                <div class="col-md-6 campos-pj" style="display:none;">
                    <label for="nome_fantasia" class="form-label">Nome Fantasia</label>
                    <input type="text" name="nome_fantasia" id="nome_fantasia"
                           class="form-control @error('nome_fantasia') is-invalid @enderror"
                           value="{{ old('nome_fantasia', $cliente->nome_fantasia) }}">
                    @error('nome_fantasia')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6 campos-pj" style="display:none;">
                    <label for="ie" class="form-label">Inscricao Estadual</label>
                    <input type="text" name="ie" id="ie"
                           class="form-control @error('ie') is-invalid @enderror"
                           value="{{ old('ie', $cliente->ie) }}"
                           placeholder="Isento ou numero">
                    @error('ie')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════
             STEP 3 — Endereco
             ═══════════════════════════════════════════════════ --}}
        <div class="wizard-step" data-step="3">
            <h5 class="step-title">Endereco</h5>
            <p class="step-subtitle">Digite o CEP para preencher automaticamente</p>

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="cep" class="form-label">CEP</label>
                    <div class="input-group">
                        <input type="text" name="cep" id="cep"
                               class="form-control @error('cep') is-invalid @enderror"
                               value="{{ old('cep', $cliente->cep) }}" maxlength="9" placeholder="00000-000">
                        <span class="input-group-text bg-white" id="cepLoading" style="display:none;">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        </span>
                    </div>
                    @error('cep')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-8">
                    <label for="logradouro" class="form-label">Logradouro</label>
                    <input type="text" name="logradouro" id="logradouro"
                           class="form-control @error('logradouro') is-invalid @enderror"
                           value="{{ old('logradouro', $cliente->logradouro) }}">
                    @error('logradouro')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label for="numero" class="form-label">Numero</label>
                    <input type="text" name="numero" id="numero"
                           class="form-control @error('numero') is-invalid @enderror"
                           value="{{ old('numero', $cliente->numero) }}">
                    @error('numero')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label for="complemento" class="form-label">Complemento</label>
                    <input type="text" name="complemento" id="complemento"
                           class="form-control @error('complemento') is-invalid @enderror"
                           value="{{ old('complemento', $cliente->complemento) }}" placeholder="Apto, Sala, etc.">
                    @error('complemento')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-5">
                    <label for="bairro" class="form-label">Bairro</label>
                    <input type="text" name="bairro" id="bairro"
                           class="form-control @error('bairro') is-invalid @enderror"
                           value="{{ old('bairro', $cliente->bairro) }}">
                    @error('bairro')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-7">
                    <label for="cidade" class="form-label">Cidade</label>
                    <input type="text" name="cidade" id="cidade"
                           class="form-control @error('cidade') is-invalid @enderror"
                           value="{{ old('cidade', $cliente->cidade) }}">
                    @error('cidade')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-5">
                    <label for="uf" class="form-label">UF</label>
                    <select name="uf" id="uf" class="form-select @error('uf') is-invalid @enderror">
                        <option value="">Selecione</option>
                        @foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $sigla)
                            <option value="{{ $sigla }}" {{ old('uf', $cliente->uf) === $sigla ? 'selected' : '' }}>{{ $sigla }}</option>
                        @endforeach
                    </select>
                    @error('uf')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════
             STEP 4 — Contato e Financeiro
             ═══════════════════════════════════════════════════ --}}
        <div class="wizard-step" data-step="4">
            <h5 class="step-title">Contato e Financeiro</h5>
            <p class="step-subtitle">Dados de contato e configuracoes do cliente</p>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="telefone" class="form-label">Telefone</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-telephone text-muted"></i></span>
                        <input type="text" name="telefone" id="telefone"
                               class="form-control @error('telefone') is-invalid @enderror"
                               value="{{ old('telefone', $cliente->telefone) }}" placeholder="(00) 0000-0000">
                    </div>
                    @error('telefone')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="whatsapp" class="form-label">
                        WhatsApp <span class="text-muted fw-normal">(opcional)</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-whatsapp text-success"></i></span>
                        <input type="text" name="whatsapp" id="whatsapp"
                               class="form-control @error('whatsapp') is-invalid @enderror"
                               value="{{ old('whatsapp', $cliente->whatsapp) }}" placeholder="(00) 00000-0000">
                    </div>
                    @error('whatsapp')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label">
                        E-mail <span class="text-muted fw-normal">(opcional)</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-envelope text-muted"></i></span>
                        <input type="email" name="email" id="email"
                               class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email', $cliente->email) }}" placeholder="exemplo@email.com">
                    </div>
                    @error('email')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="limite_credito" class="form-label">
                        Limite de Credito <span class="text-muted fw-normal">(opcional)</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-white fw-semibold">R$</span>
                        <input type="number" name="limite_credito" id="limite_credito"
                               class="form-control @error('limite_credito') is-invalid @enderror"
                               value="{{ old('limite_credito', $cliente->limite_credito) }}" step="0.01" min="0">
                    </div>
                    @error('limite_credito')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="status" class="form-label required-dot">Status</label>
                    @php
                        $currentStatus = old('status', $cliente->status);
                        $statusClass = 'status-select-' . $currentStatus;
                    @endphp
                    <select name="status" id="status" class="form-select {{ $statusClass }} @error('status') is-invalid @enderror" required>
                        <option value="ativo" {{ $currentStatus === 'ativo' ? 'selected' : '' }}>Ativo</option>
                        <option value="inativo" {{ $currentStatus === 'inativo' ? 'selected' : '' }}>Inativo</option>
                        <option value="bloqueado" {{ $currentStatus === 'bloqueado' ? 'selected' : '' }}>Bloqueado</option>
                    </select>
                    @error('status')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    {{-- spacer for alignment --}}
                </div>

                <div class="col-12">
                    <label for="observacoes" class="form-label">
                        Observacoes <span class="text-muted fw-normal">(opcional)</span>
                    </label>
                    <textarea name="observacoes" id="observacoes"
                              class="form-control @error('observacoes') is-invalid @enderror"
                              rows="2" placeholder="Informacoes adicionais...">{{ old('observacoes', $cliente->observacoes) }}</textarea>
                    @error('observacoes')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════
             Navigation Bar
             ═══════════════════════════════════════════════════ --}}
        <div class="wizard-nav">
            <div>
                <button type="button" class="btn btn-outline-secondary rounded-pill" id="btnVoltar" onclick="goToStep(currentStep - 1)" style="display:none;">
                    <i class="bi bi-arrow-left me-1"></i> Voltar
                </button>
            </div>
            <div id="navSpacer"></div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary rounded-pill" id="btnAvancar" onclick="goToStep(currentStep + 1)">
                    Avancar <i class="bi bi-arrow-right ms-1"></i>
                </button>
                <button type="submit" class="btn btn-success rounded-pill" id="btnSalvar" style="display:none;">
                    <i class="bi bi-check-lg me-1"></i> Atualizar Cliente
                </button>
            </div>
        </div>

        {{-- Delete button below wizard --}}
        <div class="text-center mt-4 pt-3" style="border-top: 1px solid var(--border);">
            <form method="POST" action="{{ route('app.clientes.destroy', $cliente) }}" class="d-inline"
                  onsubmit="return confirm('Tem certeza que deseja excluir este cliente? Esta acao nao pode ser desfeita.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-link text-danger btn-sm">
                    <i class="bi bi-trash me-1"></i> Excluir este cliente
                </button>
            </form>
        </div>

    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    let currentStep = 1;
    const totalSteps = 4;
    let selectedType = document.getElementById('tipo_pessoa').value || '';

    // Pre-fill: update PJ fields on load
    updatePJFields();

    // ─── Restore step if validation errors came back ─────────
    @if($errors->any())
        @if($errors->has('tipo_pessoa'))
            goToStep(1, true);
        @elseif($errors->has('cpf_cnpj') || $errors->has('nome_razao_social') || $errors->has('nome_fantasia') || $errors->has('ie'))
            goToStep(2, true);
        @elseif($errors->has('cep') || $errors->has('logradouro') || $errors->has('numero') || $errors->has('bairro') || $errors->has('cidade') || $errors->has('uf'))
            goToStep(3, true);
        @else
            goToStep(4, true);
        @endif
    @endif

    // ─── Select Type (Step 1) ────────────────────────────────
    window.selectType = function(tipo) {
        selectedType = tipo;
        document.getElementById('tipo_pessoa').value = tipo;

        document.querySelectorAll('.wizard-option').forEach(el => el.classList.remove('selected'));
        document.querySelector('.wizard-option[data-type="' + tipo + '"]').classList.add('selected');

        document.getElementById('errorStep1').classList.remove('show');

        updatePJFields();

        setTimeout(() => goToStep(2), 350);
    };

    function updatePJFields() {
        const isPJ = selectedType === 'pj';
        document.querySelectorAll('.campos-pj').forEach(el => {
            el.style.display = isPJ ? '' : 'none';
        });
        document.getElementById('labelCpfCnpj').innerHTML = isPJ
            ? 'CNPJ <span class="text-danger fw-bold">*</span>'
            : 'CPF <span class="text-danger fw-bold">*</span>';
        document.getElementById('labelNome').innerHTML = isPJ
            ? 'Razao Social <span class="text-danger fw-bold">*</span>'
            : 'Nome Completo <span class="text-danger fw-bold">*</span>';
        document.getElementById('cpf_cnpj').placeholder = isPJ ? '00.000.000/0000-00' : '000.000.000-00';
        document.getElementById('cpf_cnpj').maxLength = isPJ ? 18 : 14;
        document.getElementById('step2Subtitle').textContent = isPJ
            ? 'Informe o CNPJ para buscar automaticamente'
            : 'Informe o CPF e o nome do cliente';
    }

    // ─── Step Navigation ─────────────────────────────────────
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

    // ─── Step Validation ─────────────────────────────────────
    function validateStep(step) {
        clearErrors();

        if (step === 1) {
            if (!selectedType) {
                document.getElementById('errorStep1').classList.add('show');
                return false;
            }
            return true;
        }

        if (step === 2) {
            let valid = true;
            const cpfCnpj = document.getElementById('cpf_cnpj').value.replace(/\D/g, '');
            const nome = document.getElementById('nome_razao_social').value.trim();

            if (selectedType === 'pf' && cpfCnpj.length !== 11) {
                showError('errorCpfCnpj', 'Informe um CPF valido com 11 digitos');
                valid = false;
            }
            if (selectedType === 'pj' && cpfCnpj.length !== 14) {
                showError('errorCpfCnpj', 'Informe um CNPJ valido com 14 digitos');
                valid = false;
            }
            if (!nome) {
                showError('errorNome', selectedType === 'pj' ? 'Informe a Razao Social' : 'Informe o Nome Completo');
                valid = false;
            }
            return valid;
        }

        return true;
    }

    function showError(id, msg) {
        const el = document.getElementById(id);
        if (el) { el.textContent = msg; el.classList.add('show'); }
    }

    function clearErrors() {
        document.querySelectorAll('.field-error').forEach(el => el.classList.remove('show'));
    }

    // ─── CPF/CNPJ Mask ──────────────────────────────────────
    const cpfCnpjInput = document.getElementById('cpf_cnpj');
    cpfCnpjInput.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '');
        if (selectedType === 'pj') {
            v = v.substring(0, 14);
            v = v.replace(/^(\d{2})(\d)/, '$1.$2');
            v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
            v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
            v = v.replace(/(\d{4})(\d)/, '$1-$2');
        } else {
            v = v.substring(0, 11);
            v = v.replace(/(\d{3})(\d)/, '$1.$2');
            v = v.replace(/(\d{3})(\d)/, '$1.$2');
            v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        }
        this.value = v;
    });

    // ─── CNPJ Auto-Lookup (ReceitaWS) ───────────────────────
    cpfCnpjInput.addEventListener('blur', function () {
        if (selectedType !== 'pj') return;
        const cnpj = this.value.replace(/\D/g, '');
        if (cnpj.length !== 14) return;

        const loading = document.getElementById('cnpjLoading');
        loading.style.display = '';

        fetch('https://receitaws.com.br/v1/cnpj/' + cnpj, { mode: 'cors' })
            .then(r => r.json())
            .then(data => {
                if (data.status !== 'ERROR') {
                    if (data.nome) document.getElementById('nome_razao_social').value = data.nome;
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

    // ─── Phone Masks ─────────────────────────────────────────
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

    // ─── Status Color Indicator ──────────────────────────────
    const statusSelect = document.getElementById('status');
    if (statusSelect) {
        statusSelect.addEventListener('change', function () {
            this.className = this.className.replace(/status-select-\w+/, '');
            this.classList.add('status-select-' + this.value);
        });
    }
});
</script>
@endpush
