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
        'nobanco' => ['Pendentes no banco', $totais['nobanco']],
        'feitos' => ['Conciliados', $totais['feitos']],
        'ignorados' => ['Ignorados', $totais['ignorados']],
    ];
@endphp
<div class="fade-in">
<x-erp.page-header title="Open Finance" icon="bank"
    :subtitle="'Contas ligadas ao banco e conciliação · ' . $usadas . ' de ' . $limite . ' ' . ($limite === 1 ? 'conta usada' : 'contas usadas')">
    @if($usadas < $limite)
        <button type="button" class="btn btn-erp btn-erp-primary" data-bs-toggle="modal" data-bs-target="#modalNovaConta">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Ligar uma conta
        </button>
    @else
        <span class="text-muted small align-self-center">Todas as contas do plano estão em uso. Para ligar mais, fale com a IA365.</span>
    @endif
</x-erp.page-header>

{{-- ---------------- Painel: uma faixa só, não quatro cartões iguais ---------------- --}}
@if($contas->isNotEmpty())
@php $pctMes = $painel['total_mes'] > 0 ? (int) round($painel['feitos_mes'] / $painel['total_mes'] * 100) : 0; @endphp
<section class="erp-card of-painel mb-4" aria-label="Resumo">
    <div class="of-painel-item">
        <div class="of-rotulo">Saldo nas contas</div>
        <div class="of-valor">{{ $painel['saldo'] === null ? '—' : $brl($painel['saldo']) }}</div>
        <div class="of-apoio">
            @if($painel['saldo'] === null)
                O banco ainda não informou.
            @else
                Informado pelo banco em {{ $painel['saldo_em']?->format('d/m H:i') }}
                @if($painel['saldo_aplicado'] > 0)<br>{{ $brl($painel['saldo_aplicado']) }} em aplicação automática @endif
            @endif
        </div>
    </div>
    <a class="of-painel-item of-painel-link" href="{{ route('app.bancos.index', ['ver' => 'conferir']) }}">
        <div class="of-rotulo">Para confirmar</div>
        <div class="of-valor">{{ $painel['confirmar_qtd'] }}</div>
        <div class="of-apoio">{{ $painel['confirmar_qtd'] ? $brl($painel['confirmar_valor']) . ' já com sugestão' : 'Nada esperando você' }}</div>
    </a>
    <a class="of-painel-item of-painel-link" href="{{ route('app.bancos.index', ['ver' => 'sem']) }}">
        <div class="of-rotulo">Sem sugestão</div>
        <div class="of-valor">{{ $painel['sem_qtd'] }}</div>
        <div class="of-apoio">{{ $painel['sem_qtd'] ? $brl($painel['sem_valor']) . ' para classificar' : 'Tudo classificado' }}</div>
    </a>
    <div class="of-painel-item">
        <div class="of-rotulo">{{ ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'][now()->month - 1] }} na conta</div>
        <div class="of-mes"><span class="of-entrada">+ {{ $brl($painel['entradas_mes']) }}</span> entraram</div>
        <div class="of-mes"><span class="of-saida">− {{ $brl($painel['saidas_mes']) }}</span> saíram</div>
        <div class="progress mt-2" style="height: 6px;" role="progressbar" aria-label="Conciliado no mês" aria-valuenow="{{ $pctMes }}" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar bg-success" style="width: {{ $pctMes }}%"></div>
        </div>
        <div class="of-apoio mt-1">{{ $painel['feitos_mes'] }} de {{ $painel['total_mes'] }} conciliados ({{ $pctMes }}%)</div>
    </div>
</section>
@endif

{{-- ---------------- Contas ---------------- --}}
<section class="erp-card mb-4" aria-label="Contas ligadas">
    @forelse($contas as $c)
    @php $doConta = $cartoes->where('conta_bancaria_id', $c->id); $ligados = $doConta->where('importar', true)->count(); @endphp
    <div class="of-conta">
        <div class="of-conta-quem">
            <div class="of-conta-nome">{{ $c->apelido }}</div>
            <div class="of-apoio">{{ $c->descricaoConta() }}</div>
            <div class="of-apoio">{{ $c->titular_nome ?: 'Dono' }} · {{ $c->titularFormatado() }}@if($c->unidade) · {{ $c->unidade->nome }}@endif</div>
        </div>
        <div class="of-conta-estado">
            @if($c->situacao === 'conectada')
                <span class="badge-status ativa">Conectada</span>
            @else
                <span class="badge-status pendente">Falta autorizar no banco</span>
            @endif
            <div class="of-apoio mt-1">
                @if($c->protocolo_pendente)
                    Banco preparando o extrato…
                @elseif($c->ultima_sincronizacao_em)
                    Último extrato {{ $c->ultima_sincronizacao_em->format('d/m H:i') }}
                @elseif($c->situacao === 'conectada')
                    Extrato ainda não buscado
                @endif
            </div>
            @if($c->saldo !== null)
                <div class="of-apoio">Saldo <strong class="text-body">{{ $brl($c->saldo) }}</strong> em {{ $c->saldo_atualizado_em?->format('d/m H:i') }}</div>
            @endif
            @if($c->ultimo_erro)
                <div class="of-erro mt-1"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $c->ultimo_erro }}</div>
            @endif
        </div>
        <div class="of-conta-acoes">
            @if($c->situacao !== 'conectada' && $c->link_conexao)
                <a href="{{ $c->link_conexao }}" target="_blank" rel="noopener" class="btn btn-sm btn-erp btn-erp-primary">
                    <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i> Conectar ao banco
                </a>
            @endif
            <form method="POST" action="{{ route('app.bancos.contas.atualizar', $c) }}">
                @csrf
                <button class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i> {{ $c->situacao === 'conectada' ? 'Buscar extrato' : 'Já autorizei' }}
                </button>
            </form>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Mais ações da conta {{ $c->apelido }}">
                    <i class="bi bi-three-dots" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @if($c->situacao === 'conectada')
                    <li>
                        <form method="POST" action="{{ route('app.bancos.contas.cartoes', $c) }}">
                            @csrf
                            <button class="dropdown-item"><i class="bi bi-credit-card me-2" aria-hidden="true"></i>Buscar cartões desta conta</button>
                        </form>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    @endif
                    <li>
                        <form method="POST" action="{{ route('app.bancos.contas.desconectar', $c) }}"
                              data-confirm="Desconectar esta conta do banco? O que já foi conciliado continua como está.">
                            @csrf
                            <button class="dropdown-item text-danger"><i class="bi bi-plug me-2" aria-hidden="true"></i>Desconectar do banco</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    @if($doConta->isNotEmpty())
    <details class="of-cartoes" @if($cartaoId && $doConta->contains('id', $cartaoId)) open @endif>
        <summary>
            <i class="bi bi-credit-card me-1" aria-hidden="true"></i>
            {{ $doConta->count() }} {{ $doConta->count() === 1 ? 'cartão de crédito' : 'cartões de crédito' }}
            <span class="of-apoio">· {{ $ligados ? $ligados . ' trazendo as compras' : 'nenhum trazendo as compras' }} · não contam como conta extra</span>
        </summary>
        <ul class="of-cartoes-lista">
            @foreach($doConta as $cc)
            <li>
                <div>
                    <strong>{{ $cc->nome() }}</strong>
                    @if($cc->adicionais)<span class="of-apoio"> · adicionais {{ str_replace(',', ', ', $cc->adicionais) }}</span>@endif
                    <div class="of-apoio">
                        @if($cc->importar)
                            {{ $cc->protocolo_pendente ? 'Banco preparando as compras…' : ($cc->ultima_sincronizacao_em ? 'Compras buscadas em ' . $cc->ultima_sincronizacao_em->format('d/m H:i') : 'Compras entram no próximo extrato') }}
                        @else
                            Desligado
                        @endif
                        @if($cc->limite !== null && (float) $cc->limite > 0) · limite disponível {{ $brl($cc->limite_disponivel) }} de {{ $brl($cc->limite) }} @endif
                    </div>
                    @if($cc->ultimo_erro)<div class="of-erro">{{ $cc->ultimo_erro }}</div>@endif
                </div>
                <form method="POST" action="{{ route('app.bancos.cartoes.alternar', $cc) }}">
                    @csrf
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="cartao-{{ $cc->id }}"
                               {{ $cc->importar ? 'checked' : '' }} onchange="this.form.submit()">
                        <label class="form-check-label" for="cartao-{{ $cc->id }}">Trazer as compras</label>
                    </div>
                </form>
            </li>
            @endforeach
        </ul>
    </details>
    @endif
    @empty
    <x-erp.empty-state title="Nenhuma conta ligada ainda" icon="bank"
        description="Ligue a conta do banco e o extrato começa a chegar aqui sozinho, já cruzado com as suas contas a receber e a pagar." />
    @endforelse
