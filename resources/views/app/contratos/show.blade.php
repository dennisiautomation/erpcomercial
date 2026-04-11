@extends('layouts.app')

@section('title', 'Contrato #' . $contrato->id)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Contrato #{{ $contrato->id }}</h4>
    <div>
        @if($contrato->status === 'ativo')
        <form action="{{ route('app.contratos.faturar', $contrato) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-success" onclick="return confirm('Gerar faturamento para este contrato?')">
                <i class="bi bi-receipt me-1"></i> Faturar
            </button>
        </form>
        @endif
        <a href="{{ route('app.contratos.edit', $contrato) }}" class="btn btn-outline-secondary">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>
        <a href="{{ route('app.contratos.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

{{-- Contract Details --}}
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">Dados do Contrato</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless mb-0">
                    <tr>
                        <th class="text-muted" width="180">Cliente:</th>
                        <td>{{ $contrato->cliente->nome_razao_social ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Descricao:</th>
                        <td>{{ $contrato->descricao }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Valor:</th>
                        <td class="fw-bold">R$ {{ number_format($contrato->valor, 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Periodicidade:</th>
                        <td><span class="badge bg-info text-dark">{{ ucfirst($contrato->periodicidade) }}</span></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless mb-0">
                    <tr>
                        <th class="text-muted" width="180">Status:</th>
                        <td>
                            @php
                                $statusClass = match($contrato->status) {
                                    'ativo' => 'success',
                                    'vencido' => 'danger',
                                    'cancelado' => 'secondary',
                                    'suspenso' => 'warning',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge bg-{{ $statusClass }}">{{ ucfirst($contrato->status) }}</span>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted">Inicio:</th>
                        <td>{{ $contrato->inicio?->format('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Fim:</th>
                        <td>{{ $contrato->fim?->format('d/m/Y') ?? 'Indeterminado' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Prox. Faturamento:</th>
                        <td>{{ $contrato->proximo_faturamento?->format('d/m/Y') ?? '-' }}</td>
                    </tr>
                </table>
            </div>
        </div>
        @if($contrato->observacoes)
        <hr>
        <p class="mb-0 text-muted"><strong>Observacoes:</strong> {{ $contrato->observacoes }}</p>
        @endif
    </div>
</div>

{{-- Payment History --}}
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">Historico de Faturamentos</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
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
                    <td>
                        @php
                            $contaStatusClass = match($conta->status) {
                                'paga' => 'success',
                                'pendente' => 'warning',
                                'vencida' => 'danger',
                                'cancelada' => 'secondary',
                                default => 'secondary',
                            };
                        @endphp
                        <span class="badge bg-{{ $contaStatusClass }}">{{ ucfirst($conta->status) }}</span>
                    </td>
                    <td>{{ $conta->pago_em?->format('d/m/Y') ?? '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-3">Nenhum faturamento gerado ainda.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Boletos --}}
<div class="card">
    <div class="card-header">
        <h6 class="mb-0">Boletos</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
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
                    <td>
                        @php
                            $boletoStatusClass = match($boleto->status) {
                                'pago' => 'success',
                                'pendente' => 'warning',
                                'vencido' => 'danger',
                                'cancelado' => 'secondary',
                                default => 'secondary',
                            };
                        @endphp
                        <span class="badge bg-{{ $boletoStatusClass }}">{{ ucfirst($boleto->status) }}</span>
                    </td>
                    <td>
                        <a href="{{ route('app.boletos.show', $boleto) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-3">Nenhum boleto gerado.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
