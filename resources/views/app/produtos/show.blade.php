@extends('layouts.app')

@section('title', 'Detalhes do Produto')

@section('content')
<x-erp.page-header :title="$produto->descricao" icon="box-seam">
    <a href="{{ route('app.produtos.edit', $produto) }}" class="btn btn-erp-primary">
        <i class="bi bi-pencil me-1"></i> Editar
    </a>
    <a href="{{ route('app.produtos.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

{{-- Subtitle info --}}
<div class="mb-3">
    <small class="text-muted">
        Codigo: <code>{{ $produto->codigo_interno }}</code>
        @if($produto->sku)
            | SKU: <code>{{ $produto->sku }}</code>
        @endif
        @if($produto->codigo_barras)
            | <i class="bi bi-upc"></i> {{ $produto->codigo_barras }}
        @endif
    </small>
</div>

{{-- Status Badge --}}
<div class="mb-4">
    <x-erp.status-badge :status="$produto->status" />
    @if($produto->categoria)
        <span class="badge bg-info fs-6 ms-1">
            <i class="bi bi-tag me-1"></i>{{ $produto->categoria->nome }}
        </span>
    @endif
    <span class="badge bg-light text-dark fs-6 ms-1">
        {{ $produto->unidade_medida }}
    </span>
</div>

<div class="row g-4 mb-4">
    {{-- Precos --}}
    <div class="col-md-4">
        <x-erp.card title="Precos" icon="currency-dollar" class="h-100">
            <div class="mb-3">
                <small class="text-muted d-block">Preco de Custo</small>
                <span class="fs-5">R$ {{ number_format($produto->preco_custo ?? 0, 2, ',', '.') }}</span>
            </div>
            <div class="mb-3">
                <small class="text-muted d-block">Markup</small>
                <span class="fs-5">{{ number_format($produto->markup ?? 0, 2, ',', '.') }}%</span>
            </div>
            <div class="mb-3">
                <small class="text-muted d-block">Preco de Venda</small>
                <span class="fs-4 fw-bold text-success">R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}</span>
            </div>
            @php
                $custo = $produto->preco_custo ?? 0;
                $venda = $produto->preco_venda ?? 0;
                $lucro = $venda - $custo;
                $margemPct = $venda > 0 ? ($lucro / $venda) * 100 : 0;
            @endphp
            <hr>
            <div class="d-flex justify-content-between">
                <div>
                    <small class="text-muted d-block">Lucro</small>
                    <span class="fw-bold {{ $lucro >= 0 ? 'text-success' : 'text-danger' }}">
                        R$ {{ number_format($lucro, 2, ',', '.') }}
                    </span>
                </div>
                <div class="text-end">
                    <small class="text-muted d-block">Margem</small>
                    <span class="fw-bold {{ $margemPct >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($margemPct, 1, ',', '.') }}%
                    </span>
                </div>
            </div>
        </x-erp.card>
    </div>

    {{-- Dados Fiscais --}}
    <div class="col-md-4">
        <x-erp.card title="Dados Fiscais" icon="file-earmark-text" class="h-100">
            <div class="row g-2">
                <div class="col-6 mb-2">
                    <small class="text-muted d-block">NCM</small>
                    <span class="fw-semibold">{{ $produto->ncm ?: '-' }}</span>
                </div>
                <div class="col-6 mb-2">
                    <small class="text-muted d-block">CEST</small>
                    <span>{{ $produto->cest ?: '-' }}</span>
                </div>
                <div class="col-12 mb-2">
                    <small class="text-muted d-block">Origem</small>
                    @if($produto->origem !== null && $produto->origem !== '')
                        <span>{{ $produto->origem }} -
                        @switch($produto->origem)
                            @case('0') Nacional @break
                            @case('1') Estrangeira (imp. direta) @break
                            @case('2') Estrangeira (merc. interno) @break
                            @case('3') Nacional (imp. 40-70%) @break
                            @case('4') Nacional (proc. basicos) @break
                            @case('5') Nacional (imp. < 40%) @break
                            @case('6') Estrangeira (sem similar) @break
                            @case('7') Estrangeira (c/ similar) @break
                            @case('8') Nacional (imp. > 70%) @break
                        @endswitch
                        </span>
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </div>
                <div class="col-6 mb-2">
                    <small class="text-muted d-block">CFOP</small>
                    <span class="fw-semibold">{{ $produto->cfop ?: '-' }}</span>
                </div>
                <div class="col-6 mb-2">
                    <small class="text-muted d-block">CST/CSOSN</small>
                    <span class="fw-semibold">{{ $produto->cst_csosn ?: '-' }}</span>
                </div>
            </div>
            <hr>
            <h6 class="text-muted mb-2"><i class="bi bi-percent me-1"></i> Aliquotas</h6>
            <div class="row g-2">
                <div class="col-6">
                    <div class="d-flex justify-content-between border-bottom pb-1 mb-1">
                        <small>ICMS</small>
                        <strong>{{ number_format($produto->icms_aliquota ?? 0, 2, ',', '.') }}%</strong>
                    </div>
                </div>
                <div class="col-6">
                    <div class="d-flex justify-content-between border-bottom pb-1 mb-1">
                        <small>PIS</small>
                        <strong>{{ number_format($produto->pis_aliquota ?? 0, 2, ',', '.') }}%</strong>
                    </div>
                </div>
                <div class="col-6">
                    <div class="d-flex justify-content-between border-bottom pb-1 mb-1">
                        <small>COFINS</small>
                        <strong>{{ number_format($produto->cofins_aliquota ?? 0, 2, ',', '.') }}%</strong>
                    </div>
                </div>
                <div class="col-6">
                    <div class="d-flex justify-content-between border-bottom pb-1 mb-1">
                        <small>IPI</small>
                        <strong>{{ number_format($produto->ipi_aliquota ?? 0, 2, ',', '.') }}%</strong>
                    </div>
                </div>
            </div>
        </x-erp.card>
    </div>

    {{-- Estoque e Peso --}}
    <div class="col-md-4">
        <x-erp.card title="Estoque e Especificacoes" icon="boxes" class="h-100">
            <div class="mb-3">
                <small class="text-muted d-block">Estoque Minimo</small>
                <span class="fs-5 fw-bold">{{ $produto->estoque_minimo !== null ? number_format($produto->estoque_minimo, 0, ',', '.') : '-' }}</span>
                <small class="text-muted ms-1">{{ $produto->unidade_medida }}</small>
            </div>
            <hr>
            <div class="mb-2">
                <small class="text-muted d-block">Peso Bruto</small>
                <span>{{ number_format($produto->peso_bruto ?? 0, 3, ',', '.') }} kg</span>
            </div>
            <div class="mb-3">
                <small class="text-muted d-block">Peso Liquido</small>
                <span>{{ number_format($produto->peso_liquido ?? 0, 3, ',', '.') }} kg</span>
            </div>

            @if($produto->descricao_detalhada)
                <hr>
                <div>
                    <small class="text-muted d-block mb-1">Descricao Detalhada</small>
                    <span>{{ $produto->descricao_detalhada }}</span>
                </div>
            @endif

            @if($produto->foto)
                <hr>
                <div>
                    <small class="text-muted d-block mb-2">Foto do Produto</small>
                    <img src="{{ Storage::url($produto->foto) }}" alt="{{ $produto->descricao }}" class="img-fluid rounded" style="max-height: 150px;">
                </div>
            @endif
        </x-erp.card>
    </div>
