@extends('layouts.app')

@section('title', 'Financeiro da Plataforma')

@section('content')
<x-erp.page-header title="Financeiro da Plataforma" icon="cash-coin">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalGerarFatura">
        <i class="bi bi-plus-lg me-1"></i> Gerar fatura
    </button>
</x-erp.page-header>

{{-- Cards de resumo --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="calendar-event" color="primary"
            :value="'R$ ' . number_format($aReceberMes, 2, ',', '.')" label="A receber no mês" />
    </div>
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="exclamation-triangle" color="danger"
            :value="'R$ ' . number_format($emAtraso, 2, ',', '.')" label="Em atraso" />
    </div>
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="check-circle" color="success"
            :value="'R$ ' . number_format($recebidoMes, 2, ',', '.')" label="Recebido no mês" />
    </div>
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="arrow-repeat" color="info"
            :value="'R$ ' . number_format($mrr, 2, ',', '.')" label="Receita recorrente/mês" />
    </div>
</div>

{{-- Empresas suspensas --}}
@if($suspensas->isNotEmpty())
    <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-lock-fill fs-4"></i>
        <div>
            <strong>{{ $suspensas->count() }} empresa(s) com acesso suspenso:</strong>
            @foreach($suspensas as $s)
                {{ $s->nome_fantasia ?: $s->razao_social }}
                <form class="d-inline" method="POST" action="{{ route('admin.financeiro.empresas.reativar', $s) }}"
                      data-confirm="Reativar o acesso da {{ $s->razao_social }} sem baixar a fatura?">
                    @csrf
                    <button class="btn btn-sm btn-outline-light ms-1">Reativar</button>
                </form>{{ !$loop->last ? ' · ' : '' }}
            @endforeach
        </div>
    </div>
@endif

{{-- Contratos ativos --}}
<x-erp.card title="Contratos de cobrança direta" icon="file-earmark-text">
    @if($contratos->isEmpty())
        <p class="text-muted mb-0">Nenhuma empresa com cobrança direta configurada.
            Configure no cadastro da empresa (card "Cobrança direta").</p>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Periodicidade</th>
                        <th class="text-end">Valor</th>
                        <th>Vencimento</th>
                        <th>Geração</th>
                        <th>Bloqueio</th>
                        <th>Situação</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($contratos as $c)
                        <tr>
                            <td>
                                <a href="{{ route('admin.empresas.edit', $c) }}">{{ $c->nome_fantasia ?: $c->razao_social }}</a>
                            </td>
                            <td>{{ ucfirst($c->cobranca_periodicidade) }}</td>
                            <td class="text-end">R$ {{ number_format($c->cobranca_valor, 2, ',', '.') }}</td>
                            <td>
                                @if($c->cobranca_periodicidade === 'mensal')
                                    dia {{ $c->cobranca_dia_vencimento }}
                                @else
                                    {{ $c->cobranca_proxima_renovacao?->format('d/m/Y') ?? '—' }}
                                @endif
                            </td>
                            <td>{{ $c->cobranca_geracao === 'automatica' ? 'Automática' : 'Manual' }}</td>
                            <td>
                                @if($c->cobranca_bloqueio_automatico)
                                    <span class="badge bg-warning text-dark">auto ({{ $c->cobranca_tolerancia_dias }}d)</span>
                                @else
                                    <span class="badge bg-secondary">desligado</span>
                                @endif
                            </td>
                            <td>
                                @if($c->estaSuspensa())
                                    <span class="badge bg-danger">SUSPENSA</span>
                                @else
                                    <span class="badge bg-success">ativa</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($c->estaSuspensa())
                                    <form class="d-inline" method="POST" action="{{ route('admin.financeiro.empresas.reativar', $c) }}"
                                          data-confirm="Reativar o acesso da {{ $c->razao_social }}?">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success">Reativar</button>
                                    </form>
                                @else
                                    <form class="d-inline" method="POST" action="{{ route('admin.financeiro.empresas.suspender', $c) }}"
                                          data-confirm="Suspender TODO o acesso da {{ $c->razao_social }} agora?">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger">Suspender</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-erp.card>

{{-- Faturas --}}
<x-erp.card title="Faturas" icon="receipt" class="mt-4">
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">Todos os status</option>
                <option value="pendente" {{ request('status') === 'pendente' ? 'selected' : '' }}>Pendentes</option>
                <option value="atrasada" {{ request('status') === 'atrasada' ? 'selected' : '' }}>Em atraso</option>
                <option value="paga" {{ request('status') === 'paga' ? 'selected' : '' }}>Pagas</option>
                <option value="cancelada" {{ request('status') === 'cancelada' ? 'selected' : '' }}>Canceladas</option>
            </select>
        </div>
        <div class="col-md-4">
            <select name="empresa_id" class="form-select" onchange="this.form.submit()">
                <option value="">Todas as empresas</option>
                @foreach($empresas as $e)
                    <option value="{{ $e->id }}" {{ (int) request('empresa_id') === $e->id ? 'selected' : '' }}>
                        {{ $e->nome_fantasia ?: $e->razao_social }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>

    @if($faturas->isEmpty())
        <x-erp.empty-state title="Nenhuma fatura" icon="receipt"
            description="Gere a primeira fatura pelo botão acima ou configure a geração automática no cadastro da empresa." />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Competência</th>
                        <th>Descrição</th>
                        <th class="text-end">Valor</th>
                        <th>Vencimento</th>
                        <th>Status</th>
                        <th>Pagamento</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($faturas as $fatura)
                        @php $badge = $fatura->statusBadge(); @endphp
                        <tr>
                            <td>{{ $fatura->empresa->nome_fantasia ?: $fatura->empresa->razao_social }}</td>
                            <td>{{ $fatura->competencia }}</td>
                            <td class="text-muted small">{{ $fatura->descricao ?: '—' }}</td>
                            <td class="text-end"><strong>R$ {{ number_format($fatura->valor, 2, ',', '.') }}</strong></td>
                            <td>{{ $fatura->vencimento->format('d/m/Y') }}</td>
                            <td><span class="badge bg-{{ $badge['cor'] }}">{{ $badge['label'] }}</span></td>
                            <td class="small text-muted">
                                @if($fatura->status === 'paga')
                                    {{ $fatura->pago_em?->format('d/m/Y') }}
                                    {{ $fatura->forma_pagamento ? '· ' . $fatura->forma_pagamento : '' }}
                                    {{ $fatura->marcadaPor ? '· por ' . $fatura->marcadaPor->name : '' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if($fatura->isPendente())
                                    <button class="btn btn-sm btn-success btn-marcar-paga"
                                            data-fatura="{{ $fatura->id }}"
                                            data-label="{{ $fatura->empresa->razao_social }} — {{ $fatura->competencia }} (R$ {{ number_format($fatura->valor, 2, ',', '.') }})"
                                            data-bs-toggle="modal" data-bs-target="#modalMarcarPaga">
                                        <i class="bi bi-check-lg"></i> Marcar paga
                                    </button>
                                    <form class="d-inline" method="POST" action="{{ route('admin.financeiro.faturas.cancelar', $fatura) }}"
                                          data-confirm="Cancelar esta fatura?">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $faturas->links() }}</div>
    @endif
</x-erp.card>

{{-- Modal: gerar fatura manual --}}
<div class="modal fade" id="modalGerarFatura" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('admin.financeiro.faturas.store') }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Gerar fatura</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Empresa</label>
                    <select name="empresa_id" class="form-select" required id="gf_empresa">
                        <option value="">Selecione...</option>
                        @foreach($empresas as $e)
                            <option value="{{ $e->id }}" data-valor="{{ $e->cobranca_valor }}">
                                {{ $e->nome_fantasia ?: $e->razao_social }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label">Valor (R$)</label>
                        <input type="number" step="0.01" min="0.01" name="valor" class="form-control" required id="gf_valor">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Vencimento</label>
                        <input type="date" name="vencimento" class="form-control" required>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label">Descrição <span class="text-muted">(opcional)</span></label>
                    <input type="text" name="descricao" class="form-control" maxlength="255"
                           placeholder="Ex.: Mensalidade agosto/2026, setup, personalização...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="submit" class="btn btn-primary">Gerar fatura</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal: marcar paga --}}
<div class="modal fade" id="modalMarcarPaga" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" id="formMarcarPaga" action="">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-check-circle me-2 text-success"></i>Confirmar pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted" id="mp_label"></p>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label">Pago em</label>
                        <input type="date" name="pago_em" class="form-control" value="{{ now()->format('Y-m-d') }}">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Forma</label>
                        <select name="forma_pagamento" class="form-select">
                            <option value="pix">PIX</option>
                            <option value="transferencia">Transferência</option>
                            <option value="dinheiro">Dinheiro</option>
                            <option value="boleto">Boleto</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label">Observação <span class="text-muted">(opcional)</span></label>
                    <input type="text" name="observacao" class="form-control" maxlength="500">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="submit" class="btn btn-success">Confirmar pagamento</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Modal "marcar paga": aponta o form para a fatura clicada
    document.querySelectorAll('.btn-marcar-paga').forEach(btn => {
        btn.addEventListener('click', () => {
            const form = document.getElementById('formMarcarPaga');
            form.action = '{{ url('admin/financeiro/faturas') }}/' + btn.dataset.fatura + '/pagar';
            document.getElementById('mp_label').textContent = btn.dataset.label;
        });
    });

    // Modal "gerar fatura": pré-preenche o valor do contrato da empresa
    document.getElementById('gf_empresa')?.addEventListener('change', (e) => {
        const valor = e.target.selectedOptions[0]?.dataset.valor;
        if (valor) document.getElementById('gf_valor').value = valor;
    });
</script>
@endpush
