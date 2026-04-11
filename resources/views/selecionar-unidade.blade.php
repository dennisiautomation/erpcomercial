<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selecionar Unidade - ERP Comercial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: #f1f5f9;
            min-height: 100vh;
        }

        .unidade-card {
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .unidade-card:hover {
            border-color: #1e293b;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .unidade-card .icon-wrapper {
            width: 48px;
            height: 48px;
            background: #f1f5f9;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .unidade-card:hover .icon-wrapper {
            background: #1e293b;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                {{-- Header --}}
                <div class="text-center mb-4">
                    <i class="bi bi-box-seam fs-1 text-dark"></i>
                    <h3 class="fw-bold mt-2">Selecionar Unidade</h3>
                    <p class="text-muted">
                        Ola, {{ auth()->user()->name }}! Escolha a unidade para acessar o sistema.
                    </p>
                </div>

                @if($errors->any())
                    <div class="alert alert-danger">
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                @if($unidades->isEmpty())
                    <div class="alert alert-warning text-center">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Nenhuma unidade disponivel. Contate o administrador.
                    </div>
                @else
                    <div class="row g-3">
                        @foreach($unidades as $unidade)
                            <div class="col-md-6">
                                <form method="POST" action="{{ route('selecionar-unidade.store') }}">
                                    @csrf
                                    <input type="hidden" name="unidade_id" value="{{ $unidade->id }}">
                                    <button type="submit" class="unidade-card card w-100 text-start border bg-white p-0">
                                        <div class="card-body d-flex align-items-start gap-3">
                                            <div class="icon-wrapper flex-shrink-0">
                                                <i class="bi bi-building fs-5"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="fw-bold mb-1">{{ $unidade->nome }}</h6>
                                                @if($unidade->cidade && $unidade->uf)
                                                    <div class="text-muted small">
                                                        <i class="bi bi-geo-alt me-1"></i>
                                                        {{ $unidade->cidade }}/{{ $unidade->uf }}
                                                    </div>
                                                @endif
                                                @if($unidade->telefone)
                                                    <div class="text-muted small">
                                                        <i class="bi bi-telephone me-1"></i>
                                                        {{ $unidade->telefone }}
                                                    </div>
                                                @endif
                                                @if($unidade->relationLoaded('empresa') && $unidade->empresa)
                                                    <div class="text-muted small mt-1">
                                                        <i class="bi bi-briefcase me-1"></i>
                                                        {{ $unidade->empresa->nome_fantasia ?? $unidade->empresa->razao_social }}
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="flex-shrink-0 align-self-center">
                                                <i class="bi bi-chevron-right text-muted"></i>
                                            </div>
                                        </div>
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Logout link --}}
                <div class="text-center mt-4">
                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-link text-muted text-decoration-none small">
                            <i class="bi bi-box-arrow-left me-1"></i> Sair da conta
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
