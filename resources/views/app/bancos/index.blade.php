@extends('layouts.app')

@section('title', 'Open Finance')

@section('content')
@php
    $brl = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $quem = function ($t) {
        if (! $t) return '—';
        if ($t instanceof \App\Models\ContaReceber) return $t->cliente?->nome_razao_social ?: 'Sem cliente';
        return $t->fornecedor?->razao_social ?: $t->funcionario?->name ?: $t->favorecido_nome ?: 'Sem favorecido';
    };
    $abas = [
        'conferir' => ['Para confirmar', $totais['conferir']],
        'sem' => ['Sem sugestão', $totais['sem']],
        'feitos' => ['Conciliados', $totais['feitos']],
        'ignorados' => ['Ignorados', $totais['ignorados']],
    ];
@endphp
<div class="fade-in">
<div class="page-header">
    <div>
        <h4><i class="bi bi-bank me-2"></i>Open Finance</h4>
        <div class="subtitle">Contas do banco ligadas pelo Open Finance e conciliação · {{ $usadas }} de {{ $limite }} {{ $limite === 1 ? 'conta usada' : 'contas usadas' }}</div>
    </div>
    @if($usadas < $limite)
        <button type="button" class="btn btn-erp btn-erp-primary" data-bs-toggle="modal" data-bs-target="#modalNovaConta">
            <i class="bi bi-plus-lg me-1"></i> Ligar uma conta
        </button>
    @else
        <span class="text-muted small">Todas as contas do plano estão em uso. Para ligar mais, fale com a IA365.</span>
    @endif
</div>

{{-- ---------------- Painel ---------------- --}}
@if($contas->isNotEmpty())
@php $pctMes = $painel['total_mes'] > 0 ? (int) round($painel['feitos_mes'] / $painel['total_mes'] * 100) : 0; @endphp
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="erp-card p-3 h-100">
            <div class="text-muted small">Saldo nas contas</div>
            <div class="fs-4 fw-semibold">{{ $painel['saldo'] === null ? '—' : $brl($painel['saldo']) }}</div>
            <div class="text-muted small">
                @if($painel['saldo'] === null)
                    O banco ainda não informou.
                @else
                    Informado pelo banco em {{ $painel['saldo_em']?->format('d/m H:i') }}
                    @if($painel['saldo_aplicado'] > 0) · mais {{ $brl($painel['saldo_aplicado']) }} em aplicação automática @endif
                @endif
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <a class="erp-card p-3 h-100 d-block text-decoration-none text-reset" href="{{ route('app.bancos.index', ['ver' => 'conferir']) }}">
            <div class="text-muted small">Para confirmar</div>
            <div class="fs-4 fw-semibold">{{ $painel['confirmar_qtd'] }}</div>
            <div class="text-muted small">{{ $painel['confirmar_qtd'] ? $brl($painel['confirmar_valor']) . ' já com sugestão' : 'Nada esperando você' }}</div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a class="erp-card p-3 h-100 d-block text-decoration-none text-reset" href="{{ route('app.bancos.index', ['ver' => 'sem']) }}">
            <div class="text-muted small">Sem sugestão</div>
            <div class="fs-4 fw-semibold">{{ $painel['sem_qtd'] }}</div>
            <div class="text-muted small">{{ $painel['sem_qtd'] ? $brl($painel['sem_valor']) . ' para classificar' : 'Tudo classificado' }}</div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <div class="erp-card p-3 h-100">
            <div class="text-muted small">{{ ucfirst(now()->translatedFormat('F')) }} na conta</div>
            <div class="small mt-1"><span class="text-success fw-semibold">+ {{ $brl($painel['entradas_mes']) }}</span> entraram</div>
            <div class="small"><span class="text-danger fw-semibold">− {{ $brl($painel['saidas_mes']) }}</span> saíram</div>
            <div class="progress mt-2" style="height: 6px;" role="progressbar" aria-label="Conciliado no mês" aria-valuenow="{{ $pctMes }}" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar bg-success" style="width: {{ $pctMes }}%"></div>
            </div>
            <div class="text-muted small mt-1">{{ $painel['feitos_mes'] }} de {{ $painel['total_mes'] }} conciliados ({{ $pctMes }}%)</div>
        </div>
    </div>
</div>
@endif

