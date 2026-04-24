@extends('layouts.app')

@section('title', 'Nota Fiscal #' . ($notaFiscal->numero ?? $notaFiscal->id))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-file-earmark-text me-2"></i>
            {{ strtoupper($notaFiscal->tipo->value) }} #{{ $notaFiscal->numero ?? 'Pendente' }}
        </h4>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-{{ $notaFiscal->status->color() }} fs-6">{{ $notaFiscal->status->label() }}</span>
            @if($notaFiscal->ambiente)
                <span class="badge bg-{{ $notaFiscal->ambiente === 'producao' ? 'danger' : 'warning text-dark' }}">
                    {{ ucfirst($notaFiscal->ambiente) }}
                </span>
            @endif
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap justify-content-end">
        @if($notaFiscal->status === \App\Enums\StatusNotaFiscal::Pendente)
            <button type="button" class="btn btn-info btn-sm" id="btn-consultar">
                <i class="bi bi-arrow-repeat me-1"></i> Consultar Status
            </button>
        @endif
        @if($notaFiscal->xml_url)
            <a href="{{ route('app.notas-fiscais.xml', $notaFiscal) }}" class="btn btn-success btn-sm" target="_blank">
                <i class="bi bi-file-code me-1"></i> XML
            </a>
        @endif
        @if($notaFiscal->danfe_url || $notaFiscal->pdf_url)
            <a href="{{ route('app.notas-fiscais.danfe', $notaFiscal) }}" class="btn btn-danger btn-sm" target="_blank">
                <i class="bi bi-file-pdf me-1"></i> DANFE / PDF
            </a>
        @endif
        @if($notaFiscal->status === \App\Enums\StatusNotaFiscal::Autorizada)
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalCancelar">
                <i class="bi bi-x-circle me-1"></i> Cancelar
            </button>
            @if($notaFiscal->tipo === \App\Enums\TipoNotaFiscal::NFe)
                <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalCartaCorrecao">
                    <i class="bi bi-pencil-square me-1"></i> CC-e
                </button>
            @endif
        @endif
        <a href="{{ route('app.notas-fiscais.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

