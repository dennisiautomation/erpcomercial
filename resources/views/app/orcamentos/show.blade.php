@extends('layouts.app')

@section('title', 'Orcamento #' . $orcamento->numero)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-file-earmark-text me-2"></i>Orcamento #{{ $orcamento->numero }}
            <span class="badge rounded-pill bg-{{ $orcamento->status->color() }}{{ $orcamento->status->color() === 'warning' ? ' text-dark' : '' }} ms-2">
                {{ $orcamento->status->label() }}
            </span>
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('app.orcamentos.index') }}">Orcamentos</a></li>
                <li class="breadcrumb-item active">#{{ $orcamento->numero }}</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if(!in_array($orcamento->status->value, ['convertido', 'recusado']))
            <form method="POST" action="{{ route('app.orcamentos.converter', $orcamento) }}"
                  onsubmit="return confirm('Converter este orcamento em pedido? Esta acao nao pode ser desfeita.')">
                @csrf
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-arrow-right-circle me-1"></i> Converter em Pedido
                </button>
            </form>
        @endif
        @if($orcamento->status->value !== 'convertido')
            <a href="{{ route('app.orcamentos.edit', $orcamento) }}" class="btn btn-outline-warning">
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

{{-- Status Actions --}}
@if(in_array($orcamento->status->value, ['em_aberto']))
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="text-muted fw-semibold me-2"><i class="bi bi-signpost-split me-1"></i> Alterar Status:</span>
                <form method="POST" action="{{ route('app.orcamentos.update-status', $orcamento) }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="status" value="aprovado">
                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Aprovar este orcamento?')">
                        <i class="bi bi-check-circle me-1"></i> Aprovar
                    </button>
                </form>
                <form method="POST" action="{{ route('app.orcamentos.update-status', $orcamento) }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="status" value="recusado">
                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Recusar este orcamento?')">
                        <i class="bi bi-x-circle me-1"></i> Recusar
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
                <i class="bi bi-info-circle me-1"></i> Informacoes do Orcamento
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <small class="text-muted d-block">Numero</small>
                        <span class="fw-bold">#{{ $orcamento->numero }}</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Data de Criacao</small>
                        <span>{{ $orcamento->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Validade</small>
                        @if($orcamento->validade_ate)
                            @if($orcamento->validade_ate->isPast() && $orcamento->status === \App\Enums\StatusOrcamento::EmAberto)
                                <span class="text-danger fw-bold">
                                    <i class="bi bi-exclamation-triangle me-1"></i>{{ $orcamento->validade_ate->format('d/m/Y') }}
                                    <small>(Expirado)</small>
                                </span>
                            @else
                                <span>{{ $orcamento->validade_ate->format('d/m/Y') }}</span>
                            @endif
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Status</small>
                        <span class="badge rounded-pill bg-{{ $orcamento->status->color() }}{{ $orcamento->status->color() === 'warning' ? ' text-dark' : '' }}">
                            {{ $orcamento->status->label() }}
                        </span>
                    </div>
                    @if($orcamento->pedido)
                        <div class="col-12">
                            <small class="text-muted d-block">Pedido Gerado</small>
                            <a href="{{ route('app.pedidos.show', $orcamento->pedido) }}" class="fw-semibold text-decoration-none">
                                <i class="bi bi-link-45deg me-1"></i>Pedido #{{ $orcamento->pedido->numero }}
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
                        <span class="fw-bold">{{ $orcamento->cliente->nome_razao_social ?? '-' }}</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">CPF/CNPJ</small>
                        <span>{{ $orcamento->cliente->cpf_cnpj ?? '-' }}</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Telefone</small>
                        <span>{{ $orcamento->cliente->telefone ?? '-' }}</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">E-mail</small>
                        <span>{{ $orcamento->cliente->email ?? '-' }}</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">Vendedor</small>
                        <span>{{ $orcamento->vendedor->name ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Items Table --}}
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-list-ul me-1"></i> Itens do Orcamento
                <span class="badge bg-secondary ms-1">{{ $orcamento->itens->count() }}</span>
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
                        @foreach($orcamento->itens as $i => $item)
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
                            <strong>R$ {{ number_format($orcamento->subtotal, 2, ',', '.') }}</strong>
                        </div>
                        @if($orcamento->desconto_valor > 0)
                            <div class="d-flex justify-content-between py-1 text-danger">
                                <span>
                                    Desconto
                                    @if($orcamento->desconto_percentual > 0)
                                        ({{ number_format($orcamento->desconto_percentual, 1, ',', '.') }}%)
                                    @endif
                                    :
                                </span>
                                <strong>- R$ {{ number_format($orcamento->desconto_valor, 2, ',', '.') }}</strong>
                            </div>
                        @endif
                        <hr class="my-2">
                        <div class="d-flex justify-content-between py-1">
                            <span class="fs-5 fw-bold">TOTAL:</span>
                            <span class="fs-5 fw-bold text-success">R$ {{ number_format($orcamento->total, 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Observacoes --}}
    @if($orcamento->observacoes_internas || $orcamento->observacoes_externas)
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-chat-text me-1"></i> Observacoes
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        @if($orcamento->observacoes_internas)
                            <div class="col-md-6">
                                <div class="bg-light rounded-3 p-3">
                                    <h6 class="text-muted mb-2"><i class="bi bi-lock me-1"></i> Internas</h6>
                                    <p class="mb-0">{{ $orcamento->observacoes_internas }}</p>
                                </div>
                            </div>
                        @endif
                        @if($orcamento->observacoes_externas)
                            <div class="col-md-6">
                                <div class="bg-info bg-opacity-10 rounded-3 p-3">
                                    <h6 class="text-muted mb-2"><i class="bi bi-envelope me-1"></i> Para o Cliente</h6>
                                    <p class="mb-0">{{ $orcamento->observacoes_externas }}</p>
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
