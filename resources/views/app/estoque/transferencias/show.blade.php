@extends('layouts.app')

@section('title', 'Detalhes da Transferencia')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-truck me-2"></i>Transferencia #{{ $transferencia->id }}</h4>
        <p class="text-muted mb-0 small">Solicitada em {{ $transferencia->created_at->format('d/m/Y \a\s H:i') }}</p>
    </div>
    <a href="{{ route('app.transferencias.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

{{-- Status Timeline --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-4">
        @php
            $steps = ['solicitada', 'aprovada', 'em_transito', 'concluida'];
            $currentIndex = array_search($transferencia->status, $steps);
            $isCancelled = $transferencia->status === 'cancelada';
        @endphp
        <div class="d-flex justify-content-between position-relative px-4">
            <div class="position-absolute top-50 start-0 end-0" style="height:2px; background:#e9ecef; z-index:0; margin-top:15px;"></div>
            @foreach($steps as $i => $step)
                @php
                    if ($isCancelled) {
                        $active = false;
                        $stepClass = 'bg-secondary bg-opacity-25 text-secondary';
                    } elseif ($currentIndex !== false && $i <= $currentIndex) {
                        $active = true;
                        $stepClass = 'bg-success text-white';
                    } else {
                        $active = false;
                        $stepClass = 'bg-secondary bg-opacity-25 text-secondary';
                    }
                    $stepIcon = match($step) {
                        'solicitada' => 'bi-send',
                        'aprovada' => 'bi-check-circle',
                        'em_transito' => 'bi-truck',
                        'concluida' => 'bi-check-all',
                    };
                @endphp
                <div class="text-center position-relative" style="z-index:1;">
                    <div class="rounded-circle {{ $stepClass }} d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                        <i class="bi {{ $stepIcon }}"></i>
                    </div>
                    <div class="small mt-1 {{ $active ? 'fw-semibold' : 'text-muted' }}">{{ ucfirst(str_replace('_', ' ', $step)) }}</div>
                </div>
            @endforeach
        </div>
        @if($isCancelled)
            <div class="text-center mt-3">
                <span class="badge bg-danger rounded-pill px-4 py-2 fs-6">
                    <i class="bi bi-x-circle me-1"></i> Transferencia Cancelada
                </span>
            </div>
        @endif
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        {{-- General Info --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle me-1"></i> Informacoes Gerais</h6>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-3 bg-primary bg-opacity-10 p-2 me-3">
                                <i class="bi bi-building text-primary"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Unidade Origem</div>
                                <div class="fw-semibold">{{ $transferencia->unidadeOrigem->nome ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-3 bg-success bg-opacity-10 p-2 me-3">
                                <i class="bi bi-building text-success"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Unidade Destino</div>
                                <div class="fw-semibold">{{ $transferencia->unidadeDestino->nome ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-3 bg-info bg-opacity-10 p-2 me-3">
                                <i class="bi bi-person text-info"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Solicitante</div>
                                <div class="fw-semibold">{{ $transferencia->solicitante->name ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-3 bg-warning bg-opacity-10 p-2 me-3">
                                <i class="bi bi-person-check text-warning"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Aprovador</div>
                                <div class="fw-semibold">{{ $transferencia->aprovador->name ?? 'Aguardando' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                @if($transferencia->observacoes)
                <hr>
                <div>
                    <div class="text-muted small mb-1">Observacoes</div>
                    <p class="mb-0">{{ $transferencia->observacoes }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Items --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-box-seam me-1"></i> Itens da Transferencia</h6>
                <span class="badge bg-primary rounded-pill">{{ $transferencia->itens->count() }} {{ $transferencia->itens->count() === 1 ? 'item' : 'itens' }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="bg-light">
                            <th class="ps-3" style="width:60px;">#</th>
                            <th>Produto</th>
                            <th class="text-end pe-3">Quantidade</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transferencia->itens as $index => $item)
                        <tr>
                            <td class="ps-3">
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill">{{ $index + 1 }}</span>
                            </td>
                            <td class="fw-semibold">{{ $item->produto->descricao ?? '-' }}</td>
                            <td class="text-end pe-3">
                                <span class="fw-bold">{{ number_format($item->quantidade, 3, ',', '.') }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Action buttons --}}
        @if($transferencia->status === 'solicitada')
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-lightning me-1"></i> Acoes</h6>
                <div class="d-flex gap-2">
                    <form method="POST" action="{{ route('app.transferencias.aprovar', $transferencia) }}"
                        onsubmit="return confirm('Confirma a aprovacao desta transferencia? O estoque sera movimentado automaticamente.')">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-check-circle me-1"></i> Aprovar Transferencia
                        </button>
                    </form>
                    <form method="POST" action="{{ route('app.transferencias.cancelar', $transferencia) }}"
                        onsubmit="return confirm('Confirma o cancelamento desta transferencia?')">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-outline-danger btn-lg">
                            <i class="bi bi-x-circle me-1"></i> Cancelar
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Sidebar --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-1"></i> Historico</h6>
                <div class="d-flex align-items-start mb-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3" style="min-width:36px;height:36px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-send text-primary small"></i>
                    </div>
                    <div>
                        <div class="fw-semibold small">Solicitacao criada</div>
                        <div class="text-muted small">{{ $transferencia->created_at->format('d/m/Y H:i') }}</div>
                        <div class="text-muted small">por {{ $transferencia->solicitante->name ?? '-' }}</div>
                    </div>
                </div>
                @if($transferencia->status !== 'solicitada')
                <div class="d-flex align-items-start mb-3">
                    <div class="rounded-circle {{ $transferencia->status === 'cancelada' ? 'bg-danger' : 'bg-success' }} bg-opacity-10 p-2 me-3" style="min-width:36px;height:36px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi {{ $transferencia->status === 'cancelada' ? 'bi-x-circle text-danger' : 'bi-check-circle text-success' }} small"></i>
                    </div>
                    <div>
                        <div class="fw-semibold small">{{ $transferencia->status === 'cancelada' ? 'Cancelada' : 'Aprovada' }}</div>
                        <div class="text-muted small">{{ $transferencia->updated_at->format('d/m/Y H:i') }}</div>
                        @if($transferencia->aprovador)
                            <div class="text-muted small">por {{ $transferencia->aprovador->name }}</div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
