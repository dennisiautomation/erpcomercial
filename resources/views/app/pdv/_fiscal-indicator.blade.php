{{-- Fiscal Status Indicator for PDV top bar --}}
@php
    $configFiscal = \App\Models\ConfiguracaoFiscal::where('empresa_id', session('empresa_id'))
        ->where('unidade_id', session('unidade_id'))
        ->first();
@endphp

@if($configFiscal && $configFiscal->emissao_fiscal_ativa && $configFiscal->tipo_cupom_pdv === 'fiscal')
    <span class="badge bg-success-subtle text-success d-inline-flex align-items-center gap-1" title="Emissao fiscal ativa - NFC-e">
        <span class="d-inline-block rounded-circle bg-success" style="width:8px;height:8px"></span>
        NFC-e Ativa
    </span>
@elseif($configFiscal && $configFiscal->emissao_fiscal_ativa)
    <span class="badge bg-secondary-subtle text-secondary d-inline-flex align-items-center gap-1" title="Emissao nao fiscal - Recibo">
        <span class="d-inline-block rounded-circle bg-secondary" style="width:8px;height:8px"></span>
        Recibo
    </span>
@else
    <span class="badge bg-danger-subtle text-danger d-inline-flex align-items-center gap-1" title="Configuracao fiscal nao encontrada ou inativa">
        <span class="d-inline-block rounded-circle bg-danger" style="width:8px;height:8px"></span>
        Sem Config
    </span>
@endif
