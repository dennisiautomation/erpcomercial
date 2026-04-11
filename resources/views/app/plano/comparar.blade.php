@extends('layouts.app')

@section('title', 'Comparar Planos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Comparar Planos</h4>
    <a href="{{ route('app.plano.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

{{-- Monthly/Annual Toggle --}}
<div class="text-center mb-4">
    <div class="btn-group" role="group" id="billingToggle">
        <button type="button" class="btn btn-outline-primary active" data-billing="mensal" onclick="toggleBilling('mensal')">Mensal</button>
        <button type="button" class="btn btn-outline-primary" data-billing="anual" onclick="toggleBilling('anual')">Anual <span class="badge bg-success ms-1">Economize</span></button>
    </div>
</div>

{{-- Pricing Cards --}}
<div class="row g-4 justify-content-center mb-5">
    @foreach($planos as $plano)
        @php
            $isCurrent = $planoAtual && $planoAtual->id === $plano->id;
            $isPopular = $plano->slug === 'profissional';
        @endphp
        <div class="col-md-4">
            <div class="card shadow-sm h-100 {{ $isPopular ? 'border-primary border-2' : '' }}" style="border-radius: 1rem;">
                @if($isPopular)
                    <div class="bg-primary text-white text-center py-2" style="border-radius: 0.9rem 0.9rem 0 0;">
                        <small class="fw-bold text-uppercase">Mais Popular</small>
                    </div>
                @endif
                <div class="card-body text-center p-4">
                    <h4 class="fw-bold">{{ $plano->nome }}</h4>
                    @if($isCurrent)
                        <span class="badge bg-info mb-2">Plano Atual</span>
                    @endif
                    <p class="text-muted small">{{ $plano->descricao }}</p>

                    <div class="my-4">
                        <div class="preco-mensal" style="{{ false ? 'display:none' : '' }}">
                            <span class="display-5 fw-bold">R$ {{ number_format($plano->preco_mensal, 0, ',', '.') }}</span>
                            <span class="text-muted">/mes</span>
                        </div>
                        <div class="preco-anual" style="display:none">
                            <span class="display-5 fw-bold">R$ {{ number_format($plano->preco_anual / 12, 0, ',', '.') }}</span>
                            <span class="text-muted">/mes</span>
                            <div class="text-muted small mt-1">R$ {{ number_format($plano->preco_anual, 0, ',', '.') }} por ano</div>
                        </div>
                    </div>

                    @if($plano->preco_mensal > 0)
                        <p class="text-success small fw-semibold preco-anual" style="display:none">
                            Economia de R$ {{ number_format(($plano->preco_mensal * 12) - $plano->preco_anual, 0, ',', '.') }}/ano
                        </p>
                    @endif
                </div>
                <div class="card-footer bg-transparent border-0 text-center pb-4">
                    @if($isCurrent)
                        <button class="btn btn-secondary btn-lg w-100" disabled>Plano Atual</button>
                    @else
                        <a href="mailto:contato@ia365.com.br?subject=Upgrade para {{ $plano->nome }}"
                           class="btn {{ $isPopular ? 'btn-primary' : 'btn-outline-primary' }} btn-lg w-100">
                            {{ $planoAtual && $plano->ordem > $planoAtual->ordem ? 'Fazer Upgrade' : 'Selecionar' }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- Comparison Table --}}
<div class="card shadow-sm">
    <div class="card-header"><h6 class="mb-0">Comparacao Detalhada</h6></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Recurso</th>
                    @foreach($planos as $plano)
                        <th class="text-center {{ $planoAtual && $planoAtual->id === $plano->id ? 'table-primary' : '' }}">
                            {{ $plano->nome }}
                            @if($planoAtual && $planoAtual->id === $plano->id)
                                <br><span class="badge bg-info">Atual</span>
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                {{-- Limits --}}
                <tr class="table-light"><td colspan="{{ $planos->count() + 1 }}" class="fw-bold">Limites</td></tr>
                <tr>
                    <td>Unidades / Filiais</td>
                    @foreach($planos as $plano)
                        <td class="text-center {{ $planoAtual && $planoAtual->id === $plano->id ? 'table-primary' : '' }}">
                            {{ $plano->max_unidades >= 999 ? 'Ilimitadas' : $plano->max_unidades }}
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td>Usuarios</td>
                    @foreach($planos as $plano)
                        <td class="text-center {{ $planoAtual && $planoAtual->id === $plano->id ? 'table-primary' : '' }}">
                            {{ $plano->max_usuarios >= 999 ? 'Ilimitados' : $plano->max_usuarios }}
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td>Produtos</td>
                    @foreach($planos as $plano)
                        <td class="text-center {{ $planoAtual && $planoAtual->id === $plano->id ? 'table-primary' : '' }}">
                            {{ $plano->max_produtos >= 999999 ? 'Ilimitados' : number_format($plano->max_produtos, 0, ',', '.') }}
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td>Notas Fiscais / mes</td>
                    @foreach($planos as $plano)
                        <td class="text-center {{ $planoAtual && $planoAtual->id === $plano->id ? 'table-primary' : '' }}">
                            {{ $plano->max_notas_mes >= 999999 ? 'Ilimitadas' : number_format($plano->max_notas_mes, 0, ',', '.') }}
                        </td>
                    @endforeach
                </tr>
                <tr>
                    <td>Dias de Trial</td>
                    @foreach($planos as $plano)
                        <td class="text-center {{ $planoAtual && $planoAtual->id === $plano->id ? 'table-primary' : '' }}">
                            {{ $plano->dias_trial }} dias
                        </td>
                    @endforeach
                </tr>

                {{-- Features --}}
                <tr class="table-light"><td colspan="{{ $planos->count() + 1 }}" class="fw-bold">Funcionalidades</td></tr>
                @php
                    $featureRows = [
                        'PDV'                   => 'pdv_habilitado',
                        'Fiscal (NF-e/NFC-e/NFS-e)' => 'fiscal_habilitado',
                        'Multilojas'            => 'multilojas_habilitado',
                        'Ordens de Servico'     => 'os_habilitado',
                        'Contratos / Recorrencia' => 'contratos_habilitado',
                        'Conciliacao Bancaria'  => 'conciliacao_habilitada',
                        'DRE'                   => 'dre_habilitado',
                        'Boletos'               => 'boletos_habilitado',
                        'API Externa'           => 'api_habilitada',
                    ];
                @endphp
                @foreach($featureRows as $label => $field)
                    <tr>
                        <td>{{ $label }}</td>
                        @foreach($planos as $plano)
                            <td class="text-center {{ $planoAtual && $planoAtual->id === $plano->id ? 'table-primary' : '' }}">
                                @if($plano->$field)
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                @else
                                    <i class="bi bi-x-circle text-muted"></i>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function toggleBilling(type) {
        document.querySelectorAll('#billingToggle .btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.billing === type);
        });
        document.querySelectorAll('.preco-mensal').forEach(el => {
            el.style.display = type === 'mensal' ? '' : 'none';
        });
        document.querySelectorAll('.preco-anual').forEach(el => {
            el.style.display = type === 'anual' ? '' : 'none';
        });
    }
</script>
@endpush
