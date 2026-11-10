@extends('layouts.app')

@section('title', 'Plano de Contas')

@section('content')
<div class="fade-in">
<div class="page-header">
    <div>
        <h4><i class="bi bi-diagram-3 me-2"></i>Plano de Contas</h4>
        <div class="subtitle">Estrutura hierarquica de contas contabeis</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <form method="POST" action="{{ route('app.plano-contas.padrao') }}"
              data-confirm="Criar as contas padrão (salários, encargos, benefícios, aluguel, energia...)? As contas que você já tem não mudam.">
            @csrf
            <button type="submit" class="btn btn-erp btn-erp-outline">
                <i class="bi bi-magic me-1"></i> Criar contas padrão
            </button>
        </form>
        <a href="{{ route('app.plano-contas.create') }}" class="btn btn-erp btn-erp-primary">
            <i class="bi bi-plus-lg me-1"></i> Nova Conta
        </a>
    </div>
</div>

{{-- os avisos de sucesso/erro saem do layout (components.alert); o bloco próprio daqui duplicava a mensagem --}}

<div class="erp-card">
    <div class="card-body">
        @if($contas->isEmpty())
            <div class="empty-state">
                <i class="bi bi-diagram-3 d-block"></i>
                <h5>Nenhuma conta cadastrada</h5>
                <p>Crie a primeira conta para começar.</p>
                <a href="{{ route('app.plano-contas.create') }}" class="btn btn-erp btn-erp-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Cadastrar Primeira Conta
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="erp-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Natureza</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
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
