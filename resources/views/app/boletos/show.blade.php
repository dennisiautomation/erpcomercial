@extends('layouts.app')

@section('title', 'Boleto')

@section('content')
<x-erp.page-header title="Boleto #{{ $boleto->id }}" icon="upc-scan">
    @if($boleto->status === 'pendente')
    <form action="{{ route('app.boletos.baixar', $boleto) }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-success" onclick="return confirm('Marcar como pago?')">
            <i class="bi bi-check-lg me-1"></i> Baixar
        </button>
    </form>
    <form action="{{ route('app.boletos.cancelar', $boleto) }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-danger" onclick="return confirm('Cancelar este boleto?')">
            <i class="bi bi-x-lg me-1"></i> Cancelar
        </button>
    </form>
    @endif
    <a href="{{ route('app.boletos.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

<div class="row g-4">
    <div class="col-md-6">
        <x-erp.card title="Dados do Boleto" icon="info-circle">
            <table class="table table-borderless mb-0">
                <tr>
                    <th width="40%">Cliente</th>
                    <td>{{ $boleto->cliente->nome_razao_social ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Nosso Numero</th>
                    <td>{{ $boleto->nosso_numero ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Linha Digitavel</th>
                    <td><code>{{ $boleto->linha_digitavel ?? '-' }}</code></td>
                </tr>
                <tr>
                    <th>Codigo de Barras</th>
                    <td><code>{{ $boleto->codigo_barras ?? '-' }}</code></td>
                </tr>
                <tr>
                    <th>Banco</th>
                    <td>{{ $boleto->banco ?? '-' }}</td>
                </tr>
            </table>
        </x-erp.card>
    </div>
    <div class="col-md-6">
        <x-erp.card title="Valores e Status" icon="cash-stack">
            <table class="table table-borderless mb-0">
                <tr>
                    <th width="40%">Valor</th>
                    <td class="fw-bold fs-5">R$ {{ number_format($boleto->valor, 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Vencimento</th>
                    <td>{{ $boleto->vencimento?->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td><x-erp.status-badge :status="$boleto->status" /></td>
                </tr>
                @if($boleto->pago_em)
                <tr>
                    <th>Pago em</th>
                    <td>{{ $boleto->pago_em->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <th>Valor Pago</th>
                    <td>R$ {{ number_format($boleto->valor_pago, 2, ',', '.') }}</td>
                </tr>
                @endif
                @if($boleto->contrato)
                <tr>
                    <th>Contrato</th>
                    <td>
                        <a href="{{ route('app.contratos.show', $boleto->contrato) }}">
                            #{{ $boleto->contrato->id }} - {{ Str::limit($boleto->contrato->descricao, 30) }}
                        </a>
                    </td>
                </tr>
                @endif
            </table>

            @if($boleto->url_boleto)
            <hr>
            <a href="{{ $boleto->url_boleto }}" target="_blank" class="btn btn-erp-outline">
                <i class="bi bi-download me-1"></i> Visualizar Boleto
            </a>
            @endif

            @if($boleto->observacoes)
            <hr>
            <p class="mb-0 text-muted"><strong>Observacoes:</strong> {{ $boleto->observacoes }}</p>
            @endif
        </x-erp.card>
    </div>
</div>
@endsection
