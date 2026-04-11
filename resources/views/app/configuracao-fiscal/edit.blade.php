@extends('layouts.app')

@section('title', 'Configuracao Fiscal')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-gear me-2"></i>Configuracao Fiscal</h4>
</div>

<form method="POST" action="{{ route('app.configuracao-fiscal.update') }}">
    @csrf
    @method('PUT')

    <div class="erp-card">
        <div class="card-header"><i class="bi bi-shield-check me-2"></i>Configuracao Fiscal</div>
        <div class="card-body">

            {{-- Pergunta principal --}}
            <h5 class="mb-3">Sua empresa emite nota fiscal eletronica?</h5>
            <div class="d-flex gap-3 mb-4">
                <div class="form-check form-check-inline">
                    <input type="radio" name="emissao_fiscal_ativa" value="1" id="fiscal_sim"
                           class="form-check-input" {{ old('emissao_fiscal_ativa', $config->emissao_fiscal_ativa) ? 'checked' : '' }}
                           onchange="document.getElementById('fiscal-config').classList.remove('d-none')">
                    <label for="fiscal_sim" class="form-check-label fw-bold text-success">Sim, emitimos</label>
                </div>
                <div class="form-check form-check-inline">
                    <input type="radio" name="emissao_fiscal_ativa" value="0" id="fiscal_nao"
                           class="form-check-input" {{ !old('emissao_fiscal_ativa', $config->emissao_fiscal_ativa) ? 'checked' : '' }}
                           onchange="document.getElementById('fiscal-config').classList.add('d-none')">
                    <label for="fiscal_nao" class="form-check-label fw-bold">Nao, apenas recibos</label>
                </div>
            </div>

            {{-- Config aparece so se SIM --}}
            <div id="fiscal-config" class="{{ old('emissao_fiscal_ativa', $config->emissao_fiscal_ativa) ? '' : 'd-none' }}">

                {{-- Tipo cupom PDV --}}
                <h6 class="mb-2">No PDV (frente de caixa), emitir:</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="d-block">
                            <input type="radio" name="tipo_cupom_pdv" value="fiscal" class="btn-check"
                                   {{ old('tipo_cupom_pdv', $config->tipo_cupom_pdv ?? 'nao_fiscal') === 'fiscal' ? 'checked' : '' }}>
                            <div class="erp-card p-3 text-center cursor-pointer" style="border: 2px solid transparent">
                                <i class="bi bi-receipt fs-2 text-success"></i>
                                <h6 class="mt-2 mb-1">NFC-e (Cupom Fiscal)</h6>
                                <small class="text-muted">Nota fiscal ao consumidor via SEFAZ</small>
                            </div>
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="d-block">
                            <input type="radio" name="tipo_cupom_pdv" value="nao_fiscal" class="btn-check"
                                   {{ old('tipo_cupom_pdv', $config->tipo_cupom_pdv ?? 'nao_fiscal') !== 'fiscal' ? 'checked' : '' }}>
                            <div class="erp-card p-3 text-center cursor-pointer" style="border: 2px solid transparent">
                                <i class="bi bi-file-text fs-2 text-secondary"></i>
                                <h6 class="mt-2 mb-1">Recibo (Nao Fiscal)</h6>
                                <small class="text-muted">Comprovante interno sem valor fiscal</small>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Token e Ambiente --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Token Focus NFe</label>
                        <div class="input-group">
                            <input type="password" name="focus_token" class="form-control @error('focus_token') is-invalid @enderror"
                                   value="{{ old('focus_token', $config->focus_token) }}" id="tokenInput"
                                   placeholder="Cole aqui o token da Focus NFe">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('tokenInput')">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="btn-testar-conexao">
                                Testar
                            </button>
                        </div>
                        @error('focus_token')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                        <small class="form-text">Token fornecido pela Focus NFe para sua empresa</small>
                        <span id="teste-resultado" class="small d-block mt-1"></span>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Ambiente</label>
                        <select name="ambiente" class="form-select @error('ambiente') is-invalid @enderror">
                            <option value="homologacao" {{ old('ambiente', $config->ambiente ?? 'homologacao') === 'homologacao' ? 'selected' : '' }}>Homologacao (testes)</option>
                            <option value="producao" {{ old('ambiente', $config->ambiente) === 'producao' ? 'selected' : '' }}>Producao (real)</option>
                        </select>
                        @error('ambiente')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Certificado Digital (info) --}}
                @if($config->certificado_validade)
                    @php
                        $validade = $config->certificado_validade;
                        $diasRestantes = now()->diffInDays($validade, false);
                    @endphp
                    <div class="d-flex align-items-center gap-3 p-3 rounded-3 mb-3 bg-{{ $diasRestantes > 30 ? 'success' : ($diasRestantes > 0 ? 'warning' : 'danger') }} bg-opacity-10">
                        <i class="bi bi-{{ $diasRestantes > 30 ? 'shield-check' : ($diasRestantes > 0 ? 'exclamation-triangle' : 'shield-x') }} fs-3 text-{{ $diasRestantes > 30 ? 'success' : ($diasRestantes > 0 ? 'warning' : 'danger') }}"></i>
                        <div>
                            <strong>Certificado Digital - Validade: {{ $validade->format('d/m/Y') }}</strong>
                            @if($diasRestantes > 0)
                                <small class="text-muted d-block">{{ $diasRestantes }} dia(s) restante(s)</small>
                            @else
                                <small class="text-danger fw-bold d-block">CERTIFICADO VENCIDO</small>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Modo avancado (colapsavel) --}}
                <a class="text-muted small" data-bs-toggle="collapse" href="#advancedConfig">
                    <i class="bi bi-gear me-1"></i>Configuracoes avancadas
                </a>
                <div class="collapse mt-3" id="advancedConfig">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Serie NF-e</label>
                            <input type="text" name="serie_nfe" class="form-control @error('serie_nfe') is-invalid @enderror"
                                   value="{{ old('serie_nfe', $config->serie_nfe ?? '1') }}">
                            @error('serie_nfe')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Serie NFC-e</label>
                            <input type="text" name="serie_nfce" class="form-control @error('serie_nfce') is-invalid @enderror"
                                   value="{{ old('serie_nfce', $config->serie_nfce ?? '1') }}">
                            @error('serie_nfce')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">CSC (NFC-e)</label>
                            <input type="text" name="csc_nfce" class="form-control @error('csc_nfce') is-invalid @enderror"
                                   value="{{ old('csc_nfce', $config->csc_nfce) }}">
                            @error('csc_nfce')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">ID CSC</label>
                            <input type="text" name="csc_id_nfce" class="form-control @error('csc_id_nfce') is-invalid @enderror"
                                   value="{{ old('csc_id_nfce', $config->csc_id_nfce) }}">
                            @error('csc_id_nfce')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-erp-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
