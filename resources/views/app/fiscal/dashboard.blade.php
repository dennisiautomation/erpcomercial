@extends('layouts.app')

@section('title', 'Dashboard Fiscal')

@section('content')
<div class="container-fluid">
    <x-erp.page-header title="Dashboard Fiscal" icon="speedometer2"
        subtitle="Visão consolidada dos últimos 30 dias">
        <a href="{{ route('app.configuracao-fiscal.edit') }}" class="btn btn-outline-secondary">
            <i class="bi bi-gear"></i> Configuração
        </a>
    </x-erp.page-header>

    {{-- ─── Stat cards ─── --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <x-erp.stat-card icon="check-circle" color="success"
                :value="$porStatus['Autorizada'] ?? 0" label="Autorizadas" />
        </div>
        <div class="col-md-3">
            <x-erp.stat-card icon="clock-history" color="warning"
                :value="$porStatus['Pendente'] ?? 0" label="Pendentes" />
        </div>
        <div class="col-md-3">
            <x-erp.stat-card icon="x-circle" color="danger"
                :value="$porStatus['Rejeitada'] ?? 0" label="Rejeitadas" />
        </div>
        <div class="col-md-3">
            <x-erp.stat-card icon="slash-circle" color="secondary"
                :value="$porStatus['Cancelada'] ?? 0" label="Canceladas" />
        </div>
    </div>

    <div class="row g-3">
        {{-- ─── Por tipo ─── --}}
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-0">
                    <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Por tipo de nota</h6>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse(['NF-e' => 'nfe', 'NFC-e' => 'nfce', 'NFS-e' => 'nfse'] as $label => $key)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $label }}</span>
                            <strong>{{ $porTipo[$key] ?? 0 }}</strong>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">Sem dados</li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- ─── Top 5 erros ─── --}}
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-0">
                    <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Top 5 erros SEFAZ</h6>
                </div>
                @if($topErros->isEmpty())
                    <div class="card-body text-center text-success">
                        <i class="bi bi-shield-check fs-1"></i>
                        <p class="mb-0">Nenhuma rejeição nos últimos 30 dias.</p>
                    </div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($topErros as $erro)
                            <li class="list-group-item d-flex justify-content-between">
                                <span class="text-truncate me-2" style="max-width: 80%;" title="{{ $erro->focus_mensagem }}">
                                    {{ Str::limit($erro->focus_mensagem, 100) }}
                                </span>
                                <span class="badge bg-danger">{{ $erro->total }}×</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- ─── Saúde por unidade ─── --}}
        <div class="col-12 mt-3">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-0">
                    <h6 class="mb-0"><i class="bi bi-building me-2"></i>Saúde por unidade</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Unidade</th>
                                <th>Focus</th>
                                <th>Certificado</th>
                                <th>Webhooks</th>
                                <th>Último backup</th>
                                <th>SEFAZ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($configs as $config)
                                <tr>
                                    <td>{{ $config->unidade->nome ?? '—' }}</td>
                                    <td>
                                        @if($config->focus_empresa_id)
                                            <span class="badge bg-success">#{{ $config->focus_empresa_id }}</span>
                                        @else
                                            <span class="badge bg-secondary">não criada</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($config->temCertificadoValido())
                                            @php $d = $config->diasParaVencerCertificado(); @endphp
                                            <span class="badge bg-{{ $d < 7 ? 'danger' : ($d < 30 ? 'warning' : 'success') }}">
                                                {{ $d }}d
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php $n = is_array($config->focus_webhook_ids) ? count($config->focus_webhook_ids) : 0; @endphp
                                        <span class="badge bg-{{ $n > 0 ? 'success' : 'warning' }}">{{ $n }} ativos</span>
                                    </td>
                                    <td class="small text-muted">
                                        {{ $ultimosBackups[$config->unidade_id] ?? '—' }}
                                    </td>
                                    <td>
                                        @if($config->unidade?->uf)
                                            <span class="badge bg-light text-dark border sefaz-status" data-uf="{{ $config->unidade->uf }}">…</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ─── Série diária ─── --}}
        <div class="col-12 mt-3">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-0">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Emissões — últimos 14 dias</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartSerieDiaria" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = @json($serieDiaria->pluck('dia'));
const dados = @json($serieDiaria->pluck('total'));

new Chart(document.getElementById('chartSerieDiaria'), {
    type: 'line',
    data: {
        labels,
        datasets: [{
            label: 'Notas emitidas',
            data: dados,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13,110,253,.1)',
            tension: .3,
            fill: true,
        }],
    },
    options: {
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
    },
});

document.querySelectorAll('.sefaz-status').forEach(el => {
    const uf = el.dataset.uf;
    fetch(`/app/configuracao-fiscal/sefaz-status?uf=${uf}`)
        .then(r => r.json())
        .then(d => {
            const status = d.status || '—';
            const map = { online: 'success', instavel: 'warning', offline: 'danger' };
            el.classList.remove('bg-light', 'text-dark');
            el.classList.add('bg-' + (map[status] || 'secondary'), 'text-white');
            el.textContent = status;
        })
        .catch(() => el.textContent = '—');
});
</script>
@endpush
@endsection
