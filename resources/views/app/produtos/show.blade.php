@extends('layouts.app')

@section('title', 'Detalhes do Produto')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-box-seam me-2"></i>{{ $produto->descricao }}</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('app.produtos.edit', $produto) }}" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>
        <a href="{{ route('app.produtos.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="bi bi-info-circle me-1"></i> Dados Gerais</h6>
                <p class="mb-1"><strong>Código Interno:</strong> <code>{{ $produto->codigo_interno }}</code></p>
                <p class="mb-1"><strong>Código de Barras:</strong> {{ $produto->codigo_barras ?: '-' }}</p>
                <p class="mb-1"><strong>SKU:</strong> {{ $produto->sku ?: '-' }}</p>
                <p class="mb-1"><strong>Unidade:</strong> {{ $produto->unidade_medida }}</p>
                <p class="mb-1"><strong>Categoria:</strong> {{ $produto->categoria->nome ?? '-' }}</p>
                <p class="mb-0">
                    <strong>Status:</strong>
                    <span class="badge bg-{{ $produto->status === 'ativo' ? 'success' : 'secondary' }}">{{ ucfirst($produto->status) }}</span>
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="bi bi-currency-dollar me-1"></i> Preços</h6>
                <p class="mb-1"><strong>Preço de Custo:</strong> R$ {{ number_format($produto->preco_custo ?? 0, 2, ',', '.') }}</p>
                <p class="mb-1"><strong>Markup:</strong> {{ number_format($produto->markup ?? 0, 2, ',', '.') }}%</p>
                <p class="mb-1"><strong>Preço de Venda:</strong> <span class="fs-5 fw-bold text-success">R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}</span></p>
                <hr>
                <p class="mb-1"><strong>Peso Bruto:</strong> {{ number_format($produto->peso_bruto ?? 0, 3, ',', '.') }} kg</p>
                <p class="mb-0"><strong>Peso Líquido:</strong> {{ number_format($produto->peso_liquido ?? 0, 3, ',', '.') }} kg</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="bi bi-file-earmark-text me-1"></i> Fiscal</h6>
                <p class="mb-1"><strong>NCM:</strong> {{ $produto->ncm ?: '-' }}</p>
                <p class="mb-1"><strong>CEST:</strong> {{ $produto->cest ?: '-' }}</p>
                <p class="mb-1"><strong>Origem:</strong> {{ $produto->origem ?? '-' }}</p>
                <p class="mb-1"><strong>CFOP:</strong> {{ $produto->cfop ?: '-' }}</p>
                <p class="mb-1"><strong>CST/CSOSN:</strong> {{ $produto->cst_csosn ?: '-' }}</p>
                <hr>
                <p class="mb-1"><strong>ICMS:</strong> {{ number_format($produto->icms_aliquota ?? 0, 2, ',', '.') }}%</p>
                <p class="mb-1"><strong>PIS:</strong> {{ number_format($produto->pis_aliquota ?? 0, 2, ',', '.') }}%</p>
                <p class="mb-1"><strong>COFINS:</strong> {{ number_format($produto->cofins_aliquota ?? 0, 2, ',', '.') }}%</p>
                <p class="mb-0"><strong>IPI:</strong> {{ number_format($produto->ipi_aliquota ?? 0, 2, ',', '.') }}%</p>
            </div>
        </div>
    </div>
</div>

{{-- Movimentações de Estoque --}}
<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Últimas Movimentações de Estoque</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th class="text-end">Quantidade</th>
                        <th class="text-end">Estoque Anterior</th>
                        <th class="text-end">Estoque Posterior</th>
                        <th>Observações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($produto->estoqueMovimentacoes as $mov)
                        <tr>
                            <td>{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <span class="badge bg-{{ ($mov->tipo->value ?? $mov->tipo) === 'entrada' ? 'success' : 'danger' }}">
                                    {{ ucfirst($mov->tipo->value ?? $mov->tipo) }}
                                </span>
                            </td>
                            <td class="text-end">{{ number_format($mov->quantidade, 3, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($mov->quantidade_anterior, 3, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($mov->quantidade_posterior, 3, ',', '.') }}</td>
                            <td>{{ $mov->observacoes ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">Nenhuma movimentação registrada</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
