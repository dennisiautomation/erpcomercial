{{-- Pedido / protocolo de entrega em A4 (28/10/2026, DK Med).
     Não é a folha do orçamento: cabeçalho enxuto (nome, CNPJ, telefone, logo pequena),
     "Entregar para" em destaque, coluna de conferência, situação do pagamento e canhoto
     de recebimento destacável no rodapé (a loja fica com o canhoto assinado, o cliente
     com a via). Layout com tabela/float: o Chrome não pagina flex na impressão (armadilha 76). --}}
@php
    $qtd = fn ($q) => rtrim(rtrim(number_format((float) $q, 3, ',', '.'), '0'), ',');
    $brl = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $cli = $pedido->cliente;
    $limpo = fn ($v) => preg_match('/^[\s\-–.]*$/u', (string) $v) ? null : trim((string) $v);
    $linhaRua = $cli && $limpo($cli->logradouro)
        ? trim(implode(' — ', array_filter([
            trim(implode(', ', array_filter([$limpo($cli->logradouro), $limpo($cli->numero)]))),
            $limpo($cli->complemento),
        ])))
        : null;
    $linhaCidade = $cli ? trim(implode(' · ', array_filter([
        trim(implode(' - ', array_filter([$limpo($cli->bairro), trim(implode('/', array_filter([$limpo($cli->cidade), $limpo($cli->uf)])))]))),
        $limpo($cli->cep) ? 'CEP ' . preg_replace('/^(\d{5})(\d{3})$/', '$1-$2', preg_replace('/\D/', '', $cli->cep)) : null,
    ]))) : null;
    $telCliente = $cli && ($cli->whatsapp || $cli->telefone)
        ? \App\Models\ConfiguracaoLoja::formatarTelefone($cli->whatsapp ?: $cli->telefone) : null;

    if ($pedido->metodo_entrega === 'retirada') {
        $formaEntrega = 'Retirada na loja';
    } elseif ($pedido->frete_provedor === 'melhor_envio') {
        $formaEntrega = ($pedido->frete_servico_nome ?: 'Melhor Envio') . ($pedido->frete_prazo_dias ? ' · até ' . $pedido->frete_prazo_dias . ' dias úteis' : '');
    } elseif ($entrega || (float) $pedido->frete_valor > 0) {
        $formaEntrega = 'Entrega por aplicativo (Uber)';
    } elseif ($pedido->metodo_entrega === 'entrega') {
        $formaEntrega = 'Entrega';
    } else {
        $formaEntrega = null;
    }
    $entregador = $entrega && is_array($entrega->courier)
        ? trim(implode(' · ', array_filter([$entrega->courier['name'] ?? null, $entrega->courier['phone_number'] ?? null])))
        : null;
    $qtdTotal = (float) $pedido->itens->sum('quantidade');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido {{ $pedido->numero }} — {{ $cabecalho['nome'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Helvetica Neue", Arial, sans-serif; font-size: 12px; color: #1f2937; background: #e5e7eb; }
        .folha { width: 210mm; min-height: 297mm; margin: 12px auto; background: #fff; padding: 12mm 14mm 10mm; }
        .barra { width: 210mm; margin: 12px auto 0; }
        .barra button, .barra a { font: inherit; font-size: 13px; padding: 8px 16px; border: 0; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; }
        .barra button { background: #111827; color: #fff; float: right; }
        .barra a { background: #fff; color: #111827; border: 1px solid #9ca3af; }
        .barra .aviso { display: inline-block; margin-left: 10px; color: #065f46; font-size: 13px; line-height: 34px; }
        .barra::after { content: ""; display: block; clear: both; }

        table { border-collapse: collapse; width: 100%; }
        .r { text-align: right; } .c { text-align: center; }

        /* Cabeçalho enxuto: loja à esquerda, número do pedido grande à direita */
        .cab { border-bottom: 3px solid #111827; padding-bottom: 8px; }
        .cab td { vertical-align: middle; }
        .cab .logo { width: 1%; padding-right: 10px; }
        .cab .logo img { max-height: 42px; max-width: 110px; display: block; }
        .cab .loja h1 { font-size: 16px; color: #111827; }
        .cab .loja .linha { font-size: 10.5px; color: #374151; margin-top: 1px; }
        .cab .numero { text-align: right; white-space: nowrap; }
        .cab .numero .tipo { font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: #6b7280; }
        .cab .numero .n { font-size: 28px; font-weight: bold; color: #111827; line-height: 1.05; }
        .cab .numero .data { font-size: 10.5px; color: #374151; margin-top: 2px; }
        .cab .numero .situacao { display: inline-block; margin-left: 6px; font-size: 9px; letter-spacing: 1px; text-transform: uppercase; border: 1px solid #111827; border-radius: 3px; padding: 1px 5px; vertical-align: 1px; }

        .blocos { margin-top: 10px; }
        .blocos > tbody > tr > td { vertical-align: top; }
        .caixa { border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 11px; }
        .caixa.destaque { border: 2px solid #111827; }
        .rotulo { font-size: 9px; text-transform: uppercase; letter-spacing: .6px; color: #6b7280; margin-bottom: 4px; font-weight: bold; }
        .nome { font-size: 15px; font-weight: bold; color: #111827; }
        .linha { font-size: 11.5px; color: #1f2937; margin-top: 2px; }
        .linha b { color: #111827; }
        .tel { font-size: 13px; font-weight: bold; margin-top: 3px; }

        .itens { margin-top: 12px; }
        .itens th { background: #111827; color: #fff; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; padding: 6px 8px; text-align: left; }
        .itens td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; font-size: 11.5px; vertical-align: middle; }
        .itens tr:nth-child(even) td { background: #f9fafb; }
        .itens .cod { color: #6b7280; font-size: 10px; }
        .itens .q { font-size: 14px; font-weight: bold; }
        .itens .v { font-size: 10.5px; color: #4b5563; }
        .itens .conf span { display: inline-block; width: 14px; height: 14px; border: 1.5px solid #111827; border-radius: 2px; }
        .itens tr { page-break-inside: avoid; }

        .resumo-tab { margin-top: 10px; }
        .resumo-tab > tbody > tr > td { vertical-align: top; }
        .texto { white-space: pre-line; font-size: 11.5px; line-height: 1.5; }
        .resumo { width: 250px; margin-left: auto; }
        .resumo td { padding: 3px 0; font-size: 11.5px; }
        .resumo .total td { border-top: 2px solid #111827; padding-top: 6px; font-size: 14px; font-weight: bold; }
        .pago { margin-top: 6px; border: 2px solid #111827; border-radius: 6px; padding: 6px 10px; text-align: center; font-size: 13px; font-weight: bold; }
        .pago small { display: block; font-size: 10px; font-weight: normal; color: #374151; margin-top: 1px; }

        /* Canhoto destacável: a loja fica com ele assinado */
        .corte { margin-top: 26px; border-top: 1.5px dashed #6b7280; position: relative; page-break-inside: avoid; }
        .corte span { position: absolute; top: -8px; left: 0; background: #fff; padding-right: 6px; font-size: 10px; color: #6b7280; }
        .canhoto { margin-top: 10px; page-break-inside: avoid; }
        .canhoto .titulo { font-size: 11px; letter-spacing: 2px; text-transform: uppercase; font-weight: bold; color: #111827; }
        .canhoto .titulo b { float: right; font-size: 13px; letter-spacing: 0; }
        .canhoto .ref { font-size: 10.5px; color: #374151; margin-top: 2px; }
        .campos { margin-top: 8px; border-collapse: separate; border-spacing: 8px 0; margin-left: -8px; width: calc(100% + 16px); }
        .campos td { border-bottom: 1px solid #111827; height: 34px; vertical-align: bottom; padding: 0 2px 2px; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; }
        .campos .assina { height: 48px; }
        .obs-entrega { margin-top: 10px; border: 1px solid #d1d5db; border-radius: 6px; height: 46px; padding: 5px 8px; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; }
        .rodape { margin-top: 10px; text-align: center; font-size: 9.5px; color: #6b7280; }

        @media print {
            body { background: #fff; }
            .barra { display: none; }
            .folha { margin: 0; width: auto; min-height: 0; padding: 0; }
            @page { size: A4; margin: 11mm; }
            .itens th, .itens tr:nth-child(even) td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="barra">
    <a href="{{ route('app.pedidos.show', $pedido) }}">&larr; Voltar ao pedido</a>
    @if(session('success'))<span class="aviso">{{ session('success') }}</span>@endif
    <button type="button" onclick="window.print()">Imprimir / Salvar PDF</button>
</div>
<div class="folha">

    {{-- ===== Cabeçalho enxuto ===== --}}
    <table class="cab">
        <tr>
            @if($cabecalho['logo'])
                <td class="logo"><img src="{{ $cabecalho['logo'] }}" alt=""></td>
            @endif
            <td class="loja">
                <h1>{{ $cabecalho['nome'] }}</h1>
                @if($cabecalho['loja'])<div class="linha">Loja: <b>{{ $cabecalho['loja'] }}</b></div>@endif
                <div class="linha">
                    @if($cabecalho['cnpj'])CNPJ {{ $cabecalho['cnpj'] }}@endif
                    @if($cabecalho['cnpj'] && $telefone) · @endif
                    @if($telefone)Tel.: {{ $telefone }}@endif
                </div>
            </td>
            <td class="numero">
                <div class="tipo">Pedido de venda</div>
                <div class="n">Nº {{ $pedido->numero }}</div>
                <div class="data">{{ $pedido->created_at->format('d/m/Y H:i') }}<span class="situacao">{{ $pedido->status->label() }}</span></div>
            </td>
        </tr>
    </table>

    {{-- ===== Entregar para / Entrega ===== --}}
    <table class="blocos">
        <tr>
            <td style="width:58%; padding-right:8px;">
                <div class="caixa destaque">
                    <div class="rotulo">Entregar para</div>
                    <div class="nome">{{ $cli->nome_razao_social ?? 'Consumidor' }}</div>
                    @if($telCliente)<div class="tel">{{ $telCliente }}</div>@endif
                    @if($linhaRua)<div class="linha">{{ $linhaRua }}</div>@endif
                    @if($linhaCidade)<div class="linha">{{ $linhaCidade }}</div>@endif
                    @if(! $linhaRua && ! $linhaCidade)<div class="linha" style="color:#6b7280;">Endereço não informado no cadastro</div>@endif
                    @if($cli?->cpf_cnpj)<div class="linha" style="color:#4b5563;">{{ strlen(preg_replace('/\D/', '', $cli->cpf_cnpj)) > 11 ? 'CNPJ' : 'CPF' }} {{ \App\Models\ConfiguracaoLoja::formatarDocumento($cli->cpf_cnpj) }}</div>@endif
                </div>
            </td>
            <td>
                <div class="caixa">
                    <div class="rotulo">Entrega</div>
                    <div class="linha"><b>{{ $formaEntrega ?: 'A combinar' }}</b></div>
                    @if($entregador)<div class="linha">Entregador: {{ $entregador }}</div>@endif
                    @if($pedido->vendedor)<div class="linha">Vendedor: {{ $pedido->vendedor->name }}</div>@endif
                    @if($pedido->orcamento)<div class="linha">Orçamento nº {{ $pedido->orcamento->numero }}</div>@endif
                    @if($pedido->venda)<div class="linha">Venda nº {{ $pedido->venda->numero }}</div>@endif
                </div>
            </td>
        </tr>
    </table>

    {{-- ===== Itens com coluna de conferência ===== --}}
    <table class="itens">
        <thead>
            <tr>
                <th class="c" style="width:5%">Conf.</th>
                <th style="width:4%">#</th>
                <th>Produto / serviço</th>
                <th class="c" style="width:9%">Qtd</th>
                <th class="r" style="width:13%">Unit.</th>
                <th class="r" style="width:14%">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pedido->itens as $i => $item)
                <tr>
                    <td class="c conf"><span></span></td>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $item->descricao ?? $item->produto->descricao ?? $item->servico->descricao ?? '-' }}
                        @if($item->produto?->codigo_interno)<div class="cod">Cód. {{ $item->produto->codigo_interno }}</div>@endif
                    </td>
                    <td class="c q">{{ $qtd($item->quantidade) }}</td>
                    <td class="r v">{{ $brl($item->preco_unitario) }}@if((float) $item->desconto_valor > 0)<br><span style="font-size:9.5px;">desc. {{ $brl($item->desconto_valor) }}</span>@endif</td>
                    <td class="r v"><b>{{ $brl($item->total) }}</b></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ===== Observações + resumo ===== --}}
    <table class="resumo-tab">
        <tr>
            <td style="padding-right:16px;">
                @if(filled($pedido->observacoes_externas))
                    <div class="caixa">
                        <div class="rotulo">Observações</div>
                        <div class="texto">{{ $pedido->observacoes_externas }}</div>
                    </div>
                @endif
            </td>
            <td style="width:260px;">
                <table class="resumo">
                    <tr><td>Itens</td><td class="r">{{ $pedido->itens->count() }} ({{ $qtd($qtdTotal) }} un.)</td></tr>
                    @if((float) $pedido->desconto_valor > 0)
                        <tr><td>Subtotal</td><td class="r">{{ $brl($pedido->subtotal) }}</td></tr>
                        <tr><td>Desconto</td><td class="r">− {{ $brl($pedido->desconto_valor) }}</td></tr>
                    @endif
                    @if((float) $pedido->frete_valor > 0)
                        <tr><td>Taxa de entrega</td><td class="r">+ {{ $brl($pedido->frete_valor) }}</td></tr>
                    @endif
                    <tr class="total"><td>TOTAL</td><td class="r">{{ $brl($pedido->total) }}</td></tr>
                    @if($pagamento)
                        <tr><td>Pagamento</td><td class="r">{{ $pagamento }}</td></tr>
                    @endif
                </table>
                <div class="pago">
                    @if($situacao['valor'])
                        {{ $situacao['texto'] }}: {{ $brl($situacao['valor']) }}
                        <small>cobrar na entrega</small>
                    @else
                        {{ $situacao['texto'] }}
                        <small>nada a cobrar na entrega</small>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- ===== Canhoto de recebimento ===== --}}
    <div class="corte"><span>&#9986; recortar — via da loja</span></div>
    <div class="canhoto">
        <div class="titulo">Protocolo de entrega <b>Pedido Nº {{ $pedido->numero }}</b></div>
        <div class="ref">{{ $cabecalho['nome'] }} · {{ $cli->nome_razao_social ?? 'Consumidor' }} · {{ $pedido->itens->count() }} item(ns), {{ $qtd($qtdTotal) }} un. · {{ $brl($pedido->total) }} · {{ $situacao['valor'] ? $situacao['texto'] . ' ' . $brl($situacao['valor']) : $situacao['texto'] }}</div>
        <table class="campos">
            <tr>
                <td style="width:44%">Recebido por (nome legível)</td>
                <td style="width:24%">Documento (CPF/RG)</td>
                <td style="width:17%">Data</td>
                <td style="width:15%">Hora</td>
            </tr>
        </table>
        <table class="campos">
            <tr>
                <td class="assina" style="width:60%">Assinatura de quem recebeu</td>
                <td class="assina">Entregador</td>
            </tr>
        </table>
        <div class="obs-entrega">Observações da entrega (recusa, ausência, avaria)</div>
    </div>

    <div class="rodape">Emitido em {{ now()->format('d/m/Y H:i') }}</div>
</div>
<script>
    // Mesmo comportamento do orçamento: abre a janela de impressão sozinho (dali também sai o PDF)
    window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
</script>
</body>
</html>
