@extends('layouts.app')

@section('title', 'Detalhes da Transferência')

@section('content')
<x-erp.page-header title="Transferência #{{ $transferencia->id }}" subtitle="Solicitada em {{ $transferencia->created_at->format('d/m/Y \a\s H:i') }}" icon="truck">
    <a href="{{ route('app.transferencias.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

{{-- Status Timeline --}}
<x-erp.card>
    @php
        $steps = ['solicitada', 'aprovada', 'em_transito', 'concluida'];
        $currentIndex = array_search($transferencia->status, $steps);
        $isCancelled = $transferencia->status === 'cancelada';
    @endphp
    <div class="d-flex justify-content-between position-relative px-4">
        <div class="position-absolute top-50 start-0 end-0" style="height:2px; background:#e9ecef; z-index:0; margin-top:15px;"></div>
        @foreach($steps as $i => $step)
            @php
                if ($isCancelled) {
                    $active = false;
                    $stepClass = 'bg-secondary bg-opacity-25 text-secondary';
                } elseif ($currentIndex !== false && $i <= $currentIndex) {
                    $active = true;
                    $stepClass = 'bg-success text-white';
                } else {
                    $active = false;
                    $stepClass = 'bg-secondary bg-opacity-25 text-secondary';
                }
                $stepIcon = match($step) {
                    'solicitada' => 'bi-send',
                    'aprovada' => 'bi-check-circle',
                    'em_transito' => 'bi-truck',
                    'concluida' => 'bi-check-all',
                };
            @endphp
            <div class="text-center position-relative" style="z-index:1;">
                <div class="rounded-circle {{ $stepClass }} d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                    <i class="bi {{ $stepIcon }}"></i>
                </div>
                <div class="small mt-1 {{ $active ? 'fw-semibold' : 'text-muted' }}">{{ ucfirst(str_replace('_', ' ', $step)) }}</div>
            </div>
        @endforeach
    </div>
    @if($isCancelled)
        <div class="text-center mt-3">
            <x-erp.status-badge status="cancelada" />
        </div>
    @endif
</x-erp.card>

