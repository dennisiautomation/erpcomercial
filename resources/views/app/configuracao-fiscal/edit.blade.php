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

                <div class="alert alert-info small">
                    <i class="bi bi-info-circle me-1"></i>
                    O mesmo <strong>token e certificado</strong> da Focus NFe emite NF-e, NFC-e e NFS-e.
                    Abaixo, habilite cada tipo e configure os dados específicos.
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

                {{-- ═══ NF-e ═══ --}}
                <div class="erp-card mt-3 mb-3 border">
                    <div class="card-header bg-transparent d-flex align-items-center">
                        <i class="bi bi-file-earmark-text fs-4 text-primary me-2"></i>
                        <div class="flex-grow-1">
                            <strong>NF-e (DANFE)</strong>
                            <div class="small text-muted">Nota fiscal eletrônica para vendas a empresas</div>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input type="hidden" name="emite_nfe" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="switch_nfe"
                                   name="emite_nfe" value="1"
                                   {{ old('emite_nfe', $config->emite_nfe ?? false) ? 'checked' : '' }}
                                   onchange="document.getElementById('nfe_campos').classList.toggle('d-none', !this.checked)">
                            <label class="form-check-label small" for="switch_nfe">Habilitar</label>
                        </div>
                    </div>
                    <div class="card-body {{ old('emite_nfe', $config->emite_nfe ?? false) ? '' : 'd-none' }}" id="nfe_campos">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Série NF-e</label>
                                <input type="text" name="serie_nfe" class="form-control @error('serie_nfe') is-invalid @enderror"
                                       value="{{ old('serie_nfe', $config->serie_nfe ?? '1') }}" placeholder="1">
                                @error('serie_nfe')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            NF-e é emitida manualmente a partir da tela da venda (botão "Emitir NF-e").
                        </small>
                    </div>
                </div>

                {{-- ═══ NFC-e ═══ --}}
                <div class="erp-card mb-3 border">
                    <div class="card-header bg-transparent d-flex align-items-center">
                        <i class="bi bi-receipt fs-4 text-success me-2"></i>
                        <div class="flex-grow-1">
                            <strong>NFC-e (Cupom Fiscal)</strong>
                            <div class="small text-muted">Cupom fiscal eletrônico para consumidor final (PDV)</div>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input type="hidden" name="emite_nfce" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="switch_nfce"
                                   name="emite_nfce" value="1"
                                   {{ old('emite_nfce', $config->emite_nfce ?? false) ? 'checked' : '' }}
                                   onchange="document.getElementById('nfce_campos').classList.toggle('d-none', !this.checked)">
                            <label class="form-check-label small" for="switch_nfce">Habilitar</label>
                        </div>
                    </div>
                    <div class="card-body {{ old('emite_nfce', $config->emite_nfce ?? false) ? '' : 'd-none' }}" id="nfce_campos">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Série NFC-e</label>
                                <input type="text" name="serie_nfce" class="form-control @error('serie_nfce') is-invalid @enderror"
                                       value="{{ old('serie_nfce', $config->serie_nfce ?? '1') }}" placeholder="1">
                                @error('serie_nfce')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">CSC (Código de Segurança)</label>
                                <input type="text" name="csc_nfce" class="form-control @error('csc_nfce') is-invalid @enderror"
                                       value="{{ old('csc_nfce', $config->csc_nfce) }}" placeholder="Obtido na SEFAZ do seu estado">
                                @error('csc_nfce')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">ID CSC</label>
                                <input type="text" name="csc_id_nfce" class="form-control @error('csc_id_nfce') is-invalid @enderror"
                                       value="{{ old('csc_id_nfce', $config->csc_id_nfce) }}" placeholder="1">
                                @error('csc_id_nfce')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            Para usar NFC-e no PDV, selecione "NFC-e (Cupom Fiscal)" na opção acima.
                        </small>
                    </div>
                </div>

                {{-- ═══ NFS-e ═══ --}}
                <div class="erp-card mb-3 border">
                    <div class="card-header bg-transparent d-flex align-items-center">
                        <i class="bi bi-briefcase fs-4 text-info me-2"></i>
                        <div class="flex-grow-1">
                            <strong>NFS-e (Serviços)</strong>
                            <div class="small text-muted">Nota fiscal eletrônica de serviços (emitida pela prefeitura)</div>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input type="hidden" name="emite_nfse" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="switch_nfse"
                                   name="emite_nfse" value="1"
                                   {{ old('emite_nfse', $config->emite_nfse ?? false) ? 'checked' : '' }}
                                   onchange="document.getElementById('nfse_campos').classList.toggle('d-none', !this.checked)">
                            <label class="form-check-label small" for="switch_nfse">Habilitar</label>
                        </div>
                    </div>
                    <div class="card-body {{ old('emite_nfse', $config->emite_nfse ?? false) ? '' : 'd-none' }}" id="nfse_campos">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Série RPS</label>
                                <input type="text" name="serie_nfse" class="form-control @error('serie_nfse') is-invalid @enderror"
                                       value="{{ old('serie_nfse', $config->serie_nfse ?? '1') }}" placeholder="1">
                                @error('serie_nfse')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Item LC 116</label>
                                <input type="text" name="nfse_item_lista_servico" class="form-control"
                                       value="{{ old('nfse_item_lista_servico', $config->nfse_item_lista_servico) }}" placeholder="01.01">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Código de Tributação Municipal</label>
                                <input type="text" name="nfse_codigo_tributacao" class="form-control"
                                       value="{{ old('nfse_codigo_tributacao', $config->nfse_codigo_tributacao) }}" placeholder="Conforme prefeitura">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Regime Especial</label>
                                <select name="nfse_regime_especial" class="form-select">
                                    <option value="">Nenhum</option>
                                    @foreach(['microempresa_municipal' => 'Microempresa Municipal', 'estimativa' => 'Estimativa', 'sociedade_profissionais' => 'Sociedade de Profissionais', 'cooperativa' => 'Cooperativa', 'mei' => 'MEI', 'me_epp' => 'ME / EPP'] as $v => $l)
                                        <option value="{{ $v }}" {{ old('nfse_regime_especial', $config->nfse_regime_especial) === $v ? 'selected' : '' }}>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check">
                                    <input type="hidden" name="nfse_incentivador_cultural" value="0">
                                    <input type="checkbox" name="nfse_incentivador_cultural" value="1" id="nfse_incent" class="form-check-input"
                                           {{ old('nfse_incentivador_cultural', $config->nfse_incentivador_cultural ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="nfse_incent">Incentivador Cultural</label>
                                </div>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            Cada prefeitura tem regras próprias. Item LC 116 e código de tributação devem ser obtidos na sua prefeitura.
                        </small>
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
