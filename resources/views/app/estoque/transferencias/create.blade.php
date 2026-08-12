@extends('layouts.app')

@section('title', 'Nova Transferencia de Estoque')

@section('content')
<x-erp.page-header title="Nova Transferencia de Estoque" subtitle="Solicite a transferencia de produtos para outra unidade" icon="truck">
    <a href="{{ route('app.transferencias.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

<div class="row">
    <div class="col-lg-9">
        <form method="POST" action="{{ route('app.transferencias.store') }}" id="form-transferencia">
            @csrf

            @php
                $unidadeAtual = (int) session('unidade_id');
                $estoquesOrigem = $estoques->where('unidade_id', $unidadeAtual)->values();
                $temMultiplos = $estoques->count() > $unidades->count();
            @endphp

            <x-erp.form-section title="Origem e Destino" icon="building">
                <div class="row g-3 mb-4">
                    @if($temMultiplos && $estoquesOrigem->count() > 1)
                    <div class="col-md-6">
                        <label for="estoque_origem_id" class="form-label fw-semibold">
                            <i class="bi bi-boxes me-1"></i> Estoque de origem
                        </label>
                        <select name="estoque_origem_id" id="estoque_origem_id" class="form-select form-select-lg">
                            @foreach($estoquesOrigem as $e)
                                <option value="{{ $e->id }}" {{ old('estoque_origem_id', $estoquesOrigem->firstWhere('is_padrao', true)?->id) == $e->id ? 'selected' : '' }}>
                                    {{ $e->nome }}{{ $e->is_padrao ? ' (padrão)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Desta loja</div>
                    </div>
                    @endif

                    <div class="col-md-6">
                        <label for="unidade_destino_id" class="form-label fw-semibold">
                            <i class="bi bi-building me-1"></i> Loja destino <span class="text-danger">*</span>
                        </label>
                        <select name="unidade_destino_id" id="unidade_destino_id"
                            class="form-select form-select-lg @error('unidade_destino_id') is-invalid @enderror" required>
                            <option value="">Selecione...</option>
                            @foreach($unidades as $unidade)
                                <option value="{{ $unidade->id }}" {{ old('unidade_destino_id') == $unidade->id ? 'selected' : '' }}>
                                    {{ $unidade->nome }}{{ $unidade->id === $unidadeAtual ? ' (esta loja)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('unidade_destino_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    @if($temMultiplos)
                    <div class="col-md-6" id="wrap-estoque-destino" style="display:none">
                        <label for="estoque_destino_id" class="form-label fw-semibold">
                            <i class="bi bi-boxes me-1"></i> Estoque de destino
                        </label>
                        <select name="estoque_destino_id" id="estoque_destino_id"
                            class="form-select form-select-lg @error('estoque_destino_id') is-invalid @enderror"></select>
                        @error('estoque_destino_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    @endif
                </div>
            </x-erp.form-section>

            <x-erp.form-section title="Itens da Transferencia" icon="box-seam">
                <div class="d-flex justify-content-end mb-3">
                    <button type="button" class="btn btn-success btn-sm" id="btn-add-item">
                        <i class="bi bi-plus-lg me-1"></i> Adicionar Item
                    </button>
                </div>

                <div id="itens-container">
                    <div class="card bg-light border-0 mb-2 item-row" data-index="0">
                        <div class="card-body py-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-1 text-center">
                                    <span class="badge bg-primary rounded-pill item-number">1</span>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-semibold text-muted">Produto</label>
                                    <select name="itens[0][produto_id]" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        @foreach($produtos as $produto)
                                            <option value="{{ $produto->id }}">{{ $produto->descricao }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold text-muted">Quantidade</label>
                                    <input type="number" name="itens[0][quantidade]" class="form-control" step="0.001" min="0.001" placeholder="0,000" required>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-danger btn-sm btn-remove-item" disabled>
                                        <i class="bi bi-trash me-1"></i> Remover
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </x-erp.form-section>

            <x-erp.form-section title="Observacoes" icon="chat-text">
                <div class="mb-4">
                    <label for="observacoes" class="form-label fw-semibold">Observacoes</label>
                    <textarea name="observacoes" id="observacoes" rows="3"
                        class="form-control @error('observacoes') is-invalid @enderror"
                        placeholder="Motivo da transferencia, instrucoes, etc.">{{ old('observacoes') }}</textarea>
                    @error('observacoes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-erp-primary btn-lg px-4">
                        <i class="bi bi-send me-1"></i> Solicitar Transferencia
                    </button>
                    <a href="{{ route('app.transferencias.index') }}" class="btn btn-erp-outline btn-lg">Cancelar</a>
                </div>
            </x-erp.form-section>
        </form>
    </div>

    {{-- Side info --}}
    <div class="col-lg-3">
        <x-erp.card title="Como funciona" icon="info-circle">
            <ol class="small text-muted ps-3 mb-0">
                <li class="mb-2">Selecione a unidade de destino</li>
                <li class="mb-2">Adicione os produtos e quantidades</li>
                <li class="mb-2">Envie a solicitacao</li>
                <li class="mb-2">Aguarde a aprovacao do responsavel</li>
                <li class="mb-0">O estoque sera movimentado automaticamente</li>
            </ol>
        </x-erp.card>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let itemIndex = 1;
    const container = document.getElementById('itens-container');
    const produtos = @json($produtos);

    document.getElementById('btn-add-item').addEventListener('click', function() {
        let options = '<option value="">Selecione...</option>';
        produtos.forEach(p => {
            options += `<option value="${p.id}">${p.descricao}</option>`;
        });

        const row = document.createElement('div');
        row.className = 'card bg-light border-0 mb-2 item-row';
        row.dataset.index = itemIndex;
        row.innerHTML = `
            <div class="card-body py-3">
                <div class="row g-3 align-items-center">
                    <div class="col-md-1 text-center">
                        <span class="badge bg-primary rounded-pill item-number">${itemIndex + 1}</span>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold text-muted">Produto</label>
                        <select name="itens[${itemIndex}][produto_id]" class="form-select" required>
                            ${options}
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Quantidade</label>
                        <input type="number" name="itens[${itemIndex}][quantidade]" class="form-control" step="0.001" min="0.001" placeholder="0,000" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-danger btn-sm btn-remove-item">
                            <i class="bi bi-trash me-1"></i> Remover
                        </button>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(row);
        itemIndex++;
        updateRemoveButtons();
        renumberItems();
    });

    container.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-item')) {
            e.target.closest('.item-row').remove();
            updateRemoveButtons();
            renumberItems();
        }
    });

    function updateRemoveButtons() {
        const rows = container.querySelectorAll('.item-row');
        rows.forEach(row => {
            row.querySelector('.btn-remove-item').disabled = rows.length <= 1;
        });
    }

    function renumberItems() {
        const rows = container.querySelectorAll('.item-row');
        rows.forEach((row, i) => {
            row.querySelector('.item-number').textContent = i + 1;
        });
    }

    /* --- Estoque de destino segue a loja escolhida ------------------- */
    (function () {
        const wrap = document.getElementById('wrap-estoque-destino');
        if (!wrap) return; // empresa sem múltiplos estoques: bloco nem existe

        const selLoja = document.getElementById('unidade_destino_id');
        const selEstoque = document.getElementById('estoque_destino_id');
        const estoques = @json($estoques);
        const antigo = @json(old('estoque_destino_id'));

        function sync() {
            const lojaId = parseInt(selLoja.value, 10);
            const daLoja = estoques.filter(e => e.unidade_id === lojaId);

            selEstoque.innerHTML = '';
            daLoja.forEach(e => {
                const opt = document.createElement('option');
                opt.value = e.id;
                opt.textContent = e.nome + (e.is_padrao ? ' (padrão)' : '');
                if (antigo && parseInt(antigo, 10) === e.id) opt.selected = true;
                selEstoque.appendChild(opt);
            });

            // Só faz sentido perguntar se a loja destino tem mais de um
            wrap.style.display = daLoja.length > 1 ? '' : 'none';
        }

        selLoja.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush
