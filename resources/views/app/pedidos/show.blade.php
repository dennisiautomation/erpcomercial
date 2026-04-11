@extends('layouts.app')

@section('title', 'Pedido #' . $pedido->numero)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-cart-check me-2"></i>Pedido #{{ $pedido->numero }}
            <span class="badge rounded-pill bg-{{ $pedido->status->color() }} ms-2">
                {{ $pedido->status->label() }}
            </span>
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('app.pedidos.index') }}">Pedidos</a></li>
                <li class="breadcrumb-item active">#{{ $pedido->numero }}</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if($pedido->status->value === 'rascunho')
            <a href="{{ route('app.pedidos.edit', $pedido) }}" class="btn btn-outline-warning">
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

{{-- Status Workflow Visual --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        @php
            $steps = [
                'rascunho'   => ['icon' => 'bi-pencil', 'label' => 'Rascunho'],
                'confirmado' => ['icon' => 'bi-check-circle', 'label' => 'Confirmado'],
                'faturado'   => ['icon' => 'bi-receipt', 'label' => 'Faturado'],
                'entregue'   => ['icon' => 'bi-truck', 'label' => 'Entregue'],
            ];
            $statusOrder = ['rascunho', 'confirmado', 'faturado', 'entregue'];
            $currentIdx = array_search($pedido->status->value, $statusOrder);
            $isCancelled = $pedido->status->value === 'cancelado';
        @endphp

        @if($isCancelled)
            <div class="text-center">
                <span class="badge bg-danger fs-6 px-4 py-2">
                    <i class="bi bi-x-circle me-2"></i>Pedido Cancelado
                </span>
            </div>
        @else
            <div class="d-flex justify-content-between text-center">
                @foreach($steps as $key => $step)
                    @php
                        $stepIdx = array_search($key, $statusOrder);
                        $isActive = $stepIdx <= $currentIdx;
                        $isCurrent = $stepIdx === $currentIdx;
                    @endphp
                    <div class="flex-fill">
                        <div class="{{ $isActive ? 'bg-' . $pedido->status->color() . ' text-white' : 'bg-light text-muted' }} rounded-circle d-inline-flex align-items-center justify-content-center {{ $isCurrent ? 'shadow' : '' }}"
                             style="width:44px;height:44px;{{ $isCurrent ? 'outline: 3px solid rgba(var(--bs-' . $pedido->status->color() . '-rgb), 0.3); outline-offset: 3px;' : '' }}">
                            <i class="bi {{ $step['icon'] }} fs-5"></i>
                        </div>
                        <div class="small mt-1 {{ $isActive ? 'fw-semibold' : 'text-muted' }}">{{ $step['label'] }}</div>
                    </div>
                    @if(!$loop->last)
                        <div class="flex-fill d-flex align-items-center px-1">
                            <div class="border-top flex-fill {{ $stepIdx < $currentIdx ? 'border-2 border-' . $pedido->status->color() : '' }}"></div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</div>

{{-- Status Action Buttons --}}
@if(!in_array($pedido->status->value, ['cancelado', 'entregue']))
    <div class="card border-0 shadow-sm mb-4 border-start border-4 border-primary">
        <div class="card-body py-3">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="text-muted fw-semibold"><i class="bi bi-signpost-split me-1"></i> Acoes:</span>

                @if($pedido->status->value === 'rascunho')
                    <form method="POST" action="{{ route('app.pedidos.update-status', $pedido) }}" class="d-inline">
                        @csrf
                        <input type="hidden" name="status" value="confirmado">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Confirmar este pedido? Uma conta a receber sera criada.')">
                            <i class="bi bi-check-circle me-1"></i> Confirmar Pedido
                        </button>
                    </form>
                @endif

                @if($pedido->status->value === 'confirmado')
                    <form method="POST" action="{{ route('app.pedidos.update-status', $pedido) }}" class="d-inline">
                        @csrf
                        <input type="hidden" name="status" value="faturado">
                        <button type="submit" class="btn btn-info text-white" onclick="return confirm('Faturar este pedido? O estoque sera baixado automaticamente.')">
                            <i class="bi bi-receipt me-1"></i> Faturar Pedido
                        </button>
                    </form>
                @endif

                @if($pedido->status->value === 'faturado')
                    <form method="POST" action="{{ route('app.pedidos.update-status', $pedido) }}" class="d-inline">
                        @csrf
                        <input type="hidden" name="status" value="entregue">
                        <button type="submit" class="btn btn-success" onclick="return confirm('Marcar pedido como entregue?')">
                            <i class="bi bi-truck me-1"></i> Marcar Entregue
                        </button>
                    </form>
                @endif

                <div class="vr d-none d-md-block"></div>

                <form method="POST" action="{{ route('app.pedidos.update-status', $pedido) }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="status" value="cancelado">
                    <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Cancelar este pedido? Esta acao nao pode ser desfeita.')">
                        <i class="bi bi-x-circle me-1"></i> Cancelar Pedido
                    </button>
                </form>
            </div>
        </div>
    </div>
@endif

<div class="row g-4">
    {{-- Informacoes --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-info-circle me-1"></i> Informacoes do Pedido
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <small class="text-muted d-block">Numero</small>
                        <span class="fw-bold">#{{ $pedido->numero }}</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Data de Criacao</small>
                        <span>{{ $pedido->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Cond. Pagamento</small>
                        <span>{{ ucfirst(str_replace('_', ' ', $pedido->condicao_pagamento ?? '-')) }}</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Status</small>
                        <span class="badge rounded-pill bg-{{ $pedido->status->color() }}">{{ $pedido->status->label() }}</span>
                    </div>
                    @if($pedido->orcamento)
                        <div class="col-12">
                            <small class="text-muted d-block">Origem</small>
                            <a href="{{ route('app.orcamentos.show', $pedido->orcamento) }}" class="fw-semibold text-decoration-none">
                                <i class="bi bi-link-45deg me-1"></i>Orcamento #{{ $pedido->orcamento->numero }}
                            </a>
                        </div>
                    @endif
                    @if($pedido->venda)
                        <div class="col-12">
                            <small class="text-muted d-block">Venda Gerada</small>
                            <a href="{{ route('app.vendas.show', $pedido->venda) }}" class="fw-semibold text-decoration-none">
                                <i class="bi bi-link-45deg me-1"></i>Venda #{{ $pedido->venda->numero }}
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
                        <span class="fw-bold">{{ $pedido->cliente->nome_razao_social ?? '-' }}</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">CPF/CNPJ</small>
                        <span>{{ $pedido->cliente->cpf_cnpj ?? '-' }}</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Telefone</small>
                        <span>{{ $pedido->cliente->telefone ?? '-' }}</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">E-mail</small>
                        <span>{{ $pedido->cliente->email ?? '-' }}</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Vendedor</small>
                        <span>{{ $pedido->vendedor->name ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Items Table --}}
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-list-ul me-1"></i> Itens do Pedido
                <span class="badge bg-secondary ms-1">{{ $pedido->itens->count() }}</span>
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
                        @foreach($pedido->itens as $i => $item)
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
                            <strong>R$ {{ number_format($pedido->subtotal, 2, ',', '.') }}</strong>
                        </div>
                        @if($pedido->desconto_valor > 0)
                            <div class="d-flex justify-content-between py-1 text-danger">
                                <span>
                                    Desconto
                                    @if($pedido->desconto_percentual > 0)
                                        ({{ number_format($pedido->desconto_percentual, 1, ',', '.') }}%)
                                    @endif
                                    :
                                </span>
                                <strong>- R$ {{ number_format($pedido->desconto_valor, 2, ',', '.') }}</strong>
                            </div>
                        @endif
                        <hr class="my-2">
                        <div class="d-flex justify-content-between py-1">
                            <span class="fs-5 fw-bold">TOTAL:</span>
                            <span class="fs-5 fw-bold text-success">R$ {{ number_format($pedido->total, 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Observacoes --}}
    @if($pedido->observacoes_internas || $pedido->observacoes_externas)
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-chat-text me-1"></i> Observacoes
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        @if($pedido->observacoes_internas)
                            <div class="col-md-6">
                                <div class="bg-light rounded-3 p-3">
                                    <h6 class="text-muted mb-2"><i class="bi bi-lock me-1"></i> Internas</h6>
                                    <p class="mb-0">{{ $pedido->observacoes_internas }}</p>
                                </div>
                            </div>
                        @endif
                        @if($pedido->observacoes_externas)
                            <div class="col-md-6">
                                <div class="bg-info bg-opacity-10 rounded-3 p-3">
                                    <h6 class="text-muted mb-2"><i class="bi bi-envelope me-1"></i> Para o Cliente</h6>
                                    <p class="mb-0">{{ $pedido->observacoes_externas }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
