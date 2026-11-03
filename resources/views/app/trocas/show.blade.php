@extends('layouts.app')

@section('title', $devolucao->tipoLabel() . ' #' . $devolucao->id)

@section('content')
<x-erp.page-header :title="$devolucao->tipoLabel() . ' #' . $devolucao->id" icon="arrow-repeat"
    :subtitle="($devolucao->venda ? 'Venda #' . $devolucao->venda->numero : 'Sem venda de origem') . ' · ' . $devolucao->created_at->format('d/m/Y H:i') . ' · ' . ($devolucao->unidade->nome ?? '')">
    <a href="{{ route('app.trocas.comprovante', $devolucao) }}?print=1" target="_blank" class="btn btn-erp-outline"><i class="bi bi-printer me-1"></i> Comprovante</a>
    @if($devolucao->vale)
    <a href="{{ route('app.trocas.vales.imprimir', $devolucao->vale) }}?print=1" target="_blank" class="btn btn-erp-outline"><i class="bi bi-ticket-perforated me-1"></i> Imprimir vale</a>
    @endif
    @if(! $devolucao->estaCancelada() && $devolucao->status === 'concluida')
    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalCancelarTroca"><i class="bi bi-x-octagon me-1"></i> Cancelar {{ strtolower($devolucao->tipoLabel()) }}</button>
    @endif
    <a href="{{ route('app.trocas.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
</x-erp.page-header>

@if($devolucao->estaCancelada())
<div class="alert alert-danger">
    <strong><i class="bi bi-x-octagon me-1"></i> {{ $devolucao->tipoLabel() }} CANCELADA</strong>
    em {{ $devolucao->cancelado_em?->format('d/m/Y H:i') }} por {{ $devolucao->canceladoPor->name ?? '-' }}.
    <div class="small">Motivo: {{ $devolucao->motivo_cancelamento }}</div>
</div>
@endif

@if(! $devolucao->estaCancelada() && $devolucao->status === 'concluida')
<div class="modal fade" id="modalCancelarTroca" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('app.trocas.cancelar', $devolucao) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Cancelar {{ strtolower($devolucao->tipoLabel()) }} #{{ $devolucao->id }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Isto vai desfazer, de uma vez:</p>
                <ul class="small">
                    @foreach($devolucao->itens->where('retorna_estoque', true) as $it)
                        <li>tirar do estoque {{ rtrim(rtrim(number_format($it->quantidade, 3, ',', '.'), '0'), ',') }}× {{ $it->produto->descricao ?? 'peça' }} ({{ $it->estoque->nome ?? 'estoque' }})</li>
                    @endforeach
                    @if($devolucao->vendaNova)<li>cancelar a venda nova #{{ $devolucao->vendaNova->numero }} (estoque, caixa e vale dela)</li>@endif
                    @if($devolucao->vale)<li>cancelar o vale {{ $devolucao->vale->codigo }} (só se não foi usado em outra venda)</li>@endif
                    @if($devolucao->forma_sobra === 'dinheiro')<li>repor R$ {{ number_format($devolucao->valor_sobra, 2, ',', '.') }} na gaveta (caixa aberto)</li>@endif
                    @if($devolucao->valor_abatido_parcelas > 0)<li>reabrir R$ {{ number_format($devolucao->valor_abatido_parcelas, 2, ',', '.') }} abatidos das parcelas</li>@endif
                </ul>
                <p class="small text-muted">Se algo não puder ser desfeito (peça já vendida, vale já gasto, sem caixa aberto), nada é alterado e o sistema explica o porquê.</p>
                <label class="form-label fw-semibold" for="motivoCancelarTroca">Motivo <span class="text-danger">*</span></label>
                <textarea name="motivo_cancelamento" id="motivoCancelarTroca" class="form-control" rows="2" maxlength="500" required>{{ old('motivo_cancelamento') }}</textarea>
                @php($ehGer = auth()->user()->is_admin || in_array(auth()->user()->perfil instanceof \App\Enums\Perfil ? auth()->user()->perfil->value : (string) auth()->user()->perfil, ['admin','dono','gerente'], true))
                @if(! $ehGer)
                <div class="row g-2 mt-2">
                    <div class="col-12 small text-muted">Precisa da autorização de um gerente:</div>
                    <div class="col"><input type="email" name="gerente_email" class="form-control" placeholder="e-mail do gerente" value="{{ old('gerente_email') }}"></div>
                    <div class="col"><input type="password" name="gerente_senha" class="form-control" placeholder="senha" autocomplete="off"></div>
                </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar</button>
                <button type="submit" class="btn btn-danger">Cancelar a {{ strtolower($devolucao->tipoLabel()) }}</button>
            </div>
        </form>
    </div>
