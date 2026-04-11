@extends('layouts.app')

@section('title', 'Venda #' . $venda->numero)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <i class="bi bi-bag-check me-2"></i>Venda #{{ $venda->numero }}
        <span class="badge bg-{{ $venda->status->color() }} ms-2">{{ $venda->status->label() }}</span>
    </h4>
    <div class="d-flex gap-2">
        @if($venda->status->value === 'concluida')
            <form method="POST" action="{{ route('app.vendas.destroy', $venda) }}" onsubmit="return confirm('Cancelar esta venda?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-x-circle me-1"></i> Cancelar Venda
                </button>
            </form>
        @endif
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Imprimir
        </button>
        <a href="{{ route('app.vendas.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

<div class="row g-4">
    {{-- Details --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i> Informacoes da Venda</div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <th class="text-muted" style="width:40%">Numero:</th>
                        <td>#{{ $venda->numero }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Data/Hora:</th>
                        <td>{{ $venda->created_at->format('d/m/Y H:i:s') }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Tipo:</th>
                        <td><span class="badge bg-{{ $venda->tipo === 'pdv' ? 'info' : 'primary' }}">{{ strtoupper($venda->tipo ?? 'N/A') }}</span></td>
                    </tr>
                    <tr>
                        <th class="text-muted">Status:</th>
                        <td><span class="badge bg-{{ $venda->status->color() }}">{{ $venda->status->label() }}</span></td>
                    </tr>
                    @if($venda->caixa)
                        <tr>
                            <th class="text-muted">Caixa:</th>
                            <td>#{{ $venda->caixa->numero_caixa }}</td>
                        </tr>
                    @endif
                    @if($venda->pedido)
                        <tr>
                            <th class="text-muted">Pedido:</th>
                            <td><a href="{{ route('app.pedidos.show', $venda->pedido) }}">#{{ $venda->pedido->numero }}</a></td>
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
                        <td>{{ $venda->cliente->nome_razao_social ?? 'Consumidor Final' }}</td>
                    </tr>
                    @if($venda->cliente)
                        <tr>
                            <th class="text-muted">CPF/CNPJ:</th>
                            <td>{{ $venda->cliente->cpf_cnpj ?? '-' }}</td>
                        </tr>
                    @endif
                    <tr>
                        <th class="text-muted">Vendedor:</th>
                        <td>{{ $venda->vendedor->name ?? '-' }}</td>
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
                            <th class="text-end">Desc. R$</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($venda->itens as $i => $item)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $item->descricao ?? $item->produto->descricao ?? '-' }}</td>
                                <td class="text-center">{{ number_format($item->quantidade, 3, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($item->desconto_valor, 2, ',', '.') }}</td>
                                <td class="text-end fw-semibold">{{ number_format($item->total, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="5" class="text-end"><strong>Subtotal:</strong></td>
                            <td class="text-end"><strong>R$ {{ number_format($venda->subtotal, 2, ',', '.') }}</strong></td>
                        </tr>
                        @if($venda->desconto_valor > 0)
                            <tr>
                                <td colspan="5" class="text-end text-danger"><strong>Desconto:</strong></td>
                                <td class="text-end text-danger"><strong>- R$ {{ number_format($venda->desconto_valor, 2, ',', '.') }}</strong></td>
                            </tr>
                        @endif
                        <tr>
                            <td colspan="5" class="text-end"><strong class="fs-5">TOTAL:</strong></td>
                            <td class="text-end"><strong class="fs-5 text-success">R$ {{ number_format($venda->total, 2, ',', '.') }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Payment --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-credit-card me-1"></i> Pagamento</div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <th class="text-muted" style="width:40%">Forma:</th>
                        <td>{{ ucfirst(str_replace('_', ' ', $venda->forma_pagamento ?? '-')) }}</td>
                    </tr>
                    @if($venda->pagamento_detalhes && is_array($venda->pagamento_detalhes))
                        @foreach($venda->pagamento_detalhes as $pgto)
                            <tr>
                                <th class="text-muted">{{ ucfirst($pgto['forma'] ?? '-') }}:</th>
                                <td>R$ {{ number_format($pgto['valor'] ?? 0, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    @endif
                    @if($venda->troco > 0)
                        <tr>
                            <th class="text-muted">Troco:</th>
                            <td class="text-info fw-bold">R$ {{ number_format($venda->troco, 2, ',', '.') }}</td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- Nota Fiscal --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-file-earmark-text me-1"></i> Notas Fiscais</div>
            <div class="card-body">
                @if($venda->notasFiscais->count())
                    @foreach($venda->notasFiscais as $nf)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>NF #{{ $nf->numero }} - {{ $nf->status }}</span>
                        </div>
                    @endforeach
                @else
                    <p class="text-muted mb-0">Nenhuma nota fiscal emitida.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Contas a Receber --}}
    @if($venda->contasReceber->count())
        <div class="col-12">
            <div class="card">
                <div class="card-header"><i class="bi bi-cash-stack me-1"></i> Contas a Receber</div>
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Descricao</th>
                                <th>Forma</th>
                                <th class="text-end">Valor</th>
                                <th>Vencimento</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($venda->contasReceber as $cr)
                                <tr>
                                    <td>{{ $cr->descricao }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $cr->forma_pagamento ?? '-')) }}</td>
                                    <td class="text-end">R$ {{ number_format($cr->valor, 2, ',', '.') }}</td>
                                    <td>{{ $cr->vencimento?->format('d/m/Y') }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $cr->status === 'pago' ? 'success' : ($cr->status === 'cancelada' ? 'danger' : 'warning') }}">
                                            {{ ucfirst($cr->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
