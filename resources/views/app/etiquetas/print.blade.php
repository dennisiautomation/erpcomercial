<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiquetas - Impressao</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    @php
        // Formato cadastrado pelo lojista (largura × altura × colunas em etiqueta_formatos).
        // Os formatos fixos abaixo continuam exatamente como sempre foram.
        $formatoCustom = $formatoCustom ?? null;
        $ehCustom = (bool) $formatoCustom;
        // Basta UM produto do lote com Cartão/PIX para a etiqueta ganhar a 2ª
        // linha de preço — o layout inteiro do lote se ajusta a ela.
        $temPrecoDuplo = collect($precosEtiqueta)->contains(fn ($p) => $p['dual'] ?? false);
        $L = $ehCustom ? $formatoCustom->layout($temPrecoDuplo) : null;

        // Estilo do arranjo: só o formato cadastrado tem estilo; os fixos são padrão.
        $ehNomeTopo = $ehCustom && $formatoCustom->ehNomeTopo();
        $ehTermica = $ehCustom || str_starts_with($formato, 'termica-');
        // largura x altura da MÍDIA (página) por formato térmico
        $termicaPage = [
            'termica-40x25' => ['w' => '40mm', 'h' => '25mm'],
            'termica-50x30' => ['w' => '50mm', 'h' => '30mm'],
            'termica-60x40' => ['w' => '60mm', 'h' => '40mm'],
            'termica-33x22' => ['w' => '70mm', 'h' => '22mm'],  // bobina 2 colunas
            'termica-36x20-2col' => ['w' => '74mm', 'h' => '20mm'], // Argox 2 col c/ espaço: 2 × 36mm + 2mm
            'termica-tag-35x60' => ['w' => '105mm', 'h' => '60mm'], // tag de roupa: 3 × 35mm
        ];
        if ($ehCustom) {
            $termicaPage[$formato] = [
                'w' => $formatoCustom->largura_pagina_mm . 'mm',
                'h' => $formatoCustom->altura_mm . 'mm',
            ];
        }
        // Barras esticadas de ponta a ponta + dígitos em linha única embaixo:
        // é o que torna etiqueta pequena legível (padrão Hiper). Formato
        // personalizado entra sempre nesse tratamento.
        $digitosEmLinha = $ehCustom || in_array($formato, ['termica-36x20-2col', 'termica-33x22', 'termica-tag-35x60']);
        $alturaBarras = $ehCustom
            ? $L['altura_barras'] . 'mm'
            : (['termica-36x20-2col' => '6mm', 'termica-33x22' => '6.5mm', 'termica-tag-35x60' => '10mm'][$formato] ?? '6mm');
    @endphp
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            background: #fff;
        }

        @if($ehTermica)
        @page {
            size: {{ $termicaPage[$formato]['w'] }} {{ $termicaPage[$formato]['h'] }};
            margin: 0;
        }
        @else
        @page {
            margin: 5mm;
        }
        @endif

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            display: grid;
            gap: 1px;
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: avoid;
        }

        /* Formato 2x5 - 10 etiquetas por pagina (grande) */
        .page.formato-2x5 {
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: repeat(5, 1fr);
            padding: 5mm;
            gap: 3mm;
        }

        .formato-2x5 .etiqueta {
            padding: 4mm;
        }

        .formato-2x5 .etiqueta .empresa {
            font-size: 8pt;
        }

        .formato-2x5 .etiqueta .descricao {
            font-size: 9pt;
            max-height: 2.4em;
        }

        .formato-2x5 .etiqueta .barcode-container svg {
            height: 28mm;
        }

        .formato-2x5 .etiqueta .preco {
            font-size: 16pt;
        }

        .formato-2x5 .etiqueta .codigo {
            font-size: 7pt;
        }

        /* Formato 3x7 - 21 etiquetas por pagina (media) */
        .page.formato-3x7 {
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(7, 1fr);
            padding: 3mm;
            gap: 2mm;
        }

        .formato-3x7 .etiqueta {
            padding: 2mm;
        }

        .formato-3x7 .etiqueta .empresa {
            font-size: 6pt;
        }

        .formato-3x7 .etiqueta .descricao {
            font-size: 7pt;
            max-height: 2em;
        }

        .formato-3x7 .etiqueta .barcode-container svg {
            height: 18mm;
        }

        .formato-3x7 .etiqueta .preco {
            font-size: 12pt;
        }

        .formato-3x7 .etiqueta .codigo {
            font-size: 6pt;
        }

        /* Formato 4x10 - 40 etiquetas por pagina (pequena) */
        .page.formato-4x10 {
            grid-template-columns: repeat(4, 1fr);
            grid-template-rows: repeat(10, 1fr);
            padding: 2mm;
            gap: 1mm;
        }

        .formato-4x10 .etiqueta {
            padding: 1.5mm;
        }

        .formato-4x10 .etiqueta .empresa {
            font-size: 5pt;
        }

        .formato-4x10 .etiqueta .descricao {
            font-size: 5.5pt;
            max-height: 1.8em;
        }

        .formato-4x10 .etiqueta .barcode-container svg {
            height: 12mm;
        }

        .formato-4x10 .etiqueta .preco {
            font-size: 9pt;
        }

        .formato-4x10 .etiqueta .codigo {
            font-size: 5pt;
        }

        /* ---- Formatos térmicos (bobina) — cada página = 1 etiqueta ---- */
        .page[class*="formato-termica"] {
            width: auto;
            min-height: 0;
            margin: 0;
            display: grid;
            gap: 0;
        }

        .page.formato-termica-40x25 { width: 40mm; height: 25mm; grid-template-columns: 1fr; }
        .page.formato-termica-50x30 { width: 50mm; height: 30mm; grid-template-columns: 1fr; }
        .page.formato-termica-60x40 { width: 60mm; height: 40mm; grid-template-columns: 1fr; }
        .page.formato-termica-33x22 { width: 70mm; height: 22mm; grid-template-columns: 1fr 1fr; gap: 0 2mm; }
        .page.formato-termica-36x20-2col { width: 74mm; height: 20mm; grid-template-columns: repeat(2, 36mm); gap: 0 2mm; }
        .page.formato-termica-tag-35x60 { width: 105mm; height: 60mm; grid-template-columns: repeat(3, 35mm); gap: 0; }

        [class*="formato-termica"] .etiqueta { border: none; padding: 1mm; }
        [class*="formato-termica"] .etiqueta .empresa { font-size: 5pt; }
        [class*="formato-termica"] .etiqueta .descricao { font-size: 6pt; max-height: 1.6em; -webkit-line-clamp: 1; }
        [class*="formato-termica"] .etiqueta .preco { font-size: 9pt; }
        [class*="formato-termica"] .etiqueta .codigo { display: none; }
        .formato-termica-40x25 .etiqueta .barcode-container svg { height: 9mm; }
        .formato-termica-50x30 .etiqueta .barcode-container svg { height: 12mm; }
        .formato-termica-60x40 .etiqueta .barcode-container svg { height: 16mm; }
        .formato-termica-33x22 .etiqueta .barcode-container svg { width: 100%; height: 6.5mm; }
        /* 36x20 e Tag 35x60: barra de ponta a ponta (SVG esticado por JS) e
           dígitos numa LINHA ÚNICA centrada embaixo (padrão Hiper) — o layout
           EAN-13 clássico joga o 1º dígito para fora das barras e ele encostava
           na borda da etiqueta ("número para fora", Dennis 05/08) */
        .formato-termica-36x20-2col .etiqueta .barcode-container svg { width: 100%; height: 6mm; }
        .formato-termica-tag-35x60 .etiqueta .barcode-container svg { width: 100%; height: 10mm; }
        .formato-termica-36x20-2col .etiqueta .barcode-container,
        .formato-termica-33x22 .etiqueta .barcode-container,
        .formato-termica-tag-35x60 .etiqueta .barcode-container { flex-direction: column; }
        .etiqueta .barcode-digits {
            width: 100%;
            text-align: center;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            line-height: 1.1;
            white-space: nowrap;
        }
        .formato-termica-36x20-2col .etiqueta .barcode-digits { font-size: 8pt; letter-spacing: 1px; }
        .formato-termica-33x22 .etiqueta .barcode-digits { font-size: 7.5pt; letter-spacing: 0.5px; }
        .formato-termica-tag-35x60 .etiqueta .barcode-digits { font-size: 9pt; letter-spacing: 1.5px; }

        .formato-termica-60x40 .etiqueta .empresa { font-size: 6pt; }
        .formato-termica-60x40 .etiqueta .descricao { font-size: 8pt; -webkit-line-clamp: 2; max-height: 2.4em; }
        .formato-termica-60x40 .etiqueta .preco { font-size: 12pt; }
        .formato-termica-33x22 .etiqueta .empresa,
        .formato-termica-33x22 .etiqueta .empresa-logo { display: none; }
        /* Argox 36×20mm, 2 colunas com espaço (equivalente ao layout 27 da Hiper):
           descrição + código interno + barras com número + preço. Sem nome/logo da
           empresa — não cabe em 20mm de altura. */
        .formato-termica-36x20-2col .etiqueta { padding: 0.6mm 0.5mm; gap: 0.2mm; }
        .formato-termica-36x20-2col .etiqueta .empresa,
        .formato-termica-36x20-2col .etiqueta .empresa-logo { display: none; }
        .formato-termica-36x20-2col .etiqueta .descricao { font-size: 6pt; -webkit-line-clamp: 1; max-height: 1.3em; }
        .formato-termica-36x20-2col .etiqueta .codigo { display: block; font-size: 5.5pt; }
        .formato-termica-36x20-2col .etiqueta .preco { font-size: 10.5pt; }
        /* Ordem da Hiper: nome → código interno → barras (com número) → preço.
           No template padrão o código vem por último; aqui é reordenado no flex. */
        .formato-termica-36x20-2col .etiqueta .descricao { order: 1; }
        .formato-termica-36x20-2col .etiqueta .codigo { order: 2; }
        .formato-termica-36x20-2col .etiqueta .barcode-container { order: 3; }
        .formato-termica-36x20-2col .etiqueta .preco { order: 4; }
        /* Tag de roupa (ilabel 35×60mm, furo no topo): conteúdo desce 6mm para não cair no furo */
        .formato-termica-tag-35x60 .etiqueta { padding: 7mm 2mm 2mm; justify-content: flex-start; gap: 1mm; }
        .formato-termica-tag-35x60 .etiqueta .empresa { font-size: 6pt; }
        .formato-termica-tag-35x60 .etiqueta .descricao { font-size: 7pt; -webkit-line-clamp: 3; max-height: 3.6em; }
        .formato-termica-tag-35x60 .etiqueta .preco { font-size: 13pt; }
        .formato-termica-tag-35x60 .etiqueta .codigo { display: block; font-size: 5pt; }

        @if($ehCustom)
        /* ---- Formato cadastrado pelo lojista ({{ $formatoCustom->nome }}:
           {{ $formatoCustom->resumo }}). Tudo derivado das medidas — ver
           EtiquetaFormato::layout(). ---- */
        .page.formato-{{ $formato }} {
            width: {{ $formatoCustom->largura_pagina_mm }}mm;
            height: {{ $formatoCustom->altura_mm }}mm;
            grid-template-columns: repeat({{ $formatoCustom->colunas }}, {{ $formatoCustom->largura_mm }}mm);
            grid-template-rows: {{ $formatoCustom->altura_mm }}mm;
            gap: 0 {{ $formatoCustom->espaco_mm }}mm;
            /* Redundante com o reset de [class*="formato-termica"], mas explícito
               de propósito: sem zerar o min-height do A4 a linha da bobina se
               espalha por dezenas de páginas em branco. */
            min-height: 0;
            margin: 0;
            overflow: hidden;
        }
        .formato-{{ $formato }} .etiqueta { padding: {{ $L['padding'] }}mm 0.5mm; gap: 0.2mm; }
        .formato-{{ $formato }} .etiqueta .barcode-container { flex-direction: column; }
        .formato-{{ $formato }} .etiqueta .barcode-container svg { width: 100%; height: {{ $L['altura_barras'] }}mm; }
        .formato-{{ $formato }} .etiqueta .barcode-digits { font-size: {{ $L['fonte_digitos'] }}pt; letter-spacing: 0.5px; }
        .formato-{{ $formato }} .etiqueta .descricao { font-size: {{ $L['fonte_descricao'] }}pt; -webkit-line-clamp: 1; max-height: 1.3em; }
        .formato-{{ $formato }} .etiqueta .preco { font-size: {{ $L['fonte_preco'] }}pt; }
        .formato-{{ $formato }} .etiqueta .preco-forma { font-size: {{ $L['fonte_preco_duplo'] }}pt; }
        @if($L['mostrar_empresa'])
        .formato-{{ $formato }} .etiqueta .empresa { font-size: {{ $L['fonte_empresa'] }}pt; }
        @else
        .formato-{{ $formato }} .etiqueta .empresa,
        .formato-{{ $formato }} .etiqueta .empresa-logo { display: none; }
        @endif
        @if($L['mostrar_codigo'])
        .formato-{{ $formato }} .etiqueta .codigo { display: block; font-size: {{ $L['fonte_codigo'] }}pt; }
        @else
        .formato-{{ $formato }} .etiqueta .codigo { display: none; }
        @endif
        @if(! ($L['mostrar_descricao'] ?? true))
        .formato-{{ $formato }} .etiqueta .descricao { display: none; }
        @endif

        @if($ehNomeTopo)
        /* ---- Estilo "nome no topo" ----------------------------------------
           Nome da loja ocupa a largura toda e manda na etiqueta; os preços
           recuam para a direita em corpo pequeno; as barras tomam o rodapé.
           `justify-content: space-between` gruda o nome em cima e as barras
           embaixo, independente de o preço ter 1 ou 2 linhas. */
        .formato-{{ $formato }} .etiqueta {
            justify-content: space-between;
            align-items: stretch;
            text-align: left;
        }
        .formato-{{ $formato }} .etiqueta .empresa {
            font-weight: 800;
            line-height: 1.05;
            text-align: center;
            width: 100%;
            white-space: nowrap;
            overflow: hidden;
        }
        .formato-{{ $formato }} .etiqueta .bloco-precos {
            width: 100%;
            text-align: right;
            line-height: 1.15;
        }
        .formato-{{ $formato }} .etiqueta .preco { font-weight: 600; }
        .formato-{{ $formato }} .etiqueta .barcode-container { width: 100%; margin-top: auto; }
        @endif
        @endif

        @media screen {
            .page[class*="formato-termica"] {
                outline: 1px dashed #bbb;
                margin: 0 auto 8px;
            }
        }

        /* Etiqueta base */
        .etiqueta {
            border: 1px dashed #ccc;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
        }

        .etiqueta .empresa {
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }

        .etiqueta .descricao {
            font-weight: bold;
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            width: 100%;
            margin-bottom: 1px;
        }

        .etiqueta .barcode-container {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .etiqueta .barcode-container svg {
            width: 90%;
        }

        .etiqueta .preco {
            font-weight: bold;
            margin-top: 1px;
            white-space: nowrap;
        }

        /* etiqueta dupla (Cartão + PIX): 2 linhas menores que o preço único,
           nowrap para nunca quebrar "Cartão R$ 22,00" no meio */
        .etiqueta .preco-forma {
            font-size: 72%;
            line-height: 1.25;
        }

        .etiqueta .empresa-logo img {
            max-width: 82%;
            max-height: 7mm;
            object-fit: contain;
            /* logo colorido/dourado sai apagado na térmica — imprime em preto sólido */
            filter: brightness(0);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .etiqueta .empresa-logo { text-align: center; margin-bottom: 1px; }

        .etiqueta .codigo {
            color: #000;
            margin-top: 1px;
        }

        /* Impressao */
        @media print {
            body {
                background: none;
            }

            .etiqueta {
                border: 1px dashed #ddd;
            }

            .no-print {
                display: none !important;
            }
        }

        /* Toolbar para tela */
        .toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #333;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
        }

        .toolbar button {
            background: #0d6efd;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .toolbar button:hover {
            background: #0b5ed7;
        }

        .toolbar .info {
            font-size: 14px;
        }

        @media print {
            body {
                padding-top: 0;
            }
        }

        @media screen {
            body {
                padding-top: 60px;
                background: #f0f0f0;
            }

            .page {
                background: #fff;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>

    <div class="toolbar no-print">
        <div class="info">
            {{ count($itens) }} etiqueta(s) | Formato: {{ $formato }}
        </div>
        <div>
            <button onclick="window.print()">🖨️ Imprimir</button>
        </div>
    </div>

    @php
        $formatos = [
            '2x5' => ['cols' => 2, 'rows' => 5, 'per_page' => 10],
            '3x7' => ['cols' => 3, 'rows' => 7, 'per_page' => 21],
            '4x10' => ['cols' => 4, 'rows' => 10, 'per_page' => 40],
            // térmicas: 1 etiqueta por página (2 no formato de 2 colunas)
            'termica-40x25' => ['cols' => 1, 'rows' => 1, 'per_page' => 1],
            'termica-50x30' => ['cols' => 1, 'rows' => 1, 'per_page' => 1],
            'termica-60x40' => ['cols' => 1, 'rows' => 1, 'per_page' => 1],
            'termica-33x22' => ['cols' => 2, 'rows' => 1, 'per_page' => 2],
            'termica-36x20-2col' => ['cols' => 2, 'rows' => 1, 'per_page' => 2],
            'termica-tag-35x60' => ['cols' => 3, 'rows' => 1, 'per_page' => 3],
        ];
        if ($ehCustom) {
            // 1 "página" = 1 linha da bobina = N etiquetas lado a lado.
            $formatos[$formato] = [
                'cols'     => $formatoCustom->colunas,
                'rows'     => 1,
                'per_page' => $formatoCustom->colunas,
            ];
        }
        $config = $formatos[$formato];
        $pages = array_chunk($itens, $config['per_page']);
        $empresaNome = auth()->user()->empresa->razao_social ?? auth()->user()->empresa->nome_fantasia ?? 'Empresa';
        // Logo da empresa substitui o nome na etiqueta (vale para todas as unidades)
        $empresaLogo = auth()->user()->empresa?->logo ? asset('storage/' . auth()->user()->empresa->logo) : null;
    @endphp

    @foreach($pages as $pageItens)
        <div class="page formato-{{ $formato }}">
            @foreach($pageItens as $produto)
                @php
                    $pe = $precosEtiqueta[$produto->id] ?? null;
                    $codigoBarras = $produto->codigo_barras ?: $produto->codigo_interno;
                    $formatoBarras = $produto->codigo_barras && strlen($produto->codigo_barras) == 13
                        ? 'EAN13'
                        : ($produto->codigo_barras && strlen($produto->codigo_barras) == 8 ? 'EAN8' : 'CODE128');
                @endphp
                <div class="etiqueta">
                    @if($empresaLogo)
                        <div class="empresa-logo"><img src="{{ $empresaLogo }}" alt=""></div>
                    @else
                        <div class="empresa">{{ $empresaNome }}</div>
                    @endif

                    @if($ehNomeTopo)
                        {{-- Estilo "nome no topo": nome (acima), preços recuados, barras
                             no rodapé. Replica o layout do BarTender da MISS MERLINDA. --}}
                        <div class="bloco-precos">
                            @if($pe && $pe['dual'])
                                <div class="preco preco-forma">Cartão R$ {{ number_format($pe['credito'], 2, ',', '.') }}</div>
                                <div class="preco preco-forma">PIX R$ {{ number_format($pe['base'], 2, ',', '.') }}</div>
                            @else
                                <div class="preco">R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}</div>
                            @endif
                        </div>
                        <div class="barcode-container">
                            <svg class="barcode" data-code="{{ $codigoBarras }}" data-format="{{ $formatoBarras }}"></svg>
                        </div>
                    @else
                        <div class="descricao">{{ $produto->descricao }}</div>
                        <div class="barcode-container">
                            <svg class="barcode" data-code="{{ $codigoBarras }}" data-format="{{ $formatoBarras }}"></svg>
                        </div>
                        @if($pe && $pe['dual'])
                            {{-- valores secos por forma — sem parcelamento (pedido do Dennis 25/07) --}}
                            <div class="preco preco-forma">Cartão R$ {{ number_format($pe['credito'], 2, ',', '.') }}</div>
                            <div class="preco preco-forma">PIX R$ {{ number_format($pe['base'], 2, ',', '.') }}</div>
                        @else
                            <div class="preco">R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}</div>
                        @endif
                        <div class="codigo">{{ $produto->codigo_interno }}</div>
                    @endif
                </div>
            @endforeach

            {{-- Preencher celulas vazias para manter o grid --}}
            @for($i = count($pageItens); $i < $config['per_page']; $i++)
                <div class="etiqueta" style="border-color: transparent;"></div>
            @endfor
        </div>
    @endforeach

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.barcode').forEach(function(svg) {
                var code = svg.getAttribute('data-code');
                var format = svg.getAttribute('data-format');

                if (!code) return;

                var barOpts = {
                    format: format,
                    width: 1.5,
                    height: 50,
                    displayValue: true,
                    fontSize: 10,
                    margin: 2,
                    textMargin: 1
                };
                @if($digitosEmLinha)
                    // 36x20, 33x22, Tag 35x60 e formatos personalizados: o SVG carrega SÓ as barras — os dígitos
                    // saem numa div própria, em linha única centrada (padrão Hiper).
                    // O layout EAN-13 clássico (displayValue) joga o 1º dígito para
                    // fora das barras e ele encostava na borda da etiqueta.
                    // margin 10 no intrínseco vira ~1,5mm de quiet zone por lado
                    // depois do stretch — o leitor precisa desse respiro.
                    barOpts = {
                        format: format,
                        width: 2,
                        height: 30,
                        displayValue: false,
                        margin: 10
                    };
                @endif

                try {
                    JsBarcode(svg, code, barOpts);
                } catch (e) {
                    // Fallback to CODE128 if format fails
                    try {
                        JsBarcode(svg, code, Object.assign({}, barOpts, { format: 'CODE128' }));
                    } catch (e2) {
                        console.warn('Nao foi possivel gerar barcode para:', code);
                    }
                }

                @if($digitosEmLinha)
                    // Blindagem: estica o SVG para a largura toda, sem depender do
                    // aspect-ratio da lib ('none' = preenche exatamente o box; a
                    // proporção horizontal das barras — o que o leitor lê — é
                    // preservada pelo viewBox). Inline style vence qualquer CSS.
                    svg.setAttribute('preserveAspectRatio', 'none');
                    svg.removeAttribute('width');
                    svg.removeAttribute('height');
                    svg.style.width = '100%';
                    svg.style.height = '{{ $alturaBarras }}';
                    // dígitos em linha única centrada embaixo das barras
                    var digits = document.createElement('div');
                    digits.className = 'barcode-digits';
                    digits.textContent = /^\d{13}$/.test(code)
                        ? code[0] + ' ' + code.slice(1, 7) + ' ' + code.slice(7)
                        : code;
                    svg.parentNode.appendChild(digits);
                @endif
            });

            // Auto-print
            setTimeout(function() {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>