</section>

{{-- ---------------- Conciliação ---------------- --}}
<div class="of-barra">
    <ul class="nav nav-pills of-abas">
        @foreach($abas as $chave => [$rotulo, $qtd])
            @continue($chave === 'nobanco' && ! $qtd && $filtro !== 'nobanco')
            <li class="nav-item">
                <a class="nav-link {{ $filtro === $chave ? 'active' : '' }}" @if($filtro === $chave) aria-current="page" @endif
                   href="{{ route('app.bancos.index', array_filter(['ver' => $chave, 'conta' => $contaId, 'cartao' => $cartaoId, 'lado' => $lado])) }}">
                    {{ $rotulo }} <span class="of-conta-num">{{ $qtd }}</span>
                </a>
            </li>
        @endforeach
    </ul>
    <div class="of-filtros">
        <div class="btn-group btn-group-sm" role="group" aria-label="Entradas ou saídas">
            @foreach(['' => ['Tudo', $porLado['tudo']], 'entradas' => ['Entradas', $porLado['entradas']], 'saidas' => ['Saídas', $porLado['saidas']]] as $valor => [$rotulo, $qtd])
                <a class="btn {{ (string) $lado === (string) $valor ? 'btn-secondary' : 'btn-outline-secondary' }}"
                   @if((string) $lado === (string) $valor) aria-current="true" @endif
                   href="{{ route('app.bancos.index', array_filter(['ver' => $filtro, 'conta' => $contaId, 'cartao' => $cartaoId, 'lado' => $valor])) }}">
                    {{ $rotulo }} <span class="of-conta-num">{{ $qtd }}</span>
                </a>
            @endforeach
        </div>
        @if($contas->count() > 1 || $cartoes->isNotEmpty())
            <select class="form-select form-select-sm" style="width: auto;" aria-label="Conta ou cartão" onchange="window.location = this.value">
                <option value="{{ route('app.bancos.index', array_filter(['ver' => $filtro, 'lado' => $lado])) }}">Todas as contas e cartões</option>
                @foreach($contas as $c)
                    <option value="{{ route('app.bancos.index', array_filter(['ver' => $filtro, 'conta' => $c->id, 'lado' => $lado])) }}" {{ $contaId === $c->id ? 'selected' : '' }}>{{ $c->apelido }}</option>
                @endforeach
                @foreach($cartoes as $cc)
                    <option value="{{ route('app.bancos.index', array_filter(['ver' => $filtro, 'cartao' => $cc->id, 'lado' => $lado])) }}" {{ $cartaoId === $cc->id ? 'selected' : '' }}>{{ $cc->nome() }}</option>
                @endforeach
            </select>
        @endif
        @if($filtro === 'sem' && $conhecidos > 0)
            <form method="POST" action="{{ route('app.bancos.lancar-conhecidos') }}"
                  data-confirm="Lançar {{ $conhecidos }} despesa(s) com a classificação que o sistema já aprendeu? Cada uma entra como paga, com a data e o valor do banco.">
                @csrf
                <input type="hidden" name="conta" value="{{ $contaId }}">
                <button class="btn btn-sm btn-success">
                    <i class="bi bi-lightning-charge me-1" aria-hidden="true"></i> Lançar as {{ $conhecidos }} já conhecidas
                </button>
            </form>
        @endif
        @if($filtro === 'conferir' && $totais['certos'] > 0)
            <form method="POST" action="{{ route('app.bancos.confirmar-certos') }}"
                  data-confirm="Confirmar os {{ $totais['certos'] }} lançamentos que o sistema tem como certos? As contas serão baixadas com a data e o valor do banco.">
                @csrf
                <input type="hidden" name="conta" value="{{ $contaId }}">
                <input type="hidden" name="lado" value="{{ $lado }}">
                <button class="btn btn-sm btn-success">
                    <i class="bi bi-check2-all me-1" aria-hidden="true"></i> Confirmar os {{ $totais['certos'] }} certos
                </button>
            </form>
        @endif
    </div>
