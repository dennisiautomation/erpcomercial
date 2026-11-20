<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Suspenso - ERP Comercial</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    @include('auth.partials.bloqueio-estilo')
</head>
<body>
    <div class="wrapper">
        <div class="card-suspenso">
            <div class="icone"><i class="bi bi-lock-fill"></i></div>
            <h1>Acesso temporariamente suspenso</h1>
            <p class="sub">
                O acesso da empresa <strong>{{ $empresa->nome_fantasia ?: $empresa->razao_social }}</strong>
                ao ERP Comercial está suspenso por pendência financeira junto à IA365.
            </p>

            @php $podeVerFat = \App\Services\Plataforma\FaturasPlataforma::podeVer(auth()->user()); @endphp
            @if($podeVerFat && $faturas->isNotEmpty())
                <div class="faturas">
                    <div class="rotulo">Pendências em aberto</div>
                    @foreach($faturas as $fatura)
                        <div class="item">
                            <div>{{ $fatura->descricao ?: 'Mensalidade ' . $fatura->competencia }}</div>
                            <div class="valor">
                                <strong>R$ {{ number_format($fatura->valor, 2, ',', '.') }}</strong>
                                <small>{{ $fatura->isVencida() ? 'venceu em' : 'vence em' }} {{ $fatura->vencimento->format('d/m/Y') }}</small>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="acoes">
                    @php $comLink = $faturas->firstWhere('link', '!=', null); @endphp
                    @if($comLink)
                        <a href="{{ $comLink->link }}" target="_blank" rel="noopener" class="btn-bloqueio" data-pagar-fatura>
                            <i class="bi bi-box-arrow-up-right"></i> Pagar agora por PIX ou boleto
                        </a>
                    @endif
                    @if($empresa->veioDoSite())
                        <a href="{{ route('app.faturas.index') }}" class="{{ $comLink ? 'btn-bloqueio-sec' : 'btn-bloqueio' }}" data-suspenso-faturas>
                            <i class="bi bi-receipt"></i> Ver as faturas: cartão, boleto e PIX
                        </a>
                    @endif
                </div>
            @elseif(! $podeVerFat)
                <p class="sub">Avise o responsável pela loja.</p>
            @endif

            <p class="contato">
                O acesso volta sozinho assim que o pagamento é confirmado. Dúvidas, fale com a IA365:<br>
                <a href="mailto:dcanteli@ia365.com.br">dcanteli@ia365.com.br</a>
            </p>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-sair"><i class="bi bi-box-arrow-right me-1"></i> Sair</button>
            </form>
        </div>
    </div>
</body>
</html>
