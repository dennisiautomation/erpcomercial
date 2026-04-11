@extends('layouts.app')

@section('title', 'Nota Fiscal #' . ($notaFiscal->numero ?? $notaFiscal->id))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <i class="bi bi-file-earmark-text me-2"></i>
        {{ strtoupper($notaFiscal->tipo->value) }} #{{ $notaFiscal->numero ?? 'Pendente' }}
        <span class="badge bg-{{ $notaFiscal->status->color() }} ms-2">{{ $notaFiscal->status->label() }}</span>
    </h4>
    <div class="d-flex gap-2">
        @if($notaFiscal->status === \App\Enums\StatusNotaFiscal::Pendente)
            <button type="button" class="btn btn-info" id="btn-consultar">
                <i class="bi bi-arrow-repeat me-1"></i> Consultar Status
            </button>
        @endif

        @if($notaFiscal->xml_url)
            <a href="{{ route('app.notas-fiscais.xml', $notaFiscal) }}" class="btn btn-success" target="_blank">
                <i class="bi bi-file-code me-1"></i> XML
            </a>
        @endif

        @if($notaFiscal->danfe_url || $notaFiscal->pdf_url)
            <a href="{{ route('app.notas-fiscais.danfe', $notaFiscal) }}" class="btn btn-danger" target="_blank">
                <i class="bi bi-file-pdf me-1"></i> DANFE / PDF
            </a>
        @endif

        @if($notaFiscal->status === \App\Enums\StatusNotaFiscal::Autorizada)
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalCancelar">
                <i class="bi bi-x-circle me-1"></i> Cancelar
            </button>
            @if($notaFiscal->tipo === \App\Enums\TipoNotaFiscal::NFe)
                <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalCartaCorrecao">
                    <i class="bi bi-pencil-square me-1"></i> Carta de Correcao
                </button>
            @endif
        @endif

        <a href="{{ route('app.notas-fiscais.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
</div>

