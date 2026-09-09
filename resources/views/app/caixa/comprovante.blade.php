@php
    /**
     * Comprovante de fechamento de caixa — bobina 80 mm (09/09/2026).
     *
     * O que a gaveta conhece são as MOVIMENTAÇÕES (quanto entrou em cada
     * forma). Desconto e acréscimo vêm da VENDA — sem eles o papel não explica
     * por que a soma das formas não bate com a etiqueta do produto.
     *
     * Térmica só imprime preto puro: fonte de traço grosso e ZERO cinza (a
     * lição de 25/07 — cinza vira chuviscado a 203 dpi).
     */
    $unidade = $caixa->unidade;
    $empresa = $unidade->empresa ?? null;
    $labels  = \App\Models\MovimentacaoCaixa::FORMAS_LABELS;

    $fechado = $caixa->status === \App\Enums\StatusCaixa::Fechado;

    // Dinheiro é o único que fecha a gaveta; o resto é conferência.
    $contadoDinheiro  = $fechado ? (float) $caixa->valor_fechamento : null;
    $esperadoDinheiro = $fechado && $caixa->valor_esperado !== null
        ? (float) $caixa->valor_esperado
        : $resumo['esperado_dinheiro'];
    $diferenca = $contadoDinheiro === null ? null : round($contadoDinheiro - $esperadoDinheiro, 2);

    $conferencia = collect($caixa->conferencia ?? [])->except('dinheiro');

    $brl = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fechamento do Caixa {{ $caixa->numero_caixa }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;
        font-size: 13px;
        font-weight: 700;
        width: 80mm;
        margin: 0 auto;
        padding: 4mm 3mm;
        color: #000;
        background: #fff;
        line-height: 1.35;
    }
    .center { text-align: center; }
    .empresa-nome { font-size: 16px; font-weight: 900; text-transform: uppercase; }
    .info-line { font-size: 11px; }
    .titulo { font-size: 15px; font-weight: 900; text-align: center; text-transform: uppercase; margin: 4px 0 2px; }
    hr.line { border: none; border-top: 2px dashed #000; margin: 6px 0; }
    hr.double { border: none; border-top: 3px solid #000; margin: 6px 0; }
    .row { display: flex; justify-content: space-between; gap: 8px; padding: 1px 0; }
    .row .v { text-align: right; white-space: nowrap; }
    .secao { font-size: 12px; font-weight: 900; text-transform: uppercase; margin-top: 6px; }
    .total { font-size: 15px; font-weight: 900; }
    .destaque { border: 2px solid #000; padding: 5px 6px; margin: 6px 0; }
    .obs { font-size: 11px; }
    .assin { margin-top: 22px; border-top: 2px solid #000; padding-top: 2px; font-size: 11px; text-align: center; }
    .rodape { font-size: 10px; text-align: center; margin-top: 8px; }
    .no-print { text-align: center; margin-top: 14px; }
    .no-print a, .no-print button {
        font: inherit; font-size: 12px; padding: 8px 14px; margin: 0 3px;
        border: 2px solid #000; background: #fff; color: #000;
        border-radius: 6px; text-decoration: none; cursor: pointer; display: inline-block;
    }
    @media print {
        * { color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        html, body { width: 80mm; margin: 0; padding: 2mm; }
        @page { margin: 0; size: 80mm auto; }
        .no-print { display: none !important; }
    }
</style>
</head>
<body>

<div class="center">
    <div class="empresa-nome">{{ $empresa->nome_fantasia ?? $empresa->razao_social ?? 'EMPRESA' }}</div>
    @if($unidade?->nome)<div class="info-line">{{ $unidade->nome }}</div>@endif
    @if($unidade?->cnpj || $empresa?->cnpj)
        <div class="info-line">CNPJ: {{ $unidade->cnpj ?: $empresa->cnpj }}</div>
    @endif
</div>

<hr class="line">
<div class="titulo">Fechamento de Caixa</div>
<hr class="line">

<div class="row"><span>Caixa nº</span><span class="v">{{ $caixa->numero_caixa }}</span></div>
<div class="row"><span>Operador</span><span class="v">{{ \Illuminate\Support\Str::limit($operadorNome ?? '—', 22) }}</span></div>
<div class="row"><span>Abertura</span><span class="v">{{ $caixa->aberto_em?->format('d/m/Y H:i') }}</span></div>
<div class="row"><span>Fechamento</span><span class="v">{{ $caixa->fechado_em?->format('d/m/Y H:i') ?? '— em aberto —' }}</span></div>
<div class="row"><span>Vendas no caixa</span><span class="v">{{ $resumo['qtd_vendas'] }}</span></div>

<hr class="line">
<div class="secao">Vendas por forma de pagamento</div>
@forelse($resumo['vendas_por_forma'] as $forma => $valor)
    <div class="row">
        <span>{{ $labels[$forma] ?? ucfirst(str_replace('_', ' ', $forma)) }}</span>
        <span class="v">{{ $brl($valor) }}</span>
    </div>
@empty
    <div class="row"><span>Nenhuma venda neste caixa</span><span class="v">{{ $brl(0) }}</span></div>
@endforelse
<hr class="line">
<div class="row total"><span>TOTAL VENDIDO</span><span class="v">{{ $brl($resumo['vendas']) }}</span></div>

@if($resumo['descontos'] > 0 || $resumo['acrescimos'] > 0)
    <hr class="line">
    <div class="secao">Descontos e acréscimos</div>
    @if($resumo['descontos'] > 0)
        <div class="row"><span>Descontos concedidos</span><span class="v">- {{ $brl($resumo['descontos']) }}</span></div>
    @endif
    @if($resumo['acrescimos'] > 0)
        {{-- Juros de parcelamento e acréscimo de cartão cobrado por parte --}}
        <div class="row"><span>Acréscimos (cartão/juros)</span><span class="v">+ {{ $brl($resumo['acrescimos']) }}</span></div>
    @endif
    <div class="obs">Já embutidos nos valores acima.</div>
@endif

<hr class="line">
<div class="secao">Movimentos da gaveta</div>
<div class="row"><span>Abertura (troco inicial)</span><span class="v">{{ $brl($resumo['abertura']) }}</span></div>
<div class="row"><span>Vendas em dinheiro</span><span class="v">+ {{ $brl($resumo['vendas_dinheiro']) }}</span></div>
<div class="row"><span>Suprimentos</span><span class="v">+ {{ $brl($resumo['suprimentos']) }}</span></div>
<div class="row"><span>Sangrias</span><span class="v">- {{ $brl($resumo['sangrias']) }}</span></div>
@if($resumo['devolucoes'] > 0)
    <div class="row"><span>Devoluções (trocas)</span><span class="v">- {{ $brl($resumo['devolucoes']) }}</span></div>
@endif

<hr class="double">
<div class="destaque">
    <div class="row total"><span>ESPERADO EM DINHEIRO</span><span class="v">{{ $brl($esperadoDinheiro) }}</span></div>
    @if($contadoDinheiro !== null)
        <div class="row total"><span>CONTADO NA GAVETA</span><span class="v">{{ $brl($contadoDinheiro) }}</span></div>
        <hr class="line">
        <div class="row total">
            <span>{{ $diferenca > 0.019 ? 'SOBRA' : ($diferenca < -0.019 ? 'FALTA' : 'RESULTADO') }}</span>
            <span class="v">
                @if(abs($diferenca) < 0.02)
                    CONFERE
                @else
                    {{ $diferenca > 0 ? '+ ' : '- ' }}{{ $brl(abs($diferenca)) }}
                @endif
            </span>
        </div>
    @endif
</div>
<div class="obs">PIX e cartão não entram na gaveta — só conferência.</div>

@if($conferencia->isNotEmpty())
    <hr class="line">
    <div class="secao">Conferência das demais formas</div>
    @foreach($conferencia as $forma => $c)
        @php $diff = round((float) ($c['diferenca'] ?? 0), 2); @endphp
        <div class="row">
            <span>{{ $labels[$forma] ?? ucfirst(str_replace('_', ' ', $forma)) }}</span>
            <span class="v">{{ $brl($c['contado'] ?? 0) }} / {{ $brl($c['esperado'] ?? 0) }}</span>
        </div>
        @if(abs($diff) >= 0.02)
            <div class="row obs"><span>diferença</span><span class="v">{{ $diff > 0 ? '+ ' : '- ' }}{{ $brl(abs($diff)) }}</span></div>
        @endif
    @endforeach
    <div class="obs">contado / esperado</div>
@endif

@if($caixa->observacoes)
    <hr class="line">
    <div class="secao">Observações</div>
    <div class="obs">{{ $caixa->observacoes }}</div>
@endif

@unless($fechado)
    <hr class="line">
    <div class="destaque center">CAIXA AINDA ABERTO — VALORES PARCIAIS</div>
@endunless

<hr class="line">
<div class="rodape">
    Emitido em {{ now()->format('d/m/Y H:i') }} por {{ \Illuminate\Support\Str::limit(auth()->user()->name ?? '-', 22) }}
</div>
<div class="assin">Conferido por (assinatura)</div>
<div class="rodape">Documento sem valor fiscal</div>

<div class="no-print">
    <button type="button" onclick="window.print()">Imprimir</button>
    {{-- Vendedor em modo PDV não tem o extrato: para ele só existe o PDV. --}}
    @if(\App\Http\Middleware\CheckPermission::modoPdv(auth()->user()))
        <a href="{{ route('app.pdv.index') }}">Voltar ao PDV</a>
    @else
        <a href="{{ route('app.caixa.show', $caixa) }}">Ver extrato</a>
        <a href="{{ route('app.caixa.index') }}">Caixas</a>
    @endif
</div>

@if($autoPrint ?? false)
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script>
@endif
</body>
</html>
