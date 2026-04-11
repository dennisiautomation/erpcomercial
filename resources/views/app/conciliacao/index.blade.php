@extends('layouts.app')

@section('title', 'Conciliacao Bancaria')

@section('content')
<div class="fade-in">
<div class="page-header">
    <div>
        <h4><i class="bi bi-bank me-2"></i>Conciliacao Bancaria</h4>
        <div class="subtitle">Importe extratos e concilie lancamentos</div>
    </div>
    <a href="{{ route('app.conciliacao.create') }}" class="btn btn-erp btn-erp-primary">
        <i class="bi bi-upload me-1"></i> Importar OFX
    </a>
</div>

<div class="erp-card">
    <div class="table-responsive">
        <table class="erp-table">
            <thead>
                <tr>
                    <th>Banco</th>
                    <th>Agencia / Conta</th>
                    <th>Periodo</th>
                    <th>Lancamentos</th>
                    <th>Conciliados</th>
                    <th>Status</th>
                    <th>Criado em</th>
                    <th width="100">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($conciliacoes as $c)
                <tr>
                    <td>{{ $c->banco }}</td>
                    <td>{{ $c->agencia ?? '-' }} / {{ $c->conta ?? '-' }}</td>
                    <td>{{ $c->periodo_inicio?->format('d/m/Y') }} a {{ $c->periodo_fim?->format('d/m/Y') }}</td>
                    <td>{{ $c->total_lancamentos }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height: 8px;">
                                @php $pct = $c->total_lancamentos > 0 ? round(($c->conciliados / $c->total_lancamentos) * 100) : 0; @endphp
                                <div class="progress-bar bg-success" style="width: {{ $pct }}%"></div>
                            </div>
                            <small class="text-muted">{{ $c->conciliados }}/{{ $c->total_lancamentos }}</small>
                        </div>
                    </td>
                    <td>
                        @php
                            $statusLabel = match($c->status) {
                                'concluida' => 'Concluida',
                                'em_andamento' => 'Em Andamento',
                                'pendente' => 'Pendente',
                                default => $c->status,
                            };
                        @endphp
                        <span class="badge-status {{ $c->status }}">{{ $statusLabel }}</span>
                    </td>
                    <td>{{ $c->created_at?->format('d/m/Y') }}</td>
                    <td>
                        <div class="action-btns">
                            <a href="{{ route('app.conciliacao.show', $c) }}" class="btn btn-sm btn-outline-primary" title="Ver">
                                <i class="bi bi-eye"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="bi bi-bank d-block"></i>
                            <h5>Nenhuma conciliacao encontrada</h5>
                            <p>Importe um arquivo OFX para comecar.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($conciliacoes->hasPages())
        <div class="card-body border-top">
            {{ $conciliacoes->links() }}
        </div>
    @endif
</div>
</div>
@endsection
