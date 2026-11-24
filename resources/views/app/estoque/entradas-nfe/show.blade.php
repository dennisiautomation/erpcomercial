@extends('layouts.app')

@section('title', 'Entrada NF-e ' . $entrada->numero)

@section('content')
@php $fmt = fn ($v, $d = 2) => number_format((float) $v, $d, ',', '.'); @endphp

<x-erp.page-header title="NF-e nº {{ $entrada->numero }} — no estoque" icon="box-seam"
    subtitle="{{ $entrada->fornecedor?->razao_social ?? $entrada->nome_emitente }}">
    @if($entrada->devolucao_situacao !== 'total')
        <a href="#devolver" class="btn btn-outline-danger" data-bs-toggle="collapse" role="button" aria-expanded="{{ old('motivo') ? 'true' : 'false' }}" aria-controls="devolver">
            <i class="bi bi-box-arrow-left me-1"></i> Devolver ao fornecedor
        </a>
    @endif
    <a href="{{ route('app.entradas-nfe.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

@if($entrada->devolucao_situacao)
    <div class="alert {{ $entrada->devolucao_situacao === 'total' ? 'alert-secondary' : 'alert-warning' }} py-2 small">
        <i class="bi bi-box-arrow-left me-1"></i>
        <strong>{{ $entrada->devolucao_situacao === 'total' ? 'Nota devolvida ao fornecedor' : 'Nota devolvida em parte' }}</strong>
        — veja as devoluções no fim da página.
    </div>
@endif

{{-- Devolução de compra ao fornecedor (24/11/2026): sem emitir nota; estoque sai e as contas a pagar da nota são abatidas --}}
@if($entrada->devolucao_situacao !== 'total')
<div class="collapse {{ old('motivo') ? 'show' : '' }} mb-3" id="devolver">
    <form method="POST" action="{{ route('app.entradas-nfe.devolver', $entrada) }}" class="erp-card border-danger-subtle"
          data-confirm="Registrar a devolução? Os itens saem do estoque e as contas a pagar desta nota são abatidas.">
        @csrf
        <div class="card-body">
            <h2 class="h6 mb-1"><i class="bi bi-box-arrow-left me-1"></i> Devolver ao fornecedor</h2>
            <p class="small text-muted mb-3">
                Informe quanto volta de cada item, na unidade da nota. O que voltar sai do estoque
                <strong>{{ $entrada->estoque?->nome }}</strong> e o valor é tirado das contas a pagar desta nota que ainda estão em aberto.
                O sistema não emite nota de devolução. A nota nova do fornecedor entra pelo caminho de sempre.
            </p>
            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Item</th>
                            <th class="text-end">Na nota</th>
                            <th class="text-end">Já devolvido</th>
                            <th class="text-end" style="width:11rem">Devolver agora</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @foreach($entrada->itens as $item)
                            @php $jaQtd = $jaDevolvido[$item->id]['qtd'] ?? 0; $resta = max(0, round((float) $item->quantidade - $jaQtd, 4)); @endphp
                            <tr>
                                <td>{{ $item->descricao }}
                                    @if($item->acao === 'ignorar')<span class="text-muted">· não entrou no estoque</span>@endif
                                </td>
                                <td class="text-end">{{ $fmt($item->quantidade, 3) }} {{ $item->unidade_comercial }}</td>
                                <td class="text-end">{{ $jaQtd > 0 ? $fmt($jaQtd, 3) : '—' }}</td>
                                <td class="text-end">
                                    @if($resta > 0)
                                        <div class="input-group input-group-sm">
                                            <input type="text" inputmode="decimal" class="form-control text-end" name="quantidades[{{ $item->id }}]"
                                                   value="{{ old('quantidades.' . $item->id) }}" placeholder="0" data-resta="{{ $resta }}"
                                                   aria-label="Devolver de {{ $item->descricao }}">
                                            <span class="input-group-text">de {{ $fmt($resta, 3) }}</span>
                                        </div>
                                    @else
                                        <span class="text-muted">devolvido</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-link btn-sm px-0 mb-3" id="devolverTudo">Devolver tudo o que resta</button>
            <div class="row g-2 mb-3">
                <div class="col-md-8">
                    <label class="form-label small fw-semibold" for="devMotivo">Motivo <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="devMotivo" name="motivo" maxlength="500" required
                           value="{{ old('motivo') }}" placeholder="Ex.: mercadoria com defeito, o fornecedor vai mandar outra">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold" for="devDocumento">Nota de devolução (opcional)</label>
                    <input type="text" class="form-control" id="devDocumento" name="documento" maxlength="60"
                           value="{{ old('documento') }}" placeholder="Número ou chave, se alguém emitiu">
                </div>
            </div>
            <div class="small mb-3">
                @if($contasAbertas->isNotEmpty())
                    <i class="bi bi-cash-stack me-1"></i>
                    Contas a pagar em aberto desta nota: <strong>{{ $contasAbertas->count() }}</strong>
                    (R$ {{ $fmt($contasAbertas->sum(fn ($c) => (float) $c->valor - (float) $c->valor_pago)) }}).
                    O valor devolvido sai delas, da última parcela para a primeira; a parcela que zerar fica cancelada.
                    @unless($podeFinanceiro)
                        <div class="text-danger mt-1">Só quem cuida do financeiro (dono ou financeiro) pode registrar esta devolução.</div>
                    @endunless
                @elseif($entrada->contas_pagar_geradas)
                    <i class="bi bi-cash-stack me-1"></i>
                    As contas a pagar desta nota já estão pagas: o valor devolvido fica como <strong>crédito com o fornecedor</strong> (anotado aqui; o caixa e o banco não mudam).
                @else
                    <i class="bi bi-info-circle me-1"></i> Esta nota não gerou contas a pagar no sistema: só o estoque muda.
                @endif
            </div>
            <button type="submit" class="btn btn-danger" @disabled($contasAbertas->isNotEmpty() && ! $podeFinanceiro)>
                <i class="bi bi-check-lg me-1"></i> Registrar devolução
            </button>
        </div>
    </form>
