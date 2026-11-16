@extends('layouts.app')

@section('title', 'Pagamento e entrega')

@php
    $rota = fn (string $provedor, string $acao) => route('app.pagamento-entrega.acao', [$provedor, $acao]);
    $salvo = fn ($valor, string $vazio) => filled($valor) ? '•••• salvo — preencha só para trocar' : $vazio;
    $rotulo = fn (string $p) => \App\Services\Pagamento\CartaoPedidoService::rotulo($p);
    $selo = function (string $estado) {
        [$cor, $texto] = match ($estado) {
            'ativo' => ['success', 'Ligado'],
            'incompleto' => ['warning', 'Falta completar'],
            default => ['secondary', 'Desligado'],
        };
        return '<span class="badge bg-' . $cor . ' bg-opacity-10 text-' . $cor . '" data-estado="' . $estado . '">' . $texto . '</span>';
    };
    $estadoPix = $pix?->ativo ? ($pix->utilizavel() ? 'ativo' : 'incompleto') : 'desligado';
    $estadoAsaas = $asaas?->ativo ? (filled($asaas->client_secret) ? 'ativo' : 'incompleto') : 'desligado';
    $estadoInfinite = $infinite?->capacidadeAtiva() ? 'ativo' : ($infinite?->ativo ? 'incompleto' : 'desligado');
    $meConectado = filled($me?->access_token);
    $estadoMe = $meConectado ? ($me->ativo ? 'ativo' : 'incompleto') : 'desligado';
    $estadoUber = $uber?->ativo
        ? (filled($uber->client_id) && filled($uber->client_secret) && filled($uber->config['customer_id'] ?? null) ? 'ativo' : 'incompleto')
        : 'desligado';
    $handle = $infinite?->config['handle'] ?? null;
@endphp

@push('styles')
<style>
    .pe-secao { font-size: .78rem; letter-spacing: .06em; text-transform: uppercase; font-weight: 600; color: var(--bs-secondary-color); margin: 1.75rem 0 .75rem; }
    .pe-card { scroll-margin-top: 5rem; }
    .pe-card .card-header { background: transparent; }
    .pe-card :target, .pe-card:target { outline: 2px solid var(--bs-primary); outline-offset: 2px; }
    .pe-onde { font-size: .8125rem; color: var(--bs-secondary-color); }
    .pe-onde summary { cursor: pointer; }
    .pe-falha { overflow-wrap: anywhere; }
</style>
@endpush

@section('content')
<x-erp.page-header title="Pagamento e entrega" icon="plug"
    subtitle="Ligue as contas da sua empresa para receber por PIX e cartão e para entregar">
    <a href="{{ route('app.configuracoes.edit') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Configurações
    </a>
</x-erp.page-header>

<div class="alert alert-info d-flex align-items-start">
    <i class="bi bi-shield-lock me-2 mt-1"></i>
    <div>
        As contas são <strong>da sua empresa</strong>: o dinheiro cai direto nelas. O que você colar aqui fica guardado
        cifrado e <strong>não aparece de novo</strong> na tela — para trocar, é só preencher outra vez.
        Depois de salvar, use <strong>Testar</strong> para ter certeza de que ligou.
        @if(\App\Models\LojaOnline::ativaPara(auth()->user()->empresa_id))
            O que estiver ligado aqui passa a valer na <a href="{{ route('app.loja-online.configuracoes') }}">loja online</a>.
        @endif
    </div>
</div>

