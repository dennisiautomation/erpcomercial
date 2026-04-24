@extends('layouts.app')

@section('title', 'Auditoria')

@section('content')
<x-erp.page-header title="Auditoria" icon="shield-check" subtitle="Histórico de alterações na plataforma">
    <a href="{{ route('app.auditoria.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-clockwise me-1"></i> Limpar filtros
    </a>
</x-erp.page-header>

<x-erp.filter-bar :action="route('app.auditoria.index')">
    <div class="col-md-2">
        <label class="form-label small fw-semibold">Tipo</label>
        <select name="tipo" class="form-select form-select-sm">
            <option value="">Todos</option>
            @foreach($tiposDisponiveis as $t)
                <option value="{{ $t }}" @selected(request('tipo') === $t)>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label small fw-semibold">Evento</label>
        <select name="evento" class="form-select form-select-sm">
            <option value="">Todos</option>
            <option value="created" @selected(request('evento') === 'created')>Criado</option>
            <option value="updated" @selected(request('evento') === 'updated')>Alterado</option>
            <option value="deleted" @selected(request('evento') === 'deleted')>Excluído</option>
            <option value="restored" @selected(request('evento') === 'restored')>Restaurado</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-semibold">Usuário</label>
        <select name="user_id" class="form-select form-select-sm">
            <option value="">Todos</option>
            @foreach($usuarios as $u)
                <option value="{{ $u->id }}" @selected((string) request('user_id') === (string) $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label small fw-semibold">De</label>
        <input type="date" name="desde" value="{{ request('desde') }}" class="form-control form-control-sm">
    </div>
    <div class="col-md-2">
        <label class="form-label small fw-semibold">Até</label>
        <input type="date" name="ate" value="{{ request('ate') }}" class="form-control form-control-sm">
    </div>
    <div class="col-md-1 d-flex align-items-end">
        <button type="submit" class="btn btn-sm btn-primary w-100">Filtrar</button>
    </div>
</x-erp.filter-bar>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table erp-table mb-0 align-middle">
            <thead>
                <tr>
                    <th style="width:160px">Quando</th>
                    <th style="width:180px">Usuário</th>
                    <th style="width:140px">Tipo</th>
                    <th style="width:120px">Evento</th>
                    <th>Descrição</th>
                    <th style="width:80px" class="text-end">Detalhes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activities as $act)
                    @php
                        $eventBadge = match($act->event) {
                            'created' => 'success',
                            'updated' => 'info',
                            'deleted' => 'danger',
                            'restored' => 'warning',
                            default => 'secondary',
                        };
                        $changes = $act->attribute_changes?->toArray() ?? [];
                    @endphp
                    <tr>
                        <td>
                            <small>{{ $act->created_at->format('d/m/Y H:i:s') }}</small>
                            <small class="text-muted d-block">{{ $act->created_at->diffForHumans() }}</small>
                        </td>
                        <td>
                            @if($act->causer)
                                <i class="bi bi-person-circle text-primary me-1"></i>
                                <small>{{ $act->causer->name }}</small>
                            @else
                                <small class="text-muted">Sistema</small>
                            @endif
                        </td>
                        <td><span class="badge bg-light text-dark">{{ $act->log_name ?? '-' }}</span></td>
                        <td><span class="badge bg-{{ $eventBadge }}">{{ $act->event ?? '-' }}</span></td>
                        <td>
                            <small>{{ $act->description }}</small>
                            @if($act->subject_type && $act->subject_id)
                                <small class="text-muted d-block">ID #{{ $act->subject_id }}</small>
                            @endif
                        </td>
                        <td class="text-end">
                            @if(!empty($changes['attributes']) || !empty($changes['old']))
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="collapse" data-bs-target="#detalhes-{{ $act->id }}"
                                    aria-label="Ver detalhes">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                    @if(!empty($changes['attributes']) || !empty($changes['old']))
                    <tr class="collapse" id="detalhes-{{ $act->id }}">
                        <td colspan="6" class="bg-light">
                            <div class="py-2 px-1">
                                <table class="table table-sm mb-0 small">
                                    <thead>
                                        <tr>
                                            <th>Campo</th>
                                            <th>Antes</th>
                                            <th>Depois</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach(($changes['attributes'] ?? []) as $field => $newVal)
                                            @php $oldVal = $changes['old'][$field] ?? null; @endphp
                                            <tr>
                                                <td class="fw-semibold">{{ $field }}</td>
                                                <td class="text-muted">
                                                    <code>{{ is_scalar($oldVal) ? $oldVal : json_encode($oldVal) }}</code>
                                                </td>
                                                <td>
                                                    <code>{{ is_scalar($newVal) ? $newVal : json_encode($newVal) }}</code>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="6">
                            <x-erp.empty-state title="Nenhum registro de auditoria" icon="inbox" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($activities->hasPages())
        <div class="card-footer bg-white">{{ $activities->links() }}</div>
    @endif
</div>
@endsection