</div>
@endif

<div class="erp-card mb-3">
    <div class="card-body">
        <div class="row g-3 small">
            <div class="col-6 col-md-3">
                <span class="text-muted d-block">Lançada em</span>
                <strong>{{ $entrada->concluida_em?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</strong>
                <span class="text-muted d-block">por {{ $entrada->concluidor?->name }}</span>
            </div>
            <div class="col-6 col-md-3">
                <span class="text-muted d-block">Estoque</span>
                <strong>{{ $entrada->estoque?->nome }}</strong>
            </div>
            <div class="col-6 col-md-3">
                <span class="text-muted d-block">Custo dos produtos</span>
                <strong>{{ $entrada->atualizou_custo ? 'Atualizado' : 'Mantido' }}</strong>
            </div>
            <div class="col-6 col-md-3">
                <span class="text-muted d-block">Contas a pagar</span>
                <strong>{{ $entrada->contas_pagar_geradas ? $entrada->contas_pagar_geradas . ' criada(s)' : 'Não lançadas' }}</strong>
            </div>
            <div class="col-12">
                <span class="text-muted">Chave</span>
                <span class="font-monospace">{{ $entrada->chave_acesso }}</span>
                · <span class="text-muted">Total</span> <strong>R$ {{ $fmt($entrada->valor_total) }}</strong>
            </div>
        </div>
    </div>
</div>

<div class="erp-card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light small">
                <tr>
                    <th>Na nota</th>
                    <th class="text-end">Qtd na nota</th>
                    <th>Produto</th>
                    <th class="text-end">Entrou</th>
                    <th class="text-end">Custo un.</th>
                </tr>
            </thead>
            <tbody class="small">
                @foreach($entrada->itens as $item)
                    <tr @class(['text-muted' => $item->acao === 'ignorar'])>
                        <td>{{ $item->descricao }} <span class="text-muted">· cód. {{ $item->codigo_fornecedor }}</span></td>
                        <td class="text-end">{{ $fmt($item->quantidade, 3) }} {{ $item->unidade_comercial }}</td>
                        <td>
                            @if($item->acao === 'ignorar')
                                <em>não lançado</em>
                            @elseif($item->produto)
                                <a href="{{ route('app.produtos.show', $item->produto_id) }}">{{ $item->produto->descricao }}</a>
                                @if($item->acao === 'criar') <span class="badge bg-info-subtle text-info">novo</span> @endif
                            @endif
                        </td>
                        <td class="text-end">{{ $item->acao === 'ignorar' ? '—' : $fmt($item->quantidadeEntrada(), 3) }}</td>
                        <td class="text-end">{{ $item->acao === 'ignorar' ? '—' : 'R$ ' . $fmt($item->custoUnitarioEntrada()) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if($entrada->devolucoes->isNotEmpty())
<div class="erp-card mt-3">
    <div class="card-body">
        <h2 class="h6 mb-3"><i class="bi bi-box-arrow-left me-1"></i> Devoluções ao fornecedor</h2>
        @foreach($entrada->devolucoes as $dev)
            <div @class(['border rounded p-2 mb-2 small', 'bg-light text-muted' => $dev->estornada()])>
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <strong>Devolução nº {{ $dev->numero }} — R$ {{ $fmt($dev->valor_total) }}
                        @if($dev->estornada()) <span class="badge bg-secondary ms-1">estornada</span> @endif
                    </strong>
                    <span class="text-muted">{{ $dev->created_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }} · {{ $dev->user?->name }}</span>
                </div>
                <div>Motivo: {{ $dev->motivo }}</div>
                @if($dev->documento)<div>Nota de devolução: <span class="font-monospace">{{ $dev->documento }}</span></div>@endif
                <ul class="mb-1 ps-3">
                    @foreach($dev->itens as $di)
                        @php $it = $entrada->itens->firstWhere('id', $di->nfe_entrada_item_id); @endphp
                        <li>{{ $it?->descricao }}: {{ $fmt($di->quantidade_nota, 3) }} {{ $it?->unidade_comercial }}
                            @if((float) $di->quantidade_estoque > 0) (saíram {{ $fmt($di->quantidade_estoque, 3) }} do estoque) @endif
                            — R$ {{ $fmt($di->valor) }}</li>
                    @endforeach
                </ul>
                @if((float) $dev->valor_abatido > 0)
                    <div>Contas a pagar abatidas: R$ {{ $fmt($dev->valor_abatido) }}
                        @foreach((array) $dev->contas_ajustadas as $aj)
                            <span class="text-muted">· parcela {{ $aj['parcela'] ?? '' }}: R$ {{ $fmt($aj['valor_antes']) }} → {{ ($aj['status_depois'] ?? '') === 'cancelada' ? 'cancelada' : 'R$ ' . $fmt($aj['valor_depois']) }}</span>
                        @endforeach
                    </div>
                @endif
                @if((float) $dev->credito_fornecedor > 0 && ! $dev->estornada())
                    <div class="text-warning-emphasis">Crédito com o fornecedor (já estava pago): R$ {{ $fmt($dev->credito_fornecedor) }}</div>
                @endif
                @if($dev->estornada())
                    {{-- Estorno (24/11/2026) --}}
                    <div class="mt-1"><i class="bi bi-arrow-counterclockwise me-1"></i>Estornada em
                        {{ $dev->estornada_em->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }} por {{ $dev->estornador?->name }}
                        — {{ $dev->estorno_motivo }}. A mercadoria voltou ao estoque{{ ! empty($dev->contas_ajustadas) ? ' e as contas a pagar voltaram como estavam' : '' }}.</div>
                @else
                    <button class="btn btn-sm btn-outline-secondary mt-1" type="button" data-bs-toggle="collapse"
                            data-bs-target="#estornar{{ $dev->id }}" aria-expanded="false" aria-controls="estornar{{ $dev->id }}">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Estornar esta devolução
                    </button>
                    <div class="collapse mt-2" id="estornar{{ $dev->id }}">
                        <form method="POST" action="{{ route('app.entradas-nfe.devolucoes.estornar', [$entrada, $dev->id]) }}"
                              class="d-flex flex-wrap gap-2 align-items-end"
                              data-confirm="Estornar a devolução nº {{ $dev->numero }}? A mercadoria volta ao estoque{{ ! empty($dev->contas_ajustadas) ? ' e as contas a pagar voltam como estavam' : '' }}.">
                            @csrf
                            <div class="flex-grow-1">
                                <label class="form-label small mb-1" for="estMotivo{{ $dev->id }}">Motivo do estorno</label>
                                <input type="text" class="form-control form-control-sm" id="estMotivo{{ $dev->id }}" name="estorno_motivo"
                                       maxlength="500" required placeholder="Ex.: o fornecedor não aceitou a devolução">
                            </div>
                            <button type="submit" class="btn btn-sm btn-secondary">Confirmar estorno</button>
                        </form>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endif

@if($entrada->devolucao_situacao !== 'total')
<script>
document.getElementById('devolverTudo')?.addEventListener('click', function () {
    document.querySelectorAll('#devolver input[data-resta]').forEach(function (i) {
        i.value = String(i.dataset.resta).replace('.', ',');
    });
});
</script>
@endif
@endsection
