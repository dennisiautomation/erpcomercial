@extends('layouts.app')

@section('title', 'Integrações')

@section('content')
<x-erp.page-header title="Integrações" icon="plug" subtitle="O que cada empresa ligou e o que a IA365 configura uma vez para todas">
</x-erp.page-header>

{{-- 17/11/2026: situação das ligações que são da PLATAFORMA (uma vez para todas as empresas) --}}
@php
    $todasEmpresas = \App\Models\Empresa::withoutGlobalScopes();
    $plataforma = [
        [
            'icone' => 'box-seam', 'nome' => 'Melhor Envio', 'onde' => 'Aplicativo IA365, configurado nesta tela',
            'ok' => $melhorEnvio['configurado'],
            'estado' => $melhorEnvio['configurado'] ? 'Configurado · ' . ($melhorEnvio['ambiente'] === 'sandbox' ? 'sandbox' : 'produção') : 'Falta Client ID / Secret',
            'link' => '#melhor-envio',
        ],
        [
            'icone' => 'bank', 'nome' => 'Open Finance (Tecnospeed)', 'onde' => 'Chaves no servidor; liberação por plano e por empresa',
            'ok' => filled(config('services.tecnospeed.client_id')) && filled(config('services.tecnospeed.client_secret')),
            'estado' => (filled(config('services.tecnospeed.client_id')) ? 'Chaves gravadas' : 'Sem chaves')
                . ' · aparece em ' . (clone $todasEmpresas)->where('open_finance_ocultar', false)->count() . ' de ' . (clone $todasEmpresas)->count() . ' empresas',
            'link' => route('admin.planos.index'),
        ],
        [
            'icone' => 'receipt', 'nome' => 'Cobrança pelo caixa.ia365', 'onde' => 'Faturas da IA365 nascem no caixa e aparecem no ERP',
            'ok' => filled(config('services.caixa_ia365.token')),
            'estado' => (filled(config('services.caixa_ia365.token')) ? 'Ligado' : 'Sem segredo')
                . ' · ' . (clone $todasEmpresas)->whereNotNull('caixa_cliente_id')->count() . ' empresa(s) vinculada(s)',
            'link' => route('admin.financeiro.index'),
        ],
        [
            'icone' => 'chat-dots', 'nome' => 'Assistente de ajuda', 'onde' => 'Ligado/desligado para todas aqui; exceção em cada empresa',
            'ok' => \App\Services\Ajuda\AjudaLiberacao::servidor() && \App\Services\Ajuda\AjudaLiberacao::paraTodas(),
            'estado' => ! \App\Services\Ajuda\AjudaLiberacao::servidor() ? 'Sem AJUDA_ATIVO no servidor'
                : ((\App\Services\Ajuda\AjudaLiberacao::paraTodas() ? 'Ligado para todas' : 'Desligado para todas')
                    . ' · ' . (clone $todasEmpresas)->whereNotNull('ajuda_modo')->count() . ' exceção(ões)'),
            'link' => '#ajuda',
        ],
    ];
@endphp
<div class="plataforma-faixa mb-4" aria-label="Ligações da plataforma">
    @foreach($plataforma as $item)
        <a href="{{ $item['link'] }}" class="plataforma-item">
            <span class="plataforma-icone {{ $item['ok'] ? 'is-ok' : 'is-pendente' }}"><i class="bi bi-{{ $item['icone'] }}"></i></span>
            <span class="plataforma-texto">
                <span class="plataforma-nome">{{ $item['nome'] }}</span>
                <span class="plataforma-estado {{ $item['ok'] ? 'text-success-emphasis' : 'text-warning-emphasis' }}">
                    <i class="bi bi-{{ $item['ok'] ? 'check-circle-fill' : 'exclamation-circle-fill' }} me-1"></i>{{ $item['estado'] }}
                </span>
                <span class="plataforma-onde">{{ $item['onde'] }}</span>
            </span>
        </a>
    @endforeach
</div>

{{-- 25/09/2026: painel geral. As integrações de pagamento e entrega são DE CADA EMPRESA
     (a conta dela) e se configuram na aba Integração da empresa; aqui é só a visão de todas. --}}
