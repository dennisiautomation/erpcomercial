@extends('layouts.app')

@section('title', 'Loja online — Editor visual')

@push('styles')
<style>
    .ed{display:grid;grid-template-columns:380px minmax(0,1fr);gap:1rem;align-items:start}
    @media (max-width:1100px){.ed{grid-template-columns:1fr}}
    .ed-painel{background:var(--bg-card,#fff);border:1px solid var(--border,#e2e8f0);border-radius:.75rem;overflow:hidden}
    .ed-painel .nav-link{border-radius:0;font-weight:600;font-size:.9rem;color:var(--text-secondary,#475569)}
    .ed-painel .nav-link.active{background:transparent;color:var(--primary,#4f46e5);box-shadow:inset 0 -2px 0 var(--primary,#4f46e5)}
    .ed-corpo{padding:1rem;max-height:calc(100vh - 250px);overflow:auto}
    @media (max-width:1100px){.ed-corpo{max-height:none}}
    .ed-modelo{display:block;width:100%;text-align:left;border:1.5px solid var(--border,#e2e8f0);border-radius:.6rem;padding:.6rem;background:#fff;margin-bottom:.6rem;cursor:pointer}
    .ed-modelo:hover{border-color:var(--border-hover,#cbd5e1)}
    .ed-modelo[aria-pressed=true]{border-color:var(--primary,#4f46e5);box-shadow:0 0 0 2px var(--primary-light,#eef2ff)}
    /* miniatura do modelo: mostra a FORMA (topo, cartão, preço), não só duas cores */
    .ed-amostra{border-radius:.4rem;overflow:hidden;margin-bottom:.5rem;border:1px solid rgb(0 0 0/.1);font-size:0;line-height:0}
    .ed-am__topo{height:12px;display:flex;align-items:center;padding:0 8px}
    .ed-am__topo i{display:block;height:3px;width:34px;border-radius:2px;opacity:.9}
    .ed-am__topo[data-c=centralizado]{justify-content:center}
    .ed-am__banner{display:grid;gap:6px;padding:9px 10px;align-items:center;min-height:62px}
    .ed-am__banner b{display:block;font-size:13px;line-height:1.1;font-weight:700;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
    .ed-am__banner u{display:block;text-decoration:none;width:34px;height:8px;margin-top:6px}
    .ed-am__fotos{display:grid;gap:3px}
    .ed-am__fotos s{display:block;background:rgb(255 255 255/.88);text-decoration:none}
    .ed-am__banner[data-e=dividido]{grid-template-columns:1.2fr 1fr}
    .ed-am__banner[data-e=dividido] .ed-am__fotos{grid-template-columns:1.25fr 1fr;grid-template-rows:17px 17px}
    .ed-am__banner[data-e=dividido] .ed-am__fotos s:first-child{grid-row:1/3}
    .ed-am__banner[data-e=cheio] .ed-am__fotos,.ed-am__banner[data-e=centro] .ed-am__fotos,.ed-am__banner[data-e=editorial] .ed-am__fotos{grid-template-columns:repeat(3,1fr)}
    .ed-am__banner[data-e=cheio] .ed-am__fotos s{height:18px}
    .ed-am__banner[data-e=centro]{text-align:center}.ed-am__banner[data-e=centro] u{margin-inline:auto}
    .ed-am__banner[data-e=centro] .ed-am__fotos{width:70%;margin-inline:auto}.ed-am__banner[data-e=centro] .ed-am__fotos s{height:15px}
    .ed-am__banner[data-e=editorial] b{font-size:19px;font-weight:500;text-transform:uppercase;border-top:1px solid;padding-top:4px}
    .ed-am__banner[data-e=editorial] u{display:none}
    .ed-am__banner[data-e=editorial] .ed-am__fotos s{height:26px}
    .ed-am__cartoes{display:grid;grid-template-columns:repeat(3,1fr);gap:5px;padding:7px 10px 9px}
    .ed-am__cartoes > span{display:block}
    .ed-am__cartoes em{display:block;height:20px}
    .ed-am__cartoes i{display:block;height:6px;width:55%;margin-top:4px}
    .ed-am__cartoes[data-k=borda] > span{border:1px solid rgb(0 0 0/.14);padding:2px}
    .ed-am__cartoes[data-k=sombra] > span{box-shadow:0 2px 5px -1px rgb(0 0 0/.3);padding-bottom:3px}
    .ed-am__cartoes[data-k=sombra] i{margin-left:3px}
    .ed-am__cartoes[data-k=lista]{grid-template-columns:1fr 1fr;gap:3px 10px}
    .ed-am__cartoes[data-k=lista] > span{display:flex;align-items:center;gap:4px;border-bottom:1px solid rgb(0 0 0/.14);padding-bottom:3px}
    .ed-am__cartoes[data-k=lista] em{width:12px;height:12px;flex:none}
    .ed-am__cartoes[data-k=lista] i{margin:0;width:24px}
    .ed-am__cartoes[data-f=retrato] em{height:30px}
    .ed-am__cartoes[data-p=etiqueta] i{height:8px;clip-path:polygon(4px 0,100% 0,100% 100%,4px 100%,0 50%)}
    .ed-cor{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.3rem 0}
    .ed-cor input[type=color]{width:44px;height:32px;padding:2px;border:1px solid var(--border,#e2e8f0);border-radius:.4rem;background:#fff;cursor:pointer}
    .ed-bloco{border:1px solid var(--border,#e2e8f0);border-radius:.6rem;background:#fff;margin-bottom:.5rem}
    .ed-bloco.aberto{border-color:var(--primary,#4f46e5)}
    .ed-bloco__topo{display:flex;align-items:center;gap:.5rem;padding:.5rem .6rem}
    .ed-pega{cursor:grab;color:var(--text-muted,#64748b);padding:.2rem;touch-action:none}
    .ed-bloco__nome{flex:1;min-width:0;border:0;background:none;text-align:left;padding:.2rem 0;font-weight:600;font-size:.9rem}
    .ed-bloco__nome small{display:block;font-weight:400;color:var(--text-muted,#64748b);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .ed-bloco__form{padding:.25rem .75rem .75rem;border-top:1px solid var(--border-light,#f1f5f9)}
    .ed-bloco__form .form-label{font-size:.8rem;font-weight:600;margin:.6rem 0 .2rem}
    .ed-fantasma{opacity:.4}
    .ed-img{display:flex;align-items:center;gap:.6rem}
    .ed-img img{width:72px;height:48px;object-fit:cover;border-radius:.35rem;border:1px solid var(--border,#e2e8f0)}
    .ed-palco{background:var(--bg-table-head,#f8fafc);border:1px solid var(--border,#e2e8f0);border-radius:.75rem;padding:.75rem}
    .ed-palco__barra{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:.6rem;flex-wrap:wrap}
    .ed-moldura{margin-inline:auto;background:#fff;border-radius:.5rem;overflow:hidden;box-shadow:0 8px 24px -14px rgb(15 23 42/.35);transition:max-width .25s}
    .ed-moldura[data-tela=celular]{max-width:390px}
    .ed-moldura iframe{display:block;width:100%;height:calc(100vh - 290px);min-height:520px;border:0}
    .ed-estado{font-size:.85rem;color:var(--text-muted,#64748b);min-width:150px}
</style>
@endpush

@section('content')
<x-erp.page-header title="Loja online" icon="globe2" subtitle="Editor visual — logo, modelo, cores e página inicial">
    <span class="ed-estado" id="edEstado" role="status">{{ $temRascunho ? 'Há mudanças não publicadas' : 'Igual ao que está publicado' }}</span>
    <button type="button" class="btn btn-outline-secondary" id="edDescartar" @disabled(!$temRascunho)><i class="bi bi-arrow-counterclockwise me-1"></i> Descartar mudanças</button>
    <button type="button" class="btn btn-primary" id="edPublicar" @disabled(!$temRascunho)><i class="bi bi-cloud-upload me-1"></i> Publicar</button>
</x-erp.page-header>

@include('app.loja-online._abas')

<div class="ed">
    <div class="ed-painel">
        <ul class="nav nav-fill border-bottom" role="tablist">
            <li class="nav-item"><button class="nav-link active w-100" data-bs-toggle="tab" data-bs-target="#edModelos" type="button" role="tab">Modelo</button></li>
            <li class="nav-item"><button class="nav-link w-100" data-bs-toggle="tab" data-bs-target="#edTema" type="button" role="tab">Logo, cores e letras</button></li>
            <li class="nav-item"><button class="nav-link w-100" data-bs-toggle="tab" data-bs-target="#edPagina" type="button" role="tab">Página inicial</button></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active ed-corpo" id="edModelos" role="tabpanel">
                <p class="text-muted small">Escolha um ponto de partida. Trocar de modelo substitui as cores e a página inicial pelas do modelo; sua logo e seus produtos ficam.</p>
                <div id="edListaModelos"></div>
            </div>
            <div class="tab-pane fade ed-corpo" id="edTema" role="tabpanel">
                <div class="fw-semibold mb-2">Logo</div>
                <div class="ed-img mb-3" id="edLogo"></div>
                <div class="fw-semibold mb-1">Cores</div>
                <div id="edCores"></div>
                <button type="button" class="btn btn-sm btn-link px-0 mb-3" id="edCoresModelo">Voltar às cores do modelo</button>
                <div class="fw-semibold mb-1">Letras</div>
                <div id="edFontes" class="mb-3"></div>
                <div class="fw-semibold mb-1">Formas</div>
                <div id="edFormas"></div>
            </div>
            <div class="tab-pane fade ed-corpo" id="edPagina" role="tabpanel">
                <p class="text-muted small">Arraste pela alça para mudar a ordem. Clique no nome para editar. Você também pode clicar num trecho da prévia.</p>
                <div id="edBlocos"></div>
                <div class="dropdown mt-2">
                    <button class="btn btn-outline-primary w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="bi bi-plus-lg me-1"></i> Adicionar bloco</button>
                    <ul class="dropdown-menu w-100" id="edNovoBloco"></ul>
                </div>
            </div>
        </div>
    </div>

    <div class="ed-palco">
        <div class="ed-palco__barra">
            <div class="btn-group btn-group-sm" role="group" aria-label="Tamanho da prévia">
                <button type="button" class="btn btn-outline-secondary active" data-tela="computador"><i class="bi bi-display me-1"></i> Computador</button>
                <button type="button" class="btn btn-outline-secondary" data-tela="celular"><i class="bi bi-phone me-1"></i> Celular</button>
            </div>
            <a href="{{ $previa }}" target="_blank" rel="noopener" class="btn btn-sm btn-link">Abrir a prévia em outra aba <i class="bi bi-box-arrow-up-right"></i></a>
        </div>
        <div class="ed-moldura" id="edMoldura" data-tela="computador">
            <iframe id="edPrevia" src="{{ $previa }}" title="Prévia da loja online"></iframe>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    var DADOS = {
        rascunho: @json($rascunho), modelos: @json($modelos), tipos: @json($tipos), fontes: @json($fontes),
        opcoesTema: @json($opcoesTema), cores: @json($cores), icones: @json($icones), categorias: @json($categorias),
        rotas: {
            rascunho: @json(route('app.loja-online.editor.rascunho')), modelo: @json(route('app.loja-online.editor.modelo')),
            publicar: @json(route('app.loja-online.editor.publicar')), descartar: @json(route('app.loja-online.editor.descartar')),
            imagem: @json(route('app.loja-online.editor.imagem'))
        },
        rotulosTema: {raio: 'Cantos', botao: 'Botões', cartao: 'Cartão do produto', cabecalho: 'Cabeçalho', titulos: 'Títulos', preco: 'Preço', foto: 'Foto do produto', movimento: 'Movimento da página', faixa: 'Faixa de aviso'}
    };
    var csrf = document.querySelector('meta[name=csrf-token]').content;
    var estado = JSON.parse(JSON.stringify(DADOS.rascunho));
    var aberto = null, relogio = null, salvando = false, pendente = false;
    var $ = function (id) { return document.getElementById(id); };
    var previa = $('edPrevia');

    function el(tag, attrs, filhos) {
        var e = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (k === 'text') e.textContent = attrs[k];
            else if (k === 'html') e.innerHTML = attrs[k];
            else if (k.slice(0, 2) === 'on') e.addEventListener(k.slice(2), attrs[k]);
            else if (attrs[k] !== null && attrs[k] !== undefined && attrs[k] !== false) e.setAttribute(k, attrs[k] === true ? '' : attrs[k]);
        });
        (filhos || []).forEach(function (f) { if (f) e.appendChild(typeof f === 'string' ? document.createTextNode(f) : f); });
        return e;
    }
    function modeloAtual() { return DADOS.modelos.filter(function (m) { return m.chave === estado.modelo; })[0] || DADOS.modelos[0]; }
    function novoId() { return 'b' + Math.random().toString(36).slice(2, 10); }
    function arquivo(caminho) { return caminho ? '/storage/' + caminho : ''; }

    function chamar(url, corpo, ehArquivo) {
        var opts = {method: 'POST', credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest'}};
        if (ehArquivo) opts.body = corpo;
        else { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(corpo || {}); }
        return fetch(url, opts).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) {
                if (r.status === 401 || r.status === 419) throw new Error('Sua sessão expirou. Recarregue a página e entre de novo.');
                if (!r.ok) throw new Error((j.errors && Object.values(j.errors)[0][0]) || j.message || 'Não foi possível salvar.');
                return j;
            });
        });
    }

    /* ── salvar rascunho (com espera) e recarregar a prévia ── */
    function marcar(texto) { $('edEstado').textContent = texto; }
    function mudou() {
        marcar('Salvando…');
        clearTimeout(relogio);
        relogio = setTimeout(salvar, 600);
    }
    function salvar() {
        if (salvando) { pendente = true; return; }
        salvando = true;
        chamar(DADOS.rotas.rascunho, {modelo: estado.modelo, tema: estado.tema, blocos: estado.blocos}).then(function () {
            marcar('Há mudanças não publicadas');
            $('edPublicar').disabled = false; $('edDescartar').disabled = false;
            recarregarPrevia();
        }).catch(function (e) { marcar('Não salvou'); ERP.toast(e.message, 'danger'); })
          .finally(function () { salvando = false; if (pendente) { pendente = false; salvar(); } });
    }
    function recarregarPrevia() {
        var y = 0;
        try { y = previa.contentWindow.scrollY; } catch (e) {}
        previa.addEventListener('load', function aoCarregar() {
            previa.removeEventListener('load', aoCarregar);
            try { previa.contentWindow.scrollTo(0, y); } catch (e) {}
        });
        try { previa.contentWindow.location.reload(); } catch (e) { previa.src = previa.src; }
    }
    // clique num trecho da prévia abre o bloco correspondente
    previa.addEventListener('load', function () {
        try {
            var doc = previa.contentDocument;
            doc.addEventListener('click', function (ev) {
                var alvo = ev.target.closest('[data-lj-bloco]');
                if (!alvo || !alvo.dataset.ljBloco) return;
                ev.preventDefault();
                aberto = alvo.dataset.ljBloco;
                bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#edPagina"]')).show();
                desenharBlocos();
                var linha = document.querySelector('.ed-bloco.aberto');
                if (linha) linha.scrollIntoView({block: 'nearest', behavior: 'smooth'});
            }, true);
            var st = doc.createElement('style');
            st.textContent = '[data-lj-bloco]{cursor:pointer}[data-lj-bloco]:hover{outline:2px dashed #4f46e5;outline-offset:-2px}';
            doc.head.appendChild(st);
        } catch (e) {}
    });

    /* ── modelos ── */
    function desenharModelos() {
        var lista = $('edListaModelos'); lista.innerHTML = '';
        DADOS.modelos.forEach(function (m) {
            var t = m.tema;
            var raio = 'border-radius:' + Math.min(6, parseInt(t.raio, 10) / 3) + 'px;';
            var sobreBanner = (m.banner === 'centro' || m.banner === 'editorial') ? t.cor_texto : m.variaveis['--sobre-marca'];
            var fundoBanner = m.banner === 'centro' ? t.cor_superficie : (m.banner === 'editorial' ? t.cor_fundo : t.cor_primaria);
            var fotos = function (n) { var a = []; for (var i = 0; i < n; i++) a.push(el('s', {style: raio + ((m.banner === 'centro' || m.banner === 'editorial') ? 'background:' + (m.banner === 'editorial' ? t.cor_superficie : t.cor_fundo) : '')})); return a; };
            var cartoes = []; for (var c = 0; c < (t.cartao === 'lista' ? 4 : 3); c++) cartoes.push(el('span', {style: raio + (t.cartao === 'sombra' ? 'background:' + t.cor_superficie : '')}, [
                el('em', {style: raio + 'background:' + (t.cartao === 'sombra' ? 'rgb(0 0 0/.08)' : t.cor_superficie === t.cor_fundo ? 'rgb(0 0 0/.08)' : (t.cartao === 'limpo' || t.cartao === 'borda' || t.cartao === 'lista') ? 'color-mix(in srgb,' + t.cor_texto + ' 10%,' + t.cor_fundo + ')' : t.cor_superficie)}),
                el('i', {style: 'background:' + (t.preco === 'etiqueta' ? t.cor_destaque : t.cor_texto)})
            ]));
            var amostra = el('div', {class: 'ed-amostra', style: 'background:' + t.cor_fundo, 'aria-hidden': 'true'}, [
                el('div', {class: 'ed-am__topo', 'data-c': t.cabecalho, style: 'background:' + t.cor_cabecalho + ';border-bottom:1px solid rgb(0 0 0/.08)'}, [el('i', {style: 'background:' + t.cor_cabecalho_texto})]),
                el('div', {class: 'ed-am__banner', 'data-e': m.banner, style: 'background:' + fundoBanner + ';color:' + sobreBanner}, [
                    el('div', {}, [el('b', {text: m.titulo, style: 'font-family:' + m.variaveis['--fonte-titulo']}),
                        el('u', {style: 'background:' + ((m.banner === 'centro') ? t.cor_primaria : sobreBanner) + ';border-radius:' + m.variaveis['--raio-botao']})]),
                    el('div', {class: 'ed-am__fotos'}, fotos(3))
                ]),
                el('div', {class: 'ed-am__cartoes', 'data-k': t.cartao, 'data-f': t.foto, 'data-p': t.preco}, cartoes)
            ]);
            lista.appendChild(el('button', {type: 'button', class: 'ed-modelo', 'aria-pressed': m.chave === estado.modelo ? 'true' : 'false', onclick: function () { trocarModelo(m); }}, [
                amostra,
                el('div', {class: 'fw-semibold', text: m.nome + (m.chave === estado.modelo ? ' — em uso' : '')}),
                el('div', {class: 'small text-muted', text: m.ramo + '. ' + m.descricao})
            ]));
        });
    }
    function trocarModelo(m) {
        if (m.chave === estado.modelo) return;
        ERP.confirm({title: 'Trocar para o modelo ' + m.nome + '?', message: 'As cores e a página inicial passam a ser as do modelo ' + m.nome + '. O que está publicado só muda quando você clicar em Publicar.', confirmText: 'Trocar de modelo'}).then(function (sim) {
            if (!sim) return;
            marcar('Salvando…');
            chamar(DADOS.rotas.modelo, {modelo: m.chave}).then(function (j) {
                estado = j.rascunho; aberto = null; desenharTudo();
                marcar('Há mudanças não publicadas'); $('edPublicar').disabled = false; $('edDescartar').disabled = false;
                recarregarPrevia();
            }).catch(function (e) { ERP.toast(e.message, 'danger'); marcar('Não salvou'); });
        });
    }
    // carrega as fontes dos modelos para as amostras
    DADOS.modelos.forEach(function (m) { if (m.fontes) document.head.appendChild(el('link', {rel: 'stylesheet', href: m.fontes})); });

    /* ── imagem (logo, banner, bloco) ── */
    function campoImagem(caminho, aoMudar, rotulo) {
        var caixa = el('div', {class: 'ed-img'});
        function pintar(c) {
            caixa.innerHTML = '';
            if (c) caixa.appendChild(el('img', {src: arquivo(c), alt: ''}));
            var entrada = el('input', {type: 'file', accept: 'image/png,image/jpeg,image/webp', class: 'd-none'});
            entrada.addEventListener('change', function () {
                if (!entrada.files[0]) return;
                var fd = new FormData(); fd.append('imagem', entrada.files[0]);
                marcar('Enviando a imagem…');
                chamar(DADOS.rotas.imagem, fd, true).then(function (j) { pintar(j.caminho); aoMudar(j.caminho); })
                    .catch(function (e) { ERP.toast(e.message, 'danger'); marcar('Imagem não enviada'); });
            });
            caixa.appendChild(entrada);
            caixa.appendChild(el('button', {type: 'button', class: 'btn btn-sm btn-outline-secondary', onclick: function () { entrada.click(); }, text: c ? 'Trocar' : (rotulo || 'Enviar imagem')}));
            if (c) caixa.appendChild(el('button', {type: 'button', class: 'btn btn-sm btn-link text-danger', onclick: function () { pintar(''); aoMudar(''); }, text: 'Tirar'}));
        }
        pintar(caminho);
        return caixa;
    }

    /* ── cores, letras e formas ── */
    function desenharTema() {
        var logo = $('edLogo'); logo.innerHTML = '';
        logo.appendChild(campoImagem(estado.tema.logo || '', function (c) { estado.tema.logo = c; mudou(); }, 'Enviar logo'));
        if (!estado.tema.logo) logo.appendChild(el('span', {class: 'small text-muted', text: 'Sem logo, a loja mostra o nome.'}));

        var cores = $('edCores'); cores.innerHTML = '';
        Object.keys(DADOS.cores).forEach(function (chave) {
            var id = 'edCor_' + chave;
            var entrada = el('input', {type: 'color', id: id, value: estado.tema[chave]});
            entrada.addEventListener('input', function () { estado.tema[chave] = entrada.value; mudou(); });
            cores.appendChild(el('div', {class: 'ed-cor'}, [el('label', {for: id, class: 'small', text: DADOS.cores[chave]}), entrada]));
        });

        var fontes = $('edFontes'); fontes.innerHTML = '';
        [['fonte_titulo', 'Títulos'], ['fonte_texto', 'Texto']].forEach(function (par) {
            var sel = el('select', {class: 'form-select form-select-sm', id: 'ed_' + par[0]});
            Object.keys(DADOS.fontes).forEach(function (k) { sel.appendChild(el('option', {value: k, text: DADOS.fontes[k], selected: estado.tema[par[0]] === k})); });
            sel.addEventListener('change', function () { estado.tema[par[0]] = sel.value; mudou(); });
            fontes.appendChild(el('label', {class: 'form-label small mb-1 mt-2', for: 'ed_' + par[0], text: par[1]}));
            fontes.appendChild(sel);
        });

        var formas = $('edFormas'); formas.innerHTML = '';
        Object.keys(DADOS.opcoesTema).forEach(function (chave) {
            var sel = el('select', {class: 'form-select form-select-sm', id: 'ed_' + chave});
            Object.keys(DADOS.opcoesTema[chave]).forEach(function (k) { sel.appendChild(el('option', {value: k, text: DADOS.opcoesTema[chave][k], selected: String(estado.tema[chave]) === k})); });
            sel.addEventListener('change', function () { estado.tema[chave] = sel.value; mudou(); });
            formas.appendChild(el('label', {class: 'form-label small mb-1 mt-2', for: 'ed_' + chave, text: DADOS.rotulosTema[chave] || chave}));
            formas.appendChild(sel);
        });
    }
    $('edCoresModelo').addEventListener('click', function () {
        var base = modeloAtual().tema;
        Object.keys(DADOS.cores).forEach(function (c) { estado.tema[c] = base[c]; });
        desenharTema(); mudou();
    });

    /* ── blocos da página inicial ── */
    function resumo(b) {
        if (b.tipo === 'beneficios') return (b.itens || []).map(function (i) { return i.titulo; }).filter(Boolean).join(' · ');
        if (b.lista) return (b.titulo ? b.titulo + ' — ' : '') + b.lista.length + (b.lista.length === 1 ? ' item' : ' itens');
        return b.titulo || b.texto || '';
    }
    function destinos() {
        var d = [['', 'Sem botão'], ['produtos', 'Todos os produtos'], ['produtos?promocao=1', 'Promoções'], ['produtos?ordem=novos', 'Novidades'], ['informacoes', 'Entrega, pagamento e trocas']];
        DADOS.categorias.forEach(function (c) { d.push(['produtos?categoria=' + c.id, 'Categoria: ' + c.nome]); });
        return d;
    }
    function campoDoBloco(b, campo) {
        var chave = campo[0], rotulo = campo[1], tipo = campo[2], id = 'ed_' + b.id + '_' + chave, entrada;
        var caixa = el('div', {}, [el('label', {class: 'form-label', for: id, text: rotulo})]);
        var aoMudar = function (v) { b[chave] = v; atualizarResumo(b); mudou(); };
        if (tipo === 'texto') {
            entrada = el('input', {type: 'text', class: 'form-control form-control-sm', id: id, value: b[chave] || '', maxlength: 160});
            entrada.addEventListener('input', function () { aoMudar(entrada.value); });
        } else if (tipo === 'textolongo') {
            entrada = el('textarea', {class: 'form-control form-control-sm', id: id, rows: 4, maxlength: 2000});
            entrada.value = b[chave] || '';
            entrada.addEventListener('input', function () { aoMudar(entrada.value); });
        } else if (tipo === 'opcoes') {
            entrada = el('select', {class: 'form-select form-select-sm', id: id});
            Object.keys(campo[3]).forEach(function (k) { entrada.appendChild(el('option', {value: k, text: campo[3][k], selected: String(b[chave]) === k})); });
            entrada.addEventListener('change', function () { aoMudar(entrada.value); if (chave === 'origem') desenharBlocos(); });
        } else if (tipo === 'categoria') {
            if (b.origem !== 'categoria') return null;
            entrada = el('select', {class: 'form-select form-select-sm', id: id}, [el('option', {value: '', text: 'Escolha a categoria…'})]);
            DADOS.categorias.forEach(function (c) { entrada.appendChild(el('option', {value: c.id, text: c.nome, selected: Number(b[chave]) === c.id})); });
            entrada.addEventListener('change', function () { aoMudar(entrada.value ? Number(entrada.value) : null); });
        } else if (tipo === 'link') {
            var lista = destinos(), conhecido = lista.some(function (d) { return d[0] === (b[chave] || ''); });
            entrada = el('select', {class: 'form-select form-select-sm', id: id});
            lista.forEach(function (d) { entrada.appendChild(el('option', {value: d[0], text: d[1], selected: d[0] === (b[chave] || '')})); });
            entrada.appendChild(el('option', {value: '__outro', text: 'Outro endereço (https://…)', selected: !conhecido}));
            var livre = el('input', {type: 'url', class: 'form-control form-control-sm mt-1', placeholder: 'https://', value: conhecido ? '' : (b[chave] || ''), 'aria-label': 'Endereço do botão'});
            livre.hidden = conhecido;
            entrada.addEventListener('change', function () { livre.hidden = entrada.value !== '__outro'; aoMudar(entrada.value === '__outro' ? livre.value : entrada.value); });
            livre.addEventListener('input', function () { aoMudar(livre.value); });
            caixa.appendChild(entrada); caixa.appendChild(livre);
            return caixa;
        } else if (tipo === 'imagem') {
            caixa.appendChild(campoImagem(b[chave] || '', aoMudar));
            if (b.tipo === 'banner') caixa.appendChild(el('div', {class: 'form-text', text: 'Sem imagem, o banner mostra fotos de produtos da loja. Use imagem deitada, com pelo menos 1600 px de largura.'}));
            if (/_lista\d+$/.test(String(b.id))) caixa.appendChild(el('div', {class: 'form-text', text: 'Sem imagem, a loja usa a foto de um produto. Imagem deitada, com pelo menos 1600 px de largura.'}));
            return caixa;
        } else if (tipo === 'datahora') {
            entrada = el('input', {type: 'datetime-local', class: 'form-control form-control-sm', id: id, value: b[chave] || ''});
            entrada.addEventListener('change', function () { aoMudar(entrada.value); });
            caixa.appendChild(entrada);
            caixa.appendChild(el('div', {class: 'form-text', text: 'Em branco: sem relógio. Depois da data, o bloco some sozinho da loja.'}));
            return caixa;
        } else if (tipo === 'youtube') {
            entrada = el('input', {type: 'url', class: 'form-control form-control-sm', id: id, value: b[chave] ? 'https://youtu.be/' + b[chave] : '', placeholder: 'https://www.youtube.com/watch?v=…'});
            entrada.addEventListener('change', function () { aoMudar(entrada.value); });
        } else if (tipo === 'lista') {
            var sub = campo[3], maximo = campo[4] || 6;
            b[chave] = b[chave] || [];
            b[chave].forEach(function (linha, n) {
                var quadro = el('div', {class: 'border rounded p-2 mb-2'});
                if (!Object.prototype.hasOwnProperty.call(linha, 'id')) Object.defineProperty(linha, 'id', {value: b.id + '_' + chave + n, enumerable: false});
                if (sub.length > 2) quadro.appendChild(el('div', {class: 'small fw-semibold mb-1', text: (rotulo.replace(/s$/, '')) + ' ' + (n + 1)}));
                sub.forEach(function (sc) {
                    if (sc[2] === 'imagem' || sc[2] === 'link' || sc[2] === 'opcoes') {
                        var pronto = campoDoBloco(linha, sc);
                        if (pronto) { pronto.classList.add('mb-2'); var lb = pronto.querySelector('label'); if (lb) lb.className = 'form-label small mb-1'; quadro.appendChild(pronto); }
                        return;
                    }
                    var e2 = sc[2] === 'textolongo'
                        ? el('textarea', {class: 'form-control form-control-sm mb-1', rows: 2, maxlength: 600, placeholder: sc[1], 'aria-label': sc[1] + ' ' + (n + 1)})
                        : el('input', {type: 'text', class: 'form-control form-control-sm mb-1', maxlength: 120, placeholder: sc[1], 'aria-label': sc[1] + ' ' + (n + 1)});
                    e2.value = linha[sc[0]] || '';
                    e2.addEventListener('input', function () { linha[sc[0]] = e2.value; mudou(); });
                    quadro.appendChild(e2);
                });
                quadro.appendChild(el('button', {type: 'button', class: 'btn btn-sm btn-link text-danger p-0', text: 'Tirar', onclick: function () { b[chave].splice(n, 1); desenharBlocos(); mudou(); }}));
                caixa.appendChild(quadro);
            });
            if (b[chave].length < maximo) caixa.appendChild(el('button', {type: 'button', class: 'btn btn-sm btn-outline-secondary', text: '+ Adicionar', onclick: function () {
                var nova = {}; sub.forEach(function (sc) { nova[sc[0]] = sc[2] === 'opcoes' ? Object.keys(sc[3])[0] : ''; }); b[chave].push(nova); desenharBlocos(); mudou();
            }}));
            return caixa;
        } else if (tipo === 'itens') {
            b.itens = b.itens || [];
            b.itens.forEach(function (item, n) {
                var sel = el('select', {class: 'form-select form-select-sm', style: 'max-width:120px', 'aria-label': 'Ícone ' + (n + 1)});
                DADOS.icones.forEach(function (ic) { sel.appendChild(el('option', {value: ic, text: ic, selected: item.icone === ic})); });
                var t = el('input', {type: 'text', class: 'form-control form-control-sm', value: item.titulo || '', maxlength: 60, placeholder: 'Título', 'aria-label': 'Título ' + (n + 1)});
                var x = el('input', {type: 'text', class: 'form-control form-control-sm mt-1', value: item.texto || '', maxlength: 120, placeholder: 'Frase curta', 'aria-label': 'Frase ' + (n + 1)});
                sel.addEventListener('change', function () { item.icone = sel.value; mudou(); });
                t.addEventListener('input', function () { item.titulo = t.value; atualizarResumo(b); mudou(); });
                x.addEventListener('input', function () { item.texto = x.value; mudou(); });
                caixa.appendChild(el('div', {class: 'border rounded p-2 mb-2'}, [el('div', {class: 'd-flex gap-1'}, [sel, t]), x]));
            });
            return caixa;
        }
        if (entrada) caixa.appendChild(entrada);
        return caixa;
    }
    function atualizarResumo(b) {
        var alvo = document.querySelector('[data-resumo="' + b.id + '"]');
        if (alvo) alvo.textContent = resumo(b);
    }
    function desenharBlocos() {
        var lista = $('edBlocos'); lista.innerHTML = '';
        if (!estado.blocos.length) lista.appendChild(el('div', {class: 'text-muted small border rounded p-3 text-center', text: 'A página inicial está vazia. Adicione um bloco abaixo.'}));
        estado.blocos.forEach(function (b, n) {
            var def = DADOS.tipos[b.tipo];
            var linha = el('div', {class: 'ed-bloco' + (aberto === b.id ? ' aberto' : ''), 'data-id': b.id});
            linha.appendChild(el('div', {class: 'ed-bloco__topo'}, [
                el('span', {class: 'ed-pega', title: 'Arraste para mudar a ordem', html: '<i class="bi bi-grip-vertical"></i>'}),
                el('i', {class: 'bi bi-' + def.icone + ' text-muted'}),
                el('button', {type: 'button', class: 'ed-bloco__nome', 'aria-expanded': aberto === b.id ? 'true' : 'false', onclick: function () { aberto = aberto === b.id ? null : b.id; desenharBlocos(); }}, [
                    document.createTextNode(def.nome), el('small', {'data-resumo': b.id, text: resumo(b)})
                ]),
                el('button', {type: 'button', class: 'btn btn-sm btn-link text-muted p-1', title: 'Subir', 'aria-label': 'Subir ' + def.nome, disabled: n === 0, onclick: function () { mover(n, -1); }, html: '<i class="bi bi-arrow-up"></i>'}),
                el('button', {type: 'button', class: 'btn btn-sm btn-link text-muted p-1', title: 'Descer', 'aria-label': 'Descer ' + def.nome, disabled: n === estado.blocos.length - 1, onclick: function () { mover(n, 1); }, html: '<i class="bi bi-arrow-down"></i>'}),
                el('button', {type: 'button', class: 'btn btn-sm btn-link text-danger p-1', title: 'Excluir bloco', 'aria-label': 'Excluir ' + def.nome, onclick: function () { excluir(b); }, html: '<i class="bi bi-trash"></i>'})
            ]));
            if (aberto === b.id) {
                var form = el('div', {class: 'ed-bloco__form'});
                def.campos.forEach(function (campo) { var c = campoDoBloco(b, campo); if (c) form.appendChild(c); });
                linha.appendChild(form);
            }
            lista.appendChild(linha);
        });
    }
    function mover(n, d) {
        var x = estado.blocos.splice(n, 1)[0]; estado.blocos.splice(n + d, 0, x); desenharBlocos(); mudou();
    }
    function excluir(b) {
        ERP.confirm({title: 'Excluir este bloco?', message: 'O bloco "' + DADOS.tipos[b.tipo].nome + '" sai da página inicial. Você pode adicionar outro depois.', variant: 'danger', confirmText: 'Excluir'}).then(function (sim) {
            if (!sim) return;
            estado.blocos = estado.blocos.filter(function (o) { return o.id !== b.id; });
            if (aberto === b.id) aberto = null;
            desenharBlocos(); mudou();
        });
    }
    new Sortable($('edBlocos'), {handle: '.ed-pega', animation: 150, ghostClass: 'ed-fantasma', forceFallback: true, onEnd: function () {
        var ordem = Array.prototype.map.call($('edBlocos').querySelectorAll('.ed-bloco'), function (l) { return l.dataset.id; });
        estado.blocos.sort(function (a, b) { return ordem.indexOf(a.id) - ordem.indexOf(b.id); });
        desenharBlocos(); mudou();
    }});
    Object.keys(DADOS.tipos).forEach(function (tipo) {
        var def = DADOS.tipos[tipo];
        $('edNovoBloco').appendChild(el('li', {}, [el('button', {type: 'button', class: 'dropdown-item', onclick: function () {
            var novo = JSON.parse(JSON.stringify(def.inicial)); novo.id = novoId(); novo.tipo = tipo;
            estado.blocos.push(novo); aberto = novo.id; desenharBlocos(); mudou();
        }}, [el('i', {class: 'bi bi-' + def.icone + ' me-2'}), document.createTextNode(def.nome), el('small', {class: 'd-block text-muted ms-4', text: def.descricao})])]));
    });

    /* ── publicar / descartar / tamanho da prévia ── */
    $('edPublicar').addEventListener('click', function () {
        ERP.confirm({title: 'Publicar as mudanças?', message: 'A loja online passa a aparecer assim para todo mundo.', confirmText: 'Publicar'}).then(function (sim) {
            if (!sim) return;
            clearTimeout(relogio);
            chamar(DADOS.rotas.rascunho, {modelo: estado.modelo, tema: estado.tema, blocos: estado.blocos})
                .then(function () { return chamar(DADOS.rotas.publicar); })
                .then(function () {
                    marcar('Publicado agora'); $('edPublicar').disabled = true; $('edDescartar').disabled = true;
                    ERP.toast('Visual publicado. A loja já está com o novo visual.', 'success');
                }).catch(function (e) { ERP.toast(e.message, 'danger'); });
        });
    });
    $('edDescartar').addEventListener('click', function () {
        ERP.confirm({title: 'Descartar as mudanças?', message: 'O editor volta ao que está publicado. O que você montou e não publicou é perdido.', variant: 'danger', confirmText: 'Descartar'}).then(function (sim) {
            if (!sim) return;
            clearTimeout(relogio);
            chamar(DADOS.rotas.descartar).then(function (j) {
                estado = j.rascunho; aberto = null; desenharTudo(); recarregarPrevia();
                marcar('Igual ao que está publicado'); $('edPublicar').disabled = true; $('edDescartar').disabled = true;
            }).catch(function (e) { ERP.toast(e.message, 'danger'); });
        });
    });
    document.querySelectorAll('[data-tela]').forEach(function (b) {
        if (b.tagName !== 'BUTTON') return;
        b.addEventListener('click', function () {
            document.querySelectorAll('button[data-tela]').forEach(function (o) { o.classList.toggle('active', o === b); });
            $('edMoldura').dataset.tela = b.dataset.tela;
        });
    });

    function desenharTudo() { desenharModelos(); desenharTema(); desenharBlocos(); }
    desenharTudo();
})();
</script>
@endpush
