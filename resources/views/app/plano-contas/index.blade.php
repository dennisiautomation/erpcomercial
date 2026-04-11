@extends('layouts.app')

@section('title', 'Plano de Contas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Plano de Contas</h4>
    <a href="{{ route('app.plano-contas.create') }}" class="btn btn-primary">
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

<div class="card">
    <div class="card-body">
        @if($contas->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-diagram-3 fs-1"></i>
                <p class="mt-2">Nenhuma conta cadastrada.</p>
                <a href="{{ route('app.plano-contas.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Cadastrar Primeira Conta
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
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
