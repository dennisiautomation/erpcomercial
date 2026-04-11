@extends('layouts.app')

@section('title', 'Contrato #' . $contrato->id)

@section('content')
<x-erp.page-header title="Contrato #{{ $contrato->id }}" icon="file-earmark-text">
    @if($contrato->status === 'ativo')
    <form action="{{ route('app.contratos.faturar', $contrato) }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-success" onclick="return confirm('Gerar faturamento para este contrato?')">
            <i class="bi bi-receipt me-1"></i> Faturar
        </button>
    </form>
    @endif
    <a href="{{ route('app.contratos.edit', $contrato) }}" class="btn btn-erp-outline">
        <i class="bi bi-pencil me-1"></i> Editar
    </a>
    <a href="{{ route('app.contratos.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

{{-- Contract Details --}}
<div class="row g-4">
    <div class="col-md-6">
        <x-erp.card title="Dados do Contrato" icon="info-circle">
            <table class="table table-borderless mb-0">
                <tr>
                    <th width="40%">Cliente</th>
                    <td>{{ $contrato->cliente->nome_razao_social ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Descricao</th>
                    <td>{{ $contrato->descricao }}</td>
                </tr>
                <tr>
                    <th>Valor</th>
                    <td class="fw-bold">R$ {{ number_format($contrato->valor, 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Periodicidade</th>
                    <td><span class="badge bg-info text-dark">{{ ucfirst($contrato->periodicidade) }}</span></td>
                </tr>
            </table>
        </x-erp.card>
    </div>
    <div class="col-md-6">
        <x-erp.card title="Vigencia" icon="calendar-range">
            <table class="table table-borderless mb-0">
                <tr>
                    <th width="40%">Status</th>
                    <td><x-erp.status-badge :status="$contrato->status" /></td>
                </tr>
                <tr>
                    <th>Inicio</th>
                    <td>{{ $contrato->inicio?->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <th>Fim</th>
                    <td>{{ $contrato->fim?->format('d/m/Y') ?? 'Indeterminado' }}</td>
                </tr>
                <tr>
                    <th>Prox. Faturamento</th>
                    <td>{{ $contrato->proximo_faturamento?->format('d/m/Y') ?? '-' }}</td>
                </tr>
            </table>
            @if($contrato->observacoes)
            <hr>
            <p class="mb-0 text-muted"><strong>Observacoes:</strong> {{ $contrato->observacoes }}</p>
            @endif
        </x-erp.card>
    </div>
</div>

{{-- Payment History --}}
<x-erp.card title="Historico de Faturamentos" icon="receipt" class="mt-4">
    <div class="table-responsive">
        <table class="erp-table">
            <thead>
                <tr>
                    <th>Descricao</th>
                    <th>Valor</th>
                    <th>Vencimento</th>
                    <th>Status</th>
                    <th>Pago em</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contasReceber as $conta)
                <tr>
                    <td>{{ $conta->descricao }}</td>
                    <td>R$ {{ number_format($conta->valor, 2, ',', '.') }}</td>
                    <td>{{ $conta->vencimento?->format('d/m/Y') }}</td>
                    <td><x-erp.status-badge :status="$conta->status" /></td>
                    <td>{{ $conta->pago_em?->format('d/m/Y') ?? '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5">
                        <x-erp.empty-state icon="receipt" title="Nenhum faturamento gerado ainda" />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-erp.card>

{{-- Boletos --}}
<x-erp.card title="Boletos" icon="upc-scan" class="mt-4">
    <div class="table-responsive">
        <table class="erp-table">
            <thead>
                <tr>
                    <th>Nosso Numero</th>
                    <th>Valor</th>
                    <th>Vencimento</th>
                    <th>Status</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($boletos as $boleto)
                <tr>
                    <td>{{ $boleto->nosso_numero ?? '-' }}</td>
                    <td>R$ {{ number_format($boleto->valor, 2, ',', '.') }}</td>
                    <td>{{ $boleto->vencimento?->format('d/m/Y') }}</td>
                    <td><x-erp.status-badge :status="$boleto->status" /></td>
                    <td>
                        <a href="{{ route('app.boletos.show', $boleto) }}" class="btn btn-sm btn-erp-outline">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5">
                        <x-erp.empty-state icon="upc-scan" title="Nenhum boleto gerado" />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-erp.card>
@endsection
