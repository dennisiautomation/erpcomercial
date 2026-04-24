@extends('layouts.app')

@section('title', 'Editar Servico')

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

    .info-tooltip { color: var(--text-muted); cursor: help; font-size: 0.85rem; }

    .nfse-skip-banner {
        background: var(--bg-body); border: 1px dashed var(--border);
        border-radius: var(--radius-xl); padding: 2rem; text-align: center;
    }
    .nfse-skip-banner i { font-size: 2.5rem; color: var(--text-muted); }
</style>
@endpush

@section('content')
{{-- Header --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-pencil-square me-2"></i>Editar Servico</h4>
        <p class="text-muted mb-0 small">{{ $servico->descricao }}</p>
    </div>
    <a href="{{ route('app.servicos.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

{{-- Progress Indicator --}}
<div class="wizard-progress" id="wizardProgress">
    <div class="step-item">
        <div class="step-circle active" data-step="1">1<span class="step-label">Dados do Servico</span></div>
    </div>
    <div class="step-item"><div class="step-line" data-line="1"></div></div>
    <div class="step-item">
        <div class="step-circle" data-step="2">2<span class="step-label">Dados Fiscais</span></div>
    </div>
</div>

<form method="POST" action="{{ route('app.servicos.update', $servico) }}" id="formServico" novalidate>
    @csrf
    @method('PUT')

    <div class="wizard-card">

        {{-- STEP 1 — Dados do Servico --}}
        <div class="wizard-step active" data-step="1">
            <h5 class="step-title">Dados do Servico</h5>
            <p class="step-subtitle">Informe a descricao e o valor padrao</p>

            <div class="row g-3">
                <div class="col-12">
                    <label for="descricao" class="form-label required-dot">Descricao</label>
                    <input type="text" name="descricao" id="descricao"
                           class="form-control @error('descricao') is-invalid @enderror"
                           value="{{ old('descricao', $servico->descricao) }}" required>
                    @error('descricao') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    <div class="field-error" id="errorDescricao"></div>
                </div>

                <div class="col-md-5">
                    <label for="valor_padrao" class="form-label required-dot">Valor Padrao (R$)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white fw-semibold">R$</span>
                        <input type="number" name="valor_padrao" id="valor_padrao"
                               class="form-control @error('valor_padrao') is-invalid @enderror"
                               value="{{ old('valor_padrao', $servico->valor_padrao) }}" step="0.01" min="0" required>
                    </div>
                    @error('valor_padrao') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    <div class="field-error" id="errorValor"></div>
                </div>

                <div class="col-md-4">
                    <label for="status" class="form-label required-dot">Status</label>
                    <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                        <option value="ativo" {{ old('status', $servico->status) === 'ativo' ? 'selected' : '' }}>Ativo</option>
                        <option value="inativo" {{ old('status', $servico->status) === 'inativo' ? 'selected' : '' }}>Inativo</option>
                    </select>
                    @error('status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        {{-- STEP 2 — Dados Fiscais --}}
        <div class="wizard-step" data-step="2">
            <h5 class="step-title">Dados Fiscais</h5>
            <p class="step-subtitle">Configuracoes fiscais para emissao de NFS-e</p>

            @if(isset($emiteNfse) && $emiteNfse)
                <div class="row g-3">
                    <div class="col-md-5">
                        <label for="codigo_servico_municipal" class="form-label">
                            Codigo LC 116
                            <i class="bi bi-info-circle info-tooltip" data-bs-toggle="tooltip" data-bs-placement="top"
                               title="Codigo da Lista de Servicos (Lei Complementar 116). Define a classificacao do servico para fins de ISS."></i>
                        </label>
                        <input type="text" name="codigo_servico_municipal" id="codigo_servico_municipal"
                               class="form-control @error('codigo_servico_municipal') is-invalid @enderror"
                               value="{{ old('codigo_servico_municipal', $servico->codigo_servico_municipal) }}" placeholder="Ex: 14.01">
                        @error('codigo_servico_municipal') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="cnae_busca" class="form-label">CNAE</label>
                        <input type="text" id="cnae_busca" class="form-control"
                               value="{{ old('cnae', $servico->cnae) }}" placeholder="Digite atividade (ex: software, consultoria)"
                               data-autocomplete="{{ route('app.focus-autocomplete.cnae') }}"
                               data-autocomplete-target="cnae"
                               data-autocomplete-display="descricao"
                               data-autocomplete-value="codigo">
                        <input type="hidden" name="cnae" id="cnae" value="{{ old('cnae', $servico->cnae) }}">
                        @error('cnae') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label for="iss_aliquota" class="form-label">Aliquota ISS (%)</label>
                        <div class="input-group">
                            <input type="number" name="iss_aliquota" id="iss_aliquota"
                                   class="form-control @error('iss_aliquota') is-invalid @enderror"
                                   value="{{ old('iss_aliquota', $servico->iss_aliquota) }}" step="0.01" min="0" max="100">
                            <span class="input-group-text bg-white">%</span>
                        </div>
                        @error('iss_aliquota') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>
            @else
                <div class="nfse-skip-banner">
                    <i class="bi bi-file-earmark-x d-block mb-3"></i>
                    <h6 class="fw-bold mb-2">Sua empresa nao emite NFS-e</h6>
                    <p class="text-muted mb-3">Voce pode pular esta etapa. Os dados fiscais podem ser configurados posteriormente.</p>
                    <button type="submit" class="btn btn-outline-primary rounded-pill">
                        <i class="bi bi-skip-forward me-1"></i> Pular e Salvar
                    </button>
                </div>
            @endif
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
                <i class="bi bi-check-lg me-1"></i> Atualizar Servico
            </button>
        </div>

    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    let currentStep = 1;
    const totalSteps = 2;

    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });

    @if($errors->any())
        @if($errors->has('descricao') || $errors->has('valor_padrao') || $errors->has('status'))
            goToStep(1, true);
        @else
            goToStep(2, true);
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
            if (!document.getElementById('descricao').value.trim()) { showError('errorDescricao', 'Informe a descricao do servico'); valid = false; }
            return valid;
        }
        return true;
    }

    function showError(id, msg) { const el = document.getElementById(id); if (el) { el.textContent = msg; el.classList.add('show'); } }
    function clearErrors() { document.querySelectorAll('.field-error').forEach(el => el.classList.remove('show')); }
});
</script>
@endpush
