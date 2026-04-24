@extends('layouts.app')

@section('title', 'NFes Recebidas')

@section('content')
<x-erp.page-header title="NFes Recebidas" icon="inbox-fill" subtitle="Manifestação do destinatário — NFes emitidas contra sua empresa">
    <form method="POST" action="{{ route('app.nfes-recebidas.sincronizar') }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-sm btn-primary" @if(!$sincronizacaoAtiva) disabled title="Ative a emissão fiscal para sincronizar" @endif>
            <i class="bi bi-arrow-clockwise me-1"></i> Sincronizar agora
        </button>
    </form>
</x-erp.page-header>

@if(!$sincronizacaoAtiva)
    <div class="alert alert-info d-flex align-items-start">
        <i class="bi bi-info-circle me-2 fs-5 mt-1"></i>
        <div>
            <strong>Configuração fiscal pendente.</strong><br>
            Ative a emissão fiscal e cadastre o token Focus NFe em
            <a href="{{ route('app.configuracao-fiscal.edit') }}">Configurações Fiscais</a>
            para começar a receber NFes destinadas a esta unidade.
        </div>
    </div>
@endif

<x-erp.filter-bar :action="route('app.nfes-recebidas.index')">
    <div class="col-md-3">
        <label class="form-label small fw-semibold">Status</label>
        <select name="status" class="form-select form-select-sm">
            <option value="">Todas</option>
            <option value="pendente" @selected(request('status') === 'pendente')>Pendentes de manifestação</option>
            <option value="manifestada" @selected(request('status') === 'manifestada')>Já manifestadas</option>
            @foreach($tipos as $t)
                <option value="{{ $t->value }}" @selected(request('status') === $t->value)>{{ $t->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-semibold">Emitente (nome ou CNPJ)</label>
        <input type="text" name="emitente" value="{{ request('emitente') }}" class="form-control form-control-sm">
    </div>
    <div class="col-md-2">
        <label class="form-label small fw-semibold">De</label>
        <input type="date" name="desde" value="{{ request('desde') }}" class="form-control form-control-sm">
    </div>
    <div class="col-md-2">
        <label class="form-label small fw-semibold">Até</label>
        <input type="date" name="ate" value="{{ request('ate') }}" class="form-control form-control-sm">
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-sm btn-primary w-100">Filtrar</button>
    </div>
</x-erp.filter-bar>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table erp-table mb-0 align-middle">
            <thead>
                <tr>
                    <th style="width:105px">Data</th>
                    <th>Emitente</th>
                    <th style="width:170px">Chave</th>
                    <th style="width:110px" class="text-end">Valor</th>
                    <th style="width:200px">Manifestação</th>
                    <th style="width:110px" class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($notas as $nota)
                    @php
                        $tipo = $nota->tipo_ultima_manifestacao;
                        $cnpjFormatado = preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $nota->cnpj_emitente);
                    @endphp
                    <tr>
                        <td>
                            <small>{{ $nota->data_emissao?->format('d/m/Y') ?? '-' }}</small>
                            @if($nota->numero)
                                <small class="text-muted d-block">NF #{{ $nota->numero }}</small>
                            @endif
                        </td>
                        <td>
                            <strong class="small">{{ $nota->nome_emitente }}</strong>
                            <small class="text-muted d-block font-monospace">{{ $cnpjFormatado }}</small>
                        </td>
                        <td>
                            <small class="font-monospace text-muted" title="{{ $nota->chave_acesso }}">
                                …{{ substr($nota->chave_acesso, -12) }}
                            </small>
                        </td>
                        <td class="text-end">
                            <strong class="small">R$ {{ number_format((float) $nota->valor_total, 2, ',', '.') }}</strong>
                        </td>
                        <td>
                            @if($tipo)
                                <span class="badge bg-{{ $tipo->severidade() }}">
                                    <i class="bi bi-{{ $tipo->icone() }} me-1"></i>{{ $tipo->label() }}
                                </span>
                                <small class="text-muted d-block">
                                    {{ $nota->manifestada_em?->format('d/m/Y H:i') }}
                                    @if($nota->manifestador) — {{ $nota->manifestador->name }} @endif
                                </small>
                            @else
                                <span class="badge bg-secondary"><i class="bi bi-clock me-1"></i>Pendente</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($nota->danfe_url)
                                <a href="{{ $nota->danfe_url }}" target="_blank" class="btn btn-sm btn-outline-danger" title="DANFE">
                                    <i class="bi bi-file-pdf"></i>
                                </a>
                            @endif
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#modalManifestar-{{ $nota->id }}"
                                    title="Manifestar">
                                <i class="bi bi-megaphone"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-erp.empty-state title="Nenhuma NF-e recebida" icon="inbox"
                                description="Clique em 'Sincronizar agora' para buscar NFes destinadas à sua empresa." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($notas->hasPages())
        <div class="card-footer bg-white">{{ $notas->links() }}</div>
    @endif
</div>

{{-- Modais de manifestação --}}
@foreach($notas as $nota)
<div class="modal fade" id="modalManifestar-{{ $nota->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('app.nfes-recebidas.manifestar', $nota) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-megaphone me-2"></i>Manifestar NF-e de {{ $nota->nome_emitente }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Chave: <code class="small">{{ $nota->chave_acesso }}</code><br>
                        Valor: <strong>R$ {{ number_format((float) $nota->valor_total, 2, ',', '.') }}</strong>
                    </p>

                    <label class="form-label fw-semibold">Tipo de manifestação</label>
                    <div class="d-grid gap-2 mb-3">
                        @foreach($tipos as $t)
                            <label class="border rounded p-2 d-flex align-items-start gap-2 manifestacao-opcao cursor-pointer"
                                   data-variant="{{ $t->severidade() }}">
                                <input type="radio" name="tipo" value="{{ $t->value }}" class="form-check-input mt-1" required>
                                <div class="flex-grow-1">
                                    <strong><i class="bi bi-{{ $t->icone() }} text-{{ $t->severidade() }} me-1"></i>{{ $t->label() }}</strong>
                                    <small class="d-block text-muted">{{ $t->descricaoCurta() }}</small>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    <div data-requer-justificativa style="display:none;">
                        <label class="form-label fw-semibold">Justificativa <small class="text-muted fw-normal">(mínimo 15 caracteres)</small></label>
                        <textarea name="justificativa" class="form-control" rows="3" minlength="15" maxlength="255"
                                  placeholder="Descreva o motivo da manifestação"></textarea>
                        <div class="form-text">Obrigatória para "Operação Não Realizada" e "Desconhecimento".</div>
                    </div>

                    <div data-aviso-desconhecimento class="alert alert-danger mt-3 small" style="display:none;">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Atenção:</strong> o "Desconhecimento da Operação" é um ato grave — indica que você NÃO autorizou
                        nem recebeu esta operação. Use apenas em caso de fraude ou emissão indevida contra o seu CNPJ.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i> Enviar manifestação
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endsection

@push('scripts')
<script>
(function() {
    document.querySelectorAll('[id^="modalManifestar-"]').forEach(function (modal) {
        const radios = modal.querySelectorAll('input[name="tipo"]');
        const justificativaBox = modal.querySelector('[data-requer-justificativa]');
        const justificativaInput = justificativaBox?.querySelector('textarea');
        const avisoDescon = modal.querySelector('[data-aviso-desconhecimento]');
        const opcoes = modal.querySelectorAll('.manifestacao-opcao');

        function aplicar() {
            const sel = modal.querySelector('input[name="tipo"]:checked');
            const val = sel?.value;
            // 210220 = Desconhecimento, 210240 = Não Realizada
            const exigeJust = val === '210220' || val === '210240';
            const isDescon = val === '210220';

            if (justificativaBox) {
                justificativaBox.style.display = exigeJust ? '' : 'none';
                if (justificativaInput) {
                    justificativaInput.required = exigeJust;
                }
            }
            if (avisoDescon) {
                avisoDescon.style.display = isDescon ? '' : 'none';
            }

            opcoes.forEach(function (op) {
                op.classList.remove('border-success', 'border-warning', 'border-danger', 'border-primary');
            });
            if (sel) {
                const label = sel.closest('.manifestacao-opcao');
                const variant = label?.dataset?.variant || 'primary';
                label?.classList.add('border-' + variant);
            }
        }

        radios.forEach(function (r) { r.addEventListener('change', aplicar); });
        modal.addEventListener('shown.bs.modal', aplicar);
    });
})();
</script>
<style>
.manifestacao-opcao { transition: background 0.15s; }
.manifestacao-opcao:hover { background: #f8fafc; }
.manifestacao-opcao input:checked ~ div strong { text-decoration: none; }
</style>
@endpush
