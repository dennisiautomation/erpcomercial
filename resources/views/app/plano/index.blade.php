@extends('layouts.app')

@section('title', 'Meu Plano')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-credit-card-2-front me-2"></i>Meu Plano</h4>
    <a href="{{ route('app.plano.comparar') }}" class="btn btn-outline-primary">
        <i class="bi bi-arrow-left-right me-1"></i> Comparar Planos
    </a>
</div>

{{-- Trial Banner --}}
@if($empresa->em_trial && $empresa->isTrialActive())
    <div class="alert alert-warning d-flex align-items-center mb-4">
        <i class="bi bi-clock-history fs-4 me-3"></i>
        <div>
            <strong>Periodo de avaliacao</strong> &mdash;
            Restam <strong>{{ $empresa->diasRestantesTrial() }}</strong> dias no seu trial.
            <a href="{{ route('app.plano.comparar') }}">Assine agora</a> para nao perder acesso.
        </div>
    </div>
@endif

{{-- Current Plan Card --}}
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Plano Atual</h6>
        @if($planoAtual)
            <span class="badge bg-primary fs-6">{{ $planoAtual->nome }}</span>
        @else
            <span class="badge bg-secondary fs-6">Sem plano</span>
        @endif
    </div>
    <div class="card-body">
        @if($planoAtual)
            <div class="row mb-3">
                <div class="col-md-4">
                    <small class="text-muted">Valor Mensal</small>
                    <div class="fw-bold fs-5">R$ {{ number_format($planoAtual->preco_mensal, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <small class="text-muted">Tipo de Cobranca</small>
                    <div class="fw-bold">{{ ucfirst($empresa->tipo_cobranca) }}</div>
                </div>
                <div class="col-md-4">
                    <small class="text-muted">Status</small>
                    <div>
                        @if($empresa->isTrialActive())
                            <span class="badge bg-warning text-dark">Trial ({{ $empresa->diasRestantesTrial() }} dias)</span>
                        @elseif($empresa->isAssinaturaAtiva())
                            <span class="badge bg-success">Ativo</span>
                            @if($empresa->assinatura_fim)
                                <small class="text-muted ms-1">ate {{ $empresa->assinatura_fim->format('d/m/Y') }}</small>
                            @endif
                        @else
                            <span class="badge bg-danger">Expirado</span>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <p class="text-muted mb-0">Nenhum plano vinculado. Entre em contato com o suporte.</p>
        @endif
    </div>
</div>

{{-- Usage Stats --}}
@if($planoAtual)
<div class="card shadow-sm mb-4">
    <div class="card-header"><h6 class="mb-0">Uso do Plano</h6></div>
    <div class="card-body">
        <div class="row g-4">
            @php
                $recursos = [
                    'unidades' => ['label' => 'Unidades', 'icon' => 'bi-building'],
                    'usuarios' => ['label' => 'Usuarios', 'icon' => 'bi-people'],
                    'produtos' => ['label' => 'Produtos', 'icon' => 'bi-box'],
                    'notas'    => ['label' => 'Notas Fiscais (mes)', 'icon' => 'bi-file-earmark-text'],
                ];
            @endphp
            @foreach($recursos as $key => $info)
                @php
                    $atual = $uso[$key]['atual'];
                    $limite = $uso[$key]['limite'];
                    $pct = $limite > 0 ? round(($atual / $limite) * 100) : 0;
                    $pct = min($pct, 100);
                    $cor = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success');
                @endphp
                <div class="col-md-6">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span><i class="bi {{ $info['icon'] }} me-1"></i> {{ $info['label'] }}</span>
                        <span class="fw-semibold">{{ number_format($atual, 0, ',', '.') }} / {{ number_format($limite, 0, ',', '.') }}</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-{{ $cor }}" role="progressbar"
                             style="width: {{ $pct }}%"
                             aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Features --}}
<div class="card shadow-sm mb-4">
    <div class="card-header"><h6 class="mb-0">Funcionalidades do Plano</h6></div>
    <div class="card-body">
        <div class="row g-2">
            @php
                $featureList = [
                    'pdv'         => 'PDV',
                    'fiscal'      => 'Fiscal (NF-e, NFC-e, NFS-e)',
                    'multilojas'  => 'Multilojas',
                    'os'          => 'Ordens de Servico',
                    'contratos'   => 'Contratos / Recorrencia',
                    'conciliacao' => 'Conciliacao Bancaria',
                    'dre'         => 'DRE',
                    'boletos'     => 'Boletos',
                    'api'         => 'API Externa',
                ];
            @endphp
            @foreach($featureList as $key => $label)
                <div class="col-md-4">
                    @if($planoAtual->isFeatureEnabled($key))
                        <i class="bi bi-check-circle-fill text-success me-1"></i> {{ $label }}
                    @else
                        <i class="bi bi-x-circle-fill text-muted me-1"></i>
                        <span class="text-muted">{{ $label }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- Upgrade CTA --}}
@if($planoAtual && $planoAtual->slug !== 'enterprise')
    <div class="text-center">
        <a href="{{ route('app.plano.comparar') }}" class="btn btn-primary btn-lg">
            <i class="bi bi-rocket-takeoff me-1"></i> Fazer Upgrade
        </a>
    </div>
@endif
@endsection
