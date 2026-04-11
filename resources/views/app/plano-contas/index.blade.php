@extends('layouts.app')

@section('title', 'Plano de Contas')

@section('content')
<div class="fade-in">
<div class="page-header">
    <div>
        <h4><i class="bi bi-diagram-3 me-2"></i>Plano de Contas</h4>
        <div class="subtitle">Estrutura hierarquica de contas contabeis</div>
    </div>
    <a href="{{ route('app.plano-contas.create') }}" class="btn btn-erp btn-erp-primary">
        <i class="bi bi-plus-lg me-1"></i> Nova Conta
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="erp-card">
    <div class="card-body">
        @if($contas->isEmpty())
            <div class="empty-state">
                <i class="bi bi-diagram-3 d-block"></i>
                <h5>Nenhuma conta cadastrada</h5>
                <p>Crie a primeira conta para comecar.</p>
                <a href="{{ route('app.plano-contas.create') }}" class="btn btn-erp btn-erp-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Cadastrar Primeira Conta
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="erp-table">
                    <thead>
                        <tr>
                            <th>Codigo</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Natureza</th>
                            <th>Status</th>
                            <th class="text-end">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($contas as $conta)
                            @include('app.plano-contas._tree-row', ['conta' => $conta, 'level' => 0])
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
</div>

@push('scripts')
<script>
    function toggleChildren(id) {
        const rows = document.querySelectorAll(`.child-of-${id}`);
        rows.forEach(row => {
            row.classList.toggle('d-none');
            // Also hide grandchildren
            if (row.classList.contains('d-none')) {
                const childId = row.dataset.id;
                const grandchildren = document.querySelectorAll(`.child-of-${childId}`);
                grandchildren.forEach(gc => gc.classList.add('d-none'));
            }
        });
        const icon = document.querySelector(`#toggle-icon-${id}`);
        if (icon) {
            icon.classList.toggle('bi-chevron-down');
            icon.classList.toggle('bi-chevron-right');
        }
    }
</script>
@endpush
@endsection
