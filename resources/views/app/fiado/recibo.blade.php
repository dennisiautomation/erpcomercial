@php
    $unidade = $rec->unidade;
    $empresa = $unidade?->empresa ?? \App\Models\Empresa::find($rec->empresa_id);
    $brl = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Recibo de pagamento nº {{ $rec->numero }}</title>
<style>
    @page { size: 80mm auto; margin: 3mm; }
    * { box-sizing: border-box; }
    body { font-family: 'Courier New', Courier, monospace; font-size: 12px; font-weight: 700; color: #000; width: 74mm; margin: 0 auto; padding: 2mm 0; }
    .center { text-align: center; }
    .titulo { font-size: 15px; text-align: center; margin: 6px 0 2px; }
    .sub { font-size: 10px; text-align: center; }
    hr.line { border: 0; border-top: 2px dashed #000; margin: 6px 0; }
    .row { display: flex; justify-content: space-between; gap: 6px; }
    .total { font-size: 14px; }
    .item { font-size: 11px; }
    .assin { margin-top: 22px; border-top: 1px solid #000; padding-top: 2px; font-size: 10px; text-align: center; }
    @media print { body { width: 74mm; } }
</style>
</head>
<body>
<div class="center">
    <div style="font-size:14px;">{{ $empresa->nome_fantasia ?? $empresa->razao_social ?? '' }}</div>
    <div class="sub">{{ $unidade->nome ?? '' }}@if($unidade?->cnpj || $empresa?->cnpj) · CNPJ {{ $unidade?->cnpj ?: ($empresa->cnpj ?? '') }}@endif</div>
</div>
<hr class="line">
<div class="titulo">RECIBO DE PAGAMENTO</div>
<div class="sub">Nº {{ $rec->numero }} · {{ $rec->created_at->format('d/m/Y H:i') }}</div>
<hr class="line">
<div class="row"><span>Cliente:</span><span>{{ \Illuminate\Support\Str::limit($rec->cliente->nome_razao_social ?? '', 28) }}</span></div>
@if($rec->cliente?->cpf_cnpj)<div class="row"><span>CPF/CNPJ:</span><span>{{ $rec->cliente->cpf_cnpj }}</span></div>@endif
<div class="row"><span>Pago em:</span><span>{{ $rec->formaLabel() }}</span></div>
<hr class="line">
<div class="row"><span>Devia antes:</span><span>{{ $brl($rec->saldo_antes) }}</span></div>
<div class="row total"><span>VALOR PAGO:</span><span>{{ $brl($rec->valor) }}</span></div>
<div class="row total"><span>AINDA DEVE:</span><span>{{ $brl($rec->saldo_depois) }}</span></div>
<hr class="line">
<div class="sub" style="text-align:left;">Abatido de:</div>
@foreach($rec->contas ?? [] as $c)
    <div class="row item">
        <span>{{ \Illuminate\Support\Str::limit($c['descricao'] ?? '', 26) }}@if(!empty($c['vencimento'])) · venc {{ \Carbon\Carbon::parse($c['vencimento'])->format('d/m') }}@endif</span>
        <span>{{ $brl($c['abatido'] ?? 0) }}</span>
    </div>
    @if(($c['restante'] ?? 0) > 0.004)<div class="sub" style="text-align:right;">resta {{ $brl($c['restante']) }}</div>@endif
@endforeach
@if($rec->observacoes)
<hr class="line">
<div class="item">{{ $rec->observacoes }}</div>
@endif
<hr class="line">
<div class="sub">Recebido por {{ $rec->usuario->name ?? '—' }}</div>
<div class="assin">Assinatura da loja</div>
@if($autoPrint ?? false)
<script>window.addEventListener('load', () => window.print());</script>
@endif
</body>
</html>