</div>

<section class="erp-card" aria-label="Lançamentos do banco">
    <ul class="of-fila">
        @forelse($lancamentos as $l)
        @php
            $titulo = $filtro === 'feitos'
                ? ($l->ehCredito() ? $l->contaReceber : $l->contaPagar)
                : ($l->ehCredito() ? $l->sugestaoContaReceber : $l->sugestaoContaPagar);
            $fatura = $filtro === 'feitos' ? $l->faturaCartao : ($filtro === 'conferir' ? $l->sugestaoFaturaCartao : null);
            $p = $propostas[$l->id] ?? null;
            $temAbertas = ($l->ehCredito() ? $abertasReceber : $abertasPagar)->isNotEmpty();
            $resumo = $l->data->format('d/m/Y') . ' · ' . $brl($l->valor) . ' · ' . ($l->contraparte_nome ?: $l->descricao);
        @endphp
        <li class="of-linha">
            <div class="of-data">{{ $l->data->format('d/m') }}<span class="of-ano">/{{ $l->data->format('Y') }}</span></div>

            <div class="of-banco">
                <div class="of-descricao" data-quando="{{ $l->data->format('d/m') }}">{{ $l->descricao ?: 'Sem descrição' }}</div>
                <div class="of-apoio">
                    @if($l->operacao){{ $l->operacao }} · @endif
                    @if($l->contraparte_nome && ! preg_match('/^[\d.\-\/ ]+$/', $l->contraparte_nome)){{ $l->contraparte_nome }} · @endif
                    @if($l->contraparte_documento){{ $l->contraparte_documento }} · @endif
                    {{ $l->cartao ? $l->cartao->nome() : $l->contaBancaria?->apelido }}
                    @if($l->parcela_total > 1) · parcela {{ $l->parcela_numero }}/{{ $l->parcela_total }} @endif
                </div>
                @if($l->sumiu_no_banco)
                    <div class="of-erro"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>O banco deixou de mostrar este lançamento. Confira se a baixa ainda vale.</div>
                @endif
            </div>

            <div class="of-valor-linha {{ $l->ehCredito() ? 'of-entrada' : 'of-saida' }}">{{ $l->ehCredito() ? '+' : '−' }} {{ $brl($l->valor) }}</div>

            <div class="of-destino">
                @if($fatura)
                    <div><strong>Pagamento da fatura</strong> — {{ $fatura->nome() }}</div>
                    <div class="of-apoio">Não entra como despesa: as compras do cartão já entram uma a uma.</div>
                    @if($filtro === 'conferir')
                        <div class="of-apoio mt-1"><span class="badge-status ativa">Certo</span> {{ $l->sugestao_motivo }}</div>
                    @endif
                @elseif($filtro === 'nobanco')
                    <div><span class="badge-status pendente">Pendente no banco</span></div>
                    <div class="of-apoio">Fatura aberta: o banco ainda não efetivou esta compra. Quando efetivar, ela vai para "Sem sugestão" para lançar.</div>
                @elseif($filtro === 'ignorados')
                    <span class="of-apoio">{{ $l->sugestao_motivo ?: 'Ignorado por você' }}</span>
                @elseif($titulo)
                    <div><strong>{{ $quem($titulo) }}</strong> — {{ $titulo->descricao }}</div>
                    <div class="of-apoio">
                        {{ $l->ehCredito() ? 'A receber' : 'A pagar' }} · {{ $brl($titulo->valor) }} · vence {{ $titulo->vencimento?->format('d/m/Y') }}
                        @if(! $l->ehCredito() && $titulo->centroCusto) · {{ $titulo->centroCusto->nome }} @endif
                    </div>
                    @if($filtro === 'conferir')
                        <div class="of-apoio mt-1">
                            <span class="badge-status {{ $l->confiancaAlta() ? 'ativa' : 'pendente' }}">{{ $l->confiancaAlta() ? 'Certo' : 'Conferir' }}</span>
                            {{ $l->sugestao_motivo }}
                        </div>
                    @endif
                @elseif($filtro === 'sem' && $p)
                    <div>{{ $p['favorecido'] ?: 'Sem favorecido' }}</div>
                    <div class="of-apoio">
                        @if($p['origem'] === 'regra')
                            <span class="badge-status ativa">Já conhecida</span>
                            {{ collect([$p['plano_nome'], $p['centro_nome']])->filter()->implode(' · ') ?: 'sem classificação' }}@if($p['usos'] > 1) · usada {{ $p['usos'] }} vezes @endif
                        @elseif($p['origem'] === 'historico')
                            <span class="badge-status pendente">Igual à última</span>
                            {{ collect([$p['plano_nome'], $p['centro_nome']])->filter()->implode(' · ') }}
                        @else
                            Despesa nova · falta classificar
                        @endif
                    </div>
                @elseif($filtro === 'sem')
                    <span class="of-apoio">{{ $l->cartao ? 'Pagamento ou estorno no cartão' : 'Entrada sem conta a receber correspondente' }}</span>
                @else
                    <span class="of-apoio">—</span>
                @endif
            </div>

            <div class="of-acoes">
                @if($filtro === 'conferir')
                    <form method="POST" action="{{ route('app.bancos.lancamentos.confirmar', $l) }}">
                        @csrf
                        <button class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Confirmar</button>
                    </form>
                    <form method="POST" action="{{ route('app.bancos.lancamentos.recusar', $l) }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary">Não é isso</button>
                    </form>
                @elseif($filtro === 'sem')
                    @if($p && $p['origem'] === 'regra')
                        <form method="POST" action="{{ route('app.bancos.lancamentos.lancar', $l) }}">
                            @csrf
                            <input type="hidden" name="usar_proposta" value="1">
                            <button class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Lançar</button>
                        </form>
                    @endif
                    @if($p)
                        <button type="button" class="btn btn-sm {{ $p['origem'] === 'regra' ? 'btn-outline-secondary' : 'btn-outline-primary' }} btn-lancar"
                                data-bs-toggle="modal" data-bs-target="#modalLancar"
                                data-acao="{{ route('app.bancos.lancamentos.lancar', $l) }}"
                                data-resumo="{{ $resumo }}"
                                data-descricao="{{ $p['descricao'] }}"
                                data-plano="{{ $p['plano_conta_id'] }}" data-centro="{{ $p['centro_custo_id'] }}"
                                data-favorecido="{{ $p['favorecido'] }}" data-fornecedor="{{ $p['fornecedor_id'] }}" data-documento="{{ $p['documento'] }}">
                            {{ $p['origem'] === 'regra' ? 'Ajustar' : 'Lançar despesa' }}
                        </button>
                    @elseif(! $l->cartao)
                        <button type="button" class="btn btn-sm btn-outline-primary" {{ $temAbertas ? '' : 'disabled' }}
                                data-bs-toggle="modal" data-bs-target="#modalEscolher"
                                data-acao="{{ route('app.bancos.lancamentos.confirmar', $l) }}" data-resumo="{{ $resumo }}" data-lado="receber">
                            Ligar a uma conta a receber
                        </button>
                    @endif
                @endif

                @if($filtro !== 'nobanco')
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Mais ações deste lançamento">
                        <i class="bi bi-three-dots" aria-hidden="true"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @if($filtro === 'sem' && $p)
                            <li>
                                <button type="button" class="dropdown-item" {{ $temAbertas ? '' : 'disabled' }}
                                        data-bs-toggle="modal" data-bs-target="#modalEscolher"
                                        data-acao="{{ route('app.bancos.lancamentos.confirmar', $l) }}" data-resumo="{{ $resumo }}" data-lado="pagar">
                                    <i class="bi bi-link-45deg me-2" aria-hidden="true"></i>Ligar a uma conta a pagar já cadastrada
                                </button>
                            </li>
                            @if(! $l->cartao && $cartoes->isNotEmpty())
                                <li><h6 class="dropdown-header">É pagamento de fatura do</h6></li>
                                @foreach($cartoes as $cc)
                                <li>
                                    <form method="POST" action="{{ route('app.bancos.lancamentos.fatura', $l) }}">
                                        @csrf
                                        <input type="hidden" name="cartao_id" value="{{ $cc->id }}">
                                        <button class="dropdown-item"><i class="bi bi-credit-card me-2" aria-hidden="true"></i>{{ $cc->nome() }}</button>
                                    </form>
                                </li>
                                @endforeach
                            @endif
                            <li><hr class="dropdown-divider"></li>
                        @endif
                        @if(in_array($filtro, ['conferir', 'sem']))
                            <li>
                                <form method="POST" action="{{ route('app.bancos.lancamentos.ignorar', $l) }}">
                                    @csrf
                                    <button class="dropdown-item"><i class="bi bi-eye-slash me-2" aria-hidden="true"></i>Ignorar (não é conta a pagar nem a receber)</button>
                                </form>
                            </li>
                        @else
                            <li>
                                <form method="POST" action="{{ route('app.bancos.lancamentos.desfazer', $l) }}"
                                      data-confirm="{{ $filtro === 'feitos' ? 'Desfazer a conciliação? A conta volta a ficar em aberto.' : 'Voltar este lançamento para a fila?' }}">
                                    @csrf
                                    <button class="dropdown-item"><i class="bi bi-arrow-counterclockwise me-2" aria-hidden="true"></i>{{ $filtro === 'feitos' ? 'Desfazer a conciliação' : 'Voltar para a fila' }}</button>
                                </form>
                            </li>
                        @endif
                    </ul>
                </div>
                @endif
            </div>
        </li>
        @empty
        <li>
            <x-erp.empty-state icon="check2-circle"
                :title="$contas->isEmpty() ? 'O extrato aparece aqui' : ['conferir' => 'Nada para confirmar agora', 'sem' => 'Nenhum lançamento sem sugestão', 'nobanco' => 'Nenhuma compra pendente no banco', 'feitos' => 'Nada conciliado ainda', 'ignorados' => 'Nada ignorado'][$filtro]"
                :description="$contas->isEmpty() ? 'Ligue uma conta para o extrato começar a chegar.' : ($lado ? 'Não há ' . ($lado === 'entradas' ? 'entradas' : 'saídas') . ' nesta aba. Veja em Tudo.' : 'Quando o banco mandar lançamento novo, ele aparece aqui.')" />
        </li>
        @endforelse
    </ul>
    @if($lancamentos->hasPages())
        <div class="p-3 border-top">{{ $lancamentos->links() }}</div>
    @endif
