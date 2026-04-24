@extends('layouts.app')

@section('title', 'OS #' . $ordemServico->numero)

@section('content')
@php
    $statusColors = [
        'aberta' => 'warning',
        'em_andamento' => 'primary',
        'aguardando_peca' => 'info',
        'concluida' => 'success',
        'entregue' => 'dark',
        'cancelada' => 'danger',
    ];
    $statusLabels = [
        'aberta' => 'Aberta',
        'em_andamento' => 'Em Andamento',
        'aguardando_peca' => 'Aguardando Peca',
        'concluida' => 'Concluida',
        'entregue' => 'Entregue',
        'cancelada' => 'Cancelada',
    ];
    $statusIcons = [
        'aberta' => 'bi-folder2-open',
        'em_andamento' => 'bi-gear',
        'aguardando_peca' => 'bi-clock-history',
        'concluida' => 'bi-check-circle',
        'entregue' => 'bi-box-arrow-up-right',
        'cancelada' => 'bi-x-circle',
    ];
    $statusSteps = ['aberta', 'em_andamento', 'concluida', 'entregue'];
    $currentStepIndex = array_search($ordemServico->status, $statusSteps);
    // aguardando_peca is between em_andamento and concluida
    if ($ordemServico->status === 'aguardando_peca') $currentStepIndex = 1;
    if ($ordemServico->status === 'cancelada') $currentStepIndex = -1;
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-wrench-adjustable me-2"></i>OS #{{ $ordemServico->numero }}
        </h4>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-{{ $statusColors[$ordemServico->status] ?? 'secondary' }} fs-6">
                <i class="bi {{ $statusIcons[$ordemServico->status] ?? 'bi-circle' }} me-1"></i>
                {{ $statusLabels[$ordemServico->status] ?? $ordemServico->status }}
            </span>
            <span class="text-muted small">Aberta em {{ $ordemServico->created_at->format('d/m/Y H:i') }}</span>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap justify-content-end">
        @if(!in_array($ordemServico->status, ['entregue', 'cancelada']))
            <a href="{{ route('app.ordens-servico.edit', $ordemServico) }}" class="btn btn-outline-warning btn-sm">
                <i class="bi bi-pencil me-1"></i> Editar
            </a>
        @endif
        <a href="{{ route('app.ordens-servico.show', $ordemServico) }}?print=1" class="btn btn-outline-secondary btn-sm"
           target="_blank">
            <i class="bi bi-printer me-1"></i> Imprimir
        </a>
        <a href="{{ route('app.ordens-servico.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

{{-- Status Timeline --}}
@if($ordemServico->status !== 'cancelada')
<div class="card shadow-sm mb-4">
    <div class="card-body py-4">
        <div class="d-flex justify-content-between align-items-start position-relative px-4">
            {{-- Background line --}}
            <div class="position-absolute" style="top: 22px; left: 60px; right: 60px; height: 3px; background: #e2e8f0; z-index: 0;"></div>
            {{-- Progress line --}}
            @if($currentStepIndex !== false && $currentStepIndex >= 0)
                @php $progressWidth = $currentStepIndex / (count($statusSteps) - 1) * 100; @endphp
                <div class="position-absolute" style="top: 22px; left: 60px; width: calc((100% - 120px) * {{ $progressWidth / 100 }}); height: 3px; background: #198754; z-index: 0; transition: width 0.5s;"></div>
            @endif

            @foreach($statusSteps as $index => $step)
                @php
                    $isCompleted = $currentStepIndex !== false && $index <= $currentStepIndex;
                    $isCurrent = ($ordemServico->status === $step) || ($ordemServico->status === 'aguardando_peca' && $step === 'em_andamento');
                @endphp
                <div class="text-center position-relative" style="z-index: 1; min-width: 90px;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto
                        {{ $isCompleted ? 'bg-success text-white' : 'bg-white border border-2' }}"
                        style="width: 44px; height: 44px; {{ $isCurrent ? 'box-shadow: 0 0 0 4px rgba(25,135,84,0.2);' : '' }}">
                        @if($isCompleted && !$isCurrent)
                            <i class="bi bi-check-lg"></i>
                        @else
                            <i class="bi {{ $statusIcons[$step] }} {{ $isCompleted ? '' : 'text-muted' }}"></i>
                        @endif
                    </div>
                    <div class="mt-2">
                        <small class="{{ $isCurrent ? 'fw-bold text-success' : ($isCompleted ? 'fw-semibold' : 'text-muted') }}">
                            {{ $statusLabels[$step] }}
                        </small>
                    </div>
                    @if($isCurrent && $ordemServico->status === 'aguardando_peca')
                        <span class="badge bg-info mt-1" style="font-size: 0.65rem;">Aguardando Peca</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