<div class="pe-secao">Receber</div>
<div class="row g-4">
    {{-- PIX Sicredi --}}
    <div class="col-12">
        <div class="card shadow-sm pe-card" id="pix" data-provedor="pix">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-qr-code me-1"></i> PIX na hora — Sicredi</h6>
                {!! $selo($estadoPix) !!}
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2">
                    O pedido sai com PIX copia-e-cola da sua conta Sicredi e é confirmado sozinho quando o cliente paga.
                </p>
                <details class="pe-onde mb-3">
                    <summary>Onde eu pego isso?</summary>
                    Peça ao seu gerente do Sicredi a <strong>API PIX</strong> da conta. O banco entrega o Client ID, o Client Secret,
                    o certificado (.cer ou .pem) e a chave privada (.key). A chave privada precisa ser a versão <strong>sem senha</strong>.
                </details>
                <form method="POST" action="{{ $rota('pix', 'salvar') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label for="pix-client_id" class="form-label small mb-1">Client ID</label>
                        <input id="pix-client_id" type="text" name="client_id" class="form-control form-control-sm" autocomplete="off"
                               placeholder="{{ $salvo($pix?->client_id, 'Client ID que o Sicredi entregou') }}">
                    </div>
                    <div class="col-md-4">
                        <label for="pix-client_secret" class="form-label small mb-1">Client Secret</label>
                        <input id="pix-client_secret" type="password" name="client_secret" class="form-control form-control-sm" autocomplete="new-password"
                               placeholder="{{ $salvo($pix?->client_secret, 'Client Secret que o Sicredi entregou') }}">
                    </div>
                    <div class="col-md-4">
                        <label for="pix-chave_pix" class="form-label small mb-1">Chave PIX da conta</label>
                        <input id="pix-chave_pix" type="text" name="chave_pix" class="form-control form-control-sm"
                               value="{{ old('chave_pix', $pix?->chave_pix) }}" placeholder="CNPJ, e-mail ou chave aleatória">
                    </div>
                    <div class="col-md-4">
                        <label for="pix-certificado" class="form-label small mb-1">Certificado (.cer ou .pem) @if($pix?->cert_path)<span class="text-success">✓ enviado</span>@endif</label>
                        <input id="pix-certificado" type="file" name="certificado" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-4">
                        <label for="pix-chave_privada" class="form-label small mb-1">Chave privada sem senha (.key) @if($pix?->key_path)<span class="text-success">✓ enviada</span>@endif</label>
                        <input id="pix-chave_privada" type="file" name="chave_privada" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <label for="pix-expiracao" class="form-label small mb-1">Validade do PIX (segundos)</label>
                        <input id="pix-expiracao" type="number" name="expiracao_segundos" class="form-control form-control-sm"
                               value="{{ old('expiracao_segundos', $pix?->expiracao_segundos ?? 86400) }}" min="300" max="604800">
                    </div>
                    <div class="col-md-2">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" role="switch" name="ativo" value="1" id="pix-ativo" @checked($pix?->ativo)>
                            <label class="form-check-label small" for="pix-ativo">Ligado</label>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary w-100">Salvar</button>
                    </div>
                </form>
                <div class="d-flex flex-wrap gap-2 mt-3 align-items-center">
                    <form method="POST" action="{{ $rota('pix', 'testar') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled(! $pix?->utilizavel())>
                            <i class="bi bi-activity me-1"></i> Testar
                        </button>
                    </form>
                    <form method="POST" action="{{ $rota('pix', 'webhook') }}"
                          onsubmit="return confirm('Ligar o aviso de pagamento desta chave PIX a este sistema?\n\nSe a mesma chave avisa outro sistema hoje, ele deixa de ser avisado.')">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled(! $pix?->utilizavel())>
                            <i class="bi bi-broadcast me-1"></i> Ligar aviso de pagamento
                        </button>
                    </form>
                    @if($pix?->webhook_registrado_em)
                        <span class="small text-muted">Aviso ligado em {{ $pix->webhook_registrado_em->format('d/m/Y H:i') }}</span>
                    @elseif($pix?->utilizavel())
                        <span class="small text-muted">Sem o aviso ligado, o pedido não confirma sozinho.</span>
                    @endif
                    @if($pix?->ultima_falha)
                        <span class="small text-danger pe-falha w-100"><i class="bi bi-x-circle me-1"></i>Última falha: {{ $pix->ultima_falha }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Asaas --}}
    <div class="col-lg-6">
        <div class="card shadow-sm h-100 pe-card" id="asaas" data-provedor="asaas">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-credit-card me-1"></i> Cartão por link — Asaas</h6>
                {!! $selo($estadoAsaas) !!}
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2">
                    O pedido sai com um link para o cliente pagar no cartão; o pagamento confirma o pedido sozinho.
                </p>
                <details class="pe-onde mb-3">
                    <summary>Onde eu pego isso?</summary>
                    No Asaas: <strong>Minha conta → Integrações → Chave da API</strong> (começa com <code>$aact_</code>).
                    Depois de salvar aqui, cadastre no Asaas em <strong>Integrações → Webhooks</strong> o endereço e o código
                    que aparecem abaixo — é assim que o Asaas avisa que o cliente pagou.
                </details>
                <form method="POST" action="{{ $rota('asaas', 'salvar') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-12">
                        <label for="asaas-api_key" class="form-label small mb-1">Chave da API</label>
                        <input id="asaas-api_key" type="password" name="api_key" class="form-control form-control-sm" autocomplete="new-password"
                               placeholder="{{ $salvo($asaas?->client_secret, 'chave da API do Asaas ($aact_...)') }}">
                    </div>
                    <div class="col-auto form-check ms-2 mt-3">
                        <input type="hidden" name="sandbox" value="0">
                        <input class="form-check-input" type="checkbox" name="sandbox" value="1" id="asaas-sandbox" @checked($asaas?->config['sandbox'] ?? false)>
                        <label class="form-check-label small" for="asaas-sandbox">Conta de testes do Asaas</label>
                    </div>
                    <div class="col-auto form-check form-switch ms-2 mt-3">
                        <input type="hidden" name="ativo" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="ativo" value="1" id="asaas-ativo" @checked($asaas?->ativo)>
                        <label class="form-check-label small" for="asaas-ativo">Ligado</label>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-primary">Salvar</button>
                    </div>
                </form>
                @if(filled($asaas?->config['webhook_token'] ?? null))
                    <div class="small mt-3" data-webhook-asaas>
                        <div class="text-muted">Para cadastrar no Asaas (Webhooks):</div>
                        <div>Endereço: <code class="user-select-all">{{ url('/api/integracao/v1/webhooks/asaas') }}</code></div>
                        <div>Código de autenticação: <code class="user-select-all">{{ $asaas->config['webhook_token'] }}</code></div>
                    </div>
                @endif
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <form method="POST" action="{{ $rota('asaas', 'testar') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled(blank($asaas?->client_secret))>
                            <i class="bi bi-activity me-1"></i> Testar
                        </button>
                    </form>
                    @if($asaas?->ultima_falha)
                        <span class="small text-danger pe-falha"><i class="bi bi-x-circle me-1"></i>{{ $asaas->ultima_falha }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- InfinitePay --}}
    <div class="col-lg-6">
        <div class="card shadow-sm h-100 pe-card" id="infinitepay" data-provedor="infinitepay">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-credit-card-2-front me-1"></i> Cartão ou PIX por link — InfinitePay</h6>
                {!! $selo($estadoInfinite) !!}
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2">
                    O pedido sai com um link em que o cliente escolhe cartão de crédito ou PIX; o pagamento confirma o pedido sozinho.
                </p>
                <details class="pe-onde mb-3">
                    <summary>Onde eu pego isso?</summary>
                    É a sua <strong>InfiniteTag</strong>: o nome de usuário no aplicativo da InfinitePay (aparece como <code>$nome</code>).
                    Não há mais nada a cadastrar no painel da InfinitePay.
                </details>
                <form method="POST" action="{{ $rota('infinitepay', 'salvar') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-12">
                        <label for="infinite-handle" class="form-label small mb-1">InfiniteTag</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input id="infinite-handle" type="text" name="handle" class="form-control @error('handle') is-invalid @enderror" autocomplete="off"
                                   value="{{ old('handle') }}"
                                   placeholder="{{ filled($handle) ? 'salva: ' . $handle . ' — preencha só para trocar' : 'seu nome de usuário na InfinitePay' }}">
                            @error('handle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-auto form-check form-switch ms-2 mt-3">
                        <input type="hidden" name="ativo" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="ativo" value="1" id="infinite-ativo" @checked($infinite?->ativo)>
                        <label class="form-check-label small" for="infinite-ativo">Ligado</label>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-primary">Salvar</button>
                    </div>
                </form>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <form method="POST" action="{{ $rota('infinitepay', 'testar') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled(blank($handle))>
                            <i class="bi bi-activity me-1"></i> Testar (gera um link de R$ 1,00, não precisa pagar)
                        </button>
                    </form>
                    @if($infinite?->ultima_falha)
                        <span class="small text-danger pe-falha"><i class="bi bi-x-circle me-1"></i>{{ $infinite->ultima_falha }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Qual cartão vale: só importa com os dois ligados --}}
    @if(count($cartoesAtivos) >= 2)
    <div class="col-12">
        <div class="card shadow-sm pe-card" id="cartao" data-provedor="cartao">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-toggles me-1"></i> Qual link de cartão usar</h6>
                <span class="badge bg-success bg-opacity-10 text-success">{{ $cartaoEmUso ? $rotulo($cartaoEmUso) : 'Nenhum' }}</span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Asaas e InfinitePay estão ligados. O pedido sai com o link de <strong>um</strong> só — escolha qual.</p>
                <form method="POST" action="{{ $rota('cartao', 'escolher') }}" class="d-flex flex-wrap align-items-center gap-3">
                    @csrf
                    @foreach(\App\Models\EmpresaGateway::PROVEDORES_CARTAO as $p)
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="provedor_cartao" value="{{ $p }}" id="cartao-{{ $p }}"
                                   @checked($cartaoEmUso === $p) @disabled(! in_array($p, $cartoesAtivos, true))>
                            <label class="form-check-label small" for="cartao-{{ $p }}">
                                {{ $rotulo($p) }}
                                <span class="text-muted">— {{ $p === \App\Models\EmpresaGateway::PROVEDOR_INFINITEPAY ? 'cartão e PIX' : 'só cartão' }}</span>
                            </label>
                        </div>
                    @endforeach
                    <button type="submit" class="btn btn-sm btn-primary">Usar este</button>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>