{{-- ---------------- Contas ---------------- --}}
<div class="erp-card mb-4">
    <div class="table-responsive">
        <table class="erp-table">
            <thead>
                <tr>
                    <th>Conta</th>
                    <th>Dono da conta</th>
                    <th>Loja</th>
                    <th>Situação</th>
                    <th>Último extrato</th>
                    <th width="330">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contas as $c)
                <tr>
                    <td>
                        <strong>{{ $c->apelido }}</strong>
                        <div class="text-muted small">{{ $c->descricaoConta() }}</div>
                    </td>
                    <td>
                        {{ $c->titularFormatado() }}
                        @if($c->titular_nome)<div class="text-muted small">{{ $c->titular_nome }}</div>@endif
                    </td>
                    <td>{{ $c->unidade?->nome ?? 'Todas' }}</td>
                    <td>
                        @if($c->situacao === 'conectada')
                            <span class="badge bg-success">Conectada</span>
                        @else
                            <span class="badge bg-warning text-dark">Aguardando você autorizar no banco</span>
                        @endif
                        @if($c->ultimo_erro)
                            <div class="text-danger small mt-1">{{ $c->ultimo_erro }}</div>
                        @endif
                    </td>
                    <td>
                        {{ $c->ultima_sincronizacao_em?->format('d/m/Y H:i') ?? '—' }}
                        @if($c->protocolo_pendente)<div class="text-muted small">Banco preparando o extrato…</div>@endif
                        @if($c->saldo !== null)
                            <div class="small mt-1">Saldo <strong>{{ $brl($c->saldo) }}</strong> <span class="text-muted">em {{ $c->saldo_atualizado_em?->format('d/m H:i') }}</span></div>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-2">
                            @if($c->situacao !== 'conectada' && $c->link_conexao)
                                <a href="{{ $c->link_conexao }}" target="_blank" rel="noopener" class="btn btn-sm btn-erp btn-erp-primary">
                                    <i class="bi bi-box-arrow-up-right me-1"></i> Conectar ao banco
                                </a>
                            @endif
                            <form method="POST" action="{{ route('app.bancos.contas.atualizar', $c) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-arrow-repeat me-1"></i> {{ $c->situacao === 'conectada' ? 'Buscar extrato' : 'Já autorizei' }}
                                </button>
                            </form>
                            @if($c->situacao === 'conectada')
                            <form method="POST" action="{{ route('app.bancos.contas.cartoes', $c) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-credit-card me-1"></i> Cartões</button>
                            </form>
                            @endif
                            <form method="POST" action="{{ route('app.bancos.contas.desconectar', $c) }}"
                                  onsubmit="return confirm('Desconectar esta conta do banco? O que já foi conciliado continua como está.');">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger">Desconectar</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @foreach($cartoes->where('conta_bancaria_id', $c->id) as $cc)
                <tr style="background: rgba(79,70,229,.03);">
                    <td class="ps-4">
                        <i class="bi bi-credit-card me-1 text-muted"></i> <strong>{{ $cc->nome() }}</strong>
                        @if($cc->adicionais)<div class="text-muted small ps-4">Adicionais: {{ str_replace(',', ', ', $cc->adicionais) }}</div>@endif
                    </td>
                    <td class="text-muted small">
                        Cartão de crédito · não conta como conta extra
                        @if($cc->limite_disponivel !== null)<div>Limite disponível <strong class="text-body">{{ $brl($cc->limite_disponivel) }}</strong>@if($cc->limite !== null) de {{ $brl($cc->limite) }}@endif</div>@endif
                    </td>
                    <td></td>
                    <td>
                        @if($cc->importar)
                            <span class="badge bg-success">Trazendo as compras</span>
                        @else
                            <span class="badge bg-secondary">Desligado</span>
                        @endif
                        @if($cc->ultimo_erro)<div class="text-danger small mt-1">{{ $cc->ultimo_erro }}</div>@endif
                    </td>
                    <td>
                        {{ $cc->ultima_sincronizacao_em?->format('d/m/Y H:i') ?? '—' }}
                        @if($cc->protocolo_pendente)<div class="text-muted small">Banco preparando…</div>@endif
                    </td>
                    <td>
                        <form method="POST" action="{{ route('app.bancos.cartoes.alternar', $cc) }}">
                            @csrf
                            <button class="btn btn-sm {{ $cc->importar ? 'btn-outline-secondary' : 'btn-erp btn-erp-primary' }}">
                                {{ $cc->importar ? 'Parar de trazer' : 'Trazer as compras' }}
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma conta ligada ainda. Clique em <strong>Ligar uma conta</strong> para começar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ---------------- Conciliação ---------------- --}}
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
    <ul class="nav nav-pills">
        @foreach($abas as $chave => [$rotulo, $qtd])
            <li class="nav-item">
                <a class="nav-link {{ $filtro === $chave ? 'active' : '' }}"
                   href="{{ route('app.bancos.index', array_filter(['ver' => $chave, 'conta' => $contaId, 'cartao' => $cartaoId])) }}">
                    {{ $rotulo }} <span class="badge bg-light text-dark ms-1">{{ $qtd }}</span>
                </a>
            </li>
        @endforeach
    </ul>
    <div class="d-flex align-items-center gap-2">
        @if($contas->count() > 1 || $cartoes->isNotEmpty())
            <select class="form-select form-select-sm" style="width: auto;" aria-label="Conta ou cartão" onchange="window.location = this.value">
                <option value="{{ route('app.bancos.index', ['ver' => $filtro]) }}">Todas as contas e cartões</option>
                @foreach($contas as $c)
                    <option value="{{ route('app.bancos.index', ['ver' => $filtro, 'conta' => $c->id]) }}" {{ $contaId === $c->id ? 'selected' : '' }}>{{ $c->apelido }}</option>
                @endforeach
                @foreach($cartoes as $cc)
                    <option value="{{ route('app.bancos.index', ['ver' => $filtro, 'cartao' => $cc->id]) }}" {{ $cartaoId === $cc->id ? 'selected' : '' }}>{{ $cc->nome() }}</option>
                @endforeach
            </select>
        @endif
        @if($filtro === 'sem' && $conhecidos > 0)
            <form method="POST" action="{{ route('app.bancos.lancar-conhecidos') }}"
                  onsubmit="return confirm('Lançar {{ $conhecidos }} despesa(s) com a classificação que o sistema já aprendeu? Cada uma entra como paga, com a data e o valor do banco.');">
                @csrf
                <input type="hidden" name="conta" value="{{ $contaId }}">
                <button class="btn btn-sm btn-success">
                    <i class="bi bi-lightning-charge me-1"></i> Lançar as {{ $conhecidos }} já conhecidas
                </button>
            </form>
        @endif
        @if($filtro === 'conferir' && $totais['certos'] > 0)
            <form method="POST" action="{{ route('app.bancos.confirmar-certos') }}"
                  onsubmit="return confirm('Confirmar os {{ $totais['certos'] }} lançamentos que o sistema tem como certos? As contas serão baixadas com a data e o valor do banco.');">
                @csrf
                <input type="hidden" name="conta" value="{{ $contaId }}">
                <button class="btn btn-sm btn-success">
                    <i class="bi bi-check2-all me-1"></i> Confirmar os {{ $totais['certos'] }} certos
                </button>
            </form>
        @endif
    </div>
