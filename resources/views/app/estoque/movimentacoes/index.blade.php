@extends('layouts.app')

@section('title', 'Movimentacoes de Estoque')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Movimentacoes de Estoque</h4>
    <a href="{{ route('app.movimentacoes.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Nova Movimentacao
    </a>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Produto</label>
                <select name="produto_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach($produtos as $produto)
                        <option value="{{ $produto->id }}" {{ request('produto_id') == $produto->id ? 'selected' : '' }}>
                            {{ $produto->descricao }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="">Todos</option>
                    <option value="entrada" {{ request('tipo') == 'entrada' ? 'selected' : '' }}>Entrada</option>
                    <option value="saida" {{ request('tipo') == 'saida' ? 'selected' : '' }}>Saida</option>
                    <option value="ajuste" {{ request('tipo') == 'ajuste' ? 'selected' : '' }}>Ajuste</option>
                    <option value="perda" {{ request('tipo') == 'perda' ? 'selected' : '' }}>Perda</option>
                    <option value="bonificacao" {{ request('tipo') == 'bonificacao' ? 'selected' : '' }}>Bonificacao</option>
                    <option value="transferencia" {{ request('tipo') == 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                    <option value="devolucao" {{ request('tipo') == 'devolucao' ? 'selected' : '' }}>Devolucao</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Data Inicio</label>
                <input type="date" name="data_inicio" class="form-control" value="{{ request('data_inicio') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Data Fim</label>
                <input type="date" name="data_fim" class="form-control" value="{{ request('data_fim') }}">
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-search me-1"></i> Filtrar
                </button>
                <a href="{{ route('app.movimentacoes.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i> Limpar
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Data</th>
                    <th>Produto</th>
                    <th>Tipo</th>
                    <th class="text-end">Quantidade</th>
                    <th class="text-center">Estoque</th>
                    <th>Usuario</th>
                    <th>Origem</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($movimentacoes as $mov)
                <tr>
                    <td>{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $mov->produto->descricao ?? '-' }}</td>
                    <td>
                        @php
                            $badgeClass = match($mov->tipo->value) {
                                'entrada', 'devolucao' => 'bg-success',
                                'saida', 'perda' => 'bg-danger',
                                'ajuste' => 'bg-warning text-dark',
                                'bonificacao' => 'bg-info',
                                'transferencia' => 'bg-primary',
                                default => 'bg-secondary',
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ $mov->tipo->label() }}</span>
                    </td>
                    <td class="text-end">
                        @if(in_array($mov->tipo->value, ['entrada', 'devolucao', 'ajuste']))
                            <span class="text-success fw-bold">+{{ number_format($mov->quantidade, 3, ',', '.') }}</span>
                        @else
                            <span class="text-danger fw-bold">-{{ number_format($mov->quantidade, 3, ',', '.') }}</span>
                        @endif
                    </td>
                    <td class="text-center">
                        {{ number_format($mov->quantidade_anterior, 3, ',', '.') }}
                        <i class="bi bi-arrow-right mx-1"></i>
                        {{ number_format($mov->quantidade_posterior, 3, ',', '.') }}
                    </td>
                    <td>{{ $mov->user->name ?? '-' }}</td>
                    <td>
                        @if($mov->origem_tipo)
                            {{ class_basename($mov->origem_tipo) }} #{{ $mov->origem_id }}
                        @else
                            Manual
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('app.movimentacoes.show', $mov) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">Nenhuma movimentacao encontrada.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($movimentacoes->hasPages())
    <div class="card-footer">
        {{ $movimentacoes->links() }}
    </div>
    @endif
</div>
@endsection