<div class="card detail-card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-buildings me-1"></i> Integrações por empresa</h6>
        <span class="small text-muted">{{ $empresas->count() }} {{ $empresas->count() === 1 ? 'empresa' : 'empresas' }}</span>
    </div>
    <div class="card-body p-0">
        @if($empresas->isEmpty())
            <x-erp.empty-state title="Nenhuma empresa ligou integração ainda" icon="plug"
                description="O Agente IA, PIX, cartão e entrega se ligam na aba Integração de cada empresa." />
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Empresa</th>
                            <th class="text-center">Agente IA</th>
                            @foreach($colunas as $rotulo)
                                <th class="text-center">{{ $rotulo }}</th>
                            @endforeach
                            <th class="text-center">Cartão em uso</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($empresas as $linha)
                        <tr>
                            <td class="ps-3">
                                <a href="{{ route('admin.empresas.show', $linha['empresa']->id) }}">{{ $linha['empresa']->nome_fantasia ?: $linha['empresa']->razao_social }}</a>
                                @foreach($linha['falhas'] as $provedorFalha => $falha)
                                    <div class="small text-danger text-truncate" style="max-width: 22rem" title="{{ $falha }}">
                                        <i class="bi bi-x-circle me-1"></i>{{ $colunas[$provedorFalha] ?? $provedorFalha }}: {{ $falha }}
                                    </div>
                                @endforeach
                            </td>
                            <td class="text-center">
                                @if($linha['agente']?->ativo)
                                    <span class="badge bg-success bg-opacity-10 text-success">Ativo</span>
                                    @if(! $linha['agente']->plataformaRegistrada())
                                        <div class="small text-muted" title="O app.ia365 ainda não registrou onde recebe os avisos">sem avisos</div>
                                    @endif
                                @elseif($linha['agente'])
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary">Inativo</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            @foreach($colunas as $provedor => $rotulo)
                                @php
                                    $gw = $linha['gateways']->get($provedor);
                                @endphp
                                <td class="text-center">
                                    @if(! $gw)
                                        <span class="text-muted">—</span>
                                    @elseif($gw->capacidadeAtiva())
                                        <span class="badge bg-success bg-opacity-10 text-success">Ativo</span>
                                        @if($provedor === \App\Models\EmpresaGateway::PROVEDOR_MELHOR_ENVIO && $gw->token_expira_em)
                                            <div class="small text-muted">token até {{ $gw->token_expira_em->format('d/m') }}</div>
                                        @endif
                                    @elseif($gw->ativo)
                                        <span class="badge bg-warning bg-opacity-10 text-warning" title="Ligado, mas falta credencial — o agente não oferece">Incompleto</span>
                                    @else
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary">Inativo</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-center small">
                                {{ $linha['cartao'] ? \App\Services\Pagamento\CartaoPedidoService::rotulo($linha['cartao']) : '—' }}
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('admin.empresas.show', $linha['empresa']->id) }}" class="btn btn-sm btn-outline-secondary"
                                   title="Abrir a aba Integração da empresa"><i class="bi bi-gear"></i></a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    <div class="card-footer small text-muted">
        <strong>Ativo</strong> = ligado e com credencial (o agente oferece) · <strong>Incompleto</strong> = ligado sem credencial ·
        <strong>Inativo</strong> = desligado · <strong>—</strong> = nunca configurado. Para mudar, abra a empresa → aba <em>Integração</em>.
    </div>
</div>

{{-- Assistente de ajuda (22/11/2026): chave geral; a exceção de cada empresa fica em Admin → Empresa --}}
@php
    $ajServidor = \App\Services\Ajuda\AjudaLiberacao::servidor();
    $ajTodas = \App\Services\Ajuda\AjudaLiberacao::paraTodas();
    $ajExcecoes = \App\Models\Empresa::withoutGlobalScopes()->whereNotNull('ajuda_modo')
        ->orderBy('nome_fantasia')->get(['id', 'nome_fantasia', 'razao_social', 'ajuda_modo']);
