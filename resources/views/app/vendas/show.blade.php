@extends('layouts.app')

@section('title', 'Venda #' . $venda->numero)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-bag-check me-2"></i>Venda #{{ $venda->numero }}
            <span class="badge rounded-pill bg-{{ $venda->status->color() }} ms-2">
                {{ $venda->status->label() }}
            </span>
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('app.vendas.index') }}">Vendas</a></li>
                <li class="breadcrumb-item active">#{{ $venda->numero }}</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if($venda->status->value === 'concluida')
            <form method="POST" action="{{ route('app.vendas.destroy', $venda) }}"
                  onsubmit="return confirm('Cancelar esta venda? O estoque sera revertido e as contas a receber serao canceladas.')">
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

@if($venda->status->value === 'cancelada')
    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <div>
            <strong>Venda Cancelada.</strong> Esta venda foi cancelada. O estoque foi revertido e as contas a receber foram canceladas.
        </div>
    </div>
@endif

<div class="row g-4">
    {{-- Informacoes da Venda --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-info-circle me-1"></i> Informacoes da Venda
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <small class="text-muted d-block">Numero</small>
                        <span class="fw-bold">#{{ $venda->numero }}</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Data/Hora</small>
                        <span>{{ $venda->created_at->format('d/m/Y H:i:s') }}</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Tipo</small>
                        @php
                            $tipoColors = ['pdv' => 'info', 'balcao' => 'primary', 'online' => 'success'];
                            $tipoBg = $tipoColors[$venda->tipo] ?? 'secondary';
                        @endphp
                        <span class="badge bg-{{ $tipoBg }}">{{ strtoupper($venda->tipo ?? 'N/A') }}</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Status</small>
                        <span class="badge rounded-pill bg-{{ $venda->status->color() }}">{{ $venda->status->label() }}</span>
                    </div>
                    @if($venda->caixa)
                        <div class="col-6">
                            <small class="text-muted d-block">Caixa</small>
                            <span><i class="bi bi-cash-stack me-1"></i>#{{ $venda->caixa->numero_caixa ?? $venda->caixa->id }}</span>
                        </div>
                    @endif
                    @if($venda->pedido)
                        <div class="col-6">
                            <small class="text-muted d-block">Pedido Origem</small>
                            <a href="{{ route('app.pedidos.show', $venda->pedido) }}" class="fw-semibold text-decoration-none">
                                <i class="bi bi-link-45deg me-1"></i>Pedido #{{ $venda->pedido->numero }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Cliente / Vendedor --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-person me-1"></i> Cliente / Vendedor
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <small class="text-muted d-block">Cliente</small>
                        <span class="fw-bold">{{ $venda->cliente->nome_razao_social ?? 'Consumidor Final' }}</span>
                    </div>
                    @if($venda->cliente)
                        <div class="col-6">
                            <small class="text-muted d-block">CPF/CNPJ</small>
                            <span>{{ $venda->cliente->cpf_cnpj ?? '-' }}</span>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Telefone</small>
                            <span>{{ $venda->cliente->telefone ?? '-' }}</span>
                        </div>
                    @endif
                    <div class="col-6">
                        <small class="text-muted d-block">Vendedor</small>
                        <span>{{ $venda->vendedor->name ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Items Table --}}
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-list-ul me-1"></i> Itens da Venda
                <span class="badge bg-secondary ms-1">{{ $venda->itens->count() }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3" style="width:5%">#</th>
                            <th>Descricao</th>
                            <th class="text-center" style="width:10%">Qtd</th>
                            <th class="text-end" style="width:12%">Preco Unit.</th>
                            <th class="text-center" style="width:10%">Desc.</th>
                            <th class="text-end" style="width:12%">Desc. R$</th>
                            <th class="text-end pe-3" style="width:12%">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($venda->itens as $i => $item)
                            <tr>
                                <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $item->descricao ?? $item->produto->descricao ?? $item->servico->descricao ?? '-' }}</div>
                                    @if($item->produto)
                                        <small class="text-muted">Cod: {{ $item->produto->codigo_interno ?? '-' }}</small>
                                    @elseif($item->servico)
                                        <small class="text-info"><i class="bi bi-tools me-1"></i>Servico</small>
                                    @endif
                                </td>
                                <td class="text-center">{{ number_format($item->quantidade, 3, ',', '.') }}</td>
                                <td class="text-end">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                                <td class="text-center">
                                    @if($item->desconto_percentual > 0)
                                        <span class="badge bg-warning text-dark">{{ number_format($item->desconto_percentual, 1, ',', '.') }}%</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($item->desconto_valor > 0)
                                        <span class="text-danger">- R$ {{ number_format($item->desconto_valor, 2, ',', '.') }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3 fw-bold">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{-- Totals Footer --}}
            <div class="card-footer bg-transparent">
                <div class="row justify-content-end">
                    <div class="col-lg-4">
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted">Subtotal:</span>
                            <strong>R$ {{ number_format($venda->subtotal, 2, ',', '.') }}</strong>
                        </div>
                        @if($venda->desconto_valor > 0)
                            <div class="d-flex justify-content-between py-1 text-danger">
                                <span>
                                    Desconto
                                    @if($venda->desconto_percentual > 0)
                                        ({{ number_format($venda->desconto_percentual, 1, ',', '.') }}%)
                                    @endif
                                    :
                                </span>
                                <strong>- R$ {{ number_format($venda->desconto_valor, 2, ',', '.') }}</strong>
                            </div>
                        @endif
                        <hr class="my-2">
                        <div class="d-flex justify-content-between py-1">
                            <span class="fs-5 fw-bold">TOTAL:</span>
                            <span class="fs-5 fw-bold text-success">R$ {{ number_format($venda->total, 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Payment Breakdown --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-credit-card me-1"></i> Detalhes do Pagamento
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted d-block">Forma Principal</small>
                    @php
                        $formaIcons = [
                            'dinheiro' => 'bi-cash-stack',
                            'cartao_credito' => 'bi-credit-card',
                            'cartao_debito' => 'bi-credit-card-2-front',
                            'pix' => 'bi-qr-code',
                            'boleto' => 'bi-upc',
                            'split' => 'bi-diagram-2',
                        ];
                        $formaIcon = $formaIcons[$venda->forma_pagamento] ?? 'bi-wallet2';
                    @endphp
                    <span class="fs-5">
                        <i class="bi {{ $formaIcon }} me-1"></i>
                        {{ ucfirst(str_replace('_', ' ', $venda->forma_pagamento ?? '-')) }}
                    </span>
                </div>

                @if($venda->pagamento_detalhes && is_array($venda->pagamento_detalhes))
                    <div class="border-top pt-3 mt-2">
                        <small class="text-muted d-block mb-2 fw-semibold">Composicao do Pagamento:</small>
                        @foreach($venda->pagamento_detalhes as $pgto)
                            <div class="d-flex justify-content-between align-items-center py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                                <div>
                                    @php $pgtoIcon = $formaIcons[$pgto['forma'] ?? ''] ?? 'bi-wallet2'; @endphp
                                    <i class="bi {{ $pgtoIcon }} me-1 text-muted"></i>
                                    <span>{{ ucfirst(str_replace('_', ' ', $pgto['forma'] ?? '-')) }}</span>
                                </div>
                                <strong>R$ {{ number_format($pgto['valor'] ?? 0, 2, ',', '.') }}</strong>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($venda->troco > 0)
                    <div class="border-top pt-3 mt-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-cash-coin me-1"></i> Troco</span>
                            <span class="fw-bold text-info fs-5">R$ {{ number_format($venda->troco, 2, ',', '.') }}</span>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Emissao de Nota Fiscal --}}
    <div class="col-lg-6">
        <div class="erp-card h-100">
            <div class="card-header"><i class="bi bi-file-earmark-text me-2"></i>Nota Fiscal</div>
            <div class="card-body">
                @if($venda->notasFiscais->count())
                    {{-- Show existing notas --}}
                    <table class="erp-table">
                        <thead><tr><th>Tipo</th><th>Numero</th><th>Status</th><th>Acoes</th></tr></thead>
                        <tbody>
                        @foreach($venda->notasFiscais as $nota)
                            <tr>
                                <td><span class="badge bg-{{ $nota->tipo->value === 'nfce' ? 'success' : ($nota->tipo->value === 'nfe' ? 'primary' : 'info') }}">{{ strtoupper($nota->tipo->value) }}</span></td>
                                <td>{{ $nota->numero ?? 'Processando...' }}</td>
                                <td><span class="badge bg-{{ $nota->status->color() }}">{{ $nota->status->label() }}</span></td>
                                <td>
                                    @if($nota->xml_url)<a href="{{ route('app.notas-fiscais.xml', $nota) }}" class="btn btn-sm btn-outline-secondary">XML</a>@endif
                                    @if($nota->danfe_url)<a href="{{ route('app.notas-fiscais.danfe', $nota) }}" class="btn btn-sm btn-outline-primary">DANFE</a>@endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    {{-- No notas yet — show emit buttons --}}
                    <p class="text-muted mb-3">Nenhuma nota fiscal emitida para esta venda.</p>
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('app.notas-fiscais.emitir-nfe', $venda) }}">
                            @csrf
                            <button class="btn btn-erp-primary" onclick="return confirm('Emitir NF-e para esta venda?')">
                                <i class="bi bi-file-earmark-text me-1"></i> Emitir NF-e (DANFE)
                            </button>
                        </form>
                        <form method="POST" action="{{ route('app.notas-fiscais.emitir-nfce', $venda) }}">
                            @csrf
                            <button class="btn btn-erp-outline" onclick="return confirm('Emitir NFC-e para esta venda?')">
                                <i class="bi bi-receipt me-1"></i> Emitir NFC-e
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Contas a Receber --}}
    @if($venda->contasReceber->count())
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-cash-stack me-1"></i> Contas a Receber
                    <span class="badge bg-secondary ms-1">{{ $venda->contasReceber->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Descricao</th>
                                <th>Forma Pgto</th>
                                <th>Parcela</th>
                                <th class="text-end">Valor</th>
                                <th>Vencimento</th>
                                <th class="text-center pe-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($venda->contasReceber as $cr)
                                <tr>
                                    <td class="ps-3">{{ $cr->descricao }}</td>
                                    <td class="small">{{ ucfirst(str_replace('_', ' ', $cr->forma_pagamento ?? '-')) }}</td>
                                    <td>
                                        @if($cr->total_parcelas > 1)
                                            <span class="badge bg-light text-dark">{{ $cr->parcela }}/{{ $cr->total_parcelas }}</span>
                                        @else
                                            <span class="text-muted small">Parcela unica</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold">R$ {{ number_format($cr->valor, 2, ',', '.') }}</td>
                                    <td>
                                        @if($cr->vencimento)
                                            @if($cr->vencimento->isPast() && $cr->status === 'pendente')
                                                <span class="text-danger fw-semibold">
                                                    <i class="bi bi-exclamation-triangle me-1"></i>{{ $cr->vencimento->format('d/m/Y') }}
                                                </span>
                                            @else
                                                {{ $cr->vencimento->format('d/m/Y') }}
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-center pe-3">
                                        @php
                                            $crStatusColors = [
                                                'pago' => 'success',
                                                'pendente' => 'warning',
                                                'cancelada' => 'danger',
                                                'vencida' => 'danger',
                                            ];
                                            $crColor = $crStatusColors[$cr->status] ?? 'secondary';
                                        @endphp
                                        <span class="badge rounded-pill bg-{{ $crColor }}{{ $crColor === 'warning' ? ' text-dark' : '' }}">
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