</div>

<div class="erp-card">
    <div class="table-responsive">
        <table class="erp-table align-middle">
            <thead>
                <tr>
                    <th width="95">Data</th>
                    <th>No banco</th>
                    <th width="130" class="text-end">Valor</th>
                    <th>{{ $filtro === 'feitos' ? 'Conciliado com' : ($filtro === 'conferir' ? 'O sistema sugere' : 'Conta') }}</th>
                    <th width="250">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lancamentos as $l)
                @php
                    $titulo = $filtro === 'feitos'
                        ? ($l->ehCredito() ? $l->contaReceber : $l->contaPagar)
                        : ($l->ehCredito() ? $l->sugestaoContaReceber : $l->sugestaoContaPagar);
                @endphp
                <tr>
                    <td>{{ $l->data->format('d/m/Y') }}</td>
                    <td>
                        <div>{{ $l->descricao ?: 'Sem descrição' }}
                            @if($l->operacao)<span class="badge bg-light text-dark ms-1">{{ $l->operacao }}</span>@endif
                            @if($l->cartao)<span class="badge bg-info text-dark ms-1"><i class="bi bi-credit-card me-1"></i>{{ $l->cartao->nome() }}</span>@endif
                            @if($l->parcela_total > 1)<span class="badge bg-light text-dark ms-1">parcela {{ $l->parcela_numero }}/{{ $l->parcela_total }}</span>@endif
                        </div>
                        <div class="text-muted small">
                            {{ $l->contraparte_nome }}
                            @if($l->contraparte_documento) · {{ $l->contraparte_documento }} @endif
                            @if($contas->count() > 1) · {{ $l->contaBancaria?->apelido }} @endif
                        </div>
                        @if($l->sumiu_no_banco)
                            <div class="text-danger small"><i class="bi bi-exclamation-triangle me-1"></i>O banco deixou de mostrar este lançamento. Confira se a baixa ainda vale.</div>
                        @endif
                    </td>
                    <td class="text-end fw-semibold {{ $l->ehCredito() ? 'text-success' : 'text-danger' }}">
                        {{ $l->ehCredito() ? '+' : '−' }} {{ $brl($l->valor) }}
                    </td>
                    <td>
                        @php $fatura = $filtro === 'feitos' ? $l->faturaCartao : ($filtro === 'conferir' ? $l->sugestaoFaturaCartao : null); @endphp
                        @if($fatura)
                            <div><strong>Pagamento da fatura</strong> — {{ $fatura->nome() }}</div>
                            <div class="text-muted small">Não entra como despesa: as compras do cartão já entram uma a uma.</div>
                            @if($filtro === 'conferir')
                                <div class="small mt-1"><span class="badge bg-success">Certo</span> <span class="text-muted">{{ $l->sugestao_motivo }}</span></div>
                            @endif
                        @elseif($filtro === 'ignorados' && $l->sugestao_motivo)
                            <span class="text-muted">{{ $l->sugestao_motivo }}</span>
                        @elseif($titulo)
                            <div><strong>{{ $quem($titulo) }}</strong> — {{ $titulo->descricao }}</div>
                            <div class="text-muted small">
                                {{ $l->ehCredito() ? 'A receber' : 'A pagar' }} · {{ $brl($titulo->valor) }} · vence {{ $titulo->vencimento?->format('d/m/Y') }}
                                @if(! $l->ehCredito() && $titulo->centroCusto) · {{ $titulo->centroCusto->nome }} @endif
                            </div>
                            @if($filtro === 'conferir')
                                <div class="small mt-1">
                                    <span class="badge {{ $l->confiancaAlta() ? 'bg-success' : 'bg-warning text-dark' }}">{{ $l->confiancaAlta() ? 'Certo' : 'Conferir' }}</span>
                                    <span class="text-muted">{{ $l->sugestao_motivo }}</span>
                                </div>
                            @endif
                        @elseif($filtro === 'sem')
                            @php $opcoes = ($l->ehCredito() ? $abertasReceber : $abertasPagar); @endphp
                            <form method="POST" action="{{ route('app.bancos.lancamentos.confirmar', $l) }}" class="d-flex gap-2">
                                @csrf
                                <select name="titulo_id" class="form-select form-select-sm" required aria-label="Conta que corresponde">
                                    <option value="">{{ $opcoes->isEmpty() ? 'Nenhuma conta em aberto' : 'Escolha a conta…' }}</option>
                                    @foreach($opcoes as $o)
                                        <option value="{{ $o->id }}">{{ $o->vencimento?->format('d/m') }} · {{ $brl($o->valor) }} · {{ \Illuminate\Support\Str::limit($quem($o) . ' — ' . $o->descricao, 60) }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-sm btn-success" {{ $opcoes->isEmpty() ? 'disabled' : '' }}>Conciliar</button>
                            </form>
                            @if(! $l->ehCredito() && ! $l->cartao && $cartoes->isNotEmpty())
                                <form method="POST" action="{{ route('app.bancos.lancamentos.fatura', $l) }}" class="d-flex gap-2 mt-2">
                                    @csrf
                                    <select name="cartao_id" class="form-select form-select-sm" required aria-label="Cartão da fatura">
                                        <option value="">É pagamento de fatura do cartão…</option>
                                        @foreach($cartoes as $cc)<option value="{{ $cc->id }}">{{ $cc->nome() }}</option>@endforeach
                                    </select>
                                    <button class="btn btn-sm btn-outline-secondary text-nowrap">Marcar fatura</button>
                                </form>
                            @endif
                            @if(isset($propostas[$l->id]))
                                @php $p = $propostas[$l->id]; @endphp
                                <div class="mt-2 p-2 rounded" style="background: rgba(79,70,229,.06);">
                                    <div class="small">
                                        <strong>{{ $opcoes->isEmpty() ? 'Lançar como despesa' : 'Ou lançar como despesa nova' }}:</strong>
                                        {{ $p['favorecido'] ?: 'sem favorecido' }}
                                        @if($p['plano_nome']) · {{ $p['plano_nome'] }} @endif
                                        @if($p['centro_nome']) · {{ $p['centro_nome'] }} @endif
                                        @if($p['origem'] === 'regra')
                                            <span class="badge bg-success ms-1">Já conhecida{{ $p['usos'] > 1 ? ' · ' . $p['usos'] . ' vezes' : '' }}</span>
                                        @elseif($p['origem'] === 'historico')
                                            <span class="badge bg-warning text-dark ms-1">Igual à última deste fornecedor</span>
                                        @else
                                            <span class="text-muted">· falta classificar</span>
                                        @endif
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        @if($p['origem'] === 'regra')
                                            <form method="POST" action="{{ route('app.bancos.lancamentos.lancar', $l) }}">
                                                @csrf
                                                <input type="hidden" name="usar_proposta" value="1">
                                                <button class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Lançar</button>
                                            </form>
                                        @endif
                                        <button type="button" class="btn btn-sm {{ $p['origem'] === 'regra' ? 'btn-outline-secondary' : 'btn-erp btn-erp-primary' }} btn-lancar"
                                                data-bs-toggle="modal" data-bs-target="#modalLancar"
                                                data-acao="{{ route('app.bancos.lancamentos.lancar', $l) }}"
                                                data-resumo="{{ $l->data->format('d/m/Y') }} · {{ $brl($l->valor) }} · {{ $l->contraparte_nome ?: $l->descricao }}"
                                                data-descricao="{{ $p['descricao'] }}"
                                                data-plano="{{ $p['plano_conta_id'] }}" data-centro="{{ $p['centro_custo_id'] }}">
                                            {{ $p['origem'] === 'regra' ? 'Ajustar' : 'Lançar despesa' }}
                                        </button>
                                    </div>
                                </div>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-2">
                            @if($filtro === 'conferir')
                                <form method="POST" action="{{ route('app.bancos.lancamentos.confirmar', $l) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Confirmar</button>
                                </form>
                                <form method="POST" action="{{ route('app.bancos.lancamentos.recusar', $l) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary">Não é isso</button>
                                </form>
                            @endif
                            @if(in_array($filtro, ['conferir', 'sem']))
                                <form method="POST" action="{{ route('app.bancos.lancamentos.ignorar', $l) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary" title="Não é conta a pagar nem a receber">Ignorar</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('app.bancos.lancamentos.desfazer', $l) }}"
                                      onsubmit="return confirm('{{ $filtro === 'feitos' ? 'Desfazer a conciliação? A conta volta a ficar em aberto.' : 'Voltar este lançamento para a fila?' }}');">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary">{{ $filtro === 'feitos' ? 'Desfazer' : 'Voltar para a fila' }}</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-muted py-4">
                    @if($contas->isEmpty())
                        Ligue uma conta para o extrato começar a chegar.
                    @elseif($filtro === 'conferir')
                        Nada para confirmar agora.
                    @elseif($filtro === 'sem')
                        Nenhum lançamento sem sugestão.
                    @else
                        Nada aqui ainda.
                    @endif
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($lancamentos->hasPages())
        <div class="p-3">{{ $lancamentos->links() }}</div>
    @endif
