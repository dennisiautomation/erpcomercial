@extends('admin.onboarding.layout', ['step' => 4])

@section('title', 'Onboarding - Configuracao Fiscal')

@push('styles')
<style>
    .fiscal-toggle {
        cursor: pointer;
        border: 2px solid #dee2e6;
        border-radius: 0.75rem;
        padding: 1.5rem;
        transition: all 0.2s ease;
        text-align: center;
    }
    .fiscal-toggle:hover {
        border-color: var(--accent);
    }
    .fiscal-toggle.active {
        border-color: var(--accent);
        background: rgba(99, 102, 241, 0.05);
    }
    .fiscal-toggle i {
        font-size: 2rem;
        display: block;
        margin-bottom: 0.5rem;
    }
    .fiscal-config {
        display: none;
    }
    .fiscal-config.show {
        display: block;
    }
    .doc-check {
        border: 2px solid #dee2e6;
        border-radius: 0.5rem;
        padding: 1rem;
        transition: all 0.15s ease;
        cursor: pointer;
        height: 100%;
    }
    .doc-check:hover {
        border-color: var(--accent);
    }
    .doc-check input:checked + label {
        font-weight: 600;
    }
    .doc-check:has(input:checked) {
        border-color: var(--accent);
        background: rgba(99, 102, 241, 0.05);
    }
</style>
@endpush

@section('step-content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-receipt me-2"></i>Configuração Fiscal</h5>
                <p class="text-muted mb-0 small mt-1">Configure a emissão de notas fiscais (pode ser feito depois)</p>
            </div>
            <div class="card-body p-4">
                @if(session('onboarding_fiscal_erro'))
                    <div class="alert alert-warning">{{ session('onboarding_fiscal_erro') }}</div>
                @endif

                <form method="POST" action="{{ route('admin.onboarding.step4.store') }}" class="erp-form" id="form-fiscal">
                    @csrf

                    <input type="hidden" name="emite_fiscal" id="emite_fiscal" value="{{ old('emite_fiscal', '0') }}">

                    {{-- Toggle: Emite NF? --}}
                    <h6 class="fw-bold text-center mb-3">Sua empresa emite nota fiscal eletrônica?</h6>
                    <div class="row g-3 justify-content-center mb-4">
                        <div class="col-5 col-md-4">
                            <div class="fiscal-toggle {{ old('emite_fiscal') === '1' ? 'active' : '' }}" data-value="1" onclick="toggleFiscal(this)">
                                <i class="bi bi-check-circle text-success"></i>
                                <span class="fw-bold">SIM</span>
                                <small class="d-block text-muted">Emitir NF-e / NFC-e / NFS-e</small>
                            </div>
                        </div>
                        <div class="col-5 col-md-4">
                            <div class="fiscal-toggle {{ old('emite_fiscal', '0') === '0' ? 'active' : '' }}" data-value="0" onclick="toggleFiscal(this)">
                                <i class="bi bi-x-circle text-danger"></i>
                                <span class="fw-bold">NÃO</span>
                                <small class="d-block text-muted">Apenas recibos</small>
                            </div>
                        </div>
                    </div>

                    {{-- Fiscal config (hidden by default) --}}
                    <div class="fiscal-config {{ old('emite_fiscal') === '1' ? 'show' : '' }}" id="fiscal-fields">
                        <hr>

                        @if($modoRevenda)
                            {{-- MODO REVENDA: criação automática na Focus via master token --}}
                            <div class="alert alert-info small d-flex">
                                <i class="bi bi-shield-check me-2 fs-4"></i>
                                <div>
                                    <strong>Integração automática ativa.</strong>
                                    A empresa será criada na Focus NFe automaticamente.
                                    Você só precisa indicar o que vai emitir — os tokens são gerados pela plataforma.
                                    O certificado digital pode ser enviado depois, em Configurações Fiscais.
                                </div>
                            </div>

                            <h6 class="fw-semibold mt-3 mb-2">Quais documentos serão emitidos?</h6>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <div class="doc-check">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="emite_nfe" id="f_nfe"
                                                   value="1" {{ old('emite_nfe') ? 'checked' : '' }}>
                                            <label class="form-check-label w-100" for="f_nfe">
                                                <strong>NF-e</strong> — Nota Fiscal Eletrônica (venda para empresas e entrega)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="doc-check">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="emite_nfce" id="f_nfce"
                                                   value="1" {{ old('emite_nfce') ? 'checked' : '' }}>
                                            <label class="form-check-label w-100" for="f_nfce">
                                                <strong>NFC-e</strong> — Cupom fiscal do PDV (venda ao consumidor)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="doc-check">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="emite_nfse" id="f_nfse"
                                                   value="1" {{ old('emite_nfse') ? 'checked' : '' }}>
                                            <label class="form-check-label w-100" for="f_nfse">
                                                <strong>NFS-e</strong> — Nota de serviços (prestação de serviços)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="doc-check">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="habilita_manifestacao" id="f_manif"
                                                   value="1" {{ old('habilita_manifestacao') ? 'checked' : '' }}>
                                            <label class="form-check-label w-100" for="f_manif">
                                                <strong>Manifestação</strong> — Receber NFes emitidas contra seu CNPJ
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- MODO LEGADO: usuário cola token Focus manualmente --}}
                            <div class="alert alert-secondary small">
                                <i class="bi bi-info-circle me-1"></i>
                                A plataforma não está em modo revenda. Cole abaixo um token da conta Focus NFe existente.
                            </div>

                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label for="focus_token" class="form-label fw-semibold">Token Focus NFe <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('focus_token') is-invalid @enderror"
                                           id="focus_token" name="focus_token" value="{{ old('focus_token') }}"
                                           placeholder="Cole aqui o token da Focus NFe">
                                    @error('focus_token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="form-text">Token de autenticação da API Focus NFe</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Ambiente <span class="text-danger">*</span></label>
                                    <div class="d-flex gap-3 mt-1">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ambiente" id="amb_homologacao"
                                                   value="homologacao" {{ old('ambiente', 'homologacao') === 'homologacao' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="amb_homologacao">Homologação (testes)</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ambiente" id="amb_producao"
                                                   value="producao" {{ old('ambiente') === 'producao' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="amb_producao">Produção</label>
                                        </div>
                                    </div>
                                    @error('ambiente')<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        @endif

                        {{-- Tipo de cupom no PDV (comum aos dois modos) --}}
                        <div class="row g-3 mt-1">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Tipo de Cupom no PDV <span class="text-danger">*</span></label>
                                <div class="d-flex gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo_cupom_pdv" id="cupom_fiscal"
                                               value="fiscal" {{ old('tipo_cupom_pdv', 'fiscal') === 'fiscal' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="cupom_fiscal">NFC-e (fiscal)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo_cupom_pdv" id="cupom_nao_fiscal"
                                               value="nao_fiscal" {{ old('tipo_cupom_pdv') === 'nao_fiscal' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="cupom_nao_fiscal">Recibo (não fiscal)</label>
                                    </div>
                                </div>
                                @error('tipo_cupom_pdv')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <a href="{{ route('admin.onboarding.step3') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Voltar
                        </a>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Concluir Onboarding
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleFiscal(el) {
    document.querySelectorAll('.fiscal-toggle').forEach(t => t.classList.remove('active'));
    el.classList.add('active');

    const value = el.getAttribute('data-value');
    document.getElementById('emite_fiscal').value = value;

    const fields = document.getElementById('fiscal-fields');
    if (value === '1') {
        fields.classList.add('show');
    } else {
        fields.classList.remove('show');
    }
}
</script>
@endpush
