<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiquetas - Impressao</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
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

        @page {
            margin: 5mm;
        }

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
        }

        .etiqueta .codigo {
            color: #777;
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
            <button onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
        </div>
    </div>

    @php
        $formatos = [
            '2x5' => ['cols' => 2, 'rows' => 5, 'per_page' => 10],
            '3x7' => ['cols' => 3, 'rows' => 7, 'per_page' => 21],
            '4x10' => ['cols' => 4, 'rows' => 10, 'per_page' => 40],
        ];
        $config = $formatos[$formato];
        $pages = array_chunk($itens, $config['per_page']);
        $empresaNome = auth()->user()->empresa->razao_social ?? auth()->user()->empresa->nome_fantasia ?? 'Empresa';
    @endphp

    @foreach($pages as $pageItens)
        <div class="page formato-{{ $formato }}">
            @foreach($pageItens as $produto)
                <div class="etiqueta">
                    <div class="empresa">{{ $empresaNome }}</div>
                    <div class="descricao">{{ $produto->descricao }}</div>
                    <div class="barcode-container">
                        <svg class="barcode"
                             data-code="{{ $produto->codigo_barras ?: $produto->codigo_interno }}"
                             data-format="{{ $produto->codigo_barras && strlen($produto->codigo_barras) == 13 ? 'EAN13' : ($produto->codigo_barras && strlen($produto->codigo_barras) == 8 ? 'EAN8' : 'CODE128') }}">
                        </svg>
                    </div>
                    <div class="preco">R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}</div>
                    <div class="codigo">{{ $produto->codigo_interno }}</div>
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

                try {
                    JsBarcode(svg, code, {
                        format: format,
                        width: 1.5,
                        height: 50,
                        displayValue: true,
                        fontSize: 10,
                        margin: 2,
                        textMargin: 1
                    });
                } catch (e) {
                    // Fallback to CODE128 if format fails
                    try {
                        JsBarcode(svg, code, {
                            format: 'CODE128',
                            width: 1.5,
                            height: 50,
                            displayValue: true,
                            fontSize: 10,
                            margin: 2,
                            textMargin: 1
                        });
                    } catch (e2) {
                        console.warn('Nao foi possivel gerar barcode para:', code);
                    }
                }
            });

            // Auto-print
            setTimeout(function() {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>
