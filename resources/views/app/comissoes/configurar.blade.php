@extends('layouts.app')

@section('title', 'Configurar Comissoes')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-gear me-2"></i>Configurar Comissoes</h4>
    <a href="{{ route('app.comissoes.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

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
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Percentual de Comissao por Vendedor</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Defina o percentual padrao de comissao para cada vendedor. Este valor sera usado quando nao houver regra especifica por categoria ou produto.</p>

                    @if($vendedores->isEmpty())
                        <p class="text-muted text-center py-3">Nenhum vendedor cadastrado.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
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
                </div>
            </div>
        </div>

        {{-- Tab Categoria --}}
        <div class="tab-pane fade" id="tab-categoria" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Percentual de Comissao por Categoria</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Defina percentuais especificos por categoria. Estas regras tem prioridade sobre o percentual global do vendedor.</p>

                    @if($categorias->isEmpty())
                        <p class="text-muted text-center py-3">Nenhuma categoria cadastrada.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
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
                </div>
            </div>
        </div>

        {{-- Tab Produto --}}
        <div class="tab-pane fade" id="tab-produto" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Percentual de Comissao por Produto</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Defina percentuais especificos por produto. Estas regras tem a maior prioridade.</p>

                    @if($produtos->isEmpty())
                        <p class="text-muted text-center py-3">Nenhum produto cadastrado.</p>
                    @else
                        {{-- Search --}}
                        <div class="mb-3">
                            <input type="text" id="buscaProduto" class="form-control form-control-sm" placeholder="Buscar produto...">
                        </div>

                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-sm" id="tabelaProdutos">
                                <thead class="table-light">
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
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">
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
