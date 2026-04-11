@extends('layouts.app')

@section('title', 'Detalhes da Transferencia')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-truck me-2"></i>Transferencia #{{ $transferencia->id }}</h4>
    <a href="{{ route('app.transferencias.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Informacoes Gerais</h6>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Data da Solicitacao:</strong><br>
                        {{ $transferencia->created_at->format('d/m/Y H:i') }}
                    </div>
                    <div class="col-md-4">
                        <strong>Status:</strong><br>
                        @php
                            $statusBadge = match($transferencia->status) {
                                'solicitada' => 'bg-warning text-dark',
                                'aprovada' => 'bg-success',
                                'cancelada' => 'bg-danger',
                                default => 'bg-secondary',
                            };
                        @endphp
                        <span class="badge {{ $statusBadge }} fs-6">{{ ucfirst($transferencia->status) }}</span>
                    </div>
                    <div class="col-md-4">
                        <strong>Solicitante:</strong><br>
                        {{ $transferencia->solicitante->name ?? '-' }}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Unidade Origem:</strong><br>
                        {{ $transferencia->unidadeOrigem->nome ?? '-' }}
                    </div>
                    <div class="col-md-4">
                        <strong>Unidade Destino:</strong><br>
                        {{ $transferencia->unidadeDestino->nome ?? '-' }}
                    </div>
                    <div class="col-md-4">
                        <strong>Aprovador:</strong><br>
                        {{ $transferencia->aprovador->name ?? '-' }}
                    </div>
                </div>
                @if($transferencia->observacoes)
                <div>
                    <strong>Observacoes:</strong><br>
                    {{ $transferencia->observacoes }}
                </div>
                @endif
            </div>
        </div>

        {{-- Items --}}
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Itens da Transferencia</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Produto</th>
                            <th class="text-end">Quantidade</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transferencia->itens as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $item->produto->descricao ?? '-' }}</td>
                            <td class="text-end">{{ number_format($item->quantidade, 3, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Action buttons --}}
        @if($transferencia->status === 'solicitada')
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('app.transferencias.aprovar', $transferencia) }}"
                onsubmit="return confirm('Confirma a aprovacao desta transferencia?')">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle me-1"></i> Aprovar Transferencia
                </button>
            </form>
            <form method="POST" action="{{ route('app.transferencias.cancelar', $transferencia) }}"
                onsubmit="return confirm('Confirma o cancelamento desta transferencia?')">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-x-circle me-1"></i> Cancelar Transferencia
                </button>
            </form>
        </div>
        @endif
    </div>
</div>
@endsection
