@extends('layouts.app')

@section('title', 'Configuracao Fiscal')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-gear me-2"></i>Configuracao Fiscal</h4>
</div>

{{-- Status Indicator --}}
@php
    $configCompleta = $config->exists
        && $config->focus_token
        && $config->serie_nfe
        && $config->serie_nfce;
@endphp
<div class="alert alert-{{ $configCompleta ? 'success' : 'warning' }} d-flex align-items-center mb-4">
    <i class="bi bi-{{ $configCompleta ? 'check-circle-fill' : 'exclamation-triangle-fill' }} me-2 fs-5"></i>
    @if($configCompleta)
        Configuracao fiscal completa e {{ $config->emissao_fiscal_ativa ? 'ativa' : 'inativa' }}.
    @else
        Configuracao fiscal incompleta. Preencha todos os campos obrigatorios para ativar a emissao.
    @endif
</div>

<form method="POST" action="{{ route('app.configuracao-fiscal.update') }}">
    @csrf
    @method('PUT')

    <div class="row g-4">
        {{-- Ambiente --}}
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-cloud me-1"></i> Ambiente</div>
                <div class="card-body">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="ambiente" id="amb_homolog" value="homologacao"
                               {{ old('ambiente', $config->ambiente ?? 'homologacao') === 'homologacao' ? 'checked' : '' }}>
                        <label class="form-check-label" for="amb_homolog">
                            <span class="badge bg-warning me-1">Homologacao</span> Ambiente de testes
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="ambiente" id="amb_prod" value="producao"
                               {{ old('ambiente', $config->ambiente) === 'producao' ? 'checked' : '' }}>
                        <label class="form-check-label" for="amb_prod">
                            <span class="badge bg-danger me-1">Producao</span> Ambiente real (notas com validade juridica)
                        </label>
                    </div>
                    @error('ambiente')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Token Focus NFe --}}
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-key me-1"></i> Token Focus NFe</div>
                <div class="card-body">
                    <div class="input-group mb-2">
                        <input type="password" name="focus_token" id="focus_token"
                               class="form-control @error('focus_token') is-invalid @enderror"
                               value="{{ old('focus_token', $config->focus_token) }}"
                               placeholder="Cole aqui o token da Focus NFe">
                        <button class="btn btn-outline-secondary" type="button" id="toggleToken" title="Mostrar/ocultar token">
                            <i class="bi bi-eye" id="toggleTokenIcon"></i>
                        </button>
                    </div>
                    @error('focus_token')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-testar-conexao">
                        <i class="bi bi-plug me-1"></i> Testar Conexao
                    </button>
                    <span id="teste-resultado" class="ms-2 small"></span>
                </div>
            </div>
        </div>

        {{-- Series --}}
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-123 me-1"></i> Series</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Serie NF-e</label>
                            <input type="number" name="serie_nfe" class="form-control @error('serie_nfe') is-invalid @enderror"
                                   min="1" max="999" value="{{ old('serie_nfe', $config->serie_nfe ?? 1) }}">
                            @error('serie_nfe')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Serie NFC-e</label>
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
            <div class="card">
                <div class="card-header"><i class="bi bi-shield-lock me-1"></i> NFC-e - CSC</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">CSC (Codigo de Seguranca do Contribuinte)</label>
                            <input type="text" name="csc_nfce" class="form-control @error('csc_nfce') is-invalid @enderror"
                                   value="{{ old('csc_nfce', $config->csc_nfce) }}" placeholder="Token CSC da SEFAZ">
                            @error('csc_nfce')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">CSC ID</label>
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
            <div class="card">
                <div class="card-header"><i class="bi bi-file-earmark-lock me-1"></i> Certificado Digital</div>
                <div class="card-body">
                    <p class="text-muted small mb-2">
                        O certificado digital A1 e enviado diretamente pelo painel da Focus NFe.
                    </p>
                    @if($config->certificado_validade)
                        @php
                            $validade = $config->certificado_validade;
                            $diasRestantes = now()->diffInDays($validade, false);
                        @endphp
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-{{ $diasRestantes > 30 ? 'success' : ($diasRestantes > 0 ? 'warning' : 'danger') }} fs-6">
                                {{ $validade->format('d/m/Y') }}
                            </span>
                            @if($diasRestantes > 0)
                                <small class="text-muted">({{ $diasRestantes }} dias restantes)</small>
                            @else
                                <small class="text-danger fw-bold">VENCIDO</small>
                            @endif
                        </div>
                    @else
                        <span class="text-muted">Validade nao informada.</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Emissao Fiscal + PDV --}}
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-toggles me-1"></i> Emissao e PDV</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="emissao_fiscal_ativa" id="emissao_fiscal_ativa" value="1"
                               {{ old('emissao_fiscal_ativa', $config->emissao_fiscal_ativa) ? 'checked' : '' }}>
                        <label class="form-check-label" for="emissao_fiscal_ativa">
                            Emissao fiscal ativa
                        </label>
                    </div>

                    <label class="form-label">Tipo de Cupom no PDV</label>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="tipo_cupom_pdv" id="cupom_fiscal" value="fiscal"
                               {{ old('tipo_cupom_pdv', $config->tipo_cupom_pdv ?? 'nao_fiscal') === 'fiscal' ? 'checked' : '' }}>
                        <label class="form-check-label" for="cupom_fiscal">
                            <i class="bi bi-receipt me-1 text-success"></i> Fiscal (NFC-e) - Emite nota fiscal a cada venda
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="tipo_cupom_pdv" id="cupom_nao_fiscal" value="nao_fiscal"
                               {{ old('tipo_cupom_pdv', $config->tipo_cupom_pdv ?? 'nao_fiscal') === 'nao_fiscal' ? 'checked' : '' }}>
                        <label class="form-check-label" for="cupom_nao_fiscal">
                            <i class="bi bi-receipt-cutoff me-1 text-muted"></i> Nao Fiscal (Recibo) - Apenas comprovante interno
                        </label>
                    </div>
                    @error('tipo_cupom_pdv')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Save --}}
        <div class="col-12">
            <hr>
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check-lg me-1"></i> Salvar Configuracao
            </button>
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

// Test connection
document.getElementById('btn-testar-conexao')?.addEventListener('click', function () {
    const btn = this;
    const resultado = document.getElementById('teste-resultado');
    const token = document.getElementById('focus_token').value;
    const ambiente = document.querySelector('input[name="ambiente"]:checked')?.value;

    if (!token) {
        resultado.innerHTML = '<span class="text-danger">Informe o token primeiro.</span>';
        return;
    }

    btn.disabled = true;
    resultado.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split"></i> Testando...</span>';

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
        if (data.success) {
            resultado.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> ' + data.message + '</span>';
        } else {
            resultado.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> ' + data.message + '</span>';
        }
    })
    .catch(() => {
        btn.disabled = false;
        resultado.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> Erro de conexao.</span>';
    });
});
</script>
@endpush
