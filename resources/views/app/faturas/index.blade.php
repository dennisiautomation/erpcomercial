@extends('layouts.app')

@section('title', 'Faturas')

@section('content')
<x-erp.page-header title="Faturas" icon="receipt" subtitle="As faturas do sistema emitidas pela IA365 para {{ $empresa->nome_fantasia ?: $empresa->razao_social }}" />


@if($podeAssinar)
    <div class="alert alert-info d-flex flex-wrap align-items-center gap-2" data-faturas-assinar>
        <i class="bi bi-hourglass-split"></i>
        <div class="flex-grow-1" style="min-width:12rem"><strong>Você ainda não assinou.</strong> Escolha como pagar a mensalidade: PIX, boleto ou cartão.</div>
        <a href="{{ route('app.assinatura.index') }}" class="btn btn-primary btn-toque flex-shrink-0">Assinar agora</a>
    </div>
@elseif($abertas->isEmpty())
    <div class="alert alert-success d-flex align-items-center gap-2" data-faturas-em-dia>
        <i class="bi bi-check-circle-fill"></i>
        <div><strong>Tudo em dia.</strong> Não há fatura em aberto.</div>
    </div>
@else
    <div class="row g-3 mb-4">
        @foreach($abertas as $f)
            @php $badge = $f->statusBadge(); $dias = $f->diasParaVencer(); @endphp
            <div class="col-lg-6">
                <div class="erp-card h-100 {{ $dias < 0 ? 'border-danger' : '' }}" data-fatura-aberta="{{ $f->id }}">
                    <div class="card-body d-flex flex-column h-100">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div class="min-w-0">
                                <h2 class="h6 fw-semibold mb-1">{{ $f->titulo() }}</h2>
                                <div class="small text-muted">
                                    @if($dias < 0) Venceu em {{ $f->vencimento->format('d/m/Y') }}
                                    @elseif($dias === 0) Vence hoje
                                    @else Vence em {{ $f->vencimento->format('d/m/Y') }}
                                    @endif
                                </div>
                            </div>
                            <span class="badge text-bg-{{ $badge['cor'] }} flex-shrink-0">{{ $badge['label'] }}</span>
                        </div>
                        <div class="fs-3 fw-bold mb-3" data-fatura-valor>R$ {{ number_format((float) $f->valor, 2, ',', '.') }}</div>

                        @if($f->link)
                            <a href="{{ $f->link }}" target="_blank" rel="noopener" class="btn btn-primary btn-toque w-100 mb-2" data-fatura-link>
                                <i class="bi bi-box-arrow-up-right me-2"></i> Ver fatura: PIX, boleto e nota fiscal
                            </a>
                        @endif
                        <div class="erp-acoes" data-fatura-acoes>
                            @if($f->pix_copia_cola)
                                <button type="button" class="btn btn-outline-secondary" data-copiar="{{ $f->pix_copia_cola }}" data-fatura-pix>
                                    <i class="bi bi-qr-code me-2"></i> Copiar PIX
                                </button>
                            @endif
                            @if($f->linha_digitavel)
                                <button type="button" class="btn btn-outline-secondary" data-copiar="{{ $f->linha_digitavel }}" data-fatura-boleto>
                                    <i class="bi bi-upc me-2"></i> Copiar boleto
                                </button>
                            @endif
                            @if($assinaturaSite)
                                @if($cartao)
                                    <form method="POST" action="{{ route('app.faturas.cartao', $f->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary" data-fatura-cartao>
                                            <i class="bi bi-credit-card me-2"></i> Pagar com cartão
                                        </button>
                                    </form>
                                @endif
                                @if($f->doCaixa() && ! $f->linha_digitavel)
                                    <form method="POST" action="{{ route('app.faturas.emitir', $f->id) }}">
                                        @csrf <input type="hidden" name="tipo" value="boleto">
                                        <button type="submit" class="btn btn-outline-secondary" data-fatura-gerar-boleto>
                                            <i class="bi bi-upc me-2"></i> Gerar boleto
                                        </button>
                                    </form>
                                @endif
                                @if($f->doCaixa() && ! $f->pix_copia_cola)
                                    <form method="POST" action="{{ route('app.faturas.emitir', $f->id) }}">
                                        @csrf <input type="hidden" name="tipo" value="pix">
                                        <button type="submit" class="btn btn-outline-secondary" data-fatura-gerar-pix>
                                            <i class="bi bi-qr-code me-2"></i> Gerar PIX
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                        @if(! $f->link && ! $f->pix_copia_cola && ! $f->linha_digitavel)
                            <p class="small text-muted mb-0 mt-3">Para pagar esta fatura, fale com a IA365: <a href="mailto:dcanteli@ia365.com.br">dcanteli@ia365.com.br</a>.</p>
                        @else
                            <p class="small text-muted mb-0 mt-3">A baixa é automática: assim que o pagamento é confirmado, a fatura sai desta lista.</p>
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
        <div data-faturas-pagas>
            @foreach($pagas as $f)
                <div class="erp-linha">
                    <div class="erp-linha-principal">
                        <div class="fw-semibold">{{ $f->titulo() }}</div>
                        <div class="small text-muted">Paga em {{ $f->pago_em?->format('d/m/Y') ?? '—' }} · vencimento {{ $f->vencimento->format('d/m/Y') }}</div>
                    </div>
                    <div class="fw-semibold text-nowrap">R$ {{ number_format((float) $f->valor, 2, ',', '.') }}</div>
                    @if($f->link)
                        <a href="{{ $f->link }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-toque">{{ $f->tem_nota ? 'Recibo e nota' : 'Ver recibo' }}</a>
                    @endif
                </div>
            @endforeach
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