</div>
</div>

{{-- ---------------- Lançar despesa a partir do extrato ---------------- --}}
@if($filtro === 'sem')
<div class="modal fade" id="modalLancar" tabindex="-1" aria-labelledby="modalLancarTitulo" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content" id="formLancar">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="modalLancarTitulo">Lançar como despesa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="lancarResumo"></p>
                <div class="mb-3">
                    <label class="form-label" for="lancar-descricao">Descrição</label>
                    <input id="lancar-descricao" name="descricao" class="form-control" maxlength="250">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="lancar-plano">Conta do plano de contas</label>
                    <select id="lancar-plano" name="plano_conta_id" class="form-select">
                        <option value="">Sem classificar</option>
                        @foreach($planoContas as $pc)<option value="{{ $pc->id }}">{{ $pc->codigo }} — {{ $pc->nome }}</option>@endforeach
                    </select>
                    @if($planoContas->isEmpty())
                        <div class="form-text">Nenhuma conta de despesa cadastrada. Cadastre em Gestão → Plano de Contas.</div>
                    @endif
                </div>
                <div class="mb-3">
                    <label class="form-label" for="lancar-centro">Centro de custo</label>
                    <select id="lancar-centro" name="centro_custo_id" class="form-select">
                        <option value="">Sem centro de custo</option>
                        @foreach($centrosCusto as $cc)<option value="{{ $cc->id }}">{{ $cc->nome }}</option>@endforeach
                    </select>
                </div>
                <div class="form-check form-switch">
                    <input type="hidden" name="lembrar" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="lancar-lembrar" name="lembrar" value="1" checked>
                    <label class="form-check-label" for="lancar-lembrar">Lembrar esta classificação para os próximos pagamentos a este favorecido</label>
                </div>
                <div class="alert alert-info mt-3 mb-0 small">
                    A despesa entra em Contas a Pagar já como paga, com a data e o valor do banco. Dá para desfazer na aba Conciliados.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-success">Lançar e conciliar</button>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
