@extends('layouts.app')

@section('title', 'Estoques da Loja')

@section('content')
<x-erp.page-header title="Estoques da Loja"
                   subtitle="Salão, depósito, avaria, vitrine — cada um com saldo próprio"
                   icon="boxes">
    <a href="{{ route('app.configuracoes.edit') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Configurações
    </a>
    <a href="{{ route('app.estoques.create') }}" class="btn btn-erp-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo Estoque
    </a>
</x-erp.page-header>

<div class="alert alert-info d-flex align-items-start">
    <i class="bi bi-info-circle me-2 mt-1"></i>
    <div>
        A venda sempre baixa do <strong>estoque de venda</strong> — o PDV não pergunta.
        Os outros servem para guardar, separar avaria ou montar vitrine, e você move
        peças entre eles por <strong>Transferência</strong>.
        Enquanto a loja tiver um estoque só, nada muda nas telas do dia a dia.
    </div>
</div>

<x-erp.data-table>
    <thead>
        <tr>
            <th>Nome</th>
            <th>Código</th>
            <th class="text-center">Venda</th>
            <th class="text-center">Padrão</th>
            <th class="text-center">Movimentações</th>
            <th class="text-center">Situação</th>
            <th class="text-center">Ações</th>
        </tr>
    </thead>
    <tbody>
        @foreach($estoques as $estoque)
        <tr>
            <td>
                <div class="fw-semibold">{{ $estoque->nome }}</div>
                @if($estoque->observacoes)
                    <small class="text-muted">{{ Str::limit($estoque->observacoes, 70) }}</small>
                @endif
            </td>
            <td><small class="font-monospace">{{ $estoque->codigo ?? '—' }}</small></td>
            <td class="text-center">
                @if($estoque->permite_venda)
                    <span class="badge bg-success"><i class="bi bi-cart-check me-1"></i>Vende</span>
                @else
                    <span class="text-muted">—</span>
                @endif
            </td>
            <td class="text-center">
                @if($estoque->is_padrao)
                    <span class="badge bg-primary">Padrão</span>
                @else
                    <span class="text-muted">—</span>
                @endif
            </td>
            <td class="text-center">
                <span class="badge bg-light text-dark border rounded-pill">{{ $estoque->movimentacoes_count }}</span>
            </td>
            <td class="text-center">
                <span class="badge bg-{{ $estoque->status === 'ativo' ? 'success' : 'secondary' }}">
                    {{ ucfirst($estoque->status) }}
                </span>
            </td>
            <td class="text-center">
                <div class="action-btns">
                    <a href="{{ route('app.estoques.edit', $estoque) }}" class="btn btn-sm btn-erp-outline" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </a>
                    @if($estoque->status === 'ativo' && ! $estoque->permite_venda && ! $estoque->is_padrao)
                    <form method="POST" action="{{ route('app.estoques.inativar', $estoque) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary"
                                data-confirm="Inativar este estoque? O histórico continua no extrato."
                                title="Inativar">
                            <i class="bi bi-archive"></i>
                        </button>
                    </form>
                    @endif
                </div>
            </td>
        </tr>
        @endforeach
    </tbody>
</x-erp.data-table>
@endsection