{{-- Status Timeline --}}
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-clock-history me-1"></i> Timeline do Documento
    </div>
    <div class="card-body py-4">
        @php
            $currentValue = $notaFiscal->status->value;
            $isCancelled = $currentValue === 'cancelada';
            $isRejected = $currentValue === 'rejeitada';
            $isInutilizada = $currentValue === 'inutilizada';
            $isContingencia = $currentValue === 'contingencia';
            $isAutorizada = in_array($currentValue, ['autorizada', 'cancelada']);

            $steps = [
                ['key' => 'pendente', 'label' => 'Pendente', 'icon' => 'bi-hourglass-split'],
                ['key' => 'autorizada', 'label' => 'Autorizada', 'icon' => 'bi-check-circle'],
            ];

            if ($isCancelled) {
                $steps[] = ['key' => 'cancelada', 'label' => 'Cancelada', 'icon' => 'bi-x-circle'];
            }
        @endphp

        <div class="d-flex align-items-start justify-content-center gap-2 flex-wrap">
            @foreach($steps as $i => $step)
                @php
                    $isActive = false;
                    $isDone = false;
                    $color = 'secondary';

                    if ($step['key'] === 'pendente') {
                        $isDone = true;
                        $color = 'success';
                    } elseif ($step['key'] === 'autorizada') {
                        if ($isAutorizada) {
                            $isDone = true;
                            $color = 'success';
                        }
                    } elseif ($step['key'] === 'cancelada') {
                        $isDone = true;
                        $color = 'danger';
                    }

                    if ($step['key'] === $currentValue) {
                        $isActive = true;
                    }
                @endphp

                @if($i > 0)
                    <div class="d-flex align-items-center" style="height: 44px;">
                        <div style="width: 50px; height: 3px;" class="bg-{{ $isDone ? $color : 'light' }} rounded"></div>
                    </div>
                @endif

                <div class="text-center" style="min-width: 80px;">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center
                        {{ $isDone ? "bg-{$color} text-white" : 'bg-light border text-muted' }}"
                        style="width: 44px; height: 44px; {{ $isActive ? "box-shadow: 0 0 0 4px rgba(var(--bs-{$color}-rgb), 0.25);" : '' }}">
                        <i class="bi {{ $step['icon'] }}"></i>
                    </div>
                    <div class="small mt-1 {{ $isActive ? "fw-bold text-{$color}" : 'text-muted' }}">
                        {{ $step['label'] }}
                    </div>
                    @if($step['key'] === 'pendente' && $notaFiscal->created_at)
                        <div class="text-muted" style="font-size: 0.7rem;">{{ $notaFiscal->created_at->format('d/m H:i') }}</div>
                    @elseif($step['key'] === 'autorizada' && $notaFiscal->emitida_em && $isAutorizada)
                        <div class="text-muted" style="font-size: 0.7rem;">{{ $notaFiscal->emitida_em->format('d/m H:i') }}</div>
                    @elseif($step['key'] === 'cancelada' && $notaFiscal->cancelada_em)
                        <div class="text-muted" style="font-size: 0.7rem;">{{ $notaFiscal->cancelada_em->format('d/m H:i') }}</div>
                    @endif
                </div>
            @endforeach

            {{-- Special statuses --}}
            @if($isRejected)
                <div class="d-flex align-items-center" style="height: 44px;">
                    <div style="width: 50px; height: 3px;" class="bg-danger rounded"></div>
                </div>
                <div class="text-center" style="min-width: 80px;">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-danger text-white"
                        style="width: 44px; height: 44px; box-shadow: 0 0 0 4px rgba(var(--bs-danger-rgb), 0.25);">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="small mt-1 fw-bold text-danger">Rejeitada</div>
                </div>
            @endif

            @if($isInutilizada)
                <div class="d-flex align-items-center" style="height: 44px;">
                    <div style="width: 50px; height: 3px;" class="bg-secondary rounded"></div>
                </div>
                <div class="text-center" style="min-width: 80px;">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-secondary text-white"
                        style="width: 44px; height: 44px;">
                        <i class="bi bi-slash-circle"></i>
                    </div>
                    <div class="small mt-1 fw-bold text-secondary">Inutilizada</div>
                </div>
            @endif

            @if($isContingencia)
                <div class="d-flex align-items-center" style="height: 44px;">
                    <div style="width: 50px; height: 3px;" class="bg-warning rounded"></div>
                </div>
                <div class="text-center" style="min-width: 80px;">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-warning text-dark"
                        style="width: 44px; height: 44px;">
                        <i class="bi bi-shield-exclamation"></i>
                    </div>
                    <div class="small mt-1 fw-bold text-warning">Contingencia</div>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Dados da Nota --}}
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-info-circle me-1"></i> Dados da Nota
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <label class="text-muted small d-block">Tipo</label>
                        <span class="fw-semibold">{{ $notaFiscal->tipo->label() }}</span>
                    </div>
                    <div class="col-6">
                        <label class="text-muted small d-block">Numero / Serie</label>
                        <span class="fw-semibold">{{ $notaFiscal->numero ?? 'Aguardando' }} / {{ $notaFiscal->serie ?? '-' }}</span>
                    </div>
                    <div class="col-12">
                        <label class="text-muted small d-block">Chave de Acesso</label>
                        <code class="text-break small">{{ $notaFiscal->chave_acesso ?? 'Aguardando autorizacao' }}</code>
                    </div>
                    <div class="col-6">
                        <label class="text-muted small d-block">Natureza da Operacao</label>
                        <span>{{ $notaFiscal->natureza_operacao ?? '-' }}</span>
                    </div>
                    <div class="col-6">
                        <label class="text-muted small d-block">Emitida em</label>
                        <span>{{ $notaFiscal->emitida_em?->format('d/m/Y H:i:s') ?? '-' }}</span>
                    </div>
                    <div class="col-12">
                        <hr class="my-1">
                    </div>
                    <div class="col-12">
                        <label class="text-muted small d-block">Valor Total</label>
                        <span class="fw-bold fs-4 text-success">R$ {{ number_format($notaFiscal->valor_total, 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Cliente e Venda --}}
    <div class="col-md-6">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-person me-1"></i> Cliente / Venda
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="text-muted small d-block">Cliente</label>
                        <span class="fw-semibold">{{ $notaFiscal->cliente->nome_razao_social ?? 'Consumidor Final' }}</span>
                    </div>
                    @if($notaFiscal->cliente?->cpf_cnpj)
                    <div class="col-12">
                        <label class="text-muted small d-block">CPF/CNPJ</label>
                        <span>{{ $notaFiscal->cliente->cpf_cnpj }}</span>
                    </div>
                    @endif
                    @if($notaFiscal->venda)
                    <div class="col-12">
                        <label class="text-muted small d-block">Venda Vinculada</label>
                        <a href="{{ route('app.vendas.show', $notaFiscal->venda) }}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-receipt me-1"></i> Venda #{{ $notaFiscal->venda->numero }}
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        @if($notaFiscal->cancelamento_motivo)
            <div class="card shadow-sm border-danger">
                <div class="card-header bg-danger text-white fw-semibold">
                    <i class="bi bi-exclamation-triangle me-1"></i> Cancelamento
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>Motivo:</strong> {{ $notaFiscal->cancelamento_motivo }}</p>
                    @if($notaFiscal->cancelamento_protocolo)
                        <p class="mb-0"><strong>Protocolo:</strong> <code>{{ $notaFiscal->cancelamento_protocolo }}</code></p>
                    @endif
                    @if($notaFiscal->cancelada_em)
                        <p class="mb-0 mt-1 text-muted small">{{ $notaFiscal->cancelada_em->format('d/m/Y H:i:s') }}</p>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- Debug / Focus NFe --}}
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#debugCollapse">
                <span class="fw-semibold"><i class="bi bi-bug me-1"></i> Dados Tecnicos (Focus NFe)</span>
                <i class="bi bi-chevron-down text-muted"></i>
            </div>
            <div id="debugCollapse" class="collapse">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="text-muted small d-block">Focus Ref</label>
                            <code>{{ $notaFiscal->focus_ref ?? '-' }}</code>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small d-block">Focus Status</label>
                            <code>{{ $notaFiscal->focus_status ?? '-' }}</code>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small d-block">Focus Mensagem</label>
                            <span class="small">{{ $notaFiscal->focus_mensagem ?? '-' }}</span>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small d-block">XML URL</label>
                            <small class="text-break">{{ $notaFiscal->xml_url ?? '-' }}</small>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small d-block">DANFE URL</label>
                            <small class="text-break">{{ $notaFiscal->danfe_url ?? '-' }}</small>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small d-block">PDF URL</label>
                            <small class="text-break">{{ $notaFiscal->pdf_url ?? '-' }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Histórico de Cartas de Correção --}}