</section>
</div>

{{-- ---------------- Ligar a uma conta já cadastrada ---------------- --}}
@if($filtro === 'sem')
<div class="modal fade" id="modalEscolher" tabindex="-1" aria-labelledby="modalEscolherTitulo" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content" id="formEscolher">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="modalEscolherTitulo">Ligar a uma conta já cadastrada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="escolherResumo"></p>
                <label class="form-label" for="escolher-titulo">Qual conta é este lançamento?</label>
                <select id="escolher-titulo" name="titulo_id" class="form-select" required>
                    <option value="">Escolha…</option>
                    <optgroup label="Contas a receber em aberto" data-lado="receber">
                        @foreach($abertasReceber as $o)
                            <option value="{{ $o->id }}">{{ $o->vencimento?->format('d/m') }} · {{ $brl($o->valor) }} · {{ \Illuminate\Support\Str::limit($quem($o) . ' — ' . $o->descricao, 70) }}</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="Contas a pagar em aberto" data-lado="pagar">
                        @foreach($abertasPagar as $o)
                            <option value="{{ $o->id }}">{{ $o->vencimento?->format('d/m') }} · {{ $brl($o->valor) }} · {{ \Illuminate\Support\Str::limit($quem($o) . ' — ' . $o->descricao, 70) }}</option>
                        @endforeach
                    </optgroup>
                </select>
                <div class="form-text">A conta é baixada com a data e o valor do banco. Dá para desfazer na aba Conciliados.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-success">Conciliar</button>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