<div class="row g-4">
    {{-- Status Timeline --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history me-1"></i> Timeline</div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    @php
                        $steps = [
                            ['status' => 'pendente', 'label' => 'Pendente', 'icon' => 'bi-hourglass-split'],
                            ['status' => 'autorizada', 'label' => 'Autorizada', 'icon' => 'bi-check-circle'],
                        ];
                        $currentValue = $notaFiscal->status->value;
                        $isRejected = in_array($currentValue, ['rejeitada', 'cancelada', 'inutilizada']);
                    @endphp

                    {{-- Pendente --}}
                    <div class="text-center">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-success text-white" style="width:40px;height:40px">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div class="small mt-1">Pendente</div>
                    </div>

                    <div class="flex-grow-0" style="width:60px;height:2px;background:#dee2e6"></div>

                    {{-- Autorizada / Rejeitada --}}
                    @if($currentValue === 'autorizada' || $currentValue === 'cancelada')
                        <div class="text-center">
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-success text-white" style="width:40px;height:40px">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="small mt-1">Autorizada</div>
                        </div>
                    @elseif($currentValue === 'rejeitada')
                        <div class="text-center">
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-danger text-white" style="width:40px;height:40px">
                                <i class="bi bi-x-circle"></i>
                            </div>
                            <div class="small mt-1">Rejeitada</div>
                        </div>
                    @else
                        <div class="text-center">
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-secondary bg-opacity-25 text-muted" style="width:40px;height:40px">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="small mt-1 text-muted">Autorizada</div>
                        </div>
                    @endif

                    @if($currentValue === 'cancelada')
                        <div class="flex-grow-0" style="width:60px;height:2px;background:#dee2e6"></div>
                        <div class="text-center">
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-danger text-white" style="width:40px;height:40px">
                                <i class="bi bi-x-circle"></i>
                            </div>
                            <div class="small mt-1">Cancelada</div>
                        </div>
                    @endif

                    @if($currentValue === 'inutilizada')
                        <div class="flex-grow-0" style="width:60px;height:2px;background:#dee2e6"></div>
                        <div class="text-center">
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-secondary text-white" style="width:40px;height:40px">
                                <i class="bi bi-slash-circle"></i>
                            </div>
                            <div class="small mt-1">Inutilizada</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Dados da Nota --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i> Dados da Nota</div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <th class="text-muted" style="width:40%">Tipo:</th>
                        <td>{{ $notaFiscal->tipo->label() }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Numero:</th>
                        <td>{{ $notaFiscal->numero ?? 'Aguardando' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Serie:</th>
                        <td>{{ $notaFiscal->serie ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Chave de Acesso:</th>
                        <td><small class="text-break">{{ $notaFiscal->chave_acesso ?? 'Aguardando' }}</small></td>
                    </tr>
                    <tr>
                        <th class="text-muted">Natureza Operacao:</th>
                        <td>{{ $notaFiscal->natureza_operacao ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Ambiente:</th>
                        <td>
                            <span class="badge bg-{{ $notaFiscal->ambiente === 'producao' ? 'danger' : 'warning' }}">
                                {{ ucfirst($notaFiscal->ambiente ?? 'N/A') }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted">Valor Total:</th>
                        <td class="fw-bold fs-5 text-success">R$ {{ number_format($notaFiscal->valor_total, 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Emitida em:</th>
                        <td>{{ $notaFiscal->emitida_em?->format('d/m/Y H:i:s') ?? '-' }}</td>
                    </tr>
                    @if($notaFiscal->cancelada_em)
                        <tr>
                            <th class="text-muted">Cancelada em:</th>
                            <td class="text-danger">{{ $notaFiscal->cancelada_em->format('d/m/Y H:i:s') }}</td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- Cliente e Venda --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-person me-1"></i> Cliente / Venda</div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <th class="text-muted" style="width:40%">Cliente:</th>
                        <td>{{ $notaFiscal->cliente->nome_razao_social ?? 'Consumidor Final' }}</td>
                    </tr>
                    @if($notaFiscal->cliente)
                        <tr>
                            <th class="text-muted">CPF/CNPJ:</th>
                            <td>{{ $notaFiscal->cliente->cpf_cnpj ?? '-' }}</td>
                        </tr>
                    @endif
                    @if($notaFiscal->venda)
                        <tr>
                            <th class="text-muted">Venda:</th>
                            <td>
                                <a href="{{ route('app.vendas.show', $notaFiscal->venda) }}">
                                    #{{ $notaFiscal->venda->numero }}
                                </a>
                            </td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>

        @if($notaFiscal->cancelamento_motivo)
            <div class="card mt-3 border-danger">
                <div class="card-header bg-danger text-white"><i class="bi bi-exclamation-triangle me-1"></i> Cancelamento</div>
                <div class="card-body">
                    <p class="mb-1"><strong>Motivo:</strong> {{ $notaFiscal->cancelamento_motivo }}</p>
                    @if($notaFiscal->cancelamento_protocolo)
                        <p class="mb-0"><strong>Protocolo:</strong> {{ $notaFiscal->cancelamento_protocolo }}</p>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- Debug: Focus NFe Response --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#debugCollapse">
                <span><i class="bi bi-bug me-1"></i> Dados Focus NFe (Debug)</span>
                <i class="bi bi-chevron-down"></i>
            </div>
            <div id="debugCollapse" class="collapse">
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <th class="text-muted" style="width:30%">Focus Ref:</th>
                            <td><code>{{ $notaFiscal->focus_ref ?? '-' }}</code></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Focus Status:</th>
                            <td><code>{{ $notaFiscal->focus_status ?? '-' }}</code></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Focus Mensagem:</th>
                            <td>{{ $notaFiscal->focus_mensagem ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">XML URL:</th>
                            <td><small class="text-break">{{ $notaFiscal->xml_url ?? '-' }}</small></td>
                        </tr>
                        <tr>
                            <th class="text-muted">DANFE URL:</th>
                            <td><small class="text-break">{{ $notaFiscal->danfe_url ?? '-' }}</small></td>
                        </tr>
                        <tr>
                            <th class="text-muted">PDF URL:</th>
                            <td><small class="text-break">{{ $notaFiscal->pdf_url ?? '-' }}</small></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

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
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Acao irreversivel. A nota fiscal sera cancelada junto a SEFAZ.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Justificativa <small class="text-muted">(minimo 15 caracteres)</small></label>
                        <textarea name="justificativa" class="form-control" rows="3" minlength="15" maxlength="255" required placeholder="Informe o motivo do cancelamento..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-danger">Confirmar Cancelamento</button>
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
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-1"></i> Carta de Correcao</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-1"></i>
                        A Carta de Correcao permite corrigir informacoes da NF-e, exceto: valores fiscais, dados do destinatario, e datas.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Texto da Correcao <small class="text-muted">(minimo 15 caracteres)</small></label>
                        <textarea name="correcao" class="form-control" rows="4" minlength="15" maxlength="1000" required placeholder="Descreva a correcao a ser realizada..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-warning">Enviar Carta de Correcao</button>
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
    icon.classList.add('spin');

    fetch('{{ route("app.notas-fiscais.consultar", $notaFiscal) }}', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        }
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        icon.classList.remove('spin');
        if (data.success) {
            alert('Status atualizado: ' + data.label);
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(() => {
        btn.disabled = false;
        icon.classList.remove('spin');
        alert('Erro ao consultar status.');
    });
});
</script>
<style>
.spin { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
@endpush
