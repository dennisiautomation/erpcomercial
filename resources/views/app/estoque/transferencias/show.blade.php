@extends('layouts.app')

@section('title', 'Detalhes da Transferencia')

@section('content')
<x-erp.page-header title="Transferencia #{{ $transferencia->id }}" subtitle="Solicitada em {{ $transferencia->created_at->format('d/m/Y \a\s H:i') }}" icon="truck">
    <a href="{{ route('app.transferencias.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

{{-- Status Timeline --}}
<x-erp.card>
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
            <x-erp.status-badge status="cancelada" />
        </div>
    @endif
</x-erp.card>

<div class="row g-4 mt-1">
    <div class="col-lg-8">
        {{-- General Info --}}
        <x-erp.card title="Informacoes Gerais" icon="info-circle">
            <table class="table table-borderless mb-0">
                <tr>
                    <th width="40%">Unidade Origem</th>
                    <td>{{ $transferencia->unidadeOrigem->nome ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Unidade Destino</th>
                    <td>{{ $transferencia->unidadeDestino->nome ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Solicitante</th>
                    <td>{{ $transferencia->solicitante->name ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Aprovador</th>
                    <td>{{ $transferencia->aprovador->name ?? 'Aguardando' }}</td>
                </tr>
                @if($transferencia->observacoes)
                <tr>
                    <th>Observacoes</th>
                    <td>{{ $transferencia->observacoes }}</td>
                </tr>
                @endif
            </table>
        </x-erp.card>

        {{-- Items --}}
        <x-erp.card title="Itens da Transferencia" icon="box-seam" class="mt-4">
            <div class="d-flex justify-content-end mb-2">
                <span class="badge bg-primary rounded-pill">{{ $transferencia->itens->count() }} {{ $transferencia->itens->count() === 1 ? 'item' : 'itens' }}</span>
            </div>
            <div class="table-responsive">
                <table class="erp-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">#</th>
                            <th>Produto</th>
                            <th class="text-end">Quantidade</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transferencia->itens as $index => $item)
                        <tr>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill">{{ $index + 1 }}</span>
                            </td>
                            <td class="fw-semibold">{{ $item->produto->descricao ?? '-' }}</td>
                            <td class="text-end">
                                <span class="fw-bold">{{ number_format($item->quantidade, 3, ',', '.') }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-erp.card>

        {{-- Action buttons --}}
        @if($transferencia->status === 'solicitada')
        <x-erp.card title="Acoes" icon="lightning" class="mt-4">
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
        </x-erp.card>
        @endif
    </div>

    {{-- Sidebar --}}
    <div class="col-lg-4">
        <x-erp.card title="Historico" icon="clock-history">
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
        </x-erp.card>
    </div>
</div>
@endsection
