@extends('layouts.app')

@section('title', 'Configuracao Fiscal')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-gear me-2"></i>Configuracao Fiscal</h4>
</div>

{{-- Status Banner --}}
@php
    $configCompleta = $config->exists
        && $config->focus_token
        && $config->serie_nfe
        && $config->serie_nfce;
@endphp
<div class="alert {{ $configCompleta ? 'alert-success' : 'alert-warning' }} d-flex align-items-center shadow-sm mb-4">
    <div class="me-3">
        <i class="bi bi-{{ $configCompleta ? 'shield-check' : 'exclamation-triangle-fill' }} fs-3"></i>
    </div>
    <div>
        @if($configCompleta)
            <strong>Configuracao fiscal completa.</strong>
            <span class="d-block small">Emissao {{ $config->emissao_fiscal_ativa ? 'ativa' : 'inativa' }} | Ambiente: {{ ucfirst($config->ambiente ?? 'homologacao') }}</span>
        @else
            <strong>Configuracao incompleta.</strong>
            <span class="d-block small">Preencha todos os campos obrigatorios para ativar a emissao fiscal.</span>
        @endif
    </div>
</div>

<form method="POST" action="{{ route('app.configuracao-fiscal.update') }}">
    @csrf
    @method('PUT')

    <div class="row g-4">
        {{-- Emissao e PDV Toggle --}}
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-toggles me-1"></i> Emissao Fiscal e PDV
                </div>
                <div class="card-body">
                    <div class="row align-items-center g-4">
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="emissao_fiscal_ativa" id="emissao_fiscal_ativa" value="1"
                                       {{ old('emissao_fiscal_ativa', $config->emissao_fiscal_ativa) ? 'checked' : '' }}
                                       style="width: 3em; height: 1.5em;">
                                <label class="form-check-label fw-semibold ms-2" for="emissao_fiscal_ativa" style="line-height: 1.5em;">
                                    Emissao Fiscal Ativa
                                </label>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold mb-2">Tipo de Cupom no PDV</label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo_cupom_pdv" id="cupom_fiscal" value="fiscal"
                                           {{ old('tipo_cupom_pdv', $config->tipo_cupom_pdv ?? 'nao_fiscal') === 'fiscal' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="cupom_fiscal">
                                        <i class="bi bi-receipt text-success me-1"></i> <strong>Fiscal</strong> (NFC-e)
                                        <small class="text-muted d-block">Emite nota fiscal a cada venda</small>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipo_cupom_pdv" id="cupom_nao_fiscal" value="nao_fiscal"
                                           {{ old('tipo_cupom_pdv', $config->tipo_cupom_pdv ?? 'nao_fiscal') === 'nao_fiscal' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="cupom_nao_fiscal">
                                        <i class="bi bi-receipt-cutoff text-muted me-1"></i> <strong>Nao Fiscal</strong> (Recibo)
                                        <small class="text-muted d-block">Apenas comprovante interno</small>
                                    </label>
                                </div>
                            </div>
                            @error('tipo_cupom_pdv')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Ambiente --}}
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-cloud me-1"></i> Ambiente
                </div>
                <div class="card-body">
                    <div class="d-flex gap-3">
                        <div class="form-check flex-fill border rounded-3 p-3 {{ old('ambiente', $config->ambiente ?? 'homologacao') === 'homologacao' ? 'border-warning bg-warning bg-opacity-10' : '' }}">
                            <input class="form-check-input" type="radio" name="ambiente" id="amb_homolog" value="homologacao"
                                   {{ old('ambiente', $config->ambiente ?? 'homologacao') === 'homologacao' ? 'checked' : '' }}
                                   onchange="highlightAmbiente()">
                            <label class="form-check-label w-100" for="amb_homolog">
                                <span class="badge bg-warning text-dark me-1">HML</span>
                                <strong>Homologacao</strong>
                                <small class="text-muted d-block mt-1">Ambiente de testes, notas sem validade</small>
                            </label>
                        </div>
                        <div class="form-check flex-fill border rounded-3 p-3 {{ old('ambiente', $config->ambiente) === 'producao' ? 'border-danger bg-danger bg-opacity-10' : '' }}">
                            <input class="form-check-input" type="radio" name="ambiente" id="amb_prod" value="producao"
                                   {{ old('ambiente', $config->ambiente) === 'producao' ? 'checked' : '' }}
                                   onchange="highlightAmbiente()">
                            <label class="form-check-label w-100" for="amb_prod">
                                <span class="badge bg-danger me-1">PRD</span>
                                <strong>Producao</strong>
                                <small class="text-muted d-block mt-1">Notas com validade juridica</small>
                            </label>
                        </div>
                    </div>
                    @error('ambiente')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Token Focus NFe --}}
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-key me-1"></i> Token Focus NFe
                </div>
                <div class="card-body">
                    <div class="input-group mb-3">
                        <input type="password" name="focus_token" id="focus_token"
                               class="form-control @error('focus_token') is-invalid @enderror"
                               value="{{ old('focus_token', $config->focus_token) }}"
                               placeholder="Cole aqui o token da Focus NFe">
                        <button class="btn btn-outline-secondary" type="button" id="toggleToken" title="Mostrar/ocultar token">
                            <i class="bi bi-eye" id="toggleTokenIcon"></i>
                        </button>
                    </div>
                    @error('focus_token')
                        <div class="text-danger small mb-2">{{ $message }}</div>
                    @enderror
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-testar-conexao">
                            <i class="bi bi-plug me-1"></i> Testar Conexao
                        </button>
                        <span id="teste-resultado" class="small"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Series --}}
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-123 me-1"></i> Series de Numeracao
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Serie NF-e</label>
                            <input type="number" name="serie_nfe" class="form-control @error('serie_nfe') is-invalid @enderror"
                                   min="1" max="999" value="{{ old('serie_nfe', $config->serie_nfe ?? 1) }}">
                            @error('serie_nfe')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Serie NFC-e</label>
                            <input type="number" name="serie_nfce" class="form-control @error('serie_nfce') is-invalid @enderror"
                                   min="1" max="999" value="{{ old('serie_nfce', $config->serie_nfce ?? 1) }}">
                            @error('serie_nfce')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- NFC-e CSC --}}
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-shield-lock me-1"></i> NFC-e - CSC
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">CSC (Codigo de Seguranca)</label>
                            <input type="text" name="csc_nfce" class="form-control @error('csc_nfce') is-invalid @enderror"
                                   value="{{ old('csc_nfce', $config->csc_nfce) }}" placeholder="Token CSC da SEFAZ">
                            @error('csc_nfce')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">CSC ID</label>
                            <input type="text" name="csc_id_nfce" class="form-control @error('csc_id_nfce') is-invalid @enderror"
                                   value="{{ old('csc_id_nfce', $config->csc_id_nfce) }}" placeholder="ID">
                            @error('csc_id_nfce')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Certificado Digital --}}
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-file-earmark-lock me-1"></i> Certificado Digital
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        O certificado digital A1 deve ser enviado diretamente pelo painel da Focus NFe.
                    </p>
                    @if($config->certificado_validade)
                        @php
                            $validade = $config->certificado_validade;
                            $diasRestantes = now()->diffInDays($validade, false);
                        @endphp
                        <div class="d-flex align-items-center gap-3 p-3 rounded-3 bg-{{ $diasRestantes > 30 ? 'success' : ($diasRestantes > 0 ? 'warning' : 'danger') }} bg-opacity-10">
                            <i class="bi bi-{{ $diasRestantes > 30 ? 'shield-check' : ($diasRestantes > 0 ? 'exclamation-triangle' : 'shield-x') }} fs-3 text-{{ $diasRestantes > 30 ? 'success' : ($diasRestantes > 0 ? 'warning' : 'danger') }}"></i>
                            <div>
                                <strong>Validade: {{ $validade->format('d/m/Y') }}</strong>
                                @if($diasRestantes > 0)
                                    <small class="text-muted d-block">{{ $diasRestantes }} dia(s) restante(s)</small>
                                @else
                                    <small class="text-danger fw-bold d-block">CERTIFICADO VENCIDO</small>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="text-muted p-3 border rounded-3 text-center">
                            <i class="bi bi-shield-x d-block fs-3 mb-1 opacity-50"></i>
                            Validade nao informada
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Save --}}
        <div class="col-12">
            <hr>
            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-lg me-1"></i> Salvar Configuracao
                </button>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