</div>

{{-- Movimentacoes de Estoque --}}
<x-erp.card title="Ultimas Movimentacoes de Estoque" icon="arrow-left-right">
    <div class="d-flex justify-content-end mb-2">
        <span class="badge bg-secondary">{{ $produto->estoqueMovimentacoes->count() }} registro(s)</span>
    </div>
    <div class="table-responsive">
        <table class="erp-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th class="text-end">Quantidade</th>
                    <th class="text-end">Estoque Anterior</th>
                    <th class="text-end">Estoque Posterior</th>
                    <th>Observacoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($produto->estoqueMovimentacoes as $mov)
                    <tr>
                        <td class="text-nowrap">{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            @php $tipo = $mov->tipo->value ?? $mov->tipo; @endphp
                            <span class="badge bg-{{ $tipo === 'entrada' ? 'success' : ($tipo === 'saida' ? 'danger' : 'warning') }}">
                                <i class="bi bi-{{ $tipo === 'entrada' ? 'arrow-down' : ($tipo === 'saida' ? 'arrow-up' : 'arrow-repeat') }} me-1"></i>
                                {{ ucfirst($tipo) }}
                            </span>
                        </td>
                        <td class="text-end fw-bold">{{ number_format($mov->quantidade, 3, ',', '.') }}</td>
                        <td class="text-end">{{ number_format($mov->quantidade_anterior, 3, ',', '.') }}</td>
                        <td class="text-end">{{ number_format($mov->quantidade_posterior, 3, ',', '.') }}</td>
                        <td>{{ $mov->observacoes ?: '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-erp.empty-state icon="check-circle" title="Nenhuma movimentacao registrada" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-erp.card>
@endsection
