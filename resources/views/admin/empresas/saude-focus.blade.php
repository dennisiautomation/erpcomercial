@extends('layouts.app')

@section('title', 'Saúde Focus NFe — ' . $empresa->razao_social)

@section('content')
<div class="container-fluid">
    <x-erp.page-header title="Saúde Focus NFe" icon="shield-check"
        subtitle="{{ $empresa->razao_social }}">
        @php
            $pendentes = $unidades->filter(fn ($u) => ! $u['tem_focus'] || ! $u['tem_webhooks'])->count();
        @endphp
        @if($master_disponivel && $pendentes > 0)
            <form method="POST" action="{{ route('admin.empresas.saude-focus.resincronizar', $empresa) }}" class="d-inline">
                @csrf
                <button class="btn btn-success" data-confirm="Provisionar/resincronizar {{ $pendentes }} unidade(s) na Focus NFe?">
                    <i class="bi bi-magic me-1"></i> Provisionar {{ $pendentes }} pendente(s)
                </button>
            </form>
        @endif
        <a href="{{ route('admin.empresas.show', $empresa) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </x-erp.page-header>

    @if(! $master_disponivel)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Token master Focus NFe não configurado.</strong>
            Defina <code>FOCUS_MASTER_TOKEN</code> no <code>.env</code> para habilitar o auto-provisionamento.
        </div>
    @endif

    <div class="row g-3">
        @foreach($unidades as $u)
            @php
                $unidade = $u['unidade'];
                $config = $u['config'];
                $statusGeral = $u['tem_focus'] && $u['tem_tokens'] && $u['tem_webhooks'];
            @endphp
            <div class="col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-{{ $statusGeral ? 'success' : 'warning' }} bg-opacity-10 border-0">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-{{ $statusGeral ? 'check-circle-fill text-success' : 'exclamation-circle-fill text-warning' }} fs-4 me-2"></i>
                            <div class="flex-grow-1">
                                <h6 class="mb-0">{{ $unidade->nome }}</h6>
                                <small class="text-muted">CNPJ {{ $unidade->cnpj ?: $empresa->cnpj }}</small>
                            </div>
                            @if($u['pode_provisionar'])
                                <form method="POST" action="{{ route('admin.empresas.saude-focus.resincronizar', $empresa) }}" class="m-0">
                                    @csrf
                                    <input type="hidden" name="unidade_id" value="{{ $unidade->id }}">
                                    <button class="btn btn-sm btn-outline-primary" data-confirm="Refazer toda a configuração desta unidade na Focus NFe?">
                                        <i class="bi bi-arrow-repeat"></i> Resincronizar
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-building me-2"></i>Empresa na Focus</span>
                            @if($u['tem_focus'])
                                <span class="badge bg-success">ID {{ $config->focus_empresa_id }}</span>
                            @else
                                <span class="badge bg-danger">não criada</span>
                            @endif
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-key me-2"></i>Tokens emitidos</span>
                            @if($u['tem_tokens'])
                                <span>
                                    @if($config->focus_token_homologacao) <span class="badge bg-info">homolog</span> @endif
                                    @if($config->focus_token_producao) <span class="badge bg-success">produção</span> @endif
                                </span>
                            @else
                                <span class="badge bg-danger">faltando</span>
                            @endif
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-shield-lock me-2"></i>Certificado A1</span>
                            @if($u['tem_certificado'])
                                <span class="badge bg-{{ $u['dias_cert'] < 30 ? 'warning' : 'success' }}">
                                    vence em {{ $u['dias_cert'] }}d
                                </span>
                            @else
                                <span class="badge bg-secondary">não enviado</span>
                            @endif
                        </li>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span><i class="bi bi-broadcast me-2"></i>Webhooks ativos ({{ $u['webhooks_count'] }})</span>
                                @if($u['ultimo_sync'])
                                    <small class="text-muted">sync {{ $u['ultimo_sync']->diffForHumans() }}</small>
                                @endif
                            </div>
                            @if($u['eventos_ativos'])
                                <div class="d-flex flex-wrap gap-1 mt-1">
                                    @foreach($u['eventos_ativos'] as $evento)
                                        <span class="badge bg-light text-dark border">{{ $evento }}</span>
                                    @endforeach
                                </div>
                            @else
                                <small class="text-danger">nenhum webhook cadastrado — status fiscal só atualiza por polling</small>
                            @endif
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-geo me-2"></i>SEFAZ {{ $unidade->uf ?: '?' }}</span>
                            @if($unidade->uf)
                                <span class="badge bg-light text-dark border sefaz-status" data-uf="{{ $unidade->uf }}">
                                    consultando…
                                </span>
                            @else
                                <small class="text-muted">UF não definida</small>
                            @endif
                        </li>
                    </ul>
                </div>
            </div>
        @endforeach
    </div>

    @if($empresa->unidades->isEmpty())
        <x-erp.empty-state title="Nenhuma unidade cadastrada"
            icon="building"
            description="Cadastre uma unidade para esta empresa antes de configurar a Focus NFe." />
    @endif
</div>

@push('scripts')
<script>
document.querySelectorAll('.sefaz-status').forEach(el => {
    const uf = el.dataset.uf;
    fetch(`/app/configuracao-fiscal/sefaz-status?uf=${uf}`)
        .then(r => r.json())
        .then(d => {
            const status = d.status || 'desconhecido';
            const map = { online: 'success', instavel: 'warning', offline: 'danger' };
            el.classList.remove('bg-light', 'text-dark');
            el.classList.add('bg-' + (map[status] || 'secondary'), 'text-white');
            el.textContent = status;
        })
        .catch(() => el.textContent = 'sem dados');
});
</script>
@endpush
@endsection
