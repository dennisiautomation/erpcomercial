@extends('layouts.app')

@section('title', 'Configuracao Fiscal')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-gear me-2"></i>Configuracao Fiscal</h4>
</div>

{{-- ═══ Regime tributário da empresa (fora do form principal — submit próprio) ═══ --}}
@php
    $empresaAtual = auth()->user()->empresa;
    $ehDono = (auth()->user()->perfil?->value ?? '') === 'dono' || auth()->user()->is_admin;
@endphp
<div class="erp-card mb-3 border">
    <div class="card-header bg-transparent d-flex align-items-center">
        <i class="bi bi-building fs-4 text-primary me-2"></i>
        <div class="flex-grow-1">
            <strong>Regime tributário da empresa</strong>
            <div class="small text-muted">
                Define CST×CSOSN, alíquotas sugeridas e o envio automático de IBS/CBS — vale para todas as unidades.
            </div>
        </div>
        <span class="badge bg-primary bg-opacity-10 text-primary">
            {{ $empresaAtual?->regime_tributario?->label() ?? 'Não definido' }}
        </span>
    </div>
    <div class="card-body">
        @if($ehDono)
            <form method="POST" action="{{ route('app.configuracao-fiscal.regime') }}"
                  class="row g-2 align-items-end"
                  data-confirm="Mudar o regime tributário altera como TODAS as notas são emitidas (CST×CSOSN, IBS/CBS automático, alíquotas). Confirme com o seu contador antes. Continuar?">
                @csrf
                @method('PUT')
                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Regime</label>
                    <select name="regime_tributario" class="form-select">
                        @foreach(\App\Enums\RegimeTributario::cases() as $regime)
                            <option value="{{ $regime->value }}"
                                {{ ($empresaAtual?->regime_tributario?->value ?? '') === $regime->value ? 'selected' : '' }}>
                                {{ $regime->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-check2 me-1"></i>Salvar regime
                    </button>
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">
                        Lucro Presumido/Real: IBS/CBS automático nas notas (obrigatório desde 03/08/2026).
                        Simples Nacional: entra em 01/2027.
                    </small>
                </div>
            </form>
        @else
            <small class="text-muted">
                <i class="bi bi-lock me-1"></i>Somente o dono da empresa pode alterar o regime tributário.
            </small>
        @endif
    </div>
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

                @if($gerenciadaPelaFocus)
                    <div class="alert alert-success small d-flex">
                        <i class="bi bi-shield-check me-2 fs-4"></i>
                        <div>
                            <strong>Empresa gerenciada pela plataforma.</strong>
                            Os tokens de produção e homologação desta unidade foram gerados automaticamente na Focus NFe (ID #{{ $config->focus_empresa_id }})
                            e são usados conforme o ambiente selecionado. Você não precisa configurá-los manualmente.
                        </div>
                    </div>
                @else
                    <div class="alert alert-info small">
                        <i class="bi bi-info-circle me-1"></i>
                        O mesmo <strong>token e certificado</strong> da Focus NFe emite NF-e, NFC-e e NFS-e.
                        Abaixo, habilite cada tipo e configure os dados específicos.
                    </div>
                @endif

                {{-- ═══ Checklist de prontidão para emissão ═══ --}}
                @if(! empty($checklist))
                    <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);">
                        <div class="card-header bg-transparent border-bottom-0 d-flex align-items-center pb-0">
                            <i class="bi bi-clipboard-check fs-4 text-primary me-2"></i>
                            <div>
                                <strong>O que falta para começar a emitir</strong>
                                <div class="small text-muted">Checklist baseado nos tipos que você habilitou</div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                @foreach($checklist as $tipo => $info)
                                    @php
                                        $criticos = array_filter($info['itens'], fn($i) => $i['critico']);
                                        $criticosOk = array_filter($criticos, fn($i) => $i['ok']);
                                        $totalCriticos = count($criticos);
                                        $okCriticos = count($criticosOk);
                                        $pct = $totalCriticos > 0 ? round(($okCriticos / $totalCriticos) * 100) : 0;
                                        $prontoCor = $pct === 100 ? 'success' : ($pct >= 60 ? 'warning' : 'danger');
                                    @endphp
                                    <div class="col-md-4">
                                        <div class="border rounded-3 p-3 h-100">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="bi bi-{{ $info['icon'] }} fs-4 text-{{ $info['cor'] }} me-2"></i>
                                                <strong class="flex-grow-1">{{ $info['titulo'] }}</strong>
                                                <span class="badge bg-{{ $prontoCor }}">{{ $okCriticos }}/{{ $totalCriticos }}</span>
                                            </div>
                                            <div class="progress mb-2" style="height: 4px;">
                                                <div class="progress-bar bg-{{ $prontoCor }}" role="progressbar" style="width: {{ $pct }}%"></div>
                                            </div>
                                            <ul class="list-unstyled small mb-0">
                                                @foreach($info['itens'] as $item)
                                                    <li class="mb-1 d-flex align-items-start">
                                                        @if($item['ok'])
                                                            <i class="bi bi-check-circle-fill text-success me-2 flex-shrink-0" style="margin-top:2px;"></i>
                                                            <span class="text-success-emphasis">{{ $item['label'] }}</span>
                                                        @else
                                                            <i class="bi bi-{{ $item['critico'] ? 'x-circle-fill text-danger' : 'circle text-muted' }} me-2 flex-shrink-0" style="margin-top:2px;"></i>
                                                            <span class="{{ $item['critico'] ? 'text-danger' : 'text-muted' }}">
                                                                {{ $item['label'] }}
                                                                @if(! $item['critico'])
                                                                    <small class="text-muted">(opcional)</small>
                                                                @endif
                                                            </span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                            @if($pct === 100)
                                                <div class="mt-2 small text-success fw-semibold">
                                                    <i class="bi bi-check-circle-fill me-1"></i>Pronto para emitir!
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Token e Ambiente --}}
                <div class="row g-3 mb-3">
                    @if($gerenciadaPelaFocus)
                        @php
                            $webhooksCount = is_array($config->focus_webhook_ids) ? count($config->focus_webhook_ids) : 0;
                            $temToken = $config->focus_token_producao || $config->focus_token_homologacao;
                        @endphp
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Status da integração Focus NFe</label>
                            <div class="border rounded p-3 bg-light">
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <span class="badge bg-success">
                                        <i class="bi bi-check2-circle me-1"></i>Empresa #{{ $config->focus_empresa_id }}
                                    </span>
                                    @if($config->focus_token_homologacao)
                                        <span class="badge bg-info">homologação ✓</span>
                                    @endif
                                    @if($config->focus_token_producao)
                                        <span class="badge bg-success">produção ✓</span>
                                    @endif
                                    @if($webhooksCount > 0)
                                        <span class="badge bg-primary">
                                            <i class="bi bi-broadcast me-1"></i>{{ $webhooksCount }} webhooks
                                        </span>
                                    @else
                                        <span class="badge bg-warning text-dark">sem webhooks</span>
                                    @endif
                                </div>
                                <small class="text-muted d-block">
                                    Cadastro gerenciado automaticamente — não é mais necessário colar token.
                                    @if($config->focus_sincronizado_em)
                                        Sincronizado em {{ $config->focus_sincronizado_em->format('d/m/Y H:i') }}.
                                    @endif
                                </small>
                                @if(auth()->user()->is_admin)
                                    <a href="{{ route('admin.empresas.saude-focus', $config->empresa_id) }}"
                                       class="btn btn-sm btn-outline-secondary mt-2">
                                        <i class="bi bi-shield-check me-1"></i>Saúde Focus detalhada
                                    </a>
                                @endif
                            </div>
                        </div>
                    @elseif($modoRevenda)
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Integração Focus NFe</label>
                            <div class="alert alert-warning small mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                <strong>Ainda não conectada.</strong> A plataforma está em modo automático —
                                ao salvar essa configuração, a empresa será criada na Focus NFe e os tokens
                                serão emitidos para você. Não precisa colar nenhum token manualmente.
                            </div>
                            @if(auth()->user()->is_admin)
                                {{-- form real declarado fora do form principal (forms não podem aninhar) --}}
                                <button class="btn btn-sm btn-success mt-2" type="submit" form="formProvisionarFocus"
                                        data-confirm="Provisionar essa unidade na Focus NFe agora?">
                                    <i class="bi bi-magic me-1"></i>Provisionar agora
                                </button>
                            @endif
                        </div>
                    @else
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
                    @endif
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Ambiente</label>
                        <select name="ambiente" class="form-select @error('ambiente') is-invalid @enderror">
                            <option value="homologacao" {{ old('ambiente', $config->ambiente ?? 'homologacao') === 'homologacao' ? 'selected' : '' }}>Homologação (testes)</option>
                            <option value="producao" {{ old('ambiente', $config->ambiente) === 'producao' ? 'selected' : '' }}>Produção (real)</option>
                        </select>
                        @error('ambiente')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- ═══ Certificado Digital A1 ═══ --}}
                @php
                    // Certificado A1 só é obrigatório para NF-e (modelo 55) e NFC-e em várias UFs.
                    // NFS-e geralmente usa login/senha do portal da prefeitura (varia por cidade).
                    // Em homologação muitas vezes nem precisa para testar.
                    $precisaCertificado = old('emite_nfe', $config->emite_nfe ?? false) || old('emite_nfce', $config->emite_nfce ?? false);
                    $apenasNFSe = !$precisaCertificado && old('emite_nfse', $config->emite_nfse ?? false);
                    $ambienteAtual = old('ambiente', $config->ambiente ?? 'homologacao');
                @endphp

                <div class="erp-card mt-3 mb-3 border">
                    <div class="card-header bg-transparent d-flex align-items-center">
                        <i class="bi bi-shield-lock fs-4 text-primary me-2"></i>
                        <div class="flex-grow-1">
                            <strong>Certificado Digital A1</strong>
                            <div class="small text-muted">
                                @if($precisaCertificado)
                                    Obrigatório para emitir NF-e e NFC-e
                                @elseif($apenasNFSe)
                                    Opcional para NFS-e — depende da sua prefeitura
                                @else
                                    Só necessário quando você habilitar NF-e ou NFC-e
                                @endif
                            </div>
                        </div>
                        @if($config->certificado_validade)
                            @php $dias = (int) now()->startOfDay()->diffInDays($config->certificado_validade->startOfDay(), false); @endphp
                            @if($dias > 30)
                                <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i>Válido — {{ $dias }} dias</span>
                            @elseif($dias > 0)
                                <span class="badge bg-warning"><i class="bi bi-exclamation-triangle me-1"></i>Expira em {{ $dias }} dias</span>
                            @else
                                <span class="badge bg-danger"><i class="bi bi-shield-x me-1"></i>VENCIDO</span>
                            @endif
                        @elseif($precisaCertificado)
                            <span class="badge bg-warning"><i class="bi bi-shield-exclamation me-1"></i>Necessário</span>
                        @else
                            <span class="badge bg-secondary"><i class="bi bi-dash-circle me-1"></i>Opcional</span>
                        @endif
                    </div>
                    <div class="card-body">
                        {{-- Contexto de quando é necessário --}}
                        @if($apenasNFSe && !$config->certificado_enviado_em)
                            <div class="alert alert-info small mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                <strong>Você só marcou NFS-e.</strong>
                                A maioria das prefeituras permite emitir NFS-e apenas com login e senha do portal municipal —
                                <strong>o certificado digital A1 geralmente não é exigido</strong>. Algumas cidades (ex: São Paulo, Curitiba)
                                pedem o certificado. Consulte sua prefeitura ou contador se não tem certeza.
                            </div>
                        @elseif($ambienteAtual === 'homologacao' && !$config->certificado_enviado_em)
                            <div class="alert alert-info small mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                Em <strong>homologação</strong> (ambiente de teste), você pode fazer emissões de teste sem enviar o certificado —
                                a Focus NFe tem um certificado de homologação genérico. O certificado A1 só vira obrigatório
                                quando trocar para <strong>produção</strong>.
                            </div>
                        @endif

                        @if($config->certificado_enviado_em)
                            <div class="small text-muted mb-3">
                                <i class="bi bi-check-circle text-success me-1"></i>
                                Enviado em <strong>{{ $config->certificado_enviado_em->format('d/m/Y H:i') }}</strong>
                                @if($config->certificado_nome) — arquivo <code>{{ $config->certificado_nome }}</code> @endif
                                @if($config->certificado_validade) — validade até <strong>{{ $config->certificado_validade->format('d/m/Y') }}</strong> @endif
                            </div>
                        @endif

                        {{-- inputs apontam para formCertificado (declarado fora do form principal) --}}
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label small fw-semibold">Arquivo do certificado (.pfx)</label>
                                {{-- sem `accept`: no macOS o Chrome esmaece .pfx quando há mimetype
                                     desconhecido na lista (o usuário só conseguia arrastar o arquivo).
                                     A validação de formato é feita no servidor + leitura via openssl. --}}
                                <input type="file" name="certificado" form="formCertificado" class="form-control">
                                <div class="form-text">Arquivo <strong>.pfx</strong> ou <strong>.p12</strong> (certificado A1), máximo 2MB.</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Senha</label>
                                <input type="password" name="certificado_senha" form="formCertificado" class="form-control" autocomplete="off">
                                <div class="form-text">Senha definida na emissão do certificado.</div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" form="formCertificado" class="btn btn-primary w-100">
                                    <i class="bi bi-upload me-1"></i> Enviar
                                </button>
                            </div>
                            <div class="col-12">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    O arquivo é enviado diretamente ao Focus NFe e <strong>não é armazenado</strong> em nossos servidores.
                                    A senha também não é gravada.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                @error('certificado') <div class="alert alert-danger">{{ $message }}</div> @enderror
                @error('certificado_senha') <div class="alert alert-danger">{{ $message }}</div> @enderror

                {{-- ═══ Status SEFAZ (UF da unidade) ═══ --}}
                @if(!empty($ufSefaz))
                <div class="d-flex align-items-center gap-2 p-2 rounded-3 bg-light border mb-3" id="sefaz-status-widget" data-uf="{{ $ufSefaz }}">
                    <i class="bi bi-broadcast fs-5 text-muted" data-role="icon"></i>
                    <div class="flex-grow-1">
                        <strong class="small">SEFAZ {{ $ufSefaz }}</strong>
                        <span class="text-muted small ms-2" data-role="mensagem">consultando...</span>
                    </div>
                    <span class="badge bg-secondary" data-role="badge">--</span>
                    <button type="button" class="btn btn-sm btn-link text-muted" data-role="refresh" title="Atualizar">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
                @endif

                <div id="aviso-nenhum-tipo" class="alert alert-warning d-flex align-items-start mb-3 d-none">
                    <i class="bi bi-exclamation-triangle me-2 fs-5 mt-1"></i>
                    <div>
                        <strong>Emissão fiscal ativada, mas nenhum tipo marcado.</strong><br>
                        <small>Habilite ao menos <strong>NF-e</strong> (para empresas/transporte) ou
                        <strong>NFC-e</strong> (para cupom fiscal no PDV) nos cards abaixo.</small>
                    </div>
                </div>

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
                                <label class="form-label fw-semibold">
                                    Série NF-e
                                    <span class="badge bg-danger bg-opacity-10 text-danger small ms-1">★ obrigatório</span>
                                </label>
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
                                <label class="form-label fw-semibold">
                                    Série NFC-e
                                    <span class="badge bg-danger bg-opacity-10 text-danger small ms-1">★ obrigatório</span>
                                </label>
                                <input type="text" name="serie_nfce" class="form-control @error('serie_nfce') is-invalid @enderror"
                                       value="{{ old('serie_nfce', $config->serie_nfce ?? '1') }}" placeholder="1">
                                @error('serie_nfce')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    CSC (Código de Segurança SEFAZ)
                                    <span class="badge bg-danger bg-opacity-10 text-danger small ms-1">★ obrigatório</span>
                                    <x-erp.fiscal-tooltip field="csc" />
                                </label>
                                <input type="text" name="csc_nfce" class="form-control @error('csc_nfce') is-invalid @enderror"
                                       value="{{ old('csc_nfce', $config->csc_nfce) }}" placeholder="Obtido no portal SEFAZ do seu estado (ex: e-CAC)">
                                @error('csc_nfce')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <small class="form-text">Sem CSC + ID, a SEFAZ rejeita NFC-e mesmo em homologação.</small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">
                                    ID CSC
                                    <span class="badge bg-danger bg-opacity-10 text-danger small ms-1">★ obrigatório</span>
                                </label>
                                <input type="text" name="csc_id_nfce" class="form-control @error('csc_id_nfce') is-invalid @enderror"
                                       value="{{ old('csc_id_nfce', $config->csc_id_nfce) }}" placeholder="1">
                                @error('csc_id_nfce')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            Como obter CSC: <strong>portal SEFAZ do seu estado</strong> (e-CAC ou similar) → menu "NFC-e" → "Gerar CSC". Você recebe o código e o ID (1 ou 2).
                        </small>

                        {{-- Migração de outro sistema: a numeração é da Focus, não do ERP.
                             Sem série nova (ou reinício de numeração pedido à Focus) a SEFAZ
                             rejeita por duplicidade. --}}
                        <div class="alert alert-warning py-2 px-3 small mt-3 mb-0">
                            <i class="bi bi-123 me-1"></i>
                            <strong>Veio de outro sistema?</strong> O <strong>número</strong> da NFC-e é controlado
                            pela Focus NFe (não existe campo "última NFC-e" aqui — o ERP não numera a nota).
                            Se a série informada acima já foi usada no sistema anterior, escolha uma
                            <strong>série nova</strong> (ex.: a última + 1) ou peça à Focus para iniciar a numeração
                            no próximo número. Repetir série + número já emitido = rejeição da SEFAZ por duplicidade.
                        </div>
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
                                <label class="form-label fw-semibold">Item LC 116 <x-erp.fiscal-tooltip field="item_lc116" /></label>
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

                        {{-- Sub-seção: padrão NFS-e --}}
                        <hr class="my-3">
                        <label class="form-label fw-semibold d-block">Padrão da NFS-e <x-erp.fiscal-tooltip field="nfse_padrao" /></label>
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check" name="nfse_padrao" id="padrao_municipal"
                                   value="municipal" autocomplete="off"
                                   {{ old('nfse_padrao', $config->nfse_padrao ?? 'municipal') === 'municipal' ? 'checked' : '' }}>
                            <label class="btn btn-outline-primary" for="padrao_municipal">
                                <i class="bi bi-building me-1"></i>Municipal (prefeitura)
                            </label>
                            <input type="radio" class="btn-check" name="nfse_padrao" id="padrao_nacional"
                                   value="nacional" autocomplete="off"
                                   {{ old('nfse_padrao', $config->nfse_padrao ?? 'municipal') === 'nacional' ? 'checked' : '' }}>
                            <label class="btn btn-outline-primary" for="padrao_nacional">
                                <i class="bi bi-globe2 me-1"></i>Nacional (Portal RFB)
                            </label>
                        </div>
                        <small class="d-block text-muted mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Municipal</strong>: cada prefeitura valida a NFS-e (modelo legado). <strong>Nacional</strong>: novo padrão unificado da Receita Federal — obrigatório para cidades novas e em migração nas existentes até 2033.
                        </small>
                    </div>
                </div>

                {{-- ═══ Reforma Tributária (IBS / CBS / IS) ═══ --}}
                @php($reformaObrigatoria = \App\Services\FocusNFe\ReformaTributariaCalculator::obrigatoriaParaEmpresa(auth()->user()->empresa))
                <div class="erp-card mb-3 border {{ $reformaObrigatoria ? 'border-success border-opacity-50' : '' }}">
                    <div class="card-header bg-transparent d-flex align-items-center">
                        <i class="bi bi-stars fs-4 {{ $reformaObrigatoria ? 'text-success' : 'text-warning' }} me-2"></i>
                        <div class="flex-grow-1">
                            <strong>Reforma Tributária (EC 132/2023) — IBS / CBS / IS</strong>
                            <div class="small text-muted">
                                Novos tributos que substituem ICMS/ISS/PIS/COFINS/IPI na transição 2026-2033.
                            </div>
                        </div>
                        @if($reformaObrigatoria)
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Envio automático ativo</span>
                        @else
                            <span class="badge bg-warning text-dark">Simples Nacional — obrigatório em 01/2027</span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if($reformaObrigatoria)
                            <div class="alert alert-success small mb-3">
                                <i class="bi bi-check-circle me-1"></i>
                                <strong>Sua empresa é do regime normal ({{ auth()->user()->empresa?->regime_tributario?->value === 'lucro_real' ? 'Lucro Real' : 'Lucro Presumido' }})
                                — todas as NF-e, NFC-e e NFS-e já saem com IBS e CBS automaticamente.</strong>
                                Nada a configurar aqui. A SEFAZ <strong>rejeita</strong> notas do regime normal sem
                                esses campos a partir de <strong>agosto/2026</strong> (NT 2025.002, vigora em 03/08).
                            </div>
                        @else
                            <div class="alert alert-warning small mb-3">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                <strong>A partir de agosto/2026 (03/08) a SEFAZ rejeita NF-e/NFC-e sem IBS/CBS
                                para empresas do regime normal</strong> (Lucro Presumido/Real — NT 2025.002).
                                Sua empresa é do <strong>Simples Nacional</strong>: a obrigação começa em
                                <strong>04/01/2027</strong>. Se quiser <strong>antecipar</strong> o envio dos novos
                                campos nas suas notas, ligue as chaves abaixo — a plataforma calcula tudo sozinha.
                            </div>
                        @endif

                        {{-- Alíquotas em uso (2026 = fase de teste) --}}
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <div class="border rounded p-2 text-center h-100">
                                    <div class="small text-muted">CBS (federal) em 2026</div>
                                    <div class="fs-5 fw-bold">{{ $config->cbs_aliquota_padrao ? number_format((float) $config->cbs_aliquota_padrao, 2, ',', '.') : '0,90' }}%</div>
                                    <div class="small text-muted">{{ $config->cbs_aliquota_padrao ? 'personalizada' : 'alíquota-teste legal' }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-2 text-center h-100">
                                    <div class="small text-muted">IBS (estadual) em 2026</div>
                                    <div class="fs-5 fw-bold">{{ $config->ibs_aliquota_padrao ? number_format((float) $config->ibs_aliquota_padrao, 2, ',', '.') : '0,10' }}%</div>
                                    <div class="small text-muted">{{ $config->ibs_aliquota_padrao ? 'personalizada' : 'alíquota-teste legal' }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-2 text-center h-100">
                                    <div class="small text-muted">Impacto no cliente</div>
                                    <div class="fs-5 fw-bold">R$ 0,00</div>
                                    <div class="small text-muted">compensado via PIS/COFINS em 2026</div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info small mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Cada produto sai com CST <code>000</code> e classificação tributária
                            (cClassTrib) <code>000001</code> — tributação integral, o caso comum do varejo.
                            Produto com isenção/redução? Informe o CST e o cClassTrib próprios na
                            <strong>ficha do produto</strong> (aba Fiscal). Alíquotas em branco = valores legais.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                @if($reformaObrigatoria)
                                    {{-- Regime normal: envio automático — chaves ocultas, valor preservado --}}
                                    <input type="hidden" name="ibs_ativo" value="{{ old('ibs_ativo', $config->ibs_ativo ?? false) ? 1 : 0 }}">
                                    <div class="small fw-semibold mb-1">
                                        IBS <x-erp.fiscal-tooltip field="ibs" />
                                        <span class="badge bg-success bg-opacity-10 text-success">automático</span>
                                    </div>
                                @else
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="ibs_ativo" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="switch_ibs"
                                               name="ibs_ativo" value="1"
                                               {{ old('ibs_ativo', $config->ibs_ativo ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="switch_ibs">
                                            <strong>IBS</strong> <small class="text-muted">(estadual/municipal)</small>
                                            <x-erp.fiscal-tooltip field="ibs" />
                                        </label>
                                    </div>
                                @endif
                                <label class="form-label small mt-2">Alíquota padrão (%)</label>
                                <input type="number" step="0.0001" min="0" max="100" name="ibs_aliquota_padrao"
                                       class="form-control form-control-sm"
                                       value="{{ old('ibs_aliquota_padrao', $config->ibs_aliquota_padrao ?? '') }}"
                                       placeholder="0,1 (teste 2026)">
                            </div>
                            <div class="col-md-4">
                                @if($reformaObrigatoria)
                                    <input type="hidden" name="cbs_ativo" value="{{ old('cbs_ativo', $config->cbs_ativo ?? false) ? 1 : 0 }}">
                                    <div class="small fw-semibold mb-1">
                                        CBS <x-erp.fiscal-tooltip field="cbs" />
                                        <span class="badge bg-success bg-opacity-10 text-success">automático</span>
                                    </div>
                                @else
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="cbs_ativo" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="switch_cbs"
                                               name="cbs_ativo" value="1"
                                               {{ old('cbs_ativo', $config->cbs_ativo ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="switch_cbs">
                                            <strong>CBS</strong> <small class="text-muted">(federal)</small>
                                            <x-erp.fiscal-tooltip field="cbs" />
                                        </label>
                                    </div>
                                @endif
                                <label class="form-label small mt-2">Alíquota padrão (%)</label>
                                <input type="number" step="0.0001" min="0" max="100" name="cbs_aliquota_padrao"
                                       class="form-control form-control-sm"
                                       value="{{ old('cbs_aliquota_padrao', $config->cbs_aliquota_padrao ?? '') }}"
                                       placeholder="0,9 (teste 2026)">
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_ativo" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="switch_is"
                                           name="is_ativo" value="1"
                                           {{ old('is_ativo', $config->is_ativo ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="switch_is">
                                        <strong>IS</strong> <small class="text-muted">(seletivo — bebidas/cigarros)</small>
                                        <x-erp.fiscal-tooltip field="is" />
                                    </label>
                                </div>
                                <small class="d-block text-muted mt-3">
                                    Só para produtos nocivos (bebidas, cigarros...). Alíquota na ficha do item.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ═══ Mensagem fixa nas notas ═══ --}}
                <div class="erp-card mb-3 border">
                    <div class="card-header bg-transparent d-flex align-items-center">
                        <i class="bi bi-chat-left-text fs-4 text-secondary me-2"></i>
                        <div class="flex-grow-1">
                            <strong>Informações complementares</strong>
                            <div class="small text-muted">
                                Mensagem fixa impressa em todas as NF-e e NFC-e (campo "informações adicionais do contribuinte").
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <textarea name="informacoes_complementares" rows="3"
                                  class="form-control @error('informacoes_complementares') is-invalid @enderror"
                                  placeholder="Ex.: Mercadoria não retirada em 30 dias será cobrada armazenagem. Trocas em até 7 dias com o cupom.">{{ old('informacoes_complementares', $config->informacoes_complementares) }}</textarea>
                        @error('informacoes_complementares')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="form-text">
                            As observações digitadas na venda continuam saindo também — as duas aparecem separadas por " | ".
                            Não use este campo para informação fiscal obrigatória (ICMS, ST, etc.), que já sai automática.
                        </small>
                    </div>
                </div>

                {{-- ═══ Responsável Técnico (NT 2018/003) ═══ --}}
                <div class="erp-card mb-3 border border-danger border-opacity-25">
                    <div class="card-header bg-transparent d-flex align-items-center">
                        <i class="bi bi-person-badge fs-4 text-primary me-2"></i>
                        <div class="flex-grow-1">
                            <strong>Responsável técnico</strong>
                            <span class="badge bg-danger bg-opacity-10 text-danger small ms-1">★ obrigatório</span>
                            <div class="small text-muted">
                                Dados de quem cuida da TI / cadastro fiscal da SUA empresa — vai na NF-e (NT 2018/003).
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info small d-flex mb-3">
                            <i class="bi bi-info-circle me-2 fs-5"></i>
                            <div>
                                <strong>Quem preencher aqui?</strong>
                                Geralmente o <strong>dono da empresa</strong>, o <strong>contador</strong>,
                                ou a <strong>pessoa de TI</strong> que cuida do sistema fiscal.
                                Esses dados ficam visíveis na NF-e, e a SEFAZ pode usar pra contato em caso de problema técnico.
                                @auth
                                    <button type="button" class="btn btn-sm btn-outline-primary mt-2 d-block" id="btn-usar-dados-dono">
                                        <i class="bi bi-person-fill-check me-1"></i> Usar meus dados ({{ auth()->user()->name }})
                                    </button>
                                @endauth
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">CNPJ/CPF <span class="text-danger">*</span></label>
                                <input type="text" name="responsavel_tecnico_cnpj" data-mask="cnpj"
                                       class="form-control @error('responsavel_tecnico_cnpj') is-invalid @enderror"
                                       value="{{ old('responsavel_tecnico_cnpj', $config->responsavel_tecnico_cnpj) }}"
                                       placeholder="00.000.000/0000-00" maxlength="18">
                                @error('responsavel_tecnico_cnpj')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Nome do contato <span class="text-danger">*</span></label>
                                <input type="text" name="responsavel_tecnico_nome"
                                       class="form-control @error('responsavel_tecnico_nome') is-invalid @enderror"
                                       value="{{ old('responsavel_tecnico_nome', $config->responsavel_tecnico_nome) }}"
                                       maxlength="60">
                                @error('responsavel_tecnico_nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">E-mail <span class="text-danger">*</span></label>
                                <input type="email" name="responsavel_tecnico_email"
                                       class="form-control @error('responsavel_tecnico_email') is-invalid @enderror"
                                       value="{{ old('responsavel_tecnico_email', $config->responsavel_tecnico_email) }}"
                                       maxlength="60">
                                @error('responsavel_tecnico_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefone <span class="text-danger">*</span></label>
                                <input type="text" name="responsavel_tecnico_telefone" data-mask="telefone"
                                       class="form-control @error('responsavel_tecnico_telefone') is-invalid @enderror"
                                       value="{{ old('responsavel_tecnico_telefone', $config->responsavel_tecnico_telefone) }}"
                                       maxlength="14">
                                @error('responsavel_tecnico_telefone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <small class="d-block text-muted mt-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Sem responsável técnico cadastrado, a SEFAZ rejeita a NF-e com erro 778 desde a NT 2018/003.
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

{{-- Forms auxiliares — declarados FORA do form principal (HTML não permite
     forms aninhados; aninhados o navegador descarta a tag interna e o botão
     passa a submeter o form errado). Os botões/inputs referenciam via form="". --}}
<form id="formCertificado" action="{{ route('app.configuracao-fiscal.certificado') }}" method="POST" enctype="multipart/form-data" class="d-none">
    @csrf
</form>
@if(auth()->user()->is_admin)
    <form id="formProvisionarFocus" method="POST" action="{{ route('admin.empresas.saude-focus.resincronizar', $config->empresa_id) }}" class="d-none">
        @csrf
        <input type="hidden" name="unidade_id" value="{{ $config->unidade_id }}">
    </form>
@endif
@endsection

@push('scripts')
<script>
// Auto-preencher Responsável Técnico com dados do usuário logado
(function() {
    const btn = document.getElementById('btn-usar-dados-dono');
    if (!btn) return;
    btn.addEventListener('click', () => {
        const empresaCnpj = @json(auth()->user()->empresa?->cnpj ?? '');
        const nome = @json(auth()->user()->name);
        const email = @json(auth()->user()->email);
        const set = (n, v) => { const el = document.querySelector(`[name="${n}"]`); if (el && !el.value) el.value = v; };
        set('responsavel_tecnico_cnpj', empresaCnpj);
        set('responsavel_tecnico_nome', nome);
        set('responsavel_tecnico_email', email);
        // telefone: tenta usar o da empresa (não temos no scope JS); deixa vazio
    });
})();

// Status SEFAZ — badge + auto-refresh a cada 60s
(function() {
    const widget = document.getElementById('sefaz-status-widget');
    if (!widget) return;

    const uf = widget.dataset.uf;
    const badge = widget.querySelector('[data-role="badge"]');
    const icon = widget.querySelector('[data-role="icon"]');
    const msg = widget.querySelector('[data-role="mensagem"]');
    const refreshBtn = widget.querySelector('[data-role="refresh"]');

    const cores = {
        online:       { badge: 'bg-success', icon: 'bi-broadcast text-success',  label: 'Online' },
        instavel:     { badge: 'bg-warning', icon: 'bi-exclamation-triangle text-warning', label: 'Instável' },
        offline:      { badge: 'bg-danger',  icon: 'bi-x-circle text-danger',    label: 'Offline' },
        desconhecido: { badge: 'bg-secondary', icon: 'bi-question-circle text-muted', label: '—' },
    };

    async function consultar() {
        refreshBtn.disabled = true;
        refreshBtn.querySelector('i').classList.add('spinner-border', 'spinner-border-sm');
        refreshBtn.querySelector('i').classList.remove('bi-arrow-clockwise');
        try {
            const res = await fetch('{{ route("app.configuracao-fiscal.sefaz-status") }}?uf=' + encodeURIComponent(uf), {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            const config = cores[data.situacao] || cores.desconhecido;

            badge.className = 'badge ' + config.badge;
            badge.textContent = config.label;
            icon.className = 'bi fs-5 ' + config.icon;
            msg.textContent = data.mensagem + (data.consultado_em ? ' (' + data.consultado_em + ')' : '');
        } catch (e) {
            msg.textContent = 'Sem resposta — tente novamente.';
        } finally {
            refreshBtn.querySelector('i').classList.remove('spinner-border', 'spinner-border-sm');
            refreshBtn.querySelector('i').classList.add('bi-arrow-clockwise');
            refreshBtn.disabled = false;
        }
    }

    refreshBtn.addEventListener('click', consultar);
    consultar();
    setInterval(consultar, 60_000);
})();

// Alerta se ativou emissão fiscal mas não escolheu nenhum tipo
(function() {
    const fiscalSim = document.getElementById('fiscal_sim');
    const aviso = document.getElementById('aviso-nenhum-tipo');
    const switches = ['switch_nfe', 'switch_nfce', 'switch_nfse'].map(id => document.getElementById(id));

    function atualizar() {
        if (!fiscalSim || !aviso) return;
        const algumMarcado = switches.some(s => s && s.checked);
        aviso.classList.toggle('d-none', !fiscalSim.checked || algumMarcado);
    }
    fiscalSim && fiscalSim.addEventListener('change', atualizar);
    document.getElementById('fiscal_nao')?.addEventListener('change', atualizar);
    switches.forEach(s => s && s.addEventListener('change', atualizar));
    atualizar();
})();

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
