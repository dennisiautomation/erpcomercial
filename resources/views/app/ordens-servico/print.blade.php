<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OS #{{ $ordemServico->numero }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { font-size: 18px; margin-bottom: 4px; }
        .header p { font-size: 11px; color: #666; }
        .os-info { display: flex; justify-content: space-between; margin-bottom: 15px; padding: 8px; background: #f5f5f5; border-radius: 4px; }
        .os-info div { text-align: center; }
        .os-info .label { font-size: 10px; color: #666; text-transform: uppercase; }
        .os-info .value { font-size: 14px; font-weight: bold; }
        .section { margin-bottom: 15px; }
        .section-title { font-size: 12px; font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #ccc; padding-bottom: 3px; margin-bottom: 8px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
        .info-item .label { font-size: 10px; color: #666; text-transform: uppercase; }
        .info-item .value { font-size: 12px; }
        .full-width { grid-column: 1 / -1; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th, td { border: 1px solid #ddd; padding: 5px 8px; text-align: left; font-size: 11px; }
        th { background: #f0f0f0; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totals { margin-top: 10px; text-align: right; }
        .totals .line { display: flex; justify-content: flex-end; gap: 20px; margin-bottom: 3px; }
        .totals .total-final { font-size: 14px; font-weight: bold; border-top: 2px solid #333; padding-top: 5px; }
        .signatures { margin-top: 40px; display: flex; justify-content: space-between; }
        .signature-line { text-align: center; width: 45%; }
        .signature-line .line { border-top: 1px solid #333; padding-top: 5px; margin-top: 40px; font-size: 11px; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
        @media print {
            body { padding: 0; }
            @page { margin: 15mm; }
        }
    </style>
</head>
<body onload="window.print()">
    {{-- Header --}}
    <div class="header">
        <h1>{{ $ordemServico->unidade->nome ?? 'Empresa' }}</h1>
        @if($ordemServico->unidade)
            <p>{{ $ordemServico->unidade->logradouro }}, {{ $ordemServico->unidade->numero }} - {{ $ordemServico->unidade->bairro }} - {{ $ordemServico->unidade->cidade }}/{{ $ordemServico->unidade->uf }}</p>
            <p>Tel: {{ $ordemServico->unidade->telefone }} | CNPJ: {{ $ordemServico->unidade->cnpj }}</p>
        @endif
    </div>

    {{-- OS Info Bar --}}
    <div class="os-info">
        <div>
            <div class="label">OS Numero</div>
            <div class="value">#{{ $ordemServico->numero }}</div>
        </div>
        <div>
            <div class="label">Data Abertura</div>
            <div class="value">{{ $ordemServico->created_at->format('d/m/Y') }}</div>
        </div>
        <div>
            <div class="label">Status</div>
            <div class="value">{{ ucfirst(str_replace('_', ' ', $ordemServico->status)) }}</div>
        </div>
    </div>

    {{-- Client Info --}}
    <div class="section">
        <div class="section-title">Dados do Cliente</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="label">Nome/Razao Social</div>
                <div class="value">{{ $ordemServico->cliente->nome_razao_social ?? '-' }}</div>
            </div>
            <div class="info-item">
                <div class="label">CPF/CNPJ</div>
                <div class="value">{{ $ordemServico->cliente->cpf_cnpj ?? '-' }}</div>
            </div>
            <div class="info-item">
                <div class="label">Telefone</div>
                <div class="value">{{ $ordemServico->cliente->telefone ?? '-' }}</div>
            </div>
            <div class="info-item">
                <div class="label">Email</div>
                <div class="value">{{ $ordemServico->cliente->email ?? '-' }}</div>
            </div>
        </div>
    </div>

    {{-- Equipment & Defect --}}
    <div class="section">
        <div class="section-title">Equipamento e Defeito</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="label">Equipamento</div>
                <div class="value">{{ $ordemServico->equipamento }}</div>
            </div>
            <div class="info-item">
                <div class="label">Tecnico Responsavel</div>
                <div class="value">{{ $ordemServico->tecnico->name ?? '-' }}</div>
            </div>
            <div class="info-item full-width">
                <div class="label">Defeito Relatado</div>
                <div class="value">{{ $ordemServico->defeito_relatado }}</div>
            </div>
        </div>
    </div>

    {{-- Items Table --}}
    @if($ordemServico->itens->count() > 0)
    <div class="section">
        <div class="section-title">Itens</div>
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Descricao</th>
                    <th class="text-center">Qtd</th>
                    <th class="text-right">Preco Unit.</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ordemServico->itens as $item)
                    <tr>
                        <td>{{ ucfirst($item->tipo) }}</td>
                        <td>{{ $item->descricao }}</td>
                        <td class="text-center">{{ number_format($item->quantidade, 2, ',', '.') }}</td>
                        <td class="text-right">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                        <td class="text-right">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Totals --}}
    <div class="totals">
        <div class="line"><span>Produtos:</span> <span>R$ {{ number_format($ordemServico->valor_produtos, 2, ',', '.') }}</span></div>
        <div class="line"><span>Servicos:</span> <span>R$ {{ number_format($ordemServico->valor_servicos, 2, ',', '.') }}</span></div>
        @if($ordemServico->desconto > 0)
            <div class="line"><span>Desconto:</span> <span>- R$ {{ number_format($ordemServico->desconto, 2, ',', '.') }}</span></div>
        @endif
        <div class="line total-final"><span>TOTAL:</span> <span>R$ {{ number_format($ordemServico->total, 2, ',', '.') }}</span></div>
    </div>

    {{-- Laudo --}}
    @if($ordemServico->laudo_tecnico)
    <div class="section" style="margin-top: 15px;">
        <div class="section-title">Laudo Tecnico</div>
        <p>{{ $ordemServico->laudo_tecnico }}</p>
    </div>
    @endif

    {{-- Signatures --}}
    <div class="signatures">
        <div class="signature-line">
            <div class="line">Cliente</div>
        </div>
        <div class="signature-line">
            <div class="line">Tecnico Responsavel</div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        Documento gerado em {{ now()->format('d/m/Y H:i') }} | OS #{{ $ordemServico->numero }}
    </div>
</body>
</html>
