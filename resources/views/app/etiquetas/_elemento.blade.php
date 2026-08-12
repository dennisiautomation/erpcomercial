{{--
    Um item do layout livre, posicionado em milímetros dentro da etiqueta.

    Recebe: $el (item do layout_json, já normalizado no controller), $produto,
    $pe (preços da etiqueta), $empresaNome, $empresaLogo, $codigoBarras,
    $formatoBarras.

    As medidas saem em mm e a fonte em pt — as mesmas unidades do editor, que é
    o que faz o desenho da tela bater com o papel.
--}}
@php
    $caixa = sprintf(
        'position:absolute;left:%smm;top:%smm;width:%smm;height:%smm;overflow:hidden;',
        $el['x'], $el['y'], $el['w'], $el['h']
    );

    $ehTexto = in_array($el['tipo'], \App\Models\EtiquetaFormato::TIPOS_TEXTO, true);

    if ($ehTexto) {
        $justify = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'][$el['alinhamento'] ?? 'center'];
        $caixa .= sprintf(
            'display:flex;align-items:center;justify-content:%s;text-align:%s;'
            . 'font-family:%s,Arial,sans-serif;font-size:%spt;font-weight:%s;font-style:%s;'
            . 'line-height:1.05;color:%s;',
            $justify,
            $el['alinhamento'] ?? 'center',
            $el['fonte'] ?? 'Arial',
            $el['tamanho'] ?? 8,
            ($el['negrito'] ?? false) ? '700' : '400',
            ($el['italico'] ?? false) ? 'italic' : 'normal',
            $el['cor'] ?? '#000000'
        );
    }

    // Preço no cartão/PIX só existe quando a loja tem tabela de crédito diferente.
    // Sem isso, os dois campos mostram o preço único — nunca uma etiqueta em branco.
    $precoBase    = $pe['base'] ?? $produto->preco_venda;
    $precoCartao  = $pe['credito'] ?? $produto->preco_venda;
    $dinheiro     = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
@endphp

@switch($el['tipo'])
    @case('empresa_nome')
        <div style="{{ $caixa }}"><div style="width:100%;overflow:hidden;">{{ $empresaNome }}</div></div>
        @break

    @case('empresa_logo')
        @if($empresaLogo)
            <div style="{{ $caixa }}">
                <img src="{{ $empresaLogo }}" alt="" style="width:100%;height:100%;object-fit:contain;">
            </div>
        @endif
        @break

    @case('descricao')
        <div style="{{ $caixa }}"><div style="width:100%;overflow:hidden;">{{ $produto->descricao }}</div></div>
        @break

    @case('preco')
        <div style="{{ $caixa }}"><div style="width:100%;">{{ $dinheiro($precoBase) }}</div></div>
        @break

    @case('preco_cartao')
        <div style="{{ $caixa }}"><div style="width:100%;">Cartão {{ $dinheiro($precoCartao) }}</div></div>
        @break

    @case('preco_pix')
        <div style="{{ $caixa }}"><div style="width:100%;">PIX {{ $dinheiro($precoBase) }}</div></div>
        @break

    @case('codigo_interno')
        <div style="{{ $caixa }}"><div style="width:100%;">{{ $produto->codigo_interno }}</div></div>
        @break

    @case('digitos_barras')
        @php
            $digitos = preg_match('/^\d{13}$/', (string) $codigoBarras)
                ? substr($codigoBarras, 0, 1) . ' ' . substr($codigoBarras, 1, 6) . ' ' . substr($codigoBarras, 7)
                : $codigoBarras;
        @endphp
        <div style="{{ $caixa }}"><div style="width:100%;">{{ $digitos }}</div></div>
        @break

    @case('codigo_barras')
        <div style="{{ $caixa }}">
            {{-- barcode-livre: o JS estica as barras no box exato do item, sem
                 pendurar os dígitos embaixo (eles são um item próprio, que o
                 lojista posiciona onde quiser — ou tira). --}}
            <svg class="barcode barcode-livre" data-code="{{ $codigoBarras }}" data-format="{{ $formatoBarras }}"></svg>
        </div>
        @break

    @case('imagem')
        @php
            // A imagem é resolvida AQUI, pelo id, dentro da galeria da empresa —
            // o layout_json nunca carrega caminho de arquivo.
            $img = ($imagensLayout ?? collect())->get($el['imagem_id'] ?? 0);
        @endphp
        @if($img)
            <div style="{{ $caixa }}">
                <img src="{{ $img->url }}" alt="" style="width:100%;height:100%;object-fit:contain;">
            </div>
        @endif
        @break

    @case('retangulo')
        @php
            $borda = ($el['preenchido'] ?? false)
                ? sprintf('background:%s;', $el['cor'] ?? '#000000')
                : sprintf('border:%smm solid %s;', $el['espessura'] ?? 0.3, $el['cor'] ?? '#000000');
        @endphp
        <div style="{{ $caixa }}{{ $borda }}"></div>
        @break

    @case('linha')
        {{-- Borda, não fundo. Cor de fundo depende do "Gráficos em segundo plano"
             do diálogo de impressão; borda é conteúdo e sai sempre, mesmo se o
             navegador (ou o driver da térmica) ignorar o print-color-adjust. --}}
        @php $espessura = $el['espessura'] ?? 0.3; @endphp
        <div style="{{ $caixa }}border-top:{{ $espessura }}mm solid {{ $el['cor'] ?? '#000000' }};height:{{ $espessura }}mm;"></div>
        @break
@endswitch