document.getElementById('modalLancar')?.addEventListener('show.bs.modal', function (e) {
    const b = e.relatedTarget;
    if (!b) return;
    document.getElementById('formLancar').action = b.dataset.acao;
    document.getElementById('lancarResumo').textContent = b.dataset.resumo;
    document.getElementById('lancar-descricao').value = b.dataset.descricao || '';
    document.getElementById('lancar-plano').value = b.dataset.plano || '';
    document.getElementById('lancar-centro').value = b.dataset.centro || '';
});
</script>
@endpush
@endif

{{-- ---------------- Nova conta ---------------- --}}
<div class="modal fade" id="modalNovaConta" tabindex="-1" aria-labelledby="modalNovaContaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('app.bancos.contas.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="modalNovaContaTitulo">Ligar uma conta do banco</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="nc-apelido">Nome para você reconhecer</label>
                        <input id="nc-apelido" name="apelido" class="form-control" maxlength="80" required value="{{ old('apelido') }}" placeholder="Ex.: Itaú da loja centro">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="nc-banco-busca">Banco</label>
                        <div class="banco-busca">
                            <input type="hidden" name="banco_codigo" id="nc-banco" value="{{ old('banco_codigo') }}">
                            <input id="nc-banco-busca" type="text" class="form-control" autocomplete="off" required
                                   placeholder="Digite o número ou o nome do banco"
                                   role="combobox" aria-expanded="false" aria-controls="nc-banco-lista" aria-autocomplete="list"
                                   value="{{ old('banco_codigo') ? old('banco_codigo') . ' — ' . ($bancos[old('banco_codigo')] ?? 'Outro banco') : '' }}">
                            <ul id="nc-banco-lista" class="banco-lista list-unstyled mb-0" role="listbox" hidden>
                                @foreach($bancos as $cod => $nome)
                                    <li role="option" tabindex="-1" data-codigo="{{ $cod }}" data-texto="{{ $cod }} — {{ $nome }}">
                                        <strong>{{ $cod }}</strong> — {{ $nome }}
                                    </li>
                                @endforeach
                                <li class="banco-outro text-muted" hidden>Não está na lista? Digite os 3 números do banco.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="nc-agencia">Agência</label>
                        <input id="nc-agencia" name="agencia" class="form-control" maxlength="10" required inputmode="numeric" value="{{ old('agencia') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="nc-agdig">Dígito</label>
                        <input id="nc-agdig" name="agencia_digito" class="form-control" maxlength="2" value="{{ old('agencia_digito') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="nc-conta">Conta</label>
                        <input id="nc-conta" name="conta" class="form-control" maxlength="12" required inputmode="numeric" value="{{ old('conta') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="nc-ctdig">Dígito</label>
                        <input id="nc-ctdig" name="conta_digito" class="form-control" maxlength="2" value="{{ old('conta_digito') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="nc-doc">CPF ou CNPJ do dono da conta</label>
                        <input id="nc-doc" name="titular_documento" class="form-control" required maxlength="18" inputmode="numeric" value="{{ old('titular_documento', $empresa->cnpj) }}">
                        <div class="form-text">É quem vai autorizar no banco.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="nc-nome">Nome do dono da conta</label>
                        <input id="nc-nome" name="titular_nome" class="form-control" maxlength="120" value="{{ old('titular_nome', $empresa->razao_social) }}">
                    </div>
                    @if($unidades->count() > 1)
                    <div class="col-md-6">
                        <label class="form-label" for="nc-loja">Loja</label>
                        <select id="nc-loja" name="unidade_id" class="form-select">
                            <option value="">Todas as lojas</option>
                            @foreach($unidades as $u)<option value="{{ $u->id }}" {{ (string) old('unidade_id') === (string) $u->id ? 'selected' : '' }}>{{ $u->nome }}</option>@endforeach
                        </select>
                    </div>
                    @endif
                </div>
                <div class="alert alert-info mt-3 mb-0 small">
                    Depois de salvar, clique em <strong>Conectar ao banco</strong>: abre a página do seu banco para você autorizar. O extrato começa a chegar em até 24 horas.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-erp btn-erp-primary">Salvar e continuar</button>
            </div>
        </form>
    </div>