@if($notaFiscal->tipo === \App\Enums\TipoNotaFiscal::NFe && $notaFiscal->cartasCorrecao->isNotEmpty())
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div class="fw-semibold">
            <i class="bi bi-pencil-square me-1 text-warning"></i>
            Cartas de Correção ({{ $notaFiscal->cartasCorrecao->count() }}/20)
        </div>
        @if($notaFiscal->status === \App\Enums\StatusNotaFiscal::Autorizada && $notaFiscal->cartasCorrecao->count() < 20)
        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalCartaCorrecao">
            <i class="bi bi-plus-lg me-1"></i> Nova CC-e
        </button>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:60px">#</th>
                    <th style="width:150px">Enviada em</th>
                    <th>Texto da correção</th>
                    <th style="width:120px">Status</th>
                    <th style="width:150px">Protocolo</th>
                    <th style="width:120px" class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach($notaFiscal->cartasCorrecao as $cc)
                <tr>
                    <td class="fw-semibold text-muted">#{{ $cc->numero_sequencia }}</td>
                    <td><small>{{ $cc->enviada_em?->format('d/m/Y H:i') ?? '-' }}</small></td>
                    <td><small>{{ $cc->correcao }}</small>
                        @if($cc->user)
                            <small class="text-muted d-block"><i class="bi bi-person me-1"></i>{{ $cc->user->name }}</small>
                        @endif
                        @if($cc->status === 'rejeitada' && $cc->mensagem_sefaz)
                            <small class="text-danger d-block"><i class="bi bi-exclamation-triangle me-1"></i>{{ $cc->mensagem_sefaz }}</small>
                        @endif
                    </td>
                    <td>
                        @if($cc->status === 'autorizada')
                            <span class="badge bg-success">Autorizada</span>
                        @elseif($cc->status === 'rejeitada')
                            <span class="badge bg-danger">Rejeitada</span>
                        @else
                            <span class="badge bg-secondary">Pendente</span>
                        @endif
                    </td>
                    <td><small class="font-monospace">{{ $cc->protocolo ?? '-' }}</small></td>
                    <td class="text-end">
                        @if($cc->pdf_url)
                            <a href="{{ $cc->pdf_url }}" target="_blank" class="btn btn-sm btn-outline-danger" title="PDF">
                                <i class="bi bi-file-pdf"></i>
                            </a>
                        @endif
                        @if($cc->xml_url)
                            <a href="{{ $cc->xml_url }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="XML">
                                <i class="bi bi-file-code"></i>
                            </a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Modal Cancelar --}}
