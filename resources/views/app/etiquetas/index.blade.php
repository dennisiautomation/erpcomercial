@extends('layouts.app')

@section('title', 'Gerar Etiquetas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0"><i class="bi bi-upc me-2"></i>Gerar Etiquetas</h4>
        <small class="text-muted">Selecione os produtos e gere etiquetas com codigo de barras</small>
    </div>
</div>

<form id="formEtiquetas" method="POST" action="{{ route('app.etiquetas.gerar') }}" target="_blank">
    @csrf

    {{-- Formato --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-grid me-2"></i>Formato da Etiqueta</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="formato" id="formato2x5" value="2x5" checked>
                        <label class="form-check-label" for="formato2x5">
                            <strong>2 x 5</strong> — 10 etiquetas por pagina (grande)
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="formato" id="formato3x7" value="3x7">
                        <label class="form-check-label" for="formato3x7">
                            <strong>3 x 7</strong> — 21 etiquetas por pagina (media)
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="formato" id="formato4x10" value="4x10">
                        <label class="form-check-label" for="formato4x10">
                            <strong>4 x 10</strong> — 40 etiquetas por pagina (pequena)
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Busca e selecao --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-box-seam me-2"></i>Produtos</h6>
            <div class="d-flex gap-2 align-items-center">
                <input type="text" id="buscaProduto" class="form-control form-control-sm" placeholder="Filtrar produtos..." style="width: 300px;">
                <button type="button" id="btnSelecionarTodos" class="btn btn-sm btn-outline-secondary">Selecionar Todos</button>
                <button type="button" id="btnLimparSelecao" class="btn btn-sm btn-outline-secondary">Limpar</button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th style="width: 50px;" class="text-center">
                                <i class="bi bi-check2-square"></i>
                            </th>
                            <th style="width: 100px;">Codigo</th>
                            <th>Descricao</th>
                            <th style="width: 150px;">Cod. Barras</th>
                            <th class="text-end" style="width: 120px;">Preco</th>
                            <th class="text-center" style="width: 120px;">Qtd. Etiquetas</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaProdutos">
                        @foreach($produtos as $index => $produto)
                            <tr class="produto-row" data-descricao="{{ strtolower($produto->descricao) }}" data-codigo="{{ strtolower($produto->codigo_interno ?? '') }}" data-barras="{{ strtolower($produto->codigo_barras ?? '') }}">
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input produto-check" data-index="{{ $index }}" value="{{ $produto->id }}">
                                </td>
                                <td><code class="fw-bold">{{ $produto->codigo_interno }}</code></td>
                                <td>{{ $produto->descricao }}</td>
                                <td>
                                    @if($produto->codigo_barras)
                                        <small class="text-muted">{{ $produto->codigo_barras }}</small>
                                    @else
                                        <small class="text-muted">-</small>
                                    @endif
                                </td>
                                <td class="text-end fw-bold text-success">R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}</td>
                                <td class="text-center">
                                    <input type="number" class="form-control form-control-sm text-center qtd-input" min="1" max="100" value="1" disabled style="width: 80px; margin: 0 auto;">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Resumo e botao --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <span class="text-muted">Produtos selecionados: <strong id="contadorProdutos">0</strong></span>
                <span class="text-muted ms-3">Total de etiquetas: <strong id="contadorEtiquetas">0</strong></span>
            </div>
            <button type="submit" class="btn btn-primary btn-lg" id="btnGerar" disabled>
                <i class="bi bi-printer me-2"></i>Gerar Etiquetas
            </button>
        </div>
    </div>

    {{-- Hidden inputs dinamicos --}}
    <div id="hiddenInputs"></div>
</form>

@if($produtos->isEmpty())
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>Nenhum produto ativo encontrado. <a href="{{ route('app.produtos.create') }}">Cadastre um produto</a> primeiro.
    </div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const buscaInput = document.getElementById('buscaProduto');
    const rows = document.querySelectorAll('.produto-row');
    const btnGerar = document.getElementById('btnGerar');
    const contadorProdutos = document.getElementById('contadorProdutos');
    const contadorEtiquetas = document.getElementById('contadorEtiquetas');
    const hiddenInputs = document.getElementById('hiddenInputs');
    const btnSelecionarTodos = document.getElementById('btnSelecionarTodos');
    const btnLimparSelecao = document.getElementById('btnLimparSelecao');

    // Filtro de busca
    buscaInput.addEventListener('input', function() {
        const termo = this.value.toLowerCase();
        rows.forEach(row => {
            const desc = row.dataset.descricao;
            const cod = row.dataset.codigo;
            const barras = row.dataset.barras;
            const match = desc.includes(termo) || cod.includes(termo) || barras.includes(termo);
            row.style.display = match ? '' : 'none';
        });
    });

    // Selecionar todos visiveis
    btnSelecionarTodos.addEventListener('click', function() {
        rows.forEach(row => {
            if (row.style.display !== 'none') {
                const check = row.querySelector('.produto-check');
                const qtd = row.querySelector('.qtd-input');
                check.checked = true;
                qtd.disabled = false;
            }
        });
        atualizarContadores();
    });

    // Limpar selecao
    btnLimparSelecao.addEventListener('click', function() {
        document.querySelectorAll('.produto-check').forEach(check => {
            check.checked = false;
        });
        document.querySelectorAll('.qtd-input').forEach(qtd => {
            qtd.disabled = true;
            qtd.value = 1;
        });
        atualizarContadores();
    });

    // Toggle checkbox
    document.querySelectorAll('.produto-check').forEach(check => {
        check.addEventListener('change', function() {
            const row = this.closest('tr');
            const qtd = row.querySelector('.qtd-input');
            qtd.disabled = !this.checked;
            if (!this.checked) qtd.value = 1;
            atualizarContadores();
        });
    });

    // Atualizar ao mudar quantidade
    document.querySelectorAll('.qtd-input').forEach(input => {
        input.addEventListener('input', atualizarContadores);
    });

    function atualizarContadores() {
        const checks = document.querySelectorAll('.produto-check:checked');
        let totalEtiquetas = 0;

        checks.forEach(check => {
            const row = check.closest('tr');
            const qtd = parseInt(row.querySelector('.qtd-input').value) || 1;
            totalEtiquetas += qtd;
        });

        contadorProdutos.textContent = checks.length;
        contadorEtiquetas.textContent = totalEtiquetas;
        btnGerar.disabled = checks.length === 0;
    }

    // Montar hidden inputs antes de submeter
    document.getElementById('formEtiquetas').addEventListener('submit', function(e) {
        hiddenInputs.innerHTML = '';
        const checks = document.querySelectorAll('.produto-check:checked');

        if (checks.length === 0) {
            e.preventDefault();
            alert('Selecione pelo menos um produto.');
            return;
        }

        checks.forEach((check, i) => {
            const row = check.closest('tr');
            const qtd = row.querySelector('.qtd-input').value;

            const inputId = document.createElement('input');
            inputId.type = 'hidden';
            inputId.name = `produtos[${i}][id]`;
            inputId.value = check.value;
            hiddenInputs.appendChild(inputId);

            const inputQtd = document.createElement('input');
            inputQtd.type = 'hidden';
            inputQtd.name = `produtos[${i}][quantidade]`;
            inputQtd.value = qtd;
            hiddenInputs.appendChild(inputQtd);
        });
    });
});
</script>
@endpush
