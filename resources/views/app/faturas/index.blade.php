@extends('layouts.app')

@section('title', 'Faturas')

@section('content')
<x-erp.page-header title="Faturas" icon="receipt" subtitle="As faturas do sistema emitidas pela IA365 para {{ $empresa->nome_fantasia ?: $empresa->razao_social }}" />


@if($abertas->isEmpty())
    <div class="alert alert-success d-flex align-items-center gap-2" data-faturas-em-dia>
        <i class="bi bi-check-circle-fill"></i>
        <div><strong>Tudo em dia.</strong> Não há fatura em aberto.</div>
    </div>
@else
    <div class="row g-3 mb-4">
        @foreach($abertas as $f)
            @php $badge = $f->statusBadge(); $dias = $f->diasParaVencer(); @endphp
            <div class="col-lg-6">
                <div class="card shadow-sm h-100 border-{{ $badge['cor'] }}" data-fatura-aberta="{{ $f->id }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <div class="fw-semibold">{{ $f->titulo() }}</div>
                                <div class="small text-muted">
                                    @if($dias < 0) Venceu em {{ $f->vencimento->format('d/m/Y') }}
                                    @elseif($dias === 0) Vence hoje
                                    @else Vence em {{ $f->vencimento->format('d/m/Y') }}
                                    @endif
                                </div>
                            </div>
                            <span class="badge text-bg-{{ $badge['cor'] }}">{{ $badge['label'] }}</span>
                        </div>
                        <div class="fs-3 fw-bold mb-3" data-fatura-valor>R$ {{ number_format((float) $f->valor, 2, ',', '.') }}</div>

                        @if($f->link)
                            <a href="{{ $f->link }}" target="_blank" rel="noopener" class="btn btn-primary w-100 mb-2" data-fatura-link>
                                <i class="bi bi-box-arrow-up-right me-1"></i> Ver fatura: PIX, boleto e nota fiscal
                            </a>
                        @endif
                        <div class="d-flex flex-wrap gap-2">
                            @if($f->pix_copia_cola)
                                <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" data-copiar="{{ $f->pix_copia_cola }}" data-fatura-pix>
                                    <i class="bi bi-qr-code me-1"></i> Copiar o PIX copia e cola
                                </button>
                            @endif
                            @if($f->linha_digitavel)
                                <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" data-copiar="{{ $f->linha_digitavel }}" data-fatura-boleto>
                                    <i class="bi bi-upc me-1"></i> Copiar a linha do boleto
                                </button>
                            @endif
                        </div>
                        @if(! $f->link && ! $f->pix_copia_cola && ! $f->linha_digitavel)
                            <p class="small text-muted mb-0">Para pagar esta fatura, fale com a IA365: <a href="mailto:dcanteli@ia365.com.br">dcanteli@ia365.com.br</a>.</p>
                        @else
                            <p class="small text-muted mb-0 mt-2">A baixa é automática: assim que o banco confirma o pagamento, a fatura some daqui.</p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<x-erp.card title="Faturas pagas" icon="clock-history">
    @if($pagas->isEmpty())
        <p class="text-muted small mb-0">Ainda não há fatura paga registrada aqui.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" data-faturas-pagas>
                <thead><tr><th>Fatura</th><th class="d-none d-sm-table-cell">Vencimento</th><th>Pago em</th><th class="text-end">Valor</th><th></th></tr></thead>
                <tbody>
                @foreach($pagas as $f)
                    <tr>
                        <td>{{ $f->titulo() }}</td>
                        <td class="d-none d-sm-table-cell">{{ $f->vencimento->format('d/m/Y') }}</td>
                        <td>{{ $f->pago_em?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-end">R$ {{ number_format((float) $f->valor, 2, ',', '.') }}</td>
                        <td class="text-end">
                            @if($f->link)
                                <a href="{{ $f->link }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">{{ $f->tem_nota ? 'Recibo e nota' : 'Ver' }}</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-erp.card>

<script>
document.querySelectorAll('[data-copiar]').forEach(function (b) {
    b.addEventListener('click', function () {
        var texto = b.getAttribute('data-copiar'), antes = b.innerHTML;
        var feito = function () { b.innerHTML = '<i class="bi bi-check-lg me-1"></i> Copiado'; setTimeout(function () { b.innerHTML = antes; }, 2000); };
        if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(texto).then(feito, function () { window.prompt('Copie o código:', texto); }); }
        else { window.prompt('Copie o código:', texto); }
    });
});
</script>
@endsection