@if($notaFiscal->status === \App\Enums\StatusNotaFiscal::Autorizada)
<div class="modal fade" id="modalCancelar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('app.notas-fiscais.cancelar', $notaFiscal) }}">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-x-circle me-1"></i> Cancelar Nota Fiscal</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Atencao:</strong> Esta acao e irreversivel. A nota fiscal sera cancelada junto a SEFAZ.
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Justificativa <small class="text-muted fw-normal">(minimo 15 caracteres)</small></label>
                        <textarea name="justificativa" class="form-control" rows="3" minlength="15" maxlength="255" required placeholder="Informe o motivo do cancelamento..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle me-1"></i> Confirmar Cancelamento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Modal Carta de Correcao --}}
@if($notaFiscal->status === \App\Enums\StatusNotaFiscal::Autorizada && $notaFiscal->tipo === \App\Enums\TipoNotaFiscal::NFe)
<div class="modal fade" id="modalCartaCorrecao" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('app.notas-fiscais.carta-correcao', $notaFiscal) }}">
                @csrf
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-1"></i> Carta de Correcao (CC-e)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        A Carta de Correcao permite corrigir informacoes da NF-e, <strong>exceto</strong>: valores fiscais, dados do destinatario e datas.
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Texto da Correcao <small class="text-muted fw-normal">(minimo 15 caracteres)</small></label>
                        <textarea name="correcao" class="form-control" rows="4" minlength="15" maxlength="1000" required placeholder="Descreva a correcao a ser realizada..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-send me-1"></i> Enviar CC-e
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
document.getElementById('btn-consultar')?.addEventListener('click', function () {
    const btn = this;
    const icon = btn.querySelector('i');
    btn.disabled = true;
    icon.className = 'bi bi-arrow-repeat spin';

    fetch('{{ route("app.notas-fiscais.consultar", $notaFiscal) }}', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        }
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        icon.className = 'bi bi-arrow-repeat';
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(() => {
        btn.disabled = false;
        icon.className = 'bi bi-arrow-repeat';
        alert('Erro ao consultar status.');
    });
});
</script>
<style>
.spin { animation: spin 1s linear infinite; display: inline-block; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
@endpush
