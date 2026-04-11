@extends('layouts.app')

@section('title', 'Boleto')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-upc-scan me-2"></i>Boleto #{{ $boleto->id }}</h4>
    <div>
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
        <a href="{{ route('app.boletos.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h6 class="mb-0">Dados do Boleto</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless mb-0">
                    <tr>
                        <th class="text-muted" width="180">Cliente:</th>
                        <td>{{ $boleto->cliente->nome_razao_social ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Nosso Numero:</th>
                        <td>{{ $boleto->nosso_numero ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Linha Digitavel:</th>
                        <td><code>{{ $boleto->linha_digitavel ?? '-' }}</code></td>
                    </tr>
                    <tr>
                        <th class="text-muted">Codigo de Barras:</th>
                        <td><code>{{ $boleto->codigo_barras ?? '-' }}</code></td>
                    </tr>
                    <tr>
                        <th class="text-muted">Banco:</th>
                        <td>{{ $boleto->banco ?? '-' }}</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless mb-0">
                    <tr>
                        <th class="text-muted" width="180">Valor:</th>
                        <td class="fw-bold fs-5">R$ {{ number_format($boleto->valor, 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Vencimento:</th>
                        <td>{{ $boleto->vencimento?->format('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Status:</th>
                        <td>
                            @php
                                $statusClass = match($boleto->status) {
                                    'pago' => 'success',
                                    'pendente' => 'warning',
                                    'vencido' => 'danger',
                                    'cancelado' => 'secondary',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge bg-{{ $statusClass }} fs-6">{{ ucfirst($boleto->status) }}</span>
                        </td>
                    </tr>
                    @if($boleto->pago_em)
                    <tr>
                        <th class="text-muted">Pago em:</th>
                        <td>{{ $boleto->pago_em->format('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Valor Pago:</th>
                        <td>R$ {{ number_format($boleto->valor_pago, 2, ',', '.') }}</td>
                    </tr>
                    @endif
                    @if($boleto->contrato)
                    <tr>
                        <th class="text-muted">Contrato:</th>
                        <td>
                            <a href="{{ route('app.contratos.show', $boleto->contrato) }}">
                                #{{ $boleto->contrato->id }} - {{ Str::limit($boleto->contrato->descricao, 30) }}
                            </a>
                        </td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>

        @if($boleto->url_boleto)
        <hr>
        <a href="{{ $boleto->url_boleto }}" target="_blank" class="btn btn-outline-primary">
            <i class="bi bi-download me-1"></i> Visualizar Boleto
        </a>
        @endif

        @if($boleto->observacoes)
        <hr>
        <p class="mb-0 text-muted"><strong>Observacoes:</strong> {{ $boleto->observacoes }}</p>
        @endif
    </div>
</div>
@endsection
