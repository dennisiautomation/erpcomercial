@extends('layouts.app')

@section('title', 'Conciliacao - ' . $conciliacao->banco)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-bank me-2"></i>Conciliacao - {{ $conciliacao->banco }}</h4>
    <div>
        @if($conciliacao->status !== 'concluida')
        <form action="{{ route('app.conciliacao.auto', $conciliacao) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-info" onclick="return confirm('Executar conciliacao automatica?')">
                <i class="bi bi-magic me-1"></i> Auto-Conciliar
            </button>
        </form>
        <form action="{{ route('app.conciliacao.finalizar', $conciliacao) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-success" onclick="return confirm('Finalizar esta conciliacao?')">
                <i class="bi bi-check-circle me-1"></i> Finalizar
            </button>
        </form>
        @endif
        <a href="{{ route('app.conciliacao.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

{{-- Info --}}
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-muted small">Periodo</div>
                <div class="fw-bold">{{ $conciliacao->periodo_inicio?->format('d/m/Y') }} - {{ $conciliacao->periodo_fim?->format('d/m/Y') }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-muted small">Agencia / Conta</div>
                <div class="fw-bold">{{ $conciliacao->agencia ?? '-' }} / {{ $conciliacao->conta ?? '-' }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-muted small">Saldo Final</div>
                <div class="fw-bold">R$ {{ number_format($conciliacao->saldo_final, 2, ',', '.') }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-muted small">Progresso</div>
                @php $pct = $conciliacao->total_lancamentos > 0 ? round(($conciliacao->conciliados / $conciliacao->total_lancamentos) * 100) : 0; @endphp
                <div class="progress mt-1" style="height: 20px;">
                    <div class="progress-bar bg-success" style="width: {{ $pct }}%">{{ $pct }}%</div>
                </div>
                <small class="text-muted">{{ $conciliacao->conciliados }} de {{ $conciliacao->total_lancamentos }}</small>
            </div>
        </div>
    </div>
</div>

{{-- Split View --}}
<div class="row">
    {{-- Left: Extratos --}}
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-list-ul me-1"></i> Extrato Bancario</h6>
            </div>
            <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Data</th>
                            <th>Descricao</th>
                            <th>Valor</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th width="80">Acao</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($extratos as $extrato)
                        <tr class="{{ $extrato->conciliado ? 'table-success' : '' }}">
                            <td>{{ $extrato->data?->format('d/m') }}</td>
                            <td title="{{ $extrato->descricao }}">{{ Str::limit($extrato->descricao, 35) }}</td>
                            <td class="{{ $extrato->tipo === 'credito' ? 'text-success' : 'text-danger' }}">
                                {{ $extrato->tipo === 'debito' ? '-' : '' }}R$ {{ number_format($extrato->valor, 2, ',', '.') }}
                            </td>
                            <td>
                                <span class="badge bg-{{ $extrato->tipo === 'credito' ? 'success' : 'danger' }}">
                                    {{ $extrato->tipo === 'credito' ? 'C' : 'D' }}
                                </span>
                            </td>
                            <td>
                                @if($extrato->conciliado)
                                    <span class="badge bg-success"><i class="bi bi-check"></i> Conciliado</span>
                                @else
                                    <span class="badge bg-warning">Pendente</span>
                                @endif
                            </td>
                            <td>
                                @if(!$extrato->conciliado && $conciliacao->status !== 'concluida')
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalConciliar{{ $extrato->id }}">
                                        <i class="bi bi-link-45deg"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Right: Contas Pendentes --}}
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-cash-stack me-1"></i> Contas a Receber Pendentes</h6>
            </div>
            <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                <table class="table table-sm mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Descricao</th>
                            <th>Valor</th>
                            <th>Vencimento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contasReceber as $cr)
                        <tr>
                            <td title="{{ $cr->descricao }}">{{ Str::limit($cr->descricao, 25) }}</td>
                            <td class="text-success">R$ {{ number_format($cr->valor, 2, ',', '.') }}</td>
                            <td>{{ $cr->vencimento?->format('d/m/Y') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted">Nenhuma conta pendente.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-wallet2 me-1"></i> Contas a Pagar Pendentes</h6>
            </div>
            <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                <table class="table table-sm mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Descricao</th>
                            <th>Valor</th>
                            <th>Vencimento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contasPagar as $cp)
                        <tr>
                            <td title="{{ $cp->descricao }}">{{ Str::limit($cp->descricao, 25) }}</td>
                            <td class="text-danger">R$ {{ number_format($cp->valor, 2, ',', '.') }}</td>
                            <td>{{ $cp->vencimento?->format('d/m/Y') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted">Nenhuma conta pendente.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Modals for linking extratos --}}
@foreach($extratos->where('conciliado', false) as $extrato)
<div class="modal fade" id="modalConciliar{{ $extrato->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('app.conciliacao.conciliar', $extrato) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Conciliar Lancamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light">
                        <strong>{{ $extrato->data?->format('d/m/Y') }}</strong> -
                        {{ $extrato->descricao }}<br>
                        <span class="{{ $extrato->tipo === 'credito' ? 'text-success' : 'text-danger' }} fw-bold">
                            R$ {{ number_format($extrato->valor, 2, ',', '.') }} ({{ $extrato->tipo === 'credito' ? 'Credito' : 'Debito' }})
                        </span>
                    </div>

                    @if($extrato->tipo === 'credito')
                    <div class="mb-3">
                        <label class="form-label">Vincular a Conta a Receber</label>
                        <select name="conta_receber_id" class="form-select">
                            <option value="">Selecione...</option>
                            @foreach($contasReceber as $cr)
                                <option value="{{ $cr->id }}">
                                    {{ $cr->descricao }} - R$ {{ number_format($cr->valor, 2, ',', '.') }} - Venc: {{ $cr->vencimento?->format('d/m/Y') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @else
                    <div class="mb-3">
                        <label class="form-label">Vincular a Conta a Pagar</label>
                        <select name="conta_pagar_id" class="form-select">
                            <option value="">Selecione...</option>
                            @foreach($contasPagar as $cp)
                                <option value="{{ $cp->id }}">
                                    {{ $cp->descricao }} - R$ {{ number_format($cp->valor, 2, ',', '.') }} - Venc: {{ $cp->vencimento?->format('d/m/Y') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-link-45deg me-1"></i> Conciliar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endsection
