@extends('layouts.app')

@section('title', 'NFS-es Recebidas')

@section('content')
<x-erp.page-header title="NFS-es Recebidas" icon="briefcase">
    <div class="d-flex gap-2">
        @if($sincronizacaoAtiva)
            <form action="{{ route('app.nfses-recebidas.sincronizar') }}" method="POST"
                  data-confirm="Sincronizar NFS-es tomadas agora?">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-cloud-download me-1"></i>Sincronizar agora
                </button>
            </form>
        @else
            <span class="badge bg-warning text-dark">
                <i class="bi bi-exclamation-triangle me-1"></i>Fiscal desativado
            </span>
        @endif
    </div>
</x-erp.page-header>

<div class="alert alert-info small">
    <i class="bi bi-info-circle me-1"></i>
    NFS-es emitidas por prestadores contra o seu CNPJ. Útil para conferência fiscal,
    crédito de ISS retido e conciliação de pagamentos. Sincronização automática a cada 6 horas.
</div>

<x-erp.filter-bar :action="route('app.nfses-recebidas.index')">
    <div class="col-md-4">
        <input name="prestador" value="{{ request('prestador') }}" class="form-control"
               placeholder="Nome ou CNPJ do prestador">
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select">
            <option value="">Todas</option>
            <option value="autorizada" {{ request('status') === 'autorizada' ? 'selected' : '' }}>Autorizadas</option>
            <option value="cancelada" {{ request('status') === 'cancelada' ? 'selected' : '' }}>Canceladas</option>
        </select>
    </div>
    <div class="col-md-2"><input type="date" name="desde" value="{{ request('desde') }}" class="form-control"></div>
    <div class="col-md-2"><input type="date" name="ate" value="{{ request('ate') }}" class="form-control"></div>
</x-erp.filter-bar>

<x-erp.data-table>
    <thead>
        <tr>
            <th>Emissão</th>
            <th>Prestador</th>
            <th>Nº / Série</th>
            <th>Serviço</th>
            <th class="text-end">Valor</th>
            <th class="text-end">ISS</th>
            <th>Status</th>
            <th class="text-end">Ações</th>
        </tr>
    </thead>
    <tbody>
        @forelse($notas as $n)
            <tr>
                <td class="small">{{ $n->data_emissao?->format('d/m/Y') ?? '—' }}</td>
                <td>
                    <div class="fw-semibold">{{ $n->nome_prestador }}</div>
                    <div class="small text-muted">
                        {{ preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $n->cnpj_prestador) }}
                    </div>
                </td>
                <td class="small">{{ $n->numero }} / {{ $n->serie ?: '—' }}</td>
                <td class="small" style="max-width: 260px;">
                    <div class="text-truncate" title="{{ $n->discriminacao }}">
                        {{ $n->discriminacao ?? '—' }}
                    </div>
                    @if($n->item_lista_servico)
                        <small class="text-muted">LC {{ $n->item_lista_servico }}</small>
                    @endif
                </td>
                <td class="text-end">R$ {{ number_format($n->valor_servicos, 2, ',', '.') }}</td>
                <td class="text-end">
                    R$ {{ number_format($n->valor_iss, 2, ',', '.') }}
                    @if($n->iss_retido)
                        <br><span class="badge bg-secondary small">ISS retido</span>
                    @endif
                </td>
                <td>
                    <span class="badge bg-{{ $n->status === 'autorizada' ? 'success' : 'danger' }}">
                        {{ ucfirst($n->status) }}
                    </span>
                    @if($n->padrao === 'nacional')
                        <br><small class="text-muted">Portal Nacional</small>
                    @endif
                </td>
                <td class="text-end">
                    @if($n->pdf_url)
                        <a href="{{ $n->pdf_url }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </a>
                    @endif
                    @if($n->xml_url)
                        <a href="{{ $n->xml_url }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-file-code"></i>
                        </a>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8">
                    <x-erp.empty-state icon="briefcase"
                        title="Nenhuma NFS-e recebida"
                        description="Quando um prestador emitir NFS-e contra o seu CNPJ, ela aparecerá aqui após a próxima sincronização." />
                </td>
            </tr>
        @endforelse
    </tbody>
    <x-slot:pagination>{{ $notas->links() }}</x-slot:pagination>
</x-erp.data-table>
@endsection
