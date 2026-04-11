@extends('layouts.app')

@section('title', 'Notas Fiscais')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Notas Fiscais</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('app.notas-fiscais.emitir-nfse') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Emitir NFS-e
        </a>
        <a href="{{ route('app.notas-fiscais.inutilizar') }}" class="btn btn-outline-secondary">
            <i class="bi bi-slash-circle me-1"></i> Inutilizar
        </a>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 bg-primary bg-opacity-10">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-primary">{{ $totalEmitidas }}</div>
                <div class="text-muted small">Emitidas no Mes</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 bg-success bg-opacity-10">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-success">{{ $totalAutorizadas }}</div>
                <div class="text-muted small">Autorizadas no Mes</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 bg-danger bg-opacity-10">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-danger">{{ $totalCanceladas }}</div>
                <div class="text-muted small">Canceladas no Mes</div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Buscar</label>
                <input type="text" name="busca" class="form-control" placeholder="Numero, chave, cliente..." value="{{ request('busca') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="">Todos</option>
                    @foreach(\App\Enums\TipoNotaFiscal::cases() as $tipo)
                        <option value="{{ $tipo->value }}" {{ request('tipo') === $tipo->value ? 'selected' : '' }}>
                            {{ $tipo->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    @foreach(\App\Enums\StatusNotaFiscal::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Data Inicio</label>
                <input type="date" name="data_inicio" class="form-control" value="{{ request('data_inicio') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Data Fim</label>
                <input type="date" name="data_fim" class="form-control" value="{{ request('data_fim') }}">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search"></i>
                </button>
            </div>
            <div class="col-md-1">
                <a href="{{ route('app.notas-fiscais.index') }}" class="btn btn-outline-secondary w-100" title="Limpar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Numero</th>
                    <th>Serie</th>
                    <th class="text-center">Tipo</th>
                    <th>Chave de Acesso</th>
                    <th>Cliente</th>
                    <th class="text-end">Valor (R$)</th>
                    <th class="text-center">Status</th>
                    <th>Data</th>
                    <th class="text-center">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($notasFiscais as $nf)
                    <tr>
                        <td><strong>#{{ $nf->numero ?? '-' }}</strong></td>
                        <td>{{ $nf->serie ?? '-' }}</td>
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
                            @if($nf->chave_acesso)
                                <small class="text-muted" title="{{ $nf->chave_acesso }}">
                                    {{ substr($nf->chave_acesso, 0, 20) }}...
                                </small>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>{{ $nf->cliente->nome_razao_social ?? 'Consumidor Final' }}</td>
                        <td class="text-end fw-semibold">{{ number_format($nf->valor_total, 2, ',', '.') }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $nf->status->color() }}">{{ $nf->status->label() }}</span>
                        </td>
                        <td>{{ $nf->emitida_em?->format('d/m/Y H:i') ?? $nf->created_at->format('d/m/Y H:i') }}</td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('app.notas-fiscais.show', $nf) }}" class="btn btn-outline-primary" title="Ver detalhes">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($nf->status === \App\Enums\StatusNotaFiscal::Pendente)
                                    <button type="button" class="btn btn-outline-info btn-consultar" data-url="{{ route('app.notas-fiscais.consultar', $nf) }}" title="Consultar status">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                @endif
                                @if($nf->xml_url)
                                    <a href="{{ route('app.notas-fiscais.xml', $nf) }}" class="btn btn-outline-success" title="Download XML" target="_blank">
                                        <i class="bi bi-file-code"></i>
                                    </a>
                                @endif
                                @if($nf->danfe_url || $nf->pdf_url)
                                    <a href="{{ route('app.notas-fiscais.danfe', $nf) }}" class="btn btn-outline-danger" title="Download DANFE/PDF" target="_blank">
                                        <i class="bi bi-file-pdf"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Nenhuma nota fiscal encontrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($notasFiscais->hasPages())
        <div class="card-footer">
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
        icon.classList.add('spin');

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        })
        .then(r => r.json())
        .then(data => {
            icon.classList.remove('spin');
            if (data.success) {
                alert('Status: ' + data.label + (data.message ? '\n' + data.message : ''));
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        })
        .catch(() => {
            icon.classList.remove('spin');
            alert('Erro ao consultar status.');
        });
    });
});
</script>
<style>
.spin { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
@endpush
