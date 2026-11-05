@extends('layouts.app')

@section('title', 'Nova promoção')

@php
    // Volta do servidor com erro: o que a pessoa já tinha montado (old) vale mais que o inicial
    $estado = [
        'nome' => old('nome', $inicial['nome']),
        'data_inicio' => old('data_inicio', $inicial['data_inicio']),
        'data_fim' => old('data_fim', $inicial['data_fim']),
        'unidades' => array_map('intval', old('unidades', $inicial['unidades'])),
        'itens' => array_values(old('itens', $inicial['itens'])),
        'origem_id' => old('origem_id', $inicial['origem_id']),
    ];
    $passoComErro = $errors->hasAny(['nome', 'data_inicio', 'data_fim']) ? 1
        : ($errors->hasAny(['unidades', 'unidades.*']) ? 2
        : ($errors->any() ? 4 : 1));
@endphp

@push('styles')
<style>
    .promo-passos { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .promo-passos button { border: 1px solid var(--bs-border-color); background: var(--bs-body-bg); border-radius: 999px;
        padding: .35rem .9rem; font-size: .875rem; color: var(--bs-secondary-color); }
    .promo-passos button.ativo { background: var(--bs-primary); border-color: var(--bs-primary); color: #fff; }
    .promo-passos button.feito { color: var(--bs-body-color); }
    .promo-passo { display: none; }
    .promo-passo.ativo { display: block; }
    .promo-resultados { max-height: 320px; overflow: auto; }
    .promo-resultados .list-group-item { display: flex; justify-content: space-between; align-items: center; gap: .75rem; }
    .promo-lojas label { display: flex; gap: .5rem; align-items: center; padding: .6rem .8rem; border: 1px solid var(--bs-border-color);
        border-radius: .5rem; cursor: pointer; }
    /* Passo 4 (05/11/2026): em tela de notebook os seletores cortavam ("% descont…", "Regra da lo…") e os
       preços quebravam em duas linhas ("R$" em cima). Larguras mínimas + preço sem quebra; o que não couber
       rola de lado dentro da tabela (.table-responsive), nunca a página. */
    .promo-precos table { min-width: 1020px; }
    .promo-precos.table-responsive .erp-table thead th,
    .promo-precos.table-responsive .erp-table tbody td { padding: .55rem .5rem; vertical-align: middle; }
    .promo-precos td:first-child { min-width: 150px; }
    .promo-precos select.form-select-sm { min-width: 128px; padding-right: 1.75rem; }
    .promo-precos input.form-control-sm { min-width: 78px; max-width: 96px; }
    .promo-precos .text-end { white-space: nowrap; }
    .promo-de { text-decoration: line-through; color: var(--bs-secondary-color); }
</style>
@endpush

@section('content')
<div class="fade-in">
<div class="page-header">
    <div>
        <h4><i class="bi bi-megaphone me-2"></i>{{ $base ? 'Duplicar promoção' : 'Nova promoção' }}</h4>
        <div class="subtitle">
            @if($base)
                Começando com os produtos e preços de <strong>{{ $base->nome }}</strong>. Escolha o novo período e confira.
            @else
                Período, lojas, produtos e preços à vista e no cartão de cada produto.
            @endif
        </div>
    </div>
    <a href="{{ route('app.promocoes.index') }}" class="btn btn-erp btn-erp-outline"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
</div>

{{-- Os erros do servidor (inclusive a sobreposição) aparecem no aviso do layout, no topo --}}

<div class="promo-passos" id="promo-passos">
    <button type="button" data-ir="1">1. Período</button>
    <button type="button" data-ir="2">2. Lojas</button>
    <button type="button" data-ir="3">3. Produtos</button>
    <button type="button" data-ir="4">4. Preços</button>
    <button type="button" data-ir="5">5. Revisar e salvar</button>
</div>

<form method="POST" action="{{ route('app.promocoes.store') }}" id="promo-form" class="erp-form" novalidate>
    @csrf
    <div id="promo-campos"></div>

    {{-- 1. Período --}}
    <div class="erp-card promo-passo" data-passo="1">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="promo-nome" class="form-label">Nome da promoção</label>
                    <input type="text" id="promo-nome" class="form-control" maxlength="120" placeholder="Ex.: Black Friday, Semana do Biquíni">
                </div>
                <div class="col-md-3">
                    <label for="promo-inicio" class="form-label">Começa em</label>
                    <input type="date" id="promo-inicio" class="form-control" min="{{ now()->toDateString() }}">
                </div>
                <div class="col-md-3">
                    <label for="promo-fim" class="form-label">Termina em (inclusive)</label>
                    <input type="date" id="promo-fim" class="form-control" min="{{ now()->toDateString() }}">
                </div>
            </div>
            <div class="form-text mt-2">A promoção vale o dia inteiro, do primeiro ao último dia. Dá para encerrar antes pela tela da promoção.</div>
        </div>
    </div>

    {{-- 2. Lojas --}}
    <div class="erp-card promo-passo" data-passo="2">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>Em quais lojas a promoção vale?</strong>
                <button type="button" class="btn btn-sm btn-erp btn-erp-outline" id="promo-todas-lojas">Marcar todas</button>
            </div>
            <div class="row g-2 promo-lojas">
                @forelse($lojas as $loja)
                    <div class="col-md-4">
                        <label><input type="checkbox" class="form-check-input m-0 promo-loja" value="{{ $loja->id }}"> {{ $loja->nome }}</label>
                    </div>
                @empty
                    <div class="text-muted">Você não tem loja ativa vinculada.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- 3. Produtos --}}
    <div class="erp-card promo-passo" data-passo="3">
        <div class="card-body">
            @if($ultima && $ultima->itens_count > 0)
                <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2" id="promo-sugestao">
                    <span><i class="bi bi-lightbulb me-1"></i> Usar a lista da última promoção, <strong>{{ $ultima->nome }}</strong>
                        ({{ $ultima->itens_count }} produto{{ $ultima->itens_count > 1 ? 's' : '' }}, com os preços)?</span>
                    <button type="button" class="btn btn-sm btn-erp btn-erp-primary" id="promo-usar-ultima"
                            data-url="{{ route('app.promocoes.itens', $ultima) }}">Usar esta lista</button>
                </div>
            @endif
            <label for="promo-busca" class="form-label">Buscar produto (nome em qualquer ordem, código de barras ou código interno)</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="promo-busca" class="form-control" autocomplete="off" placeholder="Ex.: biquini estampado">
                <button type="button" class="btn btn-erp btn-erp-outline" id="promo-add-todos" disabled>Adicionar os resultados</button>
            </div>
            <div class="mt-2">
                <button type="button" class="btn btn-sm btn-erp btn-erp-primary" id="promo-add-catalogo" data-url="{{ route('app.promocoes.todos-produtos') }}">
                    <i class="bi bi-collection me-1"></i> Adicionar todos os produtos da loja
                </button>
                <span class="form-text ms-2">Produto sem preço (R$ 0,00) fica de fora.</span>
            </div>
            <div class="list-group promo-resultados mt-2" id="promo-resultados"></div>

            <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
                <strong>Produtos na promoção: <span id="promo-qtd">0</span></strong>
                <button type="button" class="btn btn-sm btn-link text-danger" id="promo-limpar">Tirar todos</button>
            </div>
            <div class="table-responsive">
                <table class="erp-table">
                    <thead><tr><th>Produto</th><th>Código</th><th class="text-end">À vista</th><th class="text-end">Cartão</th><th style="width:60px"></th></tr></thead>
                    <tbody id="promo-lista"></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 4. Preços --}}
    <div class="erp-card promo-passo" data-passo="4">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-end gap-3 mb-3">
                <div>
                    <label for="promo-pct-todos" class="form-label mb-1">À vista: mesmo % para todos</label>
                    <div class="input-group input-group-sm" style="width: 190px;">
                        <input type="number" id="promo-pct-todos" class="form-control" min="0.01" max="99.99" step="0.01" placeholder="Ex.: 20">
                        <span class="input-group-text">%</span>
                        <button type="button" class="btn btn-erp btn-erp-primary" id="promo-aplicar-pct">Aplicar</button>
                    </div>
                </div>
                <div>
                    <label for="promo-pct-cartao-todos" class="form-label mb-1">Cartão: mesmo % para todos</label>
                    <div class="input-group input-group-sm" style="width: 190px;">
                        <input type="number" id="promo-pct-cartao-todos" class="form-control" min="0.01" max="99.99" step="0.01" placeholder="Ex.: 10">
                        <span class="input-group-text">%</span>
                        <button type="button" class="btn btn-erp btn-erp-primary" id="promo-aplicar-pct-cartao">Aplicar</button>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-erp btn-erp-outline" id="promo-cartao-regra">Cartão pela regra da loja em todos</button>
                <div class="form-text w-100">Em cada produto: o preço à vista (R$ ou %) e o do cartão (R$, % sobre o preço normal do cartão, ou a regra da loja — o acréscimo do cartão sobre o à vista da promoção).</div>
            </div>
            <div class="table-responsive promo-precos">
                <table class="erp-table">
                    <thead>
                        <tr><th rowspan="2">Produto</th><th colspan="4" class="text-center border-bottom">À vista (Dinheiro/PIX)</th><th colspan="4" class="text-center border-bottom">Cartão</th></tr>
                        <tr><th class="text-end">Normal</th><th>Como</th><th>Valor</th><th class="text-end">Fica por</th><th class="text-end">Normal</th><th>Como</th><th>Valor</th><th class="text-end">Fica por</th></tr>
                    </thead>
                    <tbody id="promo-tabela-precos"></tbody>
                </table>
            </div>
            <div class="form-text">Preços normais desta loja. "Regra da loja" no cartão: cada loja soma o acréscimo do cartão sobre o à vista da promoção.</div>
        </div>
    </div>

    {{-- 5. Revisão --}}
    <div class="erp-card promo-passo" data-passo="5">
        <div class="card-body" id="promo-revisao"></div>
    </div>

    <div class="d-flex justify-content-between mt-3">
        <button type="button" class="btn btn-erp btn-erp-outline" id="promo-voltar"><i class="bi bi-arrow-left me-1"></i> Voltar</button>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-erp btn-erp-primary" id="promo-avancar">Avançar <i class="bi bi-arrow-right ms-1"></i></button>
            <button type="submit" class="btn btn-erp btn-erp-primary d-none" id="promo-salvar"><i class="bi bi-check-lg me-1"></i> Salvar promoção</button>
        </div>
    </div>
