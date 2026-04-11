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
    $statusSteps = ['aberta', 'em_andamento', 'aguardando_peca', 'concluida', 'entregue'];
    $currentStepIndex = array_search($ordemServico->status, $statusSteps);
    if ($ordemServico->status === 'cancelada') $currentStepIndex = -1;
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <i class="bi bi-wrench-adjustable me-2"></i>OS #{{ $ordemServico->numero }}
        <span class="badge bg-{{ $statusColors[$ordemServico->status] ?? 'secondary' }} ms-2">
            {{ $statusLabels[$ordemServico->status] ?? $ordemServico->status }}
        </span>
    </h4>
    <div class="d-flex gap-2">
        @if(!in_array($ordemServico->status, ['entregue', 'cancelada']))
            <a href="{{ route('app.ordens-servico.edit', $ordemServico) }}" class="btn btn-outline-warning">
                <i class="bi bi-pencil me-1"></i> Editar
            </a>
        @endif
        <a href="{{ route('app.ordens-servico.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

{{-- Status Timeline --}}
@if($ordemServico->status !== 'cancelada')
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center position-relative">
            <div class="position-absolute w-100" style="top: 50%; height: 3px; background: #e2e8f0; z-index: 0;"></div>
            @foreach($statusSteps as $index => $step)
                @php
                    $isCompleted = $currentStepIndex !== false && $index <= $currentStepIndex;
                    $isCurrent = $index === $currentStepIndex;
                @endphp
                <div class="text-center position-relative" style="z-index: 1;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1
                        {{ $isCompleted ? 'bg-success text-white' : 'bg-light border' }}"
                        style="width: 36px; height: 36px; {{ $isCurrent ? 'box-shadow: 0 0 0 3px rgba(25,135,84,0.3);' : '' }}">
                        @if($isCompleted)
                            <i class="bi bi-check-lg"></i>
                        @else
                            <i class="bi bi-circle text-muted"></i>
                        @endif
                    </div>
                    <small class="{{ $isCurrent ? 'fw-bold text-success' : 'text-muted' }}">
                        {{ $statusLabels[$step] }}
                    </small>
                </div>
            @endforeach
        </div>
    </div>
</div>
@else
<div class="alert alert-danger mb-4">
    <i class="bi bi-x-circle me-1"></i> Esta Ordem de Servico foi <strong>cancelada</strong>.
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
                        <label class="text-muted small">Cliente</label>
                        <p class="fw-semibold mb-0">{{ $ordemServico->cliente->nome_razao_social ?? '-' }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Data Abertura</label>
                        <p class="fw-semibold mb-0">{{ $ordemServico->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Unidade</label>
                        <p class="fw-semibold mb-0">{{ $ordemServico->unidade->nome ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Equipamento</label>
                        <p class="fw-semibold mb-0">{{ $ordemServico->equipamento }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Vendedor</label>
                        <p class="fw-semibold mb-0">{{ $ordemServico->vendedor->name ?? '-' }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Tecnico</label>
                        <p class="fw-semibold mb-0">{{ $ordemServico->tecnico->name ?? '-' }}</p>
                    </div>
                    <div class="col-md-12">
                        <label class="text-muted small">Defeito Relatado</label>
                        <p class="mb-0">{{ $ordemServico->defeito_relatado }}</p>
                    </div>
                    @if($ordemServico->observacoes)
                        <div class="col-md-12">
                            <label class="text-muted small">Observacoes</label>
                            <p class="mb-0">{{ $ordemServico->observacoes }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Items - Produtos --}}
        @php
            $itensProdutos = $ordemServico->itens->where('tipo', 'produto');
            $itensServicos = $ordemServico->itens->where('tipo', 'servico');
        @endphp

        @if($itensProdutos->count() > 0)
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-box me-1"></i> Produtos
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Descricao</th>
                                <th class="text-center">Qtd</th>
                                <th class="text-end">Preco Unit.</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($itensProdutos as $item)
                                <tr>
                                    <td>{{ $item->descricao }}</td>
                                    <td class="text-center">{{ number_format($item->quantidade, 2, ',', '.') }}</td>
                                    <td class="text-end">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                                    <td class="text-end">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- Items - Servicos --}}
        @if($itensServicos->count() > 0)
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-tools me-1"></i> Servicos
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Descricao</th>
                                <th class="text-center">Qtd</th>
                                <th class="text-end">Preco Unit.</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($itensServicos as $item)
                                <tr>
                                    <td>{{ $item->descricao }}</td>
                                    <td class="text-center">{{ number_format($item->quantidade, 2, ',', '.') }}</td>
                                    <td class="text-end">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                                    <td class="text-end">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- Laudo Tecnico --}}
        @if($ordemServico->laudo_tecnico)
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-clipboard-check me-1"></i> Laudo Tecnico
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
                <i class="bi bi-calculator me-1"></i> Totais
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Produtos:</span>
                    <strong>R$ {{ number_format($ordemServico->valor_produtos, 2, ',', '.') }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Servicos:</span>
                    <strong>R$ {{ number_format($ordemServico->valor_servicos, 2, ',', '.') }}</strong>
                </div>
                @if($ordemServico->desconto > 0)
                <div class="d-flex justify-content-between mb-2 text-danger">
                    <span>Desconto:</span>
                    <strong>- R$ {{ number_format($ordemServico->desconto, 2, ',', '.') }}</strong>
                </div>
                @endif
                <hr>
                <div class="d-flex justify-content-between">
                    <strong class="fs-5">Total:</strong>
                    <strong class="fs-5 text-success">R$ {{ number_format($ordemServico->total, 2, ',', '.') }}</strong>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-lightning me-1"></i> Acoes
            </div>
            <div class="card-body d-grid gap-2">
                {{-- Status Change --}}
                @if(!in_array($ordemServico->status, ['entregue', 'cancelada']))
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalStatus">
                        <i class="bi bi-arrow-right-circle me-1"></i> Alterar Status
                    </button>
                @endif

                {{-- Convert to Venda --}}
                @if(in_array($ordemServico->status, ['concluida', 'entregue']))
                    <form method="POST" action="{{ route('app.ordens-servico.converter-venda', $ordemServico) }}"
                          onsubmit="return confirm('Converter esta OS em venda?')">
                        @csrf
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-receipt me-1"></i> Converter em Venda
                        </button>
                    </form>
                @endif

                {{-- Print --}}
                <a href="{{ route('app.ordens-servico.show', $ordemServico) }}?print=1" class="btn btn-outline-secondary"
                   target="_blank" onclick="window.open('{{ route('app.ordens-servico.show', $ordemServico) }}?print=1', '_blank'); return false;">
                    <i class="bi bi-printer me-1"></i> Imprimir
                </a>

                {{-- Delete --}}
                @if(!in_array($ordemServico->status, ['entregue', 'cancelada']))
                    <form method="POST" action="{{ route('app.ordens-servico.destroy', $ordemServico) }}"
                          onsubmit="return confirm('Tem certeza que deseja excluir esta OS?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger w-100">
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
                    <h5 class="modal-title">Alterar Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Novo Status</label>
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
                        <label class="form-label">Laudo Tecnico</label>
                        <textarea name="laudo_tecnico" class="form-control" rows="4"
                                  placeholder="Descreva o diagnostico e servico realizado...">{{ $ordemServico->laudo_tecnico }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Confirmar</button>
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
</script>
@endpush