// Toggle token visibility
document.getElementById('toggleToken')?.addEventListener('click', function () {
    const input = document.getElementById('focus_token');
    const icon = document.getElementById('toggleTokenIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
});

// Highlight ambiente selection
function highlightAmbiente() {
    document.querySelectorAll('[name="ambiente"]').forEach(radio => {
        const container = radio.closest('.form-check');
        container.classList.remove('border-warning', 'bg-warning', 'bg-opacity-10', 'border-danger', 'bg-danger');
        if (radio.checked) {
            if (radio.value === 'homologacao') {
                container.classList.add('border-warning', 'bg-warning', 'bg-opacity-10');
            } else {
                container.classList.add('border-danger', 'bg-danger', 'bg-opacity-10');
            }
        }
    });
}

// Test connection
document.getElementById('btn-testar-conexao')?.addEventListener('click', function () {
    const btn = this;
    const resultado = document.getElementById('teste-resultado');
    const token = document.getElementById('focus_token').value;
    const ambiente = document.querySelector('input[name="ambiente"]:checked')?.value;

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
        btn.innerHTML = '<i class="bi bi-plug me-1"></i> Testar Conexao';
        if (data.success) {
            resultado.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>' + data.message + '</span>';
        } else {
            resultado.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>' + data.message + '</span>';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plug me-1"></i> Testar Conexao';
        resultado.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Erro de conexao.</span>';
    });
});
</script>
<style>
.spin { animation: spin 1s linear infinite; display: inline-block; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
@endpush
