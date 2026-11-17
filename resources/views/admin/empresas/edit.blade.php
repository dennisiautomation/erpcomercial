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
    .section-card .card-sub {
        margin: 0.125rem 0 0 1.75rem;
        font-size: 0.8125rem;
        color: var(--text-muted, #64748b);
    }
    /* Opções em cartão (regime de cobrança, política multi-loja) */
    .opcao-cartao {
        white-space: normal;
        border-width: 1.5px;
        transition: background-color .15s ease, box-shadow .15s ease;
    }
    .opcao-cartao strong { line-height: 1.25; }
    .opcao-cartao small { line-height: 1.45; }
    .btn-check:checked + .opcao-cartao small.text-muted { color: rgba(255, 255, 255, .88) !important; }
    .btn-check:focus-visible + .opcao-cartao { box-shadow: 0 0 0 .25rem rgba(79, 70, 229, .3); }
    /* Regras e recursos: chave à esquerda, efeito explicado embaixo, linhas separadas */
    .chaves-lista { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 22rem), 1fr)); }
    .chave-linha {
        padding: 1rem 1.25rem;
        border-top: 1px solid var(--border-light, #f1f5f9);
        border-right: 1px solid var(--border-light, #f1f5f9);
    }
    .chave-linha .form-check-label { color: var(--text-primary, #0f172a); }
    .chave-ajuda {
        margin: 0.375rem 0 0 2.5rem;
        font-size: 0.8125rem;
        line-height: 1.5;
        color: var(--text-muted, #64748b);
        max-width: 60ch;
    }
    .btn-actions {
        position: sticky;
        bottom: 0;
        background: rgba(241, 245, 249, .94);
        backdrop-filter: blur(6px);
        border-top: 1px solid var(--border, #e2e8f0);
        padding: 0.75rem 0;
        margin-top: -0.5rem;
        margin-bottom: 1.5rem;
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
                            <label for="razao_social" class="form-label">Razão Social <span class="required-dot">*</span></label>
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
                            <label for="ie" class="form-label">Inscrição Estadual</label>
                            <input type="text" class="form-control @error('ie') is-invalid @enderror"
                                   id="ie" name="ie" value="{{ old('ie', $empresa->ie) }}">
                            @error('ie')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="im" class="form-label">Inscrição Municipal</label>
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
                    <h6 class="mb-0"><i class="bi bi-geo-alt me-2 text-danger"></i>Endereço</h6>
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
                            <label for="numero" class="form-label">Número</label>
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

            {{-- Regime de cobrança --}}
            <div class="card section-card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-credit-card-2-back me-2 text-primary"></i>Regime de cobrança</h6>
                    <p class="card-sub">Como a IA365 fatura esta empresa</p>
                </div>
                <div class="card-body">
                    @php $regimeAtual = old('regime_cobranca', $empresa->regime_cobranca?->value ?? 'padrao'); @endphp
                    <div class="row g-3">
                        @foreach(\App\Enums\RegimeCobranca::cases() as $regime)
                            <div class="col-sm-6 col-xxl-3">
                                <input type="radio" class="btn-check" name="regime_cobranca"
                                       id="rc_{{ $regime->value }}" value="{{ $regime->value }}"
                                       {{ $regimeAtual === $regime->value ? 'checked' : '' }}>
                                <label class="btn btn-outline-{{ $regime->color() }} opcao-cartao w-100 h-100 text-start p-3"
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
                                <label for="campo-cortesia_motivo" class="form-label">Motivo / contexto <span class="text-muted">(obrigatório)</span></label>
                                <input id="campo-cortesia_motivo" type="text" name="cortesia_motivo"
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
                                <label for="campo-cortesia_concedida_em" class="form-label">Concedida em</label>
                                <input id="campo-cortesia_concedida_em" type="date" name="cortesia_concedida_em"
                                       class="form-control"
                                       value="{{ old('cortesia_concedida_em', $fmtDate($empresa->cortesia_concedida_em, now()->format('Y-m-d'))) }}">
                            </div>
                            <div class="col-md-3">
                                <label for="campo-cortesia_revisar_em" class="form-label">Revisar em <span class="text-muted small">(opcional)</span></label>
                                <input id="campo-cortesia_revisar_em" type="date" name="cortesia_revisar_em"
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
                        <h6 class="mb-0"><i class="bi bi-cash-coin me-2 text-success"></i>Cobrança direta</h6>
                        <p class="card-sub">Cliente paga direto à IA365 (sem gateway)</p>
                    </div>
                    @if($empresa->estaSuspensa())
                        <span class="badge bg-danger"><i class="bi bi-lock-fill me-1"></i>ACESSO SUSPENSO</span>
                    @elseif($empresa->temCobrancaDireta())
                        <span class="badge bg-success">Ativa</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($empresa->vinculadaAoCaixa())
                        <div class="alert alert-info py-2 small" data-vinculo-caixa>
                            <i class="bi bi-link-45deg me-1"></i>
                            <strong>Vinculada ao caixa.ia365</strong> — cliente "{{ $empresa->caixa_cliente_nome ?: '#' . $empresa->caixa_cliente_id }}".
                            As faturas desta empresa nascem e são baixadas lá; aqui o sistema não gera fatura para ela.
                            A tolerância e o bloqueio automático abaixo continuam valendo.
                        </div>
                    @endif
                    @php $periodicidade = old('cobranca_periodicidade', $empresa->cobranca_periodicidade); @endphp
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="cobranca_periodicidade" class="form-label">Periodicidade</label>
                            <select name="cobranca_periodicidade" id="cobranca_periodicidade" class="form-select">
                                <option value="">— sem cobrança direta —</option>
                                <option value="mensal" {{ $periodicidade === 'mensal' ? 'selected' : '' }}>Mensal</option>
                                <option value="anual" {{ $periodicidade === 'anual' ? 'selected' : '' }}>Anual</option>
                            </select>
                        </div>
                        <div class="col-md-3 cobranca-campo" data-quando="mensal anual">
                            <label for="campo-cobranca_valor" class="form-label">Valor (R$)</label>
                            <input id="campo-cobranca_valor" type="number" step="0.01" min="0" name="cobranca_valor"
                                   class="form-control @error('cobranca_valor') is-invalid @enderror"
                                   value="{{ old('cobranca_valor', $empresa->cobranca_valor) }}" placeholder="0,00">
                            @error('cobranca_valor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3 cobranca-campo" data-quando="mensal">
                            <label for="campo-cobranca_dia_vencimento" class="form-label">Dia do vencimento</label>
                            <input id="campo-cobranca_dia_vencimento" type="number" min="1" max="28" name="cobranca_dia_vencimento"
                                   class="form-control @error('cobranca_dia_vencimento') is-invalid @enderror"
                                   value="{{ old('cobranca_dia_vencimento', $empresa->cobranca_dia_vencimento) }}" placeholder="ex.: 10">
                            @error('cobranca_dia_vencimento')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3 cobranca-campo" data-quando="anual">
                            <label for="campo-cobranca_proxima_renovacao" class="form-label">Próxima renovação</label>
                            <input id="campo-cobranca_proxima_renovacao" type="date" name="cobranca_proxima_renovacao"
                                   class="form-control @error('cobranca_proxima_renovacao') is-invalid @enderror"
                                   value="{{ old('cobranca_proxima_renovacao', $empresa->cobranca_proxima_renovacao?->format('Y-m-d')) }}">
                            @error('cobranca_proxima_renovacao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="row g-3 mt-1 cobranca-campo" data-quando="mensal anual" @if($empresa->vinculadaAoCaixa()) data-sempre @endif>
                        <div class="col-md-4">
                            <label for="campo-cobranca_geracao" class="form-label">Geração da fatura</label>
                            @php $geracao = old('cobranca_geracao', $empresa->cobranca_geracao ?? 'automatica'); @endphp
                            <select id="campo-cobranca_geracao" name="cobranca_geracao" class="form-select">
                                <option value="automatica" {{ $geracao === 'automatica' ? 'selected' : '' }}>Automática (todo ciclo)</option>
                                <option value="manual" {{ $geracao === 'manual' ? 'selected' : '' }}>Manual (eu gero quando quiser)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="campo-cobranca_tolerancia_dias" class="form-label">Tolerância após o vencimento</label>
                            <div class="input-group">
                                <input id="campo-cobranca_tolerancia_dias" type="number" min="0" max="90" name="cobranca_tolerancia_dias"
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
                            c.style.display = (c.hasAttribute('data-sempre') || (sel.value && c.dataset.quando.includes(sel.value))) ? '' : 'none';
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
                    <h6 class="mb-0"><i class="bi bi-shop-window me-2 text-info"></i>Política multi-loja</h6>
                    <p class="card-sub">Visibilidade e venda de estoque entre unidades</p>
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
                                <label class="btn btn-outline-{{ $color }} opcao-cartao w-100 h-100 text-start p-3" for="pol_{{ $value }}">
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

            {{-- Open Finance (17/11/2026) --}}
            @php
                $ofPlano = $empresa->getPlanoAtivo();
                $ofDoPlano = ($ofPlano && $ofPlano->open_finance_habilitado) ? (int) $ofPlano->open_finance_contas : 0;
            @endphp
            <div class="card section-card shadow-sm mb-4">
                <div class="card-header d-flex align-items-start justify-content-between gap-2">
                    <div>
                        <h6 class="mb-0"><i class="bi bi-bank me-2 text-primary"></i>Open Finance</h6>
                        <p class="card-sub">Liberação e quantidade de contas</p>
                    </div>
                    @if($empresa->openFinanceVisivel())
                        <span class="badge rounded-pill text-bg-success">Aparece para a empresa</span>
                    @else
                        <span class="badge rounded-pill text-bg-light border">Escondido</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Contas do plano</label>
                            <input type="text" class="form-control" value="{{ $ofDoPlano }}" disabled>
                            <div class="form-text">{{ $ofDoPlano ? 'Vêm no plano ' . $ofPlano->nome . '.' : 'O plano desta empresa não inclui.' }}</div>
                        </div>
                        <div class="col-md-3">
                            <label for="open_finance_contas_extras" class="form-label">Contas extras (venda avulsa)</label>
                            <input type="number" min="0" max="999" class="form-control @error('open_finance_contas_extras') is-invalid @enderror"
                                   id="open_finance_contas_extras" name="open_finance_contas_extras"
                                   value="{{ old('open_finance_contas_extras', $empresa->open_finance_contas_extras ?? 0) }}">
                            @error('open_finance_contas_extras') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Em uso</label>
                            <input type="text" class="form-control" value="{{ $empresa->openFinanceContasUsadas() }} de {{ $empresa->openFinanceLimiteContas() }}" disabled>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mb-2">
                                <input type="hidden" name="open_finance_ocultar" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" value="1" name="open_finance_ocultar" id="open_finance_ocultar"
                                       {{ old('open_finance_ocultar', $empresa->open_finance_ocultar) ? 'checked' : '' }}>
                                <label class="form-check-label" for="open_finance_ocultar">
                                    <strong>Não mostrar</strong> para esta empresa
                                </label>
                            </div>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Com contas (do plano ou extras) a empresa usa a função. Sem contas, ela vê o convite para contratar — ou nada, se "Não mostrar" estiver ligado.
                    </small>
                </div>
            </div>

            {{-- Regras e recursos (antes "Cadastros"): cada chave com o efeito explicado ao lado --}}
            <div class="card section-card shadow-sm mb-4" id="sec-recursos">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-toggles me-2 text-primary"></i>Regras e recursos da empresa</h6>
                </div>
                <div class="card-body p-0">
                    @php
                        $chaves = [
                            ['exige_documento_cadastro', (bool) old('exige_documento_cadastro', $empresa->exige_documento_cadastro ?? true),
                             'Exigir CPF/CNPJ no cadastro de clientes e fornecedores',
                             'Desmarcado, o documento vira opcional nas duas telas (inclusive no cadastro rápido de cliente dos Pedidos e da Ordem de Serviço). A <strong>NF-e continua exigindo</strong> CPF/CNPJ do destinatário — venda para cliente sem documento não emite nota modelo 55. A NFC-e não é afetada.'],
                            ['vendedor_apenas_pdv', (bool) old('vendedor_apenas_pdv', $empresa->vendedor_apenas_pdv ?? false),
                             'Vendedor opera somente o PDV',
                             'Marcado, o perfil <strong>Vendedor</strong> desta empresa entra direto no PDV e perde o Dashboard, os relatórios, os cadastros, o estoque, o financeiro e o fiscal — inclusive digitando o endereço no navegador. Continua vendendo, trocando (F6), usando vale e abrindo/fechando o caixa. Vale para todas as lojas da empresa; gerente, caixa, financeiro, consulta e dono não mudam. O dono também liga isto em <strong>Configurações da Loja</strong>.'],
                            ['pdv_vendedores_da_loja', (bool) old('pdv_vendedores_da_loja', $empresa->pdv_vendedores_da_loja ?? false),
                             'No PDV, mostrar só os vendedores da loja',
                             'Marcado, o select de vendedor do PDV (F3) lista só quem está vinculado à loja em que o caixa está operando (pivô <code>unidade_user</code>, ajustado em Funcionários). <strong>Dono e IA365 aparecem em qualquer loja</strong>, e quem não tem loja vinculada aparece em todas. Independente da opção acima.'],
                            ['pdv_credishop', (bool) old('pdv_credishop', $empresa->pdv_credishop ?? false),
                             'Forma de pagamento Credishop no PDV',
                             'Marcado, o PDV de todas as lojas da empresa ganha o botão <strong>Credishop</strong>. Conta como cartão de crédito: preço do cartão, acréscimo do crédito e NFC-e com forma 03.'],
                            ['usa_ficha_tecnica', (bool) old('usa_ficha_tecnica', $empresa->usa_ficha_tecnica ?? false),
                             'Cafeteria/alimentação: tipo do produto, ficha técnica e adicionais',
                             'Liga o Tipo no cadastro de produto (Revenda, Produzido, Insumo, Adicional), a receita que baixa insumos e os grupos de opções do PDV. Os atalhos do PDV são por loja, em <strong>Configurações da Loja</strong>.'],
                        ];
                    @endphp
                    <div class="chaves-lista">
                        @foreach($chaves as [$campo, $ligado, $titulo, $explica])
                            <div class="chave-linha">
                                <div class="form-check form-switch m-0">
                                    <input type="hidden" name="{{ $campo }}" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" value="1"
                                           id="{{ $campo }}" name="{{ $campo }}" aria-describedby="{{ $campo }}_ajuda"
                                           @checked($ligado)>
                                    <label class="form-check-label fw-semibold" for="{{ $campo }}">{{ $titulo }}</label>
                                </div>
                                <p class="chave-ajuda" id="{{ $campo }}_ajuda">{!! $explica !!}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            {{-- Regime, Plano e Status --}}
            <div class="card section-card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-gear me-2 text-secondary"></i>Configurações</h6>
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
                    <h6 class="mb-0"><i class="bi bi-chat-text me-2 text-warning"></i>Observações</h6>
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
                <i class="bi bi-check-lg me-1"></i> Salvar Alterações
            </button>
            <a href="{{ route('admin.empresas.show', $empresa) }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </div>
</form>

{{-- Acessos do cliente — reenviar dados de acesso (fora do form principal) --}}
@php
    $usuariosEmpresa = $empresa->users()->withoutGlobalScopes()
        ->orderByRaw("FIELD(perfil, 'dono') DESC")->orderBy('name')->get();
@endphp
<div class="card section-card shadow-sm mb-4">
    <div class="card-header">
        <i class="bi bi-envelope-paper me-2"></i>
        <strong>Acessos do cliente</strong> — reenviar os dados de acesso por e-mail
    </div>
    <div class="card-body">
        @if($usuariosEmpresa->isEmpty())
            <p class="text-muted mb-0">Nenhum usuário cadastrado para esta empresa ainda.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-2">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Perfil</th>
                            <th>Status</th>
                            <th class="text-end">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($usuariosEmpresa as $u)
                            <tr>
                                <td>{{ $u->name }}</td>
                                <td>{{ $u->email }}</td>
                                <td><span class="badge bg-{{ $u->perfil === \App\Enums\Perfil::Dono ? 'primary' : 'secondary' }}">{{ $u->perfil?->label() ?? $u->perfil }}</span></td>
                                <td><x-erp.status-badge :status="$u->status" /></td>
                                <td class="text-end">
                                    @if($u->status === 'ativo')
                                        <form method="POST" action="{{ route('admin.empresas.reenviar-acesso', $empresa) }}" class="d-inline"
                                              data-confirm="Isso gera uma SENHA NOVA para {{ $u->name }} e envia os dados de acesso para {{ $u->email }}. A senha atual deixa de funcionar. Continuar?">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $u->id }}">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-envelope-arrow-up me-1"></i> Reenviar acesso
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted small">inativo</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <small class="text-muted">
                <i class="bi bi-info-circle me-1"></i>
                O reenvio gera uma <strong>senha nova</strong> (a anterior é criptografada e não dá para recuperar)
                e manda o e-mail de boas-vindas com endereço, login e senha.
            </small>
        @endif
    </div>
</div>
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