// Toggle password visibility
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling?.querySelector('i') || input.parentElement.querySelector('.bi-eye, .bi-eye-slash');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        if (icon) icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}

// Test connection
document.getElementById('btn-testar-conexao')?.addEventListener('click', function () {
    const btn = this;
    const resultado = document.getElementById('teste-resultado');
    const token = document.getElementById('tokenInput').value;
    const ambiente = document.querySelector('select[name="ambiente"]')?.value;

    if (!token) {
        resultado.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Informe o token primeiro.</span>';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1 spin"></i> Testando...';
    resultado.innerHTML = '';

    fetch('{{ route("app.configuracao-fiscal.testar") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ token, ambiente })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = 'Testar';
        if (data.success) {
            resultado.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>' + data.message + '</span>';
        } else {
            resultado.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>' + data.message + '</span>';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = 'Testar';
        resultado.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Erro de conexao.</span>';
    });
});

// Highlight selected PDV card
document.querySelectorAll('input[name="tipo_cupom_pdv"]').forEach(radio => {
    radio.addEventListener('change', updatePdvCards);
});
function updatePdvCards() {
    document.querySelectorAll('input[name="tipo_cupom_pdv"]').forEach(r => {
        const card = r.closest('label').querySelector('.erp-card');
        if (card) {
            card.style.borderColor = r.checked ? 'var(--bs-primary, #0d6efd)' : 'transparent';
        }
    });
}
updatePdvCards();
</script>
<style>
.spin { animation: spin 1s linear infinite; display: inline-block; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.cursor-pointer { cursor: pointer; }
</style>
@endpush
