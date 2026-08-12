@extends('layouts.app')

@section('title', 'Layout da etiqueta')

@push('styles')
<style>
    /* A régua do editor é o milímetro: tudo na tela é mm × zoom. Assim o que o
       lojista vê arrastando é o que a impressora entrega — se a conversão fosse
       "mais ou menos", o ajuste fino teria que ser feito imprimindo. */
    .etq-palco {
        background: #eceff3;
        border: 1px solid #dbe0e6;
        border-radius: .5rem;
        padding: 2rem 1rem;
        overflow: auto;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        min-height: 320px;
    }

    .etq-etiqueta {
        position: relative;
        background: #fff;
        box-shadow: 0 2px 10px rgba(0, 0, 0, .15);
        outline: 1px solid #b9c0c9;
        overflow: hidden;
        touch-action: none;
    }

    /* Malha de 1mm: dá noção de escala e ajuda a alinhar no olho. */
    .etq-etiqueta.com-malha {
        background-image:
            linear-gradient(to right, rgba(13, 110, 253, .10) 1px, transparent 1px),
            linear-gradient(to bottom, rgba(13, 110, 253, .10) 1px, transparent 1px);
    }

    .etq-item {
        position: absolute;
        overflow: hidden;
        cursor: move;
        outline: 1px dashed transparent;
        display: flex;
        line-height: 1.05;
        user-select: none;
    }

    .etq-item:hover { outline-color: #adb5bd; }

    .etq-item.selecionado {
        outline: 1px solid #0d6efd;
        box-shadow: 0 0 0 2px rgba(13, 110, 253, .18);
    }

    .etq-item .etq-conteudo {
        width: 100%;
        overflow: hidden;
        word-break: break-word;
    }

    .etq-item svg, .etq-item img { width: 100%; height: 100%; display: block; }

    /* Alça de redimensionar no canto inferior direito. */
    .etq-alca {
        position: absolute;
        right: -4px;
        bottom: -4px;
        width: 10px;
        height: 10px;
        background: #0d6efd;
        border: 1px solid #fff;
        border-radius: 2px;
        cursor: nwse-resize;
    }

    .etq-logo-vazio {
        width: 100%; height: 100%;
        display: flex; align-items: center; justify-content: center;
        background: repeating-linear-gradient(45deg, #f1f3f5, #f1f3f5 4px, #e9ecef 4px, #e9ecef 8px);
        color: #868e96; font-size: 9px; text-align: center;
    }

    .etq-lista-item {
        display: flex; align-items: center; gap: .5rem;
        padding: .35rem .5rem; border-radius: .375rem; cursor: pointer;
    }
    .etq-lista-item:hover { background: #f1f3f5; }
    .etq-lista-item.ativo { background: #e7f1ff; }
    .etq-lista-item.fora { opacity: .55; }

    .etq-painel { position: sticky; top: 1rem; }
</style>
@endpush

@section('content')
@php
    $medidas = number_format($formato->largura_mm / 10, 1, ',', '') . ' × '
             . number_format($formato->altura_mm / 10, 1, ',', '') . ' cm';
    $ehFixo = $formato->ehPersonalizacaoDeFixo();
@endphp

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-1"><i class="bi bi-vector-pen me-2"></i>Layout da etiqueta</h4>
        <div class="text-muted small">
            <strong>{{ $ehFixo ? \App\Models\EtiquetaFormato::rotuloDoFixo($formato->formato_base) : $formato->nome }}</strong>
            · {{ $medidas }}
            @if($formato->temLayoutLivre())
                <span class="badge text-bg-primary ms-1">layout personalizado</span>
            @else
                <span class="badge text-bg-secondary ms-1">layout automático</span>
            @endif
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('app.etiquetas.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg me-1"></i>Sair sem salvar
        </a>
        @if($formato->temLayoutLivre())
            <button type="submit" form="formResetLayout" class="btn btn-outline-danger"
                    data-confirm="Voltar ao layout automático? O desenho atual será descartado.">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Voltar ao automático
            </button>
        @endif
        <button type="button" class="btn btn-primary" id="btnSalvar">
            <i class="bi bi-check-lg me-1"></i>Salvar layout
        </button>
    </div>
</div>

<div class="alert alert-light border d-flex gap-2 py-2 small">
    <i class="bi bi-info-circle text-primary"></i>
    <div>
        Arraste os itens para posicionar e use a alça azul do canto para redimensionar.
        O desenho vale só para este formato — os outros continuam como estão.
        <strong>Imprima 1 etiqueta de teste</strong> antes de rodar um lote.
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-semibold"><i class="bi bi-tag me-1"></i>Pré-visualização</span>
                <div class="d-flex align-items-center gap-3">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="chkMalha" checked>
                        <label class="form-check-label small" for="chkMalha">Malha 1 mm</label>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-zoom-out text-muted"></i>
                        <input type="range" class="form-range" style="width: 120px" id="rangeZoom" min="2" max="16" step="0.5">
                        <i class="bi bi-zoom-in text-muted"></i>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="etq-palco">
                    <div class="etq-etiqueta com-malha" id="etqEtiqueta"></div>
                </div>
                <p class="text-muted small mb-0 mt-2">
                    Dados de exemplo do seu cadastro. Setas do teclado movem 0,5 mm
                    (com Shift, 0,1 mm) e Delete tira o item da etiqueta.
                </p>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="etq-painel">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-list-check me-1"></i>Itens da etiqueta
                </div>
                <div class="card-body p-2" id="listaItens"></div>
                @unless($exemplo['tem_logo'])
                    {{-- Sem arquivo de logo, o item "Logo da loja" desenha um bloco
                         cinza aqui e não imprime NADA no papel. Avisar aqui é o que
                         evita o lojista desenhar a etiqueta em volta de um vazio. --}}
                    <div class="card-body pt-0">
                        <div class="alert alert-warning py-2 small mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Sua empresa ainda não tem <strong>logo cadastrada</strong> — o item
                            "Logo da loja" não vai sair na impressão.
                            <a href="{{ route('app.empresa.edit') }}" class="alert-link">Enviar a logo</a>,
                            ou use "Enviar imagem" aqui embaixo para colocar uma arte só na etiqueta.
                        </div>
                    </div>
                @endunless
                <div class="card-footer bg-white small text-muted">
                    Desmarque para tirar o item da etiqueta. O conteúdo vem sempre do
                    cadastro do produto e da loja.
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-brush me-1"></i>Desenhos e imagens
                </div>
                <div class="card-body">
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" id="btnAddRetangulo">
                            <i class="bi bi-square me-1"></i>Retângulo
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" id="btnAddLinha">
                            <i class="bi bi-dash-lg me-1"></i>Linha
                        </button>
                    </div>

                    <label class="form-label small mb-1">Minhas imagens</label>
                    <div class="row g-2 mb-2" id="galeria"></div>

                    <label class="btn btn-sm btn-outline-primary w-100 mb-0" id="rotuloUpload">
                        <i class="bi bi-upload me-1"></i>Enviar imagem
                        <input type="file" id="inputImagem" accept="image/png,image/jpeg,image/gif,image/webp" hidden>
                    </label>
                    <div class="form-text">PNG, JPG, GIF ou WEBP até 2 MB. Clique numa imagem para colocá-la na etiqueta.</div>
                </div>
            </div>

            <div class="card shadow-sm" id="cardPropriedades">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-sliders me-1"></i><span id="tituloPropriedades">Nenhum item selecionado</span>
                </div>
                <div class="card-body" id="corpoPropriedades">
                    <p class="text-muted small mb-0">Clique num item da etiqueta para editar.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="formSalvarLayout" method="POST" action="{{ route('app.etiquetas.formatos.layout', $formato) }}" class="d-none">
    @csrf
    @method('PUT')
    <input type="hidden" name="layout" id="inputLayout">
</form>

@if($formato->temLayoutLivre())
    <form id="formResetLayout" method="POST" action="{{ route('app.etiquetas.formatos.layout.reset', $formato) }}" class="d-none">
        @csrf
        @method('DELETE')
    </form>
@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
(function () {
    'use strict';

    const ETIQUETA = { w: {{ $formato->largura_mm }}, h: {{ $formato->altura_mm }} };
    const CAMPOS   = @json(\App\Models\EtiquetaFormato::CAMPOS);
    const DESENHOS = @json(\App\Models\EtiquetaFormato::DESENHOS);
    const FONTES   = @json(\App\Models\EtiquetaFormato::FONTES);
    const TEXTOS   = @json(\App\Models\EtiquetaFormato::TIPOS_TEXTO);
    const EXEMPLO  = @json($exemplo);
    const MM_POR_PT = {{ \App\Models\EtiquetaFormato::MM_POR_PT }};
    const ROTA_UPLOAD = '{{ route('app.etiquetas.imagens.store') }}';
    const ROTA_IMAGEM = '{{ url('app/etiquetas/imagens') }}';
    const CSRF = '{{ csrf_token() }}';

    let imagens   = @json($imagens);
    let elementos = @json(array_values($elementos));
    let selecionado = -1;
    let zoom = 8;

    const $etiqueta = document.getElementById('etqEtiqueta');
    const $lista    = document.getElementById('listaItens');
    const $titulo   = document.getElementById('tituloPropriedades');
    const $props    = document.getElementById('corpoPropriedades');
    const $zoom     = document.getElementById('rangeZoom');

    // Zoom inicial: a etiqueta inteira cabendo no palco, seja ela 20mm ou 60mm.
    zoom = Math.max(3, Math.min(14, Math.floor(620 / Math.max(ETIQUETA.w, 1))));
    $zoom.value = zoom;

    const mm = v => v * zoom;
    const arred = v => Math.round(v * 10) / 10;
    const limita = (v, min, max) => Math.max(min, Math.min(max, v));
    const ehTexto = tipo => TEXTOS.indexOf(tipo) !== -1;

    function valorExemplo(tipo) {
        switch (tipo) {
            case 'empresa_nome':   return EXEMPLO.empresa_nome;
            case 'descricao':      return EXEMPLO.descricao;
            case 'preco':          return 'R$ ' + EXEMPLO.preco;
            case 'preco_cartao':   return 'Cartão R$ ' + EXEMPLO.preco;
            case 'preco_pix':      return 'PIX R$ ' + EXEMPLO.preco;
            case 'codigo_interno': return EXEMPLO.codigo_interno;
            case 'digitos_barras': return formatarDigitos(EXEMPLO.codigo_barras);
            default: return '';
        }
    }

    function formatarDigitos(codigo) {
        codigo = String(codigo || '');
        return /^\d{13}$/.test(codigo)
            ? codigo[0] + ' ' + codigo.slice(1, 7) + ' ' + codigo.slice(7)
            : codigo;
    }

    /* ---------------- desenho ---------------- */

    function render() {
        renderCanvas();
        renderLista();
        renderPropriedades();
    }

    function renderCanvas() {
        $etiqueta.style.width  = mm(ETIQUETA.w) + 'px';
        $etiqueta.style.height = mm(ETIQUETA.h) + 'px';
        $etiqueta.style.backgroundSize = zoom + 'px ' + zoom + 'px';
        $etiqueta.innerHTML = '';

        elementos.forEach(function (el, i) {
            const div = document.createElement('div');
            div.className = 'etq-item' + (i === selecionado ? ' selecionado' : '');
            div.dataset.indice = i;
            div.style.left   = mm(el.x) + 'px';
            div.style.top    = mm(el.y) + 'px';
            div.style.width  = mm(el.w) + 'px';
            div.style.height = mm(el.h) + 'px';

            if (ehTexto(el.tipo)) {
                // pt -> px na escala do palco: 1pt = 25,4/72 mm.
                div.style.fontFamily = el.fonte || 'Arial';
                div.style.fontSize   = (el.tamanho * MM_POR_PT * zoom) + 'px';
                div.style.fontWeight = el.negrito ? '700' : '400';
                div.style.fontStyle  = el.italico ? 'italic' : 'normal';
                div.style.alignItems = 'center';
                div.style.justifyContent = el.alinhamento === 'left' ? 'flex-start'
                                        : (el.alinhamento === 'right' ? 'flex-end' : 'center');
                const span = document.createElement('div');
                span.className = 'etq-conteudo';
                span.style.textAlign = el.alinhamento || 'center';
                span.textContent = valorExemplo(el.tipo);
                div.appendChild(span);
            } else if (el.tipo === 'codigo_barras') {
                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                div.appendChild(svg);
                try {
                    JsBarcode(svg, String(EXEMPLO.codigo_barras || '1234567890'), {
                        format: /^\d{13}$/.test(String(EXEMPLO.codigo_barras)) ? 'EAN13' : 'CODE128',
                        displayValue: false, margin: 10, width: 2, height: 30
                    });
                } catch (e) {
                    try {
                        JsBarcode(svg, '1234567890', { format: 'CODE128', displayValue: false, margin: 10 });
                    } catch (e2) { /* código do exemplo inválido: caixa fica vazia */ }
                }
                svg.setAttribute('preserveAspectRatio', 'none');
                svg.removeAttribute('width');
                svg.removeAttribute('height');
            } else if (el.tipo === 'imagem') {
                const img = imagens.find(i => i.id === el.imagem_id);
                if (img) {
                    const tag = document.createElement('img');
                    tag.src = img.url;
                    tag.style.objectFit = 'contain';
                    div.appendChild(tag);
                } else {
                    // Imagem apagada da galeria com o desenho aberto.
                    const vazio = document.createElement('div');
                    vazio.className = 'etq-logo-vazio';
                    vazio.textContent = 'imagem removida';
                    div.appendChild(vazio);
                }
            } else if (el.tipo === 'retangulo') {
                if (el.preenchido) {
                    div.style.background = el.cor || '#000000';
                } else {
                    // A borda em px é a espessura em mm na escala do palco.
                    div.style.border = Math.max(1, (el.espessura || 0.3) * zoom) + 'px solid ' + (el.cor || '#000000');
                }
            } else if (el.tipo === 'linha') {
                // Mesma técnica da impressão (borda, não fundo) para a tela não
                // prometer uma linha diferente da que sai no papel.
                div.style.borderTop = Math.max(1, (el.espessura || 0.3) * zoom) + 'px solid ' + (el.cor || '#000000');
                div.style.height = Math.max(1, (el.espessura || 0.3) * zoom) + 'px';
            } else if (el.tipo === 'empresa_logo') {
                if (EXEMPLO.empresa_logo) {
                    const img = document.createElement('img');
                    img.src = EXEMPLO.empresa_logo;
                    img.style.objectFit = 'contain';
                    div.appendChild(img);
                } else {
                    // Deixa claro no próprio desenho que ali não vai sair nada.
                    const vazio = document.createElement('div');
                    vazio.className = 'etq-logo-vazio';
                    vazio.textContent = 'logo não cadastrada';
                    div.appendChild(vazio);
                }
            }

            if (i === selecionado) {
                const alca = document.createElement('div');
                alca.className = 'etq-alca';
                alca.dataset.alca = '1';
                div.appendChild(alca);
            }

            $etiqueta.appendChild(div);
        });
    }

    function renderLista() {
        $lista.innerHTML = '';
        Object.keys(CAMPOS).forEach(function (tipo) {
            const indice = elementos.findIndex(e => e.tipo === tipo);
            const dentro = indice !== -1;

            const linha = document.createElement('div');
            linha.className = 'etq-lista-item'
                + (dentro ? '' : ' fora')
                + (dentro && indice === selecionado ? ' ativo' : '');

            const chk = document.createElement('input');
            chk.type = 'checkbox';
            chk.className = 'form-check-input mt-0';
            chk.checked = dentro;
            chk.addEventListener('change', function (ev) {
                ev.stopPropagation();
                chk.checked ? adicionar(tipo) : remover(indice);
            });

            const nome = document.createElement('span');
            nome.className = 'small flex-grow-1';
            nome.textContent = CAMPOS[tipo];

            linha.appendChild(chk);
            linha.appendChild(nome);
            if (dentro) {
                linha.addEventListener('click', function () { selecionar(indice); });
            }
            $lista.appendChild(linha);
        });
    }

    function renderPropriedades() {
        const el = elementos[selecionado];

        if (!el) {
            $titulo.textContent = 'Nenhum item selecionado';
            $props.innerHTML = '<p class="text-muted small mb-0">Clique num item da etiqueta para editar.</p>';
            return;
        }

        $titulo.textContent = CAMPOS[el.tipo] || DESENHOS[el.tipo] || el.tipo;

        const html = [];
        html.push(`
            <div class="row g-2 mb-2">
                ${campoNumero('x', 'Esquerda (mm)', el.x, 0, ETIQUETA.w)}
                ${campoNumero('y', 'Topo (mm)', el.y, 0, ETIQUETA.h)}
                ${campoNumero('w', 'Largura (mm)', el.w, 0.5, ETIQUETA.w)}
                ${campoNumero('h', 'Altura (mm)', el.h, 0.3, ETIQUETA.h)}
            </div>`);

        if (ehTexto(el.tipo)) {
            html.push(`
                <div class="mb-2">
                    <label class="form-label small mb-1">Fonte</label>
                    <select class="form-select form-select-sm" data-prop="fonte">
                        ${FONTES.map(f => `<option value="${f}" ${f === el.fonte ? 'selected' : ''}>${f}</option>`).join('')}
                    </select>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small mb-1">Tamanho (pt)</label>
                        <input type="number" class="form-control form-control-sm" data-prop="tamanho"
                               value="${el.tamanho}" min="3" max="72" step="0.5">
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1">Alinhamento</label>
                        <select class="form-select form-select-sm" data-prop="alinhamento">
                            <option value="left"   ${el.alinhamento === 'left' ? 'selected' : ''}>Esquerda</option>
                            <option value="center" ${el.alinhamento === 'center' ? 'selected' : ''}>Centro</option>
                            <option value="right"  ${el.alinhamento === 'right' ? 'selected' : ''}>Direita</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex gap-3 mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="propNegrito" data-prop="negrito" ${el.negrito ? 'checked' : ''}>
                        <label class="form-check-label small" for="propNegrito"><strong>Negrito</strong></label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="propItalico" data-prop="italico" ${el.italico ? 'checked' : ''}>
                        <label class="form-check-label small" for="propItalico"><em>Itálico</em></label>
                    </div>
                </div>`);
        }

        if (el.tipo === 'retangulo' || el.tipo === 'linha') {
            html.push(`
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small mb-1">Espessura (mm)</label>
                        <input type="number" class="form-control form-control-sm" data-prop="espessura"
                               value="${el.espessura ?? 0.3}" min="0.1" max="5" step="0.1">
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1">Cor</label>
                        <input type="color" class="form-control form-control-sm form-control-color w-100"
                               data-prop="cor" value="${el.cor || '#000000'}">
                    </div>
                </div>`);

            if (el.tipo === 'retangulo') {
                html.push(`
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="propPreenchido" data-prop="preenchido" ${el.preenchido ? 'checked' : ''}>
                        <label class="form-check-label small" for="propPreenchido">Preenchido (bloco sólido)</label>
                    </div>`);
            }
        }

        if (el.tipo === 'imagem') {
            html.push(`
                <div class="mb-3">
                    <label class="form-label small mb-1">Imagem</label>
                    <select class="form-select form-select-sm" data-prop="imagem_id">
                        ${imagens.map(i => `<option value="${i.id}" ${i.id === el.imagem_id ? 'selected' : ''}>${i.nome}</option>`).join('')}
                    </select>
                    <div class="form-text">Enviar outra imagem no painel "Desenhos e imagens".</div>
                </div>`);
        }

        html.push(`
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" data-acao="centralizar">
                    <i class="bi bi-align-center me-1"></i>Centralizar
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" data-acao="largura-total">
                    <i class="bi bi-arrows me-1"></i>Largura total
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" data-acao="remover" title="Tirar da etiqueta">
                    <i class="bi bi-trash"></i>
                </button>
            </div>`);

        $props.innerHTML = html.join('');

        $props.querySelectorAll('[data-prop]').forEach(function (campo) {
            campo.addEventListener('input', function () {
                const prop = campo.dataset.prop;
                const alvo = elementos[selecionado];
                if (!alvo) return;

                if (campo.type === 'checkbox') {
                    alvo[prop] = campo.checked;
                } else if (campo.type === 'number') {
                    const bruto = parseFloat(campo.value);
                    if (isNaN(bruto)) return;
                    alvo[prop] = prop === 'tamanho' ? limita(bruto, 3, 72) : bruto;
                    if (['x', 'y', 'w', 'h'].indexOf(prop) !== -1) encaixar(alvo);
                } else if (prop === 'imagem_id') {
                    alvo[prop] = parseInt(campo.value, 10);
                } else {
                    alvo[prop] = campo.value;
                }
                // Só o desenho é refeito: refazer o painel aqui tiraria o foco do
                // campo a cada tecla (e fecharia o seletor de cor no meio do uso).
                renderCanvas();
                renderLista();
            });
        });

        $props.querySelectorAll('[data-acao]').forEach(function (botao) {
            botao.addEventListener('click', function () {
                const alvo = elementos[selecionado];
                if (!alvo) return;
                const acao = botao.dataset.acao;

                if (acao === 'centralizar') {
                    alvo.x = arred((ETIQUETA.w - alvo.w) / 2);
                } else if (acao === 'largura-total') {
                    alvo.x = 0;
                    alvo.w = ETIQUETA.w;
                } else if (acao === 'remover') {
                    remover(selecionado);
                    return;
                }
                encaixar(alvo);
                render();
            });
        });
    }

    function campoNumero(prop, rotulo, valor, min, max) {
        return `
            <div class="col-6">
                <label class="form-label small mb-1">${rotulo}</label>
                <input type="number" class="form-control form-control-sm" data-prop="${prop}"
                       value="${valor}" min="${min}" max="${max}" step="0.1">
            </div>`;
    }

    /* ---------------- galeria de imagens ---------------- */

    function renderGaleria() {
        const $galeria = document.getElementById('galeria');
        $galeria.innerHTML = '';

        if (imagens.length === 0) {
            $galeria.innerHTML = '<div class="col-12"><p class="text-muted small mb-0">'
                + 'Nenhuma imagem enviada ainda.</p></div>';
            return;
        }

        imagens.forEach(function (img) {
            const col = document.createElement('div');
            col.className = 'col-4';
            col.innerHTML = `
                <div class="border rounded p-1 position-relative text-center" style="cursor:pointer" title="${img.nome}">
                    <img src="${img.url}" alt="" style="width:100%;height:38px;object-fit:contain;">
                    <button type="button" class="btn btn-sm btn-link text-danger p-0 position-absolute top-0 end-0"
                            data-apagar="${img.id}" title="Apagar da galeria">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>`;

            col.querySelector('div').addEventListener('click', function (ev) {
                if (ev.target.closest('[data-apagar]')) return;
                adicionarImagem(img.id);
            });

            col.querySelector('[data-apagar]').addEventListener('click', function () {
                apagarImagem(img.id);
            });

            $galeria.appendChild(col);
        });
    }

    function enviarImagem(arquivo) {
        const dados = new FormData();
        dados.append('arquivo', arquivo);
        dados.append('nome', arquivo.name);

        const $rotulo = document.getElementById('rotuloUpload');
        $rotulo.classList.add('disabled');

        fetch(ROTA_UPLOAD, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: dados
        })
        .then(r => r.json().then(j => ({ ok: r.ok, corpo: j })))
        .then(function (res) {
            if (!res.ok) {
                ERP.toast(res.corpo.erro || 'Não consegui enviar a imagem.', 'danger');
                return;
            }
            imagens.unshift(res.corpo);
            renderGaleria();
            ERP.toast('Imagem enviada! Clique nela para colocar na etiqueta.', 'success');
        })
        .catch(() => ERP.toast('Falha de conexão ao enviar a imagem.', 'danger'))
        .finally(() => $rotulo.classList.remove('disabled'));
    }

    function apagarImagem(id) {
        const emUso = elementos.some(e => e.tipo === 'imagem' && e.imagem_id === id);
        const aviso = emUso
            ? 'Esta imagem está na etiqueta. Apagar vai deixar o espaço em branco. Continuar?'
            : 'Apagar esta imagem da galeria?';

        ERP.confirm({
            title: 'Apagar imagem',
            message: aviso,
            variant: 'danger',
            confirmText: 'Apagar'
        }).then(function (confirmado) {
            if (!confirmado) return;

            fetch(ROTA_IMAGEM + '/' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
            })
            .then(function (r) {
                if (!r.ok) { ERP.toast('Não consegui apagar a imagem.', 'danger'); return; }
                imagens = imagens.filter(i => i.id !== id);
                elementos = elementos.filter(e => !(e.tipo === 'imagem' && e.imagem_id === id));
                selecionado = -1;
                renderGaleria();
                render();
            })
            .catch(() => ERP.toast('Falha de conexão ao apagar.', 'danger'));
        });
    }

    /* ---------------- edição ---------------- */

    function encaixar(el) {
        el.w = arred(limita(el.w, 0.5, ETIQUETA.w));
        el.h = arred(limita(el.h, 0.3, ETIQUETA.h));
        el.x = arred(limita(el.x, 0, ETIQUETA.w - el.w));
        el.y = arred(limita(el.y, 0, ETIQUETA.h - el.h));
    }

    function selecionar(i) {
        selecionado = i;
        render();
    }

    function remover(i) {
        if (i < 0 || i >= elementos.length) return;
        elementos.splice(i, 1);
        selecionado = -1;
        render();
    }

    function adicionar(tipo) {
        if (elementos.some(e => e.tipo === tipo)) return;

        const novo = {
            tipo: tipo,
            x: 1,
            y: 1,
            w: arred(Math.min(ETIQUETA.w - 2, ETIQUETA.w * 0.9)),
            h: tipo === 'codigo_barras' ? arred(Math.min(8, ETIQUETA.h * 0.3))
             : (tipo === 'empresa_logo' ? arred(Math.min(6, ETIQUETA.h * 0.25)) : 3)
        };

        if (ehTexto(tipo)) {
            Object.assign(novo, {
                fonte: 'Arial', tamanho: 7, negrito: false,
                italico: false, alinhamento: 'center'
            });
            novo.h = arred(7 * MM_POR_PT * 1.35);
        }

        // Nasce no primeiro espaço livre abaixo do último item, não em cima dele.
        const abaixo = elementos.reduce((m, e) => Math.max(m, e.y + e.h), 0);
        novo.y = arred(Math.min(abaixo + 0.5, Math.max(0, ETIQUETA.h - novo.h)));

        encaixar(novo);
        elementos.push(novo);
        selecionado = elementos.length - 1;
        render();
    }

    /**
     * Formas e imagens não são únicas: cada clique cria mais uma. Nascem no
     * canto superior esquerdo com um deslocamento por item já existente, para
     * a segunda não cair exatamente em cima da primeira (e sumir de vista).
     */
    function posicaoLivre(w, h) {
        const passo = arred(Math.min(2, ETIQUETA.w * 0.05)) * (elementos.length % 5);

        return {
            x: arred(limita(1 + passo, 0, Math.max(0, ETIQUETA.w - w))),
            y: arred(limita(1 + passo, 0, Math.max(0, ETIQUETA.h - h)))
        };
    }

    function adicionarForma(tipo) {
        const w = arred(Math.min(ETIQUETA.w - 2, ETIQUETA.w * 0.6));
        const h = tipo === 'linha' ? 0.3 : arred(Math.min(ETIQUETA.h * 0.3, 8));
        const novo = Object.assign(
            { tipo: tipo, w: w, h: h, espessura: 0.3, cor: '#000000', preenchido: false },
            posicaoLivre(w, h)
        );

        encaixar(novo);
        elementos.push(novo);
        selecionado = elementos.length - 1;
        render();
    }

    function adicionarImagem(id) {
        const lado = arred(Math.min(ETIQUETA.w * 0.3, ETIQUETA.h * 0.3, 12));
        const novo = Object.assign({ tipo: 'imagem', imagem_id: id, w: lado, h: lado }, posicaoLivre(lado, lado));

        encaixar(novo);
        elementos.push(novo);
        selecionado = elementos.length - 1;
        render();
        ERP.toast('Imagem colocada na etiqueta. Arraste para posicionar.', 'success');
    }

    /* ---------------- arrastar e redimensionar ---------------- */

    let arraste = null;

    $etiqueta.addEventListener('pointerdown', function (ev) {
        const item = ev.target.closest('.etq-item');
        if (!item) { selecionar(-1); return; }

        const indice = parseInt(item.dataset.indice, 10);
        const el = elementos[indice];
        if (!el) return;

        if (indice !== selecionado) selecionar(indice);

        arraste = {
            indice: indice,
            modo: ev.target.dataset.alca ? 'redimensionar' : 'mover',
            xInicial: ev.clientX,
            yInicial: ev.clientY,
            orig: { x: el.x, y: el.y, w: el.w, h: el.h }
        };
        $etiqueta.setPointerCapture(ev.pointerId);
        ev.preventDefault();
    });

    $etiqueta.addEventListener('pointermove', function (ev) {
        if (!arraste) return;

        const el = elementos[arraste.indice];
        if (!el) return;

        // Alt solto = encaixe de meio milímetro; com Alt, movimento contínuo.
        const passo = ev.altKey ? 0.1 : 0.5;
        const ajusta = v => Math.round(v / passo) * passo;
        const dx = (ev.clientX - arraste.xInicial) / zoom;
        const dy = (ev.clientY - arraste.yInicial) / zoom;

        if (arraste.modo === 'mover') {
            el.x = ajusta(arraste.orig.x + dx);
            el.y = ajusta(arraste.orig.y + dy);
        } else {
            el.w = ajusta(arraste.orig.w + dx);
            el.h = ajusta(arraste.orig.h + dy);
        }

        encaixar(el);
        render();
    });

    ['pointerup', 'pointercancel'].forEach(function (evento) {
        $etiqueta.addEventListener(evento, function () { arraste = null; });
    });

    document.addEventListener('keydown', function (ev) {
        const el = elementos[selecionado];
        if (!el) return;
        // Não sequestrar o teclado enquanto o lojista digita no painel.
        if (/^(INPUT|SELECT|TEXTAREA)$/.test(document.activeElement.tagName)) return;

        const passo = ev.shiftKey ? 0.1 : 0.5;
        const mapa = { ArrowLeft: ['x', -1], ArrowRight: ['x', 1], ArrowUp: ['y', -1], ArrowDown: ['y', 1] };

        if (mapa[ev.key]) {
            el[mapa[ev.key][0]] = arred(el[mapa[ev.key][0]] + mapa[ev.key][1] * passo);
            encaixar(el);
            render();
            ev.preventDefault();
        } else if (ev.key === 'Delete' || ev.key === 'Backspace') {
            remover(selecionado);
            ev.preventDefault();
        }
    });

    /* ---------------- controles da tela ---------------- */

    $zoom.addEventListener('input', function () {
        zoom = parseFloat($zoom.value);
        render();
    });

    document.getElementById('chkMalha').addEventListener('change', function () {
        $etiqueta.classList.toggle('com-malha', this.checked);
    });

    document.getElementById('btnAddRetangulo').addEventListener('click', function () {
        adicionarForma('retangulo');
    });

    document.getElementById('btnAddLinha').addEventListener('click', function () {
        adicionarForma('linha');
    });

    document.getElementById('inputImagem').addEventListener('change', function () {
        if (this.files && this.files[0]) {
            enviarImagem(this.files[0]);
        }
        // Zera para o mesmo arquivo poder ser reenviado depois de apagado.
        this.value = '';
    });

    document.getElementById('btnSalvar').addEventListener('click', function () {
        if (elementos.length === 0) {
            ERP.toast('A etiqueta ficou vazia. Marque pelo menos um item antes de salvar.', 'danger');
            return;
        }
        document.getElementById('inputLayout').value = JSON.stringify({ versao: 1, elementos: elementos });
        document.getElementById('formSalvarLayout').submit();
    });

    renderGaleria();
    render();
})();
</script>
@endpush