document.getElementById('modalEscolher')?.addEventListener('show.bs.modal', function (e) {
    const b = e.relatedTarget;
    if (!b) return;
    document.getElementById('formEscolher').action = b.dataset.acao;
    document.getElementById('escolherResumo').textContent = b.dataset.resumo;
    const sel = document.getElementById('escolher-titulo');
    sel.value = '';
    // só o lado do lançamento: entrada liga em conta a receber, saída em conta a pagar
    sel.querySelectorAll('optgroup').forEach(g => { g.hidden = g.dataset.lado !== b.dataset.lado; g.disabled = g.hidden; });
});
</script>
@endpush
@endif

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
                    <div class="form-label mb-1">Favorecido</div>
                    <div id="lancar-favorecido-fixo" class="small"></div>
                    {{-- 22/11/2026: escolher um fornecedor já cadastrado (busca por nome ou CPF/CNPJ) --}}
                    <div class="position-relative mt-2">
                        <label class="form-label small mb-1" for="lancar-fornecedor-busca">Fornecedor</label>
                        <div class="input-group">
                            <input id="lancar-fornecedor-busca" class="form-control" autocomplete="off" maxlength="250"
                                   placeholder="Buscar fornecedor já cadastrado (nome ou CPF/CNPJ)"
                                   data-autocomplete="{{ route('app.search.fornecedores') }}" data-autocomplete-target="lancar-fornecedor-id"
                                   data-autocomplete-display="razao_social" data-autocomplete-focus>
                            <button type="button" class="btn btn-outline-secondary" id="lancar-fornecedor-limpar" hidden
                                    aria-label="Tirar o fornecedor escolhido"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                        </div>
                        <input type="hidden" name="fornecedor_id" id="lancar-fornecedor-id">
                        <div class="form-text" id="lancar-fornecedor-escolhido" hidden></div>
                    </div>
                    <div id="lancar-fornecedor-bloco" class="mt-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="lancar-fornecedor-chave">
                            <label class="form-check-label" for="lancar-fornecedor-chave">Não está na lista: cadastrar como fornecedor agora</label>
                        </div>
                        <input id="lancar-fornecedor-nome" name="fornecedor_novo" class="form-control mt-2" maxlength="250" hidden disabled
                               aria-label="Nome do fornecedor">
                        <div id="lancar-fornecedor-ajuda" class="form-text" hidden></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="lancar-plano">Conta do plano de contas</label>
                    <select id="lancar-plano" name="plano_conta_id" class="form-select" data-novo="#lancar-plano-novo">
                        <option value="">Sem classificar</option>
                        @foreach($planoContas as $pc)<option value="{{ $pc->id }}">{{ $pc->codigo }} — {{ $pc->nome }}</option>@endforeach
                        <option value="__novo">＋ Cadastrar uma conta nova agora…</option>
                    </select>
                    <input id="lancar-plano-novo" name="plano_conta_novo" class="form-control mt-2" maxlength="100" hidden disabled
                           placeholder="Nome da conta nova (ex.: Aluguel, Combustível)" aria-label="Nome da conta nova do plano de contas">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="lancar-centro">Centro de custo</label>
                    <select id="lancar-centro" name="centro_custo_id" class="form-select" data-novo="#lancar-centro-novo">
                        <option value="">Sem centro de custo</option>
                        @foreach($centrosCusto as $cc)<option value="{{ $cc->id }}">{{ $cc->nome }}</option>@endforeach
                        <option value="__novo">＋ Cadastrar um centro de custo novo agora…</option>
                    </select>
                    <input id="lancar-centro-novo" name="centro_custo_novo" class="form-control mt-2" maxlength="100" hidden disabled
                           placeholder="Nome do centro de custo novo (ex.: Loja Centro, Administrativo)" aria-label="Nome do centro de custo novo">
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
    document.querySelectorAll('#modalLancar select[data-novo]').forEach(alternarNovo);

    // Favorecido: já é fornecedor cadastrado, ou pode virar um agora só com nome e CPF/CNPJ.
    const jaEh = !!b.dataset.fornecedor;
    const chave = document.getElementById('lancar-fornecedor-chave');
    const nome = document.getElementById('lancar-fornecedor-nome');
    document.getElementById('lancar-favorecido-fixo').textContent = 'No banco: ' + (b.dataset.favorecido || 'sem nome informado');
    // já reconhecido: vem escolhido, mas dá para trocar
    escolherFornecedor(jaEh ? b.dataset.fornecedor : '', jaEh ? (b.dataset.favorecido || '') : '');
    chave.checked = false;
    nome.value = b.dataset.favorecido || '';
    document.getElementById('lancar-fornecedor-ajuda').textContent = b.dataset.documento
        ? 'Entra com o CPF/CNPJ que o banco informou (' + b.dataset.documento + '). Endereço e telefone você completa depois em Cadastros → Fornecedores, se precisar.'
        : 'O banco não informou CPF/CNPJ: entra só com o nome. O resto você completa depois em Cadastros → Fornecedores.';
    alternarFornecedor();
});

