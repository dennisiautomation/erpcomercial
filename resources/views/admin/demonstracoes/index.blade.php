@extends('layouts.app')

@section('title', 'Demonstrações')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Solicitações de demonstração</h4>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

{{-- Filtros por status --}}
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('admin.demonstracoes.index') }}"
       class="btn btn-sm {{ !$status ? 'btn-primary' : 'btn-outline-secondary' }}">
        Todos
    </a>
    @foreach (\App\Models\SolicitacaoDemonstracao::STATUS_LABELS as $key => $label)
        <a href="{{ route('admin.demonstracoes.index', ['status' => $key]) }}"
           class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
            {{ $label }} <span class="badge bg-light text-dark ms-1">{{ $contagem[$key] ?? 0 }}</span>
        </a>
    @endforeach
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Recebido</th>
                    <th>Nome</th>
                    <th>Empresa</th>
                    <th>Contato</th>
                    <th class="text-center">Lojas</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Ação</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($leads as $lead)
                    <tr>
                        <td class="text-nowrap">
                            {{ $lead->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td>{{ $lead->nome }}</td>
                        <td>{{ $lead->empresa }}</td>
                        <td>
                            <a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a><br>
                            <small class="text-muted">{{ $lead->whatsapp }}</small>
                        </td>
                        <td class="text-center">{{ $lead->qtd_lojas ?? '—' }}</td>
                        <td class="text-center">
                            @php
                                $cores = ['novo' => 'primary', 'contatado' => 'info', 'convertido' => 'success', 'descartado' => 'secondary'];
                            @endphp
                            <span class="badge bg-{{ $cores[$lead->status] ?? 'secondary' }}">
                                {{ \App\Models\SolicitacaoDemonstracao::STATUS_LABELS[$lead->status] ?? $lead->status }}
                            </span>
                        </td>
                        <td class="text-end">
                            <form action="{{ route('admin.demonstracoes.status', $lead) }}" method="POST" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <div class="input-group input-group-sm" style="width: 190px; margin-left:auto;">
                                    <select name="status" class="form-select form-select-sm">
                                        @foreach (\App\Models\SolicitacaoDemonstracao::STATUS_LABELS as $key => $label)
                                            <option value="{{ $key }}" @selected($lead->status === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-outline-primary" type="submit">Salvar</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            Nenhuma solicitação ainda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $leads->links() }}
</div>
@endsection
