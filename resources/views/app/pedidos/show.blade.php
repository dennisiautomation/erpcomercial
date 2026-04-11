@extends('layouts.app')

@section('title', 'Pedido #' . $pedido->numero)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <i class="bi bi-cart-check me-2"></i>Pedido #{{ $pedido->numero }}
        <span class="badge bg-{{ $pedido->status->color() }} ms-2">{{ $pedido->status->label() }}</span>
    </h4>
    <div class="d-flex gap-2">
        @if($pedido->status->value === 'rascunho')
            <a href="{{ route('app.pedidos.edit', $pedido) }}" class="btn btn-warning">
                <i class="bi bi-pencil me-1"></i> Editar
            </a>
        @endif
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Imprimir
        </button>
        <a href="{{ route('app.pedidos.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

{{-- Status Workflow --}}
@if($pedido->status->value !== 'cancelado' && $pedido->status->value !== 'entregue')
<div class="card mb-4">
    <div class="card-body">
        <h6 class="card-title mb-3"><i class="bi bi-signpost-split me-1"></i> Fluxo do Pedido</h6>
        <div class="d-flex gap-2 flex-wrap">
            @if($pedido->status->value === 'rascunho')
                <form method="POST" action="{{ route('app.pedidos.updateStatus', $pedido) }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="status" value="confirmado">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Confirmar este pedido?')">
                        <i class="bi bi-check-circle me-1"></i> Confirmar Pedido
                    </button>
                </form>
            @endif

            @if($pedido->status->value === 'confirmado')
                <form method="POST" action="{{ route('app.pedidos.updateStatus', $pedido) }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="status" value="faturado">
                    <button type="submit" class="btn btn-info text-white" onclick="return confirm('Faturar este pedido? O estoque sera baixado.')">
                        <i class="bi bi-receipt me-1"></i> Faturar
                    </button>
                </form>
            @endif

            @if($pedido->status->value === 'faturado')
                <form method="POST" action="{{ route('app.pedidos.updateStatus', $pedido) }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="status" value="entregue">
                    <button type="submit" class="btn btn-success" onclick="return confirm('Marcar como entregue?')">
                        <i class="bi bi-truck me-1"></i> Marcar Entregue
                    </button>
                </form>
            @endif

            <form method="POST" action="{{ route('app.pedidos.updateStatus', $pedido) }}" class="d-inline">
                @csrf
                <input type="hidden" name="status" value="cancelado">
                <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Cancelar este pedido?')">
                    <i class="bi bi-x-circle me-1"></i> Cancelar
                </button>
            </form>
        </div>
    </div>
</div>
@endif

<div class="row g-4">
    {{-- Details --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i> Informacoes</div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <th class="text-muted" style="width:40%">Numero:</th>
                        <td>#{{ $pedido->numero }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Data:</th>
                        <td>{{ $pedido->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Cond. Pagamento:</th>
                        <td>{{ ucfirst(str_replace('_', ' ', $pedido->condicao_pagamento ?? '-')) }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Status:</th>
                        <td><span class="badge bg-{{ $pedido->status->color() }}">{{ $pedido->status->label() }}</span></td>
                    </tr>
                    @if($pedido->orcamento)
                        <tr>
                            <th class="text-muted">Orcamento Origem:</th>
                            <td>
                                <a href="{{ route('app.orcamentos.show', $pedido->orcamento) }}">
                                    #{{ $pedido->orcamento->numero }}
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
                        <td>{{ $pedido->cliente->nome_razao_social ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">CPF/CNPJ:</th>
                        <td>{{ $pedido->cliente->cpf_cnpj ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Telefone:</th>
                        <td>{{ $pedido->cliente->telefone ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Vendedor:</th>
                        <td>{{ $pedido->vendedor->name ?? '-' }}</td>
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
                        @foreach($pedido->itens as $i => $item)
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
                            <td class="text-end"><strong>R$ {{ number_format($pedido->subtotal, 2, ',', '.') }}</strong></td>
                        </tr>
                        @if($pedido->desconto_valor > 0)
                            <tr>
                                <td colspan="6" class="text-end text-danger">
                                    <strong>Desconto{{ $pedido->desconto_percentual > 0 ? ' (' . number_format($pedido->desconto_percentual, 2, ',', '.') . '%)' : '' }}:</strong>
                                </td>
                                <td class="text-end text-danger"><strong>- R$ {{ number_format($pedido->desconto_valor, 2, ',', '.') }}</strong></td>
                            </tr>
                        @endif
                        <tr>
                            <td colspan="6" class="text-end"><strong class="fs-5">TOTAL:</strong></td>
                            <td class="text-end"><strong class="fs-5 text-success">R$ {{ number_format($pedido->total, 2, ',', '.') }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Observacoes --}}
    @if($pedido->observacoes_internas || $pedido->observacoes_externas)
        <div class="col-12">
            <div class="card">
                <div class="card-header"><i class="bi bi-chat-text me-1"></i> Observacoes</div>
                <div class="card-body">
                    @if($pedido->observacoes_internas)
                        <div class="mb-3">
                            <h6 class="text-muted">Internas:</h6>
                            <p>{{ $pedido->observacoes_internas }}</p>
                        </div>
                    @endif
                    @if($pedido->observacoes_externas)
                        <div>
                            <h6 class="text-muted">Para o Cliente:</h6>
                            <p class="mb-0">{{ $pedido->observacoes_externas }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