@else
<div class="alert alert-danger d-flex align-items-center shadow-sm mb-4">
    <i class="bi bi-x-circle fs-4 me-3"></i>
    <div>
        <strong>Ordem de Servico Cancelada</strong>
        <span class="d-block small">Esta OS foi cancelada e nao pode mais ser alterada.</span>
    </div>
</div>
@endif

<div class="row g-4">
    {{-- Left Column --}}
    <div class="col-lg-8">
        {{-- OS Details --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-info-circle me-1"></i> Detalhes da OS
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="text-muted small d-block">Cliente</label>
                        <p class="fw-semibold mb-0">{{ $ordemServico->cliente->nome_razao_social ?? '-' }}</p>
                        @if($ordemServico->cliente?->cpf_cnpj)
                            <small class="text-muted">{{ $ordemServico->cliente->cpf_cnpj }}</small>
                        @endif
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small d-block">Vendedor</label>
                        <p class="fw-semibold mb-0">{{ $ordemServico->vendedor->name ?? '-' }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small d-block">Tecnico</label>
                        <p class="fw-semibold mb-0">{{ $ordemServico->tecnico->name ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small d-block">Equipamento</label>
                        <p class="fw-semibold mb-0">{{ $ordemServico->equipamento }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small d-block">Data Abertura</label>
                        <p class="mb-0">{{ $ordemServico->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small d-block">Unidade</label>
                        <p class="mb-0">{{ $ordemServico->unidade->nome ?? '-' }}</p>
                    </div>
                    <div class="col-12">
                        <label class="text-muted small d-block">Defeito Relatado</label>
                        <div class="bg-light rounded-3 p-3">{{ $ordemServico->defeito_relatado }}</div>
                    </div>
                    @if($ordemServico->observacoes)
                        <div class="col-12">
                            <label class="text-muted small d-block">Observacoes</label>
                            <div class="bg-light rounded-3 p-3">{{ $ordemServico->observacoes }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Items --}}
        @php
            $itensProdutos = $ordemServico->itens->where('tipo', 'produto');
            $itensServicos = $ordemServico->itens->where('tipo', 'servico');
        @endphp

        @if($itensProdutos->count() > 0)
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-box me-1"></i> Produtos</span>
                <span class="badge bg-primary bg-opacity-10 text-primary">{{ $itensProdutos->count() }} item(ns)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Descricao</th>
                            <th class="text-center" width="80">Qtd</th>
                            <th class="text-end" width="120">Preco Unit.</th>
                            <th class="text-end" width="120">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($itensProdutos as $item)
                            <tr>
                                <td>{{ $item->descricao }}</td>
                                <td class="text-center">{{ number_format($item->quantidade, 2, ',', '.') }}</td>
                                <td class="text-end">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                                <td class="text-end fw-semibold">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="3" class="text-end fw-semibold">Subtotal Produtos:</td>
                            <td class="text-end fw-bold">R$ {{ number_format($ordemServico->valor_produtos, 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif

        @if($itensServicos->count() > 0)
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-tools me-1"></i> Servicos</span>
                <span class="badge bg-success bg-opacity-10 text-success">{{ $itensServicos->count() }} item(ns)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Descricao</th>
                            <th class="text-center" width="80">Qtd</th>
                            <th class="text-end" width="120">Preco Unit.</th>
                            <th class="text-end" width="120">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($itensServicos as $item)
                            <tr>
                                <td>{{ $item->descricao }}</td>
                                <td class="text-center">{{ number_format($item->quantidade, 2, ',', '.') }}</td>
                                <td class="text-end">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                                <td class="text-end fw-semibold">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="3" class="text-end fw-semibold">Subtotal Servicos:</td>
                            <td class="text-end fw-bold">R$ {{ number_format($ordemServico->valor_servicos, 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif

        {{-- Laudo Tecnico --}}
        @if($ordemServico->laudo_tecnico)
        <div class="card shadow-sm mb-4 border-success border-opacity-50">
            <div class="card-header bg-success bg-opacity-10 fw-semibold">
                <i class="bi bi-clipboard-check me-1 text-success"></i> Laudo Tecnico
            </div>
            <div class="card-body">
                <p class="mb-0">{{ $ordemServico->laudo_tecnico }}</p>
            </div>
        </div>
        @endif
    </div>

    {{-- Right Column --}}
    <div class="col-lg-4">
        {{-- Totals --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-calculator me-1"></i> Resumo Financeiro
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Produtos:</span>
                    <strong>R$ {{ number_format($ordemServico->valor_produtos, 2, ',', '.') }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Servicos:</span>
                    <strong>R$ {{ number_format($ordemServico->valor_servicos, 2, ',', '.') }}</strong>
                </div>
                @if($ordemServico->desconto > 0)
                <div class="d-flex justify-content-between mb-2 text-danger">
                    <span>Desconto:</span>
                    <strong>- R$ {{ number_format($ordemServico->desconto, 2, ',', '.') }}</strong>
                </div>
                @endif
                <hr class="my-2">
                <div class="d-flex justify-content-between">
                    <strong class="fs-5">Total:</strong>
                    <strong class="fs-4 text-success">R$ {{ number_format($ordemServico->total, 2, ',', '.') }}</strong>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-lightning me-1"></i> Acoes
            </div>
            <div class="card-body d-grid gap-2">
                @if(!in_array($ordemServico->status, ['entregue', 'cancelada']))
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalStatus">
                        <i class="bi bi-arrow-right-circle me-1"></i> Alterar Status
                    </button>
                @endif

                @if(in_array($ordemServico->status, ['concluida', 'entregue']))
                    <form method="POST" action="{{ route('app.ordens-servico.converter-venda', $ordemServico) }}"
                          data-confirm="Converter esta OS em venda?">
                        @csrf
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-receipt me-1"></i> Converter em Venda
                        </button>
                    </form>
                @endif

                @if(!in_array($ordemServico->status, ['entregue', 'cancelada']))
                    <hr class="my-1">
                    <form method="POST" action="{{ route('app.ordens-servico.destroy', $ordemServico) }}"
                          data-confirm="Tem certeza que deseja excluir esta OS? Esta acao e irreversivel.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                            <i class="bi bi-trash me-1"></i> Excluir OS
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Status Change Modal --}}
@if(!in_array($ordemServico->status, ['entregue', 'cancelada']))
<div class="modal fade" id="modalStatus" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('app.ordens-servico.update-status', $ordemServico) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-right-circle me-1"></i> Alterar Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status Atual</label>
                        <div>
                            <span class="badge bg-{{ $statusColors[$ordemServico->status] ?? 'secondary' }} fs-6">
                                {{ $statusLabels[$ordemServico->status] ?? $ordemServico->status }}
                            </span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Novo Status</label>
                        <select name="status" class="form-select" id="selectNovoStatus" onchange="toggleLaudo()">
                            @php
                                $transicoes = [
                                    'aberta' => ['em_andamento' => 'Em Andamento', 'cancelada' => 'Cancelada'],
                                    'em_andamento' => ['aguardando_peca' => 'Aguardando Peca', 'concluida' => 'Concluida', 'cancelada' => 'Cancelada'],
                                    'aguardando_peca' => ['em_andamento' => 'Em Andamento', 'concluida' => 'Concluida', 'cancelada' => 'Cancelada'],
                                    'concluida' => ['entregue' => 'Entregue', 'cancelada' => 'Cancelada'],
                                ];
                            @endphp
                            @foreach($transicoes[$ordemServico->status] ?? [] as $valor => $label)
                                <option value="{{ $valor }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3" id="laudoField" style="display: none;">
                        <label class="form-label fw-semibold">Laudo Tecnico</label>
                        <textarea name="laudo_tecnico" class="form-control" rows="4"
                                  placeholder="Descreva o diagnostico e o servico realizado...">{{ $ordemServico->laudo_tecnico }}</textarea>
                        <small class="text-muted">Obrigatorio ao concluir a OS.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Confirmar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
    function toggleLaudo() {
        const select = document.getElementById('selectNovoStatus');
        const laudoField = document.getElementById('laudoField');
        if (select && laudoField) {
            laudoField.style.display = select.value === 'concluida' ? 'block' : 'none';
        }
    }
    // Initialize on load
    document.addEventListener('DOMContentLoaded', toggleLaudo);
</script>
@endpush
