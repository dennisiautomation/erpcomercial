@extends('layouts.app')

@section('title', 'Novo Funcionario')

@push('styles')
<style>
    /* ─── Wizard Progress ─────────────────────────────────── */
    .wizard-progress {
        display: flex; justify-content: center; align-items: center; gap: 0;
        margin-bottom: 2.5rem; padding: 0 1rem;
    }
    .wizard-progress .step-item { display: flex; align-items: center; }
    .wizard-progress .step-circle {
        width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 0.9rem; border: 2px solid var(--border); color: var(--text-muted);
        background: var(--bg-card); transition: all 0.3s ease; position: relative;
    }
    .wizard-progress .step-circle.active { border-color: var(--primary); background: var(--primary); color: #fff; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15); }
    .wizard-progress .step-circle.done { border-color: var(--success); background: var(--success); color: #fff; }
    .wizard-progress .step-label {
        display: none; font-size: 0.75rem; font-weight: 600; color: var(--text-muted);
        position: absolute; top: 48px; white-space: nowrap;
    }
    .wizard-progress .step-circle.active .step-label,
    .wizard-progress .step-circle.done .step-label { color: var(--text-secondary); }
    @media (min-width: 576px) { .wizard-progress .step-label { display: block; } }
    .wizard-progress .step-line { width: 60px; height: 2px; background: var(--border); margin: 0 0.5rem; transition: background 0.3s ease; }
    .wizard-progress .step-line.done { background: var(--success); }

    .wizard-step { display: none; }
    .wizard-step.active { display: block; animation: wizardFadeIn 0.35s ease; }
    @keyframes wizardFadeIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

    .wizard-card {
        background: var(--bg-card); border-radius: var(--radius-xl); box-shadow: var(--shadow);
        padding: 2rem; max-width: 720px; margin: 0 auto;
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

    /* ─── Perfil Selection Cards ──────────────────────────── */
    .perfil-option {
        border: 2px solid var(--border);
        border-radius: var(--radius-xl);
        cursor: pointer;
        transition: all 0.25s ease;
        background: var(--bg-card);
        position: relative;
        overflow: hidden;
        padding: 1.25rem;
        text-align: center;
    }
    .perfil-option:hover {
        border-color: var(--primary);
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
    }
    .perfil-option.selected {
        border-color: var(--primary);
        background: var(--primary-light);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
    }
    .perfil-option.selected::after {
        content: '\F26A';
        font-family: 'bootstrap-icons';
        position: absolute;
        top: 8px;
        right: 10px;
        font-size: 1.1rem;
        color: var(--primary);
    }
    .perfil-option .perfil-icon {
        font-size: 2rem;
        color: var(--primary);
        margin-bottom: 0.5rem;
    }
    .perfil-option h6 {
        font-weight: 700;
        margin-bottom: 0.15rem;
        color: var(--text-primary);
    }
    .perfil-option p {
        font-size: 0.78rem;
        color: var(--text-muted);
        margin-bottom: 0;
    }
</style>
@endpush

@section('content')
{{-- Header --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-person-plus-fill me-2"></i>Novo Funcionario</h4>
        <p class="text-muted mb-0 small">Siga os passos para cadastrar um novo funcionario</p>
    </div>
    <a href="{{ route('app.funcionarios.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

{{-- Progress Indicator --}}
<div class="wizard-progress" id="wizardProgress">
    <div class="step-item">
        <div class="step-circle active" data-step="1">1<span class="step-label">Dados Pessoais</span></div>
    </div>
    <div class="step-item"><div class="step-line" data-line="1"></div></div>
    <div class="step-item">
        <div class="step-circle" data-step="2">2<span class="step-label">Acesso</span></div>
    </div>
    <div class="step-item"><div class="step-line" data-line="2"></div></div>
    <div class="step-item">
        <div class="step-circle" data-step="3">3<span class="step-label">Configuracao</span></div>
    </div>
</div>

<form method="POST" action="{{ route('app.funcionarios.store') }}" id="formFuncionario" novalidate>
    @csrf
    <input type="hidden" name="perfil" id="perfil" value="{{ old('perfil', '') }}">

    <div class="wizard-card">

        {{-- ═══════════════════════════════════════════════════
             STEP 1 — Dados Pessoais
             ═══════════════════════════════════════════════════ --}}
        <div class="wizard-step active" data-step="1">
            <h5 class="step-title">Dados Pessoais</h5>
            <p class="step-subtitle">Informacoes pessoais do funcionario</p>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label required-dot">Nome Completo</label>
                    <input type="text" name="name" id="name"
                           class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" required>
                    @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    <div class="field-error" id="errorName"></div>
                </div>

                <div class="col-md-6">
                    <label for="cpf" class="form-label">CPF</label>
                    <input type="text" name="cpf" id="cpf"
                           class="form-control @error('cpf') is-invalid @enderror"
                           value="{{ old('cpf') }}" maxlength="14" placeholder="000.000.000-00" data-mask="cpf">
                    @error('cpf') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label for="telefone" class="form-label">Telefone</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-telephone text-muted"></i></span>
                        <input type="text" name="telefone" id="telefone"
                               class="form-control @error('telefone') is-invalid @enderror"
                               value="{{ old('telefone') }}" placeholder="(00) 00000-0000" data-mask="telefone">
                    </div>
                    @error('telefone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label for="email_pessoal" class="form-label">E-mail Pessoal <span class="text-muted fw-normal">(opcional)</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-envelope text-muted"></i></span>
                        <input type="email" name="email_pessoal" id="email_pessoal"
                               class="form-control"
                               value="{{ old('email_pessoal') }}" placeholder="pessoal@email.com">
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════
             STEP 2 — Acesso ao Sistema
             ═══════════════════════════════════════════════════ --}}
        <div class="wizard-step" data-step="2">
            <h5 class="step-title">Acesso ao Sistema</h5>
            <p class="step-subtitle">Defina as credenciais e o perfil de acesso</p>

            <div class="row g-3">
                <div class="col-md-12">
                    <label for="email" class="form-label required-dot">E-mail (login)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-at text-muted"></i></span>
                        <input type="email" name="email" id="email"
                               class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email') }}" required placeholder="login@empresa.com">
                    </div>
                    @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    <div class="field-error" id="errorEmail"></div>
                </div>

                <div class="col-md-6">
                    <label for="password" class="form-label required-dot">Senha</label>
                    <input type="password" name="password" id="password"
                           class="form-control @error('password') is-invalid @enderror" required>
                    @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    <div class="field-error" id="errorPassword"></div>
                </div>

                <div class="col-md-6">
                    <label for="password_confirmation" class="form-label required-dot">Confirmar Senha</label>
                    <input type="password" name="password_confirmation" id="password_confirmation"
                           class="form-control" required>
                    <div class="field-error" id="errorPasswordConfirm"></div>
                </div>
            </div>

            {{-- Perfil cards --}}
            <label class="form-label required-dot mt-4 mb-3">Perfil de Acesso</label>
            <div class="row g-3">
                <div class="col-6 col-md-4">
                    <div class="perfil-option {{ old('perfil') === 'gerente' ? 'selected' : '' }}" data-perfil="gerente" onclick="selectPerfil('gerente')">
                        <i class="bi bi-shield-check perfil-icon d-block"></i>
                        <h6>Gerente</h6>
                        <p>Gerencia a unidade</p>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="perfil-option {{ old('perfil') === 'vendedor' ? 'selected' : '' }}" data-perfil="vendedor" onclick="selectPerfil('vendedor')">
                        <i class="bi bi-bag perfil-icon d-block"></i>
                        <h6>Vendedor</h6>
                        <p>Registra vendas e orcamentos</p>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="perfil-option {{ old('perfil') === 'caixa' ? 'selected' : '' }}" data-perfil="caixa" onclick="selectPerfil('caixa')">
                        <i class="bi bi-cash-stack perfil-icon d-block"></i>
                        <h6>Caixa</h6>
                        <p>Opera o PDV</p>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="perfil-option {{ old('perfil') === 'financeiro' ? 'selected' : '' }}" data-perfil="financeiro" onclick="selectPerfil('financeiro')">
                        <i class="bi bi-graph-up perfil-icon d-block"></i>
                        <h6>Financeiro</h6>
                        <p>Gerencia contas e relatorios</p>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="perfil-option {{ old('perfil') === 'consulta' ? 'selected' : '' }}" data-perfil="consulta" onclick="selectPerfil('consulta')">
                        <i class="bi bi-eye perfil-icon d-block"></i>
                        <h6>Consulta</h6>
                        <p>Apenas visualizacao</p>
                    </div>
                </div>
            </div>
            <div class="field-error" id="errorPerfil"></div>
            @error('perfil') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        {{-- ═══════════════════════════════════════════════════
             STEP 3 — Configuracao
             ═══════════════════════════════════════════════════ --}}
        <div class="wizard-step" data-step="3">
            <h5 class="step-title">Configuracao</h5>
            <p class="step-subtitle">Comissao e unidades de acesso</p>

            <div class="row g-3">
                <div class="col-md-5">
                    <label for="comissao_percentual" class="form-label">Comissao (%)</label>
                    <div class="input-group">
                        <input type="number" name="comissao_percentual" id="comissao_percentual"
                               class="form-control @error('comissao_percentual') is-invalid @enderror"
                               value="{{ old('comissao_percentual', '0.00') }}" step="0.01" min="0" max="100">
                        <span class="input-group-text bg-white">%</span>
                    </div>
                    @error('comissao_percentual') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="col-12 mt-3">
                    <label class="form-label fw-semibold mb-3">Unidade(s) de Acesso</label>
                    @if($unidades->isEmpty())
                        <p class="text-muted">Nenhuma unidade disponivel</p>
                    @else
                        <div class="row g-2">
                            @foreach($unidades as $unidade)
                                <div class="col-md-6">
                                    <div class="form-check p-3 border rounded-3">
                                        <input class="form-check-input" type="checkbox" name="unidades[]"
                                               value="{{ $unidade->id }}" id="unidade_{{ $unidade->id }}"
                                               {{ in_array($unidade->id, old('unidades', [])) ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="unidade_{{ $unidade->id }}">
                                            <i class="bi bi-building me-1 text-muted"></i>{{ $unidade->nome }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @error('unidades') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════
             Navigation Bar
             ═══════════════════════════════════════════════════ --}}
        <div class="wizard-nav">
            <button type="button" class="btn btn-outline-secondary rounded-pill" id="btnVoltar" onclick="goToStep(currentStep - 1)" style="display:none;">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </button>
            <div id="navSpacer"></div>
            <button type="button" class="btn btn-primary rounded-pill" id="btnAvancar" onclick="goToStep(currentStep + 1)">
                Avancar <i class="bi bi-arrow-right ms-1"></i>
            </button>
            <button type="submit" class="btn btn-success rounded-pill" id="btnSalvar" style="display:none;">
                <i class="bi bi-check-lg me-1"></i> Salvar Funcionario
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
        @if($errors->has('name') || $errors->has('cpf') || $errors->has('telefone'))
            goToStep(1, true);
        @elseif($errors->has('email') || $errors->has('password') || $errors->has('perfil'))
            goToStep(2, true);
        @else
            goToStep(3, true);
        @endif
    @endif

    // ─── Perfil Selection ────────────────────────────────────
    window.selectPerfil = function(perfil) {
        document.getElementById('perfil').value = perfil;
        document.querySelectorAll('.perfil-option').forEach(el => el.classList.remove('selected'));
        document.querySelector('.perfil-option[data-perfil="' + perfil + '"]').classList.add('selected');
        document.getElementById('errorPerfil').classList.remove('show');
    };

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
            let valid = true;
            if (!document.getElementById('name').value.trim()) {
                showError('errorName', 'Informe o nome do funcionario');
                valid = false;
            }
            return valid;
        }

        if (step === 2) {
            let valid = true;
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirmation').value;
            const perfil = document.getElementById('perfil').value;

            if (!email) { showError('errorEmail', 'Informe o e-mail de acesso'); valid = false; }
            if (!password) { showError('errorPassword', 'Informe a senha'); valid = false; }
            else if (password.length < 6) { showError('errorPassword', 'A senha deve ter pelo menos 6 caracteres'); valid = false; }
            if (password && password !== passwordConfirm) { showError('errorPasswordConfirm', 'As senhas nao conferem'); valid = false; }
            if (!perfil) { showError('errorPerfil', 'Selecione um perfil de acesso'); valid = false; }

            return valid;
        }

        return true;
    }

    function showError(id, msg) { const el = document.getElementById(id); if (el) { el.textContent = msg; el.classList.add('show'); } }
    function clearErrors() { document.querySelectorAll('.field-error').forEach(el => el.classList.remove('show')); }

    // ─── CPF Mask ────────────────────────────────────────────
    const cpfInput = document.getElementById('cpf');
    cpfInput.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').substring(0, 11);
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        this.value = v;
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
