@extends('layouts.app')

@section('title', 'Promoção ' . $promocao->nome)

@php
    $classe = ['agendada' => 'pendente', 'vigente' => 'ativa', 'encerrada' => 'fechado', 'encerrada_antes' => 'cancelada'];
    $status = $promocao->status();
    $brl = fn ($v) => $v === null ? '—' : 'R$ ' . number_format((float) $v, 2, ',', '.');
@endphp

@section('content')
<div class="fade-in">
<div class="page-header">
    <div>
        <h4><i class="bi bi-megaphone me-2"></i>{{ $promocao->nome }}
            <span class="badge-status {{ $classe[$status] }} ms-2 align-middle">{{ $promocao->statusRotulo() }}</span></h4>
        <div class="subtitle">
            {{ $promocao->data_inicio->format('d/m/Y') }} a {{ $promocao->data_fim->format('d/m/Y') }}
            @if($status === 'encerrada_antes')
                · encerrada em {{ $promocao->encerrada_em->format('d/m/Y H:i') }} (valeu até {{ $promocao->ultimoDia()->format('d/m/Y') }})
            @endif
            · {{ $promocao->unidades->pluck('nome')->join(', ') }}
            @if($promocao->origem) · duplicada de <a href="{{ route('app.promocoes.show', $promocao->origem) }}">{{ $promocao->origem->nome }}</a> @endif
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('app.promocoes.index') }}" class="btn btn-erp btn-erp-outline"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
        @if($status === 'agendada')
            <a href="{{ route('app.promocoes.editar', $promocao) }}" class="btn btn-erp btn-erp-outline"><i class="bi bi-pencil me-1"></i> Editar</a>
        @endif
        <a href="{{ route('app.promocoes.create', ['duplicar' => $promocao->id]) }}" class="btn btn-erp btn-erp-primary">
            <i class="bi bi-copy me-1"></i> Duplicar
        </a>
        @if(in_array($status, ['agendada', 'vigente'], true))
            <form method="POST" action="{{ route('app.promocoes.encerrar', $promocao) }}"
                  data-confirm="Encerrar agora? Os produtos voltam ao preço normal hoje mesmo.">
                @csrf
                <button class="btn btn-erp btn-erp-outline text-danger"><i class="bi bi-stop-circle me-1"></i> Encerrar agora</button>
            </form>
        @endif
        @if($status === 'agendada')
            <form method="POST" action="{{ route('app.promocoes.destroy', $promocao) }}"
                  data-confirm="Excluir esta promoção agendada?">
                @csrf @method('DELETE')
                <button class="btn btn-erp btn-erp-outline text-danger"><i class="bi bi-trash"></i></button>
            </form>
        @endif
    </div>
</div>

<div class="erp-card">
    <div class="card-body p-0">
        <div class="px-3 pt-3 small text-muted">
            Preços desta loja hoje. Cartão "regra da loja": cada loja soma o acréscimo do cartão sobre o à vista da promoção.
        </div>
        <div class="table-responsive">
            <table class="erp-table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Código</th>
                        <th class="text-end">Promoção à vista</th>
                        <th class="text-end">À vista normal</th>
                        <th class="text-end">À vista na promoção</th>
                        <th class="text-end">Cartão normal</th>
                        <th class="text-end">Cartão na promoção</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($linhas as $l)
                        @php $item = $l['item']; @endphp
                        <tr>
                            <td>{{ $item->produto->descricao ?? 'Produto removido' }}</td>
                            <td class="text-muted">{{ $item->produto?->codigo_barras ?: ($item->produto?->codigo_interno ?: $item->produto?->sku) }}</td>
                            <td class="text-end">
                                {{ $item->tipo === 'percentual' ? number_format((float) $item->valor, 2, ',', '.') . '% de desconto' : 'preço fixo' }}
                            </td>
                            <td class="text-end text-muted">{{ $brl($l['preco_atual']) }}</td>
                            <td class="text-end"><strong>{{ $brl($l['preco_promocao']) }}</strong></td>
                            <td class="text-end text-muted">{{ $brl($l['cartao_atual']) }}</td>
                            <td class="text-end">
                                @if($item->temCartao())
                                    <strong>{{ $brl($l['cartao_promocao']) }}</strong>
                                    @if($item->cartao_tipo === 'percentual')<span class="small text-muted">({{ number_format((float) $item->cartao_valor, 2, ',', '.') }}%)</span>@endif
                                @else
                                    <span class="small text-muted">regra da loja</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
@endsection
