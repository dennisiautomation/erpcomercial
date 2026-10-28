@extends('layouts.app')

@section('title', 'Nova Movimentação de Estoque')

@section('content')
<x-erp.page-header title="Nova Movimentação de Estoque" subtitle="Registre entradas, ajustes, perdas ou bonificacoes — varios itens de uma vez" icon="plus-circle">
    <a href="{{ route('app.movimentacoes.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

<div class="row">
    <div class="col-lg-9">
        <form method="POST" action="{{ route('app.movimentacoes.store') }}" id="form-movimentacao">
            @csrf

            <x-erp.form-section title="Tipo de Movimentação" icon="arrow-left-right">
                <div class="row g-2" id="tipo-cards">
                    <div class="col-6 col-md-3">
                        <input type="radio" name="tipo" value="entrada" id="tipo-entrada" class="btn-check" {{ old('tipo', 'entrada') == 'entrada' ? 'checked' : '' }} required>
                        <label class="btn btn-outline-success w-100 py-3 text-center" for="tipo-entrada">
                            <i class="bi bi-box-arrow-in-down d-block fs-4 mb-1"></i>
                            <span class="small fw-semibold">Entrada</span>
                        </label>
                    </div>
                    <div class="col-6 col-md-3">
                        <input type="radio" name="tipo" value="ajuste" id="tipo-ajuste" class="btn-check" {{ old('tipo') == 'ajuste' ? 'checked' : '' }}>
                        <label class="btn btn-outline-warning w-100 py-3 text-center" for="tipo-ajuste">
                            <i class="bi bi-pencil-square d-block fs-4 mb-1"></i>
                            <span class="small fw-semibold">Ajuste</span>
                        </label>
                    </div>
                    <div class="col-6 col-md-3">
                        <input type="radio" name="tipo" value="perda" id="tipo-perda" class="btn-check" {{ old('tipo') == 'perda' ? 'checked' : '' }}>
                        <label class="btn btn-outline-danger w-100 py-3 text-center" for="tipo-perda">
                            <i class="bi bi-exclamation-triangle d-block fs-4 mb-1"></i>
                            <span class="small fw-semibold">Perda</span>
                        </label>
                    </div>
                    <div class="col-6 col-md-3">
                        <input type="radio" name="tipo" value="bonificacao" id="tipo-bonificacao" class="btn-check" {{ old('tipo') == 'bonificacao' ? 'checked' : '' }}>
                        <label class="btn btn-outline-info w-100 py-3 text-center" for="tipo-bonificacao">
                            <i class="bi bi-gift d-block fs-4 mb-1"></i>
                            <span class="small fw-semibold">Bonificacao</span>
                        </label>
                    </div>
                </div>
                @error('tipo')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </x-erp.form-section>

            {{-- Loja com um estoque só nem vê este bloco — a tela fica igual à de antes --}}
            @if($estoques->count() > 1)
            <x-erp.form-section title="Em qual estoque" icon="boxes">
                <div class="row g-3">
                    <div class="col-md-6">
                        <select name="estoque_id" class="form-select @error('estoque_id') is-invalid @enderror" required>
                            @foreach($estoques as $e)
                                <option value="{{ $e->id }}"
                                    {{ (int) old('estoque_id', $estoques->firstWhere('is_padrao', true)?->id) === $e->id ? 'selected' : '' }}>
                                    {{ $e->nome }}{{ $e->is_padrao ? ' (padrão)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('estoque_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 d-flex align-items-center">
                        <small class="text-muted">
                            Vale para todos os itens. Para mover peça entre estoques, use
                            <a href="{{ route('app.transferencias.create') }}">Transferência</a>.
                        </small>
                    </div>
                </div>
            </x-erp.form-section>
            @endif

            <x-erp.form-section title="Itens da Movimentação" icon="box-seam">
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
                                    {{-- Busca enquanto digita (25/10/2026): a lista com todos os produtos
                                         abria inteira e a DK tem centenas. Montada pelo JS (montarBusca). --}}
                                    <div class="busca-produto" data-name="itens[0][produto_id]"></div>
                                </div>
                                <div class="col-md-2">
                                    <label for="campo-itens-0-quantidade" class="form-label small fw-semibold text-muted">Quantidade</label>
                                    <input id="campo-itens-0-quantidade" type="number" name="itens[0][quantidade]" class="form-control" step="0.001" min="0.001" placeholder="0,000" required>
                                </div>
                                <div class="col-md-2">
                                    <label for="campo-itens-0-custo_unitario" class="form-label small fw-semibold text-muted">Custo Unit. (R$)</label>
                                    <input id="campo-itens-0-custo_unitario" type="number" name="itens[0][custo_unitario]" class="form-control" step="0.01" min="0" placeholder="0,00">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-danger btn-sm btn-remove-item" disabled>
                                        <i class="bi bi-trash me-1"></i> Remover
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </x-erp.form-section>

            {{-- Só aparece em Bonificação; dentro dela, os campos só abrem se marcar
                 que a peça volta. Nas outras movimentações a tela fica igual à de antes. --}}
            <div id="bloco-comodato" class="d-none">
                <x-erp.form-section title="A peça volta?" icon="arrow-counterclockwise">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="retorno_previsto" value="1" id="retorno_previsto"
                               {{ old('retorno_previsto') ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="retorno_previsto">
                            Esta peça deve retornar
                        </label>
                        <div class="form-text">
                            Marque quando a peça sai emprestada — influencer, editorial, showroom, prova.
                            O estoque baixa igual, mas a peça entra em <strong>Peças em poder de terceiros</strong>
                            até voltar.
                        </div>
                    </div>

                    <div id="campos-comodato" class="row g-3 d-none">
                        <div class="col-md-5">
                            <label for="responsavel" class="form-label fw-semibold">
                                Com quem fica <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="responsavel" id="responsavel"
                                   class="form-control @error('responsavel') is-invalid @enderror"
                                   value="{{ old('responsavel') }}" maxlength="120"
                                   placeholder="Nome da influencer, produtora, cliente...">
                            @error('responsavel')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="contato" class="form-label fw-semibold">Contato</label>
                            <input type="text" name="contato" id="contato"
                                   class="form-control @error('contato') is-invalid @enderror"
                                   value="{{ old('contato') }}" maxlength="120"
                                   placeholder="@ do Instagram ou telefone">
                            @error('contato')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label for="data_prevista_retorno" class="form-label fw-semibold">
                                Volta em <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="data_prevista_retorno" id="data_prevista_retorno"
                                   class="form-control @error('data_prevista_retorno') is-invalid @enderror"
                                   value="{{ old('data_prevista_retorno') }}"
                                   min="{{ now()->toDateString() }}">
                            @error('data_prevista_retorno')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info mb-0 py-2 small">
                                <i class="bi bi-info-circle me-1"></i>
                                Vale para <strong>todos os itens</strong> desta movimentação. Passada a data,
                                a peça aparece como atrasada e o dono recebe aviso no sino.
                            </div>
                        </div>
                    </div>
                </x-erp.form-section>
            </div>

            <x-erp.form-section title="Observações" icon="chat-text">
                <div class="mb-4">
                    <label for="observacoes" class="form-label fw-semibold">Observações</label>
                    <textarea name="observacoes" id="observacoes" rows="3"
                        class="form-control @error('observacoes') is-invalid @enderror"
                        placeholder="Motivo da movimentação, nota do fornecedor, referência, etc.">{{ old('observacoes') }}</textarea>
                    @error('observacoes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Máximo 500 caracteres — vale para todos os itens</div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-erp-primary btn-lg px-4">
                        <i class="bi bi-check-lg me-1"></i> Registrar Movimentação
                    </button>
                    <a href="{{ route('app.movimentacoes.index') }}" class="btn btn-erp-outline btn-lg">Cancelar</a>
                </div>
            </x-erp.form-section>
        </form>
    </div>

    {{-- Side help --}}
    <div class="col-lg-3">
        <x-erp.card title="Tipos de Movimentação" icon="question-circle">
            <div class="mb-3">
                <span class="badge bg-success rounded-pill me-1">Entrada</span>
                <small class="text-muted">Adiciona produtos ao estoque (compras, recebimentos)</small>
            </div>
            <div class="mb-3">
                <span class="badge bg-warning text-dark rounded-pill me-1">Ajuste</span>
                <small class="text-muted">Correcao de inventario (adiciona ao estoque)</small>
            </div>
            <div class="mb-3">
                <span class="badge bg-danger rounded-pill me-1">Perda</span>
                <small class="text-muted">Produtos danificados, vencidos ou extraviados</small>
            </div>
            <div class="mb-3">
                <span class="badge bg-info text-dark rounded-pill me-1">Bonificacao</span>
                <small class="text-muted">Saída para brindes ou amostras</small>
            </div>
            <hr>
            <small class="text-muted">Adicione quantos itens precisar — todos entram de uma vez, no mesmo tipo de movimentação.</small>
        </x-erp.card>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let itemIndex = 1;
    const container = document.getElementById('itens-container');
    // json_encode, não a diretiva json do Blade: ela separa o argumento nas vírgulas e quebra com a lista de campos
    const produtos = {!! json_encode($produtos->map->only(['id', 'descricao', 'codigo_interno', 'codigo_barras', 'sku'])->values(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!};

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

    // Rede de proteção: linha sem produto ESCOLHIDO (só digitado) não vai
    document.getElementById('form-movimentacao')?.addEventListener('submit', function (e) {
        for (const linha of container.querySelectorAll('.item-row')) {
            if (!linha.querySelector('.produto-id').value) {
                e.preventDefault();
                const t = linha.querySelector('.produto-texto');
                t.focus(); // antes da mensagem: o foco refaz a busca e reescreveria a dica
                t.classList.add('is-invalid');
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
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-muted">Quantidade</label>
                        <input type="number" name="itens[${itemIndex}][quantidade]" class="form-control" step="0.001" min="0.001" placeholder="0,000" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-muted">Custo Unit. (R$)</label>
                        <input type="number" name="itens[${itemIndex}][custo_unitario]" class="form-control" step="0.01" min="0" placeholder="0,00">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
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

    /* --- Bonificação que deve voltar --------------------------------- */
    const blocoComodato = document.getElementById('bloco-comodato');
    const switchRetorno = document.getElementById('retorno_previsto');
    const camposComodato = document.getElementById('campos-comodato');

    function syncBlocoComodato() {
        const ehBonificacao = document.querySelector('input[name="tipo"]:checked')?.value === 'bonificacao';
        blocoComodato.classList.toggle('d-none', !ehBonificacao);

        // Saiu de bonificação: desliga o switch para não mandar comodato órfão
        if (!ehBonificacao && switchRetorno.checked) {
            switchRetorno.checked = false;
        }
        syncCamposComodato();
    }

    function syncCamposComodato() {
        const ligado = switchRetorno.checked;
        camposComodato.classList.toggle('d-none', !ligado);
        // required só quando visível, senão o browser bloqueia o submit num campo escondido
        document.getElementById('responsavel').required = ligado;
        document.getElementById('data_prevista_retorno').required = ligado;
    }

    document.getElementById('tipo-cards').addEventListener('change', syncBlocoComodato);
    switchRetorno.addEventListener('change', syncCamposComodato);
    syncBlocoComodato();
</script>
@endpush
