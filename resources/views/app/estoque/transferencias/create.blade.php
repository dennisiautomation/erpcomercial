@extends('layouts.app')

@section('title', 'Nova Transferência de Estoque')

@section('content')
<x-erp.page-header title="Nova Transferência de Estoque" subtitle="Solicite a transferência de produtos para outra unidade" icon="truck">
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

            <x-erp.form-section title="Itens da Transferência" icon="box-seam">
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
                                    <div class="busca-produto" data-name="itens[0][produto_id]"></div>
                                </div>
                                <div class="col-md-3">
                                    <label for="campo-itens-0-quantidade" class="form-label small fw-semibold text-muted">Quantidade</label>
                                    <input id="campo-itens-0-quantidade" type="number" name="itens[0][quantidade]" class="form-control" step="0.001" min="0.001" placeholder="0,000" required>
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

            <x-erp.form-section title="Observações" icon="chat-text">
                <div class="mb-4">
                    <label for="observacoes" class="form-label fw-semibold">Observações</label>
                    <textarea name="observacoes" id="observacoes" rows="3"
                        class="form-control @error('observacoes') is-invalid @enderror"
                        placeholder="Motivo da transferência, instruções, etc.">{{ old('observacoes') }}</textarea>
                    @error('observacoes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-erp-primary btn-lg px-4">
                        <i class="bi bi-send me-1"></i> Solicitar Transferência
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
                <li class="mb-2">Envie a solicitação</li>
                <li class="mb-2">Aguarde a aprovação do responsável</li>
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

    /* --- Busca de produto enquanto digita (25/10/2026, pedido da DK) -----------
       Antes era um <select> com TODOS os produtos ativos, que abria inteiro. Agora
       as sugestões só aparecem a partir de MIN_LETRAS e filtram por nome, código
       interno, código de barras e SKU. Leitor de código de barras: bipou + Enter,
       se o código bate exato com um produto ele é escolhido sozinho. */
    const MIN_LETRAS = 3;
    const MAX_SUGESTOES = 15;
    const semAcento = t => (t || '').toString().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const indice = produtos.map(p => ({
        p,
        texto: semAcento([p.codigo_interno, p.descricao, p.codigo_barras, p.sku].filter(Boolean).join(' ')),
        codigos: [p.codigo_interno, p.codigo_barras, p.sku].filter(Boolean).map(c => semAcento(c)),
    }));
    const rotulo = p => (p.codigo_interno ? p.codigo_interno + ' · ' : '') + p.descricao;

    function montarBusca(alvo) {
        const nome = alvo.dataset.name;
        alvo.classList.add('position-relative');
        alvo.innerHTML = `
            <input type="text" class="form-control produto-texto" autocomplete="off"
                   placeholder="Digite nome, código ou código de barras">
            <input type="hidden" class="produto-id" name="${nome}">
            <div class="list-group shadow-sm produto-sugestoes d-none"
                 style="position:absolute; z-index:1050; left:0; right:0; max-height:320px; overflow-y:auto;"></div>
            <div class="form-text produto-dica">Mínimo ${MIN_LETRAS} letras ou números.</div>`;
        const texto = alvo.querySelector('.produto-texto');
        const hid = alvo.querySelector('.produto-id');
        const lista = alvo.querySelector('.produto-sugestoes');
        const dica = alvo.querySelector('.produto-dica');
        let achados = [], ativo = -1;

        // Segurar/arrastar a barra de rolagem da lista tirava o foco do campo e o blur
        // fechava a lista no meio da rolagem. Impedindo o mousedown na lista, o foco fica.
        lista.addEventListener('mousedown', ev => ev.preventDefault());

        function fechar() { lista.classList.add('d-none'); ativo = -1; }
        function escolher(p) {
            hid.value = p.id;
            texto.value = rotulo(p);
            texto.classList.remove('is-invalid');
            dica.textContent = 'Escolhido.';
            fechar();
            const qtd = alvo.closest('.item-row')?.querySelector('input[name$="[quantidade]"]');
            if (qtd) qtd.focus();
        }
        function marcar(i) {
            ativo = i;
            lista.querySelectorAll('.list-group-item').forEach((el, j) => el.classList.toggle('active', j === i));
            lista.querySelectorAll('.list-group-item')[i]?.scrollIntoView({ block: 'nearest' });
        }
        function buscar() {
            hid.value = '';
            const termo = semAcento(texto.value.trim());
            if (termo.length < MIN_LETRAS) {
                fechar();
                dica.textContent = termo.length ? `Digite mais ${MIN_LETRAS - termo.length} para ver sugestões.` : `Mínimo ${MIN_LETRAS} letras ou números.`;
                return;
            }
            const partes = termo.split(/\s+/);
            const exatos = indice.filter(x => x.codigos.includes(termo));
            const resto = indice.filter(x => !x.codigos.includes(termo) && partes.every(t => x.texto.includes(t)));
            const total = exatos.length + resto.length;
            achados = exatos.concat(resto).slice(0, MAX_SUGESTOES).map(x => x.p);
            lista.innerHTML = '';
            if (!achados.length) {
                lista.innerHTML = '<div class="list-group-item text-muted small">Nenhum produto encontrado.</div>';
            } else {
                achados.forEach((p, i) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'list-group-item list-group-item-action py-2';
                    const linha1 = document.createElement('div');
                    linha1.textContent = rotulo(p);
                    b.appendChild(linha1);
                    const extra = [p.codigo_barras ? 'EAN ' + p.codigo_barras : '', p.sku ? 'SKU ' + p.sku : ''].filter(Boolean).join(' · ');
                    if (extra) { const s2 = document.createElement('small'); s2.className = 'text-muted'; s2.textContent = extra; b.appendChild(s2); }
                    b.addEventListener('mousedown', ev => { ev.preventDefault(); escolher(p); });
                    lista.appendChild(b);
                });
                if (total > MAX_SUGESTOES) {
                    const mais = document.createElement('div');
                    mais.className = 'list-group-item text-muted small';
                    mais.textContent = `Mais ${total - MAX_SUGESTOES} — continue digitando para filtrar.`;
                    lista.appendChild(mais);
                }
            }
            dica.textContent = `${total} produto(s).`;
            lista.classList.remove('d-none');
            marcar(achados.length ? 0 : -1);
        }

        texto.addEventListener('input', buscar);
        texto.addEventListener('focus', () => { if (!hid.value && texto.value.trim().length >= MIN_LETRAS) buscar(); });
        texto.addEventListener('blur', () => setTimeout(fechar, 150));
        texto.addEventListener('keydown', ev => {
            const aberta = !lista.classList.contains('d-none');
            if (ev.key === 'ArrowDown' && aberta) { ev.preventDefault(); marcar(Math.min(ativo + 1, achados.length - 1)); }
            else if (ev.key === 'ArrowUp' && aberta) { ev.preventDefault(); marcar(Math.max(ativo - 1, 0)); }
            else if (ev.key === 'Escape') { fechar(); }
            else if (ev.key === 'Enter') {
                // Enter nunca envia o formulário daqui: escolhe o produto (leitor de código de barras manda Enter)
                ev.preventDefault();
                const termo = semAcento(texto.value.trim());
                const exato = indice.find(x => x.codigos.includes(termo));
                if (exato) escolher(exato.p);
                else if (aberta && achados[ativo]) escolher(achados[ativo]);
            }
        });
    }

    document.querySelectorAll('.busca-produto').forEach(montarBusca);

    document.getElementById('form-transferencia')?.addEventListener('submit', function (e) {
        for (const linha of container.querySelectorAll('.item-row')) {
            if (!linha.querySelector('.produto-id').value) {
                e.preventDefault();
                const tx = linha.querySelector('.produto-texto');
                tx.focus();
                tx.classList.add('is-invalid');
                linha.querySelector('.produto-dica').textContent = 'Escolha o produto na lista de sugestões.';
                return;
            }
        }
    });

    document.getElementById('btn-add-item').addEventListener('click', function() {

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
                        <div class="busca-produto" data-name="itens[${itemIndex}][produto_id]"></div>
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
        montarBusca(row.querySelector('.busca-produto'));
        row.querySelector('.produto-texto').focus();
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
