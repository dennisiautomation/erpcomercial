@extends('layouts.app')

@section('title', 'Configurar Comissoes')

@section('content')
<x-erp.page-header title="Configurar Comissoes" icon="gear">
    <a href="{{ route('app.comissoes.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form method="POST" action="{{ route('app.comissoes.configurar.store') }}">
    @csrf

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tab-global" role="tab">
                <i class="bi bi-people me-1"></i> Global (Por Vendedor)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-categoria" role="tab">
                <i class="bi bi-tags me-1"></i> Por Categoria
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-produto" role="tab">
                <i class="bi bi-box me-1"></i> Por Produto
            </a>
        </li>
    </ul>

    <div class="tab-content">
        {{-- Tab Global --}}
        <div class="tab-pane fade show active" id="tab-global" role="tabpanel">
            <x-erp.form-section title="Percentual de Comissao por Vendedor" icon="people">
                <p class="text-muted small mb-3">Defina o percentual padrao de comissao para cada vendedor. Este valor sera usado quando nao houver regra especifica por categoria ou produto.</p>

                @if($vendedores->isEmpty())
                    <p class="text-muted text-center py-3">Nenhum vendedor cadastrado.</p>
                @else
                    <div class="table-responsive">
                        <table class="erp-table">
                            <thead>
                                <tr>
                                    <th>Vendedor</th>
                                    <th style="width: 150px;">Comissao (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($vendedores as $vendedor)
                                    <tr>
                                        <td>{{ $vendedor->name }}</td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="0.1" min="0" max="100"
                                                       name="vendedores[{{ $vendedor->id }}]"
                                                       class="form-control"
                                                       value="{{ $config['vendedores'][$vendedor->id] ?? '' }}"
                                                       placeholder="0,0">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-erp.form-section>
        </div>

        {{-- Tab Categoria --}}
        <div class="tab-pane fade" id="tab-categoria" role="tabpanel">
            <x-erp.form-section title="Percentual de Comissao por Categoria" icon="tags">
                <p class="text-muted small mb-3">Defina percentuais especificos por categoria. Estas regras tem prioridade sobre o percentual global do vendedor.</p>

                @if($categorias->isEmpty())
                    <p class="text-muted text-center py-3">Nenhuma categoria cadastrada.</p>
                @else
                    <div class="table-responsive">
                        <table class="erp-table">
                            <thead>
                                <tr>
                                    <th>Categoria</th>
                                    <th style="width: 150px;">Comissao (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($categorias as $categoria)
                                    <tr>
                                        <td>{{ $categoria->nome }}</td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="0.1" min="0" max="100"
                                                       name="categorias[{{ $categoria->id }}]"
                                                       class="form-control"
                                                       value="{{ $config['categorias'][$categoria->id] ?? '' }}"
                                                       placeholder="0,0">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-erp.form-section>
        </div>

        {{-- Tab Produto --}}
        <div class="tab-pane fade" id="tab-produto" role="tabpanel">
            <x-erp.form-section title="Percentual de Comissao por Produto" icon="box">
                <p class="text-muted small mb-3">Defina percentuais especificos por produto. Estas regras tem a maior prioridade.</p>

                @if($produtos->isEmpty())
                    <p class="text-muted text-center py-3">Nenhum produto cadastrado.</p>
                @else
                    {{-- Search --}}
                    <div class="mb-3">
                        <input type="text" id="buscaProduto" class="form-control form-control-sm" placeholder="Buscar produto...">
                    </div>

                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="erp-table" id="tabelaProdutos">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th style="width: 150px;">Comissao (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($produtos as $produto)
                                    <tr class="produto-row">
                                        <td class="produto-nome">{{ $produto->nome }}</td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="0.1" min="0" max="100"
                                                       name="produtos[{{ $produto->id }}]"
                                                       class="form-control"
                                                       value="{{ $config['produtos'][$produto->id] ?? '' }}"
                                                       placeholder="0,0">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-erp.form-section>
        </div>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-erp-primary">
            <i class="bi bi-check-lg me-1"></i> Salvar Configuracoes
        </button>
    </div>
</form>

@push('scripts')
<script>
    document.getElementById('buscaProduto')?.addEventListener('input', function () {
        const termo = this.value.toLowerCase();
        document.querySelectorAll('.produto-row').forEach(row => {
            const nome = row.querySelector('.produto-nome').textContent.toLowerCase();
            row.style.display = nome.includes(termo) ? '' : 'none';
        });
    });
</script>
@endpush
@endsection
