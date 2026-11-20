@extends('layouts.app')

@section('title', 'Assinar')

@section('content')
<x-erp.page-header title="Assinar o ERP Comercial" icon="patch-check" subtitle="Escolha como pagar a mensalidade de {{ $empresa->nome_fantasia ?: $empresa->razao_social }}" />

@unless($ligada)
    <div class="alert alert-warning" role="alert" data-assinatura-desligada>
        A cobrança ainda não está ligada. Para assinar agora, fale com a IA365: <a href="mailto:dcanteli@ia365.com.br">dcanteli@ia365.com.br</a>.
    </div>
@endunless

<div class="row g-3">
    <div class="col-lg-5">
        <div class="erp-card h-100" data-assinatura-resumo>
            <div class="card-body">
                <div class="text-muted small">Seu plano</div>
                <div class="fs-4 fw-semibold">{{ $plano?->nome ?? 'Plano' }}</div>
                <div class="my-3">
                    <span class="display-6 fw-bold" data-assinatura-valor>R$ {{ number_format($valor, 2, ',', '.') }}</span>
                    <span class="text-muted">por mês</span>
                </div>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2"><i class="bi bi-calendar-event me-2 text-primary"></i>
                        @if($emTeste)
                            Primeira mensalidade vence em <strong>{{ $vencimento->format('d/m/Y') }}</strong>, no fim do teste grátis. Até lá você não paga nada.
                        @else
                            Seu teste grátis terminou. A primeira mensalidade vence <strong>hoje</strong> e o acesso volta quando o pagamento for confirmado.
                        @endif
                    </li>
                    <li class="mb-2"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Depois, uma mensalidade por mês, no mesmo dia.</li>
                    <li><i class="bi bi-lock me-2 text-primary"></i>
                        @if($tolerancia > 0)
                            Mensalidade com mais de {{ $tolerancia }} {{ $tolerancia === 1 ? 'dia' : 'dias' }} de atraso suspende o acesso até o pagamento.
                        @else
                            Mensalidade em atraso suspende o acesso até o pagamento.
                        @endif
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <form method="POST" action="{{ route('app.assinatura.assinar') }}" class="erp-card h-100 d-flex flex-column" id="formAssinar" data-assinatura-form>
            @csrf
            <div class="card-body">
                <fieldset @disabled(! $ligada)>
                    <legend class="fs-6 fw-semibold mb-3">Como você quer pagar?</legend>

                    <label class="erp-opcao">
                        <input class="form-check-input" type="radio" name="forma" value="pix" @checked(old('forma', 'pix') === 'pix') required>
                        <span>
                            <span class="fw-semibold d-block"><i class="bi bi-qr-code me-1"></i> PIX</span>
                            <span class="small text-muted">Todo mês o código PIX chega aqui no sistema e no e-mail. A baixa é na hora.</span>
                        </span>
                    </label>

                    <label class="erp-opcao">
                        <input class="form-check-input" type="radio" name="forma" value="boleto" @checked(old('forma') === 'boleto')>
                        <span>
                            <span class="fw-semibold d-block"><i class="bi bi-upc me-1"></i> Boleto</span>
                            <span class="small text-muted">Todo mês o boleto chega aqui no sistema e no e-mail. O banco leva até 2 dias úteis para confirmar.</span>
                        </span>
                    </label>

                    @if($cartao)
                        <label class="erp-opcao">
                            <input class="form-check-input" type="radio" name="forma" value="cartao" @checked(old('forma') === 'cartao')>
                            <span>
                                <span class="fw-semibold d-block"><i class="bi bi-credit-card me-1"></i> Cartão de crédito</span>
                                <span class="small text-muted">Você paga agora pela InfinitePay. Nos meses seguintes, o link do cartão chega aqui no sistema e no e-mail: não é débito automático. Se preferir, a mesma fatura pode ser paga por PIX ou boleto.</span>
                            </span>
                        </label>
                    @endif
                </fieldset>
            </div>
            <div class="card-footer bg-transparent d-flex flex-wrap gap-2 justify-content-end mt-auto p-3">
                <a href="{{ route('app.dashboard') }}" class="btn btn-outline-secondary btn-toque">Agora não</a>
                <button type="submit" class="btn btn-primary btn-toque" id="btnAssinar" @disabled(! $ligada) data-assinatura-enviar>Assinar agora</button>
            </div>
        </form>
    </div>
</div>

<script>
// Um toque só: a emissão no banco leva alguns segundos e o segundo toque não pode pedir outra.
document.getElementById('formAssinar').addEventListener('submit', function (e) {
    var b = document.getElementById('btnAssinar');
    if (b.dataset.enviando) { e.preventDefault(); return; }
    b.dataset.enviando = '1';
    b.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Gerando a cobrança…';
    setTimeout(function () { b.disabled = true; }, 0);
});
</script>
@endsection
