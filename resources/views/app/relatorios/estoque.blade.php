@extends('layouts.app')

@section('title', 'Relatorio de Estoque')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-box-seam me-2"></i>Relatorio de Estoque</h4>
</div>

{{-- Summary --}}
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stat-card border-start border-primary border-4">
            <div class="card-body">
                <div class="text-muted small">Total de Produtos</div>
                <div class="fs-4 fw-bold">{{ $produtos->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-start border-warning border-4">
            <div class="card-body">
                <div class="text-muted small">Estoque Baixo</div>
                <div class="fs-4 fw-bold text-warning">{{ $produtos->where('estoque_status', 'baixo')->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-start border-danger border-4">
            <div class="card-body">
                <div class="text-muted small">Estoque Critico</div>
                <div class="fs-4 fw-bold text-danger">{{ $produtos->where('estoque_status', 'critico')->count() }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Estoque Table --}}
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-table me-1"></i>Posicao Atual de Estoque</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th class="text-end">Estoque Atual</th>
                    <th class="text-end">Minimo</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Curva ABC</th>
                </tr>
            </thead>
            <tbody>
                @forelse($produtos as $produto)
                @php
                    $rowClass = match($produto->estoque_status) {
                        'critico' => 'table-danger',
                        'baixo' => 'table-warning',
                        default => '',
                    };
                @endphp
                <tr class="{{ $rowClass }}">
                    <td>{{ $produto->descricao }}</td>
                    <td>{{ $produto->categoria->nome ?? '-' }}</td>
                    <td class="text-end fw-bold">{{ number_format($produto->estoque_atual, 3, ',', '.') }}</td>
                    <td class="text-end">{{ $produto->estoque_minimo ? number_format($produto->estoque_minimo, 3, ',', '.') : '-' }}</td>
                    <td class="text-center">
                        @php
                            $statusBadge = match($produto->estoque_status) {
                                'critico' => 'bg-danger',
                                'baixo' => 'bg-warning text-dark',
                                default => 'bg-success',
                            };
                            $statusLabel = match($produto->estoque_status) {
                                'critico' => 'Critico',
                                'baixo' => 'Baixo',
                                default => 'OK',
                            };
                        @endphp
                        <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                    </td>
                    <td class="text-center">
                        @php
                            $curva = $curvaABC[$produto->id] ?? '-';
                            $curvaBadge = match($curva) {
                                'A' => 'bg-success',
                                'B' => 'bg-primary',
                                'C' => 'bg-secondary',
                                default => 'bg-light text-dark',
                            };
                        @endphp
                        <span class="badge {{ $curvaBadge }}">{{ $curva }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">Nenhum produto cadastrado.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Products below minimum --}}
@php $abaixoMinimo = $produtos->whereIn('estoque_status', ['baixo', 'critico']); @endphp
@if($abaixoMinimo->count() > 0)
<div class="card">
    <div class="card-header bg-danger text-white">
        <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Produtos Abaixo do Minimo ({{ $abaixoMinimo->count() }})</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Produto</th>
                    <th class="text-end">Estoque Atual</th>
                    <th class="text-end">Minimo</th>
                    <th class="text-end">Diferenca</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($abaixoMinimo as $produto)
                <tr>
                    <td>{{ $produto->descricao }}</td>
                    <td class="text-end fw-bold text-danger">{{ number_format($produto->estoque_atual, 3, ',', '.') }}</td>
                    <td class="text-end">{{ number_format($produto->estoque_minimo, 3, ',', '.') }}</td>
                    <td class="text-end text-danger fw-bold">
                        {{ number_format($produto->estoque_atual - $produto->estoque_minimo, 3, ',', '.') }}
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $produto->estoque_status === 'critico' ? 'bg-danger' : 'bg-warning text-dark' }}">
                            {{ $produto->estoque_status === 'critico' ? 'Critico' : 'Baixo' }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
