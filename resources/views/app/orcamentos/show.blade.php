@extends('layouts.app')

@section('title', 'Orcamento #' . $orcamento->numero)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <i class="bi bi-file-earmark-text me-2"></i>Orcamento #{{ $orcamento->numero }}
        <span class="badge bg-{{ $orcamento->status->color() }} ms-2">{{ $orcamento->status->label() }}</span>
    </h4>
    <div class="d-flex gap-2">
        @if($orcamento->status->value !== 'convertido')
            <form method="POST" action="{{ route('app.orcamentos.converter', $orcamento) }}" onsubmit="return confirm('Converter este orcamento em pedido?')">
                @csrf
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-arrow-repeat me-1"></i> Converter em Pedido
                </button>
            </form>
            <a href="{{ route('app.orcamentos.edit', $orcamento) }}" class="btn btn-warning">
                <i class="bi bi-pencil me-1"></i> Editar
            </a>
        @endif
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Imprimir
        </button>
        <a href="{{ route('app.orcamentos.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

<div class="row g-4">
    {{-- Details --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i> Informacoes</div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <th class="text-muted" style="width:40%">Numero:</th>
                        <td>#{{ $orcamento->numero }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Data:</th>
                        <td>{{ $orcamento->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Validade:</th>
                        <td>
                            @if($orcamento->validade_ate)
                                <span class="{{ $orcamento->validade_ate->isPast() ? 'text-danger fw-bold' : '' }}">
                                    {{ $orcamento->validade_ate->format('d/m/Y') }}
                                    @if($orcamento->validade_ate->isPast())
                                        (Expirado)
                                    @endif
                                </span>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted">Status:</th>
                        <td><span class="badge bg-{{ $orcamento->status->color() }}">{{ $orcamento->status->label() }}</span></td>
                    </tr>
                    @if($orcamento->pedido)
                        <tr>
                            <th class="text-muted">Pedido Gerado:</th>
                            <td>
                                <a href="{{ route('app.pedidos.show', $orcamento->pedido) }}">
                                    #{{ $orcamento->pedido->numero }}
                                </a>
                            </td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-person me-1"></i> Cliente / Vendedor</div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <th class="text-muted" style="width:40%">Cliente:</th>
                        <td>{{ $orcamento->cliente->nome_razao_social ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">CPF/CNPJ:</th>
                        <td>{{ $orcamento->cliente->cpf_cnpj ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Telefone:</th>
                        <td>{{ $orcamento->cliente->telefone ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Vendedor:</th>
                        <td>{{ $orcamento->vendedor->name ?? '-' }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    {{-- Items --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-list-ul me-1"></i> Itens</div>
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Produto</th>
                            <th class="text-center">Qtd</th>
                            <th class="text-end">Preco Unit.</th>
                            <th class="text-center">Desc. %</th>
                            <th class="text-end">Desc. R$</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orcamento->itens as $i => $item)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $item->descricao ?? $item->produto->descricao ?? '-' }}</td>
                                <td class="text-center">{{ number_format($item->quantidade, 3, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                                <td class="text-center">{{ number_format($item->desconto_percentual, 2, ',', '.') }}%</td>
                                <td class="text-end">{{ number_format($item->desconto_valor, 2, ',', '.') }}</td>
                                <td class="text-end fw-semibold">{{ number_format($item->total, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="6" class="text-end"><strong>Subtotal:</strong></td>
                            <td class="text-end"><strong>R$ {{ number_format($orcamento->subtotal, 2, ',', '.') }}</strong></td>
                        </tr>
                        @if($orcamento->desconto_valor > 0)
                            <tr>
                                <td colspan="6" class="text-end text-danger">
                                    <strong>Desconto{{ $orcamento->desconto_percentual > 0 ? ' (' . number_format($orcamento->desconto_percentual, 2, ',', '.') . '%)' : '' }}:</strong>
                                </td>
                                <td class="text-end text-danger"><strong>- R$ {{ number_format($orcamento->desconto_valor, 2, ',', '.') }}</strong></td>
                            </tr>
                        @endif
                        <tr>
                            <td colspan="6" class="text-end"><strong class="fs-5">TOTAL:</strong></td>
                            <td class="text-end"><strong class="fs-5 text-success">R$ {{ number_format($orcamento->total, 2, ',', '.') }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Observacoes --}}
    @if($orcamento->observacoes_internas || $orcamento->observacoes_externas)
        <div class="col-12">
            <div class="card">
                <div class="card-header"><i class="bi bi-chat-text me-1"></i> Observacoes</div>
                <div class="card-body">
                    @if($orcamento->observacoes_internas)
                        <div class="mb-3">
                            <h6 class="text-muted">Internas:</h6>
                            <p>{{ $orcamento->observacoes_internas }}</p>
                        </div>
                    @endif
                    @if($orcamento->observacoes_externas)
                        <div>
                            <h6 class="text-muted">Para o Cliente:</h6>
                            <p class="mb-0">{{ $orcamento->observacoes_externas }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
