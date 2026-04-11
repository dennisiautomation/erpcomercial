@extends('layouts.app')

@section('title', 'Clientes')

@push('styles')
<style>
    .counter-card {
        border: none;
        border-radius: 12px;
        transition: transform 0.15s, box-shadow 0.15s;
        cursor: pointer;
        text-decoration: none;
    }
    .counter-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.08) !important;
    }
    .counter-card .counter-value {
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1;
    }
    .counter-card .counter-label {
        font-size: 0.75rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .cliente-row { transition: background 0.15s; }
    .cliente-row:hover { background: #f8fafc !important; }
    .status-badge {
        font-size: 0.75rem;
        font-weight: 600;
        padding: 5px 12px;
        border-radius: 20px;
    }
    .action-btn {
        width: 34px;
        height: 34px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.15s;
    }
    .tipo-badge {
        font-size: 0.65rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .search-input:focus {
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        border-color: #3b82f6;
    }
</style>
@endpush

@section('content')
{{-- Header --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-people me-2"></i>Clientes</h4>
        <p class="text-muted mb-0 small">Gerencie sua base de clientes</p>
    </div>
    <a href="{{ route('app.clientes.create') }}" class="btn btn-primary rounded-pill px-4">
        <i class="bi bi-plus-lg me-1"></i> Novo Cliente
    </a>
</div>

{{-- Counter Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="{{ route('app.clientes.index') }}" class="counter-card card shadow-sm d-block h-100">
            <div class="card-body py-3 px-3 text-center">
                <div class="counter-value text-dark">{{ $totalGeral }}</div>
                <div class="counter-label text-muted mt-1">Total</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('app.clientes.index', ['status' => 'ativo']) }}" class="counter-card card shadow-sm d-block h-100">
            <div class="card-body py-3 px-3 text-center">
                <div class="counter-value text-success">{{ $totalAtivos }}</div>
                <div class="counter-label text-muted mt-1">Ativos</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('app.clientes.index', ['status' => 'inativo']) }}" class="counter-card card shadow-sm d-block h-100">
            <div class="card-body py-3 px-3 text-center">
                <div class="counter-value text-secondary">{{ $totalInativos }}</div>
                <div class="counter-label text-muted mt-1">Inativos</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('app.clientes.index', ['status' => 'bloqueado']) }}" class="counter-card card shadow-sm d-block h-100">
            <div class="card-body py-3 px-3 text-center">
                <div class="counter-value text-danger">{{ $totalBloqueados }}</div>
                <div class="counter-label text-muted mt-1">Bloqueados</div>
            </div>
        </a>
    </div>
</div>

{{-- Filtros --}}
<div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('app.clientes.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="busca" class="form-control border-start-0 search-input" placeholder="Buscar por nome, CPF/CNPJ, e-mail..." value="{{ request('busca') }}">
                </div>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">Status</option>
                    <option value="ativo" {{ request('status') === 'ativo' ? 'selected' : '' }}>Ativo</option>
                    <option value="inativo" {{ request('status') === 'inativo' ? 'selected' : '' }}>Inativo</option>
                    <option value="bloqueado" {{ request('status') === 'bloqueado' ? 'selected' : '' }}>Bloqueado</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="tipo_pessoa" class="form-select">
                    <option value="">Tipo</option>
                    <option value="pf" {{ request('tipo_pessoa') === 'pf' ? 'selected' : '' }}>Pessoa Fisica</option>
                    <option value="pj" {{ request('tipo_pessoa') === 'pj' ? 'selected' : '' }}>Pessoa Juridica</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" name="cidade" class="form-control" placeholder="Cidade" value="{{ request('cidade') }}">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
                <a href="{{ route('app.clientes.index') }}" class="btn btn-outline-secondary" title="Limpar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Tabela --}}
<div class="card shadow-sm border-0" style="border-radius: 12px;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th class="ps-4 py-3 fw-semibold text-muted small text-uppercase">Cliente</th>
                        <th class="py-3 fw-semibold text-muted small text-uppercase">CPF/CNPJ</th>
                        <th class="py-3 fw-semibold text-muted small text-uppercase">Cidade/UF</th>
                        <th class="py-3 fw-semibold text-muted small text-uppercase">Contato</th>
                        <th class="py-3 fw-semibold text-muted small text-uppercase text-center">Status</th>
                        <th class="py-3 fw-semibold text-muted small text-uppercase text-center pe-4" style="width: 140px;">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clientes as $cliente)
                        <tr class="cliente-row">
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3"
                                         style="width: 40px; height: 40px; background: {{ $cliente->tipo_pessoa === 'pj' ? '#ede9fe' : '#e0f2fe' }}; color: {{ $cliente->tipo_pessoa === 'pj' ? '#7c3aed' : '#0284c7' }}; font-weight: 700; font-size: 0.85rem; flex-shrink: 0;">
                                        {{ strtoupper(substr($cliente->nome_razao_social, 0, 2)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="fw-semibold text-truncate">{{ $cliente->nome_razao_social }}</div>
                                        @if($cliente->nome_fantasia)
                                            <small class="text-muted text-truncate d-block">{{ $cliente->nome_fantasia }}</small>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="tipo-badge {{ $cliente->tipo_pessoa === 'pj' ? 'bg-purple-subtle text-purple' : 'bg-info-subtle text-info' }}"
                                          style="{{ $cliente->tipo_pessoa === 'pj' ? 'background:#ede9fe;color:#7c3aed;' : '' }}">
                                        {{ strtoupper($cliente->tipo_pessoa) }}
                                    </span>
                                    <span class="small">{{ $cliente->cpf_cnpj }}</span>
                                </div>
                            </td>
                            <td>
                                @if($cliente->cidade)
                                    <span>{{ $cliente->cidade }}{{ $cliente->uf ? '/' . $cliente->uf : '' }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($cliente->whatsapp)
                                    <div class="small"><i class="bi bi-whatsapp text-success me-1"></i>{{ $cliente->whatsapp }}</div>
                                @elseif($cliente->telefone)
                                    <div class="small"><i class="bi bi-telephone me-1 text-muted"></i>{{ $cliente->telefone }}</div>
                                @endif
                                @if($cliente->email)
                                    <div class="small text-muted text-truncate" style="max-width: 180px;"><i class="bi bi-envelope me-1"></i>{{ $cliente->email }}</div>
                                @endif
                                @if(!$cliente->whatsapp && !$cliente->telefone && !$cliente->email)
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @php
                                    $statusMap = [
                                        'ativo'     => ['bg' => 'success', 'label' => 'Ativo'],
                                        'inativo'   => ['bg' => 'secondary', 'label' => 'Inativo'],
                                        'bloqueado' => ['bg' => 'danger', 'label' => 'Bloqueado'],
                                    ];
                                    $st = $statusMap[$cliente->status] ?? ['bg' => 'secondary', 'label' => ucfirst($cliente->status)];
                                @endphp
                                <span class="status-badge bg-{{ $st['bg'] }} bg-opacity-10 text-{{ $st['bg'] }}">
                                    {{ $st['label'] }}
                                </span>
                            </td>
                            <td class="text-center pe-4">
                                <div class="d-flex gap-1 justify-content-center">
                                    <a href="{{ route('app.clientes.show', $cliente) }}" class="action-btn btn btn-light" title="Visualizar">
                                        <i class="bi bi-eye text-info"></i>
                                    </a>
                                    <a href="{{ route('app.clientes.edit', $cliente) }}" class="action-btn btn btn-light" title="Editar">
                                        <i class="bi bi-pencil text-primary"></i>
                                    </a>
                                    <form method="POST" action="{{ route('app.clientes.destroy', $cliente) }}" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este cliente?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="action-btn btn btn-light" title="Excluir">
                                            <i class="bi bi-trash text-danger"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-people fs-1 d-block mb-3 opacity-50"></i>
                                    <h6>Nenhum cliente encontrado</h6>
                                    <p class="small mb-3">Tente ajustar os filtros ou cadastre um novo cliente</p>
                                    <a href="{{ route('app.clientes.create') }}" class="btn btn-sm btn-primary rounded-pill px-3">
                                        <i class="bi bi-plus-lg me-1"></i> Novo Cliente
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($clientes->hasPages())
        <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center" style="border-radius: 0 0 12px 12px;">
            <small class="text-muted">
                Exibindo {{ $clientes->firstItem() }} - {{ $clientes->lastItem() }} de {{ $clientes->total() }} clientes
            </small>
            {{ $clientes->links() }}
        </div>
    @endif
</div>
@endsection