</form>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const estado = @json($estado);
    const lojas = @json($lojas->map(fn ($l) => ['id' => $l->id, 'nome' => $l->nome])->values());
    const urlBusca = @json(route('app.promocoes.buscar-produtos'));
    let passo = {{ $passoComErro }};
    let resultados = [];

    estado.itens = (estado.itens || []).map(i => ({
        produto_id: Number(i.produto_id), descricao: i.descricao || ('Produto ' + i.produto_id), codigo: i.codigo || '',
        preco_referencia: Number(i.preco_referencia || 0), tipo: i.tipo || 'percentual',
        valor: i.valor === undefined || i.valor === null || i.valor === '' ? '' : Number(i.valor),
        preco_cartao_referencia: Number(i.preco_cartao_referencia || i.preco_referencia || 0),
        cartao_tipo: i.cartao_tipo || '',
        cartao_valor: i.cartao_valor === undefined || i.cartao_valor === null || i.cartao_valor === '' ? '' : Number(i.cartao_valor),
    }));
    estado.unidades = (estado.unidades || []).map(Number);

    const $ = s => document.querySelector(s);
    const brl = v => 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const dataBr = d => d ? d.split('-').reverse().join('/') : '—';

    function precoFinal(i) {
        const v = Number(i.valor);
        if (!(v > 0)) return null;
        if (i.tipo === 'percentual') return Math.round(i.preco_referencia * (1 - Math.min(v, 100) / 100) * 100) / 100;
        return Math.round(v * 100) / 100;
    }

    // Cartão: null = regra da loja (não dá para saber aqui: cada loja tem a sua)
    function cartaoFinal(i) {
        if (!i.cartao_tipo) return null;
        const v = Number(i.cartao_valor);
        if (!(v > 0)) return undefined;
        if (i.cartao_tipo === 'percentual') return Math.round(i.preco_cartao_referencia * (1 - Math.min(v, 100) / 100) * 100) / 100;
        return Math.round(v * 100) / 100;
    }

    function problemasDoPasso(n) {
        const p = [];
        if (n === 1) {
            if (!estado.nome.trim()) p.push('Dê um nome à promoção.');
            if (!estado.data_inicio || !estado.data_fim) p.push('Escolha o primeiro e o último dia.');
            else if (estado.data_fim < estado.data_inicio) p.push('O último dia não pode ser antes do primeiro.');
        }
        if (n === 2 && !estado.unidades.length) p.push('Escolha pelo menos uma loja.');
        if (n === 3 && !estado.itens.length) p.push('Adicione pelo menos um produto.');
        if (n === 4) {
            estado.itens.forEach(i => {
                const f = precoFinal(i);
                if (f === null) p.push(i.descricao + ': falta o preço ou o %.');
                else if (i.tipo === 'percentual' && Number(i.valor) >= 100) p.push(i.descricao + ': o desconto tem que ser menor que 100%.');
                else if (i.preco_referencia > 0 && f >= i.preco_referencia) p.push(i.descricao + ': o preço da promoção não é menor que o normal.');
                const c = cartaoFinal(i);
                if (c === undefined) p.push(i.descricao + ': falta o valor do cartão (ou escolha "Regra da loja").');
                else if (c !== null && i.cartao_tipo === 'percentual' && Number(i.cartao_valor) >= 100) p.push(i.descricao + ': o desconto do cartão tem que ser menor que 100%.');
                else if (c !== null && i.preco_cartao_referencia > 0 && c >= i.preco_cartao_referencia) p.push(i.descricao + ': o preço do cartão não é menor que o cartão normal.');
            });
        }
        return p;
    }

    function irPara(n) {
        // Só avança passando pelos anteriores sem pendência
        for (let k = 1; k < n; k++) {
            const p = problemasDoPasso(k);
            if (p.length) { alert(p.slice(0, 8).join('\n') + (p.length > 8 ? '\n…' : '')); n = k; break; }
        }
        passo = n;
        render();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function renderLista() {
        $('#promo-qtd').textContent = estado.itens.length;
        $('#promo-lista').innerHTML = estado.itens.length ? estado.itens.map((i, k) => `
            <tr><td>${esc(i.descricao)}</td><td class="text-muted">${esc(i.codigo)}</td>
            <td class="text-end">${brl(i.preco_referencia)}</td><td class="text-end">${brl(i.preco_cartao_referencia)}</td>
            <td class="text-end"><button type="button" class="btn btn-sm btn-link text-danger p-0" data-tirar="${k}" title="Tirar"><i class="bi bi-x-lg"></i></button></td></tr>`).join('')
            : '<tr><td colspan="5" class="text-center text-muted py-4">Nenhum produto ainda. Busque acima ou adicione todos.</td></tr>';
    }

    function celFinal(v) { return v === null ? '<span class="text-muted small">regra da loja</span>' : (v === undefined ? '—' : '<strong>' + brl(v) + '</strong>'); }

    function renderPrecos() {
        $('#promo-tabela-precos').innerHTML = estado.itens.map((i, k) => {
            const f = precoFinal(i);
            return `<tr>
                <td>${esc(i.descricao)}</td>
                <td class="text-end">${brl(i.preco_referencia)}</td>
                <td><select class="form-select form-select-sm" data-tipo="${k}">
                    <option value="percentual" ${i.tipo === 'percentual' ? 'selected' : ''}>% desconto</option>
                    <option value="preco" ${i.tipo === 'preco' ? 'selected' : ''}>R$</option></select></td>
                <td><input type="number" class="form-control form-control-sm" data-valor="${k}" min="0.01" step="0.01" value="${i.valor === '' ? '' : i.valor}"
                    placeholder="${i.tipo === 'percentual' ? '%' : 'R$'}"></td>
                <td class="text-end text-nowrap" data-final="${k}">${f === null ? '—' : '<strong>' + brl(f) + '</strong>'}</td>
                <td class="text-end">${brl(i.preco_cartao_referencia)}</td>
                <td><select class="form-select form-select-sm" data-cartao-tipo="${k}">
                    <option value="" ${!i.cartao_tipo ? 'selected' : ''}>Regra da loja</option>
                    <option value="percentual" ${i.cartao_tipo === 'percentual' ? 'selected' : ''}>% desconto</option>
                    <option value="preco" ${i.cartao_tipo === 'preco' ? 'selected' : ''}>R$</option></select></td>
                <td><input type="number" class="form-control form-control-sm" data-cartao-valor="${k}" min="0.01" step="0.01" value="${i.cartao_valor === '' ? '' : i.cartao_valor}"
                    placeholder="${i.cartao_tipo === 'percentual' ? '%' : (i.cartao_tipo ? 'R$' : '')}" ${i.cartao_tipo ? '' : 'disabled'}></td>
                <td class="text-end text-nowrap" data-cartao-final="${k}">${celFinal(cartaoFinal(i))}</td></tr>`;
        }).join('');
    }

    function renderRevisao() {
        const nomesLojas = lojas.filter(l => estado.unidades.includes(l.id)).map(l => esc(l.nome)).join(', ');
        $('#promo-revisao').innerHTML = `
            <h5 class="mb-1">${esc(estado.nome)}</h5>
            <div class="text-muted mb-3">${dataBr(estado.data_inicio)} a ${dataBr(estado.data_fim)} · ${nomesLojas}</div>
            <div class="table-responsive"><table class="erp-table"><thead><tr><th>Produto</th><th class="text-end">À vista: de</th><th class="text-end">por</th><th class="text-end">Cartão: de</th><th class="text-end">por</th></tr></thead><tbody>
            ${estado.itens.map(i => `<tr><td>${esc(i.descricao)}</td><td class="text-end promo-de">${brl(i.preco_referencia)}</td>
                <td class="text-end"><strong>${brl(precoFinal(i))}</strong>${i.tipo === 'percentual' ? ' <span class="text-muted small">(' + Number(i.valor).toLocaleString('pt-BR') + '%)</span>' : ''}</td>
                <td class="text-end promo-de">${brl(i.preco_cartao_referencia)}</td>
                <td class="text-end">${celFinal(cartaoFinal(i))}${i.cartao_tipo === 'percentual' ? ' <span class="text-muted small">(' + Number(i.cartao_valor).toLocaleString('pt-BR') + '%)</span>' : ''}</td></tr>`).join('')}
            </tbody></table></div>
            <div class="form-text mt-2">"Regra da loja" no cartão: cada loja soma o acréscimo dela sobre o à vista da promoção. Um produto não pode estar em duas promoções na mesma loja no mesmo dia: se estiver, o sistema avisa ao salvar.</div>`;
    }

    function render() {
        document.querySelectorAll('.promo-passo').forEach(el => el.classList.toggle('ativo', Number(el.dataset.passo) === passo));
        document.querySelectorAll('#promo-passos button').forEach(b => {
            const n = Number(b.dataset.ir);
            b.classList.toggle('ativo', n === passo);
            b.classList.toggle('feito', n < passo);
        });
        $('#promo-voltar').style.visibility = passo === 1 ? 'hidden' : 'visible';
        $('#promo-avancar').classList.toggle('d-none', passo === 5);
        $('#promo-salvar').classList.toggle('d-none', passo !== 5);
        if (passo === 3) renderLista();
        if (passo === 4) renderPrecos();
        if (passo === 5) renderRevisao();
    }

    function adicionar(lista) {
        let novos = 0;
        lista.forEach(p => {
            if (!(Number(p.preco_referencia) > 0)) return; // sem preço não entra em promoção
            const id = Number(p.produto_id);
            const existente = estado.itens.find(i => i.produto_id === id);
            if (existente) {
                if (p.valor !== undefined) { existente.tipo = p.tipo; existente.valor = Number(p.valor); }
                if (p.cartao_tipo !== undefined) { existente.cartao_tipo = p.cartao_tipo || ''; existente.cartao_valor = p.cartao_valor === '' ? '' : Number(p.cartao_valor); }
                return;
            }
            estado.itens.push({
                produto_id: id, descricao: p.descricao, codigo: p.codigo || '', preco_referencia: Number(p.preco_referencia || 0),
                tipo: p.tipo || 'percentual', valor: p.valor === undefined ? '' : Number(p.valor),
                preco_cartao_referencia: Number(p.preco_cartao_referencia || p.preco_referencia || 0),
                cartao_tipo: p.cartao_tipo || '', cartao_valor: p.cartao_valor === undefined || p.cartao_valor === '' ? '' : Number(p.cartao_valor),
            });
            novos++;
        });
        renderLista();
        renderResultados();
        return novos;
    }

    function renderResultados() {
        const ids = new Set(estado.itens.map(i => i.produto_id));
        $('#promo-add-todos').disabled = !resultados.some(r => !ids.has(Number(r.produto_id)) && Number(r.preco_referencia) > 0);
        $('#promo-resultados').innerHTML = resultados.map((r, k) => {
            const ja = ids.has(Number(r.produto_id));
            const semPreco = !(Number(r.preco_referencia) > 0);
            return `<div class="list-group-item"><span>${esc(r.descricao)} <span class="text-muted small">${esc(r.codigo || '')}</span></span>
                <span class="text-nowrap">${brl(r.preco_referencia)}
                ${ja ? '<span class="badge text-bg-light ms-2">na lista</span>' : (semPreco ? '<span class="badge text-bg-light ms-2">sem preço</span>' : `<button type="button" class="btn btn-sm btn-erp btn-erp-primary ms-2" data-add="${k}">Adicionar</button>`)}</span></div>`;
        }).join('');
    }

    // ---- eventos ----
    $('#promo-nome').value = estado.nome || '';
    $('#promo-inicio').value = estado.data_inicio || '';
    $('#promo-fim').value = estado.data_fim || '';
    $('#promo-nome').addEventListener('input', e => estado.nome = e.target.value);
    $('#promo-inicio').addEventListener('change', e => {
        estado.data_inicio = e.target.value;
        $('#promo-fim').min = e.target.value || $('#promo-fim').min;
        if (!estado.data_fim) { estado.data_fim = e.target.value; $('#promo-fim').value = e.target.value; }
    });
    $('#promo-fim').addEventListener('change', e => estado.data_fim = e.target.value);

    document.querySelectorAll('.promo-loja').forEach(cb => {
        cb.checked = estado.unidades.includes(Number(cb.value));
        cb.addEventListener('change', () => {
            estado.unidades = [...document.querySelectorAll('.promo-loja:checked')].map(c => Number(c.value));
        });
    });
    $('#promo-todas-lojas')?.addEventListener('click', () => {
        const marcar = document.querySelectorAll('.promo-loja:not(:checked)').length > 0;
        document.querySelectorAll('.promo-loja').forEach(c => c.checked = marcar);
        estado.unidades = marcar ? lojas.map(l => l.id) : [];
    });

    let espera = null, seq = 0;
    $('#promo-busca').addEventListener('input', e => {
        clearTimeout(espera);
        const termo = e.target.value.trim();
        if (termo.length < 2) { resultados = []; renderResultados(); return; }
        espera = setTimeout(async () => {
            const meu = ++seq;
            try {
                const r = await fetch(urlBusca + '?q=' + encodeURIComponent(termo), { headers: { Accept: 'application/json' } });
                if (!r.ok) throw new Error(r.status === 419 || r.status === 401 ? 'Sua sessão expirou. Entre de novo.' : 'Erro ' + r.status);
                const dados = await r.json();
                if (meu !== seq) return;
                resultados = dados;
                renderResultados();
                if (!dados.length) $('#promo-resultados').innerHTML = '<div class="list-group-item text-muted">Nenhum produto encontrado.</div>';
            } catch (err) {
                $('#promo-resultados').innerHTML = `<div class="list-group-item text-danger">Não deu para buscar: ${esc(err.message)}</div>`;
            }
        }, 300);
    });
    $('#promo-resultados').addEventListener('click', e => {
        const b = e.target.closest('[data-add]');
        if (b) adicionar([resultados[Number(b.dataset.add)]]);
    });
    $('#promo-add-todos').addEventListener('click', () => adicionar(resultados));
    $('#promo-add-catalogo').addEventListener('click', async e => {
        const btn = e.currentTarget;
        btn.disabled = true;
        try {
            const r = await fetch(btn.dataset.url, { headers: { Accept: 'application/json' } });
            if (!r.ok) throw new Error(r.status === 419 || r.status === 401 ? 'Sua sessão expirou. Entre de novo.' : 'Erro ' + r.status);
            const dados = await r.json();
            const novos = adicionar(dados.itens);
            alert(`${novos} produto(s) adicionados.` + (dados.sem_preco ? ` ${dados.sem_preco} sem preço (R$ 0,00) ficaram de fora.` : ''));
        } catch (err) {
            alert('Não deu para trazer os produtos: ' + err.message);
        } finally {
            btn.disabled = false;
        }
    });
    $('#promo-lista').addEventListener('click', e => {
        const b = e.target.closest('[data-tirar]');
        if (b) { estado.itens.splice(Number(b.dataset.tirar), 1); renderLista(); renderResultados(); }
    });
    $('#promo-limpar').addEventListener('click', () => {
        if (estado.itens.length && confirm('Tirar todos os produtos da lista?')) { estado.itens = []; renderLista(); renderResultados(); }
    });
    $('#promo-usar-ultima')?.addEventListener('click', async e => {
        const btn = e.currentTarget;
        btn.disabled = true;
        try {
            const r = await fetch(btn.dataset.url, { headers: { Accept: 'application/json' } });
            if (!r.ok) throw new Error('Erro ' + r.status);
            const dados = await r.json();
            const novos = adicionar(dados.itens);
            $('#promo-sugestao').outerHTML = `<div class="alert alert-success">${novos} produto(s) de <strong>${esc(dados.nome)}</strong> adicionados, com os preços de lá. Confira no passo 4.</div>`;
        } catch (err) {
            btn.disabled = false;
            alert('Não deu para trazer a lista: ' + err.message);
        }
    });

    $('#promo-tabela-precos').addEventListener('input', e => {
        const kc = e.target.dataset.cartaoValor;
        if (kc !== undefined) {
            estado.itens[kc].cartao_valor = e.target.value === '' ? '' : Number(e.target.value);
            document.querySelector(`[data-cartao-final="${kc}"]`).innerHTML = celFinal(cartaoFinal(estado.itens[kc]));
            return;
        }
        const k = e.target.dataset.valor;
        if (k === undefined) return;
        estado.itens[k].valor = e.target.value === '' ? '' : Number(e.target.value);
        const f = precoFinal(estado.itens[k]);
        document.querySelector(`[data-final="${k}"]`).innerHTML = f === null ? '—' : '<strong>' + brl(f) + '</strong>';
    });
    $('#promo-tabela-precos').addEventListener('change', e => {
        const kc = e.target.dataset.cartaoTipo;
        if (kc !== undefined) {
            estado.itens[kc].cartao_tipo = e.target.value;
            estado.itens[kc].cartao_valor = '';
            renderPrecos();
            return;
        }
        const k = e.target.dataset.tipo;
        if (k === undefined) return;
        estado.itens[k].tipo = e.target.value;
        estado.itens[k].valor = '';
        renderPrecos();
    });
    $('#promo-aplicar-pct').addEventListener('click', () => {
        const v = Number($('#promo-pct-todos').value);
        if (!(v > 0 && v < 100)) { alert('Informe um % entre 0,01 e 99,99.'); return; }
        estado.itens.forEach(i => { i.tipo = 'percentual'; i.valor = v; });
        renderPrecos();
    });
    $('#promo-aplicar-pct-cartao').addEventListener('click', () => {
        const v = Number($('#promo-pct-cartao-todos').value);
        if (!(v > 0 && v < 100)) { alert('Informe um % entre 0,01 e 99,99.'); return; }
        estado.itens.forEach(i => { i.cartao_tipo = 'percentual'; i.cartao_valor = v; });
        renderPrecos();
    });
    $('#promo-cartao-regra').addEventListener('click', () => {
        estado.itens.forEach(i => { i.cartao_tipo = ''; i.cartao_valor = ''; });
        renderPrecos();
    });

    document.querySelectorAll('#promo-passos button').forEach(b => b.addEventListener('click', () => irPara(Number(b.dataset.ir))));
    $('#promo-avancar').addEventListener('click', () => {
        const p = problemasDoPasso(passo);
        if (p.length) { alert(p.slice(0, 8).join('\n') + (p.length > 8 ? '\n…' : '')); return; }
        irPara(passo + 1);
    });
    $('#promo-voltar').addEventListener('click', () => { passo = Math.max(1, passo - 1); render(); });

    $('#promo-form').addEventListener('submit', e => {
        for (let k = 1; k <= 4; k++) {
            const p = problemasDoPasso(k);
            if (p.length) { e.preventDefault(); alert(p.slice(0, 8).join('\n')); passo = k; render(); return; }
        }
        const campos = [['nome', estado.nome], ['data_inicio', estado.data_inicio], ['data_fim', estado.data_fim], ['origem_id', estado.origem_id || '']];
        estado.unidades.forEach(u => campos.push(['unidades[]', u]));
        estado.itens.forEach((i, k) => {
            ['produto_id', 'tipo', 'valor', 'descricao', 'codigo', 'preco_referencia', 'preco_cartao_referencia', 'cartao_tipo', 'cartao_valor']
                .forEach(c => campos.push([`itens[${k}][${c}]`, i[c]]));
        });
        $('#promo-campos').innerHTML = '';
        campos.forEach(([n, v]) => {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = n; inp.value = v ?? '';
            $('#promo-campos').appendChild(inp);
        });
        $('#promo-salvar').disabled = true;
    });

    render();
})();
</script>
@endpush
