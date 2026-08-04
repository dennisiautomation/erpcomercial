@extends('layouts.app')

@section('title', 'Editar Empresa')

@push('styles')
<style>
    .section-card {
        border: none;
        border-radius: 0.75rem;
    }
    .section-card .card-header {
        background: #fff;
        border-bottom: 1px solid #f1f5f9;
        padding: 0.875rem 1.25rem;
    }
    .section-card .card-header h6 {
        font-size: 0.9375rem;
        font-weight: 600;
    }
    .section-card .card-body {
        padding: 1.25rem;
    }
    .form-label {
        font-size: 0.8125rem;
        font-weight: 600;
        color: #475569;
        margin-bottom: 0.25rem;
    }
    .required-dot {
        color: #ef4444;
    }
    .btn-actions {
        position: sticky;
        bottom: 0;
        background: #f1f5f9;
        padding: 1rem 0;
        z-index: 10;
    }
    .empresa-header-badge {
        vertical-align: middle;
    }
    #cep-spinner {
        display: none;
    }
</style>
@endpush

@section('content')
{{-- Header --}}
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-1">
            <i class="bi bi-pencil-square me-2"></i>Editar Empresa
            <span class="badge bg-{{ $empresa->status->color() }} empresa-header-badge fs-6">{{ $empresa->status->label() }}</span>
        </h4>
        <p class="text-muted mb-0 small">{{ $empresa->razao_social }} - {{ $empresa->cnpj }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.empresas.show', $empresa) }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-eye me-1"></i> Visualizar
        </a>
        <a href="{{ route('admin.empresas.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

<form action="{{ route('admin.empresas.update', $empresa) }}" method="POST" enctype="multipart/form-data" id="form-empresa">
    @csrf
    @method('PUT')

    <div class="row g-4">
        <div class="col-lg-8">
            {{-- Dados da Empresa --}}
            <div class="card section-card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-building me-2 text-primary"></i>Dados da Empresa</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label for="cnpj" class="form-label">CNPJ <span class="required-dot">*</span></label>
                            <input type="text" class="form-control @error('cnpj') is-invalid @enderror"
                                   id="cnpj" name="cnpj" value="{{ old('cnpj', $empresa->cnpj) }}"
                                   placeholder="00.000.000/0000-00" data-mask="cnpj" data-cnpj-lookup="" required>
                            <span data-cnpj-loading class="d-none"><span class="spinner-border spinner-border-sm text-primary"></span> Consultando CNPJ...</span>
                            @error('cnpj')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-7">
                            <label for="razao_social" class="form-label">Razao Social <span class="required-dot">*</span></label>
                            <input type="text" class="form-control @error('razao_social') is-invalid @enderror"
                                   id="razao_social" name="razao_social" value="{{ old('razao_social', $empresa->razao_social) }}" required>
                            @error('razao_social')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-5">
                            <label for="nome_fantasia" class="form-label">Nome Fantasia</label>
                            <input type="text" class="form-control @error('nome_fantasia') is-invalid @enderror"
                                   id="nome_fantasia" name="nome_fantasia" value="{{ old('nome_fantasia', $empresa->nome_fantasia) }}">
                            @error('nome_fantasia')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label for="ie" class="form-label">Inscricao Estadual</label>
                            <input type="text" class="form-control @error('ie') is-invalid @enderror"
                                   id="ie" name="ie" value="{{ old('ie', $empresa->ie) }}">
                            @error('ie')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="im" class="form-label">Inscricao Municipal</label>
                            <input type="text" class="form-control @error('im') is-invalid @enderror"
                                   id="im" name="im" value="{{ old('im', $empresa->im) }}">
                            @error('im')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Endereco --}}
            <div class="card section-card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-geo-alt me-2 text-danger"></i>Endereco</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="cep" class="form-label">CEP</label>
                            <div class="input-group">
                                <input type="text" class="form-control @error('cep') is-invalid @enderror"
                                       id="cep" name="cep" value="{{ old('cep', $empresa->cep) }}"
                                       placeholder="00000-000" data-mask="cep">
                                <button type="button" class="btn btn-outline-secondary" id="btn-buscar-cep" title="Buscar CEP">
                                    <i class="bi bi-search"></i>
                                    <span class="spinner-border spinner-border-sm" id="cep-spinner"></span>
                                </button>
                                @error('cep')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="logradouro" class="form-label">Logradouro</label>
                            <input type="text" class="form-control @error('logradouro') is-invalid @enderror"
                                   id="logradouro" name="logradouro" value="{{ old('logradouro', $empresa->logradouro) }}">
                            @error('logradouro')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label for="numero" class="form-label">Numero</label>
                            <input type="text" class="form-control @error('numero') is-invalid @enderror"
                                   id="numero" name="numero" value="{{ old('numero', $empresa->numero) }}">
                            @error('numero')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="complemento" class="form-label">Complemento</label>
                            <input type="text" class="form-control @error('complemento') is-invalid @enderror"
                                   id="complemento" name="complemento" value="{{ old('complemento', $empresa->complemento) }}">
                            @error('complemento')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="bairro" class="form-label">Bairro</label>
                            <input type="text" class="form-control @error('bairro') is-invalid @enderror"
                                   id="bairro" name="bairro" value="{{ old('bairro', $empresa->bairro) }}">
                            @error('bairro')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="cidade" class="form-label">Cidade</label>
                            <input type="text" class="form-control @error('cidade') is-invalid @enderror"
                                   id="cidade" name="cidade" value="{{ old('cidade', $empresa->cidade) }}">
                            @error('cidade')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-2">
                            <label for="uf" class="form-label">UF</label>
                            <select class="form-select @error('uf') is-invalid @enderror" id="uf" name="uf">
                                <option value="">UF</option>
                                @foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $estado)
                                    <option value="{{ $estado }}" {{ old('uf', $empresa->uf) === $estado ? 'selected' : '' }}>{{ $estado }}</option>
                                @endforeach
                            </select>
                            @error('uf')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Contato --}}
            <div class="card section-card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-telephone me-2 text-success"></i>Contato</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label for="telefone" class="form-label">Telefone</label>
                            <input type="text" class="form-control @error('telefone') is-invalid @enderror"
                                   id="telefone" name="telefone" value="{{ old('telefone', $empresa->telefone) }}"
                                   placeholder="(00) 00000-0000" data-mask="telefone">
                            @error('telefone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-7">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror"
                                   id="email" name="email" value="{{ old('email', $empresa->email) }}">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            {{-- Regime, Plano e Status --}}
            <div class="card section-card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-gear me-2 text-secondary"></i>Configuracoes</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status <span class="required-dot">*</span></label>
                        <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                            @foreach(\App\Enums\StatusEmpresa::cases() as $statusOpt)
                                <option value="{{ $statusOpt->value }}"
                                    {{ old('status', $empresa->status->value) === $statusOpt->value ? 'selected' : '' }}>
                                    {{ $statusOpt->label() }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="regime_tributario" class="form-label">Regime Tributario <span class="required-dot">*</span></label>
                        <select class="form-select @error('regime_tributario') is-invalid @enderror"
                                id="regime_tributario" name="regime_tributario" required>
                            <option value="">Selecione...</option>
                            @foreach(\App\Enums\RegimeTributario::cases() as $regime)
                                <option value="{{ $regime->value }}" {{ old('regime_tributario', $empresa->regime_tributario?->value) === $regime->value ? 'selected' : '' }}>
                                    {{ $regime->label() }}
                                </option>
                            @endforeach
                        </select>
                        @error('regime_tributario')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label for="plano" class="form-label">Plano</label>
                        <select class="form-select @error('plano') is-invalid @enderror" id="plano" name="plano">
                            <option value="">Selecione...</option>
                            @foreach($planos as $planoItem)
                                <option value="{{ $planoItem->slug }}" {{ old('plano', $empresa->plano) === $planoItem->slug ? 'selected' : '' }}>
                                    {{ $planoItem->nome }} - R$ {{ number_format($planoItem->preco_mensal, 2, ',', '.') }}/mes
                                </option>
                            @endforeach
                        </select>
                        @error('plano')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Regime de cobrança --}}
            <div class="card section-card shadow-sm mb-4">
                <div class="card-header">
                    <i class="bi bi-credit-card-2-back me-2"></i>
                    <strong>Regime de cobrança</strong> — como a IA365 fatura esta empresa
                </div>
                <div class="card-body">
                    @php $regimeAtual = old('regime_cobranca', $empresa->regime_cobranca?->value ?? 'padrao'); @endphp
                    <div class="row g-3">
                        @foreach(\App\Enums\RegimeCobranca::cases() as $regime)
                            <div class="col-md-6 col-lg-3">
                                <input type="radio" class="btn-check" name="regime_cobranca"
                                       id="rc_{{ $regime->value }}" value="{{ $regime->value }}"
                                       {{ $regimeAtual === $regime->value ? 'checked' : '' }}>
                                <label class="btn btn-outline-{{ $regime->color() }} w-100 h-100 text-start p-3"
                                       for="rc_{{ $regime->value }}">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-{{ $regime->icon() }} fs-3 me-2"></i>
                                        <strong>{{ $regime->label() }}</strong>
                                    </div>
                                    <small class="text-muted d-block">{{ $regime->descricao() }}</small>
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <div id="cortesia-extras" class="mt-4 {{ $regimeAtual === 'padrao' ? 'd-none' : '' }}">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Motivo / contexto <span class="text-muted">(obrigatório)</span></label>
                                <input type="text" name="cortesia_motivo"
                                       class="form-control @error('cortesia_motivo') is-invalid @enderror"
                                       value="{{ old('cortesia_motivo', $empresa->cortesia_motivo) }}"
                                       placeholder="Ex: amigo do dono, parceria com X, beta tester, contrato anual..."
                                       maxlength="255">
                                @error('cortesia_motivo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            @php
                                // Defensivo: o cast pode não estar aplicado se a view rodar antes
                                // do autoload recarregar o model. Aceita Carbon, DateTime ou string.
                                $fmtDate = function ($v, $default = null) {
                                    if (! $v) return $default;
                                    if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');
                                    return substr((string) $v, 0, 10);
                                };
                            @endphp
                            <div class="col-md-3">
                                <label class="form-label">Concedida em</label>
                                <input type="date" name="cortesia_concedida_em"
                                       class="form-control"
                                       value="{{ old('cortesia_concedida_em', $fmtDate($empresa->cortesia_concedida_em, now()->format('Y-m-d'))) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Revisar em <span class="text-muted small">(opcional)</span></label>
                                <input type="date" name="cortesia_revisar_em"
                                       class="form-control"
                                       value="{{ old('cortesia_revisar_em', $fmtDate($empresa->cortesia_revisar_em)) }}">
                            </div>
                        </div>
                        <small class="text-muted d-block mt-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Empresas fora do regime padrão nunca caem em "plano expirado". Você pode reverter para Padrão a qualquer momento.
                        </small>
                    </div>
                </div>
            </div>

            {{-- Cobrança direta (cliente paga a IA365 sem gateway) --}}
            @if(auth()->user()->podeVerFinanceiro())
            <div class="card section-card shadow-sm mb-4">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <i class="bi bi-cash-coin me-2"></i>
                        <strong>Cobrança direta</strong> — cliente paga direto à IA365 (sem gateway)
                    </div>
                    @if($empresa->estaSuspensa())
                        <span class="badge bg-danger"><i class="bi bi-lock-fill me-1"></i>ACESSO SUSPENSO</span>
                    @elseif($empresa->temCobrancaDireta())
                        <span class="badge bg-success">Ativa</span>
                    @endif
                </div>
                <div class="card-body">
                    @php $periodicidade = old('cobranca_periodicidade', $empresa->cobranca_periodicidade); @endphp
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Periodicidade</label>
                            <select name="cobranca_periodicidade" id="cobranca_periodicidade" class="form-select">
                                <option value="">— sem cobrança direta —</option>
                                <option value="mensal" {{ $periodicidade === 'mensal' ? 'selected' : '' }}>Mensal</option>
                                <option value="anual" {{ $periodicidade === 'anual' ? 'selected' : '' }}>Anual</option>
                            </select>
                        </div>
                        <div class="col-md-3 cobranca-campo" data-quando="mensal anual">
                            <label class="form-label">Valor (R$)</label>
                            <input type="number" step="0.01" min="0" name="cobranca_valor"
                                   class="form-control @error('cobranca_valor') is-invalid @enderror"
                                   value="{{ old('cobranca_valor', $empresa->cobranca_valor) }}" placeholder="0,00">
                            @error('cobranca_valor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3 cobranca-campo" data-quando="mensal">
                            <label class="form-label">Dia do vencimento</label>
                            <input type="number" min="1" max="28" name="cobranca_dia_vencimento"
                                   class="form-control @error('cobranca_dia_vencimento') is-invalid @enderror"
                                   value="{{ old('cobranca_dia_vencimento', $empresa->cobranca_dia_vencimento) }}" placeholder="ex.: 10">
                            @error('cobranca_dia_vencimento')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3 cobranca-campo" data-quando="anual">
                            <label class="form-label">Próxima renovação</label>
                            <input type="date" name="cobranca_proxima_renovacao"
                                   class="form-control @error('cobranca_proxima_renovacao') is-invalid @enderror"
                                   value="{{ old('cobranca_proxima_renovacao', $empresa->cobranca_proxima_renovacao?->format('Y-m-d')) }}">
                            @error('cobranca_proxima_renovacao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="row g-3 mt-1 cobranca-campo" data-quando="mensal anual">
                        <div class="col-md-4">
                            <label class="form-label">Geração da fatura</label>
                            @php $geracao = old('cobranca_geracao', $empresa->cobranca_geracao ?? 'automatica'); @endphp
                            <select name="cobranca_geracao" class="form-select">
                                <option value="automatica" {{ $geracao === 'automatica' ? 'selected' : '' }}>Automática (todo ciclo)</option>
                                <option value="manual" {{ $geracao === 'manual' ? 'selected' : '' }}>Manual (eu gero quando quiser)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tolerância após o vencimento</label>
                            <div class="input-group">
                                <input type="number" min="0" max="90" name="cobranca_tolerancia_dias"
                                       class="form-control"
                                       value="{{ old('cobranca_tolerancia_dias', $empresa->cobranca_tolerancia_dias ?? 5) }}">
                                <span class="input-group-text">dias</span>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input type="hidden" name="cobranca_bloqueio_automatico" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" value="1"
                                       name="cobranca_bloqueio_automatico" id="cobranca_bloqueio"
                                       {{ old('cobranca_bloqueio_automatico', $empresa->cobranca_bloqueio_automatico) ? 'checked' : '' }}>
                                <label class="form-check-label" for="cobranca_bloqueio">
                                    <strong>Bloquear automaticamente</strong> se não marcar como paga
                                </label>
                            </div>
                        </div>
                    </div>

                    <small class="text-muted d-block mt-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Com a cobrança direta ativa, o acesso da empresa passa a depender das faturas daqui
                        (o trial deixa de contar). O bloqueio derruba <strong>todo</strong> o acesso da empresa
                        e reativa na hora em que você marca a fatura como paga.
                        Faturas e pagamentos ficam em <a href="{{ route('admin.financeiro.index', ['empresa_id' => $empresa->id]) }}">Financeiro</a>.
                    </small>
                </div>
            </div>
            <script>
                (function () {
                    const sel = document.getElementById('cobranca_periodicidade');
                    const campos = document.querySelectorAll('.cobranca-campo');
                    function atualiza() {
                        campos.forEach(c => {
                            c.style.display = (sel.value && c.dataset.quando.includes(sel.value)) ? '' : 'none';
                        });
                    }
                    sel.addEventListener('change', atualiza);
                    atualiza();
                })();
            </script>
            @endif

            {{-- Multi-loja / política de estoque entre unidades --}}
            @if($empresa->unidades()->count() > 1)
            <div class="card section-card shadow-sm mb-4">
                <div class="card-header">
                    <i class="bi bi-shop-window me-2"></i>
                    <strong>Política multi-loja</strong> — visibilidade e venda de estoque entre unidades
                </div>
                <div class="card-body">
                    @php $pol = old('politica_estoque_inter_unidade', $empresa->politica_estoque_inter_unidade ?? 'ver_e_vender'); @endphp
                    <div class="row g-3">
                        @foreach([
                            'silos' => ['Silos independentes', 'shield-lock', 'secondary', 'Cada loja só vê o próprio estoque. Sem visibilidade entre unidades. Bom quando lojas têm gestão separada.'],
                            'ver_apenas' => ['Visualizar apenas', 'eye', 'info', 'Lojas veem o saldo das outras unidades, mas precisam usar Transferência (com aprovação) para mover produto. Vendedor pode orientar cliente.'],
                            'ver_e_vender' => ['Ver e vender', 'arrow-left-right', 'success', 'PDV pode vender produto de outra loja — sistema cria transferência automática origem→destino. Mais venda, menos cliente perdido.'],
                        ] as $value => [$label, $icon, $color, $desc])
                            <div class="col-md-4">
                                <input type="radio" class="btn-check" name="politica_estoque_inter_unidade"
                                       id="pol_{{ $value }}" value="{{ $value }}"
                                       {{ $pol === $value ? 'checked' : '' }}>
                                <label class="btn btn-outline-{{ $color }} w-100 h-100 text-start p-3" for="pol_{{ $value }}">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-{{ $icon }} fs-3 me-2"></i>
                                        <strong>{{ $label }}</strong>
                                    </div>
                                    <small class="text-muted d-block">{{ $desc }}</small>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <small class="text-muted d-block mt-3">
                        <i class="bi bi-info-circle me-1"></i>
                        A mudança afeta o PDV de todas as unidades. Fiscalmente, cada unidade continua sendo CNPJ independente na Focus NFe.
                    </small>
                </div>
            </div>
            @endif

            {{-- Logo --}}
            <div class="card section-card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-image me-2 text-info"></i>Logo</h6>
                </div>
                <div class="card-body">
                    @if($empresa->logo)
                        <div class="mb-3 text-center p-3 bg-light rounded">
                            <img src="{{ asset('storage/' . $empresa->logo) }}" alt="Logo atual"
                                 class="rounded" style="max-height: 80px; max-width: 100%;">
                            <div class="form-text mt-1">Logo atual</div>
                        </div>
                    @endif
                    <input type="file" class="form-control @error('logo') is-invalid @enderror"
                           id="logo" name="logo" accept="image/*">
                    <div class="form-text">Deixe vazio para manter a logo atual.</div>
                    @error('logo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="mt-2 text-center d-none" id="logo-preview-wrapper">
                        <img id="logo-preview" src="" alt="Preview" class="rounded border" style="max-height: 80px; max-width: 100%;">
                    </div>
                </div>
            </div>

            {{-- Observacoes --}}
            <div class="card section-card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-chat-text me-2 text-warning"></i>Observacoes</h6>
                </div>
                <div class="card-body">
                    <textarea class="form-control @error('observacoes') is-invalid @enderror"
                              id="observacoes" name="observacoes" rows="4"
                              placeholder="Anotacoes internas...">{{ old('observacoes', $empresa->observacoes) }}</textarea>
                    @error('observacoes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Info --}}
            <div class="card section-card shadow-sm mb-4 bg-light">
                <div class="card-body">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Criada em {{ $empresa->created_at->format('d/m/Y H:i') }}
                        @if($empresa->updated_at->ne($empresa->created_at))
                            <br>Atualizada em {{ $empresa->updated_at->format('d/m/Y H:i') }}
                        @endif
                    </small>
                </div>
            </div>
        </div>
    </div>

    {{-- Botoes --}}
    <div class="btn-actions">
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-check-lg me-1"></i> Salvar Alteracoes
            </button>
            <a href="{{ route('admin.empresas.show', $empresa) }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Toggle dos extras do regime de cobrança ---
    const extras = document.getElementById('cortesia-extras');
    if (extras) {
        document.querySelectorAll('input[name="regime_cobranca"]').forEach(r => {
            r.addEventListener('change', () => {
                if (r.value === 'padrao') extras.classList.add('d-none');
                else extras.classList.remove('d-none');
            });
        });
    }

    // --- Masks ---
    function applyMask(input, maskFn) {
        input.addEventListener('input', function () {
            this.value = maskFn(this.value);
        });
    }

    function maskCNPJ(v) {
        // CNPJ alfanumérico (NT 2025.001): 12 primeiras posições aceitam letras, DV numérico
        const a = v.replace(/[^0-9A-Za-z]/g, '').toUpperCase().slice(0, 14);
        const base = a.slice(0, 12);
        const dv = a.slice(12).replace(/[^0-9]/g, '');
        let out = base.slice(0, 2);
        if (base.length > 2) out += '.' + base.slice(2, 5);
        if (base.length > 5) out += '.' + base.slice(5, 8);
        if (base.length > 8) out += '/' + base.slice(8, 12);
        if (dv.length) out += '-' + dv;
        return out;
    }

    function maskCEP(v) {
        v = v.replace(/\D/g, '').substring(0, 8);
        v = v.replace(/^(\d{5})(\d)/, '$1-$2');
        return v;
    }

    function maskTelefone(v) {
        v = v.replace(/\D/g, '').substring(0, 11);
        if (v.length > 10) {
            v = v.replace(/^(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        } else if (v.length > 6) {
            v = v.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
        } else if (v.length > 2) {
            v = v.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
        }
        return v;
    }

    document.querySelectorAll('[data-mask="cnpj"]').forEach(el => applyMask(el, maskCNPJ));
    document.querySelectorAll('[data-mask="cep"]').forEach(el => applyMask(el, maskCEP));
    document.querySelectorAll('[data-mask="telefone"]').forEach(el => applyMask(el, maskTelefone));

    // --- ViaCEP ---
    const btnCep = document.getElementById('btn-buscar-cep');
    const cepInput = document.getElementById('cep');
    const cepSpinner = document.getElementById('cep-spinner');
    const searchIcon = btnCep.querySelector('.bi-search');

    function buscarCEP() {
        const cep = cepInput.value.replace(/\D/g, '');
        if (cep.length !== 8) {
            cepInput.classList.add('is-invalid');
            return;
        }
        cepInput.classList.remove('is-invalid');

        btnCep.disabled = true;
        searchIcon.style.display = 'none';
        cepSpinner.style.display = 'inline-block';

        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(res => res.json())
            .then(data => {
                if (data.erro) {
                    cepInput.classList.add('is-invalid');
                    return;
                }
                document.getElementById('logradouro').value = data.logradouro || '';
                document.getElementById('bairro').value = data.bairro || '';
                document.getElementById('cidade').value = data.localidade || '';
                document.getElementById('uf').value = data.uf || '';
                document.getElementById('numero').focus();
            })
            .catch(() => {
                cepInput.classList.add('is-invalid');
            })
            .finally(() => {
                btnCep.disabled = false;
                searchIcon.style.display = 'inline-block';
                cepSpinner.style.display = 'none';
            });
    }

    btnCep.addEventListener('click', buscarCEP);
    cepInput.addEventListener('blur', function () {
        if (this.value.replace(/\D/g, '').length === 8) buscarCEP();
    });

    // --- Logo Preview ---
    document.getElementById('logo').addEventListener('change', function () {
        const file = this.files[0];
        const wrapper = document.getElementById('logo-preview-wrapper');
        const preview = document.getElementById('logo-preview');
        if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                wrapper.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        } else {
            wrapper.classList.add('d-none');
        }
    });
});
</script>
@endpush
