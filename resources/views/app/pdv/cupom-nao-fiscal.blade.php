<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cupom</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            /* Térmica imprime só preto: fonte de traço GROSSO (Arial 900) +
               contorno extra nas letras (text-stroke) + zero cinza.
               Courier tem haste fina e sai fraca a 203dpi. */
            font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;
            font-size: 14px;
            font-weight: 700;
            /* sem text-stroke: 900+contorno entupia o miolo dos números na térmica */
            width: 80mm;
            margin: 0 auto;
            padding: 4mm 3mm;
            color: #000;
            background: #fff;
            line-height: 1.35;
        }
        @media print {
            * { color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }

        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }

        .line {
            border: none;
            border-top: 2px dashed #000;
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
            font-size: 17px;
            font-weight: bold;
            margin-bottom: 2px;
            text-transform: uppercase;
        }
        .header .info-line {
            font-size: 12px;
            line-height: 1.4;
        }

        /* Tipo do cupom */
        .tipo-cupom {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            padding: 4px 0;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        /* Dados da venda */
        .venda-info {
            font-size: 12px;
            margin: 4px 0;
        }
        .venda-info .row {
            display: flex;
            justify-content: space-between;
        }

        /* Tabela de itens */
        table { width: 100%; border-collapse: collapse; }
        table th {
            font-size: 11px;
            text-align: left;
            padding: 2px 0;
            border-bottom: 1px solid #000;
            text-transform: uppercase;
        }
        table th.num { text-align: right; }
        table td {
            font-size: 13px;
            padding: 2px 0;
            vertical-align: top;
        }
        table td.num { text-align: right; }
        table td.item-seq {
            font-size: 11px;
            color: #000;
            width: 16px;
        }

        /* Totais */
        .totais { margin: 4px 0; }
        .totais .row {
            display: flex;
            justify-content: space-between;
            padding: 1px 0;
            font-size: 13px;
        }
        .totais .total-row {
            font-size: 20px;
            font-weight: bold;
            padding: 4px 0;
            letter-spacing: 0.5px;
        }

        /* Pagamento */
        .pagamento { margin: 4px 0; }
        .pagamento .titulo {
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 3px;
            text-transform: uppercase;
        }
        .pagamento .row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
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
            font-size: 11px;
            text-align: center;
            line-height: 1.3;
        }

        /* Footer */
        .footer {
            margin-top: 8px;
            font-size: 11px;
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
    {{-- Manual DANFE NFC-e: nome completo + frase de não aproveitamento de crédito são obrigatórios --}}
    <div style="text-align:center; font-size:10px;">
        Documento Auxiliar da Nota Fiscal de Consumidor Eletrônica<br>
        Não permite aproveitamento de crédito de ICMS
    </div>
    @if(($notaFiscal->ambiente ?? '') === 'homologacao')
        <div style="text-align:center; font-size:9px; font-weight:bold; border:1px dashed #000; margin-top:2px; padding:2px;">
            EMITIDA EM AMBIENTE DE HOMOLOGAÇÃO — SEM VALOR FISCAL
        </div>
    @endif
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
    @if(! $venda->cliente && $venda->cpf_cnpj_nota)
        <div class="venda-info">
            <span>CPF/CNPJ do consumidor: {{ $venda->cpf_cnpj_nota }}</span>
        </div>
    @endif
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

{{-- ===== TRIBUTOS APROXIMADOS (Lei 12.741/2012 — obrigatório no cupom ao consumidor) ===== --}}
@php
    // Mesmo cálculo do payload fiscal: percentual IBPT do item (snapshot → produto),
    // fallback 25% quando o produto não tem o percentual cadastrado.
    $totalTributos = 0.0;
    foreach ($venda->itens as $itemTrib) {
        $percIbpt = (float) ($itemTrib->fiscal('percentual_tributos_ibpt', 25.0) ?: 25.0);
        $totalTributos += round((float) $itemTrib->total * ($percIbpt / 100), 2);
    }
@endphp
@if($totalTributos > 0)
    <hr class="line">
    <div class="row" style="font-size:9px;">
        <span>Tributos Totais Incidentes (Lei 12.741/2012):</span>
        <span>R$ {{ number_format($totalTributos, 2, ',', '.') }}</span>
    </div>
    <div style="font-size:10px;">Valor aproximado. Fonte: IBPT</div>
@endif

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
            'boleto' => 'Boleto',
            'crediario' => 'Crediario',
            'transferencia' => 'Transferencia',
            'vale' => 'Vale',
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

{{-- ===== NFC-e INFO (Manual DANFE NFC-e / NT 2020.006) ===== --}}
@if(isset($notaFiscal) && $notaFiscal)
    <div class="nfce-info">
        {{-- número, série, data/hora de emissão e via — obrigatórios --}}
        <div style="text-align:center;">
            NFC-e nº {{ $notaFiscal->numero ?? '-' }} &nbsp; Série {{ $notaFiscal->serie ?? '-' }} &nbsp;
            {{ ($notaFiscal->emitida_em ?? $venda->created_at)->format('d/m/Y H:i:s') }}
        </div>
        <div style="text-align:center; font-weight:bold;">Via Consumidor</div>

        {{-- consulta por chave de acesso no site da SEFAZ — obrigatório --}}
        @if($notaFiscal->url_consulta ?? null)
            <div style="text-align:center; margin-top:2px;">Consulte pela chave de acesso em</div>
            <div style="text-align:center; word-break:break-all; font-size:10px;">{{ $notaFiscal->url_consulta }}</div>
        @endif
        @if($notaFiscal->chave_acesso ?? null)
            <div style="text-align:center; margin-top:2px;">CHAVE DE ACESSO</div>
            <div style="text-align:center; word-break:break-all; font-size:11px; font-weight:bold;">{{ trim(chunk_split(preg_replace('/^NFe/', '', $notaFiscal->chave_acesso), 4, ' ')) }}</div>
        @endif

        {{-- identificação do consumidor — obrigatória (identificado ou não) --}}
        <hr class="line">
        <div style="text-align:center; font-weight:bold;">CONSUMIDOR</div>
        <div style="text-align:center;">
            @php $docConsumidor = $venda->cliente->cpf_cnpj ?? $venda->cpf_cnpj_nota ?? null; @endphp
            @if($docConsumidor)
                {{ $venda->cliente->nome_razao_social ?? 'CONSUMIDOR' }} — CPF/CNPJ: {{ $docConsumidor }}
            @else
                CONSUMIDOR NÃO IDENTIFICADO
            @endif
        </div>
    </div>

    @if($notaFiscal->qrcode_url ?? null)
        <div style="text-align:center; font-size:9px; margin-top:3px;">Consulta via leitor de QR Code</div>
        <div class="qrcode-area">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($notaFiscal->qrcode_url) }}" alt="QR Code">
        </div>
    @endif

    <div class="nfce-info" style="text-align:center;">
        Protocolo de autorização: {{ $notaFiscal->protocolo ?? '-' }}
        @if($notaFiscal->emitida_em)<br>{{ $notaFiscal->emitida_em->format('d/m/Y H:i:s') }}@endif
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

    <p style="margin-top:6px; font-size:9px;">
        Documento gerado eletronicamente | {{ $venda->created_at->format('d/m/Y H:i:s') }}
    </p>
</div>

@if(request()->boolean('print'))
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
@endif
</body>
</html>
