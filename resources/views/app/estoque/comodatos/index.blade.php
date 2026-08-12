@extends('layouts.app')

@section('title', 'Peças em Poder de Terceiros')

@section('content')
<x-erp.page-header title="Peças em Poder de Terceiros"
                   subtitle="Bonificações que devem voltar — influencer, editorial, showroom"
                   icon="arrow-counterclockwise">
    <a href="{{ route('app.movimentacoes.create') }}" class="btn btn-erp-primary">
        <i class="bi bi-plus-lg me-1"></i> Nova Saída
    </a>
</x-erp.page-header>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="hourglass-split" color="warning" :value="$totalEmAberto" label="Aguardando retorno" />
    </div>
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="exclamation-triangle" color="danger" :value="$totalAtrasado" label="Atrasadas" />
    </div>
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="box-seam" color="primary"
                         :value="rtrim(rtrim(number_format($pecasFora, 3, ',', '.'), '0'), ',')"
                         label="Peças fora" />
    </div>
    <div class="col-6 col-lg-3">
        <x-erp.stat-card icon="check-circle" color="success" :value="$totalDevolvido" label="Já voltaram" />
    </div>
</div>

{{-- Filters --}}
<x-erp.filter-bar :action="route('app.comodatos.index')">
    <div class="col-md-3">
        <label class="form-label fw-semibold small text-muted">Situação</label>
        <select name="situacao" class="form-select">
            <option value="em_aberto" {{ $situacao == 'em_aberto' ? 'selected' : '' }}>Ainda fora</option>
            <option value="atrasado" {{ $situacao == 'atrasado' ? 'selected' : '' }}>Só atrasadas</option>
            <option value="devolvido" {{ $situacao == 'devolvido' ? 'selected' : '' }}>Já voltaram</option>
            <option value="perdido" {{ $situacao == 'perdido' ? 'selected' : '' }}>Não voltaram</option>
            <option value="todos" {{ $situacao == 'todos' ? 'selected' : '' }}>Todas</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold small text-muted">Com quem</label>
        <input type="text" name="responsavel" class="form-control"
               value="{{ request('responsavel') }}" placeholder="Nome ou @">
    </div>
    <div class="col-auto">
        <a href="{{ route('app.comodatos.index') }}" class="btn btn-erp-outline">
            <i class="bi bi-x-lg me-1"></i> Limpar
        </a>
    </div>
</x-erp.filter-bar>

{{-- Table --}}
<x-erp.data-table>
    <thead>
        <tr>
            <th>Produto</th>
            <th>SKU</th>
            <th>Com quem</th>
            <th>Loja</th>
            <th class="text-center">Saiu</th>
            <th class="text-center">Volta em</th>
            <th class="text-end">Fora</th>
            <th class="text-center">Situação</th>
            <th class="text-center">Ações</th>
        </tr>
    </thead>
    <tbody>
        @forelse($comodatos as $comodato)
        <tr class="{{ $comodato->esta_atrasado ? 'table-danger' : '' }}">
            <td>
                <div class="fw-semibold">{{ $comodato->produto->descricao ?? '-' }}</div>
                @if($comodato->observacoes)
                    <small class="text-muted">{{ Str::limit($comodato->observacoes, 60) }}</small>
                @endif
            </td>
            <td><small class="font-monospace">{{ $comodato->produto->sku ?? '-' }}</small></td>
            <td>
                <div class="fw-semibold">{{ $comodato->responsavel }}</div>
                @if($comodato->contato)
                    <small class="text-muted">{{ $comodato->contato }}</small>
                @endif
            </td>
            <td>
                <span class="badge bg-light text-dark border">
                    <i class="bi bi-building me-1"></i>{{ $comodato->unidade->nome ?? '-' }}
                </span>
            </td>
            <td class="text-center">
                <small>{{ $comodato->data_saida?->format('d/m/Y') }}</small>
            </td>
            <td class="text-center">
                <div class="fw-semibold">{{ $comodato->data_prevista_retorno?->format('d/m/Y') }}</div>
                @if($comodato->esta_atrasado)
                    <small class="text-danger fw-semibold">
                        {{ $comodato->dias_atraso }} {{ $comodato->dias_atraso == 1 ? 'dia' : 'dias' }} de atraso
                    </small>
                @endif
            </td>
            <td class="text-end">
                <span class="fw-semibold">
                    {{ rtrim(rtrim(number_format($comodato->quantidade_pendente, 3, ',', '.'), '0'), ',') }}
                </span>
                @if((float) $comodato->quantidade_devolvida > 0)
                    <br><small class="text-muted">
                        de {{ rtrim(rtrim(number_format($comodato->quantidade, 3, ',', '.'), '0'), ',') }}
                    </small>
                @endif
            </td>
            <td class="text-center">
                <span class="badge bg-{{ $comodato->status->cor() }}">{{ $comodato->status->label() }}</span>
            </td>
            <td class="text-center">
                @if($comodato->status->emAberto())
                <div class="action-btns">
                    <button type="button" class="btn btn-sm btn-erp-outline"
                            data-bs-toggle="modal" data-bs-target="#modal-devolver-{{ $comodato->id }}"
                            title="Registrar retorno">
                        <i class="bi bi-arrow-return-left"></i>
                    </button>
                    <form method="POST" action="{{ route('app.comodatos.perda', $comodato) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                data-confirm="Encerrar como não devolvida? O estoque NÃO volta."
                                title="Não voltou">
                            <i class="bi bi-x-octagon"></i>
                        </button>
                    </form>
                </div>
                @else
                    <small class="text-muted">{{ $comodato->data_retorno?->format('d/m/Y') ?? '—' }}</small>
                @endif
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="9">
                <x-erp.empty-state icon="arrow-counterclockwise"
                                   title="Nenhuma peça fora"
                                   description="Quando registrar uma bonificação marcando que a peça volta, ela aparece aqui." />
            </td>
        </tr>
        @endforelse
    </tbody>
    <x-slot name="pagination">
        @if($comodatos->hasPages())
            {{ $comodatos->links() }}
        @endif
    </x-slot>
</x-erp.data-table>

{{-- Modais de devolução (fora da tabela para não quebrar o layout) --}}
@foreach($comodatos as $comodato)
    @if($comodato->status->emAberto())
    <div class="modal fade" id="modal-devolver-{{ $comodato->id }}" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('app.comodatos.devolver', $comodato) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-arrow-return-left me-1"></i> Registrar retorno
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        <strong>{{ $comodato->produto->descricao ?? '-' }}</strong><br>
                        <small class="text-muted">
                            com {{ $comodato->responsavel }} desde {{ $comodato->data_saida?->format('d/m/Y') }}
                        </small>
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Quanto voltou</label>
                        <input type="number" name="quantidade" class="form-control"
                               step="0.001" min="0.001"
                               max="{{ $comodato->quantidade_pendente }}"
                               value="{{ $comodato->quantidade_pendente }}" required>
                        <div class="form-text">
                            Faltam {{ rtrim(rtrim(number_format($comodato->quantidade_pendente, 3, ',', '.'), '0'), ',') }}.
                            Pode devolver em partes.
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Observações</label>
                        <textarea name="observacoes" rows="2" class="form-control"
                                  placeholder="Estado da peça, avaria, etc."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-erp-outline" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-erp-primary">
                        <i class="bi bi-check-lg me-1"></i> Confirmar retorno
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
@endforeach
@endsection
