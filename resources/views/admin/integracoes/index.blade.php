@extends('layouts.app')

@section('title', 'Integrações da plataforma')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-plug me-2"></i>Integrações da plataforma</h4>
</div>

<p class="text-muted">
    Credenciais que são da <strong>IA365</strong> e valem para todas as empresas-clientes. O que é de cada
    empresa (PIX Sicredi, Uber, Asaas, a conta do Melhor Envio) fica na aba <em>Integração</em> da empresa.
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
                        <label class="form-label small mb-1">Client ID</label>
                        <input type="text" name="client_id" class="form-control form-control-sm"
                               placeholder="{{ filled($melhorEnvio['client_id']) ? '•••• salvo (' . $melhorEnvio['client_id'] . ') — preencha p/ trocar' : 'ex.: 29391' }}">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small mb-1">Secret</label>
                        <input type="password" name="client_secret" class="form-control form-control-sm" autocomplete="new-password"
                               placeholder="{{ filled($melhorEnvio['client_secret']) ? '•••• salvo — preencha p/ trocar' : 'Secret do aplicativo (fica cifrado no banco)' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1">E-mail de suporte técnico</label>
                        <input type="email" name="email_suporte" class="form-control form-control-sm" value="{{ $melhorEnvio['email'] }}">
                        <div class="form-text">Vai no cabeçalho <code>User-Agent</code> de toda chamada (o Melhor Envio exige).</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Ambiente</label>
                        <select name="ambiente" class="form-select form-select-sm">
                            <option value="producao" @selected($melhorEnvio['ambiente'] !== 'sandbox')>Produção (melhorenvio.com.br)</option>
                            <option value="sandbox" @selected($melhorEnvio['ambiente'] === 'sandbox')>Sandbox (sandbox.melhorenvio.com.br — app separado)</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-1">Escopos pedidos na autorização</label>
                        <input type="text" name="scopes" class="form-control form-control-sm" value="{{ $melhorEnvio['scopes'] }}">
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

    <div class="col-lg-5">
        <div class="card detail-card shadow-sm h-100">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-buildings me-1"></i> Empresas conectadas ao Melhor Envio</h6></div>
            <div class="card-body p-0">
                @if($conectadas->isEmpty())
                    <p class="text-muted small p-3 mb-0">Nenhuma empresa conectou a conta ainda. O botão fica na aba Integração de cada empresa.</p>
                @else
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th class="ps-3">Empresa</th><th>Conta</th><th>Token até</th><th class="text-center">Status</th></tr></thead>
                        <tbody>
                        @foreach($conectadas as $gw)
                            <tr>
                                <td class="ps-3"><a href="{{ route('admin.empresas.show', $gw->empresa_id) }}#integracao">{{ $gw->empresa?->nome_fantasia ?? ('#' . $gw->empresa_id) }}</a></td>
                                <td class="small">{{ $gw->config['conta_email'] ?? $gw->config['conta_nome'] ?? '—' }}</td>
                                <td class="small">{{ optional($gw->token_expira_em)->format('d/m/Y') ?? '—' }}</td>
                                <td class="text-center">
                                    @if(filled($gw->access_token) && $gw->ativo)
                                        <span class="badge bg-success bg-opacity-10 text-success">Conectado</span>
                                    @elseif(filled($gw->access_token))
                                        <span class="badge bg-warning bg-opacity-10 text-warning">Inativo</span>
                                    @else
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary">Sem token</span>
                                    @endif
                                    @if($gw->ultima_falha)
                                        <div class="small text-danger" title="{{ $gw->ultima_falha }}">falha</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
