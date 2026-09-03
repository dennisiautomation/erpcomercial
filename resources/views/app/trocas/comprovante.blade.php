@php
    $unidade = $devolucao->unidade;
    $empresa = $unidade->empresa ?? $devolucao->venda->empresa ?? null;
    $vale = $devolucao->vale;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Comprovante de {{ $devolucao->tipoLabel() }} #{{ $devolucao->id }}</title>
<style>
    @page { size: 80mm auto; margin: 3mm; }
    * { box-sizing: border-box; }
    body { font-family: 'Courier New', Courier, monospace; font-size: 12px; font-weight: 700; color: #000; width: 74mm; margin: 0 auto; padding: 2mm 0; }
    .center { text-align: center; }
    .titulo { font-size: 15px; text-align: center; margin: 6px 0 2px; }
    .sub { font-size: 10px; text-align: center; }
    hr.line { border: 0; border-top: 2px dashed #000; margin: 6px 0; }
    .row { display: flex; justify-content: space-between; gap: 6px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { text-align: left; padding: 1px 0; vertical-align: top; }
    .num { text-align: right; }
    .total { font-size: 14px; }
    .vale { border: 2px solid #000; padding: 6px; margin: 8px 0; text-align: center; }
    .vale .codigo { font-size: 20px; letter-spacing: 2px; margin: 4px 0; }
    .vale .valor { font-size: 18px; }
    .assin { margin-top: 18px; border-top: 1px solid #000; padding-top: 2px; font-size: 10px; text-align: center; }
    @media print { body { width: 74mm; } }
</style>
</head>
<body>
<div class="center">
    <div style="font-size:14px;">{{ $empresa->nome_fantasia ?? $empresa->razao_social ?? '' }}</div>
    <div class="sub">{{ $unidade->nome ?? '' }}@if($unidade?->cnpj || $empresa?->cnpj) · CNPJ {{ $unidade->cnpj ?: ($empresa->cnpj ?? '') }}@endif</div>
</div>
<hr class="line">
<div class="titulo">COMPROVANTE DE {{ strtoupper($devolucao->tipoLabel()) }}</div>
<div class="sub">Nº {{ $devolucao->id }} · {{ $devolucao->created_at->format('d/m/Y H:i') }}</div>
<hr class="line">
<div class="row"><span>Venda de origem:</span><span>#{{ $devolucao->venda->numero ?? '?' }} ({{ $devolucao->venda?->created_at?->format('d/m/Y') }})</span></div>
@if($devolucao->venda?->cliente)
<div class="row"><span>Cliente:</span><span>{{ \Illuminate\Support\Str::limit($devolucao->venda->cliente->nome_razao_social, 28) }}</span></div>
@endif
<div class="row"><span>Motivo:</span><span>{{ \Illuminate\Support\Str::limit($devolucao->motivo, 30) }}</span></div>
<hr class="line">
<table>
    <thead><tr><th>Item devolvido</th><th class="num">Qtd</th><th class="num">Valor</th></tr></thead>
    <tbody>
    @foreach($devolucao->itens as $item)
        <tr>
            <td>{{ \Illuminate\Support\Str::limit($item->produto->descricao ?? 'Item', 24) }}@if(! $item->retorna_estoque) *@endif</td>
            <td class="num">{{ rtrim(rtrim(number_format($item->quantidade, 3, ',', '.'), '0'), ',') }}</td>
            <td class="num">{{ number_format($item->total, 2, ',', '.') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@if($devolucao->itens->contains(fn ($i) => ! $i->retorna_estoque))<div class="sub" style="text-align:left;">* avariado — não volta ao estoque</div>@endif
<hr class="line">
<div class="row total"><span>VALOR DEVOLVIDO:</span><span>R$ {{ number_format($devolucao->valor_estornado, 2, ',', '.') }}</span></div>
@if($devolucao->valor_abatido_parcelas > 0)
<div class="row"><span>Abatido de parcelas:</span><span>- R$ {{ number_format($devolucao->valor_abatido_parcelas, 2, ',', '.') }}</span></div>
@endif

@if($vale)
<div class="vale">
    <div>CRÉDITO NA LOJA (VALE)</div>
    <div class="codigo">{{ $vale->codigo }}</div>
    <svg id="valeBarcode" style="max-width:100%; height:40px;"></svg>
    <div class="valor">R$ {{ number_format($vale->valor, 2, ',', '.') }}</div>
    <div class="sub">{{ $vale->validade ? 'Válido até ' . $vale->validade->format('d/m/Y') : 'Sem validade' }} · apresente este código no caixa</div>
    @if((float) $vale->saldo !== (float) $vale->valor)<div class="sub">Saldo atual: R$ {{ number_format($vale->saldo, 2, ',', '.') }}</div>@endif
</div>
@elseif($devolucao->forma_sobra === 'dinheiro')
<div class="row total"><span>DEVOLVIDO EM DINHEIRO:</span><span>R$ {{ number_format($devolucao->valor_sobra, 2, ',', '.') }}</span></div>
@elseif($devolucao->forma_sobra === 'parcelas')
<div class="sub">Valor integralmente abatido das parcelas em aberto.</div>
@endif

@if($devolucao->tipo === 'troca' && $devolucao->vendaNova)
<hr class="line">
<div class="row"><span>Venda nova:</span><span>#{{ $devolucao->vendaNova->numero }} · R$ {{ number_format($devolucao->vendaNova->total, 2, ',', '.') }}</span></div>
@endif

<hr class="line">
<div class="sub">Atendido por {{ $devolucao->user->name ?? '' }}@if($devolucao->aprovador) · autorizado por {{ $devolucao->aprovador->name }}@endif</div>
<div class="assin">Assinatura do cliente</div>
<div class="sub" style="margin-top:6px;">Documento sem valor fiscal</div>

@if($vale)
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>try { JsBarcode('#valeBarcode', @json($vale->codigo), { format: 'CODE128', displayValue: false, height: 40, width: 1.5, margin: 0 }); } catch (e) {}</script>
@endif
@if($autoPrint ?? false)
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
@endif
</body>
</html>