</div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <x-erp.card title="Itens devolvidos" icon="box-arrow-in-left">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Produto</th><th class="text-end">Qtd</th><th class="text-end">Valor unit.</th><th class="text-end">Total</th><th>Destino</th></tr></thead>
                    <tbody>
                    @foreach($devolucao->itens as $item)
                        <tr>
                            <td>{{ $item->produto->descricao ?? 'Item' }}@if($item->venda_item_id === null) <span class="badge bg-warning text-dark ms-1" title="Peça bipada no F6 — entrou pelo preço de venda atual">{{ $devolucao->venda ? 'sem cupom desta venda' : 'sem venda no sistema' }}</span>@endif</td>
                            <td class="text-end">{{ rtrim(rtrim(number_format($item->quantidade, 3, ',', '.'), '0'), ',') }}</td>
                            <td class="text-end">R$ {{ number_format($item->valor_unitario, 2, ',', '.') }}</td>
                            <td class="text-end fw-semibold">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
                            <td><small>@if($item->retorna_estoque)<i class="bi bi-box-seam me-1 text-success"></i>voltou ao estoque {{ $item->estoque->nome ?? '' }}@else<i class="bi bi-x-octagon me-1 text-danger"></i>avariado — não volta @endif</small></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr class="table-light"><th colspan="3" class="text-end">Valor devolvido</th><th class="text-end">R$ {{ number_format($devolucao->valor_estornado, 2, ',', '.') }}</th><th></th></tr></tfoot>
                </table>
            </div>
        </x-erp.card>

        <x-erp.card title="Como fechou" icon="cash-coin" class="mt-4">
            <dl class="row mb-0">
                @if($devolucao->valor_abatido_parcelas > 0)
                <dt class="col-sm-4">Abatido das parcelas em aberto</dt><dd class="col-sm-8">R$ {{ number_format($devolucao->valor_abatido_parcelas, 2, ',', '.') }}</dd>
                @endif
                <dt class="col-sm-4">Sobra a favor do cliente</dt>
                <dd class="col-sm-8">R$ {{ number_format($devolucao->valor_sobra, 2, ',', '.') }} — {{ $devolucao->formaSobraLabel() }}
                    @if($devolucao->vale)
                        <div class="mt-1"><code class="fs-6">{{ $devolucao->vale->codigo }}</code>
                        <span class="badge bg-{{ $devolucao->vale->statusColor() }}">{{ $devolucao->vale->statusLabel() }}</span>
                        · saldo R$ {{ number_format($devolucao->vale->saldo, 2, ',', '.') }}
                        · {{ $devolucao->vale->validade ? 'válido até ' . $devolucao->vale->validade->format('d/m/Y') : 'sem validade' }}</div>
                        @if($devolucao->vale->usos->count())
                        <ul class="small text-muted mt-1 mb-0">
                            @foreach($devolucao->vale->usos as $uso)
                            <li>{{ $uso->created_at->format('d/m/Y H:i') }} — {{ $uso->tipo === 'dinheiro' ? 'devolvido em dinheiro' : 'usado na venda' }}
                                @if($uso->venda)<a href="{{ route('app.vendas.show', $uso->venda) }}">#{{ $uso->venda->numero }}</a>@endif: R$ {{ number_format($uso->valor, 2, ',', '.') }}</li>
                            @endforeach
                        </ul>
                        @endif
                    @endif
                    @if($devolucao->forma_sobra === 'dinheiro' && $devolucao->caixa)
                        <div class="small text-muted">Saída registrada no <a href="{{ route('app.caixa.show', $devolucao->caixa) }}">caixa #{{ $devolucao->caixa->numero_caixa }}</a></div>
                    @endif
                </dd>
                @if($devolucao->vendaNova)
                <dt class="col-sm-4">Venda nova (o que levou)</dt><dd class="col-sm-8"><a href="{{ route('app.vendas.show', $devolucao->vendaNova) }}">Venda #{{ $devolucao->vendaNova->numero }}</a> — R$ {{ number_format($devolucao->vendaNova->total, 2, ',', '.') }}</dd>
                @elseif($devolucao->tipo === 'troca')
                <dt class="col-sm-4">Venda nova</dt><dd class="col-sm-8 text-muted">Ainda não finalizada no PDV — o crédito segue no vale.</dd>
                @endif
            </dl>
        </x-erp.card>
    </div>

    <div class="col-lg-4">
        <x-erp.card title="Dados" icon="info-circle">
            <dl class="mb-0">
                <dt>Venda de origem</dt>
                <dd>@if($devolucao->venda)<a href="{{ route('app.vendas.show', $devolucao->venda) }}">#{{ $devolucao->venda->numero }}</a> · {{ $devolucao->venda->created_at->format('d/m/Y') }} · {{ $devolucao->venda->unidade->nome ?? '' }}@else <span class="text-muted">peça sem venda no sistema</span>@endif</dd>
                <dt>Cliente</dt><dd>{{ $devolucao->venda->cliente->nome_razao_social ?? 'Consumidor' }}</dd>
                <dt>Motivo</dt><dd>{{ $devolucao->motivo }}</dd>
                <dt>Registrado por</dt><dd>{{ $devolucao->user->name ?? '-' }}</dd>
                @if($devolucao->fora_politica || $devolucao->aprovador)
                <dt>Política</dt>
                <dd>@if($devolucao->fora_politica)<span class="badge bg-warning text-dark">fora da política</span> {{ $devolucao->motivo_fora_politica }}@endif
                    @if($devolucao->aprovador)<div class="small">Autorizado por <strong>{{ $devolucao->aprovador->name }}</strong></div>@endif</dd>
                @endif
                @if($devolucao->observacoes)<dt>Observações</dt><dd>{{ $devolucao->observacoes }}</dd>@endif
            </dl>
        </x-erp.card>
    </div>
</div>
@endsection
