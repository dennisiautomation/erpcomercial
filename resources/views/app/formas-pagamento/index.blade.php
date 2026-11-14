@extends('layouts.app')

@section('title', 'Formas de pagamento')

@push('styles')
<style>
    .fp-layout { display: grid; grid-template-columns: minmax(300px, 380px) 1fr; gap: 24px; align-items: start; }
    @media (max-width: 991px) { .fp-layout { grid-template-columns: 1fr; } }

    /* Prévia: mesmo desenho do painel de pagamento do PDV */
    .fp-pdv { background: #1e293b; border-radius: 14px; padding: 14px; color: #f1f5f9; }
    .fp-pdv-total { display: flex; justify-content: space-between; align-items: baseline; padding: 10px 12px; margin-bottom: 10px;
        background: #0f172a; border-radius: 10px; font-weight: 700; }
    .fp-pdv-total small { color: #94a3b8; font-weight: 600; letter-spacing: .04em; }
    .fp-pdv-total span { font-size: 1.35rem; color: #22c55e; }
    .fp-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; min-height: 64px; }
    .fp-btn { padding: 12px 8px; border: 2px solid #475569; background: #0f172a; color: #94a3b8; border-radius: 10px;
        font-size: .82rem; font-weight: 600; text-align: center; display: flex; flex-direction: column; align-items: center;
        gap: 4px; cursor: grab; user-select: none; position: relative; min-width: 0; }
    .fp-btn i { font-size: 1.4rem; }
    .fp-btn .fp-nome { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fp-btn.largo { grid-column: span 2; }
    .fp-btn:hover { border-color: #3b82f6; color: #f1f5f9; }
    .fp-btn.sel { border-color: #3b82f6; background: rgba(59,130,246,.18); color: #f1f5f9; box-shadow: 0 0 0 3px rgba(59,130,246,.35); }
    .fp-btn:focus-visible { outline: 3px solid #93c5fd; outline-offset: 2px; }
    .fp-btn.sortable-ghost { opacity: .35; }
    .fp-btn.sortable-chosen { cursor: grabbing; }
    .fp-btn .fp-tag { position: absolute; top: 3px; right: 5px; font-size: .6rem; font-weight: 700; color: #64748b; }
    .fp-dica-pdv { color: #94a3b8; font-size: .78rem; margin: 10px 2px 0; }

    .fp-off-wrap { margin-top: 14px; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 10px; background: #f8fafc; }
    .fp-off-wrap h3 { font-size: .85rem; font-weight: 700; color: #475569; margin: 0 0 8px; }
    .fp-off-wrap .fp-grid { min-height: 56px; }
    .fp-off-wrap .fp-btn { background: #e2e8f0; border-color: #cbd5e1; color: #64748b; }
    .fp-off-wrap .fp-btn i { color: #94a3b8 !important; }
    .fp-off-wrap .fp-btn.sel { background: #dbeafe; border-color: #3b82f6; color: #1e293b; }
    /* Dica em CSS (:empty), não em elemento: o Sortable só aceita soltar numa lista realmente vazia */
    .fp-off-wrap .fp-grid:empty::before { content: 'Arraste um botão para cá para desligar'; grid-column: span 2;
        color: #94a3b8; font-size: .8rem; text-align: center; align-self: center; pointer-events: none; }

    .fp-editor .form-label { font-weight: 600; }
    .fp-icones { display: grid; grid-template-columns: repeat(auto-fill, minmax(44px, 1fr)); gap: 6px; }
    .fp-icones button { height: 44px; border: 2px solid var(--border, #e2e8f0); border-radius: 8px; background: #fff; font-size: 1.25rem; color: #334155; }
    .fp-icones button[aria-pressed="true"] { border-color: #4f46e5; background: #eef2ff; color: #4f46e5; }
    .fp-cores { display: flex; flex-wrap: wrap; gap: 8px; }
    .fp-cores button { width: 34px; height: 34px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 0 1px #cbd5e1; }
    .fp-cores button[aria-pressed="true"] { box-shadow: 0 0 0 3px #1e293b; }
    .fp-vazio-editor { color: #64748b; padding: 32px 8px; text-align: center; }
    .fp-barra { position: sticky; bottom: 0; background: #fff; border-top: 1px solid #e2e8f0; padding: 12px 0; margin-top: 16px; z-index: 5;
        display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .fp-sujo { color: #b45309; font-weight: 600; font-size: .9rem; }
</style>
@endpush

@section('content')
<x-erp.page-header title="Formas de pagamento" icon="wallet2"
    subtitle="Os botões do PDV e as opções do faturar pedido e da venda balcão — vale para todas as lojas da empresa">
    <a href="{{ route('app.configuracoes.edit') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Configurações
    </a>
    <button type="button" class="btn btn-erp-primary" id="fpNova">
        <i class="bi bi-plus-lg me-1"></i> Nova forma
    </button>
</x-erp.page-header>

<div class="alert alert-info d-flex align-items-start">
    <i class="bi bi-info-circle me-2 mt-1" aria-hidden="true"></i>
    <div>
        <strong>Arraste os botões</strong> para mudar a ordem. Clique num botão para trocar nome, ícone, cor e tamanho.
        Arraste para <strong>Desligadas</strong> o que a empresa não usa — some do PDV e das telas de venda, mas as vendas antigas continuam com o nome certo.
        @unless($personalizado)
            <br><span class="text-muted">Esta empresa ainda está com os botões de fábrica — nada muda até você salvar.</span>
        @endunless
    </div>
</div>

<div class="fp-layout">
    <section aria-labelledby="fpPreviaTitulo">
        <h2 id="fpPreviaTitulo" class="h6 fw-bold mb-2">Como fica no PDV</h2>
        <div class="fp-pdv">
            <div class="fp-pdv-total"><small>TOTAL</small><span>R$ 129,90</span></div>
            <div class="fp-grid" id="fpAtivas" aria-label="Formas ligadas, na ordem do PDV"></div>
            <p class="fp-dica-pdv"><i class="bi bi-arrows-move me-1" aria-hidden="true"></i>Arraste para reordenar · clique para editar</p>
        </div>
        <div class="fp-off-wrap">
            <h3><i class="bi bi-eye-slash me-1" aria-hidden="true"></i>Desligadas — não aparecem nas vendas</h3>
            <div class="fp-grid" id="fpDesligadas" aria-label="Formas desligadas"></div>
        </div>
    </section>

    <section class="card fp-editor" aria-labelledby="fpEditorTitulo">
        <div class="card-body">
            <h2 id="fpEditorTitulo" class="h6 fw-bold mb-3">Editar forma</h2>
            <div id="fpEditorVazio" class="fp-vazio-editor">
                <i class="bi bi-hand-index fs-2 d-block mb-2" aria-hidden="true"></i>
                Clique num botão da prévia para editar.
            </div>
            <div id="fpEditor" hidden>
                <div class="row g-3">
                    <div class="col-sm-7">
                        <label for="fpNome" class="form-label">Nome no botão</label>
                        <input type="text" id="fpNome" class="form-control" maxlength="40" autocomplete="off">
                        <div class="form-text">É o que aparece no botão, no cupom e nos relatórios.</div>
                    </div>
                    <div class="col-sm-5">
                        <label for="fpTipo" class="form-label">Funciona como</label>
                        <select id="fpTipo" class="form-select">
                            @foreach($tipos as $valor => $rotulo)
                                <option value="{{ $valor }}">{{ $rotulo }}</option>
                            @endforeach
                        </select>
                        <div class="form-text" id="fpTipoAjuda">Define a nota fiscal, o caixa, a tabela de preço e as parcelas.</div>
                    </div>

                    <div class="col-12">
                        <span class="form-label d-block" id="fpIconeRotulo">Ícone</span>
                        <div class="fp-icones" id="fpIcones" role="group" aria-labelledby="fpIconeRotulo">
                            @foreach($icones as $icone)
                                <button type="button" data-icone="{{ $icone }}" aria-pressed="false" title="{{ $icone }}"><i class="bi {{ $icone }}" aria-hidden="true"></i><span class="visually-hidden">{{ $icone }}</span></button>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-sm-7">
                        <span class="form-label d-block" id="fpCorRotulo">Cor do ícone</span>
                        <div class="fp-cores" id="fpCores" role="group" aria-labelledby="fpCorRotulo">
                            @foreach($cores as $cor)
                                <button type="button" data-cor="{{ $cor }}" aria-pressed="false" style="background: {{ $cor }}"><span class="visually-hidden">Cor {{ $cor }}</span></button>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-sm-5">
                        <span class="form-label d-block" id="fpLarguraRotulo">Tamanho no PDV</span>
                        <div class="btn-group w-100" role="group" aria-labelledby="fpLarguraRotulo">
                            <input type="radio" class="btn-check" name="fpLargura" id="fpLargura1" value="1">
                            <label class="btn btn-outline-secondary" for="fpLargura1">Meia linha</label>
                            <input type="radio" class="btn-check" name="fpLargura" id="fpLargura2" value="2">
                            <label class="btn btn-outline-secondary" for="fpLargura2">Linha inteira</label>
                        </div>
                    </div>

                    <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                        <div class="form-check form-switch me-auto">
                            <input class="form-check-input" type="checkbox" role="switch" id="fpAtivo">
                            <label class="form-check-label fw-semibold" for="fpAtivo">Ligada (aparece nas vendas)</label>
                        </div>
                        <button type="button" class="btn btn-sm btn-erp-outline" id="fpSobe" title="Mover para antes"><i class="bi bi-arrow-up" aria-hidden="true"></i> Antes</button>
                        <button type="button" class="btn btn-sm btn-erp-outline" id="fpDesce" title="Mover para depois"><i class="bi bi-arrow-down" aria-hidden="true"></i> Depois</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="fpExcluir"><i class="bi bi-trash" aria-hidden="true"></i> Excluir</button>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted" id="fpSistemaAviso" hidden>
                            <i class="bi bi-lock me-1" aria-hidden="true"></i>Forma do sistema: dá para renomear, mudar o visual e desligar, mas não excluir nem mudar como funciona.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<form method="POST" action="{{ route('app.formas-pagamento.update') }}" id="fpForm">
    @csrf
    @method('PUT')
    <input type="hidden" name="formas" id="fpJson">
    <div class="fp-barra">
        <button type="submit" class="btn btn-erp-primary" id="fpSalvar"><i class="bi bi-check-lg me-1" aria-hidden="true"></i> Salvar</button>
        <a href="{{ route('app.formas-pagamento.index') }}" class="btn btn-erp-outline">Desfazer</a>
        <span class="fp-sujo" id="fpSujo" hidden><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>Alterações não salvas</span>
        @if($personalizado)
            <button type="submit" form="fpRestaurar" class="btn btn-link text-danger ms-auto">Voltar aos botões de fábrica</button>
        @endif
    </div>
</form>
@if($personalizado)
<form method="POST" action="{{ route('app.formas-pagamento.restaurar') }}" id="fpRestaurar"
      data-confirm="Voltar aos botões de fábrica? As formas criadas serão apagadas e os nomes, cores e ordem voltam ao original. As vendas já feitas não mudam.">
    @csrf
</form>
@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    const tipos = @json($tipos);
    const tiposNova = @json($tiposNova);
    let formas = @json($formas);
    let selecionada = null;
    let sujo = false;
    let novaSeq = 0;
    const PADRAO_JSON = JSON.stringify(formas);

    const elAtivas = document.getElementById('fpAtivas');
    const elDesl = document.getElementById('fpDesligadas');
    const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    function marcarSujo() {
        sujo = JSON.stringify(formas) !== PADRAO_JSON;
        document.getElementById('fpSujo').hidden = !sujo;
    }

    function botao(f) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'fp-btn' + (f.largura === 2 ? ' largo' : '') + (f.chave === selecionada ? ' sel' : '');
        b.dataset.chave = f.chave;
        b.setAttribute('aria-label', f.nome + (f.ativo ? '' : ' (desligada)') + ' — clique para editar');
        b.innerHTML = `<i class="bi ${esc(f.icone)}" style="color:${esc(f.cor || '#94a3b8')}" aria-hidden="true"></i><span class="fp-nome">${esc(f.nome)}</span>`
            + (f.sistema ? '' : '<span class="fp-tag">NOVA</span>');
        b.addEventListener('click', () => selecionar(f.chave));
        return b;
    }

    function render() {
        elAtivas.innerHTML = '';
        elDesl.innerHTML = '';
        formas.forEach(f => (f.ativo ? elAtivas : elDesl).appendChild(botao(f)));
        preencherEditor();
        marcarSujo();
    }

    // Ordem e ligado/desligado vêm do DOM depois de arrastar
    function lerDoDom() {
        const porChave = Object.fromEntries(formas.map(f => [f.chave, f]));
        const nova = [];
        elAtivas.querySelectorAll('.fp-btn').forEach(b => { const f = porChave[b.dataset.chave]; f.ativo = true; nova.push(f); });
        elDesl.querySelectorAll('.fp-btn').forEach(b => { const f = porChave[b.dataset.chave]; f.ativo = false; nova.push(f); });
        formas = nova;
        render();
    }

    if (window.Sortable) {
        // forceFallback: arrasto desenhado pelo próprio Sortable — igual no mouse e na tela de toque do caixa
        const opts = { group: 'formas', animation: 150, draggable: '.fp-btn', ghostClass: 'sortable-ghost', chosenClass: 'sortable-chosen', forceFallback: true, fallbackTolerance: 4, emptyInsertThreshold: 30, onEnd: lerDoDom };
        Sortable.create(elAtivas, opts);
        Sortable.create(elDesl, opts);
    }

    function atual() { return formas.find(f => f.chave === selecionada) || null; }

    function selecionar(chave) {
        selecionada = chave;
        render();
        document.getElementById('fpNome').focus();
    }

    function preencherEditor() {
        const f = atual();
        document.getElementById('fpEditor').hidden = !f;
        document.getElementById('fpEditorVazio').hidden = !!f;
        if (!f) return;
        document.getElementById('fpNome').value = f.nome;
        const tipo = document.getElementById('fpTipo');
        tipo.value = f.tipo;
        tipo.disabled = f.sistema;
        [...tipo.options].forEach(o => { o.hidden = !f.sistema && !tiposNova.includes(o.value); });
        document.getElementById('fpTipoAjuda').textContent = f.sistema
            ? 'Forma do sistema — o funcionamento não muda.'
            : 'Define a nota fiscal, o caixa, a tabela de preço e as parcelas.';
        document.querySelectorAll('#fpIcones button').forEach(b => b.setAttribute('aria-pressed', b.dataset.icone === f.icone ? 'true' : 'false'));
        document.querySelectorAll('#fpCores button').forEach(b => b.setAttribute('aria-pressed', (b.dataset.cor || '').toLowerCase() === (f.cor || '').toLowerCase() ? 'true' : 'false'));
        document.getElementById('fpLargura' + (f.largura === 2 ? 2 : 1)).checked = true;
        document.getElementById('fpAtivo').checked = !!f.ativo;
        document.getElementById('fpExcluir').hidden = f.sistema;
        document.getElementById('fpSistemaAviso').hidden = !f.sistema;
    }

    function alterar(campo, valor) {
        const f = atual();
        if (!f) return;
        f[campo] = valor;
        if (campo === 'nome') {
            // Não re-renderiza o campo que está sendo digitado — só o botão
            const b = document.querySelector(`.fp-btn[data-chave="${CSS.escape(f.chave)}"] .fp-nome`);
            if (b) b.textContent = valor || '…';
            marcarSujo();
            return;
        }
        render();
    }

    document.getElementById('fpNome').addEventListener('input', e => alterar('nome', e.target.value));
    document.getElementById('fpTipo').addEventListener('change', e => alterar('tipo', e.target.value));
    document.getElementById('fpAtivo').addEventListener('change', e => {
        const f = atual(); if (!f) return;
        f.ativo = e.target.checked;
        // Ligada vai para o fim das ligadas; desligada vai para as desligadas
        formas = formas.filter(x => x !== f);
        const ultimaAtiva = formas.map(x => x.ativo).lastIndexOf(true);
        formas.splice(f.ativo ? ultimaAtiva + 1 : formas.length, 0, f);
        render();
    });
    document.querySelectorAll('#fpIcones button').forEach(b => b.addEventListener('click', () => alterar('icone', b.dataset.icone)));
    document.querySelectorAll('#fpCores button').forEach(b => b.addEventListener('click', () => alterar('cor', b.dataset.cor)));
    document.querySelectorAll('input[name="fpLargura"]').forEach(r => r.addEventListener('change', () => alterar('largura', parseInt(r.value, 10))));

    function mover(delta) {
        const f = atual(); if (!f) return;
        const grupo = formas.filter(x => x.ativo === f.ativo);
        const i = grupo.indexOf(f), j = i + delta;
        if (j < 0 || j >= grupo.length) return;
        const outro = grupo[j];
        const a = formas.indexOf(f), b = formas.indexOf(outro);
        [formas[a], formas[b]] = [formas[b], formas[a]];
        render();
    }
    document.getElementById('fpSobe').addEventListener('click', () => mover(-1));
    document.getElementById('fpDesce').addEventListener('click', () => mover(1));

    document.getElementById('fpExcluir').addEventListener('click', () => {
        const f = atual(); if (!f || f.sistema) return;
        const apagar = () => { formas = formas.filter(x => x !== f); selecionada = null; render(); };
        const msg = `Excluir "${f.nome}"? As vendas feitas com ela continuam mostrando o nome.`;
        if (window.ERP && ERP.confirm) {
            ERP.confirm({ title: 'Excluir forma de pagamento', message: msg, variant: 'danger' }).then(ok => { if (ok) apagar(); });
        } else if (confirm(msg)) { apagar(); }
    });

    document.getElementById('fpNova').addEventListener('click', () => {
        const chave = 'nova_' + (++novaSeq);
        const ultimaAtiva = formas.map(x => x.ativo).lastIndexOf(true);
        formas.splice(ultimaAtiva + 1, 0, { chave, nome: 'Nova forma', tipo: 'cartao_credito', sistema: false, icone: 'bi-credit-card', cor: '#ec4899', largura: 1, ativo: true });
        selecionar(chave);
        const nome = document.getElementById('fpNome');
        nome.select();
    });

    document.getElementById('fpForm').addEventListener('submit', e => {
        const semNome = formas.find(f => !String(f.nome).trim());
        if (semNome) {
            e.preventDefault();
            selecionar(semNome.chave);
            window.ERP?.toast ? ERP.toast('Toda forma precisa de um nome.', 'danger') : alert('Toda forma precisa de um nome.');
            return;
        }
        if (!formas.some(f => f.ativo)) {
            e.preventDefault();
            window.ERP?.toast ? ERP.toast('Deixe pelo menos uma forma ligada.', 'danger') : alert('Deixe pelo menos uma forma ligada.');
            return;
        }
        // Formas criadas nesta tela vão sem chave: o servidor gera a definitiva
        document.getElementById('fpJson').value = JSON.stringify(formas.map(f => ({
            chave: f.chave.startsWith('nova_') ? '' : f.chave,
            nome: f.nome, tipo: f.tipo, icone: f.icone, cor: f.cor, largura: f.largura, ativo: f.ativo,
        })));
        sujo = false;
    });

    window.addEventListener('beforeunload', e => { if (sujo) { e.preventDefault(); e.returnValue = ''; } });

    render();
})();
</script>
@endpush