<div class="pe-secao">Entregar</div>
<div class="row g-4 mb-4">
    {{-- Melhor Envio --}}
    <div class="col-lg-6">
        <div class="card shadow-sm h-100 pe-card" id="melhor-envio" data-provedor="melhor-envio">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-box-seam me-1"></i> Envio para outras cidades — Melhor Envio</h6>
                {!! $selo($estadoMe) !!}
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    O frete é calculado na hora (Correios, Jadlog, Loggi…) pelas medidas dos produtos e o cliente escolhe a opção.
                    Você não copia chave nenhuma: clica em conectar, entra com a <strong>sua conta</strong> do Melhor Envio e autoriza.
                </p>
                @unless($meLiberado)
                    <div class="alert alert-warning small py-2 mb-3" data-me-nao-liberado>
                        O Melhor Envio ainda não está liberado na plataforma. Fale com o suporte da IA365 para liberar.
                    </div>
                @endunless
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    @if($meConectado)
                        <span class="small"><i class="bi bi-person-check me-1"></i>{{ $me->config['conta_nome'] ?? '' }}
                            <span class="text-muted">{{ $me->config['conta_email'] ?? '' }}</span></span>
                        <form method="POST" action="{{ $rota('melhor-envio', 'testar') }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-activity me-1"></i> Testar</button>
                        </form>
                        <form method="POST" action="{{ $rota('melhor-envio', 'conectar') }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary" @disabled(! $meLiberado)>Conectar de novo</button>
                        </form>
                        <form method="POST" action="{{ $rota('melhor-envio', 'desconectar') }}"
                              onsubmit="return confirm('Desconectar a conta do Melhor Envio? O frete para outras cidades para de ser calculado.')">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger">Desconectar</button>
                        </form>
                    @else
                        <form method="POST" action="{{ $rota('melhor-envio', 'conectar') }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary" @disabled(! $meLiberado)>
                                <i class="bi bi-plug me-1"></i> Conectar minha conta do Melhor Envio
                            </button>
                        </form>
                    @endif
                    @if($me?->ultima_falha)
                        <span class="small text-danger pe-falha w-100"><i class="bi bi-x-circle me-1"></i>Última falha: {{ $me->ultima_falha }}</span>
                    @endif
                </div>
                <form method="POST" action="{{ $rota('melhor-envio', 'salvar') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-12"><div class="small fw-semibold">Pacote padrão — usado quando o produto não tem medida cadastrada</div></div>
                    @foreach(['altura' => ['Altura (cm)', '0.1', '1'], 'largura' => ['Largura (cm)', '0.1', '1'], 'comprimento' => ['Comprimento (cm)', '0.1', '1'], 'peso' => ['Peso (kg)', '0.01', '0.01']] as $campo => [$nome, $passo, $minimo])
                        <div class="col-6 col-md-3">
                            <label for="me-{{ $campo }}" class="form-label small mb-1">{{ $nome }}</label>
                            <input id="me-{{ $campo }}" type="number" step="{{ $passo }}" min="{{ $minimo }}" name="pacote_{{ $campo }}" class="form-control form-control-sm"
                                   value="{{ old('pacote_' . $campo, $me?->config['pacote_' . $campo] ?? $pacotePadrao[$campo]) }}">
                        </div>
                    @endforeach
                    <div class="col-md-7">
                        <label for="me-servicos" class="form-label small mb-1">Só estes serviços (números do Melhor Envio, por vírgula)</label>
                        <input id="me-servicos" type="text" name="servicos" class="form-control form-control-sm @error('servicos') is-invalid @enderror"
                               value="{{ old('servicos', $me?->config['servicos'] ?? '') }}" placeholder="vazio = todos · ex.: 1,2 (PAC e SEDEX)">
                    </div>
                    <div class="col-md-5 d-flex flex-wrap gap-3 pb-1">
                        <div class="form-check">
                            <input type="hidden" name="seguro" value="0">
                            <input class="form-check-input" type="checkbox" name="seguro" value="1" id="me-seguro" @checked($me?->config['seguro'] ?? true)>
                            <label class="form-check-label small" for="me-seguro">Enviar com seguro</label>
                        </div>
                        <div class="form-check form-switch">
                            <input type="hidden" name="ativo" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" name="ativo" value="1" id="me-ativo" @checked($me?->ativo)>
                            <label class="form-check-label small" for="me-ativo">Ligado</label>
                        </div>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Uber Direct --}}
    <div class="col-lg-6">
        <div class="card shadow-sm h-100 pe-card" id="uber" data-provedor="uber">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-scooter me-1"></i> Entrega na cidade — Uber Direct</h6>
                {!! $selo($estadoUber) !!}
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2">
                    Pagamento confirmado, o sistema chama o Uber sozinho: coleta na loja e entrega no endereço do cliente.
                    Fora do horário abaixo, ou se o Uber falhar, o pedido segue confirmado e a entrega fica por sua conta.
                </p>
                <details class="pe-onde mb-3">
                    <summary>Onde eu pego isso?</summary>
                    No painel do Uber Direct, em <strong>Desenvolvedor</strong>. O <strong>“ID de cliente do desenvolvedor”</strong> tem 32 letras e números,
                    sem traços. O <strong>“ID do usuário”</strong> é um código com traços. São diferentes — o sistema avisa se você trocar um pelo outro.
                </details>
                <form method="POST" action="{{ $rota('uber', 'salvar') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-6">
                        <label for="uber-client_id" class="form-label small mb-1">ID de cliente do desenvolvedor</label>
                        <input id="uber-client_id" type="text" name="client_id" class="form-control form-control-sm" autocomplete="off"
                               placeholder="{{ $salvo($uber?->client_id, '32 letras e números, sem traços') }}">
                    </div>
                    <div class="col-md-6">
                        <label for="uber-client_secret" class="form-label small mb-1">Client Secret</label>
                        <input id="uber-client_secret" type="password" name="client_secret" class="form-control form-control-sm" autocomplete="new-password"
                               placeholder="{{ $salvo($uber?->client_secret, 'Client Secret do painel do Uber') }}">
                    </div>
                    <div class="col-md-6">
                        <label for="uber-customer_id" class="form-label small mb-1">ID do usuário</label>
                        <input id="uber-customer_id" type="text" name="customer_id" class="form-control form-control-sm"
                               value="{{ old('customer_id', $uber?->config['customer_id'] ?? '') }}" placeholder="código com traços — ex.: 56d97aa0-8311-…">
                    </div>
                    <div class="col-md-6">
                        <label for="uber-ceps" class="form-label small mb-1">CEPs atendidos</label>
                        <input id="uber-ceps" type="text" name="ceps" class="form-control form-control-sm"
                               value="{{ old('ceps', $uber?->config['ceps'] ?? '') }}" placeholder="64000-64099,65630-65639 (vazio = todos)">
                    </div>
                    @foreach(['hora_inicio' => ['Seg a sex, das', 8], 'hora_fim' => ['até', 16.5], 'hora_inicio_sab' => ['Sábado, das', 9], 'hora_fim_sab' => ['até', 12]] as $campo => [$nome, $padrao])
                        <div class="col-6 col-md-3">
                            <label for="uber-{{ $campo }}" class="form-label small mb-1">{{ $nome }}</label>
                            <input id="uber-{{ $campo }}" type="number" step="0.5" min="0" max="24" name="{{ $campo }}" class="form-control form-control-sm"
                                   value="{{ old($campo, $uber?->config[$campo] ?? $padrao) }}">
                        </div>
                    @endforeach
                    <div class="col-12 form-text mt-1">Horas em número: 8 = 8h, 16,5 = 16h30.</div>
                    <div class="col-auto form-check form-switch ms-2 mt-3">
                        <input type="hidden" name="ativo" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="ativo" value="1" id="uber-ativo" @checked($uber?->ativo)>
                        <label class="form-check-label small" for="uber-ativo">Ligado</label>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-primary">Salvar</button>
                    </div>
                </form>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <form method="POST" action="{{ $rota('uber', 'testar') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled(blank($uber?->client_id) || blank($uber?->client_secret))>
                            <i class="bi bi-activity me-1"></i> Testar
                        </button>
                    </form>
                    @if($uber?->ultima_falha)
                        <span class="small text-danger pe-falha"><i class="bi bi-x-circle me-1"></i>{{ $uber->ultima_falha }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