// Fornecedor escolhido na busca: esconde "cadastrar novo"; sem escolha, volta a mostrar.
function escolherFornecedor(id, nome) {
    const campo = document.getElementById('lancar-fornecedor-id');
    const busca = document.getElementById('lancar-fornecedor-busca');
    campo.value = id || '';
    busca.value = id ? nome : '';
    busca.readOnly = !!id;
    document.getElementById('lancar-fornecedor-limpar').hidden = !id;
    const aviso = document.getElementById('lancar-fornecedor-escolhido');
    aviso.hidden = !id;
    aviso.textContent = id ? 'A despesa fica no nome deste fornecedor.' : '';
    document.getElementById('lancar-fornecedor-bloco').hidden = !!id;
    if (id) { document.getElementById('lancar-fornecedor-chave').checked = false; alternarFornecedor(); }
}
document.getElementById('lancar-fornecedor-busca')?.addEventListener('autocomplete-select', function () {
    escolherFornecedor(document.getElementById('lancar-fornecedor-id').value, this.value);
});
document.getElementById('lancar-fornecedor-busca')?.addEventListener('input', function () {
    document.getElementById('lancar-fornecedor-id').value = '';
});
document.getElementById('lancar-fornecedor-limpar')?.addEventListener('click', function () {
    escolherFornecedor('', '');
    document.getElementById('lancar-fornecedor-busca').focus();
});

