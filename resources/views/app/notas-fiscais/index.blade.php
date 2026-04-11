@extends('layouts.app')

@section('title', 'Notas Fiscais')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Notas Fiscais</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('app.notas-fiscais.emitir-nfse') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Emitir NFS-e
        </a>
        <a href="{{ route('app.notas-fiscais.inutilizar') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-slash-circle me-1"></i> Inutilizar
        </a>
    </div>
</div>

{{-- Type Explanation Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="erp-card p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon success"><i class="bi bi-receipt"></i></div>
                <div>
                    <h6 class="mb-1 fw-bold">NFC-e</h6>
                    <small class="text-muted">Cupom fiscal emitido no PDV para consumidor final</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="erp-card p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon primary"><i class="bi bi-file-earmark-text"></i></div>
                <div>
                    <h6 class="mb-1 fw-bold">NF-e (DANFE)</h6>
                    <small class="text-muted">Nota fiscal para vendas entre empresas ou sob demanda</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="erp-card p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon info"><i class="bi bi-tools"></i></div>
                <div>
                    <h6 class="mb-1 fw-bold">NFS-e</h6>
                    <small class="text-muted">Nota fiscal de servico para prestadores</small>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Emitidas no Mes</p>
                        <h3 class="fw-bold mb-0">{{ $totalEmitidas }}</h3>
                    </div>
                    <div class="rounded-3 bg-primary bg-opacity-10 p-2">
                        <i class="bi bi-file-earmark-text fs-4 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Autorizadas no Mes</p>
                        <h3 class="fw-bold mb-0 text-success">{{ $totalAutorizadas }}</h3>
                    </div>
                    <div class="rounded-3 bg-success bg-opacity-10 p-2">
                        <i class="bi bi-check-circle fs-4 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Canceladas no Mes</p>
                        <h3 class="fw-bold mb-0 text-danger">{{ $totalCanceladas }}</h3>
                    </div>
                    <div class="rounded-3 bg-danger bg-opacity-10 p-2">
                        <i class="bi bi-x-circle fs-4 text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Buscar</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="busca" class="form-control" placeholder="Numero, chave, cliente..." value="{{ request('busca') }}">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach(\App\Enums\TipoNotaFiscal::cases() as $tipo)
                        <option value="{{ $tipo->value }}" {{ request('tipo') === $tipo->value ? 'selected' : '' }}>
                            {{ $tipo->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach(\App\Enums\StatusNotaFiscal::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">De</label>
                <input type="date" name="data_inicio" class="form-control form-control-sm" value="{{ request('data_inicio') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Ate</label>
                <input type="date" name="data_fim" class="form-control form-control-sm" value="{{ request('data_fim') }}">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1" title="Filtrar">
                    <i class="bi bi-search"></i>
                </button>
                <a href="{{ route('app.notas-fiscais.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpar">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Numero</th>
                    <th class="text-center">Tipo</th>
                    <th>Cliente</th>
                    <th>Chave de Acesso</th>
                    <th class="text-end">Valor (R$)</th>
                    <th class="text-center">Status</th>
                    <th>Emissao</th>
                    <th class="text-center" style="width: 140px;">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($notasFiscais as $nf)
                    <tr>
                        <td>
                            <strong>#{{ $nf->numero ?? '-' }}</strong>
                            @if($nf->serie)
                                <small class="text-muted d-block">Serie {{ $nf->serie }}</small>
                            @endif
                        </td>
                        <td class="text-center">
                            @php
                                $tipoBg = match($nf->tipo) {
                                    \App\Enums\TipoNotaFiscal::NFe => 'primary',
                                    \App\Enums\TipoNotaFiscal::NFCe => 'info',
                                    \App\Enums\TipoNotaFiscal::NFSe => 'dark',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge bg-{{ $tipoBg }}">{{ strtoupper($nf->tipo->value) }}</span>
                        </td>
                        <td>
                            {{ $nf->cliente->nome_razao_social ?? 'Consumidor Final' }}
                            @if($nf->cliente?->cpf_cnpj)
                                <small class="text-muted d-block">{{ $nf->cliente->cpf_cnpj }}</small>
                            @endif
                        </td>
                        <td>
                            @if($nf->chave_acesso)
                                <span class="font-monospace small text-muted" title="{{ $nf->chave_acesso }}" style="cursor: help;">
                                    {{ substr($nf->chave_acesso, 0, 25) }}...
                                </span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-end fw-semibold">{{ number_format($nf->valor_total, 2, ',', '.') }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $nf->status->color() }}">{{ $nf->status->label() }}</span>
                        </td>
                        <td class="text-nowrap small">{{ $nf->emitida_em?->format('d/m/Y H:i') ?? $nf->created_at->format('d/m/Y H:i') }}</td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('app.notas-fiscais.show', $nf) }}" class="btn btn-outline-primary" title="Detalhes">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($nf->status === \App\Enums\StatusNotaFiscal::Pendente)
                                    <button type="button" class="btn btn-outline-info btn-consultar" data-url="{{ route('app.notas-fiscais.consultar', $nf) }}" title="Consultar status">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                @endif
                                @if($nf->xml_url)
                                    <a href="{{ route('app.notas-fiscais.xml', $nf) }}" class="btn btn-outline-success" title="XML" target="_blank">
                                        <i class="bi bi-file-code"></i>
                                    </a>
                                @endif
                                @if($nf->danfe_url || $nf->pdf_url)
                                    <a href="{{ route('app.notas-fiscais.danfe', $nf) }}" class="btn btn-outline-danger" title="DANFE/PDF" target="_blank">
                                        <i class="bi bi-file-pdf"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-file-earmark-x fs-1 d-block mb-2 opacity-50"></i>
                            Nenhuma nota fiscal encontrada.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($notasFiscais->hasPages())
        <div class="card-footer bg-white">
            {{ $notasFiscais->links() }}
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.btn-consultar').forEach(btn => {
    btn.addEventListener('click', function () {
        const url = this.dataset.url;
        const icon = this.querySelector('i');
        const originalClass = icon.className;
        this.disabled = true;
        icon.className = 'bi bi-arrow-repeat spin';

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        })
        .then(r => r.json())
        .then(data => {
            this.disabled = false;
            icon.className = originalClass;
            if (data.success) {
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        })
        .catch(() => {
            this.disabled = false;
            icon.className = originalClass;
            alert('Erro ao consultar status.');
        });
    });
});
</script>
<style>
.spin { animation: spin 1s linear infinite; display: inline-block; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
@endpush