</div>

@push('styles')
<style>
    .banco-busca { position: relative; }
    .banco-lista { position: absolute; z-index: 1060; left: 0; right: 0; top: calc(100% + 4px); max-height: 260px; overflow-y: auto;
        background: var(--bs-body-bg, #fff); border: 1px solid var(--bs-border-color, #dee2e6); border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.12); }
    .banco-lista li { padding: 8px 12px; cursor: pointer; }
    .banco-lista li[role="option"]:hover, .banco-lista li.ativo { background: rgba(79, 70, 229, .10); }
    .banco-lista li.banco-outro { cursor: default; font-size: .875rem; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const busca = document.getElementById('nc-banco-busca');
    const codigo = document.getElementById('nc-banco');
    const lista = document.getElementById('nc-banco-lista');
    if (!busca || !codigo || !lista) return;
    const itens = Array.from(lista.querySelectorAll('li[role="option"]'));
    const outro = lista.querySelector('.banco-outro');
    const limpar = t => t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    let ativo = -1;

    function visiveis() { return itens.filter(li => !li.hidden); }
    function marcar(i) {
        const v = visiveis();
        v.forEach(li => li.classList.remove('ativo'));
        ativo = v.length ? (i + v.length) % v.length : -1;
        if (ativo >= 0) { v[ativo].classList.add('ativo'); v[ativo].scrollIntoView({ block: 'nearest' }); }
    }
    function abrir() { lista.hidden = false; busca.setAttribute('aria-expanded', 'true'); }
    function fechar() { lista.hidden = true; busca.setAttribute('aria-expanded', 'false'); }
    function escolher(li) { codigo.value = li.dataset.codigo; busca.value = li.dataset.texto; busca.setCustomValidity(''); fechar(); }
    function filtrar() {
        const termo = limpar(busca.value.trim());
        let achou = 0;
        itens.forEach(li => { li.hidden = termo !== '' && !limpar(li.dataset.texto).includes(termo); if (!li.hidden) achou++; });
        outro.hidden = achou > 0;
        // banco fora da lista: vale o número de 3 dígitos digitado
        codigo.value = /^\d{3}$/.test(busca.value.trim()) ? busca.value.trim() : '';
        const exato = itens.find(li => li.dataset.codigo === busca.value.trim());
        if (exato) codigo.value = exato.dataset.codigo;
        marcar(0);
        abrir();
    }

    busca.addEventListener('focus', () => { itens.forEach(li => li.hidden = false); outro.hidden = true; abrir(); });
    busca.addEventListener('input', filtrar);
    busca.addEventListener('keydown', e => {
        if (e.key === 'ArrowDown') { e.preventDefault(); abrir(); marcar(ativo + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); marcar(ativo - 1); }
        else if (e.key === 'Enter' && !lista.hidden && ativo >= 0 && visiveis()[ativo]) { e.preventDefault(); escolher(visiveis()[ativo]); }
        else if (e.key === 'Escape') { fechar(); }
    });
    itens.forEach(li => li.addEventListener('mousedown', e => { e.preventDefault(); escolher(li); }));
    busca.addEventListener('blur', () => setTimeout(fechar, 120));
    busca.form.addEventListener('submit', e => {
        if (!/^\d{3}$/.test(codigo.value)) {
            e.preventDefault();
            busca.setCustomValidity('Escolha o banco na lista ou digite os 3 números dele.');
            busca.reportValidity();
        }
    });
    busca.addEventListener('input', () => busca.setCustomValidity(''));
})();
</script>
@endpush

@if($errors->any())
@push('scripts')
<script>document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('modalNovaConta')).show());</script>
@endpush
@endif
@endsection
