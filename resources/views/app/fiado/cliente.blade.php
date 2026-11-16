@extends('layouts.app')

@section('title', $nomeForma . ' — ' . $cliente->nome_razao_social)

@section('content')
@php $brl = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.'); $hoje = now()->startOfDay(); @endphp
<x-erp.page-header :title="$cliente->nome_razao_social" :subtitle="$nomeForma . ' — o que o cliente deve e o que já pagou'" icon="journal-text">
    <a href="{{ route('app.fiado.index') }}" class="btn btn-erp-outline"><i class="bi bi-arrow-left me-1"></i> Quem me deve</a>
    <a href="{{ route('app.clientes.edit', $cliente) }}" class="btn btn-erp-outline"><i class="bi bi-pencil me-1"></i> Cadastro / limite</a>
</x-erp.page-header>

@if(session('fiado_recibo'))
    <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-printer"></i>
        <span>Pagamento registrado.</span>
        <a class="btn btn-sm btn-success ms-auto" target="_blank" href="{{ route('app.fiado.recibo', session('fiado_recibo')) }}" id="btnImprimirRecibo">
            <i class="bi bi-printer me-1"></i> Imprimir recibo
        </a>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <x-erp.stat-card icon="cash-stack" color="warning" :value="number_format($saldo['devendo'], 2, ',', '.')" label="Deve hoje" prefix="R$ " />
    </div>
    <div class="col-md-3">
        <x-erp.stat-card icon="exclamation-triangle" color="danger" :value="number_format($saldo['vencido'], 2, ',', '.')" label="Já vencido" prefix="R$ " />
    </div>
    <div class="col-md-3">
        <x-erp.stat-card icon="speedometer2" color="info" :value="$saldo['limite'] !== null ? number_format($saldo['limite'], 2, ',', '.') : 'Sem limite'" label="Limite" :prefix="$saldo['limite'] !== null ? 'R$ ' : ''" />
    </div>
    <div class="col-md-3">
        <x-erp.stat-card icon="bag-check" color="success" :value="$saldo['disponivel'] !== null ? number_format($saldo['disponivel'], 2, ',', '.') : 'Livre'" label="Ainda pode comprar" :prefix="$saldo['disponivel'] !== null ? 'R$ ' : ''" />
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4 order-lg-2">
        <x-erp.card title="Receber pagamento" icon="cash-coin">
            @if(! $podeReceber)
                <p class="text-muted mb-0">Seu perfil não recebe pagamentos. Peça ao caixa ou ao gerente.</p>
            @elseif($saldo['devendo'] <= 0)
                <p class="text-muted mb-0">Este cliente não deve nada.</p>
            @elseif(! $caixa)
                <p class="mb-2">O pagamento entra no caixa: abra o seu caixa nesta loja para receber.</p>
                <a href="{{ route('app.caixa.abrir') }}" class="btn btn-erp-primary w-100"><i class="bi bi-unlock me-1"></i> Abrir caixa</a>
            @else
                <form method="POST" action="{{ route('app.fiado.receber', $cliente->id) }}" id="formReceber"
                      data-confirm="Confirmar o recebimento?">
                    @csrf
                    <div class="mb-3">
                        <label for="campo-valor" class="form-label fw-semibold">Valor pago</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" step="0.01" min="0.01" max="{{ $saldo['devendo'] }}" name="valor" id="campo-valor"
                                   class="form-control form-control-lg" required value="{{ old('valor', number_format($saldo['devendo'], 2, '.', '')) }}">
                        </div>
                        <small class="text-muted">Pode ser só uma parte. Abate primeiro as contas mais antigas.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold d-block">Como pagou</label>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($formas as $i => $f)
                                <input type="radio" class="btn-check" name="chave" id="forma-{{ $f->chave }}" value="{{ $f->chave }}" @checked(old('chave', $formas->first()?->chave) === $f->chave) required>
                                <label class="btn btn-outline-primary" for="forma-{{ $f->chave }}"><i class="bi {{ $f->icone }} me-1"></i>{{ $f->nome }}</label>
                            @endforeach
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="campo-obs" class="form-label fw-semibold">Observação <small class="text-muted fw-normal">(opcional)</small></label>
                        <input type="text" name="observacoes" id="campo-obs" maxlength="255" class="form-control" value="{{ old('observacoes') }}">
                    </div>
                    <button type="submit" class="btn btn-success btn-lg w-100"><i class="bi bi-check2-circle me-1"></i> Receber</button>
                    <small class="text-muted d-block mt-2">Entra no caixa nº {{ $caixa->numero_caixa }} na forma escolhida.</small>
                </form>
            @endif
        </x-erp.card>
    </div>

    <div class="col-lg-8 order-lg-1">
        <x-erp.card title="Compras no {{ $nomeForma }}" icon="receipt">
            <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Compra</th><th>Vencimento</th><th class="text-end">Valor</th><th class="text-end">Pago</th><th class="text-end">Falta</th><th class="text-center">Situação</th></tr></thead>
                <tbody>
                @forelse($contas as $c)
                    @php
                        $falta = round((float) $c->valor - (float) $c->valor_pago, 2);
                        $aberta = in_array($c->status, ['pendente', 'vencida'], true);
                        $vencida = $aberta && $c->vencimento && $c->vencimento->lt($hoje);
                    @endphp
                    <tr class="{{ $aberta ? '' : 'text-muted' }}">
                        <td>
                            {{ $c->descricao }}
                            @if($c->venda)<br><small class="text-muted">{{ $c->venda->created_at?->format('d/m/Y') }} · <a href="{{ route('app.vendas.show', $c->venda) }}">ver venda</a></small>@endif
                        </td>
                        <td class="text-nowrap {{ $vencida ? 'text-danger fw-semibold' : '' }}">{{ $c->vencimento?->format('d/m/Y') }}</td>
                        <td class="text-end text-nowrap">{{ $brl($c->valor) }}</td>
                        <td class="text-end text-nowrap">{{ $brl($c->valor_pago) }}</td>
                        <td class="text-end fw-semibold text-nowrap">{{ $aberta ? $brl($falta) : '—' }}</td>
                        <td class="text-center">
                            @if(! $aberta)<span class="badge bg-success">Paga</span>
                            @elseif($vencida)<span class="badge bg-danger">Vencida</span>
                            @elseif((float) $c->valor_pago > 0)<span class="badge bg-info text-dark">Paga em parte</span>
                            @else<span class="badge bg-warning text-dark">Em aberto</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">Nenhuma compra no {{ $nomeForma }}.</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </x-erp.card>

        <div class="mt-4">
        <x-erp.card title="Pagamentos recebidos" icon="cash-coin">
            <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Recibo</th><th>Data</th><th>Como pagou</th><th class="text-end">Valor</th><th class="text-end">Ficou devendo</th><th>Quem recebeu</th><th></th></tr></thead>
                <tbody>
                @forelse($recebimentos as $r)
                    <tr>
                        <td>nº {{ $r->numero }}</td>
                        <td class="text-nowrap">{{ $r->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $r->formaLabel() }}</td>
                        <td class="text-end fw-semibold text-nowrap">{{ $brl($r->valor) }}</td>
                        <td class="text-end text-nowrap">{{ $brl($r->saldo_depois) }}</td>
                        <td>{{ $r->usuario->name ?? '—' }}</td>
                        <td class="text-end"><a href="{{ route('app.fiado.recibo', $r->id) }}" target="_blank" class="btn btn-sm btn-erp-outline" title="Imprimir recibo"><i class="bi bi-printer"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">Nenhum pagamento ainda.</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </x-erp.card>
        </div>
    </div>
</div>
@endsection