@endphp
<div class="card detail-card shadow-sm mb-4" id="ajuda">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-chat-dots me-1"></i> Assistente de ajuda</h6>
        @if(! $ajServidor)
            <span class="badge rounded-pill text-bg-light border">Desligado no servidor</span>
        @elseif($ajTodas)
            <span class="badge rounded-pill text-bg-success">Ligado para todas</span>
        @else
            <span class="badge rounded-pill text-bg-light border">Desligado para todas</span>
        @endif
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.integracoes.ajuda.store') }}" class="d-flex flex-wrap gap-3 align-items-center">
            @csrf
            <div class="form-check">
                <input class="form-check-input" type="radio" name="ajuda_todas" id="ajuda_todas_1" value="1" @checked($ajTodas)>
                <label class="form-check-label" for="ajuda_todas_1"><strong>Ligado</strong> para todas as empresas</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="ajuda_todas" id="ajuda_todas_0" value="0" @checked(! $ajTodas)>
                <label class="form-check-label" for="ajuda_todas_0"><strong>Desligado</strong> para todas as empresas</label>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
        </form>
        <p class="small text-muted mt-3 mb-2">
            Para tirar ou colocar uma empresa só: Admin → Empresas → Editar → cartão "Assistente de ajuda".
            @unless($ajServidor) <strong>O servidor está sem AJUDA_ATIVO: nada aparece, para nenhuma empresa.</strong> @endunless
        </p>
        @if($ajExcecoes->isNotEmpty())
            <div class="small"><strong>Exceções:</strong>
                @foreach($ajExcecoes as $ex)
                    <a href="{{ route('admin.empresas.edit', $ex->id) }}#sec-ajuda" class="badge rounded-pill {{ $ex->ajuda_modo === 'ligado' ? 'text-bg-success' : 'text-bg-secondary' }} text-decoration-none">
                        {{ $ex->nome_fantasia ?: $ex->razao_social }} · {{ $ex->ajuda_modo === 'ligado' ? 'ligado sempre' : 'desligado sempre' }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>

<div class="row g-4 align-items-start" id="melhor-envio">
    <div class="col-lg-7">
        <div class="card detail-card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <h6 class="mb-0"><i class="bi bi-box-seam me-1"></i> Melhor Envio — aplicativo IA365</h6>
                @if($melhorEnvio['configurado'])
                    <span class="badge bg-success bg-opacity-10 text-success">Configurado · {{ $melhorEnvio['ambiente'] === 'sandbox' ? 'sandbox' : 'produção' }}</span>
                @else
                    <span class="badge bg-warning bg-opacity-10 text-warning-emphasis">Falta Client ID / Secret</span>
                @endif
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.integracoes.melhor-envio.store') }}" class="row g-3">
                    @csrf
                    <div class="col-md-4">
                        <label for="campo-client_id" class="form-label small mb-1">Client ID</label>
                        <input id="campo-client_id" type="text" name="client_id" class="form-control"
                               placeholder="{{ filled($melhorEnvio['client_id']) ? '•••• salvo (' . $melhorEnvio['client_id'] . ') — preencha p/ trocar' : 'ex.: 29391' }}">
                    </div>
                    <div class="col-md-8">
                        <label for="campo-client_secret" class="form-label small mb-1">Secret</label>
                        <input id="campo-client_secret" type="password" name="client_secret" class="form-control" autocomplete="new-password"
                               placeholder="{{ filled($melhorEnvio['client_secret']) ? '•••• salvo — preencha p/ trocar' : 'Secret do aplicativo (fica cifrado no banco)' }}">
                    </div>
                    <div class="col-md-6">
                        <label for="campo-email_suporte" class="form-label small mb-1">E-mail de suporte técnico</label>
                        <input id="campo-email_suporte" type="email" name="email_suporte" class="form-control" value="{{ $melhorEnvio['email'] }}">
                        <div class="form-text">Vai no cabeçalho <code>User-Agent</code> de toda chamada (o Melhor Envio exige).</div>
                    </div>
                    <div class="col-md-6">
                        <label for="campo-ambiente" class="form-label small mb-1">Ambiente</label>
                        <select id="campo-ambiente" name="ambiente" class="form-select">
                            <option value="producao" @selected($melhorEnvio['ambiente'] !== 'sandbox')>Produção (melhorenvio.com.br)</option>
                            <option value="sandbox" @selected($melhorEnvio['ambiente'] === 'sandbox')>Sandbox (sandbox.melhorenvio.com.br — app separado)</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="campo-callback" class="form-label small mb-1">URL de redirecionamento (cadastre EXATAMENTE assim no aplicativo)</label>
                        <div class="input-group">
                            <input id="campo-callback" type="text" class="form-control font-monospace small" value="{{ $callbackUrl }}" readonly onclick="this.select()">
                            <button class="btn btn-outline-secondary" type="button" data-copiar="{{ $callbackUrl }}">
                                <i class="bi bi-clipboard me-1"></i><span>Copiar</span>
                            </button>
                        </div>
                        <div class="form-text">Uma URL para todas as empresas — o retorno traz um código assinado que diz de qual empresa é a autorização.</div>
                    </div>
                    <div class="col-12">
                        <details class="avancado">
                            <summary>Avançado: escopos pedidos na autorização</summary>
                            <label for="campo-scopes" class="visually-hidden">Escopos pedidos na autorização</label>
                            <input id="campo-scopes" type="text" name="scopes" class="form-control form-control-sm mt-2" value="{{ $melhorEnvio['scopes'] }}">
                            <div class="form-text">Padrão: <code>{{ $scopesPadrao }}</code>. Só mude se o Melhor Envio recusar um escopo.</div>
                        </details>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="passos-card">
            <h6 class="fw-semibold mb-1">Como ligar o Melhor Envio</h6>
            <p class="small text-muted mb-3">Só o Melhor Envio precisa de algo aqui. PIX Sicredi, Asaas, InfinitePay e Uber usam a conta de cada empresa — não têm nada nesta tela.</p>
            <ol class="passos">
                <li>No Melhor Envio, em <strong>Integrações → Área do desenvolvedor</strong>, cadastre o aplicativo IA365 com a URL de redirecionamento ao lado.</li>
                <li>Cole o <strong>Client ID</strong> e o <strong>Secret</strong> aqui e salve. É um aplicativo só, para todas as empresas.</li>
                <li>Cada empresa autoriza a própria conta pelo botão <em>Conectar Melhor Envio</em> na aba Integração dela — ninguém copia chave.</li>
            </ol>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .plataforma-faixa { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 17rem), 1fr)); gap: 0.75rem; }
    .plataforma-item {
        display: flex; gap: 0.75rem; align-items: flex-start; padding: 0.875rem 1rem;
        background: var(--bg-card, #fff); border: 1px solid var(--border, #e2e8f0); border-radius: 0.75rem;
        color: inherit; text-decoration: none; transition: border-color .15s ease, box-shadow .15s ease;
    }
    .plataforma-item:hover { border-color: var(--border-hover, #cbd5e1); box-shadow: 0 2px 8px rgba(15, 23, 42, .06); }
    .plataforma-icone {
        flex: none; width: 2.25rem; height: 2.25rem; border-radius: 0.5rem; display: grid; place-items: center; font-size: 1.1rem;
    }
    .plataforma-icone.is-ok { background: var(--success-light, #ecfdf5); color: var(--success-text, #047857); }
    .plataforma-icone.is-pendente { background: var(--warning-light, #fffbeb); color: var(--warning-text, #b45309); }
    .plataforma-texto { display: flex; flex-direction: column; min-width: 0; }
    .plataforma-nome { font-weight: 600; color: var(--text-primary, #0f172a); }
    .plataforma-estado { font-size: 0.8125rem; font-weight: 500; }
    .plataforma-onde { font-size: 0.75rem; color: var(--text-muted, #64748b); margin-top: 0.125rem; }
    .passos-card { padding: 1.25rem; border-radius: 0.75rem; background: var(--primary-light, #eef2ff); }
    .passos { counter-reset: passo; list-style: none; padding: 0; margin: 0; display: grid; gap: 0.75rem; }
    .passos li { counter-increment: passo; position: relative; padding-left: 2.25rem; font-size: 0.875rem; line-height: 1.5; color: var(--text-secondary, #475569); }
    .passos li::before {
        content: counter(passo); position: absolute; left: 0; top: 0; width: 1.5rem; height: 1.5rem; border-radius: 50%;
        background: var(--primary, #4f46e5); color: #fff; font-size: 0.75rem; font-weight: 700; display: grid; place-items: center;
    }
    .avancado summary { cursor: pointer; font-size: 0.8125rem; color: var(--text-secondary, #475569); }
    .avancado summary:focus-visible { outline: 2px solid var(--primary, #4f46e5); outline-offset: 2px; border-radius: 4px; }
</style>
@endpush

@push('scripts')
<script>
document.querySelectorAll('[data-copiar]').forEach(function (botao) {
    botao.addEventListener('click', function () {
        const rotulo = botao.querySelector('span');
        navigator.clipboard.writeText(botao.dataset.copiar).then(function () {
            rotulo.textContent = 'Copiado';
            setTimeout(function () { rotulo.textContent = 'Copiar'; }, 2000);
        });
    });
});
</script>
@endpush
