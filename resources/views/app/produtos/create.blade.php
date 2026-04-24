@extends('layouts.app')

@section('title', 'Novo Produto')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0"><i class="bi bi-plus-square me-2"></i>Novo Produto</h4>
        <small class="text-muted">Cadastre um novo produto no catalogo</small>
    </div>
    <a href="{{ route('app.produtos.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

{{-- Wizard Progress --}}
<div class="wizard-progress mb-4">
    <div class="wizard-progress-bar bg-white rounded shadow-sm p-3">
        <div class="d-flex justify-content-between align-items-center position-relative">
            <div class="wizard-progress-line"></div>
            <div class="wizard-progress-fill" id="progressFill"></div>
            <div class="wizard-progress-step active" data-step="1">
                <div class="wizard-progress-circle">
                    <i class="bi bi-box-seam"></i>
                </div>
                <span class="wizard-progress-label">O que voce vende?</span>
            </div>
            <div class="wizard-progress-step" data-step="2">
                <div class="wizard-progress-circle">
                    <i class="bi bi-upc-scan"></i>
                </div>
                <span class="wizard-progress-label">Identificacao</span>
            </div>
            <div class="wizard-progress-step" data-step="3">
                <div class="wizard-progress-circle">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <span class="wizard-progress-label">Dados Fiscais</span>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('app.produtos.store') }}" enctype="multipart/form-data" id="formProduto" novalidate>
    @csrf

    {{-- ============================================================ --}}
    {{-- STEP 1: O que voce vende? --}}
    {{-- ============================================================ --}}
    <div class="wizard-step active" id="wizardStep1">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <div class="wizard-step-icon bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width:56px;height:56px;">
                        <i class="bi bi-box-seam fs-4"></i>
                    </div>
                    <h5 class="mt-3 mb-1">O que voce vende?</h5>
                    <p class="text-muted mb-0">Preencha o basico para comecar a vender</p>
                </div>

                <div class="row g-3">
                    {{-- Descricao --}}
                    <div class="col-12">
                        <label for="descricao" class="form-label fw-semibold">Nome do produto <span class="text-danger">*</span></label>
                        <input type="text" name="descricao" id="descricao" class="form-control form-control-lg @error('descricao') is-invalid @enderror" value="{{ old('descricao') }}" placeholder="Ex: Camiseta Polo Azul M" required autofocus>
                        @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Descricao Detalhada --}}
                    <div class="col-12">
                        <label for="descricao_detalhada" class="form-label">Descricao detalhada <span class="text-muted fw-normal">(opcional)</span></label>
                        <textarea name="descricao_detalhada" id="descricao_detalhada" class="form-control @error('descricao_detalhada') is-invalid @enderror" rows="2" placeholder="Detalhes adicionais do produto">{{ old('descricao_detalhada') }}</textarea>
                        @error('descricao_detalhada') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Categoria --}}
                    <div class="col-md-6">
                        <label for="categoria_id" class="form-label">Categoria</label>
                        <select name="categoria_id" id="categoria_id" class="form-select @error('categoria_id') is-invalid @enderror">
                            <option value="">Sem categoria</option>
                            @foreach($categorias as $cat)
                                <option value="{{ $cat->id }}" {{ old('categoria_id') == $cat->id ? 'selected' : '' }}>{{ $cat->nome }}</option>
                            @endforeach
                        </select>
                        @error('categoria_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Unidade de Medida (Visual Cards) --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Unidade de medida <span class="text-danger">*</span></label>
                        <input type="hidden" name="unidade_medida" id="unidade_medida" value="{{ old('unidade_medida', '') }}" required>
                        <div class="d-flex flex-wrap gap-2" id="unidadeCards">
                            @foreach(['UN' => 'Unidade', 'KG' => 'Quilo', 'CX' => 'Caixa', 'PCT' => 'Pacote', 'LT' => 'Litro', 'MT' => 'Metro', 'M2' => 'M2', 'M3' => 'M3', 'PAR' => 'Par', 'JG' => 'Jogo'] as $val => $label)
                                <div class="unidade-card {{ old('unidade_medida') === $val ? 'selected' : '' }}" data-value="{{ $val }}">
                                    <strong>{{ $val }}</strong>
                                    <small>{{ $label }}</small>
                                </div>
                            @endforeach
                        </div>
                        @error('unidade_medida') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    {{-- Preco de Venda --}}
                    <div class="col-md-6">
                        <label for="preco_venda" class="form-label fw-semibold">Preco de venda <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text text-success fw-bold">R$</span>
                            <input type="number" name="preco_venda" id="preco_venda" class="form-control form-control-lg fw-bold @error('preco_venda') is-invalid @enderror" value="{{ old('preco_venda', '0.00') }}" step="0.01" min="0" required>
                            @error('preco_venda') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    {{-- Preco de Custo + Markup --}}
                    <div class="col-md-3">
                        <label for="preco_custo" class="form-label">Preco de custo <span class="text-muted fw-normal">(opcional)</span></label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" name="preco_custo" id="preco_custo" class="form-control @error('preco_custo') is-invalid @enderror" value="{{ old('preco_custo', '0.00') }}" step="0.01" min="0">
                            @error('preco_custo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted">Markup</label>
                        <div class="input-group">
                            <input type="number" name="markup" id="markup" class="form-control bg-light" value="{{ old('markup', '0.00') }}" step="0.01" min="0" readonly>
                            <span class="input-group-text">%</span>
                        </div>
                    </div>

                    {{-- Quick Save CTA --}}
                    <div class="col-12 mt-4">
                        <div class="alert alert-light border d-flex align-items-center justify-content-between mb-0">
                            <div>
                                <i class="bi bi-lightning-charge text-warning me-2"></i>
                                <span class="text-muted">E so isso que preciso pra comecar a vender</span>
                            </div>
                            <button type="submit" class="btn btn-success btn-sm" name="quick_save" value="1">
                                <i class="bi bi-check-lg me-1"></i> Salvar e Pronto
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-3 mb-4">
            <a href="{{ route('app.produtos.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg me-1"></i> Cancelar
            </a>
            <button type="button" class="btn btn-primary wizard-next" data-next="2">
                Avançar <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- STEP 2: Identificacao --}}
    {{-- ============================================================ --}}
    <div class="wizard-step" id="wizardStep2">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <div class="wizard-step-icon bg-info bg-opacity-10 text-info rounded-circle d-inline-flex align-items-center justify-content-center" style="width:56px;height:56px;">
                        <i class="bi bi-upc-scan fs-4"></i>
                    </div>
                    <h5 class="mt-3 mb-1">Identificacao</h5>
                    <p class="text-muted mb-0">Opcional, mas ajuda no controle do dia a dia</p>
                </div>

                <div class="row g-3">
                    {{-- Codigo de Barras --}}
                    <div class="col-md-6">
                        <label for="codigo_barras" class="form-label">
                            <i class="bi bi-upc me-1"></i> Código de barras <span class="text-muted fw-normal">(opcional)</span>
                        </label>
                        <input type="text" name="codigo_barras" id="codigo_barras" class="form-control @error('codigo_barras') is-invalid @enderror" value="{{ old('codigo_barras') }}" placeholder="Escaneie ou digite o EAN — deixe vazio se não tem">
                        @error('codigo_barras') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>
                            Se o produto já tem EAN impresso (industrializados), escaneie com o leitor.
                            Se não tem, deixe em branco — você poderá gerar etiquetas com o código interno
                            pelo menu <strong>Produtos → Etiquetas</strong>.
                        </div>
                    </div>

                    {{-- SKU --}}
                    <div class="col-md-6">
                        <label for="sku" class="form-label">
                            <i class="bi bi-hash me-1"></i> SKU
                        </label>
                        <input type="text" name="sku" id="sku" class="form-control @error('sku') is-invalid @enderror" value="{{ old('sku') }}" placeholder="Ex: CAM-AZU-M">
                        @error('sku') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text"><i class="bi bi-info-circle me-1"></i>Codigo interno para seu controle. Opcional.</div>
                    </div>

                    {{-- Foto --}}
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="bi bi-camera me-1"></i> Foto do produto
                        </label>
                        <input type="file" name="foto" id="foto" class="form-control @error('foto') is-invalid @enderror" accept="image/*">
                        @error('foto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">JPG, PNG ou WEBP. Max 2MB.</div>
                        <div id="fotoPreview" class="mt-2" style="display:none;">
                            <img id="fotoPreviewImg" class="rounded border" style="max-height:120px;" alt="Preview">
                        </div>
                    </div>

                    {{-- Estoque Minimo --}}
                    <div class="col-md-6">
                        <label for="estoque_minimo" class="form-label">
                            <i class="bi bi-bell me-1"></i> Estoque minimo
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-exclamation-triangle"></i></span>
                            <input type="number" name="estoque_minimo" id="estoque_minimo" class="form-control @error('estoque_minimo') is-invalid @enderror" value="{{ old('estoque_minimo', '0') }}" step="0.001" min="0">
                            @error('estoque_minimo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-text"><i class="bi bi-info-circle me-1"></i>Te avisamos quando o estoque ficar abaixo desse numero</div>
                    </div>

                    {{-- Peso Bruto / Liquido --}}
                    <div class="col-md-6">
                        <label for="peso_bruto" class="form-label">Peso bruto (kg)</label>
                        <div class="input-group">
                            <input type="number" name="peso_bruto" id="peso_bruto" class="form-control @error('peso_bruto') is-invalid @enderror" value="{{ old('peso_bruto', '0.000') }}" step="0.001" min="0">
                            <span class="input-group-text">kg</span>
                            @error('peso_bruto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="peso_liquido" class="form-label">Peso liquido (kg)</label>
                        <div class="input-group">
                            <input type="number" name="peso_liquido" id="peso_liquido" class="form-control @error('peso_liquido') is-invalid @enderror" value="{{ old('peso_liquido', '0.000') }}" step="0.001" min="0">
                            <span class="input-group-text">kg</span>
                            @error('peso_liquido') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-3 mb-4">
            <button type="button" class="btn btn-outline-secondary wizard-prev" data-prev="1">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </button>
            <button type="button" class="btn btn-primary wizard-next" data-next="3">
                Avançar <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- STEP 3: Dados Fiscais --}}
    {{-- ============================================================ --}}
    @php
        $empresa = auth()->user()->empresa;
        $unidadeId = session('unidade_id');
        $configFiscal = $unidadeId ? \App\Models\ConfiguracaoFiscal::withoutGlobalScopes()->where('unidade_id', $unidadeId)->first() : null;
        $emissaoAtiva = $configFiscal && $configFiscal->emissao_fiscal_ativa;
        $emiteNFe = $emissaoAtiva && $configFiscal->emite_nfe;
        $emiteNFCe = $emissaoAtiva && ($configFiscal->emite_nfce ?? $configFiscal->tipo_cupom_pdv === 'fiscal');
        // Se emissão está ativa, mostra os campos fiscais — mesmo que nenhum tipo específico
        // esteja marcado ainda (evita ter que reeditar produtos depois).
        $emiteFiscal = $emissaoAtiva;
        $nenhumTipoMarcado = $emissaoAtiva && ! $emiteNFe && ! $emiteNFCe;
        $regimeValue = $empresa->regime_tributario instanceof \App\Enums\RegimeTributario ? $empresa->regime_tributario->value : $empresa->regime_tributario;
        $isSimples = $regimeValue === 'simples_nacional';
    @endphp

    <div class="wizard-step" id="wizardStep3">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <div class="wizard-step-icon bg-warning bg-opacity-10 text-warning rounded-circle d-inline-flex align-items-center justify-content-center" style="width:56px;height:56px;">
                        <i class="bi bi-file-earmark-text fs-4"></i>
                    </div>
                    <h5 class="mt-3 mb-1">Dados Fiscais</h5>
                    <p class="text-muted mb-0">Necessarios para emissao de nota fiscal</p>
                </div>

                @if(!$emiteFiscal)
                    {{-- Empresa NAO emite nota --}}
                    <div class="text-center py-4">
                        <div class="mb-3">
                            <i class="bi bi-shield-check text-success" style="font-size:3rem;"></i>
                        </div>
                        <h6 class="mb-2">Sua empresa nao emite nota fiscal</h6>
                        <p class="text-muted mb-4">Voce pode pular esta etapa. Se no futuro precisar emitir notas, basta ativar a emissao fiscal nas configuracoes e editar o produto.</p>
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-check-lg me-1"></i> Salvar Produto
                        </button>
                    </div>
                @else
                    {{-- Empresa EMITE nota --}}
                    @if($nenhumTipoMarcado)
                        <div class="alert alert-warning d-flex align-items-start mb-3">
                            <i class="bi bi-exclamation-triangle me-2 fs-5 mt-1"></i>
                            <div>
                                <strong>Você ativou a emissão fiscal, mas ainda não escolheu o tipo (NF-e ou NFC-e).</strong><br>
                                <small>Continue preenchendo os dados fiscais do produto — quando escolher o tipo em
                                    <a href="{{ route('app.configuracao-fiscal.edit') }}" target="_blank">Configurações Fiscais</a>,
                                    este produto já estará pronto.</small>
                            </div>
                        </div>
                    @else
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="text-muted small">Campos usados na emissão de:</span>
                            @if($emiteNFe)
                                <span class="badge bg-primary"><i class="bi bi-file-earmark-text me-1"></i>NF-e</span>
                            @endif
                            @if($emiteNFCe)
                                <span class="badge bg-info"><i class="bi bi-receipt me-1"></i>NFC-e</span>
                            @endif
                        </div>
                    @endif

                    @if(!empty($fiscalDefaults['label']))
                    <div class="alert alert-info d-flex align-items-start mb-4">
                        <i class="bi bi-magic me-2 fs-5 mt-1"></i>
                        <div>
                            <strong>Preenchemos os dados fiscais baseado no regime {{ str_replace('_', ' ', $regimeValue) }}.</strong><br>
                            <small class="text-muted">{{ $fiscalDefaults['help'] }} Revise se necessario.</small>
                        </div>
                    </div>
                    @endif

                    <div class="row g-3">
                        {{-- NCM --}}
                        <div class="col-md-4">
                            <label for="ncm_busca" class="form-label fw-semibold">
                                NCM <x-erp.fiscal-tooltip field="ncm" />
                            </label>
                            <input type="text" id="ncm_busca" class="form-control" value="{{ old('ncm') }}"
                                   placeholder="Digite para buscar NCM (ex: leite, arroz, software)"
                                   data-autocomplete="{{ route('app.focus-autocomplete.ncm') }}"
                                   data-autocomplete-target="ncm"
                                   data-autocomplete-display="descricao"
                                   data-autocomplete-value="codigo">
                            <input type="hidden" name="ncm" id="ncm" value="{{ old('ncm') }}">
                            @error('ncm') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            <div class="form-text">Classificação fiscal — ou digite manualmente</div>
                        </div>

                        {{-- CFOP --}}
                        <div class="col-md-4">
                            <label for="cfop" class="form-label fw-semibold">
                                CFOP <x-erp.fiscal-tooltip field="cfop" />
                            </label>
                            <select name="cfop" id="cfop" class="form-select @error('cfop') is-invalid @enderror">
                                <option value="">Selecione...</option>
                                @foreach($cfopOptions as $code => $label)
                                    <option value="{{ $code }}" {{ old('cfop', $fiscalDefaults['cfop_venda_interna'] ?? '') == $code ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('cfop') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- CST/CSOSN --}}
                        <div class="col-md-4">
                            @if($isSimples)
                            <label for="cst_csosn" class="form-label fw-semibold">
                                CSOSN <x-erp.fiscal-tooltip field="csosn" />
                            </label>
                            <select name="cst_csosn" id="cst_csosn" class="form-select @error('cst_csosn') is-invalid @enderror">
                                <option value="">Selecione...</option>
                                <option value="102" {{ old('cst_csosn', $fiscalDefaults['cst_csosn'] ?? '') == '102' ? 'selected' : '' }}>102 - Tributada sem credito</option>
                                <option value="103" {{ old('cst_csosn', $fiscalDefaults['cst_csosn'] ?? '') == '103' ? 'selected' : '' }}>103 - Isenta de ICMS</option>
                                <option value="300" {{ old('cst_csosn', $fiscalDefaults['cst_csosn'] ?? '') == '300' ? 'selected' : '' }}>300 - Imune</option>
                                <option value="400" {{ old('cst_csosn', $fiscalDefaults['cst_csosn'] ?? '') == '400' ? 'selected' : '' }}>400 - Nao tributada</option>
                                <option value="500" {{ old('cst_csosn', $fiscalDefaults['cst_csosn'] ?? '') == '500' ? 'selected' : '' }}>500 - ICMS cobrado por ST</option>
                            </select>
                            @else
                            <label for="cst_csosn" class="form-label fw-semibold">
                                CST <x-erp.fiscal-tooltip field="cst" />
                            </label>
                            <select name="cst_csosn" id="cst_csosn" class="form-select @error('cst_csosn') is-invalid @enderror">
                                <option value="">Selecione...</option>
                                <option value="00" {{ old('cst_csosn', $fiscalDefaults['cst_csosn'] ?? '') == '00' ? 'selected' : '' }}>00 - Tributada integralmente</option>
                                <option value="10" {{ old('cst_csosn', $fiscalDefaults['cst_csosn'] ?? '') == '10' ? 'selected' : '' }}>10 - Tributada com ST</option>
                                <option value="20" {{ old('cst_csosn', $fiscalDefaults['cst_csosn'] ?? '') == '20' ? 'selected' : '' }}>20 - Com reducao de base</option>
                                <option value="40" {{ old('cst_csosn', $fiscalDefaults['cst_csosn'] ?? '') == '40' ? 'selected' : '' }}>40 - Isenta</option>
                                <option value="41" {{ old('cst_csosn', $fiscalDefaults['cst_csosn'] ?? '') == '41' ? 'selected' : '' }}>41 - Nao tributada</option>
                                <option value="60" {{ old('cst_csosn', $fiscalDefaults['cst_csosn'] ?? '') == '60' ? 'selected' : '' }}>60 - ICMS cobrado por ST anterior</option>
                            </select>
                            @endif
                            @error('cst_csosn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- Aliquotas em Cards --}}
                        <div class="col-12 mt-4">
                            <label class="form-label fw-semibold mb-3">
                                <i class="bi bi-percent me-1"></i> Aliquotas de impostos
                                <i class="bi bi-question-circle text-muted ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Percentuais de impostos que incidem sobre o produto. Se voce e do Simples Nacional, normalmente ficam zerados."></i>
                            </label>
                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <div class="aliquota-card aliquota-icms">
                                        <div class="aliquota-card-header">ICMS <x-erp.fiscal-tooltip field="icms" /></div>
                                        <div class="aliquota-card-body">
                                            <div class="input-group">
                                                <input type="number" name="icms_aliquota" id="icms_aliquota" class="form-control text-center fw-bold @error('icms_aliquota') is-invalid @enderror" value="{{ old('icms_aliquota', $fiscalDefaults['icms_aliquota'] ?? '0.00') }}" step="0.01" min="0" max="100">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                        <div class="aliquota-card-footer">
                                            <small>Pre-configurado</small>
                                        </div>
                                    </div>
                                    @error('icms_aliquota') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="aliquota-card aliquota-pis">
                                        <div class="aliquota-card-header">PIS <x-erp.fiscal-tooltip field="pis" /></div>
                                        <div class="aliquota-card-body">
                                            <div class="input-group">
                                                <input type="number" name="pis_aliquota" id="pis_aliquota" class="form-control text-center fw-bold @error('pis_aliquota') is-invalid @enderror" value="{{ old('pis_aliquota', $fiscalDefaults['pis_aliquota'] ?? '0.00') }}" step="0.01" min="0" max="100">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                        <div class="aliquota-card-footer">
                                            <small>Pre-configurado</small>
                                        </div>
                                    </div>
                                    @error('pis_aliquota') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="aliquota-card aliquota-cofins">
                                        <div class="aliquota-card-header">COFINS <x-erp.fiscal-tooltip field="cofins" /></div>
                                        <div class="aliquota-card-body">
                                            <div class="input-group">
                                                <input type="number" name="cofins_aliquota" id="cofins_aliquota" class="form-control text-center fw-bold @error('cofins_aliquota') is-invalid @enderror" value="{{ old('cofins_aliquota', $fiscalDefaults['cofins_aliquota'] ?? '0.00') }}" step="0.01" min="0" max="100">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                        <div class="aliquota-card-footer">
                                            <small>Pre-configurado</small>
                                        </div>
                                    </div>
                                    @error('cofins_aliquota') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="aliquota-card aliquota-ipi">
                                        <div class="aliquota-card-header">IPI <x-erp.fiscal-tooltip field="ipi" /></div>
                                        <div class="aliquota-card-body">
                                            <div class="input-group">
                                                <input type="number" name="ipi_aliquota" id="ipi_aliquota" class="form-control text-center fw-bold @error('ipi_aliquota') is-invalid @enderror" value="{{ old('ipi_aliquota', $fiscalDefaults['ipi_aliquota'] ?? '0.00') }}" step="0.01" min="0" max="100">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                        <div class="aliquota-card-footer">
                                            <small>Pre-configurado</small>
                                        </div>
                                    </div>
                                    @error('ipi_aliquota') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Reforma Tributária (IBS/CBS/IS) — aparece apenas quando a unidade habilitou --}}
                        @if(($configFiscal->ibs_ativo ?? false) || ($configFiscal->cbs_ativo ?? false) || ($configFiscal->is_ativo ?? false))
                            <div class="col-12 mt-3">
                                <div class="alert alert-warning small d-flex mb-3">
                                    <i class="bi bi-stars me-2 fs-5"></i>
                                    <div>
                                        <strong>Reforma Tributária (EC 132/2023)</strong> — preencha se esse item tem alíquota específica.
                                        Em branco, usa a alíquota padrão da unidade.
                                    </div>
                                </div>
                                <div class="row g-3">
                                    @if($configFiscal->ibs_ativo ?? false)
                                        <div class="col-md-3">
                                            <label class="form-label">IBS (%)</label>
                                            <input type="number" name="ibs_aliquota" step="0.0001" min="0" max="100" class="form-control" value="{{ old('ibs_aliquota') }}">
                                        </div>
                                    @endif
                                    @if($configFiscal->cbs_ativo ?? false)
                                        <div class="col-md-3">
                                            <label class="form-label">CBS (%)</label>
                                            <input type="number" name="cbs_aliquota" step="0.0001" min="0" max="100" class="form-control" value="{{ old('cbs_aliquota') }}">
                                        </div>
                                    @endif
                                    @if($configFiscal->is_ativo ?? false)
                                        <div class="col-md-3">
                                            <label class="form-label">IS (%) <small class="text-muted">seletivo</small></label>
                                            <input type="number" name="is_aliquota" step="0.0001" min="0" max="100" class="form-control" value="{{ old('is_aliquota') }}">
                                        </div>
                                    @endif
                                    <div class="col-md-3">
                                        <label class="form-label">CST IBS/CBS</label>
                                        <input type="text" name="cst_ibs_cbs" maxlength="3" class="form-control" placeholder="000" value="{{ old('cst_ibs_cbs') }}">
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Origem --}}
                        <div class="col-md-6">
                            <label for="origem" class="form-label fw-semibold">
                                Origem do produto <x-erp.fiscal-tooltip field="origem" />
                            </label>
                            <select name="origem" id="origem" class="form-select @error('origem') is-invalid @enderror">
                                <option value="">Selecione a origem</option>
                                @foreach($origemOptions as $code => $label)
                                    <option value="{{ $code }}" {{ old('origem', $fiscalDefaults['origem'] ?? '') == (string)$code && old('origem', $fiscalDefaults['origem'] ?? '') !== '' ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('origem') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{-- CEST --}}
                        <div class="col-md-6">
                            <label for="cest" class="form-label">
                                CEST <span class="text-muted fw-normal">(opcional)</span>
                                <x-erp.fiscal-tooltip field="cest" />
                            </label>
                            <input type="text" name="cest" id="cest" class="form-control @error('cest') is-invalid @enderror" value="{{ old('cest') }}" maxlength="10" placeholder="00.000.00">
                            @error('cest') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="d-flex justify-content-between mt-3 mb-4">
            <button type="button" class="btn btn-outline-secondary wizard-prev" data-prev="2">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </button>
            <div class="d-flex gap-2">
                @if($emiteFiscal)
                <button type="submit" class="btn btn-outline-success" name="skip_fiscal" value="1">
                    <i class="bi bi-fast-forward me-1"></i> Pular e Salvar
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg me-1"></i> Salvar Produto
                </button>
                @endif
            </div>
        </div>
    </div>
</form>

{{-- ============================================================ --}}
{{-- STYLES --}}
{{-- ============================================================ --}}
@push('styles')
<style>
/* Wizard Progress Bar */
.wizard-progress-bar {
    position: relative;
}
.wizard-progress-bar .d-flex {
    z-index: 1;
}
.wizard-progress-line {
    position: absolute;
    top: 20px;
    left: 15%;
    right: 15%;
    height: 3px;
    background: #e9ecef;
    z-index: 0;
}
.wizard-progress-fill {
    position: absolute;
    top: 20px;
    left: 15%;
    height: 3px;
    background: #0d6efd;
    z-index: 0;
    width: 0%;
    transition: width 0.4s ease;
}
.wizard-progress-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    z-index: 1;
    flex: 1;
}
.wizard-progress-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e9ecef;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    transition: all 0.3s ease;
    border: 3px solid #e9ecef;
}
.wizard-progress-step.active .wizard-progress-circle,
.wizard-progress-step.completed .wizard-progress-circle {
    background: #0d6efd;
    color: #fff;
    border-color: #0d6efd;
}
.wizard-progress-step.completed .wizard-progress-circle {
    background: #198754;
    border-color: #198754;
}
.wizard-progress-label {
    font-size: 0.75rem;
    color: #6c757d;
    margin-top: 6px;
    text-align: center;
    font-weight: 500;
}
.wizard-progress-step.active .wizard-progress-label {
    color: #0d6efd;
    font-weight: 600;
}
.wizard-progress-step.completed .wizard-progress-label {
    color: #198754;
}

