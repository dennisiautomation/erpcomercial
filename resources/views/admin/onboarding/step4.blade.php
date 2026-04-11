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
</style>
@endpush

@section('step-content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-receipt me-2"></i>Configuracao Fiscal</h5>
                <p class="text-muted mb-0 small mt-1">Configure a emissao de notas fiscais (pode ser feito depois)</p>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.onboarding.step4.store') }}" class="erp-form" id="form-fiscal">
                    @csrf

                    <input type="hidden" name="emite_fiscal" id="emite_fiscal" value="{{ old('emite_fiscal', '0') }}">

                    {{-- Toggle: Emite NF? --}}
                    <h6 class="fw-bold text-center mb-3">Sua empresa emite nota fiscal eletronica?</h6>
                    <div class="row g-3 justify-content-center mb-4">
                        <div class="col-5 col-md-4">
                            <div class="fiscal-toggle {{ old('emite_fiscal') === '1' ? 'active' : '' }}" data-value="1" onclick="toggleFiscal(this)">
                                <i class="bi bi-check-circle text-success"></i>
                                <span class="fw-bold">SIM</span>
                                <small class="d-block text-muted">Emitir NF-e / NFC-e</small>
                            </div>
                        </div>
                        <div class="col-5 col-md-4">
                            <div class="fiscal-toggle {{ old('emite_fiscal', '0') === '0' ? 'active' : '' }}" data-value="0" onclick="toggleFiscal(this)">
                                <i class="bi bi-x-circle text-danger"></i>
                                <span class="fw-bold">NAO</span>
                                <small class="d-block text-muted">Apenas recibos</small>
                            </div>
                        </div>
                    </div>

                    {{-- Fiscal config (hidden by default) --}}
                    <div class="fiscal-config {{ old('emite_fiscal') === '1' ? 'show' : '' }}" id="fiscal-fields">
                        <hr>
                        <div class="row g-3">
                            {{-- Token Focus NFe --}}
                            <div class="col-md-12">
                                <label for="focus_token" class="form-label fw-semibold">Token Focus NFe <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('focus_token') is-invalid @enderror"
                                       id="focus_token" name="focus_token" value="{{ old('focus_token') }}"
                                       placeholder="Cole aqui o token da Focus NFe">
                                @error('focus_token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Token de autenticacao da API Focus NFe</div>
                            </div>

                            {{-- Ambiente --}}
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Ambiente <span class="text-danger">*</span></label>
                                <div class="d-flex gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="ambiente" id="amb_homologacao"
                                               value="homologacao" {{ old('ambiente', 'homologacao') === 'homologacao' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="amb_homologacao">Homologacao (testes)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="ambiente" id="amb_producao"
                                               value="producao" {{ old('ambiente') === 'producao' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="amb_producao">Producao</label>
                                    </div>
                                </div>
                                @error('ambiente')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>

                            {{-- Tipo cupom PDV --}}
                            <div class="col-md-6">
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
                                        <label class="form-check-label" for="cupom_nao_fiscal">Recibo (nao fiscal)</label>
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
