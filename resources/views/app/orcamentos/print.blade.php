{{-- Orçamento impresso em A4 (22/10/2026). Layout com tabela/float, sem flex:
     o Chrome não pagina flex na impressão (armadilha 76). Cabeçalho e textos vêm de
     ConfiguracaoLoja::cabecalhoOrcamento() — Configurações da Loja → Impressão do Orçamento. --}}
@php
    $qtd = fn ($q) => rtrim(rtrim(number_format((float) $q, 3, ',', '.'), '0'), ',');
    $brl = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $cli = $orcamento->cliente;
    // Condições do próprio orçamento; em branco, o padrão da loja
    $condicoes = filled($orcamento->condicoes) ? $orcamento->condicoes : $configLoja->orcamento_condicoes;
    $temDescontoItem = $orcamento->itens->contains(fn ($i) => (float) $i->desconto_valor > 0);
    // Cadastro com "-" ou "S/N" no lugar do endereço não pode virar "-, S/N - - -/SP" na folha:
    // campo só de traço/ponto conta como vazio, e sem rua não sai linha de endereço.
    $limpo = fn ($v) => preg_match('/^[\s\-–.]*$/u', (string) $v) ? null : trim((string) $v);
    $enderecoCliente = ($cli && $limpo($cli->logradouro)) ? trim(implode(' - ', array_filter([
        trim(implode(', ', array_filter([$limpo($cli->logradouro), $limpo($cli->numero)]))),
        $limpo($cli->bairro),
        trim(implode('/', array_filter([$limpo($cli->cidade), $limpo($cli->uf)]))),
    ]))) : '';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orçamento {{ $orcamento->numero }} — {{ $cabecalho['nome'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Helvetica Neue", Arial, sans-serif; font-size: 12px; color: #1f2937; background: #e5e7eb; }
        .folha { width: 210mm; min-height: 297mm; margin: 12px auto; background: #fff; padding: 14mm 14mm 12mm; }
        .barra { width: 210mm; margin: 12px auto 0; text-align: right; }
        .barra button { font: inherit; font-size: 13px; padding: 8px 16px; border: 0; border-radius: 6px; background: #111827; color: #fff; cursor: pointer; }

        table { border-collapse: collapse; width: 100%; }
        .cab td { vertical-align: top; }
        .cab .logo { width: 1%; padding-right: 14px; }
        .cab .logo img { max-height: 70px; max-width: 170px; display: block; }
        .cab .empresa h1 { font-size: 19px; letter-spacing: .2px; color: #111827; }
        .cab .empresa .razao { font-size: 10.5px; color: #6b7280; margin-top: 1px; }
        .cab .empresa .linha { font-size: 11px; color: #374151; margin-top: 2px; }
        .cab .contatos { text-align: right; font-size: 11px; color: #374151; line-height: 1.55; white-space: nowrap; }

        .titulo { margin-top: 12px; border-top: 3px solid #111827; }
        .titulo td { padding: 9px 0 8px; vertical-align: bottom; }
        .titulo h2 { font-size: 22px; letter-spacing: 3px; color: #111827; }
        .meta { text-align: right; }
        .meta span { display: inline-block; margin-left: 18px; text-align: left; }
        .meta small { display: block; font-size: 9px; text-transform: uppercase; letter-spacing: .6px; color: #6b7280; }
        .meta b { font-size: 13px; }

        .caixa { border: 1px solid #e5e7eb; border-radius: 6px; padding: 9px 12px; margin-top: 6px; }
        .rotulo { font-size: 9px; text-transform: uppercase; letter-spacing: .6px; color: #6b7280; margin-bottom: 4px; font-weight: bold; }
        .cliente td { padding: 2px 12px 2px 0; vertical-align: top; font-size: 11.5px; }
        .cliente .nome { font-size: 14px; font-weight: bold; color: #111827; }

        .itens { margin-top: 14px; }
        .itens th { background: #111827; color: #fff; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; padding: 7px 8px; text-align: left; }
        .itens td { padding: 7px 8px; border-bottom: 1px solid #e5e7eb; font-size: 11.5px; vertical-align: top; }
        .itens tr:nth-child(even) td { background: #f9fafb; }
        .itens .cod { color: #6b7280; font-size: 10px; }
        .r { text-align: right; } .c { text-align: center; }
        .itens tr { page-break-inside: avoid; }

        .rodape-tab { margin-top: 12px; }
        .rodape-tab > tbody > tr > td { vertical-align: top; }
        .totais { width: 260px; margin-left: auto; }
        .totais td { padding: 4px 0; font-size: 12px; }
        .totais .final td { border-top: 2px solid #111827; padding-top: 7px; font-size: 16px; font-weight: bold; }

        .texto { white-space: pre-line; font-size: 11.5px; line-height: 1.5; }
        .pagamento { width: 260px; margin: 10px 0 0 auto; border: 1px solid #d1d5db; border-radius: 6px; border-collapse: separate; padding: 6px 10px; }
        .pagamento td { padding: 4px 0; font-size: 12px; vertical-align: top; }
        .pagamento td small { display: block; font-size: 10px; color: #6b7280; }
        .pagamento td b { font-size: 13px; }
        .validade { margin-top: 12px; font-size: 11px; color: #374151; }
        .assinatura { margin-top: 40px; width: 60%; margin-left: auto; margin-right: auto; text-align: center; }
        .assinatura div { border-top: 1px solid #374151; padding-top: 5px; font-size: 10.5px; color: #374151; }
        .rodape { margin-top: 22px; border-top: 1px solid #e5e7eb; padding-top: 8px; text-align: center; font-size: 10px; color: #6b7280; white-space: pre-line; }

        @media print {
            body { background: #fff; }
            .barra { display: none; }
            .folha { margin: 0; width: auto; min-height: 0; padding: 0; }
            @page { size: A4; margin: 12mm; }
            .itens th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .itens tr:nth-child(even) td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="barra"><button type="button" onclick="window.print()">Imprimir / Salvar PDF</button></div>
<div class="folha">

    {{-- ===== Cabeçalho da empresa ===== --}}
    <table class="cab">
        <tr>
            @if($cabecalho['logo'])
                <td class="logo"><img src="{{ $cabecalho['logo'] }}" alt="{{ $cabecalho['nome'] }}"></td>
            @endif
            <td class="empresa">
                <h1>{{ $cabecalho['nome'] }}</h1>
                @if($cabecalho['razao'])<div class="razao">{{ $cabecalho['razao'] }}</div>@endif
                @if($cabecalho['cnpj'])<div class="linha">CNPJ {{ $cabecalho['cnpj'] }}</div>@endif
                @foreach($cabecalho['endereco'] as $linha)
                    <div class="linha">{{ $linha }}</div>
                @endforeach
            </td>
            @if($cabecalho['contatos'])
                <td class="contatos">
                    @foreach($cabecalho['contatos'] as $contato)
                        {{ $contato }}<br>
                    @endforeach
                </td>
            @endif
        </tr>
    </table>

    {{-- ===== Título + número/datas ===== --}}
    <table class="titulo">
        <tr>
            <td><h2>ORÇAMENTO</h2></td>
            <td class="meta">
                <span><small>Número</small><b>{{ $orcamento->numero }}</b></span>
                <span><small>Emissão</small><b>{{ $orcamento->created_at->format('d/m/Y') }}</b></span>
                @if($orcamento->validade_ate)
                    <span><small>Válido até</small><b>{{ $orcamento->validade_ate->format('d/m/Y') }}</b></span>
                @endif
                @if($orcamento->vendedor)
                    <span><small>Vendedor</small><b>{{ $orcamento->vendedor->name }}</b></span>
                @endif
            </td>
        </tr>
    </table>

    {{-- ===== Cliente ===== --}}
    <div class="caixa">
        <div class="rotulo">Cliente</div>
        <table class="cliente">
            <tr><td colspan="3" class="nome">{{ $cli->nome_razao_social ?? 'Consumidor' }}</td></tr>
            <tr>
                @if($cli?->cpf_cnpj)<td>{{ strlen(preg_replace('/\D/', '', $cli->cpf_cnpj)) > 11 ? 'CNPJ' : 'CPF' }}: {{ \App\Models\ConfiguracaoLoja::formatarDocumento($cli->cpf_cnpj) }}</td>@endif
                @if($cli?->telefone || $cli?->whatsapp)<td>Tel.: {{ \App\Models\ConfiguracaoLoja::formatarTelefone($cli->whatsapp ?: $cli->telefone) }}</td>@endif
                @if($cli?->email)<td>{{ $cli->email }}</td>@endif
            </tr>
            @if($enderecoCliente)
                <tr><td colspan="3">{{ $enderecoCliente }}</td></tr>
            @endif
        </table>
    </div>

    {{-- ===== Itens ===== --}}
    <table class="itens">
        <thead>
            <tr>
                <th style="width:4%">#</th>
                <th>Produto / serviço</th>
                <th class="c" style="width:8%">Qtd</th>
                <th class="r" style="width:14%">Valor unit.</th>
                @if($temDescontoItem)<th class="r" style="width:12%">Desconto</th>@endif
                <th class="r" style="width:16%">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orcamento->itens as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $item->descricao ?? $item->produto->descricao ?? $item->servico->descricao ?? '-' }}
                        @if($item->produto?->codigo_interno)<div class="cod">Cód. {{ $item->produto->codigo_interno }}</div>@endif
                    </td>
                    <td class="c">{{ $qtd($item->quantidade) }}</td>
                    <td class="r">{{ $brl($item->preco_unitario) }}</td>
                    @if($temDescontoItem)
                        <td class="r">{{ (float) $item->desconto_valor > 0 ? '− ' . $brl($item->desconto_valor) : '—' }}</td>
                    @endif
                    <td class="r"><b>{{ $brl($item->total) }}</b></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ===== Observações + totais ===== --}}
    <table class="rodape-tab">
        <tr>
            <td style="padding-right:18px;">
                @if(filled($orcamento->observacoes_externas))
                    <div class="caixa">
                        <div class="rotulo">Observações</div>
                        <div class="texto">{{ $orcamento->observacoes_externas }}</div>
                    </div>
                @endif
                @if(filled($condicoes))
                    <div class="caixa">
                        <div class="rotulo">Condições</div>
                        <div class="texto">{{ $condicoes }}</div>
                    </div>
                @endif
            </td>
            <td style="width:270px;">
                <table class="totais">
                    <tr><td>Itens</td><td class="r">{{ $orcamento->itens->count() }}</td></tr>
                    <tr><td>Subtotal</td><td class="r">{{ $brl($orcamento->subtotal) }}</td></tr>
                    @if((float) $orcamento->desconto_valor > 0)
                        <tr>
                            <td>Desconto @if($orcamento->desconto_percentual > 0)({{ number_format($orcamento->desconto_percentual, 1, ',', '.') }}%)@endif</td>
                            <td class="r">− {{ $brl($orcamento->desconto_valor) }}</td>
                        </tr>
                    @endif
                    @if((float) $orcamento->frete_valor > 0)
                        <tr><td>Taxa de entrega</td><td class="r">+ {{ $brl($orcamento->frete_valor) }}</td></tr>
                    @endif
                    <tr class="final"><td>TOTAL</td><td class="r">{{ $brl($orcamento->total) }}</td></tr>
                </table>
                {{-- Formas de pagamento (25/10/2026): o cliente vê as duas contas prontas --}}
                @if($pagamento)
                    <table class="pagamento">
                        <tr><td colspan="2" class="rotulo">Formas de pagamento</td></tr>
                        <tr>
                            <td>Dinheiro / PIX @if($pagamento['desconto_percentual'] > 0)<small>{{ rtrim(rtrim(number_format($pagamento['desconto_percentual'], 2, ',', '.'), '0'), ',') }}% de desconto</small>@endif</td>
                            <td class="r"><b>{{ $brl($pagamento['avista']) }}</b></td>
                        </tr>
                        <tr>
                            <td>Cartão de crédito<small>{{ $pagamento['tem_juros'] ? 'com juros' : 'sem juros' }} @if($pagamento['parcelas'] > 1 || $pagamento['tem_juros']) · total {{ $brl($pagamento['cartao_total']) }}@endif</small></td>
                            <td class="r"><b>{{ $pagamento['parcelas'] }}x de {{ $brl($pagamento['valor_parcela']) }}</b></td>
                        </tr>
                    </table>
                @endif
            </td>
        </tr>
    </table>

    @if($orcamento->validade_ate)
        <div class="validade">
            Orçamento válido até <b>{{ $orcamento->validade_ate->format('d/m/Y') }}</b>. Após essa data, preços e disponibilidade podem mudar.
        </div>
    @endif

    <div class="assinatura"><div>De acordo — {{ $cli->nome_razao_social ?? 'Cliente' }}</div></div>

    <div class="rodape">@if(filled($configLoja->orcamento_rodape)){{ $configLoja->orcamento_rodape }}
@endif Emitido em {{ now()->format('d/m/Y H:i') }}</div>
</div>
<script>
    // Mesmo comportamento da OS: abre a janela de impressão sozinho (dali também sai o PDF)
    window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
</script>
</body>
</html>
