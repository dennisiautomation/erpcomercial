@php $empresa = $vale->unidade->empresa ?? null; @endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Vale {{ $vale->codigo }}</title>
<style>
    @page { size: 80mm auto; margin: 3mm; }
    body { font-family: 'Courier New', Courier, monospace; font-size: 12px; font-weight: 700; color: #000; width: 74mm; margin: 0 auto; }
    .center { text-align: center; }
    .sub { font-size: 10px; text-align: center; }
    hr.line { border: 0; border-top: 2px dashed #000; margin: 6px 0; }
    .vale { border: 2px solid #000; padding: 8px; margin: 8px 0; text-align: center; }
    .vale .codigo { font-size: 20px; letter-spacing: 2px; margin: 4px 0; }
    .vale .valor { font-size: 18px; }
    .row { display: flex; justify-content: space-between; }
</style>
</head>
<body>
<div class="center">
    <div style="font-size:14px;">{{ $empresa->nome_fantasia ?? $empresa->razao_social ?? '' }}</div>
    <div class="sub">{{ $vale->unidade->nome ?? '' }}</div>
</div>
<hr class="line">
<div class="vale">
    <div>CRÉDITO NA LOJA (VALE)</div>
    <div class="codigo">{{ $vale->codigo }}</div>
    <svg id="valeBarcode" style="max-width:100%; height:40px;"></svg>
    <div class="valor">Saldo: R$ {{ number_format($vale->saldo, 2, ',', '.') }}</div>
    <div class="sub">de R$ {{ number_format($vale->valor, 2, ',', '.') }} · {{ $vale->validade ? 'válido até ' . $vale->validade->format('d/m/Y') : 'sem validade' }}</div>
    <div class="sub">Situação: {{ $vale->statusLabel() }}</div>
</div>
@if($vale->cliente)<div class="row"><span>Cliente:</span><span>{{ \Illuminate\Support\Str::limit($vale->cliente->nome_razao_social, 28) }}</span></div>@endif
<div class="row"><span>Emitido:</span><span>{{ $vale->created_at->format('d/m/Y H:i') }}</span></div>
@if($vale->devolucao)<div class="row"><span>Origem:</span><span>{{ $vale->devolucao->tipoLabel() }} da venda #{{ $vale->devolucao->venda->numero ?? '?' }}</span></div>@endif
@if($vale->usos->count())
<hr class="line">
<div class="sub" style="text-align:left;">Utilizações:</div>
@foreach($vale->usos as $uso)
<div class="row" style="font-size:10px;"><span>{{ $uso->created_at->format('d/m/Y') }} {{ $uso->tipo === 'dinheiro' ? 'dinheiro' : 'venda #' . ($uso->venda->numero ?? '?') }}</span><span>- R$ {{ number_format($uso->valor, 2, ',', '.') }}</span></div>
@endforeach
@endif
<hr class="line">
<div class="sub">Apresente este código no caixa. Documento sem valor fiscal.</div>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>try { JsBarcode('#valeBarcode', @json($vale->codigo), { format: 'CODE128', displayValue: false, height: 40, width: 1.5, margin: 0 }); } catch (e) {}</script>
@if($autoPrint ?? false)
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
@endif
</body>
</html>
