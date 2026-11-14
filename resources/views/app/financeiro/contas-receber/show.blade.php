@extends('layouts.app')

@section('title', 'Detalhes - Conta a Receber')

@section('content')
<x-erp.page-header title="Conta a Receber #{{ $contaReceber->id }}" icon="eye">
    <a href="{{ route('app.contas-receber.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

<div class="row g-4">
    <div class="col-lg-8">
        <x-erp.card title="Informações" icon="info-circle">
            <div class="table-responsive">
            <table class="table table-borderless mb-0">
                <tr>
                    <th width="40%">Cliente</th>
                    <td>{{ $contaReceber->cliente->nome_razao_social ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Descrição</th>
                    <td>{{ $contaReceber->descricao }}</td>
                </tr>
                <tr>
                    <th>Valor</th>
                    <td>R$ {{ number_format($contaReceber->valor, 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Forma de pagamento</th>
                    <td>{{ ['dinheiro' => 'Dinheiro', 'pix' => 'PIX', 'cartao_credito' => 'Cartão de crédito', 'cartao_debito' => 'Cartão de débito'][$contaReceber->forma_pagamento] ?? ucfirst(str_replace('_', ' ', (string) ($contaReceber->forma_pagamento ?: '-'))) }}</td>
                </tr>
                <tr>
                    <th>Vencimento</th>
                    <td>{{ $contaReceber->vencimento->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <th>Parcela</th>
                    <td>{{ $contaReceber->parcela }}/{{ $contaReceber->total_parcelas }}</td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>
                        @php
                            $isOverdue = $contaReceber->status === 'pendente' && $contaReceber->vencimento->isPast();
                            $statusValue = $isOverdue ? 'vencida' : $contaReceber->status;
                        @endphp
                        <x-erp.status-badge :status="$statusValue" />
                    </td>
                </tr>
                @if($contaReceber->status === 'paga')
                <tr>
                    <th>Valor Pago</th>
                    <td>R$ {{ number_format($contaReceber->valor_pago, 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Pago em</th>
                    <td>{{ $contaReceber->pago_em?->format('d/m/Y') ?? '-' }}</td>
                </tr>
                @endif
                <tr>
                    <th>Lançada em</th>
                    <td>{{ $contaReceber->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') ?? '-' }}</td>
                </tr>
                @if($contaReceber->observacoes)
                <tr>
                    <th>Observações</th>
                    <td>{{ $contaReceber->observacoes }}</td>
                </tr>
                @endif
            </table>
            </div>
        </x-erp.card>

        {{-- Origem: de qual venda veio, por onde, quem vendeu, o que foi vendido e a transação na Stone (29/10/2026) --}}
        @if($venda = $contaReceber->venda)
        @php
            $nomesForma = ['dinheiro' => 'Dinheiro', 'pix' => 'PIX', 'cartao_credito' => 'Cartão de crédito', 'cartao_debito' => 'Cartão de débito',
                'boleto' => 'Boleto', 'crediario' => 'Crediário', 'transferencia' => 'Transferência', 'vale' => 'Vale troca', 'misto' => 'Misto'];
            $pagamentos = collect($venda->pagamento_detalhes ?? []);
            $comStone = $pagamentos->filter(fn ($pg) => ! empty($pg['stone_atk']));
            $pgStone = $comStone->firstWhere('forma', $contaReceber->forma_pagamento) ?? $comStone->first();
            $daMaquininha = \App\Services\Pos\PosStone::daMaquininha($venda->pagamento_detalhes)
                || \Illuminate\Support\Str::contains((string) $contaReceber->descricao, 'Maquininha');
            $canal = $daMaquininha ? 'pelo app IA365 Vendas' : ($venda->pedido_id ? 'Pedido' : 'PDV (sistema)');
        @endphp
        <x-erp.card title="Venda de origem" icon="receipt" class="mt-4">
            <div class="table-responsive">
            <table class="table table-borderless mb-3">
                <tr>
                    <th width="40%">Venda</th>
                    <td>
                        <a href="{{ route('app.vendas.show', $venda) }}" class="fw-semibold">#{{ $venda->numero }}</a>
                        @if($venda->pedido) · Pedido #{{ $venda->pedido->numero }} @endif
                        · {{ $venda->status?->label() ?? ucfirst((string) $venda->status?->value) }}
                    </td>
                </tr>
                <tr>
                    <th>Onde foi feita</th>
                    <td>
                        @if($daMaquininha) <span class="badge bg-dark"><i class="bi bi-credit-card-2-front me-1"></i>Maquininha Stone</span> @endif
                        {{ $canal }}
                    </td>
                </tr>
                <tr>
                    <th>Data e hora</th>
                    <td>{{ $venda->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}</td>
                </tr>
                <tr>
                    <th>Vendedor / operador</th>
                    <td>{{ $venda->vendedor->name ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Loja / caixa</th>
                    <td>{{ $venda->unidade->nome ?? '-' }} @if($venda->caixa) · Caixa {{ $venda->caixa->numero_caixa }} @endif</td>
                </tr>
                <tr>
                    <th>Cliente</th>
                    <td>
                        {{ $venda->cliente->nome_razao_social ?? 'Consumidor não identificado' }}
                        @if(! $venda->cliente && $venda->cpf_cnpj_nota) · CPF/CNPJ na nota {{ $venda->cpf_cnpj_nota }} @endif
                    </td>
                </tr>
                <tr>
                    <th>Quanto vendeu</th>
                    <td>
                        <span class="fw-bold">R$ {{ number_format($venda->total, 2, ',', '.') }}</span>
                        @php
                            $composicao = array_filter([
                                'subtotal R$ ' . number_format($venda->subtotal, 2, ',', '.'),
                                $venda->desconto_valor > 0 ? 'desconto R$ ' . number_format($venda->desconto_valor, 2, ',', '.') : null,
                                $venda->outras_despesas > 0 ? 'acréscimo R$ ' . number_format($venda->outras_despesas, 2, ',', '.') : null,
                            ]);
                        @endphp
                        <small class="text-muted">({{ implode(' · ', $composicao) }})</small>
                    </td>
                </tr>
                <tr>
                    <th>Forma de pagamento</th>
                    <td>
                        @forelse($pagamentos as $pg)
                            <div>
                                {{ $nomesForma[$pg['forma'] ?? ''] ?? ucfirst(str_replace('_', ' ', $pg['forma'] ?? '-')) }}
                                @if(in_array($pg['forma'] ?? '', ['cartao_credito', 'cartao_debito'], true) && ! empty($pg['bandeira_nome'])) {{ $pg['bandeira_nome'] }} @endif
                                @if(($pg['parcelas'] ?? 1) > 1) em {{ $pg['parcelas'] }}x @elseif(($pg['forma'] ?? '') === 'cartao_credito') à vista @endif
                                — R$ {{ number_format($pg['valor'] ?? 0, 2, ',', '.') }}
                            </div>
                        @empty
                            {{ $nomesForma[$venda->forma_pagamento] ?? ucfirst(str_replace('_', ' ', (string) $venda->forma_pagamento)) }}
                        @endforelse
                        @if($venda->troco > 0) <small class="text-muted">Troco R$ {{ number_format($venda->troco, 2, ',', '.') }}</small> @endif
                    </td>
                </tr>
            </table>
            </div>

            @if($pgStone)
            <h6 class="fw-bold mt-2 mb-2"><i class="bi bi-credit-card me-1"></i>Transação na Stone</h6>
            @include('app.vendas._stone', ['pg' => $pgStone])
            <div class="mb-3"></div>
            @endif

            <h6 class="fw-bold mt-2 mb-2"><i class="bi bi-box-seam me-1"></i>Produtos ({{ $venda->itens->count() }})</h6>
            <div class="table-responsive">
                <table class="erp-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th class="text-end">Qtd</th>
                            <th class="text-end">Unit.</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($venda->itens as $item)
                        <tr>
                            <td>{{ $item->descricao }}</td>
                            <td class="text-end">{{ number_format($item->quantidade, $item->quantidade == intval($item->quantidade) ? 0 : 3, ',', '.') }}</td>
                            <td class="text-end">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                            <td class="text-end">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-erp.card>
        @endif

        {{-- Parcelas --}}
        @if($parcelas->count() > 1)
        <x-erp.card title="Histórico de Parcelas" icon="list-ol" class="mt-4">
            <div class="table-responsive">
                <table class="erp-table">
                    <thead>
                        <tr>
                            <th>Parcela</th>
                            <th>Vencimento</th>
                            <th class="text-end">Valor</th>
                            <th class="text-center">Status</th>
                            <th>Pago em</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($parcelas as $parcela)
                        <tr class="{{ $parcela->id === $contaReceber->id ? 'table-active' : '' }}">
                            <td>{{ $parcela->parcela }}/{{ $parcela->total_parcelas }}</td>
                            <td>{{ $parcela->vencimento->format('d/m/Y') }}</td>
                            <td class="text-end">R$ {{ number_format($parcela->valor, 2, ',', '.') }}</td>
                            <td class="text-center">
                                @php
                                    $pOverdue = $parcela->status === 'pendente' && $parcela->vencimento->isPast();
                                    $pStatus = $pOverdue ? 'vencida' : $parcela->status;
                                @endphp
                                <x-erp.status-badge :status="$pStatus" />
                            </td>
                            <td>{{ $parcela->pago_em?->format('d/m/Y') ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-erp.card>
        @endif

        @if($contaReceber->status === 'pendente')
        <div class="d-flex gap-2 mt-4">
            <form method="POST" action="{{ route('app.contas-receber.baixar', $contaReceber) }}"
                data-confirm="Confirma o recebimento desta conta?">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle me-1"></i> Marcar como Paga
                </button>
            </form>
        </div>
        @endif
    </div>
</div>
@endsection