function alternarFornecedor() {
    const ligado = document.getElementById('lancar-fornecedor-chave').checked;
    const nome = document.getElementById('lancar-fornecedor-nome');
    nome.hidden = !ligado;
    nome.disabled = !ligado;
    nome.required = ligado;
    document.getElementById('lancar-fornecedor-ajuda').hidden = !ligado;
    if (ligado) nome.focus();
}
document.getElementById('lancar-fornecedor-chave')?.addEventListener('change', alternarFornecedor);

// "Cadastrar … novo agora": mostra o campo do nome e manda só ele (o select deixa de ir no envio).
function alternarNovo(sel) {
    const campo = document.querySelector(sel.dataset.novo);
    const novo = sel.value === '__novo';
    campo.hidden = !novo;
    campo.disabled = !novo;
    campo.required = novo;
    if (!sel.dataset.nome) sel.dataset.nome = sel.name;
    sel.name = novo ? '' : sel.dataset.nome;
    if (novo) campo.focus(); else campo.value = '';
}
document.querySelectorAll('#modalLancar select[data-novo]').forEach(sel => sel.addEventListener('change', () => alternarNovo(sel)));

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
    /* Open Finance — só o que o erp.css ainda não tem. Tudo com as variáveis do sistema. */
    .of-rotulo { font-size: .8rem; font-weight: 600; letter-spacing: .03em; color: var(--text-secondary); }
    .of-apoio { font-size: .8125rem; color: var(--text-muted); }
    .of-erro { font-size: .8125rem; color: var(--danger-text); }
    .of-entrada { color: var(--success-text); font-weight: 600; }
    .of-saida { color: var(--danger-text); font-weight: 600; }
    .of-conta-num { font-variant-numeric: tabular-nums; font-weight: 600; margin-left: .25rem; opacity: .8; }

    /* Painel: uma faixa dividida, não quatro cartões soltos */
    .of-painel { display: grid; grid-template-columns: 1.3fr 1fr 1fr 1.3fr; }
    .of-painel-item { padding: 1rem 1.25rem; border-left: 1px solid var(--border); color: inherit; text-decoration: none; min-width: 0; }
    .of-painel-item:first-child { border-left: 0; }
    .of-painel-link { transition: background-color .15s ease-out; }
    .of-painel-link:hover { background: var(--bg-table-head); color: inherit; }
    .of-painel .of-valor { font-size: 1.5rem; font-weight: 800; line-height: 1.2; font-variant-numeric: tabular-nums; margin: .15rem 0; }
    .of-mes { font-size: .875rem; font-variant-numeric: tabular-nums; margin-top: .15rem; }

    /* Contas */
    .of-conta { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1.2fr) auto; gap: 1rem; align-items: center; padding: 1rem 1.25rem; border-top: 1px solid var(--border); }
    .of-conta:first-child { border-top: 0; }
    .of-conta-nome { font-weight: 600; }
    .of-conta-acoes { display: flex; flex-wrap: wrap; gap: .5rem; justify-content: flex-end; }
    .of-cartoes { border-top: 1px solid var(--border-light); background: var(--bg-table-head); }
    .of-cartoes > summary { padding: .6rem 1.25rem; cursor: pointer; font-size: .875rem; font-weight: 600; list-style-position: inside; }
    .of-cartoes > summary .of-apoio { font-weight: 400; }
    .of-cartoes-lista { list-style: none; margin: 0; padding: 0 1.25rem .5rem; }
    .of-cartoes-lista li { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .6rem 0; border-top: 1px solid var(--border); font-size: .875rem; }

    /* Barra de abas e filtros */
    .of-barra { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: .75rem; }
    .of-filtros { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .of-abas .nav-link { padding: .4rem .85rem; font-size: .875rem; font-weight: 600; color: var(--text-secondary); }
    .of-abas .nav-link.active { color: var(--text-on-dark, #fff); }

    /* Fila: uma linha = data · o que veio do banco · valor · para onde vai · ações */
    .of-fila { list-style: none; margin: 0; padding: 0; }
    .of-linha { display: grid; grid-template-columns: 4.5rem minmax(0, 1.5fr) 8.5rem minmax(0, 1.3fr) auto; gap: 1rem; align-items: center; padding: .85rem 1.25rem; border-top: 1px solid var(--border-light); font-size: .875rem; }
    .of-linha:first-child { border-top: 0; }
    .of-linha:hover { background: var(--bg-table-head); }
    .of-data { font-variant-numeric: tabular-nums; color: var(--text-secondary); white-space: nowrap; }
    .of-ano { color: var(--text-muted); }
    .of-descricao { font-weight: 500; color: var(--text-primary); overflow-wrap: anywhere; }
    .of-valor-linha { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .of-destino { min-width: 0; overflow-wrap: anywhere; }
    .of-acoes { display: flex; gap: .5rem; align-items: center; justify-content: flex-end; flex-wrap: nowrap; }
    .of-fila .empty-state, .erp-card > .empty-state { padding: 2.5rem 1.25rem; }

    /* Lista de bancos com busca (janela Ligar uma conta) */
    .banco-busca { position: relative; }
    .banco-lista { position: absolute; z-index: 1060; left: 0; right: 0; top: calc(100% + 4px); max-height: 260px; overflow-y: auto;
        background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: 0 8px 24px rgba(15, 23, 42, .12); }
    .banco-lista li { padding: .5rem .75rem; cursor: pointer; }
    .banco-lista li[role="option"]:hover, .banco-lista li.ativo { background: var(--primary-light); }
    .banco-lista li.banco-outro { cursor: default; font-size: .875rem; }

    @media (max-width: 991.98px) {
        .of-painel { grid-template-columns: 1fr 1fr; }
        .of-painel-item:nth-child(odd) { border-left: 0; }
        .of-painel-item:nth-child(n+3) { border-top: 1px solid var(--border); }
        .of-conta { grid-template-columns: 1fr; gap: .6rem; }
        .of-conta-acoes { justify-content: flex-start; }
        /* Fila no celular: cada lançamento vira um bloco, com o valor no alto à direita */
        .of-linha { grid-template-columns: 1fr auto; grid-template-areas: "banco valor" "destino destino" "acoes acoes"; gap: .5rem .75rem; padding: 1rem; }
        .of-data { display: none; }
        .of-banco { grid-area: banco; }
        .of-banco .of-descricao::before { content: attr(data-quando) ' · '; color: var(--text-muted); font-weight: 400; }
        .of-valor-linha { grid-area: valor; align-self: start; }
        .of-destino { grid-area: destino; }
        .of-acoes { grid-area: acoes; justify-content: flex-start; flex-wrap: wrap; }
        .of-acoes .btn, .of-conta-acoes .btn, .of-filtros .btn { min-height: 44px; display: inline-flex; align-items: center; }
        .of-barra { align-items: stretch; }
        .of-abas { flex-wrap: nowrap; overflow-x: auto; }
        .of-abas .nav-link { white-space: nowrap; min-height: 44px; display: flex; align-items: center; }
    }
    @media (max-width: 575.98px) {
        .of-painel { grid-template-columns: 1fr; }
        .of-painel-item { border-left: 0; border-top: 1px solid var(--border); }
        .of-painel-item:first-child { border-top: 0; }
        .of-cartoes-lista li { flex-direction: column; align-items: flex-start; gap: .4rem; }
    }
    @media (prefers-reduced-motion: reduce) { .of-painel-link { transition: none; } }
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
