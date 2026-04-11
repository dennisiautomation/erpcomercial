<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Cupom Nao Fiscal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            width: 80mm;
            margin: 0 auto;
            padding: 5mm;
            color: #000;
            background: #fff;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .line { border-top: 1px dashed #000; margin: 5px 0; }
        .double-line { border-top: 2px solid #000; margin: 5px 0; }

        .header { margin-bottom: 10px; }
        .header h2 { font-size: 14px; margin-bottom: 2px; }
        .header p { font-size: 10px; line-height: 1.4; }

        .tipo-cupom {
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            padding: 5px 0;
            letter-spacing: 1px;
        }

        table { width: 100%; border-collapse: collapse; }
        table th { font-size: 10px; text-align: left; padding: 2px 0; border-bottom: 1px solid #000; }
        table td { font-size: 11px; padding: 2px 0; vertical-align: top; }
        table .num { text-align: right; }

        .totais { margin: 5px 0; }
        .totais .row { display: flex; justify-content: space-between; padding: 1px 0; font-size: 11px; }
        .totais .total-row { font-size: 16px; font-weight: bold; padding: 5px 0; }

        .pagamento { margin: 5px 0; }
        .pagamento .row { display: flex; justify-content: space-between; font-size: 11px; padding: 1px 0; }

        .footer { margin-top: 10px; font-size: 9px; text-align: center; line-height: 1.4; }
        .footer .aviso { font-size: 10px; font-weight: bold; margin-top: 5px; padding: 5px; border: 1px solid #000; }

        @media print {
            body { width: 80mm; margin: 0; padding: 2mm; }
            @page { margin: 0; size: 80mm auto; }
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header center">
        <h2>{{ $venda->empresa->nome_fantasia ?? $venda->empresa->razao_social ?? 'EMPRESA' }}</h2>
        <p>CNPJ: {{ $venda->empresa->cnpj ?? '00.000.000/0000-00' }}</p>
        <p>{{ $venda->empresa->logradouro ?? '' }}{{ $venda->empresa->numero ? ', ' . $venda->empresa->numero : '' }}</p>
        <p>{{ $venda->empresa->cidade ?? '' }}{{ $venda->empresa->uf ? ' - ' . $venda->empresa->uf : '' }}</p>
    </div>

    <div class="line"></div>

    <div class="tipo-cupom">CUPOM NAO FISCAL</div>

    <div class="line"></div>

    {{-- Items --}}
    <table>
        <thead>
            <tr>
                <th>QTD</th>
                <th>DESCRICAO</th>
                <th class="num">UNIT</th>
                <th class="num">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venda->itens as $item)
                <tr>
                    <td>{{ number_format($item->quantidade, $item->quantidade == intval($item->quantidade) ? 0 : 3, ',', '.') }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($item->descricao ?? $item->produto->descricao ?? '-', 20) }}</td>
                    <td class="num">{{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($item->total, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="double-line"></div>

    {{-- Totals --}}
    <div class="totais">
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

    <div class="line"></div>

    {{-- Payment --}}
    <div class="pagamento">
        <div class="center bold" style="font-size:11px; margin-bottom:3px;">PAGAMENTO</div>
        @if($venda->pagamento_detalhes && is_array($venda->pagamento_detalhes))
            @foreach($venda->pagamento_detalhes as $pgto)
                <div class="row">
                    <span>{{ ucfirst($pgto['forma'] ?? '-') }}:</span>
                    <span>R$ {{ number_format($pgto['valor'] ?? 0, 2, ',', '.') }}</span>
                </div>
            @endforeach
        @else
            <div class="row">
                <span>{{ ucfirst(str_replace('_', ' ', $venda->forma_pagamento ?? '-')) }}:</span>
                <span>R$ {{ number_format($venda->total, 2, ',', '.') }}</span>
            </div>
        @endif
        @if($venda->troco > 0)
            <div class="row bold">
                <span>TROCO:</span>
                <span>R$ {{ number_format($venda->troco, 2, ',', '.') }}</span>
            </div>
        @endif
    </div>

    <div class="line"></div>

    {{-- Info --}}
    <div class="center" style="font-size:10px; padding:5px 0;">
        <p>Data: {{ $venda->created_at->format('d/m/Y H:i:s') }}</p>
        <p>Operador: {{ $venda->vendedor->name ?? 'N/A' }}</p>
        <p>Venda: #{{ $venda->numero }}</p>
        @if($venda->cliente)
            <p>Cliente: {{ $venda->cliente->nome_razao_social }}</p>
            @if($venda->cliente->cpf_cnpj)
                <p>CPF/CNPJ: {{ $venda->cliente->cpf_cnpj }}</p>
            @endif
        @endif
    </div>

    <div class="line"></div>

    {{-- Footer --}}
    <div class="footer">
        <p>Obrigado pela preferencia!</p>
        <div class="aviso">
            ESTE CUPOM NAO POSSUI VALOR FISCAL
        </div>
        <p style="margin-top:5px;">Documento gerado eletronicamente</p>
    </div>
</body>
</html>
