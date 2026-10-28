@extends('layouts.app')

@section('title', 'Integrações')

@section('content')
<x-erp.page-header title="Integrações" icon="plug">
</x-erp.page-header>

{{-- 25/09/2026: painel geral. As integrações de pagamento e entrega são DE CADA EMPRESA
     (a conta dela) e se configuram na aba Integração da empresa; aqui é só a visão de todas. --}}
<div class="card detail-card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-buildings me-1"></i> Integrações por empresa</h6>
        <span class="small text-muted">{{ $empresas->count() }} {{ $empresas->count() === 1 ? 'empresa' : 'empresas' }}</span>
    </div>
    <div class="card-body p-0">
        @if($empresas->isEmpty())
            <p class="text-muted small p-3 mb-0">Nenhuma empresa ligou o Agente IA ou uma integração ainda. Isso se faz na aba <em>Integração</em> de cada empresa.</p>
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

<h6 class="text-muted text-uppercase small fw-semibold mb-2">Configuração da plataforma</h6>
<p class="text-muted small">
    Só o que é da <strong>IA365</strong> e vale para todas as empresas. Hoje apenas o <strong>Melhor Envio</strong> precisa disso
    (exige um aplicativo IA365 cadastrado uma vez). PIX Sicredi, Asaas, InfinitePay e Uber usam só a conta de cada empresa —
    não têm nada aqui.
</p>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card detail-card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-box-seam me-1"></i> Melhor Envio — aplicativo IA365</h6>
                @if($melhorEnvio['configurado'])
                    <span class="badge bg-success bg-opacity-10 text-success">Configurado · {{ $melhorEnvio['ambiente'] === 'sandbox' ? 'sandbox' : 'produção' }}</span>
                @else
                    <span class="badge bg-warning bg-opacity-10 text-warning">Falta Client ID / Secret</span>
                @endif
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    É o aplicativo cadastrado em <strong>Melhor Envio → Integrações → Área do desenvolvedor</strong>.
                    Um só para todas as empresas: cada uma autoriza a própria conta pelo botão
                    <em>Conectar Melhor Envio</em> na aba Integração dela — ninguém copia chave.
                </p>
                <form method="POST" action="{{ route('admin.integracoes.melhor-envio.store') }}" class="row g-2">
                    @csrf
                    <div class="col-md-4">
                        <label for="campo-client_id" class="form-label small mb-1">Client ID</label>
                        <input id="campo-client_id" type="text" name="client_id" class="form-control form-control-sm"
                               placeholder="{{ filled($melhorEnvio['client_id']) ? '•••• salvo (' . $melhorEnvio['client_id'] . ') — preencha p/ trocar' : 'ex.: 29391' }}">
                    </div>
                    <div class="col-md-8">
                        <label for="campo-client_secret" class="form-label small mb-1">Secret</label>
                        <input id="campo-client_secret" type="password" name="client_secret" class="form-control form-control-sm" autocomplete="new-password"
                               placeholder="{{ filled($melhorEnvio['client_secret']) ? '•••• salvo — preencha p/ trocar' : 'Secret do aplicativo (fica cifrado no banco)' }}">
                    </div>
                    <div class="col-md-6">
                        <label for="campo-email_suporte" class="form-label small mb-1">E-mail de suporte técnico</label>
                        <input id="campo-email_suporte" type="email" name="email_suporte" class="form-control form-control-sm" value="{{ $melhorEnvio['email'] }}">
                        <div class="form-text">Vai no cabeçalho <code>User-Agent</code> de toda chamada (o Melhor Envio exige).</div>
                    </div>
                    <div class="col-md-6">
                        <label for="campo-ambiente" class="form-label small mb-1">Ambiente</label>
                        <select id="campo-ambiente" name="ambiente" class="form-select form-select-sm">
                            <option value="producao" @selected($melhorEnvio['ambiente'] !== 'sandbox')>Produção (melhorenvio.com.br)</option>
                            <option value="sandbox" @selected($melhorEnvio['ambiente'] === 'sandbox')>Sandbox (sandbox.melhorenvio.com.br — app separado)</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="campo-scopes" class="form-label small mb-1">Escopos pedidos na autorização</label>
                        <input id="campo-scopes" type="text" name="scopes" class="form-control form-control-sm" value="{{ $melhorEnvio['scopes'] }}">
                        <div class="form-text">Padrão: <code>{{ $scopesPadrao }}</code>. Só mude se o Melhor Envio recusar um escopo.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-1">URL de redirecionamento (cadastre EXATAMENTE assim no aplicativo)</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" value="{{ $callbackUrl }}" readonly onclick="this.select()">
                            <button class="btn btn-outline-secondary" type="button"
                                    onclick="navigator.clipboard.writeText('{{ $callbackUrl }}'); this.textContent='Copiado'">Copiar</button>
                        </div>
                        <div class="form-text">Uma URL para todas as empresas — o retorno traz um código assinado que diz de qual empresa é a autorização.</div>
                    </div>
                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn-sm btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