/* Wizard Steps */
.wizard-step {
    display: none;
    animation: wizardFadeIn 0.3s ease;
}
.wizard-step.active {
    display: block;
}
@keyframes wizardFadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Unidade de Medida Cards */
.unidade-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 8px 14px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 70px;
    user-select: none;
}
.unidade-card:hover {
    border-color: #0d6efd;
    background: #f0f4ff;
}
.unidade-card.selected {
    border-color: #0d6efd;
    background: #0d6efd;
    color: #fff;
}
.unidade-card.selected small {
    color: rgba(255,255,255,0.85);
}
.unidade-card strong {
    font-size: 0.9rem;
    line-height: 1;
}
.unidade-card small {
    font-size: 0.65rem;
    color: #6c757d;
    line-height: 1;
    margin-top: 2px;
}

/* Aliquota Cards */
.aliquota-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    overflow: hidden;
    transition: box-shadow 0.2s ease;
}
.aliquota-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.aliquota-card-header {
    padding: 6px 12px;
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #fff;
    text-align: center;
}
.aliquota-card-body {
    padding: 12px;
}
.aliquota-card-body .form-control {
    font-size: 1.1rem;
}
.aliquota-card-footer {
    padding: 4px 12px;
    background: #f8f9fa;
    text-align: center;
    color: #6c757d;
}
.aliquota-icms .aliquota-card-header { background: #0d6efd; }
.aliquota-pis .aliquota-card-header { background: #6f42c1; }
.aliquota-cofins .aliquota-card-header { background: #d63384; }
.aliquota-ipi .aliquota-card-header { background: #fd7e14; }
</style>
@endpush

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ========================================
    // Wizard Navigation
    // ========================================
    let currentStep = 1;
    const totalSteps = 3;

    function showStep(step) {
        document.querySelectorAll('.wizard-step').forEach(el => el.classList.remove('active'));
        document.getElementById('wizardStep' + step).classList.add('active');

        // Update progress
        document.querySelectorAll('.wizard-progress-step').forEach(el => {
            const s = parseInt(el.dataset.step);
            el.classList.remove('active', 'completed');
            if (s === step) el.classList.add('active');
            else if (s < step) el.classList.add('completed');
        });

        // Update progress fill line
        const fillPercent = ((step - 1) / (totalSteps - 1)) * 70; // 70% is the line width
        document.getElementById('progressFill').style.width = fillPercent + '%';

        currentStep = step;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function validateStep(step) {
        const stepEl = document.getElementById('wizardStep' + step);
        const requiredFields = stepEl.querySelectorAll('[required]');
        let valid = true;

        requiredFields.forEach(field => {
            if (!field.value || field.value.trim() === '') {
                field.classList.add('is-invalid');
                valid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });

        // Step 1: check unidade_medida
        if (step === 1) {
            const unidadeInput = document.getElementById('unidade_medida');
            if (!unidadeInput.value) {
                document.getElementById('unidadeCards').style.outline = '2px solid #dc3545';
                document.getElementById('unidadeCards').style.borderRadius = '8px';
                valid = false;
            } else {
                document.getElementById('unidadeCards').style.outline = '';
            }
        }

        return valid;
    }

    // Next buttons
    document.querySelectorAll('.wizard-next').forEach(btn => {
        btn.addEventListener('click', function () {
            const nextStep = parseInt(this.dataset.next);
            if (validateStep(currentStep)) {
                showStep(nextStep);
            }
        });
    });

    // Prev buttons
    document.querySelectorAll('.wizard-prev').forEach(btn => {
        btn.addEventListener('click', function () {
            showStep(parseInt(this.dataset.prev));
        });
    });

    // ========================================
    // Unidade de Medida Cards
    // ========================================
    document.querySelectorAll('.unidade-card').forEach(card => {
        card.addEventListener('click', function () {
            document.querySelectorAll('.unidade-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            document.getElementById('unidade_medida').value = this.dataset.value;
            document.getElementById('unidadeCards').style.outline = '';
        });
    });

    // ========================================
    // Pricing / Markup
    // ========================================
    const precoCusto = document.getElementById('preco_custo');
    const markup = document.getElementById('markup');
    const precoVenda = document.getElementById('preco_venda');

    function calcMarkup() {
        const custo = parseFloat(precoCusto.value) || 0;
        const venda = parseFloat(precoVenda.value) || 0;
        if (custo > 0 && venda > custo) {
            markup.value = (((venda - custo) / custo) * 100).toFixed(2);
        } else {
            markup.value = '0.00';
        }
    }

    precoCusto.addEventListener('input', calcMarkup);
    precoVenda.addEventListener('input', calcMarkup);
    calcMarkup();

    // ========================================
    // Foto Preview
    // ========================================
    const fotoInput = document.getElementById('foto');
    const fotoPreview = document.getElementById('fotoPreview');
    const fotoPreviewImg = document.getElementById('fotoPreviewImg');

    if (fotoInput) {
        fotoInput.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    fotoPreviewImg.src = e.target.result;
                    fotoPreview.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            } else {
                fotoPreview.style.display = 'none';
            }
        });
    }

    // ========================================
    // NCM Mask: 0000.00.00
    // ========================================
    const ncmInput = document.getElementById('ncm');
    if (ncmInput) {
        ncmInput.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '').substring(0, 8);
            if (v.length > 4) v = v.substring(0, 4) + '.' + v.substring(4);
            if (v.length > 7) v = v.substring(0, 7) + '.' + v.substring(7);
            this.value = v;
        });
    }

    // ========================================
    // CEST Mask: 00.000.00
    // ========================================
    const cestInput = document.getElementById('cest');
    if (cestInput) {
        cestInput.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '').substring(0, 7);
            if (v.length > 2) v = v.substring(0, 2) + '.' + v.substring(2);
            if (v.length > 6) v = v.substring(0, 6) + '.' + v.substring(6);
            this.value = v;
        });
    }

    // ========================================
    // Bootstrap Tooltips
    // ========================================
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });

    // ========================================
    // Show step with validation errors
    // ========================================
    @if($errors->any())
        const errorFields = {!! json_encode($errors->keys()) !!};
        const step2Fields = ['codigo_barras', 'sku', 'foto', 'estoque_minimo', 'peso_bruto', 'peso_liquido'];
        const step3Fields = ['ncm', 'cest', 'origem', 'cfop', 'cst_csosn', 'icms_aliquota', 'pis_aliquota', 'cofins_aliquota', 'ipi_aliquota'];

        if (errorFields.some(f => step3Fields.includes(f))) {
            showStep(3);
        } else if (errorFields.some(f => step2Fields.includes(f))) {
            showStep(2);
        }
    @endif
});
</script>
@endpush