<div class="row g-4 mt-1">
    <div class="col-lg-8">
        {{-- General Info --}}
        <x-erp.card title="Informações Gerais" icon="info-circle">
            <div class="table-responsive">
            <table class="table table-borderless mb-0">
                <tr>
                    <th width="40%">Unidade Origem</th>
                    <td>{{ $transferencia->unidadeOrigem->nome ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Unidade Destino</th>
                    <td>{{ $transferencia->unidadeDestino->nome ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Solicitante</th>
                    <td>{{ $transferencia->solicitante->name ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Aprovador</th>
                    <td>{{ $transferencia->aprovador->name ?? 'Aguardando' }}</td>
                </tr>
                @if($transferencia->observacoes)
                <tr>
                    <th>Observações</th>
                    <td>{{ $transferencia->observacoes }}</td>
                </tr>
                @endif
            </table>
            </div>
        </x-erp.card>

        {{-- Items --}}
        <x-erp.card title="Itens da Transferência" icon="box-seam" class="mt-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <button type="button" class="btn btn-erp-outline btn-sm" id="btn-imprimir-itens">
                    <i class="bi bi-printer me-1"></i> Imprimir
                </button>
                <span class="badge bg-primary rounded-pill">{{ $transferencia->itens->count() }} {{ $transferencia->itens->count() === 1 ? 'item' : 'itens' }}</span>
            </div>
            <div class="table-responsive">
                <table class="erp-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">#</th>
                            <th>Produto</th>
                            <th class="text-end">Quantidade</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transferencia->itens as $index => $item)
                        <tr>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill">{{ $index + 1 }}</span>
                            </td>
                            <td class="fw-semibold">{{ $item->produto->descricao ?? '-' }}</td>
                            <td class="text-end">
                                <span class="fw-bold">{{ number_format($item->quantidade, 3, ',', '.') }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-erp.card>

        {{-- Action buttons --}}
        @if($transferencia->status === 'solicitada')
        <x-erp.card title="Ações" icon="lightning" class="mt-4">
            <div class="d-flex gap-2">
                <form method="POST" action="{{ route('app.transferencias.aprovar', $transferencia) }}"
                    data-confirm="Confirma a aprovação desta transferência? O estoque sera movimentado automaticamente.">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-check-circle me-1"></i> Aprovar Transferência
                    </button>
                </form>
                <form method="POST" action="{{ route('app.transferencias.cancelar', $transferencia) }}"
                    data-confirm="Confirma o cancelamento desta transferência?">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-outline-danger btn-lg">
                        <i class="bi bi-x-circle me-1"></i> Cancelar
                    </button>
                </form>
            </div>
        </x-erp.card>
        @endif
        @if($transferencia->status === 'aprovada' || $transferencia->status === 'em_transito')
        <x-erp.card title="Andamento" icon="truck" class="mt-4">
            @if($transferencia->status === 'aprovada')
            <form method="POST" action="{{ route('app.transferencias.em-transito', $transferencia) }}"
                data-confirm="Marcar esta transferencia como em transito?">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-truck me-1"></i> Marcar em transito</button>
            </form>
            @else
            <form method="POST" action="{{ route('app.transferencias.concluir', $transferencia) }}"
                data-confirm="Confirmar que a mercadoria chegou e concluir a transferencia?">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-check-all me-1"></i> Marcar concluida</button>
            </form>
            @endif
            <div class="form-text mt-2">O estoque ja foi movimentado na aprovacao; este passo apenas registra o andamento.</div>
        </x-erp.card>
        @endif
    </div>

    {{-- Sidebar --}}
    <div class="col-lg-4">
        <x-erp.card title="Histórico" icon="clock-history">
            <div class="d-flex align-items-start mb-3">
                <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3" style="min-width:36px;height:36px;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-send text-primary small"></i>
                </div>
                <div>
                    <div class="fw-semibold small">Solicitação criada</div>
                    <div class="text-muted small">{{ $transferencia->created_at->format('d/m/Y H:i') }}</div>
                    <div class="text-muted small">por {{ $transferencia->solicitante->name ?? '-' }}</div>
                </div>
            </div>
            @if($transferencia->status !== 'solicitada')
            <div class="d-flex align-items-start mb-3">
                <div class="rounded-circle {{ $transferencia->status === 'cancelada' ? 'bg-danger' : 'bg-success' }} bg-opacity-10 p-2 me-3" style="min-width:36px;height:36px;display:flex;align-items:center;justify-content:center;">
                    <i class="bi {{ $transferencia->status === 'cancelada' ? 'bi-x-circle text-danger' : 'bi-check-circle text-success' }} small"></i>
                </div>
                <div>
                    <div class="fw-semibold small">{{ match($transferencia->status) { 'cancelada' => 'Cancelada', 'em_transito' => 'Em transito', 'concluida' => 'Concluida', default => 'Aprovada' } }}</div>
                    <div class="text-muted small">{{ $transferencia->updated_at->format('d/m/Y H:i') }}</div>
                    @if($transferencia->aprovador)
                        <div class="text-muted small">por {{ $transferencia->aprovador->name }}</div>
                    @endif
                </div>
            </div>
            @endif
        </x-erp.card>
    </div>
</div>
@endsection

@push('scripts')
@php
$dadosImpressao = [
        'titulo'   => 'Transferencia #' . $transferencia->id,
        'origem'   => $transferencia->unidadeOrigem->nome ?? '-',
        'destino'  => $transferencia->unidadeDestino->nome ?? '-',
        'solic'    => $transferencia->solicitante->name ?? '-',
        'data'     => $transferencia->created_at->format('d/m/Y H:i'),
        'status'   => ucfirst(str_replace('_', ' ', $transferencia->status)),
        'obs'      => $transferencia->observacoes,
        'itens'    => $transferencia->itens->map(fn ($i) => ['p' => $i->produto->descricao ?? '-', 'q' => number_format($i->quantidade, 3, ',', '.')])->values(),
];
@endphp
<script>
document.getElementById('btn-imprimir-itens')?.addEventListener('click', function () {
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const d = @json($dadosImpressao);
    const linhas = d.itens.map((i, n) => `<tr><td>${n + 1}</td><td>${esc(i.p)}</td><td class="r">${esc(i.q)}</td></tr>`).join('');
    const w = window.open('', '_blank', 'width=800,height=900');
    if (!w) { alert('Libere pop-ups para imprimir.'); return; }
    w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${esc(d.titulo)}</title>
<style>body{font-family:Arial,sans-serif;font-size:13px;margin:24px;color:#000}h1{font-size:18px;margin:0 0 8px}
.i{margin:2px 0}table{width:100%;border-collapse:collapse;margin-top:14px}th,td{border:1px solid #444;padding:6px 8px;text-align:left}
th{background:#eee}.r{text-align:right}.ass{display:flex;gap:40px;margin-top:60px}.ass div{flex:1;border-top:1px solid #000;padding-top:4px;text-align:center}</style></head><body>
<h1>${esc(d.titulo)} - Itens da Transferencia</h1>
<div class="i"><b>Origem:</b> ${esc(d.origem)} &nbsp; <b>Destino:</b> ${esc(d.destino)}</div>
<div class="i"><b>Solicitante:</b> ${esc(d.solic)} &nbsp; <b>Data:</b> ${esc(d.data)} &nbsp; <b>Status:</b> ${esc(d.status)}</div>
${d.obs ? `<div class="i"><b>Observacoes:</b> ${esc(d.obs)}</div>` : ''}
<table><thead><tr><th style="width:40px">#</th><th>Produto</th><th class="r" style="width:110px">Quantidade</th></tr></thead><tbody>${linhas}</tbody></table>
<div class="ass"><div>Enviado por</div><div>Recebido por</div></div>
<script>window.onload=function(){window.print()}<\/script></body></html>`);
    w.document.close();
});
</script>
@endpush
