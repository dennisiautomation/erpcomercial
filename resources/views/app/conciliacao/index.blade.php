@extends('layouts.app')

@section('title', 'Conciliacao Bancaria')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-bank me-2"></i>Conciliacao Bancaria</h4>
    <a href="{{ route('app.conciliacao.create') }}" class="btn btn-primary">
        <i class="bi bi-upload me-1"></i> Importar OFX
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
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
                            $statusClass = match($c->status) {
                                'concluida' => 'success',
                                'em_andamento' => 'info',
                                'pendente' => 'warning',
                                default => 'secondary',
                            };
                            $statusLabel = match($c->status) {
                                'concluida' => 'Concluida',
                                'em_andamento' => 'Em Andamento',
                                'pendente' => 'Pendente',
                                default => $c->status,
                            };
                        @endphp
                        <span class="badge bg-{{ $statusClass }}">{{ $statusLabel }}</span>
                    </td>
                    <td>{{ $c->created_at?->format('d/m/Y') }}</td>
                    <td>
                        <a href="{{ route('app.conciliacao.show', $c) }}" class="btn btn-sm btn-outline-primary" title="Ver">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">Nenhuma conciliacao encontrada. Importe um arquivo OFX para comecar.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $conciliacoes->links() }}
</div>
@endsection
