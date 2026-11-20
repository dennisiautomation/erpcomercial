@extends('layouts.app')

@section('title', 'Planos')

@section('content')
<x-erp.page-header title="Planos" icon="credit-card-2-front">
    <a href="{{ route('admin.planos.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Novo Plano
        </a>
</x-erp.page-header>

{{-- Contratação pelo site (20/11/2026): uma chave só para preços + teste grátis --}}
<div class="card shadow-sm mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <div class="flex-grow-1" style="min-width:260px">
            <div class="fw-semibold">
                Contratação pelo site
                <span class="badge {{ $contratacaoSite ? 'bg-success' : 'bg-secondary' }} ms-1">{{ $contratacaoSite ? 'Ligada' : 'Desligada' }}</span>
            </div>
            <div class="text-muted small">
                @if($contratacaoSite)
                    A página inicial mostra os preços dos planos ativos e o botão "Testar grátis". Quem se cadastra confirma o e-mail e entra em teste pelos dias do plano.
                @else
                    A página inicial mostra os planos como "Sob consulta", sem preço, e o cadastro de teste grátis fica fechado.
                @endif
                Empresas que já se cadastraram pelo site: {{ $cadastrosSite }}.
            </div>
        </div>
        <form method="POST" action="{{ route('admin.planos.contratacao-site') }}"
              onsubmit="return confirm('{{ $contratacaoSite ? 'Desligar a contratação pelo site? Os preços somem da página inicial e o cadastro de teste grátis fecha.' : 'Ligar a contratação pelo site? Os preços aparecem na página inicial e qualquer pessoa poderá criar uma conta de teste grátis.' }}')">
            @csrf
            <input type="hidden" name="ativa" value="{{ $contratacaoSite ? 0 : 1 }}">
            <button type="submit" class="btn {{ $contratacaoSite ? 'btn-outline-secondary' : 'btn-primary' }}">
                {{ $contratacaoSite ? 'Desligar' : 'Ligar contratação pelo site' }}
            </button>
        </form>
    </div>
    <div class="card-footer bg-transparent">
        <form method="POST" action="{{ route('admin.planos.contratacao-site.email') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-6">
                <label for="email_aviso" class="form-label fw-semibold mb-1">E-mail que recebe o aviso de cadastro novo</label>
                <input type="email" class="form-control @error('email_aviso') is-invalid @enderror" id="email_aviso" name="email_aviso"
                       value="{{ old('email_aviso', $emailAviso) }}" placeholder="voce@ia365.com.br" maxlength="160" autocomplete="off">
                @error('email_aviso')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-outline-primary">Salvar e-mail</button>
            </div>
            <div class="col-12 text-muted small">
                @if($emailAviso)
                    Cada empresa que confirmar o cadastro pelo site gera um aviso para este endereço.
                @else
                    Nenhum e-mail gravado: o aviso está indo para o remetente do sistema ({{ $emailAvisoPadrao ?: 'não configurado' }}), que normalmente ninguém lê.
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Ordem</th>
                    <th>Nome</th>
                    <th>Slug</th>
                    <th class="text-end">Mensal</th>
                    <th class="text-end">Anual</th>
                    <th class="text-center">Unid.</th>
                    <th class="text-center">Users</th>
                    <th class="text-center">Prod.</th>
                    <th class="text-center">Notas/mes</th>
                    <th class="text-center">Features</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Empresas</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($planos as $plano)
                    <tr>
                        <td>{{ $plano->ordem }}</td>
                        <td class="fw-semibold">{{ $plano->nome }}</td>
                        <td><code>{{ $plano->slug }}</code></td>
                        <td class="text-end">R$ {{ number_format($plano->preco_mensal, 2, ',', '.') }}</td>
                        <td class="text-end">R$ {{ number_format($plano->preco_anual, 2, ',', '.') }}</td>
                        <td class="text-center">{{ $plano->max_unidades }}</td>
                        <td class="text-center">{{ $plano->max_usuarios }}</td>
                        <td class="text-center">{{ number_format($plano->max_produtos, 0, ',', '.') }}</td>
                        <td class="text-center">{{ number_format($plano->max_notas_mes, 0, ',', '.') }}</td>
                        <td class="text-center">
                            @php
                                $features = [
                                    'PDV' => $plano->pdv_habilitado,
                                    'Fiscal' => $plano->fiscal_habilitado,
                                    'Multi' => $plano->multilojas_habilitado,
                                    'OS' => $plano->os_habilitado,
                                    'Contr.' => $plano->contratos_habilitado,
                                    'Conc.' => $plano->conciliacao_habilitada,
                                    'DRE' => $plano->dre_habilitado,
                                    'Bol.' => $plano->boletos_habilitado,
                                    'API' => $plano->api_habilitada,
                                ];
                            @endphp
                            @foreach($features as $label => $enabled)
                                <span class="badge {{ $enabled ? 'bg-success' : 'bg-secondary' }} me-1" title="{{ $label }}">
                                    {{ $label }}
                                </span>
                            @endforeach
                        </td>
                        <td class="text-center">
                            @if($plano->ativo)
                                <span class="badge bg-success">Ativo</span>
                            @else
                                <span class="badge bg-secondary">Inativo</span>
                            @endif
                        </td>
                        <td class="text-center">{{ $plano->empresas()->count() }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.planos.edit', $plano) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @if($plano->empresas()->count() === 0)
                                <form method="POST" action="{{ route('admin.planos.destroy', $plano) }}" class="d-inline"
                                      data-confirm="Tem certeza que deseja excluir este plano?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="text-center text-muted py-4">Nenhum plano cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
