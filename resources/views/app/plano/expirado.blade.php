<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plano Expirado - ERP Comercial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; }
        .pricing-card { transition: transform 0.2s; border-radius: 1rem; }
        .pricing-card:hover { transform: translateY(-4px); }
        .pricing-card.popular { border: 2px solid #0d6efd; }
    </style>
</head>
<body>
    <div class="container py-5">
        {{-- Warning Header --}}
        <div class="text-center mb-5">
            <div class="mb-3">
                <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 4rem;"></i>
            </div>
            <h2 class="fw-bold">Seu periodo de avaliacao terminou</h2>
            <p class="text-muted fs-5">
                Escolha um plano para continuar utilizando o ERP Comercial.
            </p>
        </div>

        {{-- Plan Cards --}}
        <div class="row g-4 justify-content-center mb-5">
            @foreach($planos as $plano)
                <div class="col-md-4">
                    <div class="card pricing-card shadow-sm h-100 {{ $plano->slug === 'profissional' ? 'popular' : '' }}">
                        @if($plano->slug === 'profissional')
                            <div class="bg-primary text-white text-center py-2 rounded-top" style="border-radius: 1rem 1rem 0 0 !important;">
                                <small class="fw-bold text-uppercase">Mais Popular</small>
                            </div>
                        @endif
                        <div class="card-body text-center p-4">
                            <h4 class="fw-bold">{{ $plano->nome }}</h4>
                            <p class="text-muted small">{{ $plano->descricao }}</p>
                            <div class="my-4">
                                <span class="display-5 fw-bold">R$ {{ number_format($plano->preco_mensal, 0, ',', '.') }}</span>
                                <span class="text-muted">/mes</span>
                            </div>
                            <p class="text-muted small mb-4">
                                ou R$ {{ number_format($plano->preco_anual, 0, ',', '.') }}/ano
                                <span class="badge bg-success">Economize {{ round(100 - ($plano->preco_anual / ($plano->preco_mensal * 12)) * 100) }}%</span>
                            </p>

                            <ul class="list-unstyled text-start mb-4">
                                <li class="mb-2"><i class="bi bi-building me-2 text-primary"></i>{{ $plano->max_unidades >= 999 ? 'Ilimitadas' : $plano->max_unidades }} unidades</li>
                                <li class="mb-2"><i class="bi bi-people me-2 text-primary"></i>{{ $plano->max_usuarios >= 999 ? 'Ilimitados' : $plano->max_usuarios }} usuarios</li>
                                <li class="mb-2"><i class="bi bi-box me-2 text-primary"></i>{{ $plano->max_produtos >= 999999 ? 'Ilimitados' : number_format($plano->max_produtos, 0, ',', '.') }} produtos</li>
                                <li class="mb-2"><i class="bi bi-file-earmark-text me-2 text-primary"></i>{{ $plano->max_notas_mes >= 999999 ? 'Ilimitadas' : number_format($plano->max_notas_mes, 0, ',', '.') }} notas/mes</li>
                            </ul>

                            @php
                                $features = [
                                    'PDV' => $plano->pdv_habilitado,
                                    'Fiscal' => $plano->fiscal_habilitado,
                                    'Multilojas' => $plano->multilojas_habilitado,
                                    'Ordens de Servico' => $plano->os_habilitado,
                                    'Contratos' => $plano->contratos_habilitado,
                                    'Conciliacao' => $plano->conciliacao_habilitada,
                                    'DRE' => $plano->dre_habilitado,
                                    'Boletos' => $plano->boletos_habilitado,
                                    'API' => $plano->api_habilitada,
                                ];
                            @endphp
                            <ul class="list-unstyled text-start">
                                @foreach($features as $label => $enabled)
                                    <li class="mb-1">
                                        @if($enabled)
                                            <i class="bi bi-check-circle-fill text-success me-1"></i> {{ $label }}
                                        @else
                                            <i class="bi bi-x-circle text-muted me-1"></i> <span class="text-muted">{{ $label }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="card-footer bg-transparent border-0 text-center pb-4">
                            <a href="mailto:contato@ia365.com.br?subject=Assinatura {{ $plano->nome }}&body=Empresa: {{ $empresa->razao_social }}"
                               class="btn {{ $plano->slug === 'profissional' ? 'btn-primary' : 'btn-outline-primary' }} btn-lg w-100">
                                Assinar {{ $plano->nome }}
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Contact --}}
        <div class="text-center">
            <p class="text-muted">
                Precisa de ajuda? Entre em contato conosco:
                <a href="mailto:contato@ia365.com.br">contato@ia365.com.br</a>
            </p>
            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-link text-muted">
                    <i class="bi bi-box-arrow-right me-1"></i> Sair
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
