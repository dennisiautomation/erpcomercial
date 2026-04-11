<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Cupom</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', 'Lucida Console', monospace;
            font-size: 12px;
            width: 80mm;
            margin: 0 auto;
            padding: 4mm 3mm;
            color: #000;
            background: #fff;
            line-height: 1.3;
        }

        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }

        .line {
            border: none;
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .double-line {
            border: none;
            border-top: 2px solid #000;
            margin: 6px 0;
        }

        /* Header */
        .header { margin-bottom: 6px; }
        .header .empresa-nome {
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 2px;
            text-transform: uppercase;
        }
        .header .info-line {
            font-size: 10px;
            line-height: 1.4;
        }

        /* Tipo do cupom */
        .tipo-cupom {
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            padding: 4px 0;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        /* Dados da venda */
        .venda-info {
            font-size: 10px;
            margin: 4px 0;
        }
        .venda-info .row {
            display: flex;
            justify-content: space-between;
        }

        /* Tabela de itens */
        table { width: 100%; border-collapse: collapse; }
        table th {
            font-size: 9px;
            text-align: left;
            padding: 2px 0;
            border-bottom: 1px solid #000;
            text-transform: uppercase;
        }
        table th.num { text-align: right; }
        table td {
            font-size: 11px;
            padding: 2px 0;
            vertical-align: top;
        }
        table td.num { text-align: right; }
        table td.item-seq {
            font-size: 10px;
            color: #555;
            width: 16px;
        }

        /* Totais */
        .totais { margin: 4px 0; }
        .totais .row {
            display: flex;
            justify-content: space-between;
            padding: 1px 0;
            font-size: 11px;
        }
        .totais .total-row {
            font-size: 18px;
            font-weight: bold;
            padding: 4px 0;
            letter-spacing: 0.5px;
        }

        /* Pagamento */
        .pagamento { margin: 4px 0; }
        .pagamento .titulo {
            font-size: 10px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 3px;
            text-transform: uppercase;
        }
        .pagamento .row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            padding: 1px 0;
        }
        .pagamento .troco-row {
            font-weight: bold;
            font-size: 13px;
        }

        /* QRCode placeholder for NFC-e */
        .qrcode-area {
            text-align: center;
            margin: 8px 0;
            padding: 8px;
        }
        .qrcode-area img {
            max-width: 60mm;
            height: auto;
        }
        .nfce-info {
            font-size: 9px;
            text-align: center;
            line-height: 1.3;
        }

        /* Footer */
        .footer {
            margin-top: 8px;
            font-size: 9px;
            text-align: center;
            line-height: 1.4;
        }
        .footer .aviso {
            font-size: 10px;
            font-weight: bold;
            margin-top: 6px;
            padding: 5px 4px;
            border: 1px solid #000;
            text-transform: uppercase;
        }

        /* Print styles */
        @media print {
            html, body {
                width: 80mm;
                margin: 0;
                padding: 2mm;
            }
            @page {
                margin: 0;
                size: 80mm auto;
            }
        }
    </style>
</head>
<body>

{{-- ===== HEADER ===== --}}
<div class="header center">
    <div class="empresa-nome">{{ $venda->empresa->nome_fantasia ?? $venda->empresa->razao_social ?? 'EMPRESA' }}</div>
    @if($venda->empresa->cnpj ?? null)
        <div class="info-line">CNPJ: {{ $venda->empresa->cnpj }}</div>
    @endif
    @if(($venda->empresa->logradouro ?? null) || ($venda->empresa->cidade ?? null))
        <div class="info-line">
            {{ $venda->empresa->logradouro ?? '' }}{{ $venda->empresa->numero ? ', ' . $venda->empresa->numero : '' }}
            {{ $venda->empresa->bairro ? ' - ' . $venda->empresa->bairro : '' }}
        </div>
        <div class="info-line">
            {{ $venda->empresa->cidade ?? '' }}{{ $venda->empresa->uf ? ' - ' . $venda->empresa->uf : '' }}
            {{ $venda->empresa->cep ? ' CEP: ' . $venda->empresa->cep : '' }}
        </div>
    @endif
    @if($venda->empresa->telefone ?? null)
        <div class="info-line">Fone: {{ $venda->empresa->telefone }}</div>
    @endif
</div>

<hr class="line">

{{-- ===== TIPO DO CUPOM ===== --}}
@if(isset($notaFiscal) && $notaFiscal)
    <div class="tipo-cupom">DANFE NFC-e</div>
@else
    <div class="tipo-cupom">Cupom Nao Fiscal</div>
@endif

<hr class="line">

{{-- ===== DADOS DA VENDA ===== --}}
<div class="venda-info">
    <div class="row">
        <span>Venda: #{{ str_pad($venda->numero, 6, '0', STR_PAD_LEFT) }}</span>
        <span>{{ $venda->created_at->format('d/m/Y H:i') }}</span>
    </div>
    @if($venda->cliente)
        <div class="row">
            <span>Cliente: {{ \Illuminate\Support\Str::limit($venda->cliente->nome_razao_social, 30) }}</span>
        </div>
        @if($venda->cliente->cpf_cnpj)
            <div class="row">
                <span>CPF/CNPJ: {{ $venda->cliente->cpf_cnpj }}</span>
            </div>
        @endif
    @endif
    <div class="row">
        <span>Operador: {{ $venda->vendedor->name ?? 'N/A' }}</span>
    </div>
</div>

<hr class="line">

{{-- ===== ITENS ===== --}}
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Descricao</th>
            <th class="num">Qtd</th>
            <th class="num">Unit</th>
            <th class="num">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($venda->itens as $idx => $item)
            <tr>
                <td class="item-seq">{{ str_pad($idx + 1, 2, '0', STR_PAD_LEFT) }}</td>
                <td>{{ \Illuminate\Support\Str::limit($item->descricao ?? $item->produto->descricao ?? '-', 18) }}</td>
                <td class="num">{{ number_format($item->quantidade, $item->quantidade == intval($item->quantidade) ? 0 : 3, ',', '.') }}</td>
                <td class="num">{{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                <td class="num">{{ number_format($item->total, 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<hr class="double-line">

{{-- ===== TOTAIS ===== --}}
<div class="totais">
    <div class="row">
        <span>Qtd. Itens:</span>
        <span>{{ $venda->itens->count() }}</span>
    </div>
    <div class="row">
        <span>Subtotal:</span>
        <span>R$ {{ number_format($venda->subtotal, 2, ',', '.') }}</span>
    </div>
    @if($venda->desconto_valor > 0)
        <div class="row">
            <span>Desconto:</span>
            <span>- R$ {{ number_format($venda->desconto_valor, 2, ',', '.') }}</span>
        </div>
    @endif
    <div class="row total-row">
        <span>TOTAL:</span>
        <span>R$ {{ number_format($venda->total, 2, ',', '.') }}</span>
    </div>
</div>

<hr class="line">

{{-- ===== PAGAMENTO ===== --}}
<div class="pagamento">
    <div class="titulo">Forma de Pagamento</div>
    @php
        $formaLabels = [
            'dinheiro' => 'Dinheiro',
            'cartao_credito' => 'Cartao Credito',
            'cartao_debito' => 'Cartao Debito',
            'pix' => 'PIX',
            'misto' => 'Misto',
        ];
    @endphp
    @if($venda->pagamento_detalhes && is_array($venda->pagamento_detalhes))
        @foreach($venda->pagamento_detalhes as $pgto)
            <div class="row">
                <span>{{ $formaLabels[$pgto['forma'] ?? ''] ?? ucfirst($pgto['forma'] ?? '-') }}</span>
                <span>R$ {{ number_format($pgto['valor'] ?? 0, 2, ',', '.') }}</span>
            </div>
        @endforeach
    @else
        <div class="row">
            <span>{{ $formaLabels[$venda->forma_pagamento] ?? ucfirst(str_replace('_', ' ', $venda->forma_pagamento ?? '-')) }}</span>
            <span>R$ {{ number_format($venda->total, 2, ',', '.') }}</span>
        </div>
    @endif
    @if($venda->troco > 0)
        <div class="row troco-row">
            <span>TROCO:</span>
            <span>R$ {{ number_format($venda->troco, 2, ',', '.') }}</span>
        </div>
    @endif
</div>

<hr class="line">

{{-- ===== NFC-e INFO ===== --}}
@if(isset($notaFiscal) && $notaFiscal)
    @if($notaFiscal->qrcode_url ?? null)
        <div class="qrcode-area">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($notaFiscal->qrcode_url) }}" alt="QR Code">
        </div>
    @endif
    <div class="nfce-info">
        @if($notaFiscal->chave_acesso ?? null)
            <div>Chave de Acesso:</div>
            <div style="word-break:break-all; font-size:8px;">{{ $notaFiscal->chave_acesso }}</div>
        @endif
        @if($notaFiscal->numero_nota ?? null)
            <div>NFC-e Nr: {{ $notaFiscal->numero_nota }} Serie: {{ $notaFiscal->serie ?? '1' }}</div>
        @endif
        <div>Protocolo: {{ $notaFiscal->protocolo ?? '-' }}</div>
    </div>
    <hr class="line">
@endif

{{-- ===== FOOTER ===== --}}
<div class="footer">
    <p>Obrigado pela preferencia!</p>

    @if(!isset($notaFiscal) || !$notaFiscal)
        <div class="aviso">
            Este cupom nao possui valor fiscal
        </div>
    @endif

    <p style="margin-top:6px; font-size:8px; color:#888;">
        Documento gerado eletronicamente | {{ $venda->created_at->format('d/m/Y H:i:s') }}
    </p>
</div>

</body>
</html>
